<?php

namespace OnionWordpressDeveloperToolbox\Controllers\Command;

use \WP_CLI;
use OnionWordpressDeveloperToolbox\Controllers\AbstractController;
use OnionWordpressDeveloperToolbox\Exceptions\CliException;

abstract class AbstractCommandController extends AbstractController
{
    public const COMMAND_NAME             = '';
    protected const LOCAL_CONFIG_FILENAME = '.onionbox-config.json';

    protected const OUTPUT_FORMAT_JSON    = 'json';
    protected const OUTPUT_FORMAT_STDOUT  = 'stdout';
    protected const OUTPUT_FORMAT_DEFAULT = self::OUTPUT_FORMAT_STDOUT;
    protected const VALID_OUTPUT_FORMATS  = [ self::OUTPUT_FORMAT_STDOUT, self::OUTPUT_FORMAT_JSON ];

    protected const OUTPUT_AS_LOG         = 'log';
    protected const OUTPUT_AS_WARNING     = 'warning';
    protected const OUTPUT_AS_ERROR       = 'error';
    protected const IS_FATAL              = true;

    protected array $flags = [
        'format'  => self::OUTPUT_FORMAT_DEFAULT,
        'verbose' => false,
    ];

    protected array $json_output = [
        'command' => [],
        'log' => [],
        'stats' => [
            'good' => 0,
            'warning' => 0,
            'bad' => 0,
        ],
    ];

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

        $this->json_output['command'] = [
            'name' => $this::COMMAND_NAME,
            'version' => $version,
            'run_at' => time(),
        ];
    }

    public function __destruct() {
        $this->json_output['command']['finished_at'] = time();

        if ( $this->flags['format'] === self::OUTPUT_FORMAT_JSON ) {
            echo json_encode( $this->json_output );
        }
    }

    public function register():void {
        WP_CLI::add_command( 'onionbox ' . $this::COMMAND_NAME,  $this );
    }

    /**
     * Look for config file ($this::LOCAL_CONFIG_FILENAME) in the folder hierarchy and load it if it exists
     */
    protected function load_local_config():void {
        $path_to_config = $this->find_config_path();
        if ( ! $path_to_config ) {
            return;
        }
        
        $local_config = json_decode( file_get_contents( $path_to_config ), true );
        if ( ! $local_config || json_last_error() !== JSON_ERROR_NONE ) {
            return;
        }

        if ( ! array_key_exists( $this::COMMAND_NAME, $local_config['tools'] ?? [] ) ) {
            return;
        }
        
        if ( array_key_exists( 'flags', $local_config['tools'][ $this::COMMAND_NAME ] ) ) {
            $this->flags = array_merge($this->flags, $local_config['tools'][ $this::COMMAND_NAME ]['flags']);
            $this->output( self::OUTPUT_AS_LOG, sprintf( 'Found local config %s and imported settings.', $this::LOCAL_CONFIG_FILENAME ) );
        }
    }

    private function find_config_path(): ?string {
        $directory = realpath( __DIR__ );

        while (true) {
            $candidate = $directory . DIRECTORY_SEPARATOR . $this::LOCAL_CONFIG_FILENAME;

            if (is_file($candidate)) {
                return $candidate;
            }

            $parent = dirname($directory);

            // dirname() returns the same path when the root is reached.
            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }
    }

    protected function validate_flags():bool {
        if ( ! in_array( $this->flags['format'], $this::VALID_OUTPUT_FORMATS ) ) {
            return false;
        }

        return true;
    }

    final protected function output(
        string $log_as,
        string $message,
        bool $is_fatal = false
    ):void {
        switch ( $this->flags['format'] ?? self::OUTPUT_FORMAT_DEFAULT ) {
            case self::OUTPUT_FORMAT_STDOUT: {
                switch ( $log_as ) {
                    case self::OUTPUT_AS_LOG: {
                        WP_CLI::log( $message );
                        break;
                    }
                    case self::OUTPUT_AS_WARNING: {
                        WP_CLI::warning( $message );
                        break;
                    }
                    case self::OUTPUT_AS_ERROR: {
                        WP_CLI::error( $message, $is_fatal );
                        break;
                    }
                }
                break;
            }
            case self::OUTPUT_FORMAT_JSON: {
                $this->json_output['log'][] = sprintf( '%s: %s', ucwords( $log_as ), $message );
                if ( $is_fatal ) {
                    die;
                }
                break;
            }
        }
    }
}
