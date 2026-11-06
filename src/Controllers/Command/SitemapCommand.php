<?php

namespace OnionWordpressDeveloperToolbox\Controllers\Command;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use WP_CLI;
use WP_Error;
use OnionWordpressDeveloperToolbox\Models\SitemapEntryModel;
use OnionWordpressDeveloperToolbox\Services\DatabaseService;
use OnionWordpressDeveloperToolbox\Services\HttpService;
use OnionWordpressDeveloperToolbox\Services\RedirectionService;

class SitemapCommand extends AbstractCommandController
{
    const COMMAND_NAME = 'sitemap';

    private const LOG_AS_GOOD     = 'good';
    private const LOG_AS_WARNING  = 'warning';
    private const LOG_AS_BAD      = 'bad';
    private const LOG_AS_INFO     = 'info';

    private const SITEMAP_NAMESPACE   = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    private const MAX_SITEMAP_ENTRIES = 50000;
    private const MAX_SITEMAP_BYTES   = 52428800; // 50MB, the sitemaps.org limit
    private const REQUEST_TIMEOUT     = 30;
    private const MAX_LINK_REDIRECTS  = 5;

    // Helper plugin file => the option it reads its suppressed markets from
    private const SEOPRESS_HELPERS = [
        'onion-seopress-helper/onion-seopress-helper.php' => 'onion_seopress_helper_suppressed_markets',
        'pr-seopress-helper/pr-seopress-helper.php'       => 'pr_seopress_helper_suppressed_markets',
    ];

    protected array $flags = [
        'format'              => AbstractCommandController::OUTPUT_FORMAT_DEFAULT,
        'sitemap'             => 'sitemaps.xml',
        'expected-post-types' => [],
        'check-links'         => false,
        'staggered'           => false,
        'verbose'             => false,
        'vverbose'            => false,
    ];

    // Counts per kind of problem, for the summary and the pipeline
    private array $summary = [
        'sitemaps_checked'          => 0,
        'urls_checked'              => 0,
        'urls_ok'                   => 0,
        'setup'                     => 0,
        'invalid_sitemap'           => 0,
        'foreign_domain'            => 0,
        'hidden_market_listed'      => 0,
        'duplicate'                 => 0,
        'redirect_known'            => 0,
        'redirect_unknown'          => 0,
        'private'                   => 0,
        'not_found'                 => 0,
        'client_error'              => 0,
        'server_error'              => 0,
        'request_error'             => 0,
        'missing_from_sitemap'      => 0,
        'links_checked'             => 0,
        'link_redirected'           => 0,
        'link_broken'               => 0,
    ];

    private array $hidden_markets = [];   // market codes that must not be in the sitemap
    private array $own_hosts = [];        // hosts that count as "this site"
    private array $sitemaps_seen = [];    // sitemap URL => true
    private array $urls_in_sitemaps = []; // normalised page URL => sitemap it was found in
    private array $link_results = [];     // link URL => result of check_link()

    private ?DatabaseService $database_service;
    private ?HttpService $http_service;
    private ?RedirectionService $redirection_service;

    /**
     * @inheritDoc
     */
    public function __construct( $pluginName, $version ) {
        $this->database_service = new DatabaseService;
        $this->http_service = new HttpService;
        $this->redirection_service = new RedirectionService;
        parent::__construct( $pluginName, $version );
    }

    /**
     * Checks the health of the sitemaps: that every sitemap is valid, every URL in them
     * gives a 200, and no hidden or suppressed market is listed. Exits with code 1 if
     * anything is bad, so it can fail a pipeline.
     *
     * 3xx responses are checked against the Redirection plugin. 401/403 means a private
     * page is listed as public, 404 a routing problem and 5xx a server error.
     *
     * [--format=<stdout|json>]
     * : The output format of the command. Defaults to stdout
     *
     * [--sitemap=<root_sitemap_filename>]
     * : Which sitemap file to start at. Defaults to sitemaps.xml
     *
     * [--expected-post-types=<post-type>...]
     * : Ensure that all published, indexable posts of <post-type> are in the sitemaps. Pass in a csv for multiple.
     *
     * [--check-links]
     * : Also load every page and check that its href, src and srcset links on this site's domain give a 200. Slow.
     *
     * [--staggered]
     * : This site uses staggered delivery: fail if no seopress-helper plugin is active.
     *
     * [--verbose]
     * : Show passes as well as failures, and extra info in general.
     *
     * [--vverbose]
     * : Very verbose. Lots of output. Useful for debugging
     */
    public function __invoke( array $args, array $flags )
    {
        // Large sites have thousands of URLs; don't let PHP stop the crawl part way
        @set_time_limit( 0 );

        $this->load_local_config();
        $this->flags = wp_parse_args( $flags, $this->flags );

        // If you want very verbose, you gotta have verbose too.
        if ( $this->flags['vverbose'] ) {
            $this->flags['verbose'] = true;
        }

        if ( ! $this->validate_flags() ) {
            $this->output( self::OUTPUT_AS_ERROR, 'Invalid flag settings', self::IS_FATAL );
        }

        $base_url = $this->http_service->get_base_url();
        $this->own_hosts = array_values( array_unique( array_filter( [
            $this->host_of( $base_url ),
            $this->host_of( home_url() ),
            $this->host_of( (string) get_option( 'siteurl' ) ),
        ] ) ) );

        $this->check_staggered_delivery_setup();

        if ( ! $this->redirection_service->is_available() ) {
            $this->log( $base_url, self::LOG_AS_WARNING, 'setup', 'The Redirection plugin is not active, so redirects in the sitemap cannot be checked against it.' );
        }

        $this->crawl_sitemap( rtrim( $base_url, '/' ) . '/' . ltrim( $this->flags['sitemap'], '/' ) );

        if ( $this->flags['expected-post-types'] ) {
            if ( ltrim( $this->flags['sitemap'], '/' ) === 'sitemaps.xml' ) {
                $this->check_expected_posts();
            } else {
                // Only part of the sitemap was crawled, so every other post would look missing
                $this->log( $base_url, self::LOG_AS_INFO, '', 'Skipped the --expected-post-types check: it needs a run from the root sitemaps.xml.' );
            }
        }

        $this->finish();
    }

    /**
     * Work out which markets must not appear in the sitemap, and check the helper plugin
     * that removes them is active. Mirrors onion-seopress-helper: WPML hidden languages
     * plus the markets suppressed in the helper's settings.
     */
    private function check_staggered_delivery_setup(): void {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $wpml_settings = get_option( 'icl_sitepress_settings' );
        $hidden = (array) ( $wpml_settings['hidden_languages'] ?? [] );

        $suppressed = [];
        $active_helpers = [];
        foreach ( self::SEOPRESS_HELPERS as $plugin_file => $option_name ) {
            $is_active = is_plugin_active( $plugin_file );
            if ( $is_active ) {
                $active_helpers[] = dirname( $plugin_file );
            }

            $markets = array_keys( (array) ( get_option( $option_name ) ?: [] ) );
            if ( ! $markets ) {
                continue;
            }
            $suppressed = array_merge( $suppressed, $markets );

            if ( ! $is_active ) {
                $this->log(
                    home_url(),
                    self::LOG_AS_BAD,
                    'setup',
                    sprintf(
                        'Markets %s are suppressed in the %s settings, but %s is not active, so they are not being suppressed.',
                        implode( ', ', $markets ),
                        dirname( $plugin_file ),
                        dirname( $plugin_file )
                    )
                );
            }
        }

        $this->hidden_markets = array_values( array_unique( array_map( 'strtolower', array_merge( $hidden, $suppressed ) ) ) );

        if ( $this->flags['staggered'] ) {
            if ( ! $active_helpers ) {
                $this->log( home_url(), self::LOG_AS_BAD, 'setup', 'Staggered delivery site, but neither onion-seopress-helper nor pr-seopress-helper is active.' );
            } elseif ( ! $suppressed ) {
                $this->log( home_url(), self::LOG_AS_WARNING, 'setup', 'Staggered delivery site, but no markets are suppressed. Check the markets on the other instance are suppressed.' );
            }
        }

        $this->log(
            home_url(),
            self::LOG_AS_INFO,
            '',
            sprintf(
                'Markets that must not be in the sitemap: %s',
                $this->hidden_markets ? implode( ', ', $this->hidden_markets ) : 'none'
            )
        );
    }

    /**
     * Validate one sitemap file, then check everything it lists. Sitemap indexes recurse.
     *
     * @param string $url Full (local) URL of the sitemap
     * @param string $parent_url The sitemap index that listed it, if any
     */
    private function crawl_sitemap( string $url, string $parent_url = '', bool $parent_is_hidden = false ): void {
        if ( isset( $this->sitemaps_seen[ $url ] ) ) {
            $this->log( $url, self::LOG_AS_WARNING, 'duplicate', sprintf( 'Sitemap is listed more than once (again in %s).', $parent_url ?: 'the root' ) );
            return;
        }
        $this->sitemaps_seen[ $url ] = true;
        $this->summary['sitemaps_checked']++;

        $sitemap = $this->fetch_sitemap( $url, $parent_url );
        if ( ! $sitemap ) {
            return;
        }

        $this->log( $url, self::LOG_AS_GOOD );
        $this->log( $url, self::LOG_AS_INFO, '', sprintf( 'Valid %s with %d entries.', $sitemap['type'], count( $sitemap['entries'] ) ) );

        foreach ( $sitemap['entries'] as $entry ) {
            $local_url = $this->to_local_url( $entry->loc );
            if ( ! $local_url ) {
                $this->log( $entry->loc, self::LOG_AS_BAD, 'foreign_domain', sprintf( 'Listed in %s but is not on this site\'s domain, so it was not checked.', $url ) );
                continue;
            }

            $market = $this->market_of_url( $local_url );
            $is_hidden = $market && in_array( $market, $this->hidden_markets, true );

            // A hidden market's sub-sitemaps are already covered by the error on its index
            if ( $is_hidden && ! ( $parent_is_hidden && $sitemap['type'] === 'sitemapindex' ) ) {
                $this->log(
                    $local_url,
                    self::LOG_AS_BAD,
                    'hidden_market_listed',
                    sprintf( 'Listed in %s but market "%s" is hidden or suppressed, so it must not be in the sitemap.', $url, $market )
                );
            }

            if ( $sitemap['type'] === 'sitemapindex' ) {
                // Still crawl hidden markets' sitemaps, to catch any URLs leaking out through them
                $this->crawl_sitemap( $local_url, $url, $is_hidden );
                continue;
            }

            $key = $this->normalise_url( $local_url );
            if ( isset( $this->urls_in_sitemaps[ $key ] ) ) {
                $this->log( $local_url, self::LOG_AS_WARNING, 'duplicate', sprintf( 'Listed in %s and again in %s.', $this->urls_in_sitemaps[ $key ], $url ) );
                continue;
            }
            $this->urls_in_sitemaps[ $key ] = $url;

            $this->test_url( $local_url, $url );
        }
    }

    /**
     * Fetch a single sitemap file and check it is a valid sitemap
     *
     * @param string $url Full URL to fetch
     * @param string $parent_url The sitemap index that listed it, if any
     * @return array|null ['type' => 'sitemapindex'|'urlset', 'entries' => SitemapEntryModel[]], or null if it is invalid (already logged)
     */
    private function fetch_sitemap( string $url, string $parent_url = '' ): ?array {
        $listed_in = $parent_url ? sprintf( ' (listed in %s)', $parent_url ) : '';
        $response = $this->request( $url );

        if ( is_wp_error( $response ) ) {
            $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( 'Failed to load sitemap%s. Error "%s"', $listed_in, $response->get_error_message() ) );
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            $location = $this->location_of( $response, $url );
            $this->log(
                $url,
                self::LOG_AS_BAD,
                'invalid_sitemap',
                sprintf( 'Sitemap%s gave a %d response%s, expected 200.', $listed_in, $code, $location ? ' to ' . $location : '' )
            );
            return null;
        }

        $content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
        if ( ! preg_match( '#^(text|application)/xml\b#i', $content_type ) ) {
            $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( 'Sitemap%s has content type "%s", expected text/xml or application/xml.', $listed_in, $content_type ?: 'unknown' ) );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > self::MAX_SITEMAP_BYTES ) {
            $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( 'Sitemap is %d bytes; the limit is 50MB.', strlen( $body ) ) );
            return null;
        }

        $previous_libxml_setting = libxml_use_internal_errors( true );
        libxml_clear_errors();
        $doc = new DOMDocument();
        $loaded = $doc->loadXML( $body, LIBXML_NONET );
        $xml_errors = array_filter( libxml_get_errors(), fn( $error ) => $error->level >= LIBXML_ERR_ERROR );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous_libxml_setting );

        if ( ! $loaded || $xml_errors ) {
            $messages = array_map(
                fn( $error ) => sprintf( 'line %d: %s', $error->line, trim( $error->message ) ),
                array_slice( $xml_errors, 0, 3 )
            );
            $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( 'Sitemap is not well-formed XML: %s', implode( '; ', $messages ) ?: 'could not parse' ) );
            return null;
        }

        $root = $doc->documentElement;
        if ( ! $root || ! in_array( $root->localName, [ 'sitemapindex', 'urlset' ], true ) ) {
            $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( 'Root element is <%s>, expected <sitemapindex> or <urlset>.', $root ? $root->nodeName : 'none' ) );
            return null;
        }

        if ( $root->namespaceURI !== self::SITEMAP_NAMESPACE ) {
            $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( 'Root element namespace is "%s", expected "%s".', $root->namespaceURI ?? 'none', self::SITEMAP_NAMESPACE ) );
            return null;
        }

        $entry_name = $root->localName === 'sitemapindex' ? 'sitemap' : 'url';
        $xpath = new DOMXPath( $doc );
        $xpath->registerNamespace( 's', self::SITEMAP_NAMESPACE );
        $nodes = $xpath->query( 's:' . $entry_name, $root );

        if ( $nodes->length > self::MAX_SITEMAP_ENTRIES ) {
            $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( 'Sitemap has %d entries; the limit is %d.', $nodes->length, self::MAX_SITEMAP_ENTRIES ) );
            return null;
        }

        $entries = [];
        $is_valid = true;
        foreach ( $nodes as $position => $node ) {
            /** @var DOMElement $node */
            $loc = trim( (string) $xpath->evaluate( 'string(s:loc)', $node ) );
            $lastmod = trim( (string) $xpath->evaluate( 'string(s:lastmod)', $node ) );

            if ( ! $loc ) {
                $is_valid = false;
                $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( '<%s> number %d has no <loc>.', $entry_name, $position + 1 ) );
                continue;
            }

            if ( ! preg_match( '#^https?://#i', $loc ) || ! filter_var( $this->encode_url( $loc ), FILTER_VALIDATE_URL ) ) {
                $is_valid = false;
                $this->log( $url, self::LOG_AS_BAD, 'invalid_sitemap', sprintf( '<loc> "%s" is not a full http(s) URL.', $loc ) );
                continue;
            }

            $lastmod_date = null;
            if ( $lastmod ) {
                $lastmod_date = $this->parse_lastmod( $lastmod );
                if ( ! $lastmod_date ) {
                    $this->log( $loc, self::LOG_AS_WARNING, 'invalid_sitemap', sprintf( '<lastmod> "%s" in %s is not a valid W3C date.', $lastmod, $url ) );
                }
            }

            $entries[] = new SitemapEntryModel( $loc, $lastmod_date );
        }

        if ( ! $is_valid && ! $entries ) {
            return null;
        }

        if ( ! $entries ) {
            $this->log( $url, self::LOG_AS_INFO, '', 'Sitemap is empty.' );
        }

        return [
            'type'    => $root->localName,
            'entries' => $entries,
        ];
    }

    /**
     * Check a single page URL from the sitemap gives a 200
     *
     * @param string $url Full (local) URL
     * @param string $sitemap_url The sitemap it was listed in
     */
    private function test_url( string $url, string $sitemap_url ): void {
        $this->summary['urls_checked']++;
        $response = $this->request( $url );

        if ( is_wp_error( $response ) ) {
            $this->log( $url, self::LOG_AS_BAD, 'request_error', sprintf( 'Failed to load URL. Error "%s"', $response->get_error_message() ) );
            return;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 200 ) {
            $this->summary['urls_ok']++;
            $this->log( $url, self::LOG_AS_GOOD );

            if ( $this->flags['check-links'] ) {
                $this->check_links_on_page( $url, wp_remote_retrieve_body( $response ) );
            }
            return;
        }

        if ( $code >= 300 && $code < 400 ) {
            $this->evaluate_redirect( $url, $code, $this->location_of( $response, $url ) );
            return;
        }

        if ( in_array( $code, [ 401, 403 ], true ) ) {
            $this->log( $url, self::LOG_AS_BAD, 'private', sprintf( 'Gave a %d: a private page is listed in the sitemap as public.', $code ) );
            return;
        }

        if ( in_array( $code, [ 404, 410 ], true ) ) {
            $this->log( $url, self::LOG_AS_BAD, 'not_found', sprintf( 'Gave a %d: the sitemap lists a URL the site cannot route.', $code ) );
            return;
        }

        if ( $code >= 400 && $code < 500 ) {
            $this->log( $url, self::LOG_AS_BAD, 'client_error', sprintf( 'Gave a %d response, expected 200.', $code ) );
            return;
        }

        if ( $code >= 500 ) {
            $this->log( $url, self::LOG_AS_BAD, 'server_error', sprintf( 'Gave a %d: server error.', $code ) );
            return;
        }

        $this->log( $url, self::LOG_AS_WARNING, 'client_error', sprintf( 'Gave a %d response, expected 200.', $code ) );
    }

    /**
     * A sitemap URL redirected. If a Redirection rule explains it that is a warning
     * (list the target instead), otherwise it is bad.
     */
    private function evaluate_redirect( string $url, int $code, string $location ): void {
        $location_text = $location ?: 'an unknown location';

        if ( ! $this->redirection_service->is_available() ) {
            $this->log( $url, self::LOG_AS_BAD, 'redirect_unknown', sprintf( 'Redirects (%d) to %s. Redirection is not active, so it could not be checked.', $code, $location_text ) );
            return;
        }

        $rule = $this->redirection_service->find_matching_rule( $url );
        if ( ! $rule ) {
            $this->log(
                $url,
                self::LOG_AS_BAD,
                'redirect_unknown',
                sprintf( 'Redirects (%d) to %s, and no Redirection rule matches it.', $code, $location_text )
            );
            return;
        }

        $this->log(
            $url,
            self::LOG_AS_WARNING,
            'redirect_known',
            sprintf(
                'Redirects (%d) to %s via Redirection rule #%d (%s -> %s). The sitemap should list the target instead.',
                $code,
                $location_text,
                $rule['id'],
                $rule['source'],
                $rule['target'] ?: '?'
            )
        );
    }

    /**
     * Check every href, src and srcset link on a page that points at this site
     */
    private function check_links_on_page( string $page_url, string $html ): void {
        $links = $this->extract_links( $page_url, $html );
        $this->log( $page_url, self::LOG_AS_INFO, '', sprintf( 'Checking %d links on this site\'s domain.', count( $links ) ) );

        foreach ( $links as $link ) {
            $result = $this->check_link( $link );

            if ( $result['status'] === 'redirected' ) {
                $this->log( $page_url, self::LOG_AS_WARNING, 'link_redirected', sprintf( 'Link %s redirects (%d) to %s.', $link, $result['code'], $result['location'] ?: '?' ) );
            } elseif ( $result['status'] === 'broken' ) {
                $this->log( $page_url, self::LOG_AS_BAD, 'link_broken', sprintf( 'Link %s %s.', $link, $result['message'] ) );
            }
        }
    }

    /**
     * Collect the unique URLs on this site's domain from href, src and srcset attributes
     *
     * @return string[] Full local URLs, without fragments
     */
    private function extract_links( string $page_url, string $html ): array {
        if ( ! trim( $html ) ) {
            return [];
        }

        $previous_libxml_setting = libxml_use_internal_errors( true );
        $doc = new DOMDocument();
        $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous_libxml_setting );

        $xpath = new DOMXPath( $doc );

        $base_url = $page_url;
        $base = $xpath->query( '//base[@href]' )->item( 0 );
        if ( $base instanceof DOMElement ) {
            $base_url = $this->resolve_url( $page_url, $base->getAttribute( 'href' ) ) ?: $page_url;
        }

        $raw_links = [];

        // Links. Skip resource hints, which point at hosts rather than pages
        foreach ( $xpath->query( '//a[@href] | //area[@href] | //link[@href]' ) as $element ) {
            /** @var DOMElement $element */
            $rel = strtolower( $element->getAttribute( 'rel' ) );
            if ( $element->nodeName === 'link' && preg_match( '/\b(preconnect|dns-prefetch)\b/', $rel ) ) {
                continue;
            }
            $raw_links[] = $element->getAttribute( 'href' );
        }

        // Images, scripts, iframes, video etc. data-src is the lazy-loaded src.
        foreach ( $xpath->query( '//*[@src] | //*[@data-src]' ) as $element ) {
            /** @var DOMElement $element */
            foreach ( [ 'src', 'data-src' ] as $attribute ) {
                if ( $element->hasAttribute( $attribute ) ) {
                    $raw_links[] = $element->getAttribute( $attribute );
                }
            }
        }

        foreach ( $xpath->query( '//*[@srcset] | //*[@data-srcset]' ) as $element ) {
            /** @var DOMElement $element */
            foreach ( [ 'srcset', 'data-srcset' ] as $attribute ) {
                if ( $element->hasAttribute( $attribute ) ) {
                    $raw_links = array_merge( $raw_links, $this->parse_srcset( $element->getAttribute( $attribute ) ) );
                }
            }
        }

        $links = [];
        foreach ( $raw_links as $raw_link ) {
            $raw_link = trim( $raw_link );
            if (
                $raw_link === ''
                || $raw_link[0] === '#'
                || preg_match( '/^(mailto|tel|sms|javascript|data|blob|about):/i', $raw_link )
            ) {
                continue;
            }

            $absolute = $this->resolve_url( $base_url, $raw_link );
            if ( ! $absolute ) {
                continue;
            }

            $local = $this->to_local_url( preg_replace( '/#.*$/', '', $absolute ) );
            if ( $local ) {
                $links[ $local ] = true;
            }
        }

        return array_keys( $links );
    }

    /**
     * Check one link once; later pages reuse the result
     *
     * @return array ['status' => 'ok'|'redirected'|'broken', 'code' => int, 'location' => string, 'message' => string]
     */
    private function check_link( string $url ): array {
        if ( isset( $this->link_results[ $url ] ) ) {
            return $this->link_results[ $url ];
        }
        $this->summary['links_checked']++;

        // Only the status matters, so don't download whole images
        $response = $this->request( $url, [ 'limit_response_size' => 1024 ] );
        $result = [ 'status' => 'ok', 'code' => 0, 'location' => '', 'message' => '' ];

        if ( is_wp_error( $response ) ) {
            $result['status'] = 'broken';
            $result['message'] = sprintf( 'failed to load: "%s"', $response->get_error_message() );
            return $this->link_results[ $url ] = $result;
        }

        $result['code'] = (int) wp_remote_retrieve_response_code( $response );

        if ( $result['code'] === 200 ) {
            return $this->link_results[ $url ] = $result;
        }

        if ( $result['code'] >= 300 && $result['code'] < 400 ) {
            $result['location'] = $this->location_of( $response, $url );

            // Where does it end up?
            $final = $this->request( $url, [ 'limit_response_size' => 1024, 'redirection' => self::MAX_LINK_REDIRECTS ] );
            $final_code = is_wp_error( $final ) ? 0 : (int) wp_remote_retrieve_response_code( $final );

            if ( $final_code === 200 ) {
                $result['status'] = 'redirected';
            } else {
                $result['status'] = 'broken';
                $result['message'] = sprintf(
                    'redirects (%d) to %s, which ends in %s',
                    $result['code'],
                    $result['location'] ?: '?',
                    $final_code ? 'a ' . $final_code : 'an error or a redirect loop'
                );
            }
            return $this->link_results[ $url ] = $result;
        }

        $result['status'] = 'broken';
        $result['message'] = sprintf( 'gave a %d', $result['code'] );
        return $this->link_results[ $url ] = $result;
    }

    /**
     * Every published, indexable post of the expected types, outside hidden markets,
     * should be in the sitemap
     */
    private function check_expected_posts(): void {
        $post_types = $this->flags['expected-post-types'];
        if ( ! is_array( $post_types ) ) {
            $post_types = explode( ',', (string) $post_types );
        }
        $post_types = array_filter( array_map( 'trim', $post_types ) );

        $posts = $this->database_service->get_posts_by_types( $post_types, DatabaseService::EXCLUDE_HIDDEN_MARKETS );
        $this->log( home_url(), self::LOG_AS_INFO, '', sprintf( 'Checking %d published %s posts are in the sitemap.', count( $posts ), implode( ', ', $post_types ) ) );

        foreach ( $posts as $post ) {
            // DatabaseService only drops suppressed markets; drop WPML hidden languages too
            $language = apply_filters(
                'wpml_element_language_code',
                null,
                [ 'element_id' => $post->ID, 'element_type' => 'post_' . $post->post_type ]
            );
            if ( $language && in_array( strtolower( $language ), $this->hidden_markets, true ) ) {
                continue;
            }

            // SEOPress leaves noindex posts out of the sitemap on purpose
            if ( get_post_meta( $post->ID, '_seopress_robots_index', true ) === 'yes' ) {
                continue;
            }

            $permalink = $this->http_service->get_post_permalink( $post );
            $local = $this->to_local_url( $permalink ) ?: $permalink;

            if ( ! isset( $this->urls_in_sitemaps[ $this->normalise_url( $local ) ] ) ) {
                $this->log(
                    $local,
                    self::LOG_AS_WARNING,
                    'missing_from_sitemap',
                    sprintf( 'Published %s #%d "%s" is not in the sitemap.', $post->post_type, $post->ID, get_the_title( $post ) )
                );
            }
        }
    }

    /**
     * Print the summary and exit with 1 if anything was bad
     */
    private function finish(): void {
        $this->json_output['summary'] = $this->summary;

        if ( $this->flags['format'] === self::OUTPUT_FORMAT_STDOUT ) {
            $this->output( self::OUTPUT_AS_LOG, '' );
            $this->output( self::OUTPUT_AS_LOG, sprintf(
                'Checked %d sitemaps and %d URLs (%d gave a 200)%s.',
                $this->summary['sitemaps_checked'],
                $this->summary['urls_checked'],
                $this->summary['urls_ok'],
                $this->flags['check-links'] ? sprintf( ', and %d unique links', $this->summary['links_checked'] ) : ''
            ) );

            $labels = [
                'setup'                => 'Setup problems',
                'invalid_sitemap'      => 'Sitemap file problems',
                'foreign_domain'       => 'URLs on another domain',
                'hidden_market_listed' => 'Hidden/suppressed market entries',
                'duplicate'            => 'Duplicates',
                'redirect_known'       => '3xx with a Redirection rule',
                'redirect_unknown'     => '3xx without a Redirection rule',
                'private'              => '401/403 private pages',
                'not_found'            => '404/410 not found',
                'client_error'         => 'Other 4xx',
                'server_error'         => '5xx server errors',
                'request_error'        => 'Request errors (timeouts etc.)',
                'missing_from_sitemap' => 'Posts missing from the sitemap',
                'link_redirected'      => 'Links that redirect',
                'link_broken'          => 'Broken links',
            ];
            foreach ( $labels as $key => $label ) {
                if ( $this->summary[ $key ] ) {
                    $this->output( self::OUTPUT_AS_LOG, sprintf( '  %s: %d', $label, $this->summary[ $key ] ) );
                }
            }

            $this->output( self::OUTPUT_AS_LOG, sprintf(
                'Good: %d  Warnings: %d  Bad: %d',
                $this->json_output['stats']['good'],
                $this->json_output['stats']['warning'],
                $this->json_output['stats']['bad']
            ) );
        }

        if ( $this->json_output['stats']['bad'] > 0 ) {
            // The JSON output is still printed by __destruct on shutdown
            WP_CLI::halt( 1 );
        }
    }

    /**
     * GET a URL without following redirects (unless overridden)
     */
    private function request( string $url, array $options = [] ): array|WP_Error {
        $options = array_merge(
            [
                'timeout'     => self::REQUEST_TIMEOUT,
                'redirection' => 0,
            ],
            $options
        );

        if ( $this->flags['vverbose'] ) {
            $this->output( self::OUTPUT_AS_LOG, sprintf( 'GET %s', $url ) );
        }

        return $this->http_service->get( $this->encode_url( $url ), $options );
    }

    /**
     * Map a URL on any of this site's hosts (live domain, Lando etc.) to the base URL
     * we test against, so we never request the live site. Null for other domains.
     */
    private function to_local_url( string $url ): ?string {
        $parts = parse_url( $url );
        if ( ! $parts || empty( $parts['host'] ) ) {
            return null;
        }

        if ( ! in_array( strtolower( $parts['host'] ), $this->own_hosts, true ) ) {
            return null;
        }

        return rtrim( $this->http_service->get_base_url(), '/' )
            . ( $parts['path'] ?? '/' )
            . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
    }

    /**
     * Which WPML market a URL belongs to, based on the site's language URL format
     */
    private function market_of_url( string $url ): ?string {
        $parts = parse_url( $url );

        switch ( (string) apply_filters( 'wpml_setting', 0, 'language_negotiation_type' ) ) {
            case '1': // https://site.com/xx/
                $segments = explode( '/', trim( $parts['path'] ?? '', '/' ) );
                return $segments[0] !== '' ? strtolower( $segments[0] ) : null;

            case '2': // https://xx.site.com/
                $labels = explode( '.', $parts['host'] ?? '' );
                return count( $labels ) > 2 ? strtolower( $labels[0] ) : null;

            case '3': // https://site.com/?lang=xx
                parse_str( $parts['query'] ?? '', $query );
                return isset( $query['lang'] ) ? strtolower( (string) $query['lang'] ) : null;
        }

        return null;
    }

    /**
     * Resolve a (possibly relative) link against the page it was found on
     */
    private function resolve_url( string $base_url, string $link ): ?string {
        if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $link ) ) {
            return preg_match( '#^https?://#i', $link ) ? $link : null;
        }

        $base = parse_url( $base_url );
        if ( ! $base || empty( $base['host'] ) ) {
            return null;
        }
        $scheme = $base['scheme'] ?? 'https';
        $origin = $scheme . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . $base['port'] : '' );

        if ( str_starts_with( $link, '//' ) ) {
            return $scheme . ':' . $link;
        }

        $base_path = $base['path'] ?? '/';

        if ( $link[0] === '?' ) {
            return $origin . $base_path . $link;
        }

        if ( $link[0] === '/' ) {
            $path_and_query = $link;
        } else {
            $directory = substr( $base_path, 0, strrpos( $base_path, '/' ) + 1 );
            $path_and_query = $directory . $link;
        }

        // Collapse "." and ".." segments in the path
        [ $path, $query ] = array_pad( explode( '?', $path_and_query, 2 ), 2, null );
        $segments = [];
        foreach ( explode( '/', $path ) as $segment ) {
            if ( $segment === '..' ) {
                if ( count( $segments ) > 1 ) {
                    array_pop( $segments );
                }
            } elseif ( $segment !== '.' ) {
                $segments[] = $segment;
            }
        }
        $path = implode( '/', $segments );
        if ( preg_match( '#/\.\.?$#', $path_and_query ) ) {
            $path .= '/';
        }

        return $origin . ( str_starts_with( $path, '/' ) ? $path : '/' . $path ) . ( $query !== null ? '?' . $query : '' );
    }

    /**
     * Split a srcset into its URLs. URLs can contain commas (e.g. image CDN transforms),
     * so split on whitespace first as the HTML spec does.
     *
     * @return string[]
     */
    private function parse_srcset( string $srcset ): array {
        $urls = [];
        $length = strlen( $srcset );
        $position = 0;

        while ( $position < $length ) {
            // Skip whitespace and separating commas
            while ( $position < $length && ( ctype_space( $srcset[ $position ] ) || $srcset[ $position ] === ',' ) ) {
                $position++;
            }
            if ( $position >= $length ) {
                break;
            }

            // The URL runs up to the next whitespace
            $start = $position;
            while ( $position < $length && ! ctype_space( $srcset[ $position ] ) ) {
                $position++;
            }
            $url = substr( $srcset, $start, $position - $start );

            // A URL ending in a comma has no descriptor
            if ( str_ends_with( $url, ',' ) ) {
                $urls[] = rtrim( $url, ',' );
                continue;
            }
            $urls[] = $url;

            // Skip the descriptor (e.g. "800w", "2x") up to the next comma
            while ( $position < $length && $srcset[ $position ] !== ',' ) {
                $position++;
            }
        }

        return array_filter( $urls );
    }

    /**
     * Parse a sitemap <lastmod>: W3C datetime, e.g. 2026-11-05 or 2026-11-05T09:39:11+00:00
     */
    private function parse_lastmod( string $lastmod ): ?DateTimeImmutable {
        if ( ! preg_match( '/^\d{4}(-\d{2}(-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+\-]\d{2}:\d{2}))?)?)?$/', $lastmod ) ) {
            return null;
        }

        try {
            return new DateTimeImmutable( $lastmod );
        } catch ( \Exception $e ) {
            return null;
        }
    }

    /**
     * The Location header of a redirect, made absolute and local
     */
    private function location_of( array $response, string $request_url ): string {
        $location = wp_remote_retrieve_header( $response, 'location' );
        if ( is_array( $location ) ) {
            $location = end( $location );
        }
        if ( ! $location ) {
            return '';
        }

        $absolute = $this->resolve_url( $request_url, (string) $location ) ?: (string) $location;
        return $this->to_local_url( $absolute ) ?: $absolute;
    }

    /**
     * Percent-encode non-ASCII characters, e.g. "/ko-kr/제품/" so WP_Http and filter_var accept it
     */
    private function encode_url( string $url ): string {
        return preg_replace_callback( '/[^\x20-\x7f]/', fn( $match ) => rawurlencode( $match[0] ), $url );
    }

    /**
     * Key for comparing URLs: decoded path, lowercase host, no trailing slash
     */
    private function normalise_url( string $url ): string {
        $parts = parse_url( $url );
        $path = rawurldecode( $parts['path'] ?? '/' );

        return strtolower( $parts['host'] ?? '' )
            . ( $path === '/' ? '/' : rtrim( $path, '/' ) )
            . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
    }

    private function host_of( string $url ): string {
        return strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
    }

    /**
     * Log info to the appropriate output
     *
     * @param string $url The absolute URL this log entry relates to
     * @param string $log_as Enum of 'good', 'warning', 'bad', 'info'
     * @param string $category Key in $this->summary that this counts towards (warnings and bads)
     * @param string $reason An optional message to give further context
     */
    private function log( string $url, string $log_as, string $category = '', string $reason = '' ):void
    {
        if ( in_array( $log_as, [ self::LOG_AS_WARNING, self::LOG_AS_BAD ], true ) ) {
            if ( $category && isset( $this->summary[ $category ] ) ) {
                $this->summary[ $category ]++;
            }
            $this->json_output['issues'][] = [
                'severity' => $log_as,
                'category' => $category,
                'url'      => $url,
                'message'  => $reason,
            ];
        }

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
