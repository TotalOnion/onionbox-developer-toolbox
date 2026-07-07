<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use OnionWordpressDeveloperToolbox\Exceptions\LdJsonException;

class LdJsonValidatorFactory {

    private const BASE_VALIDATOR_CLASSNAME = 'OnionWordpressDeveloperToolbox\Validators\LdJson\LdJson%sValidator';
    public const SET_AS_SUB_SCHEMA = true;

    public const VALIDATOR_TYPE_BRAND   = 'Brand';
    public const VALIDATOR_TYPE_OFFER   = 'Offer';
    public const VALIDATOR_TYPE_PRODUCT = 'Product';
    public const VALIDATOR_TYPE_RECIPE  = 'Recipe';
    public const AVAILABLE_VALIDATORS = [
        self::VALIDATOR_TYPE_BRAND,
        self::VALIDATOR_TYPE_OFFER,
        self::VALIDATOR_TYPE_PRODUCT,
        self::VALIDATOR_TYPE_RECIPE,
    ];

    private ?Graph $graph;

    public function __construct()
    {
        RdfNamespace::set('schema', 'https://schema.org/');
        RdfNamespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');

        $this->graph = new Graph();
        $this->graph->parseFile(__DIR__ . '/schemaorg-current-https.ttl', 'turtle');
    }

    public function instance( array $ld_json, bool $is_sub_schema = false ):LdJsonValidator {
        if ( ! array_key_exists( '@type', $ld_json ) ) {
            throw new LdJsonException( 'No @type found in the ld+json' );
        }

        if ( ! in_array( $ld_json['@type'], self::AVAILABLE_VALIDATORS ) ) {
            throw new LdJsonException( sprintf( 'No validator found for @type %s', $ld_json['@type'] ) );
        }

        $classname = sprintf( self::BASE_VALIDATOR_CLASSNAME, $ld_json['@type'] );
        return new $classname( $this->graph, $ld_json, $is_sub_schema );
    }

}
