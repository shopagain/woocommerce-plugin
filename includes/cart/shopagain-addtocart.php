<?php

add_action( 'woocommerce_add_to_cart', 'shopagain_added_to_cart_event', 25, 3 );
add_action('woocommerce_after_cart_totals', 'shopagain_cart_update_action', 25);
// add_action( 'woocommerce_cart_emptied', 'shopagain_cart_update_action', 25);
// add_action( 'woocommerce_remove_cart_item_from_session', 'shopagain_cart_update_action', 25);
// add_action( 'woocommerce_cart_item_removed', 'shopagain_cart_update_action', 25);
// add_action( 'woocommerce_cart_item_set_quantity', 'shopagain_cart_update_action', 25);

add_action( 'init', 'set_shopagain_cookie');
function set_shopagain_cookie() {
    if (!isset($_COOKIE["shopagain_cart_token"]) && !headers_sent()) {
        $result = bin2hex(random_bytes(16));
        setcookie("shopagain_cart_token", $result, time()+60*60*24*30, '/');
    }
}

/**
 * Handle WP_Error
 * 
 * @param string $list String of product terms.
 * @return array
 */
function shopagain_strip_explode( $list ) {
    if ( $list instanceof WP_Error ) { return []; }
    return explode(', ', strip_tags( $list ));
}

/**
 * Add cart data 
 *
 * @param object $added_product Added product data.
 * @param object $cart Cart data.
 * @return array
 */
function shopagain_addtocart_data($added_product, $quantity, $cart)
{
    $sha_cart = shopagain_build_cart_data( $cart );
    $added_product_id = $added_product->get_id();

    return array(
        '$value' => (float) $cart->total,
        'AddedItemCategories' => (array) shopagain_strip_explode(wc_get_product_category_list( $added_product_id )),
        'AddedItemDescription' => (string) $added_product->get_description(),
        'AddedItemImageURL' => (string) wp_get_attachment_url(get_post_thumbnail_id($added_product_id)),
        'AddedItemPrice' => (float) $added_product->get_price(),
        'AddedItemQuantity' => (int) $quantity,
        'AddedItemProductID' => (int) $added_product_id,
        'AddedItemProductName' => (string) $added_product->get_name(),
        'AddedItemSKU' => (string) $added_product->get_sku(),
        'AddedItemTags' => (array) shopagain_strip_explode(wc_get_product_tag_list( $added_product_id )),
        'AddedItemURL' => (string) $added_product->get_permalink(),
        'ItemNames' => (array) $sha_cart['ItemNames'],
        'Categories' => isset( $sha_cart['Categories'] ) ? (array) $sha_cart['Categories'] : [],
        'ItemCount' => (int) $sha_cart['Quantity'],
        'Tags' =>  isset( $sha_cart['Tags'] ) ? (array) $sha_cart['Tags'] : [],
        '$extra' => $sha_cart['$extra'],
        'shopagain_cart_token' => isset($_COOKIE['shopagain_cart_token']) ? sanitize_key($_COOKIE['shopagain_cart_token']) : NULL,
        'cart' => $sha_cart,
    );
}

/**
 * Retrieve the raw response from the HTTP request
 * 
 * @param array $customer_identify Identifies the customer based on email or exchange_id.
 * @param array $data Cart and AddedItem data.
 * @returns null
 */
function shopagain_track_request($customer_identify, $data, $event_name)
{
    $public_api_key = Shopagain::get_shopagain_option( 'shopagain_auth_key' );
    if ( ! $public_api_key ) { return; }
    $iso_time = current_time( 'mysql', true );
    $atc_data = array(
        'token' => $public_api_key,
        'event' => $event_name,
        'customer_properties' => $customer_identify,
        'properties' => $data,
        'created_at' => $iso_time,
    );
    $base64_encoded = base64_encode(json_encode($atc_data));
    $url = Shopagain::get_shopagain_option( 'shopagain_webhook_url' )."track?data=" . $base64_encoded;

    wp_remote_get(
        $url,
        array(
            'method' => 'GET',
            'blocking' => false,
        )
    );
}

/**
 * Add customer data and trigger event
 *
 * @param int $product_id ID of item added to cart.
 * @param int $quantity Quantity of item added to cart.
 * @returns null
 */
function shopagain_added_to_cart_event($cart_item_key, $product_id, $quantity)
{
    global $current_user;
    $public_api_key = Shopagain::get_shopagain_option( 'shopagain_auth_key' );
    if ( ! $public_api_key ) { return; }
    if (!isset($_COOKIE["shopagain_cart_token"])) {
        $result = bin2hex(random_bytes(16));
        setcookie("shopagain_cart_token", $result, time()+60*60*24*30, '/');
    }
    wp_get_current_user();
    $email = shopagain_pull_email($current_user);
    $customer_identify = array(
        'email' => $email,
        'pid' => Shopagain::get_shopagain_pid(),
        'uid' => Shopagain::get_shopagain_uid(),
    );
    $added_product = wc_get_product( $product_id );
    if ( ! $added_product instanceof WC_Product ) { return; }

    shopagain_track_request($customer_identify, shopagain_addtocart_data($added_product, $quantity, WC()->cart), 'carts/add');
    shopagain_cart_update_action();
}


function shopagain_cart_update_action(){
    global $current_user;
    $public_api_key = Shopagain::get_shopagain_option( 'shopagain_auth_key' );
    if ( ! $public_api_key ) { return; }
    if (!isset($_COOKIE["shopagain_cart_token"])) {
        $result = bin2hex(random_bytes(16));
        setcookie("shopagain_cart_token", $result, time()+60*60*24*30, '/');
    }
    wp_get_current_user();
    $email = shopagain_pull_email($current_user);
    $customer_identify = array(
        'email' => $email,
        'pid' => Shopagain::get_shopagain_pid(),
        'uid' => Shopagain::get_shopagain_uid(),
    );

    $cart = WC()->cart;
    $sha_cart = shopagain_build_cart_data( $cart );

    shopagain_track_request($customer_identify, $sha_cart, 'carts/update');
}
