<?php
namespace Aelia\CurrencySwitcher\WC21;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Overrides standard WC_CS_Report_Taxes_By_Date class.
 * This class is an almost exact clone of its parent, with the exception that it
 * takes amounts in base currency, rather than the ones in which orders were
 * placed, to ensure that totals are consistent (original reports only take
 * absolute values, thus mixing up various multiple currencies and producing a
 * single, incorrect total).
 */
class WC_CS_Report_Taxes_By_Date extends \WC_Report_Taxes_By_Date {
	/**
	 * Get the main chart
	 * @return string
	 */
	public function get_main_chart() {
		global $wpdb;

		$tax_rows = $this->get_order_report_data( array(
			'data' => array(
				'_order_tax_base_currency' => array(
					'type'            => 'meta',
					'function'        => 'SUM',
					'name'            => 'tax_amount'
				),
				'_order_shipping_tax_base_currency' => array(
					'type'            => 'meta',
					'function'        => 'SUM',
					'name'            => 'shipping_tax_amount'
				),
				'_order_total_base_currency' => array(
					'type'     => 'meta',
					'function' => 'SUM',
					'name'     => 'total_sales'
				),
				'_order_shipping_base_currency' => array(
					'type'     => 'meta',
					'function' => 'SUM',
					'name'     => 'total_shipping'
				),
				'ID' => array(
					'type'     => 'post_data',
					'function' => 'COUNT',
					'name'     => 'total_orders',
					'distinct' => true,
				),
				'post_date' => array(
					'type'     => 'post_data',
					'function' => '',
					'name'     => 'post_date'
				),
			),
			'group_by'     => $this->group_by_query,
			'order_by'     => 'post_date ASC',
			'query_type'   => 'get_results',
			'filter_range' => true
		) );
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e( 'Period', 'woocommerce' ); ?></th>
					<th class="total_row"><?php _e( 'Number of orders', 'woocommerce' ); ?></th>
					<th class="total_row"><?php _e( 'Total Sales', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Order Total' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Total Shipping', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Shipping Total' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Total Tax', 'woocommerce' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'This is the total tax for the rate (shipping tax + product tax).', 'woocommerce' ); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Net profit', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("Total sales minus shipping and tax.", 'woocommerce'); ?>" href="#">[?]</a></th>
				</tr>
			</thead>
			<?php if ( $tax_rows ) :
				$gross = array_sum( wp_list_pluck( (array) $tax_rows, 'total_sales' ) ) - array_sum( wp_list_pluck( (array) $tax_rows, 'total_shipping' ) );
				$total_tax = array_sum( wp_list_pluck( (array) $tax_rows, 'tax_amount' ) ) - array_sum( wp_list_pluck( (array) $tax_rows, 'shipping_tax_amount' ) );
				?>
				<tfoot>
					<tr>
						<th scope="row"><?php _e( 'Totals', 'woocommerce' ); ?></th>
						<th class="total_row"><?php echo array_sum( wp_list_pluck( (array) $tax_rows, 'total_orders' ) ); ?></th>
						<th class="total_row"><?php echo wc_price( $gross ); ?></th>
						<th class="total_row"><?php echo wc_price( array_sum( wp_list_pluck( (array) $tax_rows, 'total_shipping' ) ) ); ?></th>
						<th class="total_row"><?php echo wc_price( $total_tax ); ?></th>
						<th class="total_row"><?php echo wc_price( $gross - $total_tax ); ?></th>
					</tr>
				</tfoot>
				<tbody>
					<?php
					foreach ( $tax_rows as $tax_row ) {
						$gross     = $tax_row->total_sales - $tax_row->total_shipping;
						$total_tax = $tax_row->tax_amount + $tax_row->shipping_tax_amount;
						?>
						<tr>
							<th scope="row"><?php
								if ( $this->chart_groupby == 'month' )
									echo date_i18n( 'F', strtotime( $tax_row->post_date ) );
								else
									echo date_i18n( get_option( 'date_format' ), strtotime( $tax_row->post_date ) );
							?></th>
							<td class="total_row"><?php echo $tax_row->total_orders; ?></td>
							<td class="total_row"><?php echo wc_price( $gross ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->total_shipping ); ?></td>
							<td class="total_row"><?php echo wc_price( $total_tax ); ?></td>
							<td class="total_row"><?php echo wc_price( $gross - $total_tax ); ?></td>
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
