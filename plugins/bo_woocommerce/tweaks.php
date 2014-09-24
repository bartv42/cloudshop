<?php
/*
Plugin Name: A bag of tweaks
Plugin URI: http://en.bainternet.info
Description: 
Version: 
Author: Bart Veldhuizen
Author URI: http://www.blendernation.com
*/


if( 'store.blender.org' == $_SERVER['SERVER_NAME'] ) {

	// production
	DEFINE( 'BO_BRAINTREE_MERCHANT_ID_EUR', 'cloudblenderEUR');
	DEFINE( 'BO_BRAINTREE_MERCHANT_ID_USD', 'cloudblenderUSD');

} else {

	// development
	DEFINE( 'BO_BRAINTREE_MERCHANT_ID_EUR', 'BlenderInstituteEUR');
	DEFINE( 'BO_BRAINTREE_MERCHANT_ID_USD', '46njsqh7fdhyk3fc');
}

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include( 'gateway_manual_paypal.php' );

function fontawesome_dashboard() {
   wp_enqueue_style('fontawesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css', '', '4.0.3', 'all'); 
}
 
add_action('admin_init', 'fontawesome_dashboard');

add_action( 'restrict_manage_posts', 'wpse45436_admin_posts_filter_restrict_manage_posts' );
/**
 * First create the dropdown
 * 
 * @author Ohad Raz
 * 
 * @return void
 */
function wpse45436_admin_posts_filter_restrict_manage_posts(){
    $type = 'post';
    if (isset($_GET['post_type'])) {
        $type = $_GET['post_type'];
    }

    //only add filter to post type you want
    if ('shop_order' == $type){
        //change this to the list of values you want to show
        //in 'label' => 'value' format
        $values = array(
            'PayPal' => 'PayPal', 
            'Bank transfer' => 'Direct Bank Transfer',
			'BitCoin' => 'Bitcoin Payment'
        );
        ?>
		<i class="fa fa-money"></i>
        <select name="PaymentType">
        <option value=""><?php _e('All payment types', 'wpse45436'); ?></option>
        <?php
            $current_v = isset($_GET['PaymentType'])? $_GET['PaymentType']:'';
            foreach ($values as $label => $value) {
                printf
                    (
                        '<option value="%s"%s>%s</option>',
                        $value,
                        $value == $current_v? ' selected="selected"':'',
                        $label
                    );
                }
        ?>
        </select>
        <?php
    }
}

add_filter( 'parse_query', 'wpse45436_posts_filter' );
/**
 * if submitted filter by post meta
 * 
 * @author Ohad Raz
 * @param  (wp_query object) $query
 * 
 * @return Void
 */
function wpse45436_posts_filter( $query ){
    global $pagenow;
    $type = 'post';
    if (isset($_GET['post_type'])) {
        $type = $_GET['post_type'];
    }

    if ( 'shop_order' == $type && is_admin() && $pagenow=='edit.php' && isset($_GET['PaymentType']) && $_GET['PaymentType'] != '') {
        $query->query_vars['meta_key'] = '_payment_method_title';
        $query->query_vars['meta_value'] = $_GET['PaymentType'];
    }
}

/***
 * Determine the correct merchant account ID for different currencies
 */
function bo_get_braintree_merchant_account_id( $default_id ) {	

	$curr = WC_Aelia_CurrencySwitcher::instance()->get_selected_currency();
	
	switch( $curr ) {
		case 'USD':
			return BO_BRAINTREE_MERCHANT_ID_USD;
		case 'EUR':
			return BO_BRAINTREE_MERCHANT_ID_EUR;
		default:
			wp_die( 'Invalid currency selected in bo_Gateway_Braintree::get_merchant_account_id()' );
	}
}
add_filter( 'wc_braintree_get_merchant_account_id', 'bo_get_braintree_merchant_account_id' );

/***
 * login errors 
 */


add_filter('login_errors','bo_login_error_message');

function bo_login_error_message($error){
	
    //check if that's the error you are looking for
    $pos = strpos($error, 'Invalid username');
    if (is_int($pos)) {
        //its the right error so you can overwrite it
        $error = "<strong>Error</strong> Invalid username";
    }

    return $error;
}


/***
 * Blender Cloud API
 */
include( 'api.php' );


/*** 
 * Allow manually renewing subscriptions for auto-renewing gateways
 */

add_action('woocommerce_checkout_update_order_meta','bo_update_order_meta');

function bo_update_order_meta( $order_id ){

	global $woocommerce;

	// check if order contains a product with the _force_manual_renewal flag set
	$order = new WC_Order( $order_id );
	
	$force_manual_renewal = false;
		
	$contents = $woocommerce->cart->cart_contents;
	foreach( $contents as $item ) {
		if( isset( $item['variation']['attribute_pa_renewal-type'] ) ) {
			if( $item['variation']['attribute_pa_renewal-type'] == 'manual' ) {
				$force_manual_renewal = true;
			}
		}
	}
		
	if( !$force_manual_renewal ) return;
	
	// remove recurring payment information
	delete_post_meta( $order_id, '_recurring_payment_method' );
	delete_post_meta( $order_id, '_recurring_payment_method_title' );

	// force manual renewal
	update_post_meta( $order_id, '_wcs_requires_manual_renewal', 'true');
}

/***
 * Check if order needs updating after manual creation
 */
function bo_save_post( $post_id ) {
	
    $post = get_post( $post_id );

	// fire only when saving orders
	if ( 'shop_order' != $post->post_type ) {
		return;
	}
    
	// update invoice number
	$o = new WC_Seq_Order_Number_Pro;
	$o->set_sequential_order_number( $post_id );
	$order_number_a = get_post_meta( $post_id, '_order_number' );
	$order_number = $order_number_a[0];
	
	$order_number_prefix = '{YY}{MM}{DD}-{EMAIL}-';
	$order_number_suffix = '';
	$order_number_length = 0;
				
	update_post_meta( $post_id, '_order_number_formatted', WC_Seq_Order_Number_Pro::format_order_number( $order_number, $order_number_prefix, $order_number_suffix, $order_number_length, $post_id ) );

	// is this order auto-renewing?
	$order = new WC_Order( $post_id );
	$items = $order->get_items();	

	$force_manual_renewal = false;
	foreach( $items as $item ) {
		if( $item['item_meta']['pa_renewal-type'][0] == 'manual' ) {
			// insert automatic payment data in order
			$force_manual_renewal = true;
		}
	}

	// set up temporary recurring payment, forcing user to renew upon his first payment
	if( $force_manual_renewal == false ) {
		update_post_meta( $post_id, '_recurring_payment_method', 'braintree' );
		update_post_meta( $post_id, '_recurring_payment_method_title', 'Creditcard (Braintree)' );

		// force manual renewal
		delete_post_meta( $post_id, '_wcs_requires_manual_renewal', 'true');
	}
}
add_action( 'save_post', 'bo_save_post' );

/*** 
 * Remove phone field from checkout.
 * 
 * Documentation: http://docs.woothemes.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/
 */
// Hook in
add_filter( 'woocommerce_checkout_fields' , 'bo_custom_override_checkout_fields' );
function bo_custom_override_checkout_fields( $fields ) {
     unset($fields['billing']['billing_phone']);
     return $fields;
}

/***
 * Remove phone field from billing address page
 */
add_filter( 'woocommerce_billing_fields', 'bo_custom_override_billing_fields' );
function bo_custom_override_billing_fields( $fields ) {
     unset($fields['billing_phone']);
     return $fields;
}