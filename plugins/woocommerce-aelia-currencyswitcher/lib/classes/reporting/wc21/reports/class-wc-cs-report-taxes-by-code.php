<?php
namespace Aelia\CurrencySwitcher\WC21;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Overrides standard WC_Report_Taxes_By_Code class.
 * This class is an almost exact clone of its parent, with the exception that it
 * takes amounts in base currency, rather than the ones in which orders were
 * placed, to ensure that totals are consistent (original reports only take
 * absolute values, thus mixing up various multiple currencies and producing a
 * single, incorrect total).
 */
class WC_CS_Report_Taxes_By_Code extends \WC_Report_Taxes_By_Code {
	/**
	 * Get the main chart
	 * @return string
	 */
	public function get_main_chart() {
		global $wpdb;

		$tax_rows = $this->get_order_report_data( array(
			'data' => array(
				'order_item_name' => array(
					'type'     => 'order_item',
					'function' => '',
					'name'     => 'tax_rate'
				),
				'tax_amount_base_currency' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'tax',
					'function'        => '',
					'name'            => 'tax_amount'
				),
				'shipping_tax_amount_base_currency' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'tax',
					'function'        => '',
					'name'            => 'shipping_tax_amount'
				),
				'rate_id' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'tax',
					'function'        => '',
					'name'            => 'rate_id'
				)
			),
			'where' => array(
				array(
					'key'      => 'order_item_type',
					'value'    => 'tax',
					'operator' => '='
				),
				array(
					'key'      => 'order_item_name',
					'value'    => '',
					'operator' => '!='
				)
			),
			'order_by'     => 'post_date ASC',
			'query_type'   => 'get_results',
			'filter_range' => true
		) );
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e( 'Tax', 'woocommerce' ); ?></th>
					<th><?php _e( 'Rate', 'woocommerce' ); ?></th>
					<th class="total_row"><?php _e( 'Number of orders', 'woocommerce' ); ?></th>
					<th class="total_row"><?php _e( 'Tax Amount', 'woocommerce' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'This is the sum of the "Tax Rows" tax amount within your orders.', 'woocommerce' ); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Shipping Tax Amount', 'woocommerce' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'This is the sum of the "Tax Rows" shipping tax amount within your orders.', 'woocommerce' ); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Total Tax', 'woocommerce' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'This is the total tax for the rate (shipping tax + product tax).', 'woocommerce' ); ?>" href="#">[?]</a></th>
				</tr>
			</thead>
			<?php if ( $tax_rows ) : ?>
				<tfoot>
					<tr>
						<th scope="row" colspan="3"><?php _e( 'Total', 'woocommerce' ); ?></th>
						<th class="total_row"><?php echo wc_price( wc_round_tax_total( array_sum( wp_list_pluck( (array) $tax_rows, 'tax_amount' ) ) ) ); ?></th>
						<th class="total_row"><?php echo wc_price( wc_round_tax_total( array_sum( wp_list_pluck( (array) $tax_rows, 'shipping_tax_amount' ) ) ) ); ?></th>
						<th class="total_row"><strong><?php echo wc_price( wc_round_tax_total( array_sum( wp_list_pluck( (array) $tax_rows, 'tax_amount' ) ) + array_sum( wp_list_pluck( (array) $tax_rows, 'shipping_tax_amount' ) ) ) ); ?></strong></th>
					</tr>
				</tfoot>
				<tbody>
					<?php
					$grouped_tax_tows = array();

					foreach ( $tax_rows as $tax_row ) {
						if ( ! isset( $grouped_tax_tows[ $tax_row->rate_id ] ) ) {
							$grouped_tax_tows[ $tax_row->rate_id ] = (object) array(
								'tax_rate'            => $tax_row->tax_rate,
								'total_orders'        => 0,
								'tax_amount'          => 0,
								'shipping_tax_amount' => 0
							);
						}

						$grouped_tax_tows[ $tax_row->rate_id ]->total_orders ++;
						$grouped_tax_tows[ $tax_row->rate_id ]->tax_amount += wc_round_tax_total( $tax_row->tax_amount );
						$grouped_tax_tows[ $tax_row->rate_id ]->shipping_tax_amount += wc_round_tax_total( $tax_row->shipping_tax_amount );
					}

					foreach ( $grouped_tax_tows as $rate_id => $tax_row ) {
						$rate = $wpdb->get_var( $wpdb->prepare( "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d;", $rate_id ) );
						?>
						<tr>
							<th scope="row"><?php echo $tax_row->tax_rate; ?></th>
							<td><?php echo $rate; ?>%</td>
							<td class="total_row"><?php echo $tax_row->total_orders; ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->tax_amount ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->shipping_tax_amount ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->tax_amount + $tax_row->shipping_tax_amount ); ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			<?php else : ?>
				<tbody>
					<tr>
						<td><?php _e( 'No taxes found in this period', 'woocommerce' ); ?></td>
					</tr>
				</tbody>
			<?php endif; ?>
		</table>
		<?php
	}
}
