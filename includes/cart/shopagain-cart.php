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

function coonect_shopagain_composite_products_cart ($composite_products) {
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

function shopagain_pull_email($current_user) {
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

function shopagain_adjust_cart() {

    // Exit if in back-end
    if(is_admin()){return;}
    global $woocommerce;

    // Exit if not on cart page or no shopagain_adjust_cart parameter
    $current_url = shopagain_build_current_url();
    $utm_shopagain_adjust_cart = isset($_GET['shopagain_adjust_cart']) ? sanitize_text_field($_GET['shopagain_adjust_cart']) : '';
    if($current_url[0]!==wc_get_cart_url() || $utm_shopagain_adjust_cart==='') {return;}

    // Rebuild cart
    $woocommerce->cart->empty_cart(true);
    $woocommerce->cart->get_cart();

    $sha_cart = json_decode(base64_decode($utm_shopagain_adjust_cart), true);
    $composite_products = $sha_cart['composite'];
    $normal_products = $sha_cart['normal_products'];

    foreach ($normal_products as $product) {
        $cart_key = $woocommerce->cart->add_to_cart($product['product_id'],$product['quantity'],$product['variation_id'],$product['variation']);
    }

    if ( class_exists( 'WC_Composite_Products' ) ) {
        coonect_shopagain_composite_products_cart($composite_products);
    }

    $carturl = wc_get_cart_url();
    if ($current_url[0]==wc_get_cart_url()){
        header("Refresh:0; url=".$carturl);
    }
}

function shopagain_build_current_url() {
    $server_protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $server_host = sanitize_text_field(getenv( 'HTTP_HOST' ));
    $server_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
    $full_url = sanitize_url($server_protocol . '://' . $server_host . $server_uri);
    return explode( '?', $full_url);
}

/**
 * Insert tracking code code for tracking started checkout.
 *
 * @access public
 * @return void
 */
function shopagain_add_checkout_tracking() {
    global $current_user;
    wp_reset_query();
    wp_get_current_user();
    $cart = WC()->cart;
    $event_data = shopagain_build_cart_data( $cart );
    if ( empty($event_data['$extra']['Items']) ) { return; }
    $event_data['$service'] = 'woocommerce';
    unset($event_data['Tags']);
    unset($event_data['Quantity']);
    $email = shopagain_pull_email($current_user);
    $started_checkout_data = array(
        'email' => $email,
        'event_data' => $event_data,
        'pid' => Shopagain::get_shopagain_pid(),
        'uid' => Shopagain::get_shopagain_uid(),
    );
    wp_localize_script( 'shopagain_initiated_checkout', 'shopagain_checkout', $started_checkout_data );
}


add_action( 'wp_enqueue_scripts', 'shopagain_load_started_checkout' );


add_action('woocommerce_checkout_update_order_meta', function ($order_id, $posted) {

    // add current cart token to order metadata
    if (isset($_COOKIE['shopagain_cart_token'])) {
        $order = wc_get_order($order_id);
        $order->update_meta_data('shopagain_cart_token', sanitize_key($_COOKIE['shopagain_cart_token']));
        $order->save();

    }
    // generate new cart token
    $result = bin2hex(random_bytes(16));
    setcookie("shopagain_cart_token", $result, time() + 60 * 60 * 24 * 30, '/');
}, 1, 2);

// add_action('woocommerce_new_order', function ($order_id) {

// }, 1, 1);


/**
 *  Check if page is a checkout page, if so load the Started Checkout javascript file.
 *
 */
function shopagain_load_started_checkout() {
    $token = Shopagain::get_shopagain_option( 'shopagain_auth_key' );
    if ( ! $token ) { return; }
    $shopagain_localstorage_key = Shopagain::get_shopagain_option( 'shopagain_localstorage_key' );
    $checkout_script_url = Shopagain::get_shopagain_option( 'checkout_script_url' );
    $callback_url = Shopagain::get_shopagain_option( 'shopagain_webhook_url' );
    if( !$shopagain_localstorage_key || !$checkout_script_url ) { return; }
    if ( is_checkout() ) {
        wp_enqueue_script( 'shopagain_initiated_checkout', $checkout_script_url, null, null, true );
        wp_localize_script( 'shopagain_initiated_checkout', 'shopagain_public_key', array( 'token' => $token, "callback_url" => $callback_url));
        wp_add_inline_script('shopagain_initiated_checkout', 'var shopagain_localstorage_key = ' . wp_json_encode($shopagain_localstorage_key) . ';');
        shopagain_add_checkout_tracking();
    }
}

add_action( 'wp_loaded', 'shopagain_adjust_cart');
