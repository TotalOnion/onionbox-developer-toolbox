<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use EasyRdf\Graph;
use OnionWordpressDeveloperToolbox\Exceptions\FieldValidatorException;
use OnionWordpressDeveloperToolbox\Exceptions\LdJsonException;
use OnionWordpressDeveloperToolbox\Services\HttpService;
use OnionWordpressDeveloperToolbox\Validators\FieldValidators\FieldValidatorFactory;

abstract class LdJsonValidator {

    protected const SCHEMA_NAME = '';
    protected const REQUIRED_FIELDS = [];
    protected const OPTIONAL_FIELDS = [];

    protected array $required_fields = [];
    protected array $optional_fields = [];

    protected ?HttpService $http_service;
    protected array $flags = [];

    public function __construct(
        protected Graph $graph,
        protected array $ld_json,
        protected bool $is_sub_schema = false
    ) {
        $this->http_service = new HttpService();

        try {
            foreach ( $this::REQUIRED_FIELDS as $field_key => $field_config ) {
                $this->required_fields[ $field_key ] = FieldValidatorFactory::instance( $field_key, $field_config );
            }
            foreach ( $this::OPTIONAL_FIELDS as $field_key => $field_config ) {
                $this->optional_fields[ $field_key ] = FieldValidatorFactory::instance( $field_key, $field_config );
            }
        } catch ( \Exception $e ) {
            // rethrow a generic exception as an LdJsonException
            throw new LdJsonException( $e->getMessage() );
        }
    }

    public function set_flags( array $flags ):void {
        $this->flags = $flags;
    }

    public function validate():array {
        if ( $this->flags['vverbose'] ?? false ) {
            print_r( $this->ld_json );
        }

        $errors = $this->validate_json_ld_node( $this->ld_json );
        $errors = $this->check_required_fields( $errors );
        return $errors;
    }

    /**
     * Check any REQUIRED_FIELDS that are set in the descendent class
     * 
     * @param array $errors
     * @return array
     */
    private function check_required_fields( $errors = [] ):array {
        if (
            ! array_key_exists( '@type', $this->ld_json )
            || $this->ld_json['@type'] !== $this::SCHEMA_NAME
        ) {
            $errors[] = sprintf( 'Missing schema @type=%s', $this::SCHEMA_NAME );
        }

        foreach( $this->required_fields as $key => $field_validator ) {
            if ( ! array_key_exists( $key, $this->ld_json ) ) {
                $errors[] = sprintf( 'Missing required fields "%s"', $key );
                continue;
            }

            try {
                $field_validator->validate( $this->ld_json[ $key ], $this->flags );
            } catch ( FieldValidatorException $e ) {
                $errors[] = $e->getMessage();
            }
        }

        foreach( $this->optional_fields as $key => $field_validator ) {
            if ( ! array_key_exists( $key, $this->ld_json ) ) {
                continue;
            }

            try {
                $field_validator->validate( $this->ld_json[ $key ], $this->flags );
            } catch ( FieldValidatorException $e ) {
                $errors[] = $e->getMessage();
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
