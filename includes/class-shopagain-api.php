<?php
 /**
 * ShopAgainAPI
 *
 * This is used to  handles API endpoint requests.
 *
 *
 * @since      1.0.0
 * @package    Shopagain
 * @subpackage Shopagain/includes
 * @author     Shopagain <vedang@shopagain.io>
 */

use MailPoet\Config\PopulatorData\Templates\Faith;

if ( ! defined( 'WPINC' ) ) exit; // Exit if accessed directly

class Shopagain_API
{

    const VERSION = '1.0.6';
    const SHOPAGAIN_BASE_URL = 'shopagain/v1';
    const ORDERS_ENDPOINT = 'orders';
    const EXTENSION_VERSION_ENDPOINT = 'version';
    const PRODUCTS_ENDPOINT = 'products';
    const OPTIONS_ENDPOINT = 'options';
    const DISABLE_ENDPOINT = 'disable';

    // API RESPONSES
    const API_RESPONSE_CODE = 'status_code';
    const API_RESPONSE_ERROR = 'error';
    const API_RESPONSE_REASON = 'reason';
    const API_RESPONSE_SUCCESS = 'success';

    // HTTP CODES
    const STATUS_CODE_HTTP_OK = 200;
    const STATUS_CODE_NO_CONTENT = 204;
    const STATUS_CODE_BAD_REQUEST = 400;
    const STATUS_CODE_AUTHENTICATION_ERROR = 401;
    const STATUS_CODE_AUTHORIZATION_ERROR = 403;
    const STATUS_CODE_INTERNAL_SERVER_ERROR = 500;

    const DEFAULT_RECORDS_PER_PAGE = '50';
    const DATE_MODIFIED = 'post_modified_gmt';
    const POST_STATUS_ANY = 'any';

    const ERROR_KEYS_NOT_PASSED = 'consumer key or consumer secret not passed';
    const ERROR_CONSUMER_KEY_NOT_FOUND = 'consumer_key not found';

    const PERMISSION_READ = 'read';
    const PERMISSION_WRITE = 'write';
    const PERMISSION_READ_WRITE = 'read_write';
    const PERMISSION_METHOD_MAP = array(
        self::PERMISSION_READ => array( 'GET' ),
        self::PERMISSION_WRITE => array( 'POST' ),
        self::PERMISSION_READ_WRITE => array( 'GET', 'POST' ),
    );

    public static function build_version_payload( $is_updating = false )
    {
        return array(
            'plugin_version' => self::VERSION
        );
    }

}

function shopagain_count_loop(WP_Query $loop)
{
    $loop_ids = array();
    while ($loop->have_posts()) {
        $loop->the_post();
        $loop_id = get_the_ID();
        array_push($loop_ids, $loop_id);
    }
    return $loop_ids;
}

function shopagain_validate_request($request)
{
    $consumer_key = $request->get_param('consumer_key');
    $consumer_secret = $request->get_param('consumer_secret');
    if (empty($consumer_key) || empty($consumer_secret)) {
        return shopagain_validation_response(
            true,
            Shopagain_API::STATUS_CODE_BAD_REQUEST,
            Shopagain_API::ERROR_KEYS_NOT_PASSED,
            false
        );
    }

    global $wpdb;
    // this is stored as a hash so we need to query on the hash
    $key = hash_hmac('sha256', $consumer_key, 'wc-api');
    $user = $wpdb->get_row(
        $wpdb->prepare(
            "
    SELECT consumer_key, consumer_secret
    FROM {$wpdb->prefix}woocommerce_api_keys
    WHERE consumer_key = %s
     ",
            $key
        )
    );

    if ($user->consumer_secret == $consumer_secret) {
        return shopagain_validation_response(
            false,
            Shopagain_API::STATUS_CODE_HTTP_OK,
            null,
            true
        );
    }
    return shopagain_validation_response(
        true,
        Shopagain_API::STATUS_CODE_AUTHORIZATION_ERROR,
        Shopagain_API::ERROR_CONSUMER_KEY_NOT_FOUND,
        false
    );
}

/**
 * Validate incoming requests to custom endpoints.
 *
 * @param WP_REST_Request $request Incoming request object.
 * @return bool|WP_Error True if validation succeeds, otherwise WP_Error to be handled by rest server.
 */
function shopagain_validate_request_v2( WP_REST_Request $request )
{
    $consumer_key = $request->get_param( 'consumer_key' );
    $consumer_secret = $request->get_param( 'consumer_secret' );
    if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
        return new WP_Error(
            'shopgain_missing_key_secret',
            'One or more of consumer key and secret are missing.',
            array( 'status' => Shopagain_API::STATUS_CODE_AUTHENTICATION_ERROR )
        );
    }

    global $wpdb;
    // this is stored as a hash so we need to query on the hash
    $key = hash_hmac('sha256', $consumer_key, 'wc-api');
    $user = $wpdb->get_row(
        $wpdb->prepare(
            "
    SELECT consumer_key, consumer_secret, permissions
    FROM {$wpdb->prefix}woocommerce_api_keys
    WHERE consumer_key = %s
     ",
            $key
        )
    );
    // User query lookup on consumer key can return null or false.
    if ( ! $user ) {
        return new WP_Error(
            'shopgain_cannot_authentication',
            'Cannot authenticate with provided credentials.',
            array( 'status' => 401 ) );
    }
    // User does not have proper permissions.
    if ( ! in_array( $request->get_method(), Shopagain_API::PERMISSION_METHOD_MAP[ $user->permissions ] ) ) {
        return new WP_Error(
            'shopgain_improper_permissions',
            'Improper permissions to access this resource.',
            array( 'status' => Shopagain_API::STATUS_CODE_AUTHORIZATION_ERROR )
        );
    }
    // Success!
    if ( $user->consumer_secret == $consumer_secret ) {
        return true;
    }
    // Consumer secret didn't match or some other issue authenticating.
    return new WP_Error(
        'shopgain_invalid_authentication',
        'Invalid authentication.',
        array( 'status' => Shopagain_API::STATUS_CODE_AUTHENTICATION_ERROR )
    );
}

function shopagain_validation_response($error, $code, $reason, $success)
{
    return array(
        Shopagain_API::API_RESPONSE_ERROR => $error,
        Shopagain_API::API_RESPONSE_CODE => $code,
        Shopagain_API::API_RESPONSE_REASON => $reason,
        Shopagain_API::API_RESPONSE_SUCCESS => $success,
    );
}

function shopagain_process_resource_args($request, $post_type)
{
    $page_limit = $request->get_param('page_limit');
    if (empty($page_limit)) {
        $page_limit = Shopagain_API::DEFAULT_RECORDS_PER_PAGE;
    }
    $date_modified_after = $request->get_param('date_modified_after');
    $date_modified_before = $request->get_param('date_modified_before');
    $page = $request->get_param('page');

    $args = array(
        'post_type' => $post_type,
        'posts_per_page' => $page_limit,
        'post_status' => Shopagain_API::POST_STATUS_ANY,
        'paged' => $page,
        'date_query' => array(
            array(
                'column' => Shopagain_API::DATE_MODIFIED,
                'after' => $date_modified_after,
                'before' => $date_modified_before
            )
        ),
    );
    return $args;
}

function shopagain_get_store_timezone()
{
    $timezone_string = get_option( 'timezone_string' );
 
    if ( $timezone_string ) {
        return $timezone_string;
    }
 
    $offset  = (float) get_option( 'gmt_offset' );
    $hours   = (int) $offset;
    $minutes = ( $offset - $hours );
 
    $sign      = ( $offset < 0 ) ? '-' : '+';
    $abs_hour  = abs( $hours );
    $abs_mins  = abs( $minutes * 60 );
    $tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
 
    return $tz_offset;
}

function shopagain_get_orders_count(WP_REST_Request $request)
{
    $validated_request = shopagain_validate_request($request);
    if ($validated_request['error'] === true) {
        return $validated_request;
    }

    $args = shopagain_process_resource_args($request, 'shop_order');

    $loop = new WP_Query($args);
    $data = shopagain_count_loop($loop);
    return array('order_count' => $loop->found_posts);
}

function shopagain_get_products_count(WP_REST_Request $request)
{
    $validated_request = shopagain_validate_request($request);
    if ($validated_request['error'] === true) {
        return $validated_request;
    }

    $args = shopagain_process_resource_args($request, 'product');
    $loop = new WP_Query($args);
    $data = shopagain_count_loop($loop);
    return array('product_count' => $loop->found_posts);
}

function shopagain_get_products(WP_REST_Request $request)
{
    $validated_request = shopagain_validate_request($request);
    if ($validated_request['error'] === true) {
        return $validated_request;
    }

    $args = shopagain_process_resource_args($request, 'product');

    $loop = new WP_Query($args);
    $data = shopagain_count_loop($loop);
    return array('product_ids' => $data);
}

function shopagain_get_orders(WP_REST_Request $request)
{
    $validated_request = shopagain_validate_request($request);
    if ($validated_request['error'] === true) {
        return $validated_request;
    }

    $args = shopagain_process_resource_args($request, 'shop_order');

    $loop = new WP_Query($args);
    $data = shopagain_count_loop($loop);
    return array('order_ids' => $data);
}

function shopagain_get_timezone(WP_REST_Request $request)
{
    $validated_request = shopagain_validate_request($request);
    if ($validated_request['error'] === true) {
        return $validated_request;
    } 
    return shopagain_get_store_timezone();
}

function shopagain_get_store_details(WP_REST_Request $request)
{
    $validated_request = shopagain_validate_request($request);
    if ($validated_request['error'] === true) {
        return $validated_request;
    }

    return array(
        'tz_offset' => shopagain_get_store_timezone(),
        'order_received_url' => wc_get_endpoint_url( 'order-received'),
        'cart_url' => wc_get_cart_url() 
    );
}


/**
 * Handle GET request to /shopgain/v1/version. Returns the current version and if
 * the installed version is the most recent available in the plugin directory.
 *
 * @return array
 */
function shopagain_get_extension_version()
{
    return Shopagain_API::build_version_payload();
}

/**
 * Handle POST request to /shopgain/v1/options and update plugin options.
 *
 * @param WP_REST_Request $request
 * @return bool|mixed|void|WP_Error
 */
function shopagain_update_options( WP_REST_Request $request )
{
    $body = json_decode( $request->get_body(), $assoc = true );
    if ( ! $body ) {
        return new WP_Error(
            'shopgain_empty_body',
            'Body of request cannot be empty.',
            array( 'status' => 400 )
        );
    }

    $options = get_option( 'shopagain_auth' );
    if ( ! $options ) {
        $options = array();
    }

    $updated_options = array_replace( $options, $body );
    $is_update = (bool) array_diff_assoc( $options, $updated_options );
    // If there is no change between existing and new settings `update_option` returns false. Want to distinguish
    // between that scenario and an actual problem when updating the plugin options.
    if ( ! update_option( 'shopagain_auth', $updated_options ) && $is_update ) {
        return new WP_Error(
            'shopgain_update_failed',
            'Options update failed.',
            array(
                'status' => Shopagain_API::STATUS_CODE_INTERNAL_SERVER_ERROR,
                'options' => get_option( 'shopagain_auth' )
            )
        );
    }

    // Return plugin version info so this can be saved in Shopgain when setting up integration for the first time.
    return array_merge( $updated_options, Shopagain_API::build_version_payload() );
}

/**
 * Handle GET request to /shopgain/v1/options and return options set for plugin.
 *
 * @return array Shopgain plugin options.
 */
function shopagain_get_options()
{
    return get_option( 'shopagain_auth' );
}

/**
 * Handle POST request to /shopgain/v1/disable by deactivating the plugin.
 *
 * @param WP_REST_Request $request Incoming request object.
 * @return WP_Error|WP_REST_Response
 */
function shopagain_disable_plugin( WP_REST_Request $request )
{
    $body = json_decode( $request->get_body(), $assoc = true );
    // Verify body contains required data.
    if ( ! isset( $body['shopagain_auth_key'] ) ) {
        return new WP_Error(
            'shopgain_disable_failed',
            'Disable plugin failed, \'shopagain_auth_key\' missing from body.',
            array( 'status' => Shopagain_API::STATUS_CODE_BAD_REQUEST )
        );
    }
    // // Verify keys match if set in WordPress options table

    $public_api_key = Shopagain::get_shopagain_option( 'shopagain_auth_key' );
    if ( $public_api_key && $body['shopagain_auth_key'] !== $public_api_key ) {
        return new WP_Error(
            'shopgain_disable_failed',
            'Disable plugin failed, \'shopagain_auth_key\' does not match key set in WP options.',
            array( 'status' => Shopagain_API::STATUS_CODE_BAD_REQUEST )
        );
    }

    deactivate_plugins( SHOPAGAIN_BASE );
    return new WP_REST_Response( null, Shopagain_API::STATUS_CODE_NO_CONTENT);
}


function get_shopagain_option( $option_value)
{   

    $option = shopagain_get_options();
    if ( isset( $option[ $option_value ] ) ) {
        $value = $option[ $option_value ];
    } 
    return $value ? $value : False;
}

function get_shopagain_cookie( $cookie_name )
{
    $cookie_prefix = get_shopagain_option( 'cookie_prefix' );
    if(!$cookie_prefix){
        return NULL;
    }
    $full_cookie_key = $cookie_prefix . "-" . $cookie_name;
    return isset ($_COOKIE[$full_cookie_key]) ? sanitize_text_field($_COOKIE[$full_cookie_key]) : NULL;
}

function get_shopagain_pid() {
    $pid_cookie = get_shopagain_cookie( 'pid' );
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

function get_shopagain_uid() {
    $pid_cookie = get_shopagain_cookie( 'uid' );
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


add_action('rest_api_init', function () {
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, Shopagain_API::EXTENSION_VERSION_ENDPOINT, array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'shopagain_get_extension_version',
        'permission_callback' => '__return_true',
    ));
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, 'orders/count', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'shopagain_get_orders_count',
        'permission_callback' => '__return_true',
    ));
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, 'products/count', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'shopagain_get_products_count',
        'permission_callback' => '__return_true',
    ));
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, Shopagain_API::ORDERS_ENDPOINT, array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'shopagain_get_orders',
        'args' => array(
            'id' => array(
                'validate_callback' => 'is_numeric'
            ),
        ),
        'permission_callback' => '__return_true',
    ));
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, Shopagain_API::PRODUCTS_ENDPOINT, array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'shopagain_get_products',
        'args' => array(
            'id' => array(
                'validate_callback' => 'is_numeric'
            ),
        ),
        'permission_callback' => '__return_true',
    ));
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, Shopagain_API::OPTIONS_ENDPOINT, array(
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'shopagain_update_options',
            'permission_callback' => 'shopagain_validate_request_v2',
        ),
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'shopagain_get_options',
            'permission_callback' => 'shopagain_validate_request_v2',
        )
    ));
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, Shopagain_API::DISABLE_ENDPOINT, array(
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'shopagain_disable_plugin',
            'permission_callback' => 'shopagain_validate_request_v2',
        )
    ));
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, 'timezone', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'shopagain_get_timezone',
        'permission_callback' => '__return_true',
    ));
    register_rest_route(Shopagain_API::SHOPAGAIN_BASE_URL, 'store_details', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'shopagain_get_store_details',
        'permission_callback' => '__return_true',
    ));
});
