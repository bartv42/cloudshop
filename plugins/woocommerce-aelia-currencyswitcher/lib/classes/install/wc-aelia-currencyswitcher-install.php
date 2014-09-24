<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \Aelia\CurrencySwitcher\Logger as Logger;
use \Aelia\CurrencySwitcher\Semaphore as Semaphore;

/**
 * Helper class to handle installation and update of Currency Switcher plugin.
 */
class WC_Aelia_CurrencySwitcher_Install extends WC_Aelia_Install {
	// @var WC_Aelia_CurrencySwitcher_Settings Settings controller instance.
	protected $settings;
	// @var Aelia\CurrencySwitcher\Semaphore The semaphore used to prevent race conditions.
	protected $semaphore;

	/**
	 * Returns current instance of the Currency Switcher.
	 *
	 * @return WC_Aelia_CurrencySwitcher
	 */
	protected function currency_switcher() {
		return WC_Aelia_CurrencySwitcher::instance();
	}

	public function __construct() {
		parent::__construct();

		$this->settings = WC_Aelia_CurrencySwitcher::settings();
	}

	/**
	 * Determines if WordPress maintenance mode is active.
	 *
	 * @return bool
	 */
	protected function maintenance_mode() {
		return file_exists(ABSPATH . '.maintenance') || defined('WP_INSTALLING');
	}

	/**
	 * Overrides standard update method to ensure that requirements for update are
	 * in place.
	 *
	 * @param string plugin_id The ID of the plugin.
	 * @param string new_version The new version of the plugin, which will be
	 * stored after a successful update to keep track of the status.
	 * @return bool
	 */
	public function update($plugin_id, $new_version) {
		// Don't run updates while maintenance mode is active
		if($this->maintenance_mode()) {
			return true;
		}

		$current_version = get_option($plugin_id);
		if(version_compare($current_version, $new_version, '>=')) {
			return true;
		}

		// We need the plugin to be configured before the updates can be applied. If
		// that is not the case, simply return true. The update will be called again
		// at next page load, until it will finally find settings and apply the
		// required changes
		$current_settings = $this->settings->current_settings();
		if(empty($current_settings)) {
			Logger::log(__('No settings found. This means that the plugin has just '.
										 'been installed. Update will run as soon as the settings ' .
										 'are saved.', AELIA_CS_PLUGIN_TEXTDOMAIN));
			return true;
		}

		$this->semaphore = new Semaphore('Aelia_CurrencySwitcher');
		$this->semaphore->initialize();
		if(!$this->semaphore->lock()) {
			Logger::log(__('Plugin Autoupdate - Could not obtain semaphore lock. This may mean that '.
										 'the process has already started, or that the lock is ' .
										 'stuck. Update process will run again later.', AELIA_CS_PLUGIN_TEXTDOMAIN));
			// Return true as the process already running is considered ok
			return true;
		}

		// Set time limit to 10 minutes (updates can take some time)
		set_time_limit(10 * 60);

		Logger::log(__('Running plugin autoupdate...', AELIA_CS_PLUGIN_TEXTDOMAIN));
		$result = parent::update($plugin_id, $new_version);

		Logger::log(sprintf(__('Autoupdate complete. Result: %s.', AELIA_CS_PLUGIN_TEXTDOMAIN),
												$result));

		// Unlock the semaphore, to allow update to run again later
		$this->semaphore->unlock();

		return $result;
	}

	/**
	 * Converts an amount from a Currency to another.
	 *
	 * @param float amount The amount to convert.
	 * @param string from_currency The source Currency.
	 * @param string to_currency The destination Currency.
	 * @separam int order The order from which the value was taken. Used mainly
	 * for logging purposes.
	 * @return float The amount converted in the destination currency.
	 */
	public function convert($amount, $from_currency, $to_currency, $order) {
		// If the exchange rate for either the order currency or the base currency
		// cannot be retrieved, it probably means that the plugin has just been
		// installed, or that it hasn't been configured correctly. In such case,
		// returning false will tag the update as "unsuccessful", and it will run
		// again at next page load
		$exchange_rate_msg_details = __('This usually occurs when the Currency Switcher ' .
																		'plugin has not yet been configured and exchange ' .
																		'rates have not been specified. <strong>Please refer to ' .
																		'our knowledge base to learn how to fix it</strong>: ' .
																		'<a href="https://aelia.freshdesk.com/solution/articles/3000017311-i-get-a-warning-saying-that-exchange-rate-could-not-be-retrieved-">I get a warning saying that "Exchange rate could not be retrieved" </a>.',
																		AELIA_CS_PLUGIN_TEXTDOMAIN);

		if($this->settings->get_exchange_rate($from_currency) == false) {
			$this->add_message(E_USER_WARNING,
												 sprintf(__('Exchange rate for Order Currency "%s" (Order ID: %s) ' .
																		'could not be retrieved.', AELIA_CS_PLUGIN_TEXTDOMAIN) . ' ' .
																 $exchange_rate_msg_details,
																 $order->currency,
																 $order->order_id));
			return false;
		}

		if($this->settings->get_exchange_rate($to_currency) == false) {
			$this->add_message(E_USER_WARNING,
												 sprintf(__('Exchange rate for Base Currency "%s" ' .
																		'could not be retrieved.', AELIA_CS_PLUGIN_TEXTDOMAIN) . ' ' .
																 $exchange_rate_msg_details,
																 $this->settings->base_currency()));

			return false;
		}

		return $this->currency_switcher()->convert($amount, $from_currency, $to_currency, null, false);
	}

	/**
	 * Calculate order totals and taxes in base currency for Orders that have been
	 * generated before version 1.9.4.130713.
	 *
	 * @return bool
	 */
	protected function update_to_1_9_4_130713() {
		// Retrieve all orders for which the totals in base currency have not been
		// saved
		$SQL = "
			SELECT
				posts.ID AS order_id
				,meta_order.meta_key
				,meta_order.meta_value
				,meta_order_base_currency.meta_key AS meta_key_base_currency
				,meta_order_base_currency.meta_value AS meta_value_base_currency
				,meta_order_currency.meta_value AS currency
			FROM
				{$this->wpdb->posts} AS posts
			JOIN
				{$this->wpdb->postmeta} AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key IN ('_order_total', '_order_discount', '_cart_discount', '_order_shipping', '_order_tax', '_order_shipping_tax'))
			LEFT JOIN
				{$this->wpdb->postmeta} AS meta_order_base_currency ON
					(meta_order_base_currency.post_id = posts.ID) AND
					(meta_order_base_currency.meta_key = CONCAT(meta_order.meta_key, '_base_currency')) AND
					(meta_order_base_currency.meta_value > 0)
			LEFT JOIN
				{$this->wpdb->postmeta} AS meta_order_currency ON
					(meta_order_currency.post_id = posts.ID) AND
					(meta_order_currency.meta_key = '_order_currency')
			LEFT JOIN
				{$this->wpdb->term_relationships} AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				{$this->wpdb->term_taxonomy} AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				{$this->wpdb->terms} AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(meta_order_base_currency.meta_key IS NULL)
		";

		$orders_to_update = $this->select($SQL);
		// Debug
		//var_dump($orders_to_update); die();

		foreach($orders_to_update as $order) {
			// If order currency is empty, for whatever reason, no conversio can be
			// performed (it's not possible to assume that a specific currency was
			// used)
			if(empty($order->currency)) {
				continue;
			}

			// If the exchange rate for either the order currency or the base currency
			// cannot be retrieved, it probably means that the plugin has just been
			// installed, or that it hasn't been configured correctly. In such case,
			// returning false will tag the update as "unsuccessful", and it will run
			// again at next page load
			$exchange_rate_msg_details = __('This usually occurs when the Currency Switcher ' .
																			'plugin has not yet been configured and exchange ' .
																			'rates have not been specified. <strong>Please refer to ' .
																			'our knowledge base to learn how to fix it</strong>: ' .
																			'<a href="https://aelia.freshdesk.com/solution/articles/3000017311-i-get-a-warning-saying-that-exchange-rate-could-not-be-retrieved-">I get a warning saying that "Exchange rate could not be retrieved" </a>.',
																			AELIA_CS_PLUGIN_TEXTDOMAIN);

			if($this->settings->get_exchange_rate($order->currency) == false) {
				$this->add_message(E_USER_WARNING,
													 sprintf(__('Exchange rate for Order Currency "%s" (Order ID: %s) ' .
																			'could not be retrieved.', AELIA_CS_PLUGIN_TEXTDOMAIN) . ' ' .
																	 $exchange_rate_msg_details,
																	 $order->currency,
																	 $order->order_id));
				return false;
			}

			if($this->settings->get_exchange_rate($this->settings->base_currency()) == false) {
				$this->add_message(E_USER_WARNING,
													 sprintf(__('Exchange rate for Base Currency "%s" ' .
																			'could not be retrieved.', AELIA_CS_PLUGIN_TEXTDOMAIN) . ' ' .
																	 $exchange_rate_msg_details,
																	 $this->settings->base_currency()));

				return false;
			}

			try {
				$value_in_base_currency = $this->currency_switcher()->convert($order->meta_value,
																																			$order->currency,
																																			$this->settings->base_currency(),
																																			null,
																																			false);
				$value_in_base_currency = WC_Aelia_CurrencySwitcher::instance()->float_to_string($value_in_base_currency);

				update_post_meta($order->order_id,
												 $order->meta_key . '_base_currency',
												 $value_in_base_currency);
			}
			catch(Exception $e) {
				$this->add_message(E_USER_ERROR,
													 sprintf(__('Exception occurred updating base currency values for order %s. ' .
																			'Error: %s.'),
																	 $order->order_id,
																	 $e->getMessage()));
				return false;
			}
		}
		return true;
	}

	/**
	 * Calculate order totals and taxes in base currency for Orders that have been
	 * generated before version 3.2.1.140215.
	 *
	 * @return bool
	 */
	protected function update_to_3_2_1_140215() {
		/**
		 * Parses a list of orders and returns an associative list of
		 * order id => exchange rate, where the exchange rate is the one used when
		 * the order was placed.
		 *
		 * @param array orders An array of order objects.
		 * @return array
		 */
		function get_orders_exchange_rates(array $orders) {
			$result = array();

			foreach ($orders as $order) {
				$result[$order->order_id] = $order->exchange_rate;
			}

			return $result;
		}

		$base_currency = $this->settings->base_currency();

		// Retrieve the exchange rates for the orders whose data already got
		// partially converted
		$SQL = "
			SELECT
				posts.ID AS order_id
				,meta_order.meta_key
				,meta_order.meta_value
				-- ,meta_order_base_currency.meta_key AS meta_key_base_currency
				,meta_order_base_currency.meta_value AS meta_value_base_currency
				-- ,meta_order_currency.meta_value AS currency
				,meta_order_base_currency.meta_value / meta_order.meta_value as exchange_rate
			FROM
				{$this->wpdb->posts} AS posts
			JOIN
				{$this->wpdb->postmeta} AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key IN ('_order_total', '_order_discount', '_cart_discount', '_order_shipping', '_order_tax', '_order_shipping_tax'))
			LEFT JOIN
				{$this->wpdb->postmeta} AS meta_order_base_currency ON
					(meta_order_base_currency.post_id = posts.ID) AND
					(meta_order_base_currency.meta_key = CONCAT(meta_order.meta_key, '_base_currency')) AND
					(meta_order_base_currency.meta_value > 0)
			LEFT JOIN
				{$this->wpdb->postmeta} AS meta_order_currency ON
					(meta_order_currency.post_id = posts.ID) AND
					(meta_order_currency.meta_key = '_order_currency')
			LEFT JOIN
				{$this->wpdb->term_relationships} AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				{$this->wpdb->term_taxonomy} AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				{$this->wpdb->terms} AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(meta_order.meta_key = '_order_total') AND
				(meta_order.meta_value IS NOT NULL) AND
				(meta_order_base_currency.meta_value IS NOT NULL)
		";

		$orders = $this->select($SQL);
		$orders_exchange_rates = get_orders_exchange_rates($orders);

		// Retrieve all orders for which the totals in base currency have not been
		// saved
		$SQL = "
			SELECT
				posts.ID AS order_id
				,meta_order.meta_key
				,meta_order.meta_value
				,meta_order_base_currency.meta_key AS meta_key_base_currency
				,meta_order_base_currency.meta_value AS meta_value_base_currency
				,meta_order_currency.meta_value AS currency
			FROM
				{$this->wpdb->posts} AS posts
			JOIN
				{$this->wpdb->postmeta} AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key IN ('_order_total', '_order_discount', '_cart_discount', '_order_shipping', '_order_tax', '_order_shipping_tax'))
			LEFT JOIN
				{$this->wpdb->postmeta} AS meta_order_base_currency ON
					(meta_order_base_currency.post_id = posts.ID) AND
					(meta_order_base_currency.meta_key = CONCAT(meta_order.meta_key, '_base_currency')) AND
					(meta_order_base_currency.meta_value >= 0)
			LEFT JOIN
				{$this->wpdb->postmeta} AS meta_order_currency ON
					(meta_order_currency.post_id = posts.ID) AND
					(meta_order_currency.meta_key = '_order_currency')
			LEFT JOIN
				{$this->wpdb->term_relationships} AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				{$this->wpdb->term_taxonomy} AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				{$this->wpdb->terms} AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(meta_order_base_currency.meta_key IS NULL)
		";

		$orders_to_update = $this->select($SQL);
		// Debug
		//var_dump($orders_to_update); die();

		foreach($orders_to_update as $order) {
			// If order currency is empty, for whatever reason, no conversion can be
			// performed (it's not possible to assume that a specific currency was
			// used)
			if(empty($order->currency)) {
				Logger::log(sprintf(__('Order %s does not have a currency associated, therefore ' .
															 'it is not possible to determine its value in base currency (%s). ' .
															 'This may lead to imprecise results in the reports.', AELIA_CS_PLUGIN_TEXTDOMAIN),
														$order->order_id,
														$base_currency));

				continue;
			}

			// Try to retrieve the exchange rate used when the order was placed
			$order_exchange_rate = get_value($order->order_id, $orders_exchange_rates, null);
			if($order_exchange_rate !== null) {
				$price_decimals = WC_Aelia_CurrencySwitcher::settings()->price_decimals($base_currency);
				$value_in_base_currency = round($order->meta_value * $order_exchange_rate, $price_decimals);
			}
			else {
				$value_in_base_currency = $this->convert($order->meta_value,
																								 $order->currency,
																								 $base_currency,
																								 $order);
			}

			try {
				$value_in_base_currency = WC_Aelia_CurrencySwitcher::instance()->float_to_string($value_in_base_currency);
				update_post_meta($order->order_id,
												 $order->meta_key . '_base_currency',
												 $value_in_base_currency);
			}
			catch(Exception $e) {
				$this->add_message(E_USER_ERROR,
													 sprintf(__('Exception occurred updating base currency values for order %s. ' .
																			'Error: %s.'),
																	 $order->order_id,
																	 $e->getMessage()));
				return false;
			}
		}

		return true;
	}

	/**
	 * Calculate order totals and taxes in base currency for Orders that have been
	 * generated before version 3.2.10.1402126. This method corrects the calculation
	 * of order totals in base currency, which were incorrectly made taking into
	 * account the exchange markup eventually specified in configuration.
	 * Note: recalculation is made from 2014-01-01 onwards, as exchange rates have
	 * changed significantly in the past months and it's not currently possible
	 * to retrieve them at a specific point in time.
	 *
	 * @return bool
	 */
	protected function update_to_3_2_10_1402126() {
		$base_currency = $this->settings->base_currency();

		// Retrieve the exchange rates for the orders whose data already got
		// partially converted
		$SQL = "
			SELECT
				posts.ID AS order_id
				,posts.post_date AS post_date
				,meta_order.meta_key
				,meta_order.meta_value
				-- ,meta_order_base_currency.meta_key AS meta_key_base_currency
				,meta_order_base_currency.meta_value AS meta_value_base_currency
				,meta_order_currency.meta_value AS currency
			FROM
				{$this->wpdb->posts} AS posts
			JOIN
				{$this->wpdb->postmeta} AS meta_order ON
					(meta_order.post_id = posts.ID) AND
					(meta_order.meta_key IN ('_order_total', '_order_discount', '_cart_discount', '_order_shipping', '_order_tax', '_order_shipping_tax'))
			LEFT JOIN
				{$this->wpdb->postmeta} AS meta_order_base_currency ON
					(meta_order_base_currency.post_id = posts.ID) AND
					(meta_order_base_currency.meta_key = CONCAT(meta_order.meta_key, '_base_currency')) AND
					(meta_order_base_currency.meta_value > 0)
			LEFT JOIN
				{$this->wpdb->postmeta} AS meta_order_currency ON
					(meta_order_currency.post_id = posts.ID) AND
					(meta_order_currency.meta_key = '_order_currency')
			LEFT JOIN
				{$this->wpdb->term_relationships} AS rel ON
					(rel.object_ID = posts.ID)
			LEFT JOIN
				{$this->wpdb->term_taxonomy} AS taxonomy ON
					(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
			LEFT JOIN
				{$this->wpdb->terms} AS term ON
					(term.term_id = taxonomy.term_id)
			WHERE
				(posts.post_type = 'shop_order') AND
				(meta_order.meta_key = '_order_total') AND
				(meta_order.meta_value IS NOT NULL) AND
				(meta_order_base_currency.meta_value IS NOT NULL) AND
				(post_date >= '2014-01-01 00:00:00')
		";

		$orders_to_update = $this->select($SQL);
		// Debug
		//var_dump($orders_to_update); die();

		foreach($orders_to_update as $order) {
			// If order currency is empty, for whatever reason, no conversion can be
			// performed (it's not possible to assume that a specific currency was
			// used)
			if(empty($order->currency)) {
				Logger::log(sprintf(__('Order %s does not have a currency associated, therefore ' .
															 'it is not possible to determine its value in base currency (%s). ' .
															 'This may lead to imprecise results in the reports.', AELIA_CS_PLUGIN_TEXTDOMAIN),
														$order->order_id,
														$base_currency));

				continue;
			}

			// Try to retrieve the exchange rate used when the order was placed
			$value_in_base_currency = $this->convert($order->meta_value,
																							 $order->currency,
																							 $base_currency,
																							 $order);
			$value_in_base_currency = WC_Aelia_CurrencySwitcher::instance()->float_to_string($value_in_base_currency);

			try {
				update_post_meta($order->order_id,
												 $order->meta_key . '_base_currency',
												 $value_in_base_currency);
			}
			catch(Exception $e) {
				$this->add_message(E_USER_ERROR,
													 sprintf(__('Exception occurred updating base currency values for order %s. ' .
																			'Error: %s.'),
																	 $order->order_id,
																	 $e->getMessage()));
				return false;
			}
		}

		return true;
	}

	/**
	 * Calculate order items totals and taxes in base currency for all orders.
	 * This method adds the line totals in base currency for all the order items
	 * created before Currency Switcher 3.2.11.140227 was installed.
	 *
	 * @return bool
	 */
	protected function update_to_3_3_7_140611() {
		$price_decimals = WC_Aelia_CurrencySwitcher::settings()->price_decimals($this->settings->base_currency());

		// Retrieve the order items for which the values in base currencies will be
		// added/updated
		$SQL = $this->wpdb->prepare("
			INSERT INTO {$this->wpdb->prefix}woocommerce_order_itemmeta (
				order_item_id
				,meta_key
				,meta_value
			)
			SELECT
				LINE_ITEMS_DATA.order_item_id
				,LINE_ITEMS_DATA.order_item_meta_key_base_currency
				,LINE_ITEMS_DATA.meta_value_base_currency
			FROM (
				-- Fetch all line items for whom the totals in base currency have not been saved yet
				SELECT
					posts.ID AS order_id
					,meta_order_base_currency.meta_value / meta_order.meta_value as exchange_rate
					,WCOI.order_item_id
					,WCOI.order_item_type
					,WCOIM.meta_key
					,WCOIM.meta_value
					,CONCAT(WCOIM.meta_key, '_base_currency') AS order_item_meta_key_base_currency
					,ROUND(WCOIM.meta_value * (meta_order_base_currency.meta_value / meta_order.meta_value), %d) AS meta_value_base_currency
				FROM
					{$this->wpdb->posts} AS posts
				JOIN
					{$this->wpdb->postmeta} AS meta_order ON
						(meta_order.post_id = posts.ID) AND
						(meta_order.meta_key IN ('_order_total'))
				LEFT JOIN
					{$this->wpdb->postmeta} AS meta_order_base_currency ON
						(meta_order_base_currency.post_id = posts.ID) AND
						(meta_order_base_currency.meta_key = CONCAT(meta_order.meta_key, '_base_currency')) AND
						(meta_order_base_currency.meta_value > 0)
				LEFT JOIN
					{$this->wpdb->postmeta} AS meta_order_currency ON
						(meta_order_currency.post_id = posts.ID) AND
						(meta_order_currency.meta_key = '_order_currency')
				LEFT JOIN
					{$this->wpdb->term_relationships} AS rel ON
						(rel.object_ID = posts.ID)
				LEFT JOIN
					{$this->wpdb->term_taxonomy} AS taxonomy ON
						(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
				LEFT JOIN
					{$this->wpdb->terms} AS term ON
						(term.term_id = taxonomy.term_id)
				-- Order items
				JOIN
					{$this->wpdb->prefix}woocommerce_order_items WCOI ON
						(WCOI.order_id = posts.ID)
				JOIN
					{$this->wpdb->prefix}woocommerce_order_itemmeta WCOIM ON
						(WCOIM.order_item_id = WCOI.order_item_id) AND
						(WCOIM.meta_key IN ('_line_subtotal',
											'_line_subtotal_tax',
											'_line_tax',
											'_line_total',
											'tax_amount',
											'shipping_tax_amount'))
				LEFT JOIN
					{$this->wpdb->prefix}woocommerce_order_itemmeta WCOIM_TOUPDATE ON
						(WCOIM_TOUPDATE.order_item_id = WCOIM.order_item_id) AND
						(WCOIM_TOUPDATE.meta_key = CONCAT(WCOIM.meta_key, '_base_currency'))
				WHERE
					(WCOIM_TOUPDATE.meta_value IS NULL) AND
					(posts.post_type = 'shop_order') AND
					(meta_order.meta_key = '_order_total') AND
					(meta_order.meta_value IS NOT NULL) AND
					(meta_order_base_currency.meta_value IS NOT NULL)
			) AS LINE_ITEMS_DATA;
		", $price_decimals);

		//var_dump($SQL);die();

		$this->add_message(E_USER_NOTICE,
											 __('Recalculating line totals in base currency...'));
		$rows_affected = $this->exec($SQL);

		// Debug
		//var_dump($order_items_to_update);die();
		if($rows_affected === false) {
			$this->add_message(E_USER_ERROR,
												 __('Failed. Please check PHP error log for error messages ' .
														'related to the operation.'));
			return false;
		}
		else {
			$this->add_message(E_USER_NOTICE,
												 sprintf(__('Done. %s rows affected.'), $rows_affected));
		}

		return true;
	}
}
