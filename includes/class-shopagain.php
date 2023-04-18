<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://shopagain.io
 * @since      1.0.0
 *
 * @package    Shopagain
 * @subpackage Shopagain/includes
 */

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
 * @package    Shopagain
 * @subpackage Shopagain/includes
 * @author     Shopagain <vedang@shopagain.io>
 */
class Shopagain {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Shopagain_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	
	const AUTH_NAME = 'shopagain_auth';
	const SHOPAGAIN_URL = 'https://app.shopagain.io/';

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
		if ( defined( 'SHOPAGAIN_VERSION' ) ) {
			$this->version = SHOPAGAIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'shopagain';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		
		if ( !is_admin() ) {
			$this->load_shopagain_script();
        }
	}


	/**
	 * Include the ShopAgain customized JS script for popups
	 *
	 * @since    1.0.1
	 */

	private function load_shopagain_script(){
		
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

			add_action('wp_enqueue_scripts', 'mslb_public_scripts');	

			function mslb_public_scripts(){
				$public_api_key = Shopagain::get_shopagain_option( 'shopagain_auth_key' );

				$is_thank_you = "0";
				if ( is_checkout() && !empty( is_wc_endpoint_url('order-received') ) ) {
					$is_thank_you = "1";	
				}
				// Return void if auth key is null OR in check out page
				// if($public_api_key == '' || (is_checkout() && $is_thank_you != "1" )){
				// 	return;
				// }
				if(!$public_api_key){
					return;
				}
				$shopagain_script_url = Shopagain::get_shopagain_option( 'shopagain_script_url' );
				if(!$shopagain_script_url){
					return;
				}

				$obj = get_queried_object();
				$handle = '';
				if($obj && property_exists($obj, 'post_name')){
					$handle = $obj->post_name;
				}

				global $current_user;
				wp_get_current_user();
				$email = shopagain_pull_email($current_user);

				$params = array(
					'is_shop' => is_shop(),
					'is_product_category' => is_product_category(),
					'is_product_tag' => is_product_tag(),
					'is_product' => is_product(),
					'is_cart' => is_cart(),
					'is_checkout' => is_checkout(),
					'handle' => $handle,
					'is_search' => is_search(),
					'is_thank_you' => $is_thank_you,
					'email' => $email,
					//'wp_query' => get_queried_object()
				);

				$shopagain_script_loader_url = plugins_url( '/js/shopagain_script_loader.js', __FILE__ );
				wp_enqueue_script( 'shopagain_script_loader', $shopagain_script_loader_url, null, null, true );
				wp_add_inline_script('shopagain_script_loader', 'var shopagain_script_src=' . wp_json_encode($shopagain_script_url) . ';', 'before');
				wp_localize_script( 'shopagain_script_loader', 'shopagain_script_query_params', $params );	
			}
        }
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Shopagain_Loader. Orchestrates the hooks of the plugin.
	 * - Shopagain_i18n. Defines internationalization functionality.
	 * - Shopagain_Admin. Defines all hooks for the admin area.
	 * - Shopagain_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-shopagain-loader.php';

	 	/**
		 * The class responsible for defining all APIs that call from outside.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-shopagain-api.php';

		include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cart/shopagain-cart-help.php';
		include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cart/shopagain-addtocart.php';
		include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cart/shopagain-cart.php';
		include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cart/shopagain-product.php';



		// include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cart/wck-cart-rebuild.php';
		// include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cart/wck-added-to-cart.php';
		// include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cart/wck-cart-functions.php';
		// include plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cart/wck-viewed-product.php';


		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-shopagain-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-shopagain-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-shopagain-public.php';

		

		$this->loader = new Shopagain_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Shopagain_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Shopagain_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Shopagain_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Shopagain_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Shopagain_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Retrieve the value shopagain auth.
	 *
	 * @since     1.0.0
	 * @return    string    The auth value
	 */

	public static function get_shopagain_option( $option_value)	{   

		$option = get_option( self::AUTH_NAME );
		if ( isset( $option[ $option_value ] ) ) {
			$value = $option[ $option_value ];
		} 
		return isset($value) ? $value : False;
	}

	public static function get_shopagain_cookie( $cookie_name ){
		$cookie_prefix = self::get_shopagain_option( 'cookie_prefix' );
		if(!$cookie_prefix){
			return NULL;
		}
		$full_cookie_key = $cookie_prefix . "-" . $cookie_name;
		return isset ($_COOKIE[$full_cookie_key]) ? sanitize_text_field($_COOKIE[$full_cookie_key]) : NULL;
	}

	public static function get_shopagain_pid() {
		$pid_cookie = self::get_shopagain_cookie( 'pid' );
		$is_pid_cookie_valid = FALSE;
		$pid = NULL; 
		if($pid_cookie){
			$pid_cookie = stripslashes($pid_cookie);
			$pid = json_decode($pid_cookie);
			if($pid && wp_is_uuid($pid)){
				$is_pid_cookie_valid = TRUE;
			}
		}

		if ($is_pid_cookie_valid) {
			return $pid;
		} else {
			return NULL;
		}  
	}

	public static function get_shopagain_uid() {
		$pid_cookie = self::get_shopagain_cookie( 'uid' );
		$is_pid_cookie_valid = FALSE;
		$pid = NULL; 
		if($pid_cookie){
			$pid_cookie = stripslashes($pid_cookie);
			$pid = json_decode($pid_cookie);
			if($pid && wp_is_uuid($pid)){
				$is_pid_cookie_valid = TRUE;
			}
		}

		if ($is_pid_cookie_valid) {
			return $pid;
		} else {
			return NULL;
		}  
	}
}
