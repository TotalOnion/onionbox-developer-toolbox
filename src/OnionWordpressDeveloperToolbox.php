<?php

namespace OnionWordpressDeveloperToolbox;

use OnionWordpressDeveloperToolbox\Controllers\Command\LdJsonCommand;
use OnionWordpressDeveloperToolbox\Controllers\Command\RedirectionAuditCommand;
use OnionWordpressDeveloperToolbox\Core;
use \WP_CLI;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    OnionWordpressDeveloperToolbox
 * @author     Ben Broadhurst <ben@totalonion.com>
 */
class OnionWordpressDeveloperToolbox {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      OnionWordpressDeveloperToolbox\Core\Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $pluginName    The string used to uniquely identify this plugin.
     */
    protected $pluginName;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = ONION_WORDPRESS_DEVELOPER_TOOLBOX_VERSION;
        $this->pluginName = ONION_WORDPRESS_DEVELOPER_TOOLBOX_NAME;

        $this->loader = new Core\Loader();
        $this->setLocale();
        $this->defineCommandHooks();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the OnionWordpressDeveloperToolbox\Core\Internationalisation class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function setLocale()
    {
        $i18n = new Core\I18n();
        $this->loader->addAction('plugins_loaded', $i18n, 'loadPluginTextdomain');

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function defineCommandHooks()
    {
        $this->loader->addAction( 'cli_init', new LdJsonCommand( $this->pluginName, $this->version ), 'register' );
        $this->loader->addAction( 'cli_init', new RedirectionAuditCommand( $this->pluginName, $this->version ), 'register' );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function getPluginName()
    {
        return $this->pluginName;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    OnionWordpressDeveloperToolbox\Core\Loader    Orchestrates the hooks of the plugin.
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function getVersion()
    {
        return $this->version;
    }
}
