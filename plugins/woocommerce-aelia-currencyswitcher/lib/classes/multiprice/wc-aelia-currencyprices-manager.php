<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

interface IWC_Aelia_CurrencyPrices_Manager {
	public function convert_product_prices(WC_Product $product, $currency);
	public function convert_external_product_prices(WC_Product_External $product, $currency);
	public function convert_grouped_product_prices(WC_Product_Grouped $product, $currency);
	public function convert_legacy_product_prices(WC_Product $product, $currency);
	public function convert_simple_product_prices(WC_Product $product, $currency);
	public function convert_variable_product_prices(WC_Product $product, $currency);
	public function convert_variation_product_prices(WC_Product_Variation $product, $currency);
}

/**
 * Handles currency conversion for the various product types.
 * Due to its architecture, this class should not be instantiated twice. To get
 * the instance of the class, call WC_Aelia_CurrencyPrices_Manager::Instance().
 */
class WC_Aelia_CurrencyPrices_Manager implements IWC_Aelia_CurrencyPrices_Manager {
	protected $admin_views_path;

	// @var WC_Aelia_CurrencyPrices_Manager The singleton instance of the prices manager
	protected static $instance;

	const FIELD_REGULAR_CURRENCY_PRICES = '_regular_currency_prices';
	const FIELD_SALE_CURRENCY_PRICES = '_sale_currency_prices';
	const FIELD_VARIABLE_REGULAR_CURRENCY_PRICES = 'variable_regular_currency_prices';
	const FIELD_VARIABLE_SALE_CURRENCY_PRICES = 'variable_sale_currency_prices';

	/**
	 * Convenience method. Returns the instance of the Currency Switcher.
	 *
	 * @return WC_Aelia_CurrencySwitcher
	 */
	protected function currencyswitcher() {
		return WC_Aelia_CurrencySwitcher::instance();
	}

	/**
	 * Convenience method. Returns WooCommerce base currency.
	 *
	 * @return string
	 */
	protected function base_currency() {
		return WC_Aelia_CurrencySwitcher::settings()->base_currency();
	}

	/**
	 * Converts an amount from base currency to another.
	 *
	 * @param float amount The amount to convert.
	 * @param string to_currency The destination Currency.
	 * @param int precision The precision to use when rounding the converted result.
	 * @return float The amount converted in the destination currency.
	 */
	public function convert_from_base($amount, $to_currency) {
		return $this->currencyswitcher()->convert($amount,
																							$this->base_currency(),
																							$to_currency);
	}

	/**
	 * Callback for array_filter(). Returns true if the passed value is numeric.
	 *
	 * @param mixed value The value to check.
	 * @return bool
	 */
	protected function keep_numeric($value) {
		return is_numeric($value);
	}

	/**
	 * Returns the minimum numeric value found in an array. Non numeric values are
	 * ignored. If no numeric value is passed in the array of values, then NULL is
	 * returned.
	 *
	 * @param array values An array of values.
	 * @return float|null
	 */
	public function get_min_value(array $values) {
		$values = array_filter($values, array($this, 'keep_numeric'));

		if(empty($values)) {
			return null;
		}

		return min($values);
	}

	/**
	 * Returns the maximum numeric value found in an array. Non numeric values are
	 * ignored. If no numeric value is passed in the array of values, then NULL is
	 * returned.
	 *
	 * @param array values An array of values.
	 * @return float|null
	 */
	public function get_max_value(array $values) {
		$values = array_filter($values, array($this, 'keep_numeric'));

		if(empty($values)) {
			return null;
		}

		return max($values);
	}

	/*** Hooks ***/
	/**
	 * Display Currency prices for Simple Products.
	 */
	public function woocommerce_product_options_pricing() {
		global $post;
		$this->current_post = $post;

		$file_to_load = apply_filters('wc_aelia_currencyswitcher_simple_product_pricing_view_load', 'simpleproduct_currencyprices_view.php', $post);
		$this->load_view($file_to_load);
	}

	/**
	 * Display Currency prices for Variable Products.
	 */
	public function woocommerce_product_after_variable_attributes($loop, $variation_data, $variation = null) {
		//var_dump($loop, $variation_data, $variation);
		// A Variation instance is not passed by WooCommerce 1.6. In such case, we
		// have to retrieve the variation using its ID.
		if(empty($variation)) {
			$variation_id = get_value('variation_post_id', $variation_data, null);
			if(!empty($variation_id)) {
				$variation = new WC_Product_Variation($variation_id);
				// WooCommerce 1.6 doesn't populate the ID field, therefore we set it
				// manually
				$variation->ID = $variation_id;
			}
			else {
				trigger_error(sprintf(__('Hook "woocommerce_product_after_variable_attributes". Unexpected ' .
																 'condition: variation ID is empty. Variation data (JSON): "%s".'),
											json_encode($variation_data)),
							E_USER_WARNING);
			}
		}
		$this->current_post = $variation;

		$this->loop_idx = $loop;

		$file_to_load = apply_filters('wc_aelia_currencyswitcher_variation_product_pricing_view_load', 'variation_currencyprices_view.php', $variation);
		$this->load_view($file_to_load);
	}

	/**
	 * Event handler fired when a Product is being saved. It processes and saves
	 * the Currency Prices associated with the Product.
	 *
	 * @param int post_id The ID of the Post (product) being saved.
	 */
	public function process_product_meta($post_id) {
		//var_dump($_POST);die();

		$product_regular_prices = $this->sanitise_currency_prices(get_value(self::FIELD_REGULAR_CURRENCY_PRICES, $_POST));
		$product_sale_prices = $this->sanitise_currency_prices(get_value(self::FIELD_SALE_CURRENCY_PRICES, $_POST));

		// D.Zanella - This code saves the product prices in the different Currencies
		update_post_meta($post_id, self::FIELD_REGULAR_CURRENCY_PRICES, json_encode($product_regular_prices));
		update_post_meta($post_id, self::FIELD_SALE_CURRENCY_PRICES, json_encode($product_sale_prices));
	}

	/**
	 * Event handler fired when a Product is being saved. It processes and saves
	 * the Currency Prices associated with the Product.
	 *
	 * @param int post_id The ID of the Post (product) being saved.
	 */
	public function woocommerce_process_product_meta_variable($post_id) {
		//var_dump($_POST);die();

		// Retrieve all IDs, regular prices and sale prices for all variations. The
		// "all_" prefix has been added to easily distinguish these variables from
		// the ones containing the data of a single variation, whose names would
		// be otherwise very similar
		$all_variations_ids = get_value('variable_post_id', $_POST, array());
		$all_variations_regular_currency_prices = get_value(self::FIELD_VARIABLE_REGULAR_CURRENCY_PRICES, $_POST);
		$all_variations_sales_currency_prices = get_value(self::FIELD_VARIABLE_SALE_CURRENCY_PRICES, $_POST);

		foreach($all_variations_ids as $variation_idx => $variation_id) {
			$variation_regular_currency_prices = $this->sanitise_currency_prices(get_value($variation_idx, $all_variations_regular_currency_prices, null));
			$variation_sale_currency_prices = $this->sanitise_currency_prices(get_value($variation_idx, $all_variations_sales_currency_prices, null));

			// D.Zanella - This code saves the variation prices in the different Currencies
			update_post_meta($variation_id, self::FIELD_VARIABLE_REGULAR_CURRENCY_PRICES, json_encode($variation_regular_currency_prices));
			update_post_meta($variation_id, self::FIELD_VARIABLE_SALE_CURRENCY_PRICES, json_encode($variation_sale_currency_prices));
		}
	}

	/**
	 * Returns the HTML to display minimum price for a grouped product, in
	 * currently selected currency. This method replaces the logic of
	 * WC_Product_Grouped::get_price_html() and takes into account exchange rates
	 * and manually entered product prices.
	 *
	 * @param float price The product price.
	 * @param WC_Product_Grouped product The grouped product.
	 * @return string
	 */
	public function woocommerce_grouped_price_html($price, $product) {
		$child_prices = array();

		foreach($product->get_children() as $child_id) {
			// Price must be converted to currently selected currency. To do so, a
			// Product must be instantiated, so that we can find out if there are
			// manually entered prices, or if the exchange rate should be used
			$product = new WC_Product_Simple($child_id);
			$this->convert_product_prices($product, $this->currencyswitcher()->get_selected_currency());
			$child_prices[] = $product->price;
		}

		$child_prices = array_unique($child_prices);

		if(!empty($child_prices)) {
			$min_price = min($child_prices);
		}
		else {
			$min_price = '';
		}

		$price = '';
		if(sizeof($child_prices) > 1) {
			$price .= $product->get_price_html_from_text();
		}

		$price .= woocommerce_price($min_price);

		return $price;
	}

	/**
	 * Processes an array of Currency => Price values, ensuring that they contain
	 * valid data, and returns the sanitised array.
	 *
	 * @param array currency_prices An array of Currency => Price pairs.
	 * @return array
	 */
	public function sanitise_currency_prices($currency_prices) {
		if(!is_array($currency_prices)) {
			return array();
		}

		$result = array();
		foreach($currency_prices as $currency => $price) {
			// To be valid, the Currency must have been enabled in the configuration
			if(!WC_Aelia_CurrencySwitcher::settings()->is_currency_enabled($currency)) {
				continue;
			}

			// To be valid, the Currency must be a number
			if(!is_numeric($price)) {
				continue;
			}

			$result[$currency] = $price;
		}

		return $result;
	}

	/**
	 * Convenience method. Returns an array of the Enabled Currencies.
	 *
	 * @return array
	 */
	protected function enabled_currencies() {
		return WC_Aelia_CurrencySwitcher::settings()->get_enabled_currencies();
	}

	/**
	 * Returns an array of Currency => Price values containing the Currency Prices
	 * of the specified type (e.g. Regular, Sale, etc).
	 *
	 * @param int post_id The ID of the Post (product).
	 * @param string prices_type The type of prices to return.
	 * @return array
	 */
	public function get_product_currency_prices($post_id, $prices_type) {
		$result = json_decode(get_post_meta($post_id, $prices_type, true), true);

		if(!is_array($result)) {
			$result = array();
		}

		return $result;
	}

	/**
	 * Returns an array of Currency => Price values containing the Regular
	 * Currency Prices a Product.
	 *
	 * @param int post_id The ID of the Post (product).
	 * @return array
	 */
	public function get_product_regular_prices($post_id) {
		return $this->get_product_currency_prices($post_id,
																							self::FIELD_REGULAR_CURRENCY_PRICES);
	}

	/**
	 * Returns an array of Currency => Price values containing the Sale Currency
	 * Prices a Product.
	 *
	 * @param int post_id The ID of the Post (product).
	 * @return array
	 */
	public function get_product_sale_prices($post_id) {
		return $this->get_product_currency_prices($post_id,
																							self::FIELD_SALE_CURRENCY_PRICES);
	}

	/**
	 * Returns an array of Currency => Price values containing the Regular
	 * Currency Prices a Product Variation.
	 *
	 * @param int post_id The ID of the Post (product).
	 * @return array
	 */
	public function get_variation_regular_prices($post_id) {
		return $this->get_product_currency_prices($post_id,
																							self::FIELD_VARIABLE_REGULAR_CURRENCY_PRICES);
	}

	/**
	 * Returns an array of Currency => Price values containing the Sale Currency
	 * Prices a Product Variation.
	 *
	 * @param int post_id The ID of the Post (product).
	 * @return array
	 */
	public function get_variation_sale_prices($post_id) {
		return $this->get_product_currency_prices($post_id,
																							self::FIELD_VARIABLE_SALE_CURRENCY_PRICES);
	}

	/**
	 * Loads (includes) a View file.
	 *
	 * @param string view_file_name The name of the view file to include.
	 */
	private function load_view($view_file_name) {
		$file_to_load = $this->admin_views_path . '/' . $view_file_name;
		$file_to_load = apply_filters('wc_aelia_currencyswitcher_product_pricing_view_load', $file_to_load);

		if(!empty($file_to_load) && is_readable($file_to_load)) {
			include($file_to_load);
		}
	}

	/**
	 * Sets the hooks required by the class.
	 */
	private function set_hooks() {
		// Hooks for simple, external and grouped products
		add_action('woocommerce_product_options_pricing', array($this, 'woocommerce_product_options_pricing'));
		add_action('woocommerce_process_product_meta_simple', array($this, 'process_product_meta'));
		add_action('woocommerce_process_product_meta_external', array($this, 'process_product_meta'));

		// Hooks for variable products
		add_action('woocommerce_product_after_variable_attributes', array($this, 'woocommerce_product_after_variable_attributes'), 10, 3);
		add_action('woocommerce_process_product_meta_variable', array($this, 'woocommerce_process_product_meta_variable'));

		// Hooks for grouped products
		add_action('woocommerce_process_product_meta_grouped', array($this, 'process_product_meta'));
		add_action('woocommerce_grouped_price_html', array($this, 'woocommerce_grouped_price_html'), 10, 2);

		// WooCommerce 2.1
		add_filter('woocommerce_get_variation_regular_price', array($this, 'woocommerce_get_variation_regular_price'), 20, 4);
		add_filter('woocommerce_get_variation_sale_price', array($this, 'woocommerce_get_variation_sale_price'), 20, 4);

		// Bulk pricing for variable products
		add_action('woocommerce_variable_product_bulk_edit_actions', array($this, 'woocommerce_variable_product_bulk_edit_actions'));
	}

	/**
	 * Returns the method to be used to convert the prices of a product. The
	 * method depends on the class of the product instance.
	 *
	 * @param WC_Product product An instance of a product.
	 * @return string|null The method to use to process the product, or null if
	 * product type is unsupported.
	 */
	protected function get_convert_callback(WC_Product $product) {
		$method_keys = array(
			'WC_Product' => 'legacy',
			'WC_Product_Simple' => 'simple',
			'WC_Product_Variable' => 'variable',
			'WC_Product_Variation' => 'variation',
			'WC_Product_External' => 'external',
			'WC_Product_Grouped' => 'grouped',
		);

		$method_key = get_value(get_class($product), $method_keys, '');
		// Determine the method that will be used to convert the product prices
		$convert_method = 'convert_' . $method_key . '_product_prices';
		$convert_callback = method_exists($this, $convert_method) ? array($this, $convert_method) : null;

		// Allow external classes to alter the callback, if needed
		$convert_callback = apply_filters('wc_aelia_currencyswitcher_product_convert_callback', $convert_callback, $product);
		if(!is_callable($convert_callback)) {
			trigger_error(sprintf(__('Attempted to convert an unsupported product object. This usually happens when a ' .
															 '3rd party plugin adds custom product types, of which the Currency Switcher is ' .
															 'not aware. Product prices will not be converted. Please report the issue to ' .
															 'support as a compatibility request. Product type that triggered the message: "%s".'),
														$product->product_type),
										E_USER_NOTICE);
		}
		return $convert_callback;
	}

	/**
	 * Converts a product or variation prices to the specific currency, taking
	 * into account manually entered prices.
	 *
	 * @param WC_Product product The product whose prices should be converted.
	 * @param string currency A currency code.
	 * @param array product_regular_prices_in_currency An array of manually entered
	 * product prices (one for each currency).
	 * @param array product_sale_prices_in_currency An array of manually entered
	 * product prices (one for each currency).
	 * @return WC_Product
	 */
	protected function convert_to_currency(WC_Product $product, $currency,
																				 array $product_regular_prices_in_currency,
																				 array $product_sale_prices_in_currency) {
		// The determination of "is on sale" is done using values of product's
		// prices. To ensure that the logic is not broken, such evaluation should be
		// performed before any conversion
		$product_is_on_sale = $product->is_on_sale();

		$product->regular_price = get_value($currency,
																				$product_regular_prices_in_currency,
																				$this->convert_from_base($product->regular_price, $currency));
		$product->sale_price = get_value($currency,
																		 $product_sale_prices_in_currency,
																		 $this->convert_from_base($product->sale_price, $currency));

		if(!is_numeric($product->regular_price) ||
			 ($product_is_on_sale && ($product->sale_price < $product->regular_price))) {
			$product->price = $product->sale_price;
		}
		else {
			$product->price = $product->regular_price;
		}

		return $product;
	}

	/**
	 * Convert the prices of a product in the destination currency.
	 *
	 * @param WC_Product product A product (simple, variable, variation).
	 * @param string currency A currency code.
	 * @return WC_Product The product with converted prices.
	 */
	public function convert_product_prices(WC_Product $product, $currency) {
		// Since WooCommerce 2.1, this method can be triggered recursively due to
		// a (not so wise) change in WC architecture. It's therefore necessary to keep
		// track of when the conversion started, to prevent infinite recursion
		if($product->aelia_cs_conversion_in_progress) {
			return $product;
		}

		// Flag the product to keep track that conversion is in progress
		$product->aelia_cs_conversion_in_progress = true;

		// If product has a "currencyswitcher_original_product" attribute, it means
		// that it was already processed by the Currency Switcher. In such case, it
		// has to be reverted to the original status before being processed again
		if(!empty($product->currencyswitcher_original_product)) {
			$product = $product->currencyswitcher_original_product;
		}
		// Take a copy of the original product before processing
		$original_product = clone $product;

		// Get the method to use to process the product
		$convert_callback = $this->get_convert_callback($product);
		if(!empty($convert_callback) && is_callable($convert_callback)) {
			$product = call_user_func($convert_callback, $product, $currency);
		}
		else {
			// If no conversion function is found, use the generic one
			$product = $this->convert_generic_product_prices($product, $currency);
		}

		// Assign the original product to the processed one
		$product->currencyswitcher_original_product = $original_product;

		// Remove "conversion is in progress" flag when the operation is complete
		unset($product->aelia_cs_conversion_in_progress);

		return $product;
	}

	/**
	 * Converts the prices of a variable product to the specified currency.
	 *
	 * @param WC_Product_Variable product A variable product.
	 * @param string currency A currency code.
	 * @return WC_Product_Variable The product with converted prices.
	 */
	public function convert_variable_product_prices(WC_Product $product, $currency) {
		$product_children = $product->get_children();
		if(empty($product->children)) {
			return $product;
		}

		$variation_regular_prices = array();
		$variation_sale_prices = array();
		$variation_prices = array();

		foreach($product->children as $variation_id) {
			$variation = $this->load_variation_in_currency($variation_id, $currency);
			if(empty($variation)) {
				continue;
			}

			$variation_regular_prices[] = $variation->regular_price;
			$variation_sale_prices[] = $variation->sale_price;
			$variation_prices[] = $variation->price;
		}

		$product->min_variation_regular_price = $this->get_min_value($variation_regular_prices);
		$product->max_variation_regular_price = $this->get_max_value($variation_regular_prices);

		$product->min_variation_sale_price = $this->get_min_value($variation_sale_prices);
		$product->max_variation_sale_price = $this->get_max_value($variation_sale_prices);

		$product->min_variation_price = $this->get_min_value($variation_prices);
		$product->max_variation_price = $this->get_max_value($variation_prices);

		$product->price = $product->min_variation_price;

		return $product;
	}

	/**
	 * Converts the product prices of a variation.
	 *
	 * @param WC_Product_Variation $product A product variation.
	 * @param string currency A currency code.
	 * @return WC_Product_Variation The variation with converted prices.
	 */
	public function convert_variation_product_prices(WC_Product_Variation $product, $currency) {
		$product = $this->convert_to_currency($product,
																					$currency,
																					$this->get_variation_regular_prices($product->variation_id),
																					$this->get_variation_sale_prices($product->variation_id));

		return $product;
	}

	/**
	 * Given a Variation ID, it loads the variation and returns it, with its
	 * prices converted into the specified currency.
	 *
	 * @param int variation_id The ID of the variation.
	 * @param string currency A currency code.
	 * @return WC_Product_Variation
	 */
	public function load_variation_in_currency($variation_id, $currency) {
		$variation = new WC_Product_Variation($variation_id);

		if(empty($variation)) {
			return false;
		}

		$variation = $this->convert_product_prices($variation, $currency);

		return $variation;
	}

	/**
	 * Converts the prices of a generic product to the specified currency. This
	 * method is a fallback, in case no specific conversion function was found by
	 * the pricing manager.
	 *
	 * @param WC_Product product A simple product.
	 * @param string currency A currency code.
	 * @return WC_Product_Variable The simple product with converted prices.
	 */
	public function convert_generic_product_prices(WC_Product $product, $currency) {
		return $this->convert_simple_product_prices($product, $currency);
	}

	/**
	 * Converts the prices of a simple product to the specified currency.
	 *
	 * @param WC_Product product A simple product.
	 * @param string currency A currency code.
	 * @return WC_Product_Variable The simple product with converted prices.
	 */
	public function convert_simple_product_prices(WC_Product $product, $currency) {
		$product = $this->convert_to_currency($product,
																					$currency,
																					$this->get_product_regular_prices($product->id),
																					$this->get_product_sale_prices($product->id));

		return $product;
	}

	/**
	 * Converts the prices of an external product to the specified currency.
	 *
	 * @param WC_Product_External product An external product.
	 * @param string currency A currency code.
	 * @return WC_Product_Variable The external product with converted prices.
	 */
	public function convert_external_product_prices(WC_Product_External $product, $currency) {
		return $this->convert_simple_product_prices($product, $currency);
	}

	/**
	 * Converts the prices of a grouped product to the specified currency.
	 *
	 * @param WC_Product_Grouped product A grouped product.
	 * @param string currency A currency code.
	 * @return WC_Product_Grouped
	 */
	public function convert_grouped_product_prices(WC_Product_Grouped $product, $currency) {
		// Grouped products don't have a price. Prices can be found in child products
		// which belong to the grouped product. Such child products are processed
		// independently, therefore no further action is needed
		return $product;
	}

	/**
	 * For WooCommerce 1.6 only.
	 * Converts the prices of a product into the selected currency. This method
	 * is implemented because WC 1.6 used WC_Product class for both simple and
	 * variable products.
	 *
	 * @param WC_Product product A product.
	 * @param string currency A currency code.
	 * @return WC_Product
	 */
	public function convert_legacy_product_prices(WC_Product $product, $currency) {
		$product_children = $product->get_children();

		if(empty($product_children)) {
			$product = $this->convert_to_currency($product,
																				$currency,
																				$this->get_product_regular_prices($product->id),
																				$this->get_product_sale_prices($product->id));
		}
		else {
			$product = $this->convert_variable_product_prices($product, $currency);
		}

		return $product;
	}

	/**
	 * Checks that the price type specified is "min" or "max".
	 *
	 * @param string price_type The price type.
	 * @return bool
	 */
	protected function is_min_max_price_type_valid($price_type) {
		$valid_price_types = array(
			'min',
			'max'
		);

		return in_array($price_type, $valid_price_types);
	}

	/**
	 * Process a variation price, recalculating it depending if it already
	 * includes taxes and/or if prices should be displayed with our without taxes.
	 *
	 * @param string price The product price passed by WooCommerce.
	 * @param WC_Product product The product for which the price is being retrieved.
	 * @param string min_or_max The type of price to retrieve. It can be 'min' or 'max'.
	 * @param boolean display Whether the value is going to be displayed
	 * @return float
	 * @since 3.2
	 */
	public function process_product_price_tax($product, $price) {
		$tax_display_mode = get_option('woocommerce_tax_display_shop');
		if($tax_display_mode == 'incl') {
			$price = $product->get_price_including_tax(1, $price);
		}
		else {
			$price = $product->get_price_excluding_tax(1, $price);
		}

		return $price;
	}

	/**
	 * Process a variation price, recalculating it depending if it already
	 * includes taxes and/or if prices should be displayed with our without taxes.
	 *
	 * @param string price The product price passed by WooCommerce.
	 * @param WC_Product product The product for which the price is being retrieved.
	 * @param string min_or_max The type of price to retrieve. It can be 'min' or 'max'.
	 * @param boolean display Whether the value is going to be displayed
	 * @return float
	 * @since 3.2
	 */
	public function process_variation_price_tax($price, $product, $min_or_max, $display) {
		if($display) {
			$variation_id = get_post_meta($product->id, '_' . $min_or_max . '_price_variation_id', true);
			$variation = $product->get_child($variation_id);

			$tax_display_mode = get_option('woocommerce_tax_display_shop');
			if($tax_display_mode == 'incl') {
				$price = $variation->get_price_including_tax(1, $price);
			}
			else {
				$price = $variation->get_price_excluding_tax(1, $price);
			}
		}

		return $price;
	}

	/**
	 * Get the minimum or maximum variation regular price.
	 *
	 * @param string price The product price passed by WooCommerce.
	 * @param WC_Product product The product for which the price is being retrieved.
	 * @param string min_or_max The type of price to retrieve. It can be 'min' or 'max'.
	 * @param boolean display Whether the value is going to be displayed
	 * @return float
	 */
	public function woocommerce_get_variation_regular_price($price, $product, $min_or_max, $display) {
		// If we are in the backend, no conversion takes place, therefore we can return
		// the original value, in base currency
		if(is_admin()) {
			return $price;
		}

		if(!$this->is_min_max_price_type_valid($min_or_max)) {
			trigger_error(sprintf(__('Invalid variation regular price type specified: "%s".'),
														$min_or_max),
										E_USER_WARNING);
			return $price;
		}

		// Retrieve the price in the selected currency
		$price = get_value($min_or_max . '_variation_regular_price', $product);
		// Process the price, recalculating it depending if it already includes tax or not
		$price = $this->process_variation_price_tax($price, $product, $min_or_max, $display);

		return $price;
	}

	/**
	 * Get the minimum or maximum variation regular price.
	 *
	 * @param string price The product price passed by WooCommerce.
	 * @param WC_Product product The product for which the price is being retrieved.
	 * @param string min_or_max The type of price to retrieve. It can be 'min' or 'max'.
	 * @param boolean display Whether the value is going to be displayed
	 * @return float
	 */
	public function woocommerce_get_variation_sale_price($price, $product, $min_or_max, $display) {
		// If we are in the backend, no conversion takes place, therefore we can return
		// the original value, in base currency
		if(is_admin()) {
			return $price;
		}

		if(!$this->is_min_max_price_type_valid($min_or_max)) {
			trigger_error(sprintf(__('Invalid variation sale price type specified: "%s".'),
														$min_or_max),
										E_USER_WARNING);
			return $price;
		}

		// Retrieve the price in the selected currency
		$sale_price = get_value($min_or_max . '_variation_sale_price', $product);
		// Process the price, recalculating it depending if it already includes tax or not
		$sale_price = $this->process_variation_price_tax($sale_price, $product, $min_or_max, $display);

		return $sale_price;
	}

	public function woocommerce_variable_product_bulk_edit_actions() {
		$enabled_currencies = $this->enabled_currencies();
		if(empty($enabled_currencies)) {
			return;
		}

		$text_domain = WC_Aelia_CurrencySwitcher::$text_domain;
		echo '<optgroup label="' . __('Currency prices', $text_domain) . '">';
		foreach($enabled_currencies as $currency) {
			// No need to add an option for the base currency, it already exists in standard WooCommerce menu
			if($currency == $this->base_currency()) {
				continue;
			}

			// Display entry for variation's regular prices
			echo "<option value=\"variable_regular_currency_prices_{$currency}\" currency=\"{$currency}\">";
			printf(__('Regular prices (%s)', $text_domain),
						 $currency);
			echo '</option>';

			// Display entry for variation's sale prices
			echo "<option value=\"variable_sale_currency_prices_{$currency}\"  currency=\"{$currency}\">";
			printf(__('Sale prices (%s)', $text_domain),
						 $currency);
			echo '</option>';
		}
		echo '</optgroup>';
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->admin_views_path = AELIA_CS_VIEWS_PATH . '/admin/wc20';

		$this->set_hooks();
	}

	/**
	 * Returns the singleton instance of the prices manager.
	 *
	 * @return WC_Aelia_CurrencyPrices_Manager
	 */
	public static function Instance() {
		if(empty(self::$instance)) {
			self::$instance = new WC_Aelia_CurrencyPrices_Manager();
		}

		return self::$instance;
	}
}
