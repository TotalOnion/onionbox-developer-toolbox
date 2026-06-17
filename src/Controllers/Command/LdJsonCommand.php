<?php

namespace OnionWordpressDeveloperToolbox\Controllers\Command;

use \WP_CLI;
use \WP_Http;
use ML\JsonLD\Exception\JsonLdException;
use ML\JsonLD\JsonLD;
use OnionWordpressDeveloperToolbox\Exceptions\WpHttpException;
use OnionWordpressDeveloperToolbox\Services\HttpService;

class LdJsonCommand extends AbstractCommandController
{
    const COMMAND_NAME = 'ldjson';

    private const LOG_AS_GOOD            = 'good';
    private const LOG_AS_WARNING         = 'warning';
    private const LOG_AS_BAD             = 'bad';

    private ?HttpService $http_service;
    private array $flags = [];

    /**
     * @inheritDoc
     */
    public function __construct( $pluginName, $version ) {
        $this->http_service = new HttpService;
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
            $this->test_url( $this->http_service->get_base_url() . $this->flags['target-path'] );
        } catch ( WpHttpException $e ) {
            // just re-throw for now.
            throw $e;
        }

        WP_CLI::success( 'Done' );
    }

    /**
     * Run ld+json tests against a single URL
     * 
     * @param string $url
     * @throws WpHttpException
     */
    private function test_url( string $url ) {
        $response = $this->http_service->get(
            $this->http_service->international_url_sanitize( $url )
        );

        if ( $response['response']['code'] !== WP_Http::OK ) {
            throw new WpHttpException(
                sprintf( 'Non 200 response from %s, received %s instead.', $url,  $response['response']['code'] )
            );
        }

        $ld_json_snippets = [];
        preg_match_all( '/type="application\/ld\+json"[ ]*>([^<]+)/', $response['body'], $matches );

        // Extract all matches, and merge any with identical IDs
        if ( $matches ) {
            foreach ( $matches[1] as $snippet ) {
                $ld_json_snippets[] = JsonLD::getDocument( $snippet );
            }
        }

        print_r( $ld_json_snippets[0] );
        die;
    }

    /**
     * Send info to STDOUT about the redirect
     * 
     * @param array $redirect The array object from the Redirection export that the message is concerning
     * @param string $log_as Enum of 'good', 'warning', 'bad'
     * @param string $reason An optional message to give further context
     */
    private function log( array $path, string $log_as, string $reason = '' ):void
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
