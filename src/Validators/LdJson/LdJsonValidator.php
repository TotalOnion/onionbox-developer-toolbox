<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use \WP_Http;
use EasyRdf\Graph;
use OnionWordpressDeveloperToolbox\Exceptions\LdJsonException;
use OnionWordpressDeveloperToolbox\Exceptions\WpHttpException;
use OnionWordpressDeveloperToolbox\Services\HttpService;

class LdJsonValidator {

    protected const REQUIRED_FIELDS = [];

    public const FIELD_TYPE_URL = 'url';
    public const FIELD_TYPE_ARRAY = 'array';
    public const VALID_FIELD_TYPES = [
        self::FIELD_TYPE_URL,
        self::FIELD_TYPE_ARRAY,
    ];

    protected ?HttpService $http_service;
    protected array $flags = [];

    public function __construct( protected Graph $graph, protected array $ld_json ) {
        $this->http_service = new HttpService();
    }

    public function set_flags( array $flags ):void {
        $this->flags = $flags;
    }

    public function validate():array {
        $errors = $this->validate_json_ld_node( $this->ld_json );
        $errors = $this->check_required_fields( $errors );
        return $errors;
    }

    private function check_required_fields( $errors = [] ):array {
        foreach( $this::REQUIRED_FIELDS as $key => $variable_type ) {
            
            if ( ! array_key_exists( $key, $this->ld_json ) ) {
                $errors[] = sprintf( 'Missing required fields "%s"', $key );
                continue;
            }

            if ( ! in_array( $variable_type, $this::VALID_FIELD_TYPES ) ) {
                throw new LdJsonException(
                    sprintf( 'Unknown required field type of %s. Allowed types are %s.', $variable_type, implode(', ', $this::VALID_FIELD_TYPES ) )
                );
            }

            switch ( $variable_type ) {
                case self::FIELD_TYPE_URL:
                    try {
                        HttpService::is_target_url_valid( $this->ld_json[ $key ] );
                    } catch ( WpHttpException $e ) {
                        $errors[] = sprintf( 'Field %s is expected to be a URL, but is not valid: %s', $key, $e->getMessage() );
                        continue 2;
                    }

                    if( $this->flags['follow-links'] ?? false ) {
                        $response = $this->http_service->get( $this->ld_json[ $key ] );
                        if ( is_wp_error( $response ) ) {
                            $errors[] = sprintf( 'Field "%s" caused an error when tested; "%s"', $key, $response->get_error_message() );
                            continue 2;
                        }

                        if( $response['response']['code'] !== WP_Http::OK ) {
                            $errors[] = sprintf(
                                'Field "%s", "%s" has a non 200 http response code. received %s',
                                $key,
                                $this->ld_json[ $key ],
                                $response['response']['code']
                            );
                            continue 2;
                        }
                    }
                    break;
                
                case self::FIELD_TYPE_ARRAY:
                    if ( ! is_array( $this->ld_json[ $key ] ) ) {
                        $errors[] = sprintf( 'Field %s is expected to be an array, but is %s', $key, gettype( $this->ld_json[ $key ] ) );
                    }
                    break;

            }
        }

        return $errors;
    }

    /**
     * Return all parent classes for a Schema.org type.
     */
    private function get_type_ancestors( string $type ): array
    {
        $seen = [];
        $queue = [$type];

        while ($queue) {
            $current = array_shift( $queue );

            if ( isset( $seen[$current] ) ) {
                continue;
            }

            $seen[$current] = true;

            $resource = $this->graph->resource($current);
            foreach ($resource->allResources('rdfs:subClassOf') as $parent) {
                $queue[] = $parent->getUri();
            }
        }

        return array_keys($seen);
    }

    /**
     * Build allowed properties + expected ranges for a type.
     */
    private function get_allowed_properties( string $type ): array
    {
        $types = $this->get_type_ancestors( $type );
        $properties = [];

        foreach ($this->graph->resources() as $resource) {
            foreach ($resource->allResources('schema:domainIncludes') as $domain) {
                if (in_array($domain->getUri(), $types, true)) {
                    $ranges = [];

                    foreach ($resource->allResources('schema:rangeIncludes') as $range) {
                        $ranges[] = $range->getUri();
                    }

                    $properties[$resource->getUri()] = $ranges;
                }
            }
        }

        return $properties;
    }

    /**
     * Very basic JSON-LD validator.
     *
     * This checks:
     * - property exists for the declared type
     * - simple primitive values roughly match Text, Number, Boolean, etc.
     *
     * It does not yet fully validate nested nodes.
     */
    protected function validate_json_ld_node( array $node, array $errors = [] ): array
    {
        if (empty($node['@type'])) {
            $errors[] = 'Missing @type';
            return $errors;
        }

        $type = 'https://schema.org/' . $node['@type'];
        $allowedProperties = $this->get_allowed_properties( $type );

        foreach ($node as $key => $value) {
            if (str_starts_with($key, '@')) {
                continue;
            }

            $propertyUri = 'https://schema.org/' . $key;

            if ( ! isset( $allowedProperties[ $propertyUri ] ) ) {
                $errors[] = sprintf( 'Property "%s" is not valid for %s', $key, $node['@type'] );
                continue;
            }

            $ranges = $allowedProperties[ $propertyUri ];

            if ( ! $this->value_matches_ranges( $value, $ranges ) ) {
                $expected = implode( ', ', array_map(
                    fn ($uri) => basename($uri),
                    $ranges
                ));

                $errors[] = sprintf( 'Property "%s" has invalid value type. Expected one of: %s', $key, $expected );
            }

            // recurse
            if ( is_array( $value ) && array_key_exists( '@type', $value ) ) {
                $errors = $this->validate_json_ld_node( $value, $errors );
            }
        }

        return $errors;
    }

    private function value_matches_ranges(mixed $value, array $ranges): bool
    {
        // Schema.org ranges are URIs such as https://schema.org/Text
        $shortRanges = array_map(fn ($uri) => basename($uri), $ranges);

        if (is_string($value)) {
            return array_intersect($shortRanges, [
                'Text',
                'URL',
                'Date',
                'DateTime',
                'Time',
                'Duration',
            ]) !== [];
        }

        if (is_int($value) || is_float($value)) {
            return array_intersect($shortRanges, [
                'Number',
                'Integer',
                'Float',
            ]) !== [];
        }

        if (is_bool($value)) {
            return in_array('Boolean', $shortRanges, true);
        }

        if (is_array($value)) {
            // Array of values or nested node.
            if (array_is_list($value)) {
                foreach ($value as $item) {
                    if (!$this->value_matches_ranges($item, $ranges)) {
                        return false;
                    }
                }

                return true;
            }

            // Nested JSON-LD object.
            return isset($value['@type']);
        }

        return false;
    }
}
