<?php

namespace OnionWordpressDeveloperToolbox;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://totalonion.com
 * @since             1.0.0
 * @package           OnionWordpressDeveloperToolbox
 *
 * @wordpress-plugin
 * Plugin Name:       Onion Wordpress Developers Toolbox
 * Plugin URI:        https://github.com/TotalOnion/wordpress-developer-toolbox
 * Description:       A set of extra tools for Wordpress development and testing
 * Version:           1.1.4
 * Author:            Ben Broadhurst
 * Author URI:        https://totalonion.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       onion-wordpress-developer-toolbox
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('ONION_WORDPRESS_DEVELOPER_TOOLBOX_VERSION', '1.1.4');
define('ONION_WORDPRESS_DEVELOPER_TOOLBOX_NAME', 'onion-wordpress-developer-toolbox');
define('ONION_WORDPRESS_DEVELOPER_TOOLBOX_NAMESPACE', 'OnionWordpressDeveloperToolbox');
define('ONION_WORDPRESS_DEVELOPER_TOOLBOX_PLUGIN_FOLDER', __DIR__);
define('ONION_WORDPRESS_DEVELOPER_TOOLBOX_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloaders
require_once ONION_WORDPRESS_DEVELOPER_TOOLBOX_PLUGIN_FOLDER . '/autoload.php';
if (file_exists(ONION_WORDPRESS_DEVELOPER_TOOLBOX_PLUGIN_FOLDER . '/vendor/autoload.php')) {
    require_once ONION_WORDPRESS_DEVELOPER_TOOLBOX_PLUGIN_FOLDER . '/vendor/autoload.php';
}

// Activate and deactivation hooks
register_activation_hook(__FILE__, ['\OnionWordpressDeveloperToolbox\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\OnionWordpressDeveloperToolbox\Core\Deactivator', 'deactivate']);

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function onionWordpressDeveloperToolboxStart() {
    try {
        $plugin = new OnionWordpressDeveloperToolbox();
        $plugin->run();
    } catch (\Exception $e) {
        print_r($e->getTrace());
    }
}
onionWordpressDeveloperToolboxStart();
