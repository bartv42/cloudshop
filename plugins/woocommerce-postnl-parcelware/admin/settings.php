<?php 
if ( ! defined("ABSPATH" ) ) exit; // Exit if accessed directly

/**
* WooCommerce PostNL Parcelware Settings Page
* @since 0.1
*/
function WooParc_page() {
	$version="0.2";
	
	// Enqueue Datepicker script and styles
	wp_enqueue_script('jquery-ui-datepicker');
	
	wp_register_style( 'ui-smooth', plugins_url('css/jquery-ui-1.10.3.custom.min.css', dirname(__FILE__)) );
    wp_enqueue_style( 'ui-smooth' );
	
	
	// Check the user capabilities
	if ( !current_user_can("manage_woocommerce" ) ) {
		wp_die( __("You do not have sufficient permissions to access this page.", "woo-parc" ) );
	}
	
	// Save settings
	if ( isset( $_POST['wooparc_submitted'] ) && $_POST['wooparc_submitted'] == 'submitted' ) {
	check_admin_referer("wooparc_nonce");
		foreach ( $_POST as $key => $value ) {
			if ( get_option( $key ) != $value ) {
				update_option( $key, $value );
			} else {
				add_option( $key, $value, '', 'no' );
			}
		}
	}
	
	?>
	
	<script type="text/javascript">
    // Datepickers
    jQuery(document).ready(function() {
		jQuery('.date').datepicker({
			dateFormat : 'dd-mm-yy',
			maxDate:'0D', 
			showOn: "both",
			buttonImage: "<?php echo plugins_url("woocommerce-postnl-parcelware/img/icon-calendar.png" );?>",
			buttonImageOnly: true
		});
	});
    </script>
	<style>.ui-datepicker{position:absolute;left:-999em;}.ui-datepicker-trigger {position:relative;top:5px;} .disabled {opacity:0.5;}</style>
	<div class="wrap">
	  <div id="icon-options-general" class="icon32"></div>
	  <h2><?php _e("WooCommerce PostNL Parcelware", "woo-parc" ); ?></h2>
	  <?php if ( isset( $_POST['wooparc_submitted'] ) && $_POST['wooparc_submitted']=='submitted') { ?>
			<div id="message" class="updated fade"><p><strong><?php _e("Your settings have been saved.", "woo-parc" ); ?></strong></p></div>
		<?php }
			if ( isset( $_POST['wooparc_submitted'] ) && $_POST['wooparc_submitted']=='restore') { ?>
			<div id="message" class="updated fade"><p><strong><?php _e("Restore settings saved / Restore successful.", "woo-parc" ); ?></strong></p></div>
		<?php }		?>
		<div id="content">
			<div id="poststuff">
				<div style="float:left; width:72%; padding-right:3%;">
					
					<form method="get" action="" id="wooparc_export">
						<?php wp_nonce_field('wooparc_nonce'); ?>
						<input type=hidden name=wooparc_last_export value="<?php echo WooParc_today();?>">
						<input type="hidden" name="wooparc_submitted" value="exported">
						<input type="hidden" name="wooparc_type" value="quick">
					<div class="postbox">
						<h3><?php _e("Quick Export", "woo-parc" ); ?></h3>
						<div class="inside">
							<p><?php _e("Please specify date range and order status for your Quick Export.", "woo-parc" ); ?> <?php _e( "Make sure you've saved Advanced export settings before using Quick Export", "woo-parc" ); ?></p>
							<table class="form-table">
							  <tr>
								<th>
									<label for="wooparc_datefrom"><b><?php _e("Date from:", "woo-parc" ); ?></b></label>
								</th>
								<td>
									<input type="text" id="wooparc_datefrom" name="wooparc_datefrom" class=date value="<?php echo WooParc_lastexport();?>"/>
									&nbsp;<span class="description">
										<?php _e("Default: last export date", "woo-parc" );?>
									</span>
								</td>
							  </tr>
							  <tr>
								<th>
									<label for="wooparc_dateto"><b><?php _e("Date to:", "woo-parc" ); ?></b></label>
								</th>
								<td>
									<input type="text" id="wooparc_dateto" name="wooparc_dateto" class=date value="<?php echo WooParc_today();?>">
									&nbsp;<span class="description">
										<?php _e("Default: today", "woo-parc" );?>
									</span>
								</td>
							  </tr>
							   <tr>
								<th>
									<label for="wooparc_orderstatus"><b><?php _e("With order status:", "woo-parc" ); ?></b></label>
								</th>
								<td>
									<select name=wooparc_orderstatus id=wooparc_orderstatus>
										<option value="" <?php selected( get_option("wooparc_orderstatus" ), ''); ?>><?php _e("All statusses", "woo-parc" ); ?></option>
									<?php
										// retrieve all active WooCommerce order statuses
										$statuses = get_terms("shop_order_status", array("hide_empty" => false ) );
										foreach( $statuses as $status ) {
										  ?>
										  <option value="<?php echo $status->slug;?>" <?php selected( get_option("wooparc_orderstatus" ), $status->slug ); ?>><?php _e($status->name,"woocommerce");?></option>
										  <?php 
										}
									?>
									</select>
								</td>
							  </tr>
							  <tr>
								<td colspan=2>
									<p class="submit"><input type="submit" name="Submit" class="button-primary" value="<?php _e("Export to Parcelware", "woo-parc" ); ?>" /></p>
								</td>
							  </tr>
							</table>
						</div>
					</div>
					</form>
					<form method="post" action="" id="wooparc_settings">
						<?php wp_nonce_field('wooparc_nonce'); ?>
						<input type=hidden name=wooparc_version value="<?php echo $version;?>">
						<input type="hidden" name="wooparc_submitted" value="submitted">
					<div class="postbox">
						<h3><?php _e("Advanced export settings", "woo-parc" ); ?></h3>
						<div class="inside">
							<p><?php _e("Please specify your Parcelware export defaults / settings.", "woo-parc" ); ?></p>
							<table class="form-table">
							  <tr>
								<th>
									<label for="wooparc_actions"><b><?php _e("Add to actions column:", "woo-parc" ); ?></b></label>
								</th>
								<td>
									<input type="radio" id="wooparc_actions_yes" name="wooparc_actions" value="1" <?php checked(get_option("wooparc_actions" )== 1);?>> <label for=wooparc_actions_yes><?php _e("Yes","woo-parc");?></label>&nbsp;&nbsp;&nbsp;
									<input type="radio" id="wooparc_actions_no" name="wooparc_actions" value="0" <?php checked(get_option("wooparc_actions" )== 0);?>> <label for=wooparc_actions_no><?php _e("No","woo-parc");?></label><br>
									<span class="description">
										<?php _e("Add Parcelware export to order overview page actions column.", "woo-parc" );?>
									</span>
								</td>
							  </tr>
							  <tr>
								<th>
									<label for="wooparc_actions_price"><b><?php _e("Add shipping costs:", "woo-parc" ); ?></b></label>
								</th>
								<td>
									<input type="radio" id="wooparc_actions_price_yes" name="wooparc_actions_price" value="1" <?php checked(get_option("wooparc_actions_price" )== 1);?>> <label for=wooparc_actions_price_yes><?php _e("To export button inside Actions column","woo-parc");?></label><br>
									<input type="radio" id="wooparc_actions_price_shipping" name="wooparc_actions_price" value="2" <?php checked(get_option("wooparc_actions_price" )== 2);?>> <label for=wooparc_actions_price_shipping><?php _e("To Shipping column","woo-parc");?></label><br>
									<input type="radio" id="wooparc_actions_price_no" name="wooparc_actions_price" value="0" <?php checked(get_option("wooparc_actions_price" )== 0);?>> <label for=wooparc_actions_price_no><?php _e("No","woo-parc");?></label><br>
									<span class="description">
										<?php _e("Add shipping costs to order overview page.", "woo-parc" );?>
									</span>
								</td>
							  </tr> 
							  <tr>
								<th>
									<label for="wooparc_address2"><b><?php _e("Use address 2:", "woo-parc" ); ?></b></label>
								</th>
								<td>
									<input type="radio" id="wooparc_address2_yes" name="wooparc_address2" value="1" <?php checked(get_option("wooparc_address2" )== 1);?>> <label for=wooparc_address2_yes><?php _e("Yes","woo-parc");?></label>&nbsp;&nbsp;&nbsp;
									<input type="radio" id="wooparc_address2_no" name="wooparc_address2" value="0" <?php checked(get_option("wooparc_address2" )== 0);?>> <label for=wooparc_address2_no><?php _e("No","woo-parc");?></label><br>
									<span class="description">
										<?php _e("Do you want to show the second address field? You can use this to receive a apartment, suite, unit, etc.", "woo-parc" );?><br>
										<?php _e("Not required for PostNL Parcelware.", "woo-parc" );?><br>
									</span>
								</td>
							  </tr>
							  <tr>
								<td colspan=2>
									<p class="submit"><input type="submit" name="Submit" class="button-primary" value="<?php _e("Save settings", "woo-parc" ); ?>" /></p>
								</td>
							  </tr>
							</table>
						</div>
					</div>
					</form>
				</div>
				<?php // right column with Plugin information ?>
				<div style="float:right; width:25%;">
					<div class="postbox">
						<h3><?php _e( 'Buy Pro!', 'woo-parc' ); ?></h3>
						<div class="inside parc-preview">
							<p><?php echo __( 'Check out our ', 'woo-parc' ); ?> <a href="http://wordpress.geev.nl/product/woocommerce-postnl-parcelware/">website</a> <?php _e('to find out more about WooCommerce PostNL Parcelware Pro.', 'woo-parc' );?></p>
							<p><?php _e('For only &euro; 49,00 you will get a lot of features and access to our support section.', 'woo-parc' );?></p>
							<p><?php _e('A couple of features:', 'woo-parc' );?>
							<ul style="list-style:square;padding-left:20px;margin-top:-10px;"><li><?php _e('Define your own export filename.', 'woo-parc' );?></li><li><?php _e('Bulk export orders to Parcelware.', 'woo-parc' );?></li><li><?php _e('Change order status after export.', 'woo-parc' );?></li><li><?php _e('Define export based on shipping method & shipping cost.', 'woo-parc' );?></li><li><?php _e('Replace checkout field address in seperated fields for street, number and extension.', 'woo-parc' );?></li><li><?php _e('Backup last export and restore it.', 'woo-parc' );?></li></ul>
						</div>
					</div>
					<div class="postbox">
						<h3><?php _e("Show Your Love", "woo-parc" ); ?></h3>
						<div class="inside parc-preview">
							<p><?php echo sprintf(__("This plugin is developed by %s, a Dutch graphic design and webdevelopment company.", "woo-parc" ),'Geev vormgeeving'); ?></p>
							<p><?php _e("If you are happy with this plugin please show your love by liking us on Facebook", "woo-parc" ); ?></p>
							<iframe src="//www.facebook.com/plugins/likebox.php?href=http%3A%2F%2Fwww.facebook.com%2Fgeevvormgeeving&amp;width=220&amp;height=62&amp;show_faces=false&amp;colorscheme=light&amp;stream=false&amp;border_color&amp;header=false" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:100%; height:62px;" allowTransparency="true"></iframe>
							<p><?php _e("Or", "woo-parc" ); ?></p>
							<ul style="list-style:square;padding-left:20px;margin-top:-10px;">
								<li><a href="http://wordpress.org/extend/plugins/woocommerce-postnl-parcelware/screenshots/" target=_blank title="WooCommerce PostNL Parcelware"><?php _e("Rate the plugin 5&#9733; on WordPress.org", "woo-parc" ); ?></a></li>
								<li><a href="http://wordpress.geev.nl/product/woocommerce-postnl-parcelware/" target=_blank title="WooCommerce PostNL Parcelware"><?php _e("Blog about it & link to the plugin page", "woo-parc" ); ?></a></li>
							</ul>
						</div>
					</div>
				</div>
			</div>
	</div>
</div>
<?php }