<?php

namespace OnionWordpressDeveloperToolbox\Controllers\Command;

use ML\JsonLD\Exception\JsonLdException;
use ML\JsonLD\JsonLD;
use OnionWordpressDeveloperToolbox\Exceptions\WpDatabaseException;
use OnionWordpressDeveloperToolbox\Exceptions\LdJsonException;
use OnionWordpressDeveloperToolbox\Exceptions\WpHttpException;
use OnionWordpressDeveloperToolbox\Services\DatabaseService;
use OnionWordpressDeveloperToolbox\Services\HttpService;
use OnionWordpressDeveloperToolbox\Validators\LdJson\LdJsonValidatorFactory;
use WP_CLI;
use WP_Http;
use WP_Post;

class LdJsonCommand extends AbstractCommandController
{
    const COMMAND_NAME = 'ldjson';

    private const LOG_AS_GOOD    = 'good';
    private const LOG_AS_WARNING = 'warning';
    private const LOG_AS_BAD     = 'bad';
    private const LOG_AS_INFO    = 'info';

    private ?DatabaseService $database_service;
    private ?HttpService $http_service;
    private ?LdJsonValidatorFactory $ld_json_validator_factory;
    private array $flags = [];

    /**
     * @inheritDoc
     */
    public function __construct( $pluginName, $version ) {
        $this->database_service = new DatabaseService;
        $this->http_service = new HttpService;
        $this->ld_json_validator_factory = new LdJsonValidatorFactory;
        parent::__construct( $pluginName, $version );
    }

    /**
     * Checks URLs for valid ld+json Structured data
     * 
     * [--target-post-types=<post-type>...]
     * : Test all of a post type. Pass in a csv to do multiple
     * 
     * [--target-path=<path>]
     * : Test a specific path
     * 
     * [--target-ids=<ids>...]
     * : Test a specific post ID. Pass in a csv for multiple.
     * 
     * [--follow-links]
     * : Follow things like image links to see if they are resolving correctly
     * 
     * [--verbose]
     * : Show passes as well as failures, and extra info in general.
     * 
     * [--vverbose]
     * : Dump out the ld+json etc. Only use this if you are testing individual posts, or you really like large log files
     */
    public function __invoke( array $args, array $flags )
    {
        $this->flags = wp_parse_args(
            $flags,
            array(
                'target-post-types' => null,
                'target-path'       => null,
                'target-ids'        => null,
                'follow-links'      => false,
                'verbose'           => false,
                'vverbose'          => false,
            )
        );

        // If you want very verbose, you gotta have verbose too.
        if ( $this->flags['vverbose'] ) {
            $this->flags['verbose'] = true;
        }

        // Let's fetch some things to test
        $targets = [];
        try {
            if ( $this->flags['target-path'] ) {
                $targets[] = $this->database_service->get_post_by_url( $this->flags['target-path'] );
            } elseif( $this->flags['target-post-types'] ) {
                $targets = $this->database_service->get_posts_by_types( explode( ',',$this->flags['target-post-types'] ) );
            } elseif( $this->flags['target-ids'] ) {
                $targets = $this->database_service->get_posts_by_ids( explode( ',',$this->flags['target-ids'] ) );
            }
        } catch ( WpDatabaseException $e ) {
            WP_CLI::error( 'Failed to load targets. Error %s', $e->getMessage() );
        } catch ( \Exception $e ) {
            WP_CLI::error( 'Uncaught fatal exception. Error %s', $e->getMessage() );
        }

        if ( ! $targets ) {
            WP_CLI::warning( 'No matching targets found' );
            return;
        }

        WP_CLI::log( sprintf( 'Checking %d targets', count( $targets ) ) );

        // Lets do some testing
        foreach( $targets as $post ) {
            try {
                $this->test_post( $post );
            } catch ( WpHttpException $e ) {
                $this->log(
                    $post,
                    self::LOG_AS_BAD,
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Run ld+json tests against a single URL
     * 
     * @param WP_Post $post
     * @throws WpHttpException
     */
    private function test_post( WP_Post $post ) {
        $url = $this->http_service->get_post_permalink( $post );
        $response = $this->http_service->get( $url );
        if ( is_wp_error( $response ) ) {
            throw new WpHttpException(
                sprintf( 'WP_Error received from "%s". Message "%s".', $url,  $response->get_error_message() )
            );
        }

        if ( $response['response']['code'] !== WP_Http::OK ) {
            throw new WpHttpException(
                sprintf( 'Non 200 response from %s, received %s instead.', $url,  $response['response']['code'] )
            );
        }

        // Extract all matches
        preg_match_all( '/type="application\/ld\+json"[ ]*>([^<]+)/', $response['body'], $matches );
        
        if ( ! $matches ) {
            $this->log( $post, self::LOG_AS_WARNING, 'No ld+json detected' );
            return;
        }

        // Convert snippets to arrays
        $ld_json_snippets = [];
        $this->log( $post, self::LOG_AS_INFO, sprintf( 'Found %d snippets', count( $matches[1] ) ) );
        foreach ( $matches[1] as $snippet ) {
            try {
                $ld_json = $this->ld_json_string_to_array( $snippet );
                // Merge any snippets with identical @id values
                if ( $ld_json_snippets[ $ld_json['@id'] ] ?? false ) {
                    $ld_json_snippets[ $ld_json['@id'] ] = array_replace_recursive(
                        $ld_json_snippets[ $ld_json['@id'] ],
                        $ld_json
                    );
                } else {
                    $ld_json_snippets[ $ld_json['@id'] ] = $ld_json;
                }
            } catch ( LdJsonException $e ) {
                $this->log( $post, self::LOG_AS_BAD, $e->getMessage() );
            }
        }

        if ( count( $matches[1] ) !== count( $ld_json_snippets ) ) {
            $this->log( $post, self::LOG_AS_INFO, sprintf( 'Used @id to merge down to %d', count( $ld_json_snippets ) ) );
        }

        // Convert the arrays to objects to test format - we don't then use the objects
        foreach ( $ld_json_snippets  as $snippet ) {
            try {
                $snippet = JsonLD::getDocument( json_encode( $snippet ) );
            } catch ( JsonLdException $e ) {
                $this->log(
                    $post,
                    self::LOG_AS_BAD,
                    sprintf( 'Failed to parse ld+json into a JsonLD object, error "%s"', $e->getMessage() )
                );
                continue;
            }
        }

        $total_error_count = 0;
        foreach ( $ld_json_snippets as $ld_json_snippet ) {
            $errors = [];
            try {
                $validator = $this->ld_json_validator_factory->instance( $ld_json_snippet );
                $validator->set_flags( $this->flags );
                $errors = $validator->validate();
            } catch ( LdJsonException $e ) {
                $errors[] = $e->getMessage();
            }
            
            if ( $errors ) {
                $this->log(
                    $post,
                    self::LOG_AS_BAD,
                    sprintf( 'ld+json failed validation. Errors: %s.', implode( ', ', $errors ) ),
                    $errors
                );
            }
            $total_error_count += count( $errors );
        }

        if ( $total_error_count ) {
            $this->log( $post, self::LOG_AS_INFO, sprintf( '%d errors across %d snippets discovered.', $total_error_count, count( $ld_json_snippets ) ) );
        } else {
            $this->log( $post, self::LOG_AS_GOOD );
        }
    }

    private function ld_json_string_to_array( string $ld_json_string ): array {
        $ld_json = json_decode( $ld_json_string, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new LdJsonException(
                sprintf( 'Failed to parse ld+json, error "%s"', json_last_error_msg() )
            );
        }

        return $this->remove_empty_array_values_recursively( $ld_json );
    }

    private function remove_empty_array_values_recursively( array $array ): array {
        foreach( array_keys( $array ) as $key ) {
            if ( ! $array[ $key ] ) {
                unset( $array[ $key ] );
                continue;
            }

            if( is_array( $array[ $key ] ) ) {
                $array[ $key ] = $this->remove_empty_array_values_recursively( $array[ $key ] );
            }
        }

        return $array;
    }

    /**
     * Send info to STDOUT about the redirect
     * 
     * @param string $path The absolute path this log entry relates to
     * @param string $log_as Enum of 'good', 'warning', 'bad'
     * @param string $reason An optional message to give further context
     */
    private function log( WP_Post $post, string $log_as, string $reason = '', $error_array = [] ):void
    {
        switch ( $log_as ) {
            case self::LOG_AS_INFO:
                if ( $this->flags['verbose'] ) {
                    WP_CLI::log(
                        sprintf(
                            '%d: Info: %s',
                            $post->ID,
                            $reason
                        )
                    );
                }
                break;

            case self::LOG_AS_GOOD:
                if ( $this->flags['verbose'] ) {
                    WP_CLI::log(
                        sprintf(
                            '%d: passed.',
                            $post->ID
                        )
                    );
                }
                break;

            case self::LOG_AS_WARNING:
                WP_CLI::warning( sprintf(
                    '%d: warning: %s',
                    $post->ID,
                    $reason
                ) );
                break;

            case self::LOG_AS_BAD:
            default:
                WP_CLI::error(
                    sprintf(
                        '%d: has errors: %s',
                        $post->ID,
                        $reason
                    ),
                    false
                );
                break;
        }

        if ( $this->flags['verbose'] && $error_array ) {
            foreach( $error_array as $error ) {
                WP_CLI::log( "\t" . $error );
            }
        }
    }
}
