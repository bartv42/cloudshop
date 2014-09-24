<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly
/*
Plugin Name: Aelia Tax Display by Country for WooCommerce
Description: Allows to display prices including/excluding tax depending on customer's location.
Author: Aelia (Diego Zanella)
Version: 1.5.12.140924
*/

require_once('src/lib/classes/install/aelia-wc-taxdisplaybycountry-requirementscheck.php');
// If requirements are not met, deactivate the plugin
if(Aelia_WC_TaxDisplayByCountry_RequirementsChecks::factory()->check_requirements()) {
	require_once 'src/plugin-main.php';
}
