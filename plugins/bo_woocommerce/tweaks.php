<?php
/*
Plugin Name: A bag of tweaks
Plugin URI: http://en.bainternet.info
Description: 
Version: 
Author: Bart Veldhuizen
Author URI: http://www.blendernation.com
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

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
			return '46njsqh7fdhyk3fc';
		case 'EUR':
			return 'BlenderInstituteEUR';
		default:
			wp_die( 'Invalid currency selected in bo_Gateway_Braintree::get_merchant_account_id()' );
	}
}
add_filter( 'wc_braintree_get_merchant_account_id', 'bo_get_braintree_merchant_account_id' );


/***
 * Blender Cloud API
 */
include( 'api.php' );



