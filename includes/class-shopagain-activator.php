<?php

/**
 * Fired during plugin activation
 *
 * @link       https://softeq.com
 * @since      1.0.0
 *
 * @package    Shopagain
 * @subpackage Shopagain/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Shopagain
 * @subpackage Shopagain/includes
 * @author     Softeq <jaman.khan@softeq.com>
 */
class Shopagain_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		update_option( 'shopagain_wc_version', SHOPAGAIN_VERSION);
	}
	

}
