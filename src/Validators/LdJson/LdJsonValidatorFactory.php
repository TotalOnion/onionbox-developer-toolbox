<?php

namespace OnionWordpressDeveloperToolbox\Validators\LdJson;

use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use OnionWordpressDeveloperToolbox\Exceptions\LdJsonException;

class LdJsonValidatorFactory {

    private ?Graph $graph;

    public function __construct()
    {
        RdfNamespace::set('schema', 'https://schema.org/');
        RdfNamespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');

        $this->graph = new Graph();
        $this->graph->parseFile(__DIR__ . '/schemaorg-current-https.ttl', 'turtle');
    }

    public function instance( array $ld_json ):LdJsonValidator {
        if ( ! array_key_exists( '@type', $ld_json ) ) {
            throw new LdJsonException( 'No @type found in the ld+json' );
        }

        switch ( $ld_json['@type'] ) {
            case 'Recipe':
                return new LdJsonRecipeValidator( $this->graph, $ld_json );
            
            default:
                throw new LdJsonException( sprintf( 'No validator found for @type %s', $ld_json['@type'] ) );
                //return new LdJsonValidator( $this->graph, $ld_json );
        }
    }

}
