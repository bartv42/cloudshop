<?php
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
 * WordPress Importer class for managing the import process of a CSV file
 */
class WC_CSV_Customer_Import extends WP_Importer {

	private $id; // CSV attachment ID
	private $file_url; // CSV attachment url

	// information to import from CSV file
	private $users = array();
	private $processed_users = array();

	// Counts
	public $log;
	private $merged = 0;
	private $skipped = 0;
	private $imported = 0;

	private $file_url_import_enabled = true;
	private $delimiter;

	/**
	 * Construct and initialize the importer
	 */
	public function __construct() {
		$this->log = new WC_CSV_Customer_Log();

		$this->file_url_import_enabled = apply_filters( 'woocommerce_csv_customer_file_url_import_enabled', true );
	}


	/**
	 * Manages the three separate stages of the CSV import process:
	 *
	 * 1. Display introductory text and file upload form
	 * 2. Handle the physical upload of the file and provide some configuration options
	 * 3. Perform the parsing/importing of the import file
	 */
	public function dispatch() {
		$this->header();

		if ( ! empty( $_POST['delimiter'] ) ) {
			$this->delimiter = stripslashes( trim( $_POST['delimiter'] ) );
		}

		if ( ! $this->delimiter ) {
			$this->delimiter = ',';
		}

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];

		switch ( $step ) {
			case 0:
				$this->greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				if ( $this->handle_upload() ) {
					$this->import_options();
				}
				break;
			case 2:
				check_admin_referer( 'import-woocommerce' );

				$this->id = (int) $_POST['import_id'];
				if ( $this->file_url_import_enabled ) {
					$this->file_url = esc_attr( $_POST['import_url'] );
				}

				if ( $this->id ) {
					$file = get_attached_file( $this->id );
				} elseif ( $this->file_url_import_enabled ) {
					$file = ABSPATH . $this->file_url;
				}

				if ( $file ) {
					echo '<div class="importer_loader"></div>';

					add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

					if ( function_exists( 'gc_enable' ) ) {
						gc_enable();
					}

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
	 * - Default shipping address to billing address if set
	 * - Record Offset
	 */
	private function import_options() {
		?>
		<form action="<?php echo admin_url( 'admin.php?import=woocommerce_customer_csv&step=2' ); ?>" method="post">
			<?php wp_nonce_field( 'import-woocommerce' ); ?>
			<input type="hidden" name="import_id" value="<?php echo $this->id; ?>" />
			<?php if ( $this->file_url_import_enabled ) : ?>
			<input type="hidden" name="import_url" value="<?php echo $this->file_url; ?>" />
			<?php endif; ?>
			<input type="hidden" name="merge" value="<?php echo ! empty( $_REQUEST['merge'] ) ? "1" : "0" ?>" />
			<h3><?php _e( 'Shipping Address', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></h3>
			<p>
				<input type="checkbox" value="1" name="billing_address_for_shipping_address" id="billing_address_for_shipping_address" />
				<label for="billing_address_for_shipping_address"><?php _e( 'Use billing address as shipping address if not set', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></label>
			</p>
			<h3><?php _e( 'Advanced Options', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></h3>
			<p>
				<label for="dry_run"><?php _e( 'Dry Run', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) ?></label>
				<input type="checkbox" value="1" name="dry_run" id="dry_run" />
				<span class="description"><?php _e( 'Perform a test dry run of the import process to check for errors prior to the actual import', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></span>
			</p>
			<p>
				<label for="hashed_passwords"><?php _e( "Don't hash user passwords", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) ?></label>
				<input type="checkbox" value="1" name="hashed_passwords" id="hashed_passwords" />
				<span class="description"><?php _e( 'Enable this if your customer import file contains passwords which are already correctly hashed for WordPress.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ); ?></span>
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
		$this->process_users();
		echo '</div>';

		// Show Result
		echo '<div class="updated settings-error below-h2"><p>' .
		  sprintf( __( 'Import complete - imported <strong>%s</strong>, merged <strong>%s</strong>, skipped <strong>%s</strong>', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $this->imported, $this->merged, $this->skipped ) .
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
		$this->users = $import_data['customers'];
	}


	/**
	 * Parse a CSV file
	 *
	 * @param string $file Path to CSV file for parsing
	 * @return array Information gathered from the CSV file
	 */
	private function parse( $file ) {
		$parser = new WC_CSV_Customer_Parser( 'customers' );
		return $parser->parse( $file, $this->delimiter );
	}


	/**
	 * Performs post-import cleanup of files and the cache
	 */
	private function import_end() {
		global $wc_customer_csv_import;
		wp_cache_flush();

		echo '<p>' . __( 'All done!', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . ' <a href="' . network_admin_url( 'users.php' ) . '">' . __( 'View Users', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</a>' .
		  ' or <a href="' . $wc_customer_csv_import->get_settings_url() . '">Import another file</a>.</p>';

		do_action( 'import_end' );
	}


	/**
	 * Handles the CSV upload
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
	 * Create new users based on import information
	 */
	private function process_users() {

		global $wpdb;

		$this->imported = $this->merged = 0;
		$user_id = '';

		// peforming a dry run?
		$dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] ? true : false;
		// user passwords already hashed?
		$hashed_passwords = isset( $_POST['hashed_passwords'] ) && $_POST['hashed_passwords'] ? true : false;

		$this->log->add( '---' );
		$this->log->add( __( 'Processing customers.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );

		$merging = ( ! empty( $_REQUEST['merge'] ) && $_REQUEST['merge'] ) ? true : false;

		foreach ( $this->users as $user ) {

			if ( ! empty( $user['id'] ) && isset( $this->processed_users[ $user['id'] ] ) ){
				$this->skipped++;
				$this->log->add( __( '> Customer already processed. Skipping.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), true );
				continue;
			}

			// Check if customer exists when importing
			if ( ! $merging ) {
				$user_exists = ( isset( $user['username'] ) && $user['username'] && username_exists( $user['username'] ) ) ||
				               ( isset( $user['email'] ) && $user['email'] && email_exists( $user['email'] ) );
				if ( $user_exists ) {
					$this->skipped++;
					$this->log->add( sprintf( __( '> %s already exists.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( isset( $user['username'] ) && $user['username'] ? $user['username'] : $user['email'] ) ), true );
					continue;
				}
			}

			// validate username
			if ( ! validate_username( $user['username'] ) ) {
				$this->skipped++;
				$this->log->add( sprintf( __( '> Invalid username: %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $user['username'] ) ), true );
				continue;
			}

			// validate email
			if ( ! is_email( $user['email'] ) ) {
				$this->skipped++;
				$this->log->add( sprintf( __( '> Invalid email: %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $user['email'] ) ), true );
				continue;
			}


			if ( $merging ) {

				// Only merge fields which are set
				$user_id = $user['id'];

				$this->log->add( sprintf( __( '> Merging customer ID %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $user_id ), true );

				$userdata = array(
					'ID' => $user_id,
				);
				if ( ! empty( $user['email'] ) ) {
					$userdata['user_email'] = $user['email'];
				}
				if ( ! empty( $user['username'] ) ) {
					$userdata['user_login'] = $user['username'];
				}
				if ( ! empty( $user['user_registered'] ) ) {
					$userdata['user_registered'] = $user['user_registered'];
				}
				if ( ! empty( $user['password'] ) ) {
					$userdata['user_pass'] = $user['password'];
				}
				if ( ! empty( $user['url'] ) ) {
					$userdata['user_url'] = $user['url'];
				}

				if ( ! $dry_run ) {
					$user_id = wp_update_user( $userdata );

					if ( is_wp_error( $user_id ) ) {

						$this->skipped++;
						$this->log->add( sprintf( __( 'Failed to update customer %s: %s;', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $user['username'] ), esc_html( $user_id->get_error_message() ) ) );
						continue;

					}
				}

			} else { // importing

				// make sure email isn't in use
				if ( isset( $user['email'] ) && $user['email'] && email_exists( $user['email'] ) ) {
					$this->log->add( sprintf( __( '> > Skipped. Email %s already in use.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $user['email'] ) );
					$this->skipped++;
					continue;
				}

				// make sure username isn't in use
				if ( isset( $user['username'] ) && $user['username'] && username_exists( $user['username'] ) ) {
					$this->log->add( sprintf( __( '> > Skipped. Username %s already in use.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $user['username'] ) );
					$this->skipped++;
					continue;
				}

				// Insert customer
				$this->log->add( sprintf( __( '> Inserting %s', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $user['username'] ) ), true );

				$userdata = array(
					'user_email'      => $user['email'],
					'user_login'      => $user['username'],
					'user_registered' => isset( $user['user_registered'] ) ? $user['user_registered'] : null,
					'user_pass'       => $user['password'],
					'user_url'        => $user['url'],
				);

				if ( ! $dry_run ) {
					$user_id = wp_insert_user( $userdata, true );

					if ( is_wp_error( $user_id ) ) {

						$this->skipped++;
						$this->log->add( sprintf( __( 'Failed to import customer %s: %s;', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $user['username'] ), esc_html( $user_id->get_error_message() ) ) );
						continue;

					} else {
						// success
						$this->log->add( sprintf( __( '> Inserted - customer ID is %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $user_id ) );

					}
				}
			}

			$this->processed_users[ $user_id ] = $user_id;

			if ( ! $dry_run ) {
				// for either merging or creating, update the role (if set) and password
				if ( $user['role'] ) {
					wp_update_user( array( 'ID' => $user_id, 'role' => $user['role'] ) );
				}

				// set password without hashing
				if ( $hashed_passwords && ! empty( $user['password'] ) ) {
					$wpdb->update( $wpdb->users, array( 'user_pass' => $user['password'] ), array( 'ID' => $user_id ) );
				}

				// add/update user meta
				if ( ! empty( $user['usermeta'] ) ) {
					foreach ( $user['usermeta'] as $meta ) {
						@update_user_meta( $user_id, $meta['key'], maybe_unserialize( $meta['value'] ) );
					}
				}
			}

			if ( $merging ) {
				$this->merged++;
				$this->log->add( sprintf( __( '> Finished merging customer ID %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $user_id ) );
			} else {
				$this->imported++;
				$this->log->add( sprintf( __( '> Finished importing customer ID %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $user_id ) );
			}
		}

		$this->log->add( __( 'Finished processing customers.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );

		unset( $this->users );
	}


	/**
	 * Display import page title
	 */
	private function header() {
		echo '<div class="wrap"><div class="icon32" id="icon-woocommerce-importer"><br></div>';
		echo '<h2>' . __( 'Import Customers', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</h2>';
	}


	/**
	 * Close div.wrap
	 */
	private function footer() {
		echo '<script type="text/javascript">jQuery(".importer_loader, .progress").hide();</script>';
		echo '</div>';
	}


	/**
	 * Display introductory text and file upload form
	 */
	private function greet() {
		echo '<div class="narrow">';
		echo '<p>' . __( 'Hi there! Upload a CSV file containing customer data to import the contents into your shop.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</p>';
		echo '<p>' . __( 'Choose a CSV (.csv) file to upload, then click Upload file and import.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) . '</p>';

		$action = 'admin.php?import=woocommerce_customer_csv&amp;step=1&amp;merge=' . ( ! empty( $_GET['merge'] ) ? 1 : 0 );

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
