<?php
 /**
 * All the functions are related to order
 *
 * @link       https://shopagain.io
 * @since      1.0.0
 *
 * @package    Shopagain
 * @subpackage Shopagain/includes/cart
 */


if ( ! defined( 'WPINC' ) ) exit; // Exit if accessed directly

function coonect_sha_composite_products_cart ($composite_products) {
    foreach ($composite_products as $product) {
        $container = array();
        foreach ($product as $i => $v) {
            $item = $v['item'];
            $container_id = $item['container_id'];
            if (isset($item['attributes'])) {
                $container[$container_id] = array(
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'variation_id' => $item['variation_id'],
                    'attributes' => $item['attributes'],
                );
            } else {
                $container[$container_id] = array(
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                );
            }
        }
        $added = WC_CP()->cart->add_composite_to_cart( $v['composite_id'], $v['composite_quantity'], $container );
    }
}

function sha_pull_email($current_user) {
    $email = '';
    if ($current_user->user_email) {
        $email = $current_user->user_email;
    } else {
        // See if current user is a commenter
        $commenter = wp_get_current_commenter();
        if ($commenter['comment_author_email']) {
            $email = $commenter['comment_author_email'];
        }
    }
    return $email;
}

function sha_adjust_cart() {

    // Exit if in back-end
    if(is_admin()){return;}
    global $woocommerce;

    // Exit if not on cart page or no sha_adjust_cart parameter
    $current_url = sha_build_current_url();
    $utm_sha_adjust_cart = isset($_GET['sha_adjust_cart']) ? $_GET['sha_adjust_cart'] : '';
    if($current_url[0]!==wc_get_cart_url() || $utm_sha_adjust_cart==='') {return;}

    // Rebuild cart
    $woocommerce->cart->empty_cart(true);
    $woocommerce->cart->get_cart();

    $sha_cart = json_decode(base64_decode($utm_sha_adjust_cart), true);
    $composite_products = $sha_cart['composite'];
    $normal_products = $sha_cart['normal_products'];

    foreach ($normal_products as $product) {
        $cart_key = $woocommerce->cart->add_to_cart($product['product_id'],$product['quantity'],$product['variation_id'],$product['variation']);
    }

    if ( class_exists( 'WC_Composite_Products' ) ) {
        coonect_sha_composite_products_cart($composite_products);
    }

    $carturl = wc_get_cart_url();
    if ($current_url[0]==wc_get_cart_url()){
        header("Refresh:0; url=".$carturl);
    }
}

function sha_build_current_url() {
    $server_protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $server_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $server_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    return explode( '?', $server_protocol . '://' . $server_host . $server_uri );
}

/**
 * Insert tracking code code for tracking started checkout.
 *
 * @access public
 * @return void
 */
function sha_add_checkout_tracking($checkout) {
    global $current_user;
    wp_reset_query();
    wp_get_current_user();
    $cart = WC()->cart;
    $event_data = sha_build_cart_data( $cart );
    if ( empty($event_data['$extra']['Items']) ) { return; }
    $event_data['$service'] = 'woocommerce';
    unset($event_data['Tags']);
    unset($event_data['Quantity']);
    $email = sha_pull_email($current_user);
    $started_checkout_data = array(
        'email' => $email,
        'event_data' => $event_data
    );
    wp_localize_script( 'sha_initiated_checkout', 'sha_checkout', $started_checkout_data );
}

add_action( 'woocommerce_after_checkout_form', 'sha_add_checkout_tracking' );


add_action( 'wp_enqueue_scripts', 'sha_load_started_checkout' );


add_action('woocommerce_checkout_update_order_meta',function( $order_id, $posted ) {
    // add current cart token to order metadata
    $shopagain_cart_token = $_COOKIE['shopagain_cart_token'];
    if($shopagain_cart_token){
        $order = wc_get_order( $order_id );
        $order->update_meta_data( 'shopagain_cart_token', $shopagain_cart_token );
        $order->save();
    }

} , 10, 2);

add_action('woocommerce_new_order', function ($order_id) {
    // generate new cart token
    $str=rand();
    $result = md5($str);
    setcookie("shopagain_cart_token", $result);
}, 11, 1);


/**
 *  Check if page is a checkout page, if so load the Started Checkout javascript file.
 *
 */
function sha_load_started_checkout() {
    $token = Shopagain::get_shopagain_option( 'shopagain_auth_key' );
    if ( ! $token ) { return; }
    if ( is_checkout() ) {
        wp_enqueue_script( 'sha_initiated_checkout', plugins_url( '/js/shopagain-checkout.js', __FILE__ ), null, null, true );
        wp_localize_script( 'sha_initiated_checkout', 'public_key', array( 'token' => $token, "callback_url" => Shopagain::get_shopagain_option( 'shopagain_webhook_url' )));
    }
}

add_action( 'wp_loaded', 'sha_adjust_cart');
