<?php
namespace Aelia\CurrencySwitcher\WC21;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \WC_Aelia_CurrencySwitcher;
use \Aelia_Order;
use \Aelia\CurrencySwitcher\Logger as Logger;

/**
 * Overrides the reports for WooCommerce 2.1.
 */
class Reports extends \Aelia\CurrencySwitcher\Reports {
	// @var The WooCommerce version for which this reports class has been implemented
	protected $wc_version = '21';

	/**
	 * Returns the path to WooCommerce "admin" folder. This is where basic
	 * WooCommerce classes are located.
	 *
	 * @return string
	 */
	protected function woocommerce_admin_path() {
		$wc_path = $this->woocommerce_path();
		return $wc_path . '/includes/admin';
	}

	/**
	 * Loads the base report class used by WooCommerce. All reports depend on it,
	 * therefore it must be loaded before report classes can be instantiated.
	 *
	 * @return bool
	 */
	protected function load_base_report_class() {
		$base_report_class = $this->woocommerce_admin_path() . '/reports/class-wc-admin-report.php';
		Logger::log(sprintf(__('Loading base report class: "%s".', AELIA_CS_PLUGIN_TEXTDOMAIN),
												$base_report_class),
								true);

		$result = include_once($base_report_class);
		Logger::log(sprintf(__('Result: "%d".', AELIA_CS_PLUGIN_TEXTDOMAIN),
												(int)$result),
								true);
		return $result;
	}

	/**
	 * Overrides entire reports. This method allows to replace entire report
	 * functions, and can be used when the target functions don't implement
	 * hooks that can be intercepted to alter their behaviour.
	 *
	 * @param array reports An array of reporting functions.
	 * @return array
	 */
	protected function set_reports_overrides($reports) {
		$original_reports = $reports;
		try {
			Logger::log(__('Processing reports override...', AELIA_CS_PLUGIN_TEXTDOMAIN), true);

			$result = true;
			// If the base report class cannot be loaded, then the override reports
			// won't work either. In such case, just return the reports as they are
			if(!$this->load_base_report_class()) {
				Logger::log(__('Base report class could not be loaded, report ' .
											 'overriding halted.', AELIA_CS_PLUGIN_TEXTDOMAIN),
										true);
				$result = false;
			}

			if($result) {
				Logger::log(__('Processing each report...', AELIA_CS_PLUGIN_TEXTDOMAIN), true);
				foreach($reports as $group_id => $group_info) {
					// Extract the list of the reports for the group
					$group_reports = get_value('reports', $group_info, array());

					foreach($group_reports as $report_name => $report_info) {
						// If an override for the report exists, replace the callback
						if($this->report_override_exists($report_name)) {
							Logger::log(sprintf(__('Report "%s" replaced by Currency Switcher class.', AELIA_CS_PLUGIN_TEXTDOMAIN),
																	$report_name),
													true);

							$report_info['callback'] = array($this, 'get_report');
							$group_reports[$report_name] = $report_info;
						}
					}

					// Replace the report list with the one eventually containing new callbacks
					$group_info['reports'] = $group_reports;
					$reports[$group_id] = $group_info;
				}
			}

			Logger::log(__('Reports override processing completed.', AELIA_CS_PLUGIN_TEXTDOMAIN), true);
		}
		catch(Exception $e) {
			Logger::log(sprintf(__('Exception occurred during processing, restoring original ' .
														 'reports. Error: "%s".', AELIA_CS_PLUGIN_TEXTDOMAIN),
													$e->getMessage()),
									true);
			$reports = $original_reports;
		}

		return $reports;
	}

	/**
	 * Processes the list of available reports before returning it to WooCommerce.
	 *
	 * @param arrat reports The original list of reports.
	 * @return array
	 */
	public function woocommerce_admin_reports($reports) {
		$reports = $this->set_reports_overrides($reports);

		return $reports;
	}

	/**
	 * Loads original WooCommerce report class.
	 *
	 * @param string report_name The name of the report to load.
	 * @return bool
	 */
	protected function load_report_original_class($report_name) {
		$report_name = str_replace('_', '-', $report_name);
		$report_class_file = $this->woocommerce_admin_path() . '/reports/class-wc-report-' . $report_name . '.php';

		Logger::log(sprintf(__('Loading original report class from file: "%s".', AELIA_CS_PLUGIN_TEXTDOMAIN),
												$report_class_file),
								true);
		$result = include_once($report_class_file);

		Logger::log(sprintf(__('Result: "%d".', AELIA_CS_PLUGIN_TEXTDOMAIN),
												(int)$result),
								true);
		return $result;
	}

	/**
	 * Returns the name of the class that will override the specified report.
	 *
	 * @param string report_name The report name.
	 * @return string
	 */
	protected function report_override_class($report_name) {
		$class_parts = explode('_', $report_name);
		foreach($class_parts as $idx => $part) {
			$class_parts[$idx] = ucfirst($part);
		}
		$report_name = implode('_', $class_parts);

		$report_override_class = '\Aelia\CurrencySwitcher\WC21\WC_CS_Report_' . $report_name;

		return $report_override_class;
	}

	/**
	 * Returns the name of the original class for the specified report.
	 *
	 * @param string report_name The report name.
	 * @return string
	 */
	protected function report_original_class($report_name) {
		$report_original_class = '\WC_Report_' . $report_name;
		return $report_original_class;
	}

	/**
	 * Indicates if an override class exists for the specified report.
	 *
	 * @param string report_name The report name.
	 * @return bool
	 */
	protected function report_override_exists($report_name) {
		Logger::log(sprintf(__('Checking for override class for report: "%s".', AELIA_CS_PLUGIN_TEXTDOMAIN),
												$report_name),
								true);

		// If the original class for the report doesn't exist, then even if the
		// override exists, it won't work. In such case, just log the issue and
		// return false
		if(!$this->load_report_original_class($report_name)) {
			Logger::log(__('Original report class does not exist. Override halted.', AELIA_CS_PLUGIN_TEXTDOMAIN), true);
			return false;
		}

		// Check if override class exists
		$result = class_exists($this->report_override_class($report_name));
		Logger::log(sprintf(__('Override check result: "%d".', AELIA_CS_PLUGIN_TEXTDOMAIN),
												(int)$result),
								true);

		return $result;
	}

	/**
	 * Get a report class and render it. This method returns a report class
	 * implemented by the Currency Switcher, overriding the standard one.
	 *
	 * @param string report_name The name of the report to render.
	 */
	public function get_report($report_name) {
		$report_name = sanitize_title($report_name);

		// Load report override class
		$report_override_class = $this->report_override_class($report_name);

		$report_class = class_exists($report_override_class) ? $report_override_class : $this->report_original_class($report_name);

		Logger::log(sprintf(__('Attempting to render report "%s". Report class: "%s".', AELIA_CS_PLUGIN_TEXTDOMAIN),
												$report_name,
												$report_class),
								true);

		if(class_exists($report_class)) {
			$report = new $report_override_class();
			$report->output_report();
		}
		else {
			Logger::log(sprintf(__('Class "%s" not found, report rendering aborted.', AELIA_CS_PLUGIN_TEXTDOMAIN),
													$report_class),
									true);
		}
	}

	/**
	 * Renders the sales widget in the dashboard.
	 * This method is an almost exact clone of global woocommerce_dashboard_status()
	 * function, with the main difference being that the correct totals in base
	 * currency are taken before being aggregated. Due to the lack of filters in
	 * the original function, the whole code had to be duplicated.
	 */
	public function woocommerce_dashboard_status_widget() {
		global $wpdb;
		$wpdb->show_errors();

		include_once($this->woocommerce_admin_path() . '/reports/class-wc-admin-report.php');
		$reports = new \WC_Admin_Report();

		// Get sales
		$sales = $wpdb->get_var( "SELECT SUM( postmeta.meta_value ) FROM {$wpdb->posts} as posts
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
			LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id )
			LEFT JOIN {$wpdb->terms} AS term USING( term_id )
			LEFT JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
			WHERE 	posts.post_type 	= 'shop_order'
			AND 	posts.post_status 	= 'publish'
			AND 	tax.taxonomy		= 'shop_order_status'
			AND		term.slug			IN ( 'completed', 'processing', 'on-hold' )
			AND 	postmeta.meta_key   = '_order_total_base_currency'
			AND 	posts.post_date >= '" . date( 'Y-m-01', current_time( 'timestamp' ) ) . "'
			AND 	posts.post_date <= '" . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) . "'
		" );

		// Get top seller
		$top_seller = $wpdb->get_row( "SELECT SUM( order_item_meta.meta_value ) as qty, order_item_meta_2.meta_value as product_id
			FROM {$wpdb->posts} as posts
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
			LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id )
			LEFT JOIN {$wpdb->terms} AS term USING( term_id )
			LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items ON posts.ID = order_id
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id
			WHERE 	posts.post_type 	= 'shop_order'
			AND 	posts.post_status 	= 'publish'
			AND 	tax.taxonomy		= 'shop_order_status'
			AND		term.slug			IN ( 'completed', 'processing', 'on-hold' )
			AND 	order_item_meta.meta_key = '_qty'
			AND 	order_item_meta_2.meta_key = '_product_id'
			AND 	posts.post_date >= '" . date( 'Y-m-01', current_time( 'timestamp' ) ) . "'
			AND 	posts.post_date <= '" . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) . "'
			GROUP BY product_id
			ORDER BY qty DESC
			LIMIT   1
		" );

		// Counts
		$on_hold_count      = get_term_by( 'slug', 'on-hold', 'shop_order_status' )->count;
		$processing_count   = get_term_by( 'slug', 'processing', 'shop_order_status' )->count;

		// Get products using a query - this is too advanced for get_posts :(
		$stock   = absint( max( get_option( 'woocommerce_notify_low_stock_amount' ), 1 ) );
		$nostock = absint( max( get_option( 'woocommerce_notify_no_stock_amount' ), 0 ) );

		$query_from = "FROM {$wpdb->posts} as posts
			INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
			INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
			WHERE 1=1
				AND posts.post_type IN ('product', 'product_variation')
				AND posts.post_status = 'publish'
				AND (
					postmeta.meta_key = '_stock' AND CAST(postmeta.meta_value AS SIGNED) <= '{$stock}' AND CAST(postmeta.meta_value AS SIGNED) > '{$nostock}' AND postmeta.meta_value != ''
				)
				AND (
					( postmeta2.meta_key = '_manage_stock' AND postmeta2.meta_value = 'yes' ) OR ( posts.post_type = 'product_variation' )
				)
			";

		$lowinstock_count = absint( $wpdb->get_var( "SELECT COUNT( DISTINCT posts.ID ) {$query_from};" ) );

		$query_from = "FROM {$wpdb->posts} as posts
			INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
			INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
			WHERE 1=1
				AND posts.post_type IN ('product', 'product_variation')
				AND posts.post_status = 'publish'
				AND (
					postmeta.meta_key = '_stock' AND CAST(postmeta.meta_value AS SIGNED) <= '{$stock}' AND postmeta.meta_value != ''
				)
				AND (
					( postmeta2.meta_key = '_manage_stock' AND postmeta2.meta_value = 'yes' ) OR ( posts.post_type = 'product_variation' )
				)
			";

		$outofstock_count = absint( $wpdb->get_var( "SELECT COUNT( DISTINCT posts.ID ) {$query_from};" ) );
		?>
		<ul class="wc_status_list">
			<li class="sales-this-month">
				<a href="<?php echo admin_url( 'admin.php?page=wc-reports&tab=orders&range=month' ); ?>">
					<?php echo $reports->sales_sparkline( '', max( 7, date( 'd', current_time( 'timestamp' ) ) ) ); ?>
					<?php printf( __( "<strong>%s</strong> sales this month", 'woocommerce' ), wc_price( $sales ) ); ?>
				</a>
			</li>
			<?php if ( $top_seller && $top_seller->qty ) : ?>
				<li class="best-seller-this-month">
					<a href="<?php echo admin_url( 'admin.php?page=wc-reports&tab=orders&report=sales_by_product&range=month&product_ids=' . $top_seller->product_id ); ?>">
						<?php echo $reports->sales_sparkline( $top_seller->product_id, max( 7, date( 'd', current_time( 'timestamp' ) ) ), 'count' ); ?>
						<?php printf( __( "%s top seller this month (sold %d)", 'woocommerce' ), "<strong>" . get_the_title( $top_seller->product_id ) . "</strong>", $top_seller->qty ); ?>
					</a>
				</li>
			<?php endif; ?>
			<li class="processing-orders">
				<a href="<?php echo admin_url( 'edit.php?s&post_status=all&post_type=shop_order&shop_order_status=processing' ); ?>">
					<?php printf( _n( "<strong>%s order</strong> awaiting processing", "<strong>%s orders</strong> awaiting processing", $processing_count, 'woocommerce' ), $processing_count ); ?>
				</a>
			</li>
			<li class="on-hold-orders">
				<a href="<?php echo admin_url( 'edit.php?s&post_status=all&post_type=shop_order&shop_order_status=on-hold' ); ?>">
					<?php printf( _n( "<strong>%s order</strong> on-hold", "<strong>%s orders</strong> on-hold", $on_hold_count, 'woocommerce' ), $on_hold_count ); ?>
				</a>
			</li>
			<li class="low-in-stock">
				<a href="<?php echo admin_url( 'admin.php?page=wc-reports&tab=stock&report=low_in_stock' ); ?>">
					<?php printf( _n( "<strong>%s product</strong> low in stock", "<strong>%s products</strong> low in stock", $lowinstock_count, 'woocommerce' ), $lowinstock_count ); ?>
				</a>
			</li>
			<li class="out-of-stock">
				<a href="<?php echo admin_url( 'admin.php?page=wc-reports&tab=stock&report=out_of_stock' ); ?>">
					<?php printf( _n( "<strong>%s product</strong> out of stock", "<strong>%s products</strong> out of stock", $outofstock_count, 'woocommerce' ), $outofstock_count ); ?>
				</a>
			</li>
		</ul>
		<?php
	}

	/**
	 * Overrides the WooCommerce dashboard reports.
	 */
	public function override_dashboard_reports() {
		wp_add_dashboard_widget('woocommerce_dashboard_status',
														__('WooCommerce Status', 'woocommerce'),
														array($this, 'woocommerce_dashboard_status_widget'));
	}

	/**
	 * Sets the hooks required by the class.
	 */
	protected function set_hooks() {
		parent::set_hooks();

		// Override entire reports
		add_filter('woocommerce_admin_reports', array($this, 'woocommerce_admin_reports'), 50);
	}
}
