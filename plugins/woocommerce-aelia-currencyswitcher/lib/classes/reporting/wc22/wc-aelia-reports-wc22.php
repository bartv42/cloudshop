<?php
namespace Aelia\CurrencySwitcher\WC22;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \WC_Aelia_CurrencySwitcher;
use \Aelia_Order;
use \Aelia\CurrencySwitcher\Logger as Logger;

/**
 * Overrides the reports for WooCommerce 2.2.
 */
class Reports extends \Aelia\CurrencySwitcher\WC21\Reports {
	// @var The WooCommerce version for which this reports class has been implemented
	protected $wc_version = '22';

	/**
	 * Overrides the WooCommerce dashboard reports.
	 */
	public function override_dashboard_reports() {
		// Dummy, to prevent overrides made by ancestor
	}

	/**
	 * Sets the hooks required by the class.
	 */
	protected function set_hooks() {
		parent::set_hooks();

		// Dashboard reports
		add_action('woocommerce_dashboard_status_widget_sales_query', array($this, 'woocommerce_dashboard_status_widget_sales_query'), 10, 1);
	}

	public function woocommerce_dashboard_status_widget_sales_query($query) {
		global $wpdb;
		// Replace query to one that returns the totals in base currency
		$query            = array();
		$query['fields']  = "SELECT SUM( postmeta.meta_value ) FROM {$wpdb->posts} as posts";
		$query['join']    = "INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id ";
		$query['where']   = "WHERE posts.post_type IN ( '" . implode( "','", wc_get_order_types( 'reports' ) ) . "' ) ";
		$query['where']  .= "AND posts.post_status IN ( 'wc-" . implode( "','wc-", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) ) . "' ) ";
		$query['where']  .= "AND postmeta.meta_key   = '_order_total_base_currency' ";
		$query['where']  .= "AND posts.post_date >= '" . date( 'Y-m-01', current_time( 'timestamp' ) ) . "' ";
		$query['where']  .= "AND posts.post_date <= '" . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) . "' ";

		return $query;
	}
}
