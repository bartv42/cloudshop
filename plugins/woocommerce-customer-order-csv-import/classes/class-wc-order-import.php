s<?php
/**
 * WooCommerce Customer/Order CSV Import Suite
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Customer/Order CSV Import Suite to newer
 * versions in the future. If you wish to customize WooCommerce Customer/Order CSV Import Suite for your
 * needs please refer to http://docs.woothemes.com/document/customer-order-csv-import-suite/ for more information.
 *
 * @package     WC-Customer-CSV-Import-Suite/Classes
 * @author      SkyVerge
 * @copyright   Copyright (c) 2012-2014, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! class_exists( 'WP_Importer' ) ) return;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooCommerce Order Importer class for managing the import process of a CSV file.
 *
 * The main difficulty in importing orders is that by default WooCommerce relies
 * on the internal post_id to act as the order number, which we can not set when
 * importing orders.  So we have to make some concessions based on the users
 * particular environment:
 *
 * 1. If they happen to have a custom order number plugin installed that makes
 *    use of the filter/action hooks provided by this plugin, then this import
 *    plugin will integrate seemlessly with that plugin and things will be happy.
 *    Granted, one assumption has to be made on the import format: that a custom
 *    order number will consist of a numeric (incrementing) piece, and a string
 *    formatted piece, but after that custom order number plugins can go nuts
 * 2. If the user does not have a custom order number plugin installed, then
 *    this plugin will compensate by at least setting the provided order number
 *    to the _order_number_formatted/_order_number metas used by the Sequential
 *    Order Number Pro plugin, and add an order note providing the original
 *    order number.
 *
 * The second tricky part is handling the order items.  This is dealt with by
 * allowing an arbitrary number of columns of the form order_item_1, order_item_2,
 * etc.  The value for each order item is a pipe-delimited string containing:
 * sku|quantity|price
 *
 * Due to order number/id concerns, and frankly lack of a use case, order import
 * merging is not supported.
 */
class WC_CSV_Order_Import extends WP_Importer {

	private $id; // CSV attachment ID
	private $file_url; // CSV attachmente url

	// information to import from CSV file
	private $posts = array();
	private $processed_posts = array();

	// Counts
	public $log;
	private $merged = 0;
	private $skipped = 0;
	private $imported = 0;
	private $errored = 0;

	private $file_url_import_enabled = true;
	private $delimiter;

	/**
	 * Consruct and intialize the importer
	 */
	public function __construct() {
		$this->log = new WC_CSV_Customer_Log();

		// provide some base custom order number functionality, while allowing 3rd party plugins with custom
		//  order number functionality to unhook this and provide their own logic
		add_action( 'woocommerce_set_order_number', array( $this, 'woocommerce_set_order_number' ), 10, 3 );

		$this->file_url_import_enabled = apply_filters( 'woocommerce_csv_order_file_url_import_enabled', true );
	}


	/**
	 * Manages the two separate stages of the CSV import process:
	 *
	 * 1. choosing the file
	 * 2. Handle the physical upload of the file and provide some configuration options
	 * 3. Perform the parsing/importing of the import file
	 */
	public function dispatch() {
		$this->header();

		if ( ! empty( $_POST['delimiter'] ) ) {
			$this->delimiter = stripslashes( trim( $_POST['delimiter'] ) );
		}

		if ( ! $this->delimiter ) $this->delimiter = ',';

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		switch ( $step ) {
			case 0:
				$this->greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				if ( $this->handle_upload() )
					$this->import_options();
				break;
			case 2:
				check_admin_referer( 'import-woocommerce' );

				$this->id = (int) $_POST['import_id'];
				if ( $this->file_url_import_enabled )
					$this->file_url = esc_attr( $_POST['import_url'] );

				if ( $this->id )
					$file = get_attached_file( $this->id );
				else if ( $this->file_url_import_enabled )
					$file = ABSPATH . $this->file_url;

				if ( $file ) {
					echo '<div class="importer_loader"></div>';

					add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

					if ( function_exists( 'gc_enable' ) )
						gc_enable();

					@set_time_limit(0);
					@ob_flush();
					@flush();

					$this->import( $file );
				}
				break;
		}

		$this->footer();
	}


	/**
	 * Render the file import options:
	 * - Record Offset
	 */
	private function import_options() {
		?>
		<form action="<?php echo admin_url( 'admin.php?import=woocommerce_order_csv&step=2' ); ?>" method="post">
			<?php wp_nonce_field( 'import-woocommerce' ); ?>
			<input type="hidden" name="import_id" value="<?php echo $this->id; ?>" />
			<?php if ( $this->file_url_import_enabled ) : ?>
			<input type="hidden" name="import_url" value="<?php echo $this->file_url; ?>" />
			<?php endif; ?>
			<h3><?php _e( 'Advanced Options', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></h3>
			<p>
				<label for="dry_run"><?php _e( 'Dry Run', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) ?></label>
				<input type="checkbox" value="1" name="dry_run" id="dry_run" />
				<span class="description"><?php _e( 'Perform a test dry run of the import process to check for errors prior to the actual import', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></span>
			</p>
			<p>
				<label for="allow_unknown_products"><?php _e( 'Allow Unknown Products', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) ?></label>
				<input type="checkbox" value="1" name="allow_unknown_products" id="allow_unknown_products" />
				<span class="description"><?php _e( 'Allow line items with unknown product sku/id.  The line item will not be linked to any product, so this is not necessarily recommended.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></span>
			</p>
			<p>
				<label for="record_offset"><?php _e( 'Skip Records', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) ?></label>
				<input type="text" value="0" name="record_offset" id="record_offset" />
				<span class="description"><?php _e( 'Skip this number of records before parsing.  Use this option when importing very large files that are unable to complete in a single upload attempt.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></span>
			</p>

			<p class="submit"><input type="submit" class="button" value="<?php esc_attr_e( 'Submit', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?>" /></p>
		</form>
		<?php
	}


	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the CSV file for importing
	 */
	private function import( $file ) {

		$this->import_start( $file );

		echo '<div class="progress">';
		$this->process_orders();
		echo '</div>';

		// Show Result
		echo '<div class="updated settings-error below-h2"><p>' .
		  sprintf( __('Import complete - imported <strong>%s</strong>, merged <strong>%s</strong>, skipped <strong>%s</strong>, errors <strong>%s</strong>', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $this->imported, $this->merged, $this->skipped, $this->errored ) .
		  '</p></div>';

		$this->log->show_log();

		$this->import_end();
	}


	/**
	 * Parses the CSV file and prepares us for the task of processing parsed data
	 *
	 * @param string $file Path to the CSV file for importing
	 */
	private function import_start( $file ) {
		if ( ! is_file( $file ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</strong><br />';
			echo __( 'The file does not exist, please try again.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</p>';
			$this->footer();
			die();
		}

		$import_data = $this->parse( $file );

		if ( is_wp_error( $import_data ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</strong><br />';
			echo esc_html( $import_data->get_error_message() ) . '</p>';
			$this->footer();
			die();
		}

		$this->skipped = $import_data['skipped'];
		$this->posts   = $import_data['orders'];
	}


	/**
	 * Performs post-import cleanup of files and the cache
	 */
	private function import_end() {
		global $wc_customer_csv_import;
		wp_cache_flush();

		echo '<p>' . __( 'All done!', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . ' <a href="' . admin_url( 'edit.php?post_type=shop_order' ) . '">' . __( 'View Orders', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</a>' .
		  ' or <a href="' . $wc_customer_csv_import->get_settings_url() . '">Import another file</a>.</p>';

		do_action( 'import_end' );
	}

	/**
	 * Handles the CSV upload and initial parsing of the file
	 *
	 * @return bool False if error uploading or invalid file, true otherwise
	 */
	private function handle_upload() {

		if ( empty( $_POST['file_url'] ) ) {

			$file = wp_import_handle_upload();

			if ( isset( $file['error'] ) ) {
				echo '<p><strong>' . __( 'Sorry, there has been an error.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</strong><br />';
				echo esc_html( $file['error'] ) . '</p>';
				return false;
			}

			$this->id = (int) $file['id'];

		} else {

			if ( file_exists( ABSPATH . $_POST['file_url'] ) ) {

				$this->file_url = esc_attr( $_POST['file_url'] );

			} else {

				echo '<p><strong>' . __( 'Sorry, there has been an error.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</strong></p>';
				return false;

			}

		}

		return true;
	}


	/**
	 * Create new orders based on the parsed data
	 */
	private function process_orders() {

		global $wpdb;

		$this->imported = $this->merged = 0;

		// peforming a dry run?
		$dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] ? true : false;

		$this->log->add( '---' );
		$this->log->add( __( 'Processing orders.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );

		foreach ( $this->posts as $post ) {

			// orders with custom order order numbers can be checked for existance, otherwise there's not much we can do
			if ( ! empty( $post['order_number_formatted'] ) && isset( $this->processed_posts[ $post['order_number_formatted'] ] ) ){
				$this->skipped++;
				$this->log->add( sprintf( __( '> Order %s already processed. Skipping.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $post['order_number_formatted'] ), true );
				continue;
			}

			// see class-wc-checkout.php for reference

			$order_data = array(
				'post_date'     => date( 'Y-m-d H:i:s', $post['date'] ),
				'post_type'     => 'shop_order',
				'post_title'    => 'Order &ndash; ' . date( 'F j, Y @ h:i A', $post['date'] ),
				'post_status'   => 'publish',
				'ping_status'   => 'closed',
				'post_excerpt'  => $post['order_comments'],
				'post_author'   => 1,
				'post_password' => uniqid( 'order_' ),  // Protects the post just in case
			);

			if ( ! $dry_run ) {
				// track whether download permissions need to be granted
				$add_download_permissions = false;

				$order_id = wp_insert_post( $order_data );

				if ( is_wp_error( $order_id ) ) {
					$this->errored++;
					$this->log->add( sprintf( __( '> Error inserting %s: %s', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $post['order_number_formatted'], $order_id->get_error_message() ), true );
				}

				// empty update to bump up the post_modified date to today's date (otherwise it would match the post_date, which isn't quite right)
				wp_update_post( array( 'ID' => $order_id ) );

				// set order status
				wp_set_object_terms( $order_id, $post['status'], 'shop_order_status' );

				// handle special meta fields
				update_post_meta( $order_id, '_order_key',          apply_filters( 'woocommerce_generate_order_key', uniqid( 'order_' ) ) );
				update_post_meta( $order_id, '_order_currency',     get_woocommerce_currency() );  // TODO: fine to use store default?
				if ( ! SV_WC_Plugin_Compatibility::is_wc_version_gte_2_1() ) {
					update_post_meta( $order_id, '_order_taxes',        array() );  // pre-2.1
				}
				update_post_meta( $order_id, '_prices_include_tax', get_option( 'woocommerce_prices_include_tax' ) );

				// add order postmeta
				foreach ( $post['postmeta'] as $meta ) {
					$meta_processed = false;

					// we don't set the "download permissions granted" meta, we call the woocommerce function to take care of this for us
					if ( ( 'Download Permissions Granted' == $meta['key'] || '_download_permissions_granted' == $meta['key'] ) && $meta['value'] ) {
						$add_download_permissions = true;
						$meta_processed = true;
					}

					if ( ! $meta_processed ) {
						update_post_meta( $order_id, $meta['key'], $meta['value'] );
					}

					// set the paying customer flag on the user meta if applicable
					if ( '_customer_user' == $meta['key'] && $meta['value'] && in_array( $post['status'], array( 'processing', 'completed', 'refunded' ) ) ) {
						update_user_meta( $meta['value'], "paying_customer", 1 );
					}
				}


				// handle order items
				$order_items = array();
				$order_item_meta = null;

				foreach ( $post['order_items'] as $item ) {

					$product = null;
					$variation_item_meta = array();

					// if there's a product_id then we've already determined during parsing that this product exists
					if ( $item['product_id'] ) {
						$product = get_product( $item['product_id'] );

						// handle variations
						if ( ( $product->is_type( 'variable' ) || $product->is_type( 'variation' ) || $product->is_type( 'subscription_variation' ) ) && method_exists( $product, 'get_variation_id' ) ) {
							foreach ( $product->get_variation_attributes() as $key => $value ) {
								$variation_item_meta[] = array( 'meta_name' => esc_attr( substr( $key, 10 ) ), 'meta_value' => $value );  // remove the leading 'attribute_' from the name to get 'pa_color' for instance
							}
						}
					}

					// order item
					$order_items[] = array(
						'order_item_name' => $product ? $product->get_title() : __( 'Unknown Product', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),
						'order_item_type' => 'line_item',
					);

					// standard order item meta
					$_order_item_meta = array(
						'_qty'               => (int) $item['qty'],
						'_tax_class'         => '', // Tax class (adjusted by filters)
						'_product_id'        => $item['product_id'],
						'_variation_id'      => $product && method_exists( $product,'get_variation_id' ) ? $product->get_variation_id() : 0,
						'_line_subtotal'     => number_format( (float) $item['total'], 2, '.', '' ), // Line subtotal (before discounts)
						'_line_subtotal_tax' => 0, // Line tax (before discounts)
						'_line_total'        => number_format( (float) $item['total'], 2, '.', '' ), // Line total (after discounts)
						'_line_tax'          => 0, // Line Tax (after discounts)
					);

					// add any product variation meta
					foreach ( $variation_item_meta as $meta ) {
						$_order_item_meta[ $meta['meta_name'] ] = $meta['meta_value'];
					}

					// include any arbitrary order item meta
					$_order_item_meta = array_merge( $_order_item_meta, $item['meta'] );

					$order_item_meta[] = $_order_item_meta;

				}

				foreach ( $order_items as $key => $order_item ) {
					$order_item_id = woocommerce_add_order_item( $order_id, $order_item );

					if ( $order_item_id ) {
						foreach ( $order_item_meta[ $key ] as $meta_key => $meta_value ) {
							if(strpos($meta_value, ':{i')){
								$meta_value=unserialize(stripslashes($meta_value));//'a:1:{i:0;s:19:"2014-05-01 08:00:00";}';
							}
							woocommerce_add_order_item_meta( $order_item_id, $meta_key, $meta_value );
						}
					}
				}

				// create the shipping order items (WC 2.1+)
				foreach ( $post['order_shipping'] as $order_shipping ) {

					$shipping_order_item = array(
						'order_item_name' => $order_shipping['title'],
						'order_item_type' => 'shipping',
					);

					$shipping_order_item_id = woocommerce_add_order_item( $order_id, $shipping_order_item );

					if ( $shipping_order_item_id ) {
						woocommerce_add_order_item_meta( $shipping_order_item_id, 'method_id', $order_shipping['method_id'] );
						woocommerce_add_order_item_meta( $shipping_order_item_id, 'cost',      $order_shipping['cost'] );
					}
				}

				// create the tax order items (WC 2.1+)
				foreach ( $post['tax_items'] as $tax_item ) {

					$tax_order_item = array(
						'order_item_name' => $tax_item['title'],
						'order_item_type' => 'tax',
					);

					$tax_order_item_id = woocommerce_add_order_item( $order_id, $tax_order_item );

					if ( $tax_order_item_id ) {
						woocommerce_add_order_item_meta( $tax_order_item_id, 'rate_id',             $tax_item['rate_id'] );
						woocommerce_add_order_item_meta( $tax_order_item_id, 'label',               $tax_item['label'] );
						woocommerce_add_order_item_meta( $tax_order_item_id, 'compound',            $tax_item['compound'] );
						woocommerce_add_order_item_meta( $tax_order_item_id, 'tax_amount',          $tax_item['tax_amount'] );
						woocommerce_add_order_item_meta( $tax_order_item_id, 'shipping_tax_amount', $tax_item['shipping_tax_amount'] );
					}
				}

				// Grant downloadalbe product permissions
				if ( $add_download_permissions ) {
					woocommerce_downloadable_product_permissions( $order_id );
				}

				// add order notes
				$order = new WC_Order( $order_id );
				foreach ( $post['notes'] as $order_note ) {
					$order->add_order_note( $order_note );
				}

				// record the product sales
				$order->record_product_sales();

			} // ! dry run

			// was an original order number provided?
			if ( ! empty( $post['order_number_formatted'] ) ) {
				if ( ! $dry_run ) {
					// do our best to provide some custom order number functionality while also allowing 3rd party plugins to provide their own custom order number facilities
					do_action( 'woocommerce_set_order_number', $order, $post['order_number'], $post['order_number_formatted'] );
					$order->add_order_note( sprintf( __( "Original order #%s", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $post['order_number_formatted'] ) );

					// get the order so we can display the correct order number
					$order = new WC_Order( $order_id );
				}

				$this->processed_posts[ $post['order_number_formatted'] ] = $post['order_number_formatted'];
			}

			$this->imported++;
			$this->log->add( sprintf( __( '> Finished importing order %s', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $dry_run ? "" : $order->get_order_number() ) );

		}


		$this->log->add( __( 'Finished processing orders.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );

		unset( $this->posts );
	}


	/**
	 * Action to set the custom order number.  This can be modified by 3rd party
	 * plugins via the applied filters, or replaced wholesale if entirely different
	 * logic is required
	 *
	 * @param WC_Order $order order object
	 * @param int $order_number incrementing order number piece
	 * @param string $order_number_formatted formatted order number piece
	 */
	public function woocommerce_set_order_number( $order, $order_number, $order_number_formatted ) {
		// the best we can do to tie the newly imported order to the old, is to
		//  at least record the order number internally (allowing 3rd party plugins
		//  to specify the order number meta field name), and set a visible order
		//  note indicating the original order number.  If the user has a custom order
		//  number plugin like the Sequential Order Number Pro installed, then things
		//  will be even cleaner on the backend
		update_post_meta( $order->id, apply_filters( 'woocommerce_order_number_meta_name',           '_order_number' ),           $order_number );
		update_post_meta( $order->id, apply_filters( 'woocommerce_order_number_formatted_meta_name', '_order_number_formatted' ), $order_number_formatted );
	}


	/**
	 * Parse a CSV file
	 *
	 * @param string $file Path to CSV file for parsing
	 *
	 * @return array Information gathered from the CSV file
	 */
	private function parse( $file ) {
		$parser = new WC_CSV_Customer_Parser( 'orders' );
		return $parser->parse( $file, $this->delimiter );
	}


	/**
	 * Display import page title
	 */
	private function header() {
		echo '<div class="wrap"><div class="icon32" id="icon-woocommerce-importer"><br></div>';
		echo '<h2>' . __( 'Import Orders', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</h2>';
	}


	/**
	 * Close div.wrap
	 */
	private function footer() {
		echo '<script> jQuery(".importer_loader, .progress").hide(); </script>';
		echo '</div>';
	}


	/**
	 * Display introductory text and file upload form
	 */
	private function greet() {
		echo '<div class="narrow">';
		echo '<p>' . __( 'Hi there! Upload a CSV file containing order data to import the contents into your shop.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</p>';
		echo '<p>' . __( 'Choose a CSV (.csv) file to upload, then click Upload file and import.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</p>';

		$action = 'admin.php?import=woocommerce_order_csv&amp;step=1&amp;merge=' . ( ! empty( $_GET['merge'] ) ? 1 : 0 );

		$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size = size_format( $bytes );
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) :
			?><div class="error"><p><?php _e( 'Before you can upload your import file, you will need to fix the following error:', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></p>
			<p><strong><?php echo $upload_dir['error']; ?></strong></p></div><?php
		else :
			?>
			<form enctype="multipart/form-data" id="import-upload-form" method="post" action="<?php echo esc_attr( wp_nonce_url( $action, 'import-upload' ) ); ?>">
				<table class="form-table">
					<tbody>
						<tr>
							<th>
								<label for="upload"><?php _e( 'Choose a file from your computer:', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></label>
							</th>
							<td>
								<input type="file" id="upload" name="import" size="25" />
								<input type="hidden" name="action" value="save" />
								<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
								<small><?php printf( __( 'Maximum size: %s', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $size ); ?></small>
							</td>
						</tr>
						<?php if ( $this->file_url_import_enabled ) : ?>
						<tr>
							<th>
								<label for="file_url"><?php _e( 'OR enter path to file:', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></label>
							</th>
							<td>
								<?php echo ' ' . ABSPATH . ' '; ?><input type="text" id="file_url" name="file_url" size="25" />
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<th><label><?php _e( 'Delimiter', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></label><br/></th>
							<td><input type="text" name="delimiter" placeholder="," size="2" /></td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Upload file and import', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?>" />
				</p>
			</form>
			<?php
		endif;

		echo '</div>';
	}


	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import
	 *
	 * @see WP_Importer::bump_request_timeout()
	 * @param int $val timeout value
	 * @return int 60
	 */
	function bump_request_timeout( $val ) {
		return 60;
	}

}
