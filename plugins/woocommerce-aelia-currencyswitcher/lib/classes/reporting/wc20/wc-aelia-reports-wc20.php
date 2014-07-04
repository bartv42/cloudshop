<?php
namespace Aelia\CurrencySwitcher\WC20;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \WC_Aelia_CurrencySwitcher;
use \Aelia_Order;

/**
 * Overrides the reports for WooCommerce 2.0.
 */
class Reports extends \Aelia\CurrencySwitcher\Reports {
	/**
	 * Modifies Orders Overview query to convert order amounts into base currency.
	 *
	 * @param array order_totals The order totals normally used by WooCommerce.
	 * @return array
	 */
	public function woocommerce_reports_sales_overview_order_totals($order_totals) {
		global $wpdb;

		$this->set_reporting_flag(true);

		// If no data was retrieved by WooCommerce, just return the empty dataset
		if(empty($order_totals)) {
			return $order_totals;
		}

		$SQL = "
			SELECT
				SUM(meta_order.meta_value) AS total_sales,
				COUNT(posts.ID) AS total_orders
			FROM
				$wpdb->posts AS posts
			LEFT JOIN
				$wpdb->postmeta AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key = '_order_total_base_currency')
			LEFT JOIN
				$wpdb->term_relationships AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				$wpdb->term_taxonomy AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				$wpdb->terms AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(posts.post_status = 'publish') AND
				(taxonomy.taxonomy = 'shop_order_status') AND
				(term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "'))
		";
		$order_totals = $wpdb->get_row($SQL);

		$this->set_reporting_flag(false);

		return $order_totals;
	}

	/**
	 * Modifies Discount Total query to convert amounts into base currency.
	 *
	 * @param float discount_total The total discount calculated by WooCommerce.
	 * @return float Total discount converted into base currency.
	 */
	public function woocommerce_reports_sales_overview_discount_total($discount_total) {
		global $wpdb;

		$this->set_reporting_flag(true);

		// If discount is zero, just return it. Any conversion would also return zero
		if($discount_total === 0) {
			return $discount_total;
		}

		$SQL = "
			SELECT
				SUM(meta_order.meta_value) AS total_sales
			FROM
				$wpdb->posts AS posts
			JOIN
				$wpdb->postmeta AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key IN ('_order_discount_base_currency', '_cart_discount_base_currency'))
			LEFT JOIN
				$wpdb->term_relationships AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				$wpdb->term_taxonomy AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				$wpdb->terms AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(posts.post_status = 'publish') AND
				(taxonomy.taxonomy = 'shop_order_status') AND
				(term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "'))
		";

		$discount_total = $wpdb->get_var($SQL);

		$this->set_reporting_flag(false);

		return $discount_total;
	}


	/**
	 * Modifies Shipping Total query to convert amounts into base currency.
	 *
	 * @param float shipping_total The total shipping costs calculated by WooCommerce.
	 * @return float Total shipping costs converted into base currency.
	 */
	public function woocommerce_reports_sales_overview_shipping_total($shipping_total) {
		global $wpdb;

		$this->set_reporting_flag(true);

		// If shipping total is zero, just return it. Any conversion would also
		// return zero
		if($shipping_total === 0) {
			return $shipping_total;
		}

		$SQL = "
			SELECT
				SUM(meta_order.meta_value) AS total_sales
			FROM
				$wpdb->posts AS posts
			JOIN
				$wpdb->postmeta AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key = '_order_shipping_base_currency')
			LEFT JOIN
				$wpdb->term_relationships AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				$wpdb->term_taxonomy AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				$wpdb->terms AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(posts.post_status = 'publish') AND
				(taxonomy.taxonomy = 'shop_order_status') AND
				(term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "'))
		";

		$shipping_total = $wpdb->get_var($SQL);
		$this->set_reporting_flag(false);

		return $shipping_total;
	}

	/**
	 * Modifies Daily Orders query to convert order amounts into base currency.
	 *
	 * @param array orders The orders as retrieved by WooCommerce.
	 * @param string start_date The start date used to limit the date range.
	 * @param string end_date The end date used to limit the date range.
	 * @return array
	 */
	public function woocommerce_reports_daily_sales_orders($orders, $start_date, $end_date) {
		global $wpdb;

		$this->set_reporting_flag(true);

		// If no data was retrieved by WooCommerce, just return the empty dataset
		if(empty($orders)) {
			return $orders;
		}

		$SQL = "
			SELECT
				posts.ID
				,posts.post_date
				,meta_order.meta_value AS total_sales
			FROM
				$wpdb->posts AS posts
			LEFT JOIN
				$wpdb->postmeta AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key = '_order_total_base_currency')
			LEFT JOIN
				$wpdb->term_relationships AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				$wpdb->term_taxonomy AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				$wpdb->terms AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(posts.post_status = 'publish') AND
				(taxonomy.taxonomy = 'shop_order_status') AND
				(term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "')) AND
				(posts.post_date > '" . date('Y-m-d', $start_date) . "') AND
				(posts.post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date)) . "')
			ORDER BY
				posts.post_date ASC
			";

		$orders = $wpdb->get_results($SQL);
		$this->set_reporting_flag(false);

		return $orders;
	}

	/**
	 * Modifies Monthly Orders query to convert order amounts into base currency.
	 *
	 * @param array orders The orders as retrieved by WooCommerce.
	 * @param string month The year and month used to retrieve the data, in YYYYMM
	 * format.
	 * @return stdClass
	 */
	public function woocommerce_reports_monthly_sales_orders($orders, $month) {
		global $wpdb;

		$this->set_reporting_flag(true);

		// If no data was retrieved by WooCommerce, just return the empty dataset
		if(empty($orders)) {
			return $orders;
		}

		$start_date = $month . '01000000';
		$end_date = date('Ymd000000', strtotime($start_date . ' +1 month'));

		$SQL = "
			SELECT
				SUM(meta_order.meta_value) AS total_sales
				,COUNT(posts.ID) AS total_orders
			FROM
				$wpdb->posts AS posts
			LEFT JOIN
				$wpdb->postmeta AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key = '_order_total_base_currency')
			LEFT JOIN
				$wpdb->term_relationships AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				$wpdb->term_taxonomy AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				$wpdb->terms AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(posts.post_status = 'publish') AND
				(taxonomy.taxonomy = 'shop_order_status') AND
				(term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "')) AND
				(posts.post_date >= $start_date) AND
				(posts.post_date < $end_date)
		";

		$orders = $wpdb->get_row($SQL);
		$this->set_reporting_flag(false);

		return $orders;
	}

	/**
	 * Modifies Product Sales query to convert order amounts into base currency.
	 *
	 * @param array products_data The product sales data as retrieved by
	 * WooCommerce.
	 * @param array products The products for which to retrieve the sales
	 * information.
	 * @return array
	 */
	public function woocommerce_reports_product_sales_order_items($products_data, $products) {
		global $wpdb;

		$this->set_reporting_flag(true);

		// If no data was retrieved by WooCommerce, just return the empty dataset
		if(empty($products_data)) {
			return $products_data;
		}

		$SQL = "
			SELECT
				order_item_meta_2.meta_value as product_id
				,posts.post_date
				,SUM(order_item_meta.meta_value) AS item_quantity
				-- ,SUM(order_item_meta_3.meta_value) AS line_total
				-- Return Line total converted to base currency
				,SUM(order_item_meta_3.meta_value * meta_order_base_curr.meta_value / meta_order.meta_value) AS line_total
			FROM
				{$wpdb->prefix}woocommerce_order_items AS order_items
			JOIN
				{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON
					(order_item_meta.order_item_id = order_items.order_item_id) AND
					(order_item_meta.meta_key = '_qty')
			JOIN
				{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON
					(order_item_meta_2.order_item_id = order_items.order_item_id) AND
					(order_item_meta_2.meta_key = '_product_id')
			JOIN
				{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_3 ON
					(order_item_meta_3.order_item_id = order_items.order_item_id) AND
					(order_item_meta_3.meta_key = '_line_total')
			JOIN
				$wpdb->posts AS posts ON
					(posts.ID = order_items.order_id) AND
					(posts.post_type = 'shop_order') AND
					(posts.post_status = 'publish')
			JOIN
				$wpdb->postmeta AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key = '_order_total')
			JOIN
				$wpdb->postmeta AS meta_order_base_curr ON
					(meta_order_base_curr.post_id = posts.ID) AND
					(meta_order_base_curr.meta_key = '_order_total_base_currency')
			LEFT JOIN
				$wpdb->term_relationships AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				$wpdb->term_taxonomy AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id) AND
					(taxonomy.taxonomy = 'shop_order_status')
			LEFT JOIN
				$wpdb->terms AS term ON
					(term.term_id = taxonomy.term_id) AND
					(term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "'))
			WHERE
				(order_items.order_item_type = 'line_item') AND
				(order_item_meta_2.meta_value IN ('" . implode("','", $products) . "'))
			GROUP BY
				order_items.order_id
			ORDER BY
				posts.post_date ASC
		";

		$products_data_base_curr = $wpdb->get_results($SQL);
		$this->set_reporting_flag(false);

		return $products_data_base_curr;
	}

	/**
	 * Modifies Top Earners Sales query to convert order amounts into base currency.
	 *
	 * @param array products_data The product sales data as retrieved by
	 * WooCommerce.
	 * @param string start_date The start date used to limit the date range.
	 * @param string end_date The end date used to limit the date range.
	 * @return array
	 */
	public function woocommerce_reports_top_earners_order_items($products_data, $start_date, $end_date) {
		global $wpdb;

		$this->set_reporting_flag(true);

		// If no data was retrieved by WooCommerce, just return the empty dataset
		if(empty($products_data)) {
			return $products_data;
		}

		$SQL = "
			SELECT
				order_item_meta_2.meta_value as product_id
				-- ,SUM(order_item_meta_3.meta_value) AS line_total
				-- Return Line total converted to base currency
				,SUM(order_item_meta_3.meta_value * meta_order_base_curr.meta_value / meta_order.meta_value) AS line_total
			FROM
				{$wpdb->prefix}woocommerce_order_items AS order_items
			JOIN
				{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON
					(order_item_meta_2.order_item_id = order_items.order_item_id) AND
					(order_item_meta_2.meta_key = '_product_id')
			JOIN
				{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_3 ON
					(order_item_meta_3.order_item_id = order_items.order_item_id) AND
					(order_item_meta_3.meta_key = '_line_total')
			JOIN
				$wpdb->posts AS posts ON
					(posts.ID = order_items.order_id) AND
					(posts.post_type = 'shop_order') AND
					(posts.post_status = 'publish') AND
					(post_date > '" . date('Y-m-d', $start_date) . "') AND
					(post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date)) . "')
			JOIN
				$wpdb->postmeta AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key = '_order_total')
			JOIN
				$wpdb->postmeta AS meta_order_base_curr ON
					(meta_order_base_curr.post_id = posts.ID) AND
					(meta_order_base_curr.meta_key = '_order_total_base_currency')
			LEFT JOIN
				$wpdb->term_relationships AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				$wpdb->term_taxonomy AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id) AND
					(taxonomy.taxonomy = 'shop_order_status')
			LEFT JOIN
				$wpdb->terms AS term ON
					(term.term_id = taxonomy.term_id) AND
					(term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "'))
			WHERE
				(order_items.order_item_type = 'line_item')
			GROUP BY
				order_item_meta_2.meta_value
		";

		$products_data_base_curr = $wpdb->get_results($SQL);
		$this->set_reporting_flag(false);

		return $products_data_base_curr;
	}

	/**
	 * Modifies Orders by Category query to convert order amounts into base
	 * currency.
	 *
	 * @param array orders The orders as retrieved by WooCommerce.
	 * @return array
	 */
	public function woocommerce_reports_category_sales_order_items($orders) {
		global $wpdb;
		$this->set_reporting_flag(true);

		// If no data was retrieved by WooCommerce, just return the empty dataset
		if(empty($orders)) {
			return $orders;
		}

		// This report requires one year to retrieve the data, but such information
		// is not passed by the apply_filter() call. It's therefore necessary to
		// extract it from the data that was retrieved by WooCommerce, using the
		// first date found in the dataset
		$order = $orders[0];
		$reference_date = DateTime::createFromFormat('Y-m-d H:i:s', get_value('post_date', $orders[0]));
		$year_to_retrieve = $reference_date->format('Y');

		// Calculate the first date of year passed as a parameter and the start date
		// of next one
		$start_date = $year_to_retrieve . '0101000000';
		$end_date = date('Ymd000000', strtotime($start_date . ' +1 year'));

		$SQL = "
			SELECT
				order_item_meta_2.meta_value as product_id
				,posts.post_date
				-- ,SUM(order_item_meta_3.meta_value) AS line_total
				-- Return Line total converted to base currency
				,SUM(order_item_meta_3.meta_value * meta_order_base_curr.meta_value / meta_order.meta_value) AS line_total
			FROM
				{$wpdb->prefix}woocommerce_order_items AS order_items
			JOIN
				{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON
					(order_item_meta_2.order_item_id = order_items.order_item_id) AND
					(order_item_meta_2.meta_key = '_product_id')
			JOIN
				{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_3 ON
					(order_item_meta_3.order_item_id = order_items.order_item_id) AND
					(order_item_meta_3.meta_key = '_line_total')
			JOIN
				$wpdb->posts AS posts ON
					(posts.ID = order_items.order_id) AND
					(posts.post_type = 'shop_order') AND
					(posts.post_status = 'publish') AND
					(posts.post_date >= $start_date) AND
					(posts.post_date < $end_date)
			JOIN
				$wpdb->postmeta AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key = '_order_total')
			JOIN
				$wpdb->postmeta AS meta_order_base_curr ON
					(meta_order_base_curr.post_id = posts.ID) AND
					(meta_order_base_curr.meta_key = '_order_total_base_currency')
			LEFT JOIN
				$wpdb->term_relationships AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				$wpdb->term_taxonomy AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id) AND
					(taxonomy.taxonomy = 'shop_order_status')
			LEFT JOIN
				$wpdb->terms AS term ON
					(term.term_id = taxonomy.term_id) AND
					(term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "'))
			WHERE
				(order_items.order_item_type = 'line_item')
			GROUP BY
				order_items.order_item_id
			ORDER BY
				posts.post_date ASC
		";

		$orders_base_curr = $wpdb->get_results($SQL);
		$this->set_reporting_flag(false);

		return $orders_base_curr;
	}

	/**
	 * Overrides standard WooCommerce woocommerce_monthly_taxes() function. Such
	 * function doesn't implement hooks that can be intercepted by external classes,
	 * therefore a complete override is the only way to modify its behaviour.
	 */
	public function woocommerce_monthly_taxes() {
		// TODO Implement Monthly Taxes report
		global $start_date, $end_date, $woocommerce, $wpdb;
		$this->set_reporting_flag(true);

		$first_year = $wpdb->get_var("SELECT post_date FROM $wpdb->posts WHERE post_date != 0 ORDER BY post_date ASC LIMIT 1;");

		if($first_year) {
			$first_year = date('Y', strtotime($first_year));
		}
		else {
			$first_year = date('Y');
		}

		$current_year = isset($_POST['show_year']) 	? $_POST['show_year'] 	: date('Y', current_time('timestamp'));
		$start_date = strtotime($current_year . '0101');

		$total_tax = $total_sales_tax = $total_shipping_tax = $count = 0;
		$taxes = $tax_rows = $tax_row_labels = array();

		for($count = 0; $count < 12; $count++) {
			$time = strtotime(date('Ym', strtotime('+ ' . $count . ' MONTH', $start_date)) . '01');

			if($time > current_time('timestamp')) {
				continue;
			}

			$month = date('Ym', strtotime(date('Ym', strtotime('+ ' . $count . ' MONTH', $start_date)) . '01'));

			$gross = $wpdb->get_var($wpdb->prepare("
				SELECT SUM(meta.meta_value) AS order_tax
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
				LEFT JOIN {$wpdb->term_taxonomy} AS tax USING(term_taxonomy_id)
				LEFT JOIN {$wpdb->terms} AS term USING(term_id)
				WHERE 	meta.meta_key 		= '_order_total_base_currency'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'publish'
				AND 	tax.taxonomy		= 'shop_order_status'
				AND		term.slug			IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "')
				AND		%s					= date_format(posts.post_date,'%%Y%%m')
			", $month));

			$shipping = $wpdb->get_var($wpdb->prepare("
				SELECT SUM(meta.meta_value) AS order_tax
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
				LEFT JOIN {$wpdb->term_taxonomy} AS tax USING(term_taxonomy_id)
				LEFT JOIN {$wpdb->terms} AS term USING(term_id)
				WHERE 	meta.meta_key 		= '_order_shipping_base_currency'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'publish'
				AND 	tax.taxonomy		= 'shop_order_status'
				AND		term.slug			IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "')
				AND		%s		 			= date_format(posts.post_date,'%%Y%%m')
			", $month));

			$order_tax = $wpdb->get_var($wpdb->prepare("
				SELECT SUM(meta.meta_value) AS order_tax
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
				LEFT JOIN {$wpdb->term_taxonomy} AS tax USING(term_taxonomy_id)
				LEFT JOIN {$wpdb->terms} AS term USING(term_id)
				WHERE 	meta.meta_key 		= '_order_tax_base_currency'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'publish'
				AND 	tax.taxonomy		= 'shop_order_status'
				AND		term.slug			IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "')
				AND		%s		 			= date_format(posts.post_date,'%%Y%%m')
			", $month));

			$shipping_tax = $wpdb->get_var($wpdb->prepare("
				SELECT SUM(meta.meta_value) AS order_tax
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
				LEFT JOIN {$wpdb->term_taxonomy} AS tax USING(term_taxonomy_id)
				LEFT JOIN {$wpdb->terms} AS term USING(term_id)
				WHERE 	meta.meta_key 		= '_order_shipping_tax_base_currency'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'publish'
				AND 	tax.taxonomy		= 'shop_order_status'
				AND		term.slug			IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "')
				AND		%s		 			= date_format(posts.post_date,'%%Y%%m')
			", $month));

			$tax_rows = $wpdb->get_results($wpdb->prepare("
				SELECT
					order_items.order_item_name as name,
					SUM(order_item_meta.meta_value) as tax_amount,
					SUM(order_item_meta_2.meta_value) as shipping_tax_amount,
					SUM(order_item_meta.meta_value + order_item_meta_2.meta_value) as total_tax_amount

				FROM 		{$wpdb->prefix}woocommerce_order_items as order_items

				LEFT JOIN 	{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
				LEFT JOIN 	{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id

				LEFT JOIN 	{$wpdb->posts} AS posts ON order_items.order_id = posts.ID
				LEFT JOIN 	{$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
				LEFT JOIN 	{$wpdb->term_taxonomy} AS tax USING(term_taxonomy_id)
				LEFT JOIN 	{$wpdb->terms} AS term USING(term_id)

				WHERE 		order_items.order_item_type = 'tax'
				AND 		posts.post_type 	= 'shop_order'
				AND 		posts.post_status 	= 'publish'
				AND 		tax.taxonomy		= 'shop_order_status'
				AND			term.slug IN ('" . implode("','", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))) . "')
				AND			%s = date_format(posts.post_date,'%%Y%%m')
				AND 		order_item_meta.meta_key = 'tax_amount'
				AND 		order_item_meta_2.meta_key = 'shipping_tax_amount'

				GROUP BY 	order_items.order_item_name
			", $month));

			if($tax_rows) {
				foreach ($tax_rows as $tax_row) {
					if ($tax_row->total_tax_amount > 0)
						$tax_row_labels[] = $tax_row->name;
				}
			}

			$taxes[date('M', strtotime($month . '01'))] = array(
				'gross' => $gross,
				'shipping' => $shipping,
				'order_tax' => $order_tax,
				'shipping_tax' => $shipping_tax,
				'total_tax' => $shipping_tax + $order_tax,
				'tax_rows' => $tax_rows
			);

			$total_sales_tax += $order_tax;
			$total_shipping_tax += $shipping_tax;
		}
		$total_tax = $total_sales_tax + $total_shipping_tax;

		include($this->get_view('woocommerce_monthly_taxes.php'));
		$this->set_reporting_flag(false);
	}

	public function woocommerce_reports_coupons_overview_totals($orders) {
		// TODO Implement Coupons Overview recalculation
	}

	public function woocommerce_reports_coupons_overview_coupons_by_count($coupons) {
		// TODO Implement Coupons Value recalculation
	}

	public function woocommerce_reports_coupons_sales_used_coupons($coupons) {
		// TODO Implement Sales with Coupons recalculation
	}

	public function woocommerce_reports_customer_overview_customer_orders($orders) {
		// TODO Implement Customers Orders Overview recalculation
	}

	public function woocommerce_reports_customer_overview_guest_orders($orders) {
		// TODO Implement Guest Orders Overview recalculation
	}

	/**
	 * Overrides entire reports. This method allows to replace entire report
	 * functions, and can be used when the target functions don't implement
	 * hooks that can be intercepted to alter their behaviour.
	 *
	 * @param array charts An array of reporting functions.
	 * @return array
	 */
	public function woocommerce_reports_charts($charts) {
		$charts['sales']['charts']['taxes_by_month']['function'] = array($this, 'woocommerce_monthly_taxes');

		return $charts;
	}

	/**
	 * Renders the sales widget in the dashboard.
	 * This method is an almost exact clone of global woocommerce_dashboard_sales_js()
	 * function, with the main difference being that the correct totals in base
	 * currency are taken before being aggregated. Due to the lack of filters in
	 * the original function, the whole code had to be duplicated.
	 */
	public function woocommerce_dashboard_sales() {
		$screen = get_current_screen();
		if (!$screen || $screen->id!=='dashboard') return;

		global $woocommerce, $wp_locale;
		global $current_month_offset, $the_month_num, $the_year;
		$this->set_reporting_flag(true);

		// Get orders to display in widget
		add_filter('posts_where', 'orders_this_month');

		$args = array(
				'numberposts'     => -1,
				'orderby'         => 'post_date',
				'order'           => 'DESC',
				'post_type'       => 'shop_order',
				'post_status'     => 'publish' ,
				'suppress_filters' => false,
				'tax_query' => array(
					array(
						'taxonomy' => 'shop_order_status',
					'terms' => apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold')),
					'field' => 'slug',
					'operator' => 'IN'
				)
				)
		);
		$orders = get_posts($args);

		$order_counts = array();
		$order_amounts = array();

		// Blank date ranges to begin
		$month = $the_month_num;
		$year = (int) $the_year;

		$first_day = strtotime("{$year}-{$month}-01");
		$last_day = strtotime('-1 second', strtotime('+1 month', $first_day));

		if ((date('m') - $the_month_num)==0) :
			$up_to = date('d', strtotime('NOW'));
		else :
			$up_to = date('d', $last_day);
		endif;
		$count = 0;

		while ($count < $up_to) :
			$time = strtotime(date('Ymd', strtotime('+ '.$count.' DAY', $first_day))).'000';

			$order_counts[$time] = 0;
			$order_amounts[$time] = 0;

			$count++;
		endwhile;

		if ($orders) :
			foreach ($orders as $order) :
				$order_data = new Aelia_Order($order->ID);

				if ($order_data->status=='cancelled' || $order_data->status=='refunded') continue;

				$time = strtotime(date('Ymd', strtotime($order->post_date))).'000';

				if (isset($order_counts[$time])) :
					$order_counts[$time]++;
				else :
					$order_counts[$time] = 1;
				endif;

				if (isset($order_amounts[$time])) :
					$order_amounts[$time] = $order_amounts[$time] + $order_data->order_total_base_currency;
				else :
					$order_amounts[$time] = (float) $order_data->order_total_base_currency;
				endif;

			endforeach;
		endif;

		remove_filter('posts_where', 'orders_this_month');

		/* Script variables */
		$params = array(
			'currency_symbol' 	=> get_woocommerce_currency_symbol(),
			'number_of_sales' 	=> absint(array_sum($order_counts)),
			'sales_amount'    	=> woocommerce_price(array_sum($order_amounts)),
			'sold' 				=> __('Sold', 'woocommerce'),
			'earned'    		=> __('Earned', 'woocommerce'),
			'month_names'     	=> array_values($wp_locale->month_abbrev),
		);

		$order_counts_array = array();
		foreach ($order_counts as $key => $count) :
			$order_counts_array[] = array($key, $count);
		endforeach;

		$order_amounts_array = array();
		foreach ($order_amounts as $key => $amount) :
			$order_amounts_array[] = array($key, $amount);
		endforeach;

		$order_data = array('order_counts' => $order_counts_array, 'order_amounts' => $order_amounts_array);

		$params['order_data'] = json_encode($order_data);

		// Queue scripts
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script('woocommerce_dashboard_sales', $woocommerce->plugin_url() . '/assets/js/admin/dashboard_sales' . $suffix . '.js', array('jquery', 'flot', 'flot-resize'), '1.0');
		wp_register_script('flot', $woocommerce->plugin_url() . '/assets/js/admin/jquery.flot'.$suffix.'.js', 'jquery', '1.0');
		wp_register_script('flot-resize', $woocommerce->plugin_url() . '/assets/js/admin/jquery.flot.resize'.$suffix.'.js', 'jquery', '1.0');

		wp_localize_script('woocommerce_dashboard_sales', 'params', $params);

		wp_print_scripts('woocommerce_dashboard_sales');
		$this->set_reporting_flag(false);
	}

	/**
	 * Renders the recent orders widget in the dashboard.
	 * This method is an almost exact clone of global woocommerce_dashboard_recent_orders()
	 * function, with the main difference being that the correct totals in base
	 * currency are taken before being aggregated. Due to the lack of filters in
	 * the original function, the whole code had to be duplicated.
	 */
	public function woocommerce_dashboard_recent_orders() {
		$this->set_reporting_flag(true);

		$base_currency = WC_Aelia_CurrencySwitcher::settings()->base_currency();

		$args = array(
				'numberposts'     => 8,
				'orderby'         => 'post_date',
				'order'           => 'DESC',
				'post_type'       => 'shop_order',
				'post_status'     => 'publish'
		);
		$orders = get_posts($args);
		if ($orders) :
			echo '<ul class="recent-orders">';
			foreach ($orders as $order) :
				$this_order = new Aelia_Order($order->ID);

				$order_currency = $this_order->get_order_currency();
				$order_total = WC_Aelia_CurrencySwitcher::instance()->format_price((float)$this_order->order_total,
																																					 $order_currency);
				if($order_currency != $base_currency) {
					$order_total_base_currency = woocommerce_price($this_order->order_total_base_currency);
					$formatted_order_total_base_currency = sprintf('(%s)', $order_total_base_currency);
				}
				else {
					// No need to display the total in base currency separately if the order
					// was placed in base currency
					$formatted_order_total_base_currency = '';
				}


				echo '<li>';
				// Order status and timestamp
				echo '<span class="order-status '.sanitize_title($this_order->status).'">'.ucwords(__($this_order->status, 'woocommerce')).'</span> <a href="'.admin_url('post.php?post='.$order->ID).'&action=edit">' . get_the_time(__('l jS \of F Y h:i:s A', 'woocommerce'), $order->ID) . '</a><br />';
				echo '<small>';
				echo sizeof($this_order->get_items()).' '._n('item', 'items', sizeof($this_order->get_items()), 'woocommerce');
				// Order total
				echo ' <span class="order-cost">';
				echo __('Total:', 'woocommerce') . ' ' . $order_total . ' ' . $formatted_order_total_base_currency;
				echo '</span></small>';
				echo '</li>';

			endforeach;
			echo '</ul>';
		else:
			echo '<p>' . __('There are no product orders yet.', 'woocommerce') . '</p>';
		endif;

		$this->set_reporting_flag(false);
	}

	/**
	 * Overrides the WooCommerce dashboard reports.
	 */
	public function override_dashboard_reports() {
		if(current_user_can('view_woocommerce_reports') ||
			 current_user_can('publish_shop_orders')) {
			remove_action('admin_footer', 'woocommerce_dashboard_sales_js');
			add_action('admin_footer', array($this, 'woocommerce_dashboard_sales'));
		}

		if(current_user_can('publish_shop_orders')) {
			remove_meta_box('woocommerce_dashboard_recent_orders', 'dashboard', 'normal');
			wp_add_dashboard_widget('aelia_cs_woocommerce_dashboard_recent_orders',
															__('WooCommerce Recent Orders', 'woocommerce'),
															array($this, 'woocommerce_dashboard_recent_orders'));
		}
	}

	/**
	 * Sets the hooks required by the class.
	 */
	protected function set_hooks() {
		parent::set_hooks();

		// Sales Overview
		add_filter('woocommerce_reports_sales_overview_order_totals', array($this, 'woocommerce_reports_sales_overview_order_totals'), 50);
		add_filter('woocommerce_reports_sales_overview_discount_total', array($this, 'woocommerce_reports_sales_overview_discount_total'), 50);
		add_filter('woocommerce_reports_sales_overview_shipping_total', array($this, 'woocommerce_reports_sales_overview_shipping_total'), 50);

		// Daily Sales
		add_filter('woocommerce_reports_daily_sales_orders', array($this, 'woocommerce_reports_daily_sales_orders'), 50, 3);
		// Monthly Sales
		add_filter('woocommerce_reports_monthly_sales_orders', array($this, 'woocommerce_reports_monthly_sales_orders'), 50, 2);

		// Sales per Product
		add_filter('woocommerce_reports_product_sales_order_items', array($this, 'woocommerce_reports_product_sales_order_items'), 50, 2);
		// Top Earners
		add_filter('woocommerce_reports_top_earners_order_items', array($this, 'woocommerce_reports_top_earners_order_items'), 50, 3);
		// Sales by Category
		add_filter('woocommerce_reports_category_sales_order_items', array($this, 'woocommerce_reports_category_sales_order_items'), 50, 2);

		// Override entire reports
		add_filter('woocommerce_reports_charts', array($this, 'woocommerce_reports_charts'), 50);

		// TODO Override Monthly taxes report completely. Such function doesn't implement hooks that can be intercepted to recalculate the values.
		// Intercept hook "woocommerce_reports_charts" and set the following:
		//if (get_option('woocommerce_calc_taxes') == 'yes') {
		//	$charts['sales']['charts']["taxes_by_month"] = array(
		//		'title'       => __('Taxes by month', 'woocommerce'),
		//		'description' => '',
		//		'function'    => <put new function here>
		//	);
		//}

		// Coupons Overview
		add_filter('woocommerce_reports_coupons_overview_totals', array($this, 'woocommerce_reports_coupons_overview_totals'), 50);
		// Coupons by Count and Amount
		add_filter('woocommerce_reports_coupons_overview_coupons_by_count', array($this, 'woocommerce_reports_coupons_overview_coupons_by_count'), 50);
		// Sales in which Coupons were used
		add_filter('woocommerce_reports_coupons_sales_used_coupons', array($this, 'woocommerce_reports_coupons_sales_used_coupons'), 50);

		// Customer Orders
		add_filter('woocommerce_reports_customer_overview_customer_orders', array($this, 'woocommerce_reports_customer_overview_customer_orders'), 50);
		// Guest Orders
		add_filter('woocommerce_reports_customer_overview_guest_orders', array($this, 'woocommerce_reports_customer_overview_guest_orders'), 50);
	}
}
