<?php
namespace Aelia\CurrencySwitcher\WC21;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Currency Switcher Admin Report. Overrides standard WC_Admin_Report.
 *
 * Extended by reports to show charts and stats in admin.
 *
 * @category 	Admin
 * @since 3.2.0
 */
class WC_CS_Admin_Report extends \WC_Admin_Report {
	/**
	 * Get report totals such as order totals and discount amounts.
	 * IMPORTANT: to get a correct total amount, totals fields should be replaced
	 * by their "_base_currency" counterpart, as follows:
	 * _order_total -> _order_total_base_currency
	 * _order_discount -> _order_discount_base_currency
	 * _cart_discount -> _cart_discount_base_currency
	 * _order_shipping -> _order_shipping_base_currency
	 *
	 * Data example:
	 *
	 * '_order_total_base_currency' => array(
	 * 		'type'     => 'meta',
	 *    	'function' => 'SUM',
	 *      'name'     => 'total_sales'
	 * )
	 *
	 * @param  array args The parameters to prepare the report.
	 * @return array|string depending on query_type
	 */
	public function get_order_report_data($args = array()) {
		// Just call parent method. This method exists mainly to allow documenting it,
		// in order to highlight the importance of using the "*_base_currency" fields
		return parent::get_order_report_data($args);
	}

	/**
	 * Prepares a sparkline to show sales in the last X days. This method is an
	 * almost exact clone of WC_Admin_Report::sales_sparkline(), the only difference
	 * being that the order total in base currency is taken instead of the one in
	 * order's currency.
	 *
	 * NOTE: overriding an entire class just to change a field is overkill, but
	 * this is necessary due to a limitation in the design of WooCommerce 2.1,
	 * which doesn't allow modifying the query parameters for the reports.
	 *
	 * @param int $id ID of the product to show. Blank to get all orders.
	 * @param int $days Days of stats to get.
	 * @param string $type Type of sparkline to get. Ignored if ID is not set.
	 * @return string
	 */
	public function sales_sparkline( $id = '', $days = 7, $type = 'sales' ) {
		if ( $id ) {
			$meta_key = $type == 'sales' ? '_line_total' : '_qty';

			$data = $this->get_order_report_data( array(
				'data' => array(
					'_product_id' => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => '',
						'name'            => 'product_id'
					),
					$meta_key => array(
						'type'            => 'order_item_meta',
						'order_item_type' => 'line_item',
						'function'        => 'SUM',
						'name'            => 'sparkline_value'
					),
					'post_date' => array(
						'type'     => 'post_data',
						'function' => '',
						'name'     => 'post_date'
					),
				),
				'where' => array(
					array(
						'key'      => 'post_date',
						'value'    => date( 'Y-m-d', strtotime( 'midnight -' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ) ),
						'operator' => '>'
					),
					array(
						'key'      => 'order_item_meta__product_id.meta_value',
						'value'    => $id,
						'operator' => '='
					)
				),
				'group_by'     => 'YEAR(post_date), MONTH(post_date), DAY(post_date)',
				'query_type'   => 'get_results',
				'filter_range' => false
			) );
		} else {
			$data = $this->get_order_report_data( array(
				'data' => array(
					// Ensure that the total in base currency is taken
					'_order_total_base_currency' => array(
						'type'     => 'meta',
						'function' => 'SUM',
						'name'     => 'sparkline_value'
					),
					'post_date' => array(
						'type'     => 'post_data',
						'function' => '',
						'name'     => 'post_date'
					),
				),
				'where' => array(
					array(
						'key'      => 'post_date',
						'value'    => date( 'Y-m-d', strtotime( 'midnight -' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ) ),
						'operator' => '>'
					)
				),
				'group_by'     => 'YEAR(post_date), MONTH(post_date), DAY(post_date)',
				'query_type'   => 'get_results',
				'filter_range' => false
			) );
		}

		$total = 0;
		foreach ( $data as $d )
			$total += $d->sparkline_value;

		if ( $type == 'sales' ) {
			$tooltip = sprintf( __( 'Sold %s worth in the last %d days', 'woocommerce' ), strip_tags( wc_price( $total ) ), $days );
		} else {
			$tooltip = sprintf( _n( 'Sold 1 item in the last %d days', 'Sold %d items in the last %d days', $total, 'woocommerce' ), $total, $days );
		}

		$sparkline_data = array_values( $this->prepare_chart_data( $data, 'post_date', 'sparkline_value', $days - 1, strtotime( 'midnight -' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ), 'day' ) );

		return '<span class="wc_sparkline ' . ( $type == 'sales' ? 'lines' : 'bars' ) . ' tips" data-color="#777" data-tip="' . esc_attr( $tooltip ) . '" data-barwidth="' . 60*60*16*1000 . '" data-sparkline="' . esc_attr( json_encode( $sparkline_data ) ) . '"></span>';
	}
}
