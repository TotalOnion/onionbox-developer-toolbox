<?php

namespace OnionWordpressDeveloperToolbox\Controllers\Command;

use Laravie\Parser\Xml\Document;
use Laravie\Parser\Xml\Reader;
use OnionWordpressDeveloperToolbox\Exceptions\SitemapException;
use OnionWordpressDeveloperToolbox\Exceptions\WpHttpException;
use OnionWordpressDeveloperToolbox\Models\SitemapEntryModel;
use OnionWordpressDeveloperToolbox\Services\DatabaseService;
use OnionWordpressDeveloperToolbox\Services\HttpService;
use WP_Http;
use WPML\ICLToATEMigration\Data;

class SitemapCommand extends AbstractCommandController
{
    const COMMAND_NAME = 'sitemap';

    private const LOG_AS_GOOD     = 'good';
    private const LOG_AS_WARNING  = 'warning';
    private const LOG_AS_BAD      = 'bad';
    private const LOG_AS_INFO     = 'info';

    protected array $flags = [
        'format'              => AbstractCommandController::OUTPUT_FORMAT_DEFAULT,
        'sitemap'             => 'sitemaps.xml',
        'expected-post-types' => [],
        'verbose'             => false,
        'vverbose'            => false,
    ];

    private array $urls_to_check_against = [];
    private ?DatabaseService $database_service;
    private ?HttpService $http_service;

    /**
     * @inheritDoc
     */
    public function __construct( $pluginName, $version ) {
        $this->database_service = new DatabaseService;
        $this->http_service = new HttpService;
        parent::__construct( $pluginName, $version );
    }

    /**
     * Checks the health of the sitemaps
     * 
     * [--format=<stdout|json>]
     * : The output format of the command. Defaults to stdout
     * 
     * [--sitemap=<root_sitemap_filename>]
     * : Which sitemap file to start at. Defaults to sitemaps.xml
     * 
     * [--expected-post-types=<post-type>...]
     * : Ensure that all posts of <post-type> are in the sitemaps. Pass in a csv for multiple.
     * 
     * [--verbose]
     * : Show passes as well as failures, and extra info in general.
     * 
     * [--vverbose]
     * : Very verbose. Lots of output. Useful for debugging
     */
    public function __invoke( array $args, array $flags )
    {
        $this->load_local_config();
        $this->flags = wp_parse_args( $flags, $this->flags );

        // If you want very verbose, you gotta have verbose too.
        if ( $this->flags['vverbose'] ) {
            $this->flags['verbose'] = true;
        }

        if ( ! $this->validate_flags() ) {
            $this->output( self::OUTPUT_AS_ERROR, 'Invalid flag settings', self::IS_FATAL );
        }

        // are we checking the sitemap against post types?
        if ( $this->flags['expected-post-types'] ) {
            $pages_to_check_against = $this->database_service->get_posts_by_types(
                explode( ',',$this->flags['expected-post-types'] ),
                DatabaseService::EXCLUDE_HIDDEN_MARKETS
            );
            $this->urls_to_check_against = array_map( fn($post) => get_permalink($post), $pages_to_check_against );
        }
        print_r($this->urls_to_check_against);
        die;

        try {
            $urls = $this->fetch_sitemap( $this->http_service->get_base_url() . '/' . $this->flags['sitemap'] );
            foreach ( $urls as $url ) {
                $this->test_url( $url );
            }
        } catch ( SitemapException $e ) {
            $this->output( self::OUTPUT_AS_ERROR, $e->getMessage(), self::IS_FATAL );
        }
    }

    /**
     * Check a single URL. If it's a sitemap, add the URLs in it to the list to check.
     */
    private function test_url( SitemapEntryModel $sitemap_entry ):void {
        $response = $this->http_service->get( $sitemap_entry->loc );

        if ( is_wp_error( $response ) ) {
            $this->log(
                $sitemap_entry->loc,
                self::LOG_AS_BAD,
                sprintf( 'Failed to load URL. Error "%s"', $response->get_error_message() )
            );
            return;
        }

        if ( ( $response['response']['code'] ?? '' ) !== WP_Http::OK ) {
            $this->log(
                $sitemap_entry->loc,
                self::LOG_AS_BAD,
                sprintf( 'Non 200 response code. Received %d', $response['response']['code'] )
            );
            return;
        }

        $this->log( $sitemap_entry->loc, self::LOG_AS_GOOD );

        // If it's a sitemap, recurse
        if ( preg_match( '/text\/xml/', $response['headers']['content-type'] ?? '') ) {
            $urls = $this->fetch_sitemap( $sitemap_entry->loc );
            foreach ( $urls as $url ) {
                $this->test_url( $url );
            }
        }
    }

    /**
     * Fetch a single sitemap file and return an array of URLs in it
     * 
     * @param string $url Full URL to fetch
     * @return array $urls Array of URLs in the sitemap
     * @throws SitemapException
     */
    private function fetch_sitemap( string $url ):array {
        $response = $this->http_service->get( $url );

        if ( is_wp_error( $response ) ) {
            throw new WpHttpException( sprintf( 'Failed to load sitemap "%s".', $url ) );
        }

        if ( ( $response['response']['code'] ?? '' ) !== WP_Http::OK ) {
            throw new WpHttpException(
                sprintf(
                    'Failed to load sitemap "%s". Received response code %s',
                    $url,
                    $response['response']['code'] ?? ''
                )
            );
        }

        if ( ! preg_match( '/text\/xml/', $response['headers']['content-type'] ?? '') ) {
            throw new SitemapException(
                sprintf(
                    'Invalid mime type from %s. Received "%s".',
                    $url,
                    $response['headers']['content-type'] ?? 'Unknown'
                )
            );
        }

        $xml = (new Reader(new Document()))->extract( $response['body'] );

        // Check for "sitemap" entries (root sitemap)
        $parsed = $xml->parse(['urls' => ['uses' => 'sitemap[loc,lastmod]']]);

        // otherwise, check for urls
        if ( ! $parsed['urls'] ) {
            $parsed = $xml->parse(['urls' => ['uses' => 'url[loc,lastmod]']]);
        }

        $urls = [];
        foreach( $parsed['urls'] ?? [] as $parsedUrl ) {
            $urls[] = new SitemapEntryModel(
                $parsedUrl['loc'],
                $parsedUrl['lastmod'] ? new \DateTimeImmutable( $parsedUrl['lastmod'] ) : null
            );
        }

        return $urls;
    }

    /**
     * Log info to the appropriate output
     * 
     * @param string $path The absolute path this log entry relates to
     * @param string $log_as Enum of 'good', 'warning', 'bad', 'info'
     * @param string $reason An optional message to give further context
     */
    private function log( string $url, string $log_as, string $reason = '' ):void
    {
        switch ( $log_as ) {
            case self::LOG_AS_INFO:
                if ( $this->flags['verbose'] ) {
                    $this->output(
                        self::OUTPUT_AS_LOG,
                        sprintf( '%s: Info: %s', $url, $reason )
                    );
                }
                break;

            case self::LOG_AS_GOOD:
                $this->json_output['stats']['good']++;
                if ( $this->flags['verbose'] ) {
                    $this->output(
                        self::OUTPUT_AS_LOG,
                        sprintf( '%s: passed.', $url )
                    );
                }
                break;

            case self::LOG_AS_WARNING:
                $this->json_output['stats']['warning']++;
                $this->output(
                    self::OUTPUT_AS_WARNING,
                    sprintf( '%s: warning: %s', $url, $reason )
                );
                break;

            case self::LOG_AS_BAD:
            default:
                $this->json_output['stats']['bad']++;
                $this->output(
                    self::OUTPUT_AS_ERROR,
                    sprintf( '%s: has errors: %s', $url, $reason )
                );
                break;
        }
    }
}
