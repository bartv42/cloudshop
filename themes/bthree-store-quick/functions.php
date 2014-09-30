<?php

// redirect to store homepage after logout
add_action('wp_logout',create_function('','wp_redirect(home_url());exit();'));



/***
 * Filter our Renewal products unless this user has specific products
 * in his order history 
 */
add_action( 'pre_get_posts', 'hidden_posts_pre_get_posts_query' );
function hidden_posts_pre_get_posts_query( $q ) {

	// not logged in --> filter
	// no products from 'blender-cloud-memberships' category in order history --> filter

	if( is_user_logged_in() && blendercloud_has_starter_product() ) { 
		return;
	}

	if (!$q->is_main_query() || !is_shop()) return;
	if ( ! is_admin() ) {
		$q->set( 'tax_query', array(array(
			'taxonomy' => 'product_cat',
			'field' => 'id',
			'terms' => array( 24 ),
			'operator' => 'NOT IN'
	    )));

	}
}

/***
 * Hide products in the 'Hidden Products' category
 */
add_action( 'pre_get_posts', 'custom_pre_get_posts_query' );
function custom_pre_get_posts_query( $q ) {

	// not logged in --> filter
	// no products from 'blender-cloud-memberships' category in order history --> filter

	if( is_user_logged_in() && blendercloud_has_starter_product() ) { 
		return;
	}

	if (!$q->is_main_query() || !is_shop()) return;
	if ( ! is_admin() ) {
		$q->set( 'tax_query', array(array(
			'taxonomy' => 'product_cat',
			'field' => 'id',
			'terms' => array( 34 ),
			'operator' => 'NOT IN'
	    )));

	}
}

/*** 
 * Does this user has a 'starter' subscription product in his order history?
 */
function blendercloud_has_starter_product() {

    // while we're updating the content
    return false;

	$user = wp_get_current_user();
	$user_id = $user->ID;
	$user_email = $user->email;

	$starter_products = array ( 14, 73, 119, 120 );

	$has_starter_product = false;

	foreach( $starter_products as $starter_product_id ) {
		if ( woocommerce_customer_bought_product( $user_email, $user_id , $starter_product_id ) ) {
			$has_starter_product = true;
			break;
		}
	}

	return( $has_starter_product );	
}	

/***
 * Set all virtual purchases to completed upon payment
 */
add_filter( 'woocommerce_payment_complete_order_status', 'virtual_order_payment_complete_order_status', 10, 2 );
 
function virtual_order_payment_complete_order_status( $order_status, $order_id ) {
  $order = new WC_Order( $order_id );
 
  if ( 'processing' == $order_status &&
       ( 'on-hold' == $order->status || 'pending' == $order->status || 'failed' == $order->status ) ) {
 
    $virtual_order = null;
 
    if ( count( $order->get_items() ) > 0 ) {
 
      foreach( $order->get_items() as $item ) {
 
        if ( 'line_item' == $item['type'] ) {
 
          $_product = $order->get_product_from_item( $item );
 
          if ( ! $_product->is_virtual() ) {
            // once we've found one non-virtual product we know we're done, break out of the loop
            $virtual_order = false;
            break;
          } else {
            $virtual_order = true;
          }
        }
      }
    }

    file_put_contents( '/tmp/store.log', "Setting order status for order $order_id : virtual_order=$virtual_order \n", FILE_APPEND );
 
    // virtual order, mark as completed
    if ( $virtual_order ) {
      return 'completed';
    }
  }
 
  // non-virtual order, return original status
  return $order_status;
}

/***
 * Removes related products
 */
function wc_remove_related_products( $args ) {
  return array();
}
add_filter('woocommerce_related_products_args','wc_remove_related_products', 10);

/***
 * Decide which PayPal gateway to show depending on cart contents.
 * Auto-renewing subscriptions -> paypal
 * Manually renewing subscriptions -> bo_manual_paypal
 */
function bo_payment_gateway_disable_country( $available_gateways ) {
	global $woocommerce;

	$force_manual_renewal = false;
	$contents = $woocommerce->cart->cart_contents;
	foreach( $contents as $item ) {
		if( isset( $item['variation']['attribute_pa_renewal-type'] ) ) {
			if( $item['variation']['attribute_pa_renewal-type'] == 'manual' ) {
				$force_manual_renewal = true;
			}
		}
	}

	if( $force_manual_renewal ) {
	    unset(  $available_gateways['paypal'] );
	} else {
	    unset(  $available_gateways['bo_manual_paypal'] );
	}

	return $available_gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'bo_payment_gateway_disable_country' );
		
?>