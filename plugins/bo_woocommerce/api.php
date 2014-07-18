<?php
		
function blendercloud_api( $atts ) {

	// map blenderid to userid
	 
	$user_data = array();
	
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
		
		$subscriptions = WC_Subscriptions_Manager::get_users_subscriptions( $user_id );

		if( !empty( $subscriptions ) ) {
		
			// iterate over all subscriptions. Logic still needs to be defined
			foreach( $subscriptions as $subscription ) {
				
				if( $subscription['status'] == 'active' ) {
					
					$order_id			= $subscription['order_id'];
					$product_id			= $subscription['product_id'];
					$subscription_key	= WC_Subscriptions_Manager::get_subscription_key( $order_id, $product_id );
					$next_payment_date	= WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $user_id, 'mysql' );

					/*** TEST HACK ****/	
					// WC_Subscriptions_Manager::set_trial_expiration_date( $subscription_key, $user_id = '' );
					// WC_Subscriptions_Manager::set_next_payment_date( $subscription_key, $user_id = '' );
					/*** TEST HACK ****/	


					$tmp = array();
					$tmp['cloud_access'] = '1';
					$tmp['next_payment_date'] = $next_payment_date;

					switch( $subscription['product_id'] ) {
						
						case 14:
							$tmp['account_type'] = 'individual';
							break;
					
						case 73:
							$tmp['account_type'] = 'team';
					
							// use variation ID to get number of subscriptions
							$variation_id = $subscription['variation_id'];
							$sku = strtoupper( get_post_meta( $variation_id, '_sku', true ));
					
							$members=0;
					
							if( strpos( $sku, 'CLOUD-TEAM-') === 0 ) {
								$team_members = (int)substr( $sku, 11, strlen($sku)-11);
							}
										
							$tmp['team_members'] = $team_members;
							break;
					}
					
					$user_data['subscriptions'][]=$tmp;	
				}		
			}
		
		} else {
			$user_data['cloud_access'] = '0';
		}
		
		if( !isset( $user_data['subscriptions'] ) ) {
			$user_data['cloud_access'] = '0';
			$user_data['shop_id'] = 0;
		}

	} else {
		
		// user not found
		$user_data['shop_id'] = 0;
   	 	$user_data['cloud_access'] = '0';
		
	}
	
	//echo "<pre>";print_r($user_data);
	
	echo json_encode($user_data);
	die();
}
add_shortcode('blendercloud_api', 'blendercloud_api');	
	
?>