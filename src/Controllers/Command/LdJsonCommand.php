<?php

namespace OnionWordpressDeveloperToolbox\Controllers\Command;

use \WP_CLI;
use \WP_Http;
use ML\JsonLD\Exception\JsonLdException;
use ML\JsonLD\JsonLD;
use OnionWordpressDeveloperToolbox\Exceptions\WpHttpException;
use OnionWordpressDeveloperToolbox\Exceptions\LdJsonException;
use OnionWordpressDeveloperToolbox\Services\HttpService;
use OnionWordpressDeveloperToolbox\Validators\LdJson\LdJsonValidatorFactory;

class LdJsonCommand extends AbstractCommandController
{
    const COMMAND_NAME = 'ldjson';

    private const LOG_AS_GOOD            = 'good';
    private const LOG_AS_WARNING         = 'warning';
    private const LOG_AS_BAD             = 'bad';

    private ?HttpService $http_service;
    private ?LdJsonValidatorFactory $ld_json_validator_factory;
    private array $flags = [];

    /**
     * @inheritDoc
     */
    public function __construct( $pluginName, $version ) {
        $this->http_service = new HttpService;
        $this->ld_json_validator_factory = new LdJsonValidatorFactory;
        parent::__construct( $pluginName, $version );
    }

    /**
     * Checks URLs for valid ld+json Structured data
     * 
     * [--target-path=<path>]
     * : Test a specific path
     * 
     * [--verbose]
     * : Show passes as well as failures, and extra info in general.
     */
    public function __invoke( array $args, array $flags )
    {
        $this->flags = wp_parse_args(
            $flags,
            array(
                'target-path' => null,
                'verbose'     => false,
            )
        );

        try {
            $this->test_path( $this->flags['target-path'] );
        } catch ( WpHttpException $e ) {
            // just re-throw for now.
            throw $e;
        }

        WP_CLI::success( 'Done' );
    }

    /**
     * Run ld+json tests against a single URL
     * 
     * @param string $path
     * @throws WpHttpException
     */
    private function test_path( string $path ) {
        $response = $this->http_service->get(
            $this->http_service->international_url_sanitize( $this->http_service->get_base_url() . $path )
        );

        if ( $response['response']['code'] !== WP_Http::OK ) {
            throw new WpHttpException(
                sprintf( 'Non 200 response from %s, received %s instead.', $path,  $response['response']['code'] )
            );
        }

        // Extract all matches
        preg_match_all( '/type="application\/ld\+json"[ ]*>([^<]+)/', $response['body'], $matches );

        
        if ( ! $matches ) {
            $this->log( $path, self::LOG_AS_WARNING, 'No ld+json detected' );
            return;
        }

        // Convert snippets to arrays
        $ld_json_snippets = [];
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
                $this->log( $path, self::LOG_AS_BAD, $e->getMessage() );
            }
        }

        // Convert the arrays to objects to test format - we don't then use the objects
        foreach ( $ld_json_snippets  as $snippet ) {
            try {
                $snippet = JsonLD::getDocument( json_encode( $snippet ) );
            } catch ( JsonLdException $e ) {
                $this->log(
                    $path,
                    self::LOG_AS_BAD,
                    sprintf( 'Failed to parse ld+json into a JsonLD object, error "%s"', $e->getMessage() )
                );
                continue;
            }
        }


        foreach ( $ld_json_snippets as $ld_json_snippet ) {
            //$ld_json_snippet['@type'] = 'Gahhhhhh';
            $validator = $this->ld_json_validator_factory->instance( $ld_json_snippet );
            $errors = $validator->validate();
            if ( $errors ) {
                $this->log(
                    $path,
                    self::LOG_AS_BAD,
                    sprintf( 'ld+json failed validation. Errors: %s.', implode( ', ', $errors ) )
                );
            }
        }

        print_r( $ld_json_snippets );
        die;
    }

    private function ld_json_string_to_array( string $ld_json_string ): array {
        $ld_json = json_decode( $ld_json_string, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new LdJsonException(
                sprintf( 'Failed to parse ld+json, error "%s"', json_last_error_msg() )
            );
        }

        return $ld_json;
    }

    /**
     * Send info to STDOUT about the redirect
     * 
     * @param string $path The absolute path this log entry relates to
     * @param string $log_as Enum of 'good', 'warning', 'bad'
     * @param string $reason An optional message to give further context
     */
    private function log( string $path, string $log_as, string $reason = '' ):void
    {
        switch ( $log_as ) {
            case self::LOG_AS_GOOD:
                if ( $this->flags['verbose'] ) {
                    WP_CLI::log(
                        sprintf(
                            '%s: passed.',
                            $path
                        )
                    );
                }
                break;

            case self::LOG_AS_WARNING:
                WP_CLI::warning( sprintf(
                    '%s: warning: %s',
                    $path,
                    $reason
                ) );
                break;

            case self::LOG_AS_BAD:
            default:
                WP_CLI::error(
                    sprintf(
                        '%s: has errors: %s',
                        $path,
                        $reason
                    ),
                    false
                );
                break;
        }
    }
}
