<?php
namespace Aelia\CurrencySwitcher\WC21;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Overrides standard WC_Report_Sales_By_Category class.
 * This class is an almost exact clone of its parent, with the exception that it
 * takes amounts in base currency, rather than the ones in which orders were
 * placed, to ensure that totals are consistent (original reports only take
 * absolute values, thus mixing up various multiple currencies and producing a
 * single, incorrect total).
 */
class WC_CS_Report_Sales_By_Category extends \WC_Report_Sales_By_Category {
	/**
	 * Output the report
	 */
	public function output_report() {
		global $woocommerce, $wpdb, $wp_locale;

		$ranges = array(
			'year'         => __( 'Year', 'woocommerce' ),
			'last_month'   => __( 'Last Month', 'woocommerce' ),
			'month'        => __( 'This Month', 'woocommerce' ),
			'7day'         => __( 'Last 7 Days', 'woocommerce' )
		);

		$this->chart_colours = array( '#3498db', '#34495e', '#1abc9c', '#2ecc71', '#f1c40f', '#e67e22', '#e74c3c', '#2980b9', '#8e44ad', '#2c3e50', '#16a085', '#27ae60', '#f39c12', '#d35400', '#c0392b' );

		$current_range = ! empty( $_GET['range'] ) ? $_GET['range'] : '7day';

		if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ) ) ) {
			$current_range = '7day';
		}

		$this->calculate_current_range( $current_range );

		// Get item sales data
		if ( $this->show_categories ) {
			$order_items = $this->get_order_report_data( array(
				'data' => array(
					'_product_id' => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => '',
						'name'            => 'product_id'
					),
					'_line_total_base_currency' => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function' => '',
						'name'     => 'order_item_amount'
					),
					'post_date' => array(
						'type'     => 'post_data',
						'function' => '',
						'name'     => 'post_date'
					),
				),
				'group_by'     => 'ID, product_id',
				'query_type'   => 'get_results',
				'filter_range' => true
			) );

			$this->item_sales = array();
			$this->item_sales_and_times = array();

			if ( $order_items ) {
				foreach ( $order_items as $order_item ) {
					switch ( $this->chart_groupby ) {
						case 'day' :
							$time = strtotime( date( 'Ymd', strtotime( $order_item->post_date ) ) ) * 1000;
						break;
						case 'month' :
							$time = strtotime( date( 'Ym', strtotime( $order_item->post_date ) ) . '01' ) * 1000;
						break;
					}

					$this->item_sales_and_times[ $time ][ $order_item->product_id ] = isset( $this->item_sales_and_times[ $time ][ $order_item->product_id ] ) ? $this->item_sales_and_times[ $time ][ $order_item->product_id ] + $order_item->order_item_amount : $order_item->order_item_amount;

					$this->item_sales[ $order_item->product_id ] = isset( $this->item_sales[ $order_item->product_id ] ) ? $this->item_sales[ $order_item->product_id ] + $order_item->order_item_amount : $order_item->order_item_amount;
				}
			}
		}

		include( WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php' );
	}
}
