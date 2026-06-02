<?php

namespace OnionWordpressDeveloperToolbox\Controllers\Command;

use \WP_CLI;

class JsonLdCommand extends AbstractCommandController
{
    const COMMAND_NAME = 'jsonld';

    /**
     * Checks URLs for valid ld+json Structured data
     * 
     * [--target_path=<path>]
     * : Test a specific path
     */
    public function __invoke( array $args, array $flags )
    {
        $flags = wp_parse_args(
            $flags,
            array(
                'target_path' => null,
            )
        );

        print_r( $args );
        print_r( $flags );
        WP_CLI::success( 'Done' );
    }
}
