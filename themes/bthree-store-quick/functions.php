<?php

// redirect to store homepage after logout
add_action('wp_logout',create_function('','wp_redirect(home_url());exit();'));



/***
 * Filter our Renewal products unless this user has specific products
 * in his order history 
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
			'terms' => array( 23 ),
			'operator' => 'NOT IN'
	    )));

	}
}

/*** 
 * Does this user has a 'starter' subscription product in his order history?
 */
function blendercloud_has_starter_product() {

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
		
?>