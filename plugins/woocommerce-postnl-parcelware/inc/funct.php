<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/** 
* WordPress Administration Menu - Shows WooCommerce submenu item for plugin
* @since 0.1
*/
function WooParc_admin_menu() {
	$page = add_submenu_page('woocommerce', __( 'Parcelware', 'woo-parc' ), __( 'Parcelware', 'woo-parc' ), 'manage_woocommerce', 'woocommerce_parcelware', 'WooParc_page' );
}

/**
* Add meta boxes to order detail page
* @since 0.1
*/
function WooParc_add_box() {
	add_meta_box( 'woo-parc-order-box', __( 'Export to Parcelware', 'woo-parc' ), 'WooParc_order_box', 'shop_order', 'side', 'default' );
}

/**
* Order detail export box with button
* @since 0.1
* 0.2 Added support for export date
*/
function WooParc_order_box($post) {
	$order=new WC_Order($post->ID);
	$j=1;
	echo '<div class=woo-parc-box>';
	echo '	<a class="button parc-link " href="'.wp_nonce_url(admin_url('?wooparc_submitted=exported&order_id='.$post->ID.'&wooparc_type=single&no=1'), 'wooparc_nonce').'"><img src="'. WooParc_icon($order).'" alt="'. __('Export to Parcelware', 'woo-parc').'" width="14"> '. __('Export', 'woo-parc').'</a>';
	
	if(WooParc_export_date($post->ID)) echo '<p><em>'.__('Exported to Parcelware on:','woo-parc').' '.WooParc_export_date($post->ID).'</em></p>';
	
	echo '</div>';
}

/**
* Last export date
* @since 0.1
*/
function WooParc_lastexport() {
	$date=stripslashes(get_option('wooparc_last_export',date('d-m-Y')));
	return $date;
}

/**
* Today
* @since 0.1
*/
function WooParc_today() {
	return date('d-m-Y');
}

/**
* Get shipping classes
* @since 0.1
*/
function WooParc_get_shipping_classes() {
		$shipping_classes = $classes = get_terms( 'product_shipping_class', array( 'hide_empty' => '0' ) ) ;
		return $shipping_classes;
}

/**
* Get shipping cost
* @since 0.1
*/
function WooParc_shipping_cost($cost) {
	if($cost=="") {$cost=0;}
	$cost=stripslashes($cost);
	$cost=str_replace(',', '.', $cost);
	return number_format($cost,2);
}

/**
 * Add shipping cost to shipping column
 * @since 0.1
 * @removed in version 0.4
 *
function WooParc_order_shipping($column) {
	global $post;
	$order = new WC_Order( $post->ID );
    switch ($column) {
		case "shipping_address" :
			$currency = get_option( 'woocommerce_currency' );
			$currency=get_woocommerce_currency_symbol($currency);
			if ($order->shipping_method_title) :
				echo '<small class=meta style="display:inline;">'.__('Shipping Cost:','woo-parc').' '.$currency.$order->order_shipping.'</small>';
			endif;
  			
		break;
    }
}*/

/**
 * Add export button to order actions column
 * @since 0.1
 * Changed for WC 2.1
 */
function WooParc_order_actions($column) {
	global $post;
	$order = new WC_Order( $post->ID );

	if ( version_compare( WOOCOMMERCE_VERSION, "2.0.99" ) >= 0 ) {
		$shipping_method=$order->get_shipping_methods();
		$shipping_method=array_shift(array_slice($shipping_method, 0, 1));
	} else {
		$currency = get_option( 'woocommerce_currency' );
		$currency=get_woocommerce_currency_symbol($currency);
	}
    switch ($column) {
		case "order_actions" :

  			?><p style="display:block;clear:both;height:14px">
				<a class="button parc-link tips" data-tip="<?php _e('Export to Parcelware', 'woo-parc'); ?>" href="<?php echo wp_nonce_url(admin_url('?wooparc_submitted=exported&order_id='.$post->ID.'&wooparc_type=single'), 'wooparc_nonce'); ?>"><img src="<?php echo WooParc_icon($order); ?>" alt="<?php _e('Export to Parcelware', 'woo-parc'); ?>" width="14" style="display:inline;float:left;">
			<?php 

				if (isset($shipping_method['method_id']) && get_option('wooparc_actions_price',0)==1) {
					// for WC 2.1
					echo '<small style="display:inline;float:left;line-height:12px">&nbsp;'.wc_price($shipping_method['cost']).'</small>';
				} elseif ($order->shipping_method_title && get_option('wooparc_actions_price',0)==1) {
					// for WC 2.0
					echo '<small style="display:inline;float:left;line-height:12px">&nbsp;'.$currency.$order->order_shipping.'</small>';
				}

		?></a>
  			</p>
<?php  break;
		case "shipping_address" :
			if (isset($shipping_method['method_id']) && get_option('wooparc_actions_price',0)==2) {
				// for WC 2.1
				echo '<small class="meta">'.__('Shipping','woocommerce').': '.woocommerce_price($shipping_method['cost']).'</small>';
			} elseif (get_option('wooparc_actions_price',0)==2) {
				// for WC 2.0
				if ($order->shipping_method_title) :
					echo '<small class=meta style="display:inline;">'.__('Shipping Cost:','woo-parc').' '.$currency.$order->order_shipping.'</small>';
				endif;
			}
		?></a>
  			</p>
<?php  break;
    }
}

/**
 * Get export icon
 * @since 0.1
 */
function WooParc_icon($order) {
	return plugins_url( 'img/icon-export.png' , dirname(__FILE__) );
}

/**
 * Get export date from order
 * @since 0.2
 */
function WooParc_export_date($order_id) {
	$date=get_post_meta($order_id,'export-date',true);
	if(isset($date) && $date!="") {
		$date=date(get_option('date_format').' '.get_option('time_format'),strtotime($date.' '. get_option('gmt_offset').' hours'));
		return $date;
	} else {
		return false;
	}
}

/**
 * Remove address 2 from checkout fields if set
 * @since 0.4
 */
function WooParc_removeAddress2( $fields ) {
     unset($fields['billing']['billing_address_2']);
	 unset($fields['shipping']['shipping_address_2']);
     return $fields;
}