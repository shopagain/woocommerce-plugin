<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://shopagain.io
 * @since      1.0.0
 *
 * @package    Shopagain
 * @subpackage Shopagain/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Shopagain
 * @subpackage Shopagain/admin
 * @author     Shopagain <vedang@shopagain.io>
 */
class Shopagain_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/** 
	 * @var array plugin options. 
	 * */
	private $shopgain_options;

	/** 
	 * @var string auth url. 
	 * */
	private $shopagain_url;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->shopagain_url = Shopagain::SHOPAGAIN_URL;
		
		$this->shopgain_options = get_option('shopagain_auth');
		add_action( 'plugins_loaded', array( $this, 'initiate_admin' ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Shopagain_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Shopagain_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/shopagain-admin.css', array(), $this->version, 'all' );

	}

	/**
     * Handle settings page dependencies and add appropriate menu page.
     */
    public function initiate_admin()
    {
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            add_action( 'admin_menu', array( $this, 'add_shopagain_settings_oauth' ) );		
        } else {
            add_action( 'admin_menu', array( $this, 'add_shopagain_settings_inactive' ) );
        }
    }
	

	/**
     * Plugin menu tab in left navigation panel.
     *
     * @param callable $function The function to be called to output the content for this page.
     */
	 function add_menu_page( $function )
    {
      		add_menu_page(
            __('ShopAgain', 'ShopAgain'),
            __('ShopAgain', 'ShopAgain'),
            'manage_options',
            'shopagain_settings',
            array( $this, $function),
            plugin_dir_url( __FILE__ ) . 'image/shopagain-small.png',
			'58.5'
        );
		add_filter( 'plugin_action_links_' . SHOPAGAIN_URL, 'add_action_links' );
    }



	 /**
     * Add ShopAgain menu tab for new authentication process.
     */
    public function add_shopagain_settings_oauth()
    {	
        $this->add_menu_page( 'shopagain_settings_oauth' );
    }


	public function add_shopagain_settings_inactive()
    {	
        $this->add_menu_page( 'admin_notice__error' );
    }




	function admin_notice__error() {
		$class = 'notice notice-error';
		$message = __( 'Make sure woocommerce is activated in order to connect with your shopagain account.', 'inactive-woocommerce-message' );
	 
		// printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
	}

	

    /**
     * Settings page content for new authentication process.
     */
    public function shopagain_settings_oauth()
    {	
	        include_once( __DIR__ . '/partials/shopagain-admin-display.php' );
    }


	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Shopagain_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Shopagain_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/shopagain-admin.js', array( 'jquery' ), $this->version, false );

	}

}
