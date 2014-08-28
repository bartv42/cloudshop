<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly
/*
Plugin Name: WooCommerce Currency Switcher - Subscriptions Integration
Description: Subscriptions integration for Aelia Currency Switcher for WooCommerce
Author: Diego Zanella
Version: 1.2.6.140820
*/

require_once('src/lib/classes/install/aelia-wc-cs-subscriptions-requirementscheck.php');
// If requirements are not met, deactivate the plugin
if(Aelia_WC_CS_Subscriptions_RequirementsChecks::factory()->check_requirements()) {
	require_once dirname(__FILE__) . '/src/plugin-main.php';
}
