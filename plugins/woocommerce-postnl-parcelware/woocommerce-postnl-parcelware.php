<?php
/*
Plugin Name: WooCommerce PostNL Parcelware
Plugin URI: http://wordpress.geev.nl/product/woocommerce-postnl-parcelware/
Description: This plugin exports all selected orders to Parcelware. - FREE version
Version: 0.4
Author: Bart Pluijms
Author URI: http://www.geev.nl/
*/
/*  Copyright 2012  Geev  (email : info@geev.nl)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
* Load WooCommerce required functions 
* since 0.1
*/
require_once('inc/funct-basic.php');

/**
* Check if WooCommerce is active
* @since 0.1
*/
if (in_array('woocommerce/woocommerce.php',get_option('active_plugins'))) {
	require_once('inc/funct.php');
	require_once('inc/funct-export.php');
	require_once('admin/settings.php');
	
	load_plugin_textdomain('woo-parc', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/');
	
	add_action( 'admin_init','WooParc_exporter',10 );
	add_action( 'admin_menu', 'WooParc_admin_menu' );

	add_action('add_meta_boxes', 'WooParc_add_box' );

	if(get_option('wooparc_actions',0)==1) {
		add_action( 'manage_shop_order_posts_custom_column', 'WooParc_order_actions', 3 );
	}
	/*if(get_option( 'wooparc_actions_price',0)==2) {
		add_action( 'manage_shop_order_posts_custom_column', 'WooParc_order_shipping', 3 );
	}*/
	
	if(get_option('wooparc_address2',1)==0) {
		add_filter( 'woocommerce_checkout_fields' , 'WooParc_removeAddress2' );
	}

} else {
	// if WooCommerce is not active show admin message
	function WooParc_Message() {
		showMessage(__( 'WooCommerce is not active. Please activate plugin before using WooCommerce PostNL ParcelWare plugin.', 'woo-parc'), true);
	}
	add_action('admin_notices', 'WooParc_Message');   
}

/**
* Added help links to plugin page
* @since 0.1
*/
function WooParc_plugin_links($links) { 
  $settings_link = '<a href="admin.php?page=woocommerce_parcelware">Settings</a>'; 
  $premium_link = '<a href="http://wordpress.geev.nl/product/woocommerce-postnl-parcelware/" title="Premium Support" target=_blank>Buy Pro</a>'; 
  array_unshift($links, $settings_link,$premium_link); 
  return $links; 
}
 
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'WooParc_plugin_links' );
?>