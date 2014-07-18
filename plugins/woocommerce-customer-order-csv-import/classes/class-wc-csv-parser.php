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

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooCommerce CSV Importer class for managing parsing of CSV files.  This
 * class is responsible for the physical parsing of the import files into
 * useful datastructures, and provides some field validations/normalizations.
 * Basically prepares the import data for loading into the database by the import
 * classes.
 */
class WC_CSV_Customer_Parser {

	private $type;

	/**
	 * Construct and initialize the parser
	 *
	 * @param string $type one of 'customer' or 'order'
	 */
	public function __construct( $type ) {

		$this->type = $type;

		$this->user_data_fields = array(
			"username",
			"password",
			"email",
			"date_registered",
			"role",
			"url",
		);

		$this->user_meta_fields = array(
			"billing_first_name",
			"billing_last_name",
			"billing_company",
			"billing_address_1",
			"billing_address_2",
			"billing_city",
			"billing_state",
			"billing_postcode",
			"billing_country",
			"billing_email",
			"billing_phone",
			"shipping_first_name",
			"shipping_last_name",
			"shipping_company",
			"shipping_address_1",
			"shipping_address_2",
			"shipping_city",
			"shipping_state",
			"shipping_postcode",
			"shipping_country",
			"paying_customer",
		);


		$this->order_meta_fields = array(
			"order_tax",
			"order_shipping",
			"order_shipping_tax",
			"cart_discount",
			"order_discount",
			"order_total",
			"payment_method",
			"customer_user",
			"billing_first_name",
			"billing_last_name",
			"billing_company",
			"billing_address_1",
			"billing_address_2",
			"billing_city",
			"billing_state",
			"billing_postcode",
			"billing_country",
			"billing_email",
			"billing_phone",
			"shipping_first_name",
			"shipping_last_name",
			"shipping_company",
			"shipping_address_1",
			"shipping_address_2",
			"shipping_city",
			"shipping_state",
			"shipping_postcode",
			"shipping_country",
			"Download Permissions Granted",
		);

		// pre WC 2.1 used post meta to store the single shipping method
		if ( ! SV_WC_Plugin_Compatibility::is_wc_version_gte_2_1() ) {
			$this->order_meta_fields[] = "shipping_method";
		}

		/**
		 * Other order fields:
		 *
			"date",
			"status",
			"customer_note",
			"order_item_1",
			"order_notes"
			"order_number",
		 */

		// coupon post and special/required fields
		$this->coupon_data_fields = array(
			"coupon_code",   // post title
			"description",   // post excerpt
			"discount_type", // required
		);

		// coupon meta fields (post_meta)
		$this->coupon_meta_fields = array(
			"coupon_amount",
			"free_shipping",
			"individual_use",
			"apply_before_tax",
			"minimum_amount",
			"products",
			"exclude_products",
			"product_categories",
			"exclude_product_categories",
			"customer_emails",
			"usage_limit",
			"expiry_date",
			"usage_count",
			"exclude_sale_items",
			"usage_limit_per_user",
			"limit_usage_to_x_items",
		);
	}

	private function format_data_from_csv( $data, $enc ) {
		$data = ( 'UTF-8' == $enc ) ? $data : utf8_encode( $data );

		return trim( $data );
	}


	/**
	 * Takes a heading and normalizes it based on the current importer type
	 */
	private function normalize_heading( $heading ) {
		// lowercase and replace space with underscores if not a custom meta value
		$s_heading = trim( strtolower( $heading ) );
		if ( 'meta:' != substr( $s_heading, 0, 5 ) ) $s_heading = str_replace( ' ', '_', $s_heading );

		// handle any coupon heading aliases
		if ( 'coupons' == $this->type ) {
			switch ( $s_heading ) {
				case 'exclude_categories': return 'exclude_product_categories';
			}
		} else if ( 'orders' == $this->type ) {
			switch ( $s_heading ) {
				// translations for the Customer/Order Export plugin format
				case 'order_status':      return 'status';
				case 'shipping':          return 'order_shipping';
				case 'shipping tax':      return 'order_shipping_tax';
				case 'tax':               return 'order_tax';
				case 'billing_post_code': return 'billing_postcode';
			}
		}

		// default: return the heading as-is
		return $s_heading;
	}


	/**
	 * Reads lines from CSV-formatted $file, storing them into data arrays
	 * which are then passed off to the specific customer/order methods for
	 * the next phase of parsing.
	 *
	 * @param string $file import file name
	 *
	 * @return array containing keys 'customer' or 'order' mapped to the parsed
	 *         data, and key 'skipped' with a count of the skipped rows
	 */
	function parse( $file, $delimiter ) {

		global $WC_CSV_Import;

		// Set locale
		$enc = mb_detect_encoding( $file, 'UTF-8, ISO-8859-1', true );
		if ( $enc ) setlocale( LC_ALL, 'en_US.' . $enc );
		@ini_set( 'auto_detect_line_endings', true );

		// Merging
		$merging = ( ! empty( $_REQUEST['merge'] ) && $_REQUEST['merge'] ) ? true : false;
		// skip records?
		$record_offset = isset( $_REQUEST['record_offset'] ) && is_numeric( $_REQUEST['record_offset'] ) && $_REQUEST['record_offset'] >= 0 ? $_REQUEST['record_offset'] : 0;

		// Parse $file
		$raw_headers = $parsed_data = array();

		// Put all CSV data into an associative array
		if ( false !== ( $handle = fopen( $file, "r" ) ) ) {

			// get the CSV header row with column names
			$header = fgetcsv( $handle, 0, $delimiter );

			while ( false !== ( $line = fgetcsv( $handle, 0, "," ) ) ) {
				$row = array();
				foreach ( $header as $key => $heading ) {

					// clean the heading
					$s_heading = $this->normalize_heading( $heading );

					// Add the parsed data, keyed off the normalized heading
					$row[ $s_heading ] = ( isset( $line[ $key ] ) ) ? $this->format_data_from_csv( $line[ $key ], $enc ) : '';

					// Raw Headers stores the actual column name in the CSV (used for setting any custom meta fields)
					$raw_headers[ $s_heading ] = $heading;
				}
				$parsed_data[] = $row;
			}
			fclose( $handle );

		}

		// peforming a dry run?
		$dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] ? true : false;
		if ( $dry_run ) $WC_CSV_Import->log->add( sprintf( __( 'Dry Run: no %s will be imported', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $this->type ) );

		$WC_CSV_Import->log->add( sprintf( __( 'Parsing %s CSV.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $this->type ) );

		if ( 'customers'   == $this->type ) $result = $this->parse_customers( $parsed_data, $raw_headers, $merging, $record_offset );
		elseif ( 'orders'  == $this->type ) $result = $this->parse_orders(    $parsed_data, $raw_headers, $merging, $record_offset );
		elseif ( 'coupons' == $this->type ) $result = $this->parse_coupons(   $parsed_data, $raw_headers, $merging, $record_offset );

		$WC_CSV_Import->log->add( sprintf( __( 'Finished parsing %s CSV.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $this->type ) );

		// Result
		return $result;
	}


	/**
	 * Parse and validate the coupon input file, building and returning an array of coupon data
	 * to import into the database.
	 *
	 * The coupon data is broken into two portions:  the couple of defined fields
	 * that make up the wp_posts table, and then the name-value meta data that is
	 * inserted into the wp_postmeta table.  Within the meta data category, there
	 * are known meta fields, such as 'discount_type' for instance, and then
	 * arbitrary meta fields are allowed and identified by a CSV column title with
	 * the prefix 'meta:'.
	 *
	 * @param array $parsed_data the raw data parsed from the CSV file
	 * @param array $raw_headers the headers parsed from the CSV file
	 * @param boolean $merging whether this is a straight import, or merge.  For
	 *        the coupon import this will always be false.
	 * @param int $record_offset number of records to skip before processing
	 *
	 * @return array associative array containing the key 'coupon' mapped to the parsed
	 *         data, and key 'skipped' with a count of the skipped rows
	 */
	private function parse_coupons( $parsed_data, $raw_headers, $merging, $record_offset ) {

		global $WC_CSV_Import, $wpdb;
		$results = array();

		// Count row
		$row = 0;

		// Track skipped records
		$skipped = 0;

		// first validate headers (non custom meta) and issue warnings for any unexpected ones
		foreach ( $raw_headers as $key => $value ) {
			if ( 'meta:' != substr( $key, 0, 5 ) ) {
				if ( ! in_array( $key, $this->coupon_data_fields ) && ! in_array( $key, $this->coupon_meta_fields ) ) {
					$WC_CSV_Import->log->add( sprintf( __( "> Warning: Unknown column named '%s' will be ignored.", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $value ) ) );
				}
			}
		}

		// Format coupon data
		foreach ( $parsed_data as $item ) {

			$row++;

			// skip record?
			if ( $row <= $record_offset ) {
				$WC_CSV_Import->log->add( sprintf( __( '> Row %s - skipped due to record offset.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row ) );
				continue;
			}

			$postmeta = $coupon = array();

			// give the line number and coupon code
			$WC_CSV_Import->log->add( sprintf( __( '> Row %s%s - preparing for import.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row, ( isset( $item['coupon_code'] ) && $item['coupon_code'] ? ' - "' . $item['coupon_code'] . '"' : '' ) ) );

			// coupon code is required for merging or updating
			if ( ! isset( $item['coupon_code'] ) || ! $item['coupon_code'] ) {
				$WC_CSV_Import->log->add( __( '> > Skipped. Missing coupon code.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );
				$skipped++;
				continue;
			}

			// Check for existing coupons
			$coupon_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = 'shop_coupon' AND post_status = 'publish' AND post_title = %s", $item['coupon_code'] ) );

			if ( $merging ) {
				// when merging we need that coupon to exist
				if ( ! $coupon_found ) {
					$WC_CSV_Import->log->add( sprintf( __( "> > Skipped. Coupon code '%s' not found, unable to merge.", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $item['coupon_code'] ) ) );
					$skipped++;
					continue;
				}
				// record the coupon id
				$coupon['id'] = $coupon_found;
			} else {
				// when inserting we need it to not exist
				if ( $coupon_found ) {
					$WC_CSV_Import->log->add( sprintf( __( "> > Skipped. Coupon code '%s' already exists.", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $item['coupon_code'] ) ) );
					$skipped++;
					continue;
				}
			}

			// Get the set of possible coupon discount types
			$discount_types = SV_WC_Plugin_Compatibility::wc_get_coupon_types();

			// discount type is required
			if ( ! $merging && ( ! isset( $item['discount_type'] ) || ! $item['discount_type'] ) ) {
				$WC_CSV_Import->log->add( __( '> > Skipped. Missing discount type.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );
				$skipped++;
				continue;
			}

			// Check for the discount type validity both by key and value (ie either 'fixed_cart' or 'Cart Discount'
			if ( isset( $item['discount_type'] ) && $item['discount_type'] ) {
				$discount_type_is_valid = false;
				foreach ( $discount_types as $key => $value ) {
					if ( 0 === strcasecmp( $key, $item['discount_type'] ) || 0 === strcasecmp( $value, __( $item['discount_type'], WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) ) ) {
						$discount_type_is_valid = true;
						$postmeta[] = array( 'key' => 'discount_type', 'value' => $key );
						break;
					}
				}
				if ( ! $discount_type_is_valid ) {
					$WC_CSV_Import->log->add( sprintf( __( "> > Skipped. Unknown discount type '%s'.", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $item['discount_type'] ) ) );
					$skipped++;
					continue;
				}
			}

			// build the coupon data object
			$coupon['post_title']   = apply_filters( 'woocommerce_coupon_code', $item['coupon_code'] );
			$coupon['post_excerpt'] = isset( $item['description'] ) ? $item['description'] : '';

			// Get any known coupon meta fields, and default any missing ones
			foreach ( $this->coupon_meta_fields as $column ) {

				switch ( $column ) {

					case 'products':  // handle products: look up by sku
					case 'exclude_products':
						$val = isset( $item[ $column ] ) ? $item[ $column ] : '';
						$skus = array_filter( array_map( 'trim', explode( ',', $val ) ) );
						$val = array();
						foreach ( $skus as $sku ) {
							// find by sku
							$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );

							if ( ! $product_id ) {
								// unknown product
								$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Unknown product sku: %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $sku ) ) );
								$skipped++;
								continue 4;  // break outer loop
							}
							$val[] = $product_id;
						}
						$postmeta[] = array( 'key' => ( 'products' == $column ? 'product_ids' : 'exclude_product_ids' ), 'value' => implode( ',', $val ) );
					break;

					case 'product_categories':
					case 'exclude_product_categories':
						$val = isset( $item[ $column ] ) ? $item[ $column ] : '';
						$product_cats = array_filter( array_map( 'trim', explode( ',', $val ) ) );
						$val = array();
						foreach ( $product_cats as $product_cat ) {
							// validate product category
							$term = term_exists( $product_cat, 'product_cat' );

							if ( ! $term ) {
								// unknown category
								$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Unknown product category: %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $product_cat ) ) );
								$skipped++;
								continue 4;  // continue main loop
							}
							$val[] = $term['term_id'];
						}
						$postmeta[] = array( 'key' => $column, 'value' => $val );
					break;

					case 'customer_emails':
						$val = isset( $item[ $column ] ) ?  $item[ $column ] : '';
						$emails = array_filter( array_map( 'trim', explode( ',', $val ) ) );
						$val = array();
						foreach ( $emails as $email ) {
							if ( ! is_email( $email ) ) {
								// invalid email
								$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Invalid email: %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $email ) ) );
								$skipped++;
								continue 4;  // continue main loop
							}
							$val[] = $email;
						}
						$postmeta[] = array( 'key' => 'customer_email', 'value' => $val );
					break;

					case 'free_shipping':  // handle booleans, defaulting to 'no' on import  (not merge)
					case 'individual_use':
					case 'apply_before_tax':
					case 'exclude_sale_items':
						$val = ( isset( $item[ $column ] ) && $item[ $column ] ? strtolower( $item[ $column ] ) : ( $merging ? '' : 'no' ) );
						if ( $val && 'yes' != $val && 'no' != $val ) {
							$WC_CSV_Import->log->add( sprintf( __( "> > Skipped. Column '%s' must be 'yes' or 'no'.", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $raw_headers[ $column ] ) ) );
							$skipped++;
							continue 3;  // continue main loop
						}
						$postmeta[] = array( 'key' => $column, 'value' => $val );
					break;

					case 'expiry_date':
						$val = isset( $item[ $column ] ) ? $item[ $column ] : '';
						if ( $val && false === ( $val = strtotime( $val ) ) ) {
							// invalid date format
							$WC_CSV_Import->log->add( sprintf( __( "> > Skipped. Invalid date format '%s'.", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  esc_html( $item[ $column ] ) ) );
							$skipped++;
							continue 3;  // continue main loop
						}
						$postmeta[] = array( 'key' => $column, 'value' => $val ? date( 'Y-m-d', $val ) : '' );
					break;

					case 'usage_limit':  // handle integers
					case 'usage_count':
					case 'usage_limit_per_user':
					case 'limit_usage_to_x_items':
						$val = isset( $item[ $column ] ) ? $item[ $column ] : '';
						if ( '' !== $val && ! is_numeric( $val ) ) {
							// invalid usage count value
							$WC_CSV_Import->log->add( sprintf( __( "> > Skipped. Invalid %s '%s'.", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), esc_html( $raw_headers[ $column ] ), esc_html( $val ) ) );
							$skipped++;
							continue 3;  // continue main loop
						}
						$postmeta[] = array( 'key' => $column, 'value' => $val );
					break;

					default: $postmeta[] = array( 'key' => $column, 'value' => isset( $item[ $column ] ) ? $item[ $column ] : "" );
				}
			}

			// Get any custom meta fields, as long as they have values
			foreach ( $item as $key => $value ) {

				if ( '' !== $value ) continue;

				// Handle meta: columns - import as custom fields
				if ( 'meta:' == substr( $key, 0, 5 ) ) {

					// Get raw meta key name
					$meta_key = $raw_headers[ $key ];
					$meta_key = trim( str_replace( 'meta:', '', $meta_key ) );

					// Add to postmeta array
					$postmeta[] = array(
						'key'   => esc_attr( $meta_key ),
						'value' => $value,
					);

				}
			}

			$coupon['postmeta'] = $postmeta;

			// the coupon array will now contain the necessary name-value pairs for the wp_posts table, and also any meta data in the 'postmeta' array
			$results[] = $coupon;
		}

		// Result
		return array(
			$this->type => $results,
			'skipped'   => $skipped,
		);
	}


	/**
	 * Parse the order input file, building and returning an array of order data
	 * to import into the database.
	 *
	 * The order data is broken into two portions:  the couple of defined fields
	 * that make up the wp_posts table, and then the name-value meta data that is
	 * inserted into the wp_postmeta table.  Within the meta data category, there
	 * are known meta fields, such as 'billing_first_name' for instance, and then
	 * arbitrary meta fields are allowed and identified by a CSV column title with
	 * the prefix 'meta:'.
	 *
	 * @param array $parsed_data the raw data parsed from the CSV file
	 * @param array $raw_headers the headers parsed from the CSV file
	 * @param boolean $merging whether this is a straight import, or merge.  For
	 *        the order import this will always be false.
	 * @param int $record_offset number of records to skip before processing
	 *
	 * @return array associative array containing the key 'order' mapped to the parsed
	 *         data, and key 'skipped' with a count of the skipped rows
	 */
	private function parse_orders( $parsed_data, $raw_headers, $merging, $record_offset ) {

		global $WC_CSV_Import, $wpdb;

		$allow_unknown_products = isset( $_POST['allow_unknown_products'] ) && $_POST['allow_unknown_products'] ? true : false;

		$results = array();

		// Count row
		$row = 0;

		// Track skipped records
		$skipped = 0;

		// detect whether this is an import from the Customer/Order CSV Export plugin by checking for the required header names
		$csv_export_file = false;
		if ( in_array( 'Item SKU',       $raw_headers ) && in_array( 'Item Name',   $raw_headers ) &&
		     in_array( 'Item Variation', $raw_headers ) && in_array( 'Item Amount', $raw_headers ) &&
		     in_array( 'Row Price',      $raw_headers ) && in_array( 'Order ID',    $raw_headers ) ) {
			$csv_export_file = true;

			// Note: Although I would have liked to have first transformed the
			//   Customer/Order CSV Export format into our standard format, then
			//   all error reporting line numbers would be thrown off, so we'll
			//   just deal with that other format in here
		}

		// get the known shipping methods and payment gateways once
		$available_methods  = SV_WC_Plugin_Compatibility::WC()->shipping()->load_shipping_methods();
		$available_gateways = SV_WC_Plugin_Compatibility::WC()->payment_gateways->payment_gateways();
		$shop_order_status = (array) get_terms( 'shop_order_status', array( 'hide_empty' => 0, 'orderby' => 'id' ) );

		// get all defined taxes, keyed off of id
		$tax_rates = array();
		foreach ( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates" ) as $_row ) {
			$tax_rates[ $_row->tax_rate_id ] = $_row;
		}

		// Format order data
		foreach ( $parsed_data as $item ) {

			$row++;

			// skip record?
			if ( $row <= $record_offset ) {
				$WC_CSV_Import->log->add( sprintf( __( '> Row %s - skipped due to record offset.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row ) );
				continue;
			}

			$postmeta = $order = array();

			if ( ! $csv_export_file ) {
				// standard format:  optional integer order number and formatted order number
				$order_number            = ( ! empty( $item['order_number'] ) )            ? $item['order_number']            : null;
				$order_number_formatted  = ( ! empty( $item['order_number_formatted'] ) )  ? $item['order_number_formatted']  : $order_number;

				$WC_CSV_Import->log->add( sprintf( __( '> Row %s - preparing for import.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row ) );


				// validate the supplied formatted order number/order number
				if ( is_numeric( $order_number_formatted ) && ! $order_number ) $order_number = $order_number_formatted;  // use formatted for underlying order number if possible

				if ( $order_number && ! is_numeric( $order_number ) ) {

					$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Order number field must be an integer: %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  $order_number ) );
					$skipped++;
					continue;

				}

				if ( $order_number_formatted && ! $order_number ) {

					$WC_CSV_Import->log->add( __( '> > Skipped. Formatted order number provided but no numerical order number, see the documentation for further details.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );
					$skipped++;
					continue;

				}
			} else {
				// Customer/Order CSV Export plugin format.  If the Sequential
				//  Order Numbers Pro plugin is installed, order_number will be
				//  available, if the Order ID is numeric use that, but otherwise
				//  we have no idea what the underlying sequential order number might be
				$order_number_formatted = $item['order_id'];
				$order_number           = ( ! empty( $item['order_number'] ) ? $item['order_number'] : ( is_numeric( $order_number_formatted ) ? $order_number_formatted : 0 ) );
			}

			// obviously we can't set the order's post_id, so to have the ability to set order numbers
			//  we do the best that we can and make sure things work even better when a compatible
			//  plugin like the Sequential Order Number plugin is installed
			if ( $order_number_formatted ) {
				// verify that this order number isn't already in use

				// we'll give 3rd party plugins two chances to hook in their custom order number facilities:
				// first by performing a simple search using the order meta field name used by both this and the
				// Sequential Order Number Pro plugin, allowing other plugins to filter over it if needed,
				// while still providing this plugin with some base functionality
				$query_args = array(
							'numberposts' => 1,
							'meta_key'    => apply_filters( 'woocommerce_order_number_formatted_meta_name', '_order_number_formatted' ),
							'meta_value'  => $order_number_formatted,
							'post_type'   => 'shop_order',
							'post_status' => 'publish',
							'fields'      => 'ids',
						);

				$order_id = 0;
				$orders = get_posts( $query_args );
				if ( ! empty( $orders ) ) list( $order_id ) = get_posts( $query_args );

				// and secondly allowing other plugins to return an entirely different order number if the simple search above doesn't do it for them
				$order_id = apply_filters( 'woocommerce_find_order_by_order_number', $order_id, $order_number_formatted );

				if ( $order_id ) {
					$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Order %s already exists.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $order_number_formatted ) );
					$skipped++;
					continue;
				}
			}

			// handle the special (optional) customer_user field
			if ( isset( $item['customer_user'] ) && $item['customer_user'] ) {
				// attempt to find the customer user
				$found_customer = false;
				if ( is_int( $item['customer_user'] ) ) {

					$found_customer = get_user_by( 'ID', $item['customer_user'] );

					if ( ! $found_customer ) {

						$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Cannot find customer with id %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  $item['customer_user'] ) );
						$skipped++;
						continue;

					}
				} elseif ( is_email( $item['customer_user'] ) ) {
					// check by email
					$found_customer = email_exists( $item['customer_user'] );
				}

				if ( ! $found_customer ) {
					// still haven't found the customer, check by username
					$found_customer = username_exists( $item['customer_user'] );
				}

				if ( ! $found_customer ) {
					// no sign of this customer
					$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Cannot find customer with email/username %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  $item['customer_user'] ) );
					$skipped++;
					continue;
				} else {
					$item['customer_user'] = $found_customer; // user id
				}
			} elseif ( $csv_export_file && isset( $item['billing_email'] ) && $item['billing_email'] ) {
				// see if we can link the user by billing email
				$found_customer = email_exists( $item['billing_email'] );
				if ( $found_customer ) $item['customer_user'] = $found_customer;
				else $item['customer_user'] = 0;  // guest checkout
			} else {
				// guest checkout
				$item['customer_user'] = 0;
			}


			if ( ! empty( $item['status'] ) ) {
				// check order status value
				$found_status = false;
				$available_statuses = array();
				foreach ( $shop_order_status as $status ) {
					if ( 0 == strcasecmp( $status->slug, $item['status'] ) ) $found_status = true;
					$available_statuses[] = $status->slug;
				}

				if ( ! $found_status ) {
					// unknown order status
					$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Unknown order status %s (%s).', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  $item['status'], implode( $available_statuses, ', ' ) ) );
					$skipped++;
					continue;
				}
			} else {
				$item['status'] = 'processing';  // default
			}


			if ( ! empty( $item['date'] ) ) {
				if ( false === ( $item['date'] = strtotime( $item['date'] ) ) ) {
					// invalid date format
					$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Invalid date format %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  $item['date'] ) );
					$skipped++;
					continue;
				}
			} else {
				$item['date'] = time();
			}


			$order_notes = array();
			if ( ! empty( $item['order_notes'] ) ) {
				$order_notes = explode( "|", $item['order_notes'] );
			}

			// build the order data object
			$order['status']         = $item['status'];
			$order['date']           = $item['date'];
			$order['order_comments'] = ! empty( $item['customer_note'] ) ? $item['customer_note'] : null;
			$order['notes']          = $order_notes;
			if ( ! is_null( $order_number ) ) $order['order_number']           = $order_number;  // optional order number, for convenience
			if ( $order_number_formatted )    $order['order_number_formatted'] = $order_number_formatted;

			// totals
			$order_tax = $order_shipping_tax = null;

			// Get any known order meta fields, and default any missing ones to 0/null
			// the provided shipping/payment method will be used as-is, and if found in the list of available ones, the respective titles will also be set
			foreach ( $this->order_meta_fields as $column ) {

				switch ( $column ) {

					// this will be cased for pre WC 2.1 only
					case 'shipping_method':
						$value = isset( $item[ $column ] ) ? $item[ $column ] : '';

						// look up shipping method by id or title
						$shipping_method = isset( $available_methods[ $value ] ) ? $value : null;

						if ( ! $shipping_method ) {
							// try by title
							foreach ( $available_methods as $method ) {
								if ( 0 === strcasecmp( $method->title, $value ) ) {
									$shipping_method = $method->id;
									break;  // go with the first one we find
								}
							}
						}

						if ( $shipping_method ) {
							// known shipping method found
							$postmeta[] = array( 'key' => '_shipping_method',       'value' => $shipping_method );
							$postmeta[] = array( 'key' => '_shipping_method_title', 'value' => $available_methods[ $shipping_method ]->title );
						} elseif ( $csv_export_file && $value ) {
							// Customer/Order CSV Export format, shipping method title with no corresponding shipping method type found, so just use the title
							$postmeta[] = array( 'key' => '_shipping_method',       'value' => '' );
							$postmeta[] = array( 'key' => '_shipping_method_title', 'value' => $value );
						} elseif ( $value ) {
							// Standard format, shipping method but no title
							$postmeta[] = array( 'key' => '_shipping_method',       'value' => $value );
							$postmeta[] = array( 'key' => '_shipping_method_title', 'value' => '' );
						} else {
							// none
							$postmeta[] = array( 'key' => '_shipping_method',       'value' => '' );
							$postmeta[] = array( 'key' => '_shipping_method_title', 'value' => '' );
						}
					break;

					case 'payment_method':
						$value = isset( $item[ $column ] ) ? $item[ $column ] : '';

						// look up shipping method by id or title
						$payment_method = isset( $available_gateways[ $value ] ) ? $value : null;
						if ( ! $payment_method ) {
							// try by title
							foreach ( $available_gateways as $method ) {
								if ( 0 === strcasecmp( $method->title, $value ) ) {
									$payment_method = $method->id;
									break;  // go with the first one we find
								}
							}
						}

						if ( $payment_method ) {
							// known payment method found
							$postmeta[] = array( 'key' => '_payment_method',       'value' => $payment_method );
							$postmeta[] = array( 'key' => '_payment_method_title', 'value' => $available_gateways[ $payment_method ]->title );
						} elseif ( $csv_export_file && $value ) {
							// Customer/Order CSV Export format, payment method title with no corresponding payments method type found, so just use the title
							$postmeta[] = array( 'key' => '_payment_method',       'value' => '' );
							$postmeta[] = array( 'key' => '_payment_method_title', 'value' => $value );
						} elseif ( $value ) {
							// Standard format, payment method but no title
							$postmeta[] = array( 'key' => '_payment_method',       'value' => $value );
							$postmeta[] = array( 'key' => '_payment_method_title', 'value' => '' );
						} else {
							// none
							$postmeta[] = array( 'key' => '_payment_method',       'value' => '' );
							$postmeta[] = array( 'key' => '_payment_method_title', 'value' => '' );
						}
					break;

					// handle numerics
					case 'order_shipping':  // legacy
					case 'shipping_total':
						$order_shipping = isset( $item[ $column ] ) ? $item[ $column ] : 0;  // save the order shipping total for later use
						$postmeta[] = array( 'key' => '_order_shipping', 'value' => number_format( (float) $order_shipping, 2, '.', '' ) );
					break;
					case 'order_shipping_tax':  // legacy
					case 'shipping_tax_total':
						// ignore blanks but allow zeroes
						if ( isset( $item[ $column ] ) && is_numeric( $item[ $column ] ) ) {
							$order_shipping_tax = $item[ $column ];
						}
					break;
					case 'order_tax':  // legacy
					case 'tax_total':
						// ignore blanks but allow zeroes
						if ( isset( $item[ $column ] ) && is_numeric( $item[ $column ] ) ) {
							$order_tax = $item[ $column ];
						}
					break;
					case 'order_discount':
					case 'cart_discount':
					case 'order_total':
						$value = isset( $item[ $column ] ) ? $item[ $column ] : 0;
						$postmeta[] = array( 'key' => '_' . $column, 'value' => number_format( (float) $value, 2, '.', '' ) );
					break;

					case 'billing_country':
					case 'shipping_country':
						$value = isset( $item[ $column ] ) ? $item[ $column ] : '';
						// support country name or code by converting to code
						$country_code = array_search( $value, SV_WC_Plugin_Compatibility::WC()->countries->countries );
						if ( $country_code ) $value = $country_code;
						$postmeta[] = array( 'key' => '_' . $column, 'value' => $value );
					break;

					case 'Download Permissions Granted':
					case 'download_permissions_granted':
						if ( isset( $item[ 'download_permissions_granted' ] ) ) {
							if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_2_1() ) {
								$postmeta[] = array( 'key' => '_download_permissions_granted', 'value' => $item[ 'download_permissions_granted' ] );
							} else {
								$postmeta[] = array( 'key' => 'Download Permissions Granted', 'value' => $item[ 'download_permissions_granted' ] );
							}
						}
					break;

					default: $postmeta[] = array( 'key' => '_' . $column, 'value' => isset( $item[ $column ] ) ? $item[ $column ] : "" );
				}
			}

			// Get any custom meta fields
			foreach ( $item as $key => $value ) {

				if ( ! $value ) {
					continue;
				}

				// Handle meta: columns - import as custom fields
				if ( strstr( $key, 'meta:' ) ) {

					// Get meta key name
					$meta_key = ( isset( $raw_headers[ $key ] ) ) ? $raw_headers[ $key ] : $key;
					$meta_key = trim( str_replace( 'meta:', '', $meta_key ) );

					// Add to postmeta array
					$postmeta[] = array(
						'key'   => esc_attr( $meta_key ),
						'value' => $value,
					);

				}
			}

			$order_shipping_methods = array();
			$_shipping_methods      = array();

			if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_2_1() ) {

				// pre WC 2.1 format of a single shipping method
				if ( isset( $item['shipping_method'] ) && $item['shipping_method'] ) {
					// collect the shipping method id/cost
					$_shipping_methods[] = array(
						$item['shipping_method'],
						isset( $item['shipping_cost'] ) ? $item['shipping_cost'] : null
					);
				}

				// collect any additional shipping methods
				$i = null;
				if ( isset( $item['shipping_method_1'] ) ) {
					$i = 1;
				} elseif( isset( $item['shipping_method_2'] ) ) {
					$i = 2;
				}

				if ( ! is_null( $i ) ) {
					while ( ! empty( $item[ 'shipping_method_' . $i ] ) ) {

						$_shipping_methods[] = array(
							$item[ 'shipping_method_' . $i ],
							isset( $item[ 'shipping_cost_' . $i ] ) ? $item[ 'shipping_cost_' . $i ] : null
						);
						$i++;
					}
				}

				// if the order shipping total wasn't set, calculate it
				if ( ! isset( $order_shipping ) ) {

					$order_shipping = 0;
					foreach ( $_shipping_methods as $_shipping_method ) {
						$order_shipping += $_shipping_method[1];
					}
					$postmeta[] = array( 'key' => '_order_shipping' . $column, 'value' => number_format( (float) $order_shipping, 2, '.', '' ) );

				} elseif ( isset( $order_shipping ) && 1 == count( $_shipping_methods ) && is_null( $_shipping_methods[0][1] ) ) {
					// special case: if there was a total order shipping but no cost for the single shipping method, use the total shipping for the order shipping line item
					$_shipping_methods[0][1] = $order_shipping;
				}

				foreach ( $_shipping_methods as $_shipping_method ) {

					// look up shipping method by id or title
					$shipping_method = isset( $available_methods[ $_shipping_method[0] ] ) ? $_shipping_method[0] : null;

					if ( ! $shipping_method ) {
						// try by title
						foreach ( $available_methods as $method ) {
							if ( 0 === strcasecmp( $method->title, $_shipping_method[0] ) ) {
								$shipping_method = $method->id;
								break;  // go with the first one we find
							}
						}
					}

					if ( $shipping_method ) {
						// known shipping method found
						$order_shipping_methods[] = array( 'method_id' => $shipping_method, 'cost' => $_shipping_method[1], 'title' => $available_methods[ $shipping_method ]->title );
					} elseif ( $csv_export_file && $_shipping_method[0] ) {
						// Customer/Order CSV Export format, shipping method title with no corresponding shipping method type found, so just use the title
						$order_shipping_methods[] = array( 'method_id' => '', 'cost' => $_shipping_method[1], 'title' => $_shipping_method[0] );
					} elseif ( $_shipping_method[0] ) {
						// Standard format, shipping method but no title
						$order_shipping_methods[] = array( 'method_id' => $_shipping_method[0], 'cost' => $_shipping_method[1], 'title' => '' );
					}
				}
			}

			$order_items = array();
			if ( ! $csv_export_file ) {
				// standard format
				if ( ! empty( $item['order_item_1'] ) ) {
					// one or more order items
					$i = 1;
					while ( ! empty( $item[ 'order_item_' . $i ] ) ) {

						// split on non-escaped pipes
						// http://stackoverflow.com/questions/6243778/split-string-by-delimiter-but-not-if-it-is-escaped
						$_item_meta = preg_split( "~\\\\.(*SKIP)(*FAIL)|\|~s", $item[ 'order_item_' . $i ] );

						// fallback: try a simple explode, since the above apparently doesn't always work
						if ( $item[ 'order_item_' . $i ] && empty( $_item_meta ) ) {
							$_item_meta = explode( '|', $item[ 'order_item_' . $i ] );
						}

						// pop off the special sku, qty and total values
						$product_identifier = array_shift( $_item_meta );  // sku or product_id:id
						$qty                = array_shift( $_item_meta );
						$total              = array_shift( $_item_meta );

						if ( ! $product_identifier || ! $qty || ! $total ) {
							// invalid item
							$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Missing SKU, quantity or total for %s on row %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  'order_item_' . $i, $row ) );
							$skipped++;
							continue 2;  // break outer loop
						}

						// product_id or sku
						if ( false !== strpos( $product_identifier, 'product_id:' ) ) {
							// product by product_id
							$product_id = substr( $product_identifier, 11 );

							// not a product
							if ( 'product' != get_post_type( $product_id ) ) {
								$product_id = '';
							}

						} else {
							// find by sku
							$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value=%s LIMIT 1", $product_identifier ) );
						}

						if ( ! $allow_unknown_products && ! $product_id ) {
							// unknown product
							$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Unknown order item: %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  $product_identifier ) );
							$skipped++;
							continue 2;  // break outer loop
						}

						// get any additional item meta
						$item_meta = array();
						foreach ( $_item_meta as $pair ) {

							// replace any escaped pipes
							$pair = str_replace( '\|', '|', $pair );

							// find the first ':' and split into name-value
							$split = strpos( $pair, ':' );
							$name  = substr( $pair, 0, $split );
							$value = substr( $pair, $split + 1 );

							$item_meta[ $name ] = $value;
						}

						$order_items[] = array( 'product_id' => $product_id, 'qty' => $qty, 'total' => $total, 'meta' => $item_meta );

						$i++;
					}
				}
			} else {
				// CSV Customer/Order Export format
				$sku   = $item['item_sku'];
				$qty   = $item['item_amount'];
				$total = $item['row_price'];

				if ( ! $sku || ! $qty || ! $total ) {
					// invalid item
					$WC_CSV_Import->log->add( sprintf( __( '> > Row %d - %s - skipped. Missing SKU, quantity or total', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row, $item['order_id'] ) );
					$skipped++;
					continue;  // break outer loop
				}

				// find by sku
				$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value=%s LIMIT 1", $sku ) );

				if ( ! $product_id ) {
					// unknown product
					$WC_CSV_Import->log->add( sprintf( __( '> > Row %d - %s - skipped. Unknown order item: %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row, $item['order_id'], $sku ) );
					$skipped++;
					continue;  // break outer loop
				}

				$order_items[] = array( 'product_id' => $product_id, 'qty' => $qty, 'total' => $total );
			}

			$tax_items = array();

			// standard tax item format which supports multiple tax items in numbered columns containing a pipe-delimated, colon-labeled format
			if ( ! empty( $item['tax_item_1'] ) || ! empty( $item['tax_item'] ) ) {
				// one or more order tax items

				// get the first tax item
				$tax_item = ! empty( $item['tax_item_1'] ) ? $item['tax_item_1'] : $item['tax_item'];

				$i = 1;
				$tax_amount_sum = $shipping_tax_amount_sum = 0;
				while ( $tax_item ) {

					$tax_item_data = array();

					// turn "label: Tax | tax_amount: 10" into an associative array
					foreach ( explode( '|', $tax_item ) as $piece ) {
						list( $name, $value ) = explode( ':', $piece );
						$tax_item_data[ trim( $name ) ] = trim( $value );
					}

					// default rate id to 0 if not set
					if ( ! isset( $tax_item_data['rate_id'] ) ) {
						$tax_item_data['rate_id'] = 0;
					}

					// have a tax amount or shipping tax amount
					if ( isset( $tax_item_data['tax_amount'] ) || isset( $tax_item_data['shipping_tax_amount'] ) ) {

						// try and look up rate id by label if needed
						if ( isset( $tax_item_data['label'] ) && $tax_item_data['label'] && ! $tax_item_data['rate_id'] ) {
							foreach ( $tax_rates as $tax_rate ) {

								if ( 0 === strcasecmp( $tax_rate->tax_rate_name, $tax_item_data['label'] ) ) {
									// found the tax by label
									$tax_item_data['rate_id'] = $tax_rate->tax_rate_id;
									break;
								}
							}
						}

						// check for a rate being specified which does not exist, and clear it out (technically an error?)
						if ( $tax_item_data['rate_id'] && ! isset( $tax_rates[ $tax_item_data['rate_id'] ] ) ) {
							$tax_item_data['rate_id'] = 0;
						}

						// default label of 'Tax' if not provided
						if ( ! isset( $tax_item_data['label'] ) || ! $tax_item_data['label'] ) {
							$tax_item_data['label'] = 'Tax';
						}

						// default tax amounts to 0 if not set
						if ( ! isset( $tax_item_data['tax_amount'] ) ) {
							$tax_item_data['tax_amount'] = 0;
						}
						if ( ! isset( $tax_item_data['shipping_tax_amount'] ) ) {
							$tax_item_data['shipping_tax_amount'] = 0;
						}

						// handle compound flag by using the defined tax rate value (if any)
						if ( ! isset( $tax_item_data['tax_rate_compound'] ) ) {
							$tax_item_data['tax_rate_compound'] = '';
							if ( $tax_item_data['rate_id'] ) {
								$tax_item_data['tax_rate_compound'] = $tax_rates[ $tax_item_data['rate_id'] ]->tax_rate_compound;
							}
						}

						$tax_items[] = array(
							'title'               => '',
							'rate_id'             => $tax_item_data['rate_id'],
							'label'               => $tax_item_data['label'],
							'compound'            => $tax_item_data['tax_rate_compound'],
							'tax_amount'          => $tax_item_data['tax_amount'],
							'shipping_tax_amount' => $tax_item_data['shipping_tax_amount'],
						);

						// sum up the order totals, in case it wasn't part of the import
						$tax_amount_sum          += $tax_item_data['tax_amount'];
						$shipping_tax_amount_sum += $tax_item_data['shipping_tax_amount'];
					}

					// get the next tax item (if any)
					$i++;
					$tax_item = isset( $item[ 'tax_item_' . $i ] ) ? $item[ 'tax_item_' . $i ] : null;
				}

				if ( ! is_numeric( $order_tax ) ) {
					$order_tax = $tax_amount_sum;
				}
				if ( ! is_numeric( $order_shipping_tax ) ) {
					$order_shipping_tax = $shipping_tax_amount_sum;
				}
			}

			// default to zero if not set
			if ( ! is_numeric( $order_tax ) ) {
				$order_tax = 0;
			}
			if ( ! is_numeric( $order_shipping_tax ) ) {
				$order_shipping_tax = 0;
			}

			// no tax items specified, so create a default one using the tax totals
			if ( 0 == count( $tax_items ) ) {

				$tax_items[] = array(
					'title'               => '',
					'rate_id'             => 0,
					'label'               => 'Tax',
					'compound'            => '',
					'tax_amount'          => $order_tax,
					'shipping_tax_amount' => $order_shipping_tax,
				);
			}

			// add the order tax totals to the order meta
			$postmeta[] = array( 'key' => '_order_tax',          'value' => number_format( (float) $order_tax, 2, '.', '' ) );
			$postmeta[] = array( 'key' => '_order_shipping_tax', 'value' => number_format( (float) $order_shipping_tax, 2, '.', '' ) );

			// Customer/Order CSV Export format has orders broken up onto multiple lines, one per order item
			//  so detect whether we are continuing an existing order
			if ( $csv_export_file ) {
				$ix = count( $results );
				if ( $ix > 0 && $results[ $ix - 1 ]['order_number_formatted'] == $order['order_number_formatted'] ) {
					// continuing an existing order, add the current order item
					$results[ $ix - 1 ]['order_items'][] = $order_items[0];
					$order = null;
				}
			}

			if ( $order ) {
				$order['postmeta']       = $postmeta;
				$order['order_items']    = $order_items;
				$order['order_shipping'] = $order_shipping_methods;  // WC 2.1+
				$order['tax_items']      = $tax_items;

				// the order array will now contain the necessary name-value pairs for the wp_posts table, and also any meta data in the 'postmeta' array
				$results[] = $order;
			}
		}

		// Result
		return array(
			$this->type => $results,
			'skipped'   => $skipped,
		);
	}


	/**
	 * Parse the customer input file, building and returning an array of customer data
	 * to import into the database.
	 *
	 * The customer data is broken into two portions:  the couple of defined fields
	 * that make up the wp_users table, and then the name-value meta data that is
	 * inserted into the wp_usermeta table.  Within the meta data category, there
	 * are known meta fields, such as 'billing_first_name' for instance, and then
	 * arbitrary meta fields are allowed and identified by a CSV column title with
	 * the prefix 'meta:'.
	 *
	 * @param array $parsed_data the raw data parsed from the CSV file
	 * @param array $raw_headers the headers parsed from the CSV file
	 * @param boolean $merging whether this is a straight import, or merge
	 * @param int $record_offset number of records to skip before processing
	 *
	 * @return array associative array containing the key 'customer' mapped to the parsed
	 *         data, and key 'skipped' with a count of the skipped rows
	 */
	private function parse_customers( $parsed_data, $raw_headers, $merging, $record_offset ) {

		global $WC_CSV_Import;
		$results = array();

		// Count row
		$row = 0;

		// Track skipped records
		$skipped = 0;

		// use billing address for shipping address?
		$billing_address_for_shipping_address = isset( $_REQUEST['billing_address_for_shipping_address'] ) ? $_REQUEST['billing_address_for_shipping_address'] : false;

		// Format user data
		foreach ( $parsed_data as $item ) {

			$row++;

			// skip record?
			if ( $row <= $record_offset ) {
				$WC_CSV_Import->log->add( sprintf( __( '> Row %s - skipped due to record offset.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row ) );
				continue;
			}

			// ID field mapping
			$customer_id = ( ! empty( $item['id'] ) ) ? $item['id'] : 0;
			if ( isset( $item['username'] ) ) $item['username'] = sanitize_user( $item['username'] );  // sanitize username

			if ( $merging ) {

				$WC_CSV_Import->log->add( sprintf( __( '> Row %s - preparing for merge.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row ) );

				// Required fields
				if ( ! $customer_id && empty( $item['email'] ) && empty( $item['username'] ) ) {
					$WC_CSV_Import->log->add( __( '> > Skipped. Cannot merge without id, email or username.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );
					$skipped++;
					continue;
				}

				// Check user exists
				if ( $customer_id ) {

					$found_customer = get_user_by( 'ID', $customer_id );

					if ( ! $found_customer ) {

						$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Cannot find customer with id %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ),  $customer_id ) );
						$skipped++;
						continue;

					}

					$WC_CSV_Import->log->add( sprintf( __( '> > Found user with ID %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $customer_id ) );

				} elseif ( isset( $item['username'] ) && $item['username'] ) {
					// check by username
					$found_customer = username_exists( $item['username'] );

					if ( ! $found_customer ) {

						$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Cannot find customer with username %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $item['username'] ) );
						$skipped++;
						continue;
					}

					$WC_CSV_Import->log->add( sprintf( __( '> > Found user with username %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $item['username'] ) );
					$customer_id = $found_customer;

				} elseif ( isset( $item['email'] ) && $item['email'] ) {

					// check by email
					$found_customer = email_exists( $item['email'] );

					if ( ! $found_customer ) {

						$WC_CSV_Import->log->add( sprintf( __( '> > Skipped. Cannot find customer with email %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $item['email'] ) );
						$skipped++;
						continue;
					}

					$WC_CSV_Import->log->add( sprintf( __( '> > Found user with email %s.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $item['email'] ) );
					$customer_id = $found_customer;
				}

			} else {
				// non-merge
				$WC_CSV_Import->log->add( sprintf( __( '> Row %s - preparing for import.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $row ) );

				// Required fields. although login (username) is technically also required, we can use email for that
				if ( empty( $item['email'] ) ) {
					$WC_CSV_Import->log->add( __( '> > Skipped. No email set for new customer.', WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ) );
					$skipped++;
					continue;
				}
			}

			// verify the role: allow by slug or by name.  skip if not found (is this too harsh?)
			if ( isset( $item['role'] ) && $item['role'] ) {
				global $wp_roles;

				if ( ! isset( $wp_roles->role_names[ $item['role'] ] ) ) {
					$found_role_by_name = false;

					// fallback to first role by name
					foreach ( $wp_roles->role_names as $slug => $name ) {
						if ( $name == $item['role'] ) {
							$item['role'] = $slug;
							$found_role_by_name = true;
							break;
						}
					}

					if ( ! $found_role_by_name ) {
						$WC_CSV_Import->log->add( sprintf( __( "> > Skipped. Role '%s' not found.", WC_Customer_CSV_Import_Suite::TEXT_DOMAIN ), $item['role'] ) );
						$skipped++;
						continue;
					}
				}
			}

			$usermeta = $customer = array();

			// collect the user data fields
			$customer['id'] = $customer_id;
			$customer['email'] = $item['email'];  // required
			$customer['username'] = isset( $item['username'] ) && $item['username'] ? $item['username'] : sanitize_user( $item['email'] );  // required: username or email
			if ( ! empty( $item['date_registered'] ) ) $customer['user_registered'] = $item['date_registered'];  // database column name is user_registered, input file column name is date_registered
			if ( ! empty( $item['password'] ) ) $customer['password'] = $item['password'];
			if ( ! $merging && empty( $customer['password'] ) ) {
				$customer['password'] = wp_generate_password( 12, false );
			}
			// user role, defaults to customer if not merging
			$customer['role'] = isset( $item['role'] ) && $item['role'] ? $item['role'] : ( ! $merging ? 'customer' : '' );
			$customer['url'] = isset( $item['url'] ) ? $item['url'] : null;

			// Get any known user meta fields
			foreach ( $this->user_meta_fields as $column ) {
				// on insert use all columns, on merge only use if there is a value.
				if ( isset( $item[ $column ] ) && ( $item[ $column ] || ! $merging ) ) $usermeta[ $column ] = array( 'key' => $column, 'value' => $item[ $column ] );

				// on create default wp user first/last name to billing first/last
				if ( ! $merging ) {
					if ( 'billing_first_name' == $column && ! empty( $item[ $column ] ) ) {
						$usermeta['first_name'] = array( 'key' => 'first_name', 'value' => $item[ $column ] );
					} elseif ( 'billing_last_name' == $column && ! empty( $item[$column] ) ) {
						$usermeta['last_name'] = array( 'key' => 'last_name',  'value' => $item[ $column ] );
					}
				}

				// normalize the paying customer field
				if ( "paying_customer" == $column && isset( $item[ $column ] ) ) {
					if ( "yes" == $item[ $column ] )     $usermeta[ $column ]['value'] = 1;
					elseif ( "no" == $item[ $column ] )  $usermeta[ $column ]['value'] = 0;
				}
			}

			// handle the billing/shipping address defaults as needed
			if ( $billing_address_for_shipping_address ) {
				foreach ( $this->user_meta_fields as $column ) {

					// default shipping address fields to billing address as needed (if shipping address fields are actually set those values will be used)
					if ( 'billing' == substr( $column, 0, 7 ) && isset( $item[ $column ] ) && $item[ $column ] ) {
						$shipping_column = 'shipping' . substr( $column, 7 );

						// if the shipping column exists and not set for the current user, use the billing address value
						if ( in_array( $shipping_column, $this->user_meta_fields ) &&
						     ( ! isset( $usermeta[ $shipping_column ]['value'] ) || ! $usermeta[ $shipping_column ]['value'] ) ) {
							$usermeta[ $shipping_column ] = array( 'key' => $shipping_column, 'value' => $item[ $column ] );
						}
					}
				}
			}

			// Get any custom meta fields
			foreach ( $item as $key => $value ) {

				if ( ! $value ) continue;

				// Handle meta: columns - import as custom fields
				if ( strstr( $key, 'meta:' ) ) {

					// Get meta key name
					$meta_key = ( isset( $raw_headers[ $key ] ) ) ? $raw_headers[ $key ] : $key;
					$meta_key = trim( str_replace( 'meta:', '', $meta_key ) );

					// Add to postmeta array
					$usermeta[ $meta_key ] = array(
						'key'   => esc_attr( $meta_key ),
						'value' => $value,
					);

				}
			}

			$customer['usermeta'] = $usermeta;
			// the order array will now contain the necessary name-value pairs for the wp_users table, and also any meta data in the 'usermeta' array
			$results[] = $customer;
		}

		// Result
		return array(
			$this->type => $results,
			'skipped'   => $skipped,
		);
	}

}
