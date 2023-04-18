<?php

/**
 *
 * @link              https://shopagain.io/
 * @since             1.0.0
 * @package           Shopagain
 *
 * @wordpress-plugin
 * Plugin Name:       ShopAgain
 * Plugin URI:        https://shopagain.io/
 * Description:       The plugin helps to sync your WooCommerce data with ShopAgain.
 * Version:           1.0.6
 * Author:            ShopAgain
 * Author URI:        https://shopagain.io
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       shopagain.io
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SHOPAGAIN_VERSION', '1.0.6' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-shopagain-activator.php
 */
function activate_shopagain() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-shopagain-activator.php';
	Shopagain_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-shopagain-deactivator.php
 */
function deactivate_shopagain() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-shopagain-deactivator.php';
	Shopagain_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_shopagain' );
register_deactivation_hook( __FILE__, 'deactivate_shopagain' );



add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links' );

/* 
* Applied to the list of links to display on the plugins page (beside the activate/deactivate links).
*/

function add_action_links ( $actions ) {
	$mylinks = array(
		'<a href="' . admin_url( 'admin.php?page=shopagain_settings' ) . '">Settings</a>',
	);
	$actions = array_merge( $actions, $mylinks );
	return $actions;
}



/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-shopagain.php';


/* 
 * 
 */

if (!defined('SHOPAGAIN_URL')) {
    define('SHOPAGAIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('SHOPAGAIN_PATH')) {
    define('SHOPAGAIN_PATH', __DIR__ . '/');
}
if (!defined('SHOPAGAIN_BASE')) {
    define('SHOPAGAIN_BASE', plugin_basename(__FILE__));
}
/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_shopagain() {

	$plugin = new Shopagain();
	$plugin->run();

}
run_shopagain();
