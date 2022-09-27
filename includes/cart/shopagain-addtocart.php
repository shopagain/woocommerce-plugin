<?php

add_action( 'woocommerce_add_to_cart', 'sha_added_to_cart_event', 25, 3 );

/**
 * Handle WP_Error
 * 
 * @param string $list String of product terms.
 * @return array
 */
function sha_strip_explode( $list ) {
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
function sha_addtocart_data($added_product, $quantity, $cart)
{
    $sha_cart = sha_build_cart_data( $cart );
    $added_product_id = $added_product->get_id();

    return array(
        '$value' => (float) $cart->total,
        'AddedItemCategories' => (array) sha_strip_explode(wc_get_product_category_list( $added_product_id )),
        'AddedItemDescription' => (string) $added_product->get_description(),
        'AddedItemImageURL' => (string) wp_get_attachment_url(get_post_thumbnail_id($added_product_id)),
        'AddedItemPrice' => (float) $added_product->get_price(),
        'AddedItemQuantity' => (int) $quantity,
        'AddedItemProductID' => (int) $added_product_id,
        'AddedItemProductName' => (string) $added_product->get_name(),
        'AddedItemSKU' => (string) $added_product->get_sku(),
        'AddedItemTags' => (array) sha_strip_explode(wc_get_product_tag_list( $added_product_id )),
        'AddedItemURL' => (string) $added_product->get_permalink(),
        'ItemNames' => (array) $sha_cart['ItemNames'],
        'Categories' => isset( $sha_cart['Categories'] ) ? (array) $sha_cart['Categories'] : [],
        'ItemCount' => (int) $sha_cart['Quantity'],
        'Tags' =>  isset( $sha_cart['Tags'] ) ? (array) $sha_cart['Tags'] : [],
        '$extra' => $sha_cart['$extra'],
        'shopagain_cart_token' => $_COOKIE['shopagain_cart_token']
    );
}

/**
 * Retrieve the raw response from the HTTP request
 * 
 * @param array $customer_identify Identifies the customer based on email or exchange_id.
 * @param array $data Cart and AddedItem data.
 * @returns null
 */
function sha_track_request($customer_identify, $data)
{
    $public_api_key = Shopagain::get_shopagain_option( 'shopagain_auth_key' );
    if ( ! $public_api_key ) { return; }

    $atc_data = array(
        'token' => $public_api_key,
        'event' => 'Added to Cart',
        'customer_properties' => $customer_identify,
        'properties' => $data
    );
    $base64_encoded = base64_encode(json_encode($atc_data));
    $url = Shopagain::get_shopagain_option( 'shopagain_webhook_url' )."track?data=" . $base64_encoded;

    wp_remote_get($url);
}

/**
 * Add customer data and trigger event
 *
 * @param int $product_id ID of item added to cart.
 * @param int $quantity Quantity of item added to cart.
 * @returns null
 */
function sha_added_to_cart_event($cart_item_key, $product_id, $quantity)
{
    if (!isset($_COOKIE["shopagain_cart_token"])) {
        $str=rand();
        $result = md5($str);
        setcookie("shopagain_cart_token", $result);
    }
    
    if (!isset($_COOKIE["__shopagain_id"])) {
        setcookie("__shopagain_id", get_option("admin_email"));
        $_COOKIE['__shopagain_id'] = get_option("admin_email");
        $sha_cookie = $_COOKIE['__shopagain_id'];
    } else {
        $sha_cookie = $_COOKIE['__shopagain_id'];
    }
    $sha_decoded_cookie = json_decode(base64_decode($sha_cookie), true);
    if ( isset( $sha_decoded_cookie['$exchange_id'] )) {
        $customer_identify = array('$exchange_id' => $sha_decoded_cookie['$exchange_id']);
    } elseif ( isset( $sha_decoded_cookie['$email'] )) {
        $customer_identify = array('$email' => $sha_decoded_cookie['$email']);
    } else { $customer_identify = ""; }

    
    $added_product = wc_get_product( $product_id );
    if ( ! $added_product instanceof WC_Product ) { return; }

    sha_track_request($customer_identify, sha_addtocart_data($added_product, $quantity, WC()->cart));
}
