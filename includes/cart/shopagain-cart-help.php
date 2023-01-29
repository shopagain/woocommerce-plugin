<?php
/**
 * To adjust the product data
 *
 * @param array  $item Cart item.
 * @return array Normalized cart item.
 */
function adjust_product_data( $item ) {
    return array(
        'product_id'=>$item['product_id'],
        'quantity'=>$item['quantity'],
        'variation_id'=>$item['variation_id'],
        'variation'=>$item['variation']
    );
}

/**
 * To encode product data
 *
 * @param array  $container_ids container ids.
 * @param array $values values.
 * @return array Composite product.
 */
function connect_encoded_composite($container_ids,$values) {
    $composite_product = array();
    foreach ($container_ids as $container_id => $container_values ) {
        $components = array();
        if (isset($container_values['attributes'])) {
            $components = array(
                'composite_id' => $container_values['composite_id'],
                'composite_quantity' => $values['quantity'],
                'item' => array(
                    'product_id' => $container_values['product_id'],
                    'quantity' => $container_values['quantity'],
                    'container_id' => $container_id,
                    'attributes' => $container_values['attributes'],
                    'variation_id' => isset( $container_values['variation_id'] ) ? $container_values['variation_id'] : null,
                )
            );
        } else {
            $components = array(
                'composite_id' => $container_values['composite_id'],
                'composite_quantity' => $values['quantity'],
                'item' => array(
                    'product_id' => $container_values['product_id'],
                    'quantity' => $container_values['quantity'],
                    'container_id' => $container_id,
                )
            );
        }
        array_push($composite_product, $components);
    }
    return $composite_product;
}


/**
 * Build a cart and return data
 *
 * @param ShopAgain $cart The woocommerce cart
 * @return array Normalized event data
 */
function shopagain_build_cart_data($cart) {
    $event_data = array(
        'CurrencySymbol' => get_woocommerce_currency_symbol(),
        'Currency' => get_woocommerce_currency(),
        '$value' => $cart->total,
        '$extra' => array(
            'Items' => array(),
            'SubTotal' => $cart->subtotal,
            'ShippingTotal' => $cart->shipping_total,
            'TaxTotal' => $cart->tax_total,
            'GrandTotal' => $cart->total
        ),
        'full_cart' => WC()->cart,
        'cart_hash' => WC()->cart->get_cart_hash(),
        'shopagain_cart_token' => isset($_COOKIE['shopagain_cart_token']) ? sanitize_key($_COOKIE['shopagain_cart_token']) : NULL,
        'checkout_url' => wc_get_checkout_url()
    );
    $sha_cart = array();
    $composite_products = array();
    $normal_products = array();
    $all_categories = array();
    $item_names = array();
    $all_tags = array();
    $item_count = 0;

    foreach ( $cart->get_cart() as $cart_item_key => $values ) {
        $product = $values['data'];
        $parent_product_id = $product->get_parent_id();

        if ($product->get_parent_id() == 0 ) {
            $parent_product_id = $product->get_id();
        }
        $categories_array = get_the_terms( $parent_product_id, 'product_cat' );
        if ( $categories_array && ! is_wp_error( $categories_array ) ) {
            $categories = wp_list_pluck( $categories_array, 'name' );

            foreach( $categories as $category ) {
                array_push( $all_categories, $category );
            }
        }
        $tags_array = get_the_terms( $parent_product_id, 'product_tag' );
        if ( $tags_array && ! is_wp_error( $tags_array ) ) {
            $tags = wp_list_pluck( $tags_array, 'name');

            foreach($tags as $tag) {
                array_push( $all_tags, $tag );
            }
        }

        $is_composite_child = false;

        $is_product_chained = is_product_chained($values);

        if ( class_exists( 'WC_Composite_Products' ) ) {
            $product_encoded = json_encode($product);
            $is_composite_child = wc_cp_is_composited_cart_item($values);
            $container = wc_cp_get_composited_cart_item_container($values);

            if ($product->get_type() == 'composite') {
                $composite_product = array();

                foreach (wc_cp_get_composited_cart_items($values) as $key => $val) {
                    $composite_product = connect_encoded_composite($val['composite_data'],$values);
                    break;
                }
                array_push($composite_products,$composite_product);
            } else {
                if (!$is_composite_child and !$is_product_chained) {
                    $normal_products[$cart_item_key] = adjust_product_data($values);
                }
            }
        } else {
            if (!$is_product_chained) {
                $normal_products[$cart_item_key] = adjust_product_data($values);
            }
        }

        $image = wp_get_attachment_url(get_post_thumbnail_id($product->get_id()));

        if ($image == false) {
            $image = wp_get_attachment_url(get_post_thumbnail_id($parent_product_id));
        }

        $event_data['$extra']['Items'][] = array(
            'Quantity' => $values['quantity'],
            'ProductID' => $parent_product_id,
            'VariantID' => $product->get_id(),
            'Name' => $product->get_name(),
            'URL' => $product->get_permalink(),
            'Images' => array(
                array(
                    'URL' => $image
                )
            ),
            'Categories' => $categories,
            'Variation' => $values['variation'],
            'SubTotal' => $values['line_subtotal'],
            'Total' => $values['line_subtotal_tax'],
            'LineTotal' => $values['line_total'],
            'Tax' => $values['line_tax'],
            'TotalWithTax' => $values['line_total'] + $values['line_tax']
        );
        $item_count += $values['quantity'];
        $all_categories = array_values(array_unique($all_categories));
        $event_data['Categories'] = $all_categories;
        $all_tags = array_values(array_unique($all_tags));
        $event_data['Tags'] = $all_tags;
        array_push($item_names, $product->get_name());
    }

    $event_data['Quantity'] = $item_count;
    $event_data['ItemNames'] = $item_names;
    $sha_cart['composite'] = $composite_products;
    $sha_cart['normal_products'] = $normal_products;
    $event_data['$extra']['CartRebuildKey'] = base64_encode(json_encode($sha_cart));

    return $event_data;
}

/**
 * Check if product instance of WooCommerce Chained Products plugin
 *
 * @param object $cart_item_properties cart properties key/values.
 * @return boolean
 */
function is_product_chained( $cart_item_properties ) {

    if ( class_exists( 'WC_Chained_Products' ) &&  ! empty( $cart_item_properties['chained_item_of'] ) ) {
        return true;
    }
    return false;
}
