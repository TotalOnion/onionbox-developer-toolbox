<?php

namespace OnionWordpressDeveloperToolbox\Core;

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://totalonion.com
 * @since      1.0.0
 *
 * @package    OnionWordpressDeveloperToolbox
 * @subpackage OnionWordpressDeveloperToolbox/Core
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    OnionWordpressDeveloperToolbox
 * @subpackage OnionWordpressDeveloperToolbox/Core
 * @author     Ben Broadhurst <ben@totalonion.com>
 */
class I18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function loadPluginTextdomain()
    {
        load_plugin_textdomain(
            'onion-wordpress-developer-toolbox',
            false,
            dirname(ONION_WORDPRESS_DEVELOPER_TOOLBOX_PLUGIN_FOLDER . '/languages/')
        );
    }
}
