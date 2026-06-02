<?php

namespace OnionWordpressDeveloperToolbox\Controllers\Command;

use \WP_CLI;
use OnionWordpressDeveloperToolbox\Controllers\AbstractController;
use OnionWordpressDeveloperToolbox\Exceptions\CliException;

abstract class AbstractCommandController extends AbstractController
{
    public const COMMAND_NAME = 'BAD';

    /**
     * Initialize the class and set its properties.
     *
     * @param      string    $pluginName       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($pluginName, $version) {
        if ( ! $this::COMMAND_NAME ) {
            throw new CliException( 'Missing COMMAND_NAME class constant' );
        }
        parent::__construct( $pluginName, $version );
    }

    public function register():void {
        WP_CLI::add_command( 'onionbox ' . $this::COMMAND_NAME,  $this );
    }
}
