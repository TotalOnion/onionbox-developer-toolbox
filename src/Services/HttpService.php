<?php

namespace OnionWordpressDeveloperToolbox\Services;

use \WP_Error;
use \WP_Http;
use OnionWordpressDeveloperToolbox\Exceptions\WpHttpException;

class HttpService {
    private ?WP_Http $request;
    private string $base_url = '';

    public function __construct() {

        $this->request = new WP_Http; 

        // don't use get_site_url() as that can be forced to be the true domain on sites split over multiple instances
        if ( 
            ($_ENV['LANDO_APP_NAME'] ?? false)
            && ($_ENV['LANDO_DOMAIN'] ?? false)
        ) {
            $this->base_url = sprintf( 'https://%s.%s', $_ENV['LANDO_APP_NAME'], $_ENV['LANDO_DOMAIN'] );
        } else {
            $this->base_url = get_option( 'siteurl' );
        }
    }

    public function get_base_url(): string {
        return $this->base_url;
    }


    /**
     * Check if a URL is valid to make requests to
     * 
     * @param string $url The Fully qualified URL to test
     * @return bool
     * @throws WpHttpException
     */
    public function is_target_url_valid( string $url ):bool {
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            throw new WpHttpException(
                sprintf(
                    'URL "%s" in the chain is not valid.',
                    $url,
                )
            );
        }

        return true;
    }

    /**
     * Check URLs for internationalisation like "/ko-kr/제풅-라인업/발렌타인-17년/" and return it urlencoded
     * 
     * @param string $url
     * @return string $sanitized_url
     */
    public function international_url_sanitize( string $url ): string {
        $path_parts = parse_url( $url );
        if ( ! $path_parts ) {
            throw new WpHttpException(
                sprintf(
                    'parse_url() failed for URL "%s".',
                    $url,
                )
            );
        }
        $path_parts['path'] = preg_replace_callback('/[^\x20-\x7f]/', fn($match) => urlencode($match[0]), $path_parts['path']);

        return sprintf(
            '%s://%s%s',
            $path_parts['scheme'],
            $path_parts['host'],
            $path_parts['path'],
        );
    }

    /**
     * Check URLs for internationalisation like "/ko-kr/%EC%A0%9C%ED%92%85-%EB%9D%BC%EC%9D%B8%EC%97%85/%EB%B0%9C%EB%A0%8C%ED%83%80%EC%9D%B8-17%EB%85%84/" and return it in human readable form
     * 
     * @param string $url
     * @return string $sanitized_url
     */
    public function international_url_humanize( string $url ): string {
        $path_parts = parse_url( $url );
        if ( ! $path_parts ) {
            throw new WpHttpException(
                sprintf(
                    'parse_url() failed for URL "%s".',
                    $url,
                )
            );
        }
        $path_parts['path'] = preg_replace_callback('/%([A-Fa-f0-9]{2})/', fn($match) => urldecode($match[0]), $path_parts['path']);

        return sprintf(
            '%s://%s%s',
            $path_parts['scheme'],
            $path_parts['host'],
            $path_parts['path'],
        );
    }

    /**
     * Wrapper for WP_Http
     * 
     * @param string|array Optional. Override the defaults.
     * @return array|WP_Error Array containing 'headers', 'body', 'response', 'cookies', 'filename'.
	 *                        A WP_Error instance upon error. See WP_Http::response() for details.
     */
    public function get( string $url, string|array $options = [] ): array|WP_Error {
        return $this->request->get( $url, $options );
    }
}
