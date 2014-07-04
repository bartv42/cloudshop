<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

// This view should be called from WC_Aelia_Reporting_Manager::woocommerce_monthly_taxes()
?>
<form method="post" action="">
	<p><label for="show_year"><?php _e( 'Year:', 'woocommerce' ); ?></label>
	<select name="show_year" id="show_year">
		<?php
			for ( $i = $first_year; $i <= date('Y'); $i++ )
				printf( '<option value="%s" %s>%s</option>', $i, selected( $current_year, $i, false ), $i );
		?>
	</select> <input type="submit" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" /></p>
</form>
<div id="poststuff" class="woocommerce-reports-wrap">
	<div class="woocommerce-reports-sidebar">
		<div class="postbox">
			<h3><span><?php _e( 'Total taxes for year', 'woocommerce' ); ?></span></h3>
			<div class="inside">
				<p class="stat"><?php
					if ( $total_tax > 0 )
						echo woocommerce_price( $total_tax );
					else
						_e( 'n/a', 'woocommerce' );
				?></p>
			</div>
		</div>
		<div class="postbox">
			<h3><span><?php _e( 'Total product taxes for year', 'woocommerce' ); ?></span></h3>
			<div class="inside">
				<p class="stat"><?php
					if ( $total_sales_tax > 0 )
						echo woocommerce_price( $total_sales_tax );
					else
						_e( 'n/a', 'woocommerce' );
				?></p>
			</div>
		</div>
		<div class="postbox">
			<h3><span><?php _e( 'Total shipping tax for year', 'woocommerce' ); ?></span></h3>
			<div class="inside">
				<p class="stat"><?php
					if ( $total_shipping_tax > 0 )
						echo woocommerce_price( $total_shipping_tax );
					else
						_e( 'n/a', 'woocommerce' );
				?></p>
			</div>
		</div>
	</div>
	<div class="woocommerce-reports-main">
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e( 'Month', 'woocommerce' ); ?></th>
					<th class="total_row"><?php _e( 'Total Sales', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Order Total' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Total Shipping', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Shipping Total' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Total Product Taxes', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Cart Tax' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Total Shipping Taxes', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Shipping Tax' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Total Taxes', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Cart Tax' and 'Shipping Tax' fields within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Net profit', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("Total sales minus shipping and tax.", 'woocommerce'); ?>" href="#">[?]</a></th>
					<?php
						$tax_row_labels = array_filter( array_unique( $tax_row_labels ) );
						foreach ( $tax_row_labels as $label )
							echo '<th class="tax_row">' . $label . '</th>';
					?>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<?php
						$total = array();

						foreach ( $taxes as $month => $tax ) {
							$total['gross'] = isset( $total['gross'] ) ? $total['gross'] + $tax['gross'] : $tax['gross'];
							$total['shipping'] = isset( $total['shipping'] ) ? $total['shipping'] + $tax['shipping'] : $tax['shipping'];
							$total['order_tax'] = isset( $total['order_tax'] ) ? $total['order_tax'] + $tax['order_tax'] : $tax['order_tax'];
							$total['shipping_tax'] = isset( $total['shipping_tax'] ) ? $total['shipping_tax'] + $tax['shipping_tax'] : $tax['shipping_tax'];
							$total['total_tax'] = isset( $total['total_tax'] ) ? $total['total_tax'] + $tax['total_tax'] : $tax['total_tax'];

							foreach ( $tax_row_labels as $label )
								foreach ( $tax['tax_rows'] as $tax_row )
									if ( $tax_row->name == $label ) {
										$total['tax_rows'][ $label ] = isset( $total['tax_rows'][ $label ] ) ? $total['tax_rows'][ $label ] + $tax_row->total_tax_amount : $tax_row->total_tax_amount;
									}

						}

						echo '
							<td>' . __( 'Total', 'woocommerce' ) . '</td>
							<td class="total_row">' . woocommerce_price( $total['gross'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $total['shipping'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $total['order_tax'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $total['shipping_tax'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $total['total_tax'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $total['gross'] - $total['shipping'] - $total['total_tax'] ) . '</td>';

						foreach ( $tax_row_labels as $label )
							if ( isset( $total['tax_rows'][ $label ] ) )
								echo '<td class="tax_row">' . woocommerce_price( $total['tax_rows'][ $label ] ) . '</td>';
							else
								echo '<td class="tax_row">' .  woocommerce_price( 0 ) . '</td>';
					?>
				</tr>
				<tr>
					<th colspan="<?php echo 7 + sizeof( $tax_row_labels ); ?>"><button class="button toggle_tax_rows"><?php _e( 'Toggle tax rows', 'woocommerce' ); ?></button></th>
				</tr>
			</tfoot>
			<tbody>
				<?php
					foreach ( $taxes as $month => $tax ) {
						$alt = ( isset( $alt ) && $alt == 'alt' ) ? '' : 'alt';
						echo '<tr class="' . $alt . '">
							<td>' . $month . '</td>
							<td class="total_row">' . woocommerce_price( $tax['gross'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $tax['shipping'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $tax['order_tax'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $tax['shipping_tax'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $tax['total_tax'] ) . '</td>
							<td class="total_row">' . woocommerce_price( $tax['gross'] - $tax['shipping'] - $tax['total_tax'] ) . '</td>';



						foreach ( $tax_row_labels as $label ) {

							$row_total = 0;

							foreach ( $tax['tax_rows'] as $tax_row ) {
								if ( $tax_row->name == $label ) {
									$row_total = $tax_row->total_tax_amount;
								}
							}

							echo '<td class="tax_row">' . woocommerce_price( $row_total ) . '</td>';
						}

						echo '</tr>';
					}
				?>
			</tbody>
		</table>
		<script type="text/javascript">
			jQuery('.toggle_tax_rows').click(function(){
				jQuery('.tax_row').toggle();
				jQuery('.total_row').toggle();
			});
			jQuery('.tax_row').hide();
		</script>
	</div>
</div>
