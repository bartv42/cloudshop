<?php
		
function blendercloud_api( $atts ) {

	$user_data = array(	
		'shop_id' => '0',
		'cloud_access' => 0,
		'expiration_date' => '1970-01-01 00:00:00',
	);

	$last_expiration_date = new DateTime( '1970-01-01 00:00:00' );
	
	// map blenderid to userid
	$args = array(
		'search'         => $_GET['blenderid'],
		'search_columns' => array( 'user_login' )	
	);

	$user_query = new WP_User_Query( $args );	

 	// Get the results from the query, returning the first user
 	$users = $user_query->get_results();

	if( !empty( $users ) ) {



		$user_id = $users[0]->ID;
	
		$user_data['shop_id'] = $user_id;
		
		// process simple products (prepaid subscriptions)
		$order_ids = bo_get_all_user_orders( $user_id, 'completed' );
						
		foreach( $order_ids as $order_id ) {
			$order = new WC_Order( $order_id );
			
			$order_date = $order->order_date;

			$items = $order->get_items();		
			foreach ( $items as $item ) {
				
				$tmp = bo_empty_subscription_line();
                			
			    $product_id = $item['product_id'];
				$product = get_product( $product_id );
				$sku = $product->get_sku();

				$expiry_date = new DateTime( $order_date );
				
				$tmp['sku'] = $sku;
				
				switch( $sku ) {
					case 'cloud-prepaid-3':
					case 'cloud-prepaid-3-renewal':                    
						$expiry_date->modify('+3 month');;
						break;
					
					case 'cloud-prepaid-18':
						$expiry_date->modify('+18 month');					
						break;
						
					default: 
						continue 2;	// skip to next product
				}

				$tmp['expiration_date'] = $expiry_date->format('Y-m-d H:i:s');
							
				$tmp['subscription_status'] = 'prepaid';			
								
				$now = new DateTime("now");	

				if( $expiry_date > $now ) {
					$tmp['cloud_access'] = 1;
				}
				
				if( $expiry_date > $last_expiration_date ) {
					$last_expiration_date = $expiry_date;
				}
				
				$user_data['subscriptions'][] = $tmp;
			}	


			
		}
		
		// process recurring subscriptions
		$subscriptions = WC_Subscriptions_Manager::get_users_subscriptions( $user_id );
		if( !empty( $subscriptions ) ) {
		
			// iterate over all subscriptions.
			foreach( $subscriptions as $subscription_details ) {

				if( $subscription_details['status'] != 'trash' ) {
								
					$order_id			= $subscription_details['order_id'];
					$product_id			= $subscription_details['product_id'];

					$order = new WC_Order($order_id);

					// print_r($order);
					// $next_payment_date	= WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $user_id, 'mysql' );

					$subscription_key	= WC_Subscriptions_Manager::get_subscription_key( $order_id, $product_id );

					if ( $subscription_details['expiry_date'] == 0 && ! in_array( $subscription_details['status'], array( 'cancelled', 'switched' ) ) ) {

						$end_time = WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $user_id, 'mysql' );
						$end_timestamp = strtotime( $end_time );

					} else if ( in_array( $subscription_details['status'], array( 'cancelled', 'switched' ) ) ) {

						$end_of_prepaid_term = wc_next_scheduled_action( 'scheduled_subscription_end_of_prepaid_term', array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key ) );
							
						if ( false === $end_of_prepaid_term ) {
							$end_timestamp = strtotime( $subscription_details['end_date'] );
						} else {
							$end_timestamp = $end_of_prepaid_term;
						}
					} else {

						$end_timestamp = strtotime( $subscription_details['expiry_date'] );

					}
					
					$end_time = date("Y-m-d H:i:s", $end_timestamp );

					$product = get_product( $product_id );
					$sku = $product->get_sku();

					$tmp = bo_empty_subscription_line();
					$tmp['expiration_date'] = $end_time;
					$tmp['subscription_status'] = $subscription_details['status'];																				

					$expiry_date = new DateTime( $end_time );
					if( $expiry_date > $last_expiration_date ) {
						$last_expiration_date = $expiry_date;
					}
					
					$now = new DateTime("now");	

					$tmp['cloud_access'] = ($expiry_date > $now)? 1 : 0;
					
					$tmp['sku'] = $sku;
					
					// if order is refunded, stop access
					if( $order->status == 'refunded' ) {
						$tmp['expiration_date'] = $end_time;
						$tmp['subscription_status'] = 'refunded';
						$tmp['cloud_access'] = 0;
					}

					switch( $sku ) {
                        
						case 'cloud-subscription-1-renewal':						
						case 'cloud-subscription-3':
                            //$tmp['failed_payments'] = $subscription['failed_payments'];
							break;
					
						case 'cloud-subscription-team':
					
							// use variation ID to get number of subscriptions
							$variation_id = $subscription['variation_id'];
							$variation_sku = strtoupper( get_post_meta( $variation_id, '_sku', true ));
							$team_members=0;
					
							$sku_base_name = 'CLOUD-SUBSCRIPTION-TEAM-';
					
							if( strpos( $variation_sku, $sku_base_name ) === 0 ) {
								$team_members = (int)substr( $variation_sku, strlen($sku_base_name), strlen($variation_sku) - strlen($sku_base_name) );
							}
										
							$tmp['team_members'] = $team_members;
							break;
					}
					
					$user_data['subscriptions'][]=$tmp;	
				}		
			}
		
		} 

	} 
	
	// add one grace day to expiration
	$last_expiration_date->add ( DateInterval::createfromdatestring('+1 day') );
	
	$user_data['expiration_date'] = $last_expiration_date->format('Y-m-d H:i:s');

	$now = new DateTime("now");	
	if( $expiry_date > $now ) {
		$user_data['cloud_access'] = 1;
	}
	
	//echo "<pre>";print_r($user_data);
	
	echo json_encode($user_data, JSON_PRETTY_PRINT);
	die();
}
add_shortcode('blendercloud_api', 'blendercloud_api');	
	
	
// source: http://fusedpress.com/blog/get-all-user-orders-and-products-bought-by-user-in-woocommerce/
/**
 * Returns all the orders made by the user
 *
 * @param int $user_id
 * @param string $status (completed|processing|canceled|on-hold etc)
 * @return array of order ids
 */
function bo_get_all_user_orders($user_id,$status='completed'){
    if(!$user_id)
        return false;
    
    $orders=array();//order ids
     
    $args = array(
        'numberposts'     => -1,
        'meta_key'        => '_customer_user',
        'meta_value'      => $user_id,
        'post_type'       => 'shop_order',
        'post_status'     => 'publish',
        'tax_query'=>array(
                array(
                    'taxonomy'  =>'shop_order_status',
                    'field'     => 'slug',
                    'terms'     => $status
                    )
        )  
    );
    
    $posts=get_posts($args);
    //get the post ids as order ids
    $orders=wp_list_pluck( $posts, 'ID' );
    
    return $orders;
 
}


function bo_empty_subscription_line() {
	$tmp = array( 
		'sku' => '',
		'subscription_status' => 'undefined',
		'expiration_date' => '',
		'cloud_access' => 0,
	 );
	 
	 return $tmp;
}
	
?>