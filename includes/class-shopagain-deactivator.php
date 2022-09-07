<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://softeq.com
 * @since      1.0.0
 *
 * @package    Shopagain
 * @subpackage Shopagain/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Shopagain
 * @subpackage Shopagain/includes
 * @author     Softeq <jaman.khan@softeq.com>
 */
class Shopagain_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		delete_option( 'shopagain_auth' );
		delete_option( 'shopagain_wc_version' );

	}




}
