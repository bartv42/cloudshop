<?php
namespace Aelia\WC\CurrencySwitcher\Subscriptions;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \WC_Aelia_CurrencySwitcher;
use \WC_Aelia_CurrencyPrices_Manager;
use \WC_Subscriptions_Product;
use \WC_Product;
use \WC_Product_Subscription;
use \WC_Product_Subscription_Variation;
use \WC_Subscriptions_Cart;

/**
 * Implements support for WooThemes Subscriptions plugin.
 */
class Subscriptions_Integration {
	const FIELD_SIGNUP_FEE_CURRENCY_PRICES = '_subscription_signup_fee_currency_prices';
	const FIELD_VARIATION_SIGNUP_FEE_CURRENCY_PRICES = '_subscription_variation_signup_fee_currency_prices';

	const FIELD_REGULAR_CURRENCY_PRICES = '_subscription_variation_regular_currency_prices';
	const FIELD_SALE_CURRENCY_PRICES = '_subscription_variation_sale_currency_prices';
	const FIELD_VARIATION_REGULAR_CURRENCY_PRICES = '_subscription_variation_regular_currency_prices';
	const FIELD_VARIATION_SALE_CURRENCY_PRICES = '_subscription_variation_sale_currency_prices';

	// @var WC_Aelia_CurrencyPrices_Manager The object that handles currency prices for the products.
	private $currencyprices_manager;

	/**
	 * Returns the instance of the Currency Switcher plugin.
	 *
	 * @return WC_Aelia_CurrencySwitcher
	 */
	protected function currency_switcher() {
		return WC_Aelia_CurrencySwitcher::instance();
	}

	/**
	 * Returns the instance of the settings controller loaded by the plugin.
	 *
	 * @return WC_Aelia_CurrencySwitcher_Settings
	 */
	protected function settings_controller() {
		return WC_Aelia_CurrencySwitcher::settings();
	}

	/**
	 * Returns the instance of the currency prices manager used by the Currency
	 * Switcher plugin.
	 *
	 * @return WC_Aelia_CurrencySwitcher_Settings
	 */
	protected function currencyprices_manager() {
		return WC_Aelia_CurrencyPrices_Manager::Instance();
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
	 * Returns an array of Currency => Price values containing the signup fees
	 * of a subscription, in each currency.
	 *
	 * @param int post_id The ID of the Post (subscription).
	 * @return array
	 */
	public static function get_subscription_signup_prices($post_id) {
		return WC_Aelia_CurrencyPrices_Manager::Instance()->get_product_currency_prices($post_id,
																																										self::FIELD_SIGNUP_FEE_CURRENCY_PRICES);
	}

	/**
	 * Returns an array of Currency => Price values containing the signup fees
	 * of a subscription variation, in each currency.
	 *
	 * @param int post_id The ID of the Post (subscription).
	 * @return array
	 */
	public static function get_subscription_variation_signup_prices($post_id) {
		return WC_Aelia_CurrencyPrices_Manager::Instance()->get_product_currency_prices($post_id,
																																										self::FIELD_VARIATION_SIGNUP_FEE_CURRENCY_PRICES);
	}

	/**
	 * Returns an array of Currency => Price values containing the Regular
	 * Currency Prices of a subscription.
	 *
	 * @param int post_id The ID of the Post (subscription).
	 * @return array
	 */
	public function get_subscription_regular_prices($post_id) {
		return $this->currencyprices_manager()->get_product_currency_prices($post_id,
																																				WC_Aelia_CurrencyPrices_Manager::FIELD_REGULAR_CURRENCY_PRICES);
	}

	/**
	 * Returns an array of Currency => Price values containing the Sale Currency
	 * Prices of a subscription.
	 *
	 * @param int post_id The ID of the Post (subscription).
	 * @return array
	 */
	public function get_subscription_sale_prices($post_id) {
		return $this->currencyprices_manager()->get_product_currency_prices($post_id,
																																				WC_Aelia_CurrencyPrices_Manager::FIELD_SALE_CURRENCY_PRICES);
	}

	/**
	 * Converts a subscription prices to the specific currency, taking
	 * into account manually entered prices.
	 *
	 * @param WC_Product product The subscription whose prices should
	 * be converted.
	 * @param string currency A currency code.
	 * @param array product_regular_prices_in_currency An array of manually entered
	 * product prices (one for each currency).
	 * @param array product_sale_prices_in_currency An array of manually entered
	 * product prices (one for each currency).
	 * @return WC_Product
	 */
	protected function convert_to_currency(WC_Product $product, $currency,
																				 array $product_regular_prices_in_currency,
																				 array $product_sale_prices_in_currency,
																				 array $product_signup_prices_in_currency) {

		// The determination of "is on sale" is done using values of product's
		// prices. To ensure that the logic is not broken, such evaluation should be
		// performed before any conversion
		$product_is_on_sale = $product->is_on_sale();
		$product->subscription_price = get_value($currency,
																						 $product_regular_prices_in_currency,
																						 $this->currencyprices_manager()->convert_from_base($product->subscription_price, $currency));
		$product->regular_price = $product->subscription_price;
		$product->sale_price = get_value($currency,
																		 $product_sale_prices_in_currency,
																		 $this->currencyprices_manager()->convert_from_base($product->sale_price, $currency));
		$product->subscription_sign_up_fee = get_value($currency,
																									 $product_signup_prices_in_currency,
																									 $this->currencyprices_manager()->convert_from_base($product->subscription_sign_up_fee, $currency));

		if(!is_numeric($product->subscription_price) ||
			 ($product_is_on_sale && ($product->sale_price < $product->subscription_price))) {
			$product->price = $product->sale_price;
		}
		else {
			$product->price = $product->subscription_price;
		}

		return $product;
	}

	public function __construct() {
		$this->set_hooks();
	}

	/**
	 * Set the hooks required by the class.
	 */
	protected function set_hooks() {
		// Price conversion
		add_filter('wc_aelia_currencyswitcher_product_convert_callback', array($this, 'wc_aelia_currencyswitcher_product_convert_callback'), 10, 2);
		add_filter('woocommerce_subscriptions_product_price', array($this, 'woocommerce_subscriptions_product_price'), 10, 2);
		//add_filter('woocommerce_subscriptions_product_price_string_inclusions', array($this, 'woocommerce_subscriptions_product_price_string_inclusions'), 10, 2);
		add_filter('woocommerce_subscriptions_product_sign_up_fee', array($this, 'woocommerce_subscriptions_product_sign_up_fee'), 10, 2);

		// Product edit/add hooks
		add_action('woocommerce_process_product_meta_subscription', array($this, 'woocommerce_process_product_meta_subscription'), 10);
		add_action('woocommerce_process_product_meta_variable-subscription', array($this, 'woocommerce_process_product_meta_variable_subscription'), 10);

		// Admin UI
		add_action('woocommerce_product_options_general_product_data', array($this, 'woocommerce_product_options_general_product_data'), 20);
		add_filter('woocommerce_product_after_variable_attributes', array($this, 'woocommerce_product_after_variable_attributes'), 20);

		// Cart hooks
		add_action('wc_aelia_currencyswitcher_recalculate_cart_totals_before', array($this, 'wc_aelia_currencyswitcher_recalculate_cart_totals_before'), 10);
		//add_action('wc_aelia_currencyswitcher_recalculate_cart_totals_after', array($this, 'wc_aelia_currencyswitcher_recalculate_cart_totals_after'), 10);

		// Coupon types
		add_filter('wc_aelia_cs_coupon_types_to_convert', array($this, 'wc_aelia_cs_coupon_types_to_convert'), 10, 1);
	}

	/**
	 * Converts all the prices of a given product in the currently selected
	 * currency.
	 *
	 * @param WC_Product product The product whose prices should be converted.
	 * @return WC_Product
	 */
	protected function convert_product_prices($product) {
		$selected_currency = $this->currency_switcher()->get_selected_currency();
		$base_currency = $this->settings_controller()->base_currency();

		if(get_value('currency', $product, $base_currency) != $selected_currency) {
			$product = $this->currencyprices_manager()->convert_product_prices($product, $selected_currency);
			$product->currency = $selected_currency;
		}

		return $product;
	}

	/**
	 * Callback to perform the conversion of subscription prices into selected currencu.
	 *
	 * @param callable $original_convert_callback The original callback passed to the hook.
	 * @param WC_Product The product to examine.
	 * @return callable
	 */
	public function wc_aelia_currencyswitcher_product_convert_callback($original_convert_callback, $product) {
		$method_keys = array(
			'WC_Product_Subscription' => 'subscription',
			// TODO Implement conversion of variable subscriptions
			'WC_Product_Subscription_Variation' => 'subscription_variation',
			'WC_Product_Variable_Subscription' => 'variable_subscription',
		);

		// Determine the conversion method to use
		$method_key = get_value(get_class($product), $method_keys, '');
		$convert_method = 'convert_' . $method_key . '_product_prices';

		if(!method_exists($this, $convert_method)) {
			return $original_convert_callback;
		}

		return array($this, $convert_method);
	}

	/**
	 * Converts the prices of a subscription product to the specified currency.
	 *
	 * @param WC_Product_Subscription product A subscription product.
	 * @param string currency A currency code.
	 * @return WC_Product_Subscription The product with converted prices.
	 */
	public function convert_subscription_product_prices(WC_Product_Subscription $product, $currency) {
		$product = $this->convert_to_currency($product,
																					$currency,
																					$this->get_subscription_regular_prices($product->id),
																					$this->get_subscription_sale_prices($product->id),
																					self::get_subscription_signup_prices($product->id));

		return $product;
	}

	/**
	 * Converts the prices of a variable product to the specified currency.
	 *
	 * @param WC_Product_Variable product A variable product.
	 * @param string currency A currency code.
	 * @return WC_Product_Variable The product with converted prices.
	 */
	public function convert_variable_subscription_product_prices(WC_Product $product, $currency) {
		$product_children = $product->get_children();
		if(empty($product->children)) {
			return $product;
		}

		$variation_regular_prices = array();
		$variation_sale_prices = array();
		$variation_signup_prices = array();
		$variation_prices = array();

		$currencyprices_manager = $this->currencyprices_manager();
		foreach($product->children as $variation_id) {
			$variation = $this->load_subscription_variation_in_currency($variation_id, $currency);

			if(empty($variation)) {
				continue;
			}

			$variation_regular_prices[] = $variation->regular_price;
			$variation_sale_prices[] = $variation->sale_price;
			$variation_signup_prices[] = $variation->subscription_sign_up_fee;
			$variation_prices[] = $variation->price;
		}

		$product->min_variation_regular_price = $currencyprices_manager->get_min_value($variation_regular_prices);
		$product->max_variation_regular_price = $currencyprices_manager->get_max_value($variation_regular_prices);

		$product->min_variation_sale_price = $currencyprices_manager->get_min_value($variation_sale_prices);
		$product->max_variation_sale_price = $currencyprices_manager->get_max_value($variation_sale_prices);

		$product->min_variation_price = $currencyprices_manager->get_min_value($variation_prices);
		$product->max_variation_price = $currencyprices_manager->get_max_value($variation_prices);

		$product->min_subscription_sign_up_fee = $currencyprices_manager->get_min_value($variation_signup_prices);
		$product->max_subscription_sign_up_fee = $currencyprices_manager->get_max_value($variation_signup_prices);

		$product->subscription_price = $product->min_variation_price;
		$product->price = $product->subscription_price;
		$product->subscription_sign_up_fee = $product->min_subscription_sign_up_fee;

		if(!isset($product->max_variation_period)) {
			$product->max_variation_period = '';
		}
		if(!isset($product->max_variation_period_interval)) {
			$product->max_variation_period_interval = '';
		}

		//var_dump($product);

		return $product;
	}

	/**
	 * Converts the product prices of a variation.
	 *
	 * @param WC_Product_Variation $product A product variation.
	 * @param string currency A currency code.
	 * @return WC_Product_Variation The variation with converted prices.
	 */
	public function convert_subscription_variation_product_prices(WC_Product_Subscription_Variation $product, $currency) {
		$product = $this->convert_to_currency($product,
																					$currency,
																					$this->currencyprices_manager()->get_variation_regular_prices($product->variation_id),
																					$this->currencyprices_manager()->get_variation_sale_prices($product->variation_id),
																					$this->get_subscription_variation_signup_prices($product->variation_id));

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
	public function load_subscription_variation_in_currency($variation_id, $currency) {
		$variation = new WC_Product_Subscription_Variation($variation_id);
		if(empty($variation)) {
			return false;
		}

		$variation = $this->convert_product_prices($variation, $currency);

		return $variation;
	}

	/**
	 * Converts the price of a subscription before it's used by WooCommerce.
	 *
	 * @param float subscription_price The original price of the subscription.
	 * @param WC_Subscription_Product product The subscription product.
	 * @return float
	 */
	public function woocommerce_subscriptions_product_price($subscription_price, $product) {
		$product = $this->convert_product_prices($product);
		return $product->subscription_price;
	}

	/**
	 * Returns a subscription signup fee, converted into the active currency.
	 *
	 * @param float subscription_sign_up_fee The original subscription signup fee.
	 * @param WC_Subscription_Product product The subscription product.
	 * @return float
	 */
	public function woocommerce_subscriptions_product_sign_up_fee($subscription_sign_up_fee, $product) {
		$product = $this->convert_product_prices($product);

		return $product->subscription_sign_up_fee;
	}

	/**
	 * Processes the string inclusions associated with a product, removing and/or
	 * converting them appropriately.
	 *
	 * @param array inclusions The inclusions to be processed.
	 * @param WC_Subscription_Product product The subscription product.
	 * @return array
	 *
	 * @deprecated since 1.1.5.140331-beta
	 */
	//public function woocommerce_subscriptions_product_price_string_inclusions($inclusions, $product) {
	//	// Remove formatted price. The Subscriptions plugin will re-format it,
	//	// using the converted price
	//	if(isset($inclusions['price'])) {
	//		unset($inclusions['price']);
	//	}
	//
	//	return $inclusions;
	//}

	/**
	 * Returns the path where the Admin Views can be found.
	 *
	 * @return string
	 */
	protected function admin_views_path() {
		return WC_Aelia_CS_Subscriptions_Plugin::plugin_path() . '/views/admin';
	}

	/**
	 * Loads (includes) a View file.
	 *
	 * @param string view_file_name The name of the view file to include.
	 */
	private function load_view($view_file_name) {
		$file_to_load = $this->admin_views_path() . '/' . $view_file_name;

		if(!empty($file_to_load) && is_readable($file_to_load)) {
			include($file_to_load);
		}
	}

	/**
	 * Event handler fired when a subscription is being saved. It processes and
	 * saves the Currency Prices associated with the subscription.
	 *
	 * @param int post_id The ID of the Post (subscription) being saved.
	 */
	public function woocommerce_process_product_meta_subscription($post_id) {
		$subscription_signup_prices = $this->currencyprices_manager()->sanitise_currency_prices(get_value(self::FIELD_SIGNUP_FEE_CURRENCY_PRICES, $_POST));

		// D.Zanella - This code saves the subscription prices in the various currencies
		update_post_meta($post_id, self::FIELD_SIGNUP_FEE_CURRENCY_PRICES, json_encode($subscription_signup_prices));

		// Copy the currency prices from the fields dedicated to the variation inside the standard product fields
		$_POST[WC_Aelia_CurrencyPrices_Manager::FIELD_REGULAR_CURRENCY_PRICES] = $_POST[self::FIELD_REGULAR_CURRENCY_PRICES];
		$_POST[WC_Aelia_CurrencyPrices_Manager::FIELD_SALE_CURRENCY_PRICES] = $_POST[self::FIELD_SALE_CURRENCY_PRICES];


		$this->currencyprices_manager()->process_product_meta($post_id);
	}

	/**
	 * Event handler fired when a subscription is being saved. It processes and
	 * saves the Currency Prices associated with the subscription.
	 *
	 * @param int post_id The ID of the Post (subscription) being saved.
	 */
	public function woocommerce_process_product_meta_variable_subscription($post_id) {
		// Debug
		//var_dump($_POST);die();

		// Save the instance of the pricing manager to reduce calls to internal method
		$currencyprices_manager = $this->currencyprices_manager();

		// Retrieve all IDs, regular prices and sale prices for all variations. The
		// "all_" prefix has been added to easily distinguish these variables from
		// the ones containing the data of a single variation, whose names would
		// be otherwise very similar
		$all_variations_ids = get_value('variable_post_id', $_POST, array());
		$all_variations_signup_currency_prices = get_value(self::FIELD_VARIATION_SIGNUP_FEE_CURRENCY_PRICES, $_POST);

		// D.Zanella - This code saves the subscription prices for all variations in
		// the various currencies
		foreach($all_variations_ids as $variation_idx => $variation_id) {
			$variations_signup_currency_prices = $currencyprices_manager->sanitise_currency_prices(get_value($variation_idx, $all_variations_signup_currency_prices, null));
			update_post_meta($variation_id, self::FIELD_VARIATION_SIGNUP_FEE_CURRENCY_PRICES, json_encode($variations_signup_currency_prices));
		}

		// Copy the currency prices from the fields dedicated to the variation inside the standard product fields
		$_POST[WC_Aelia_CurrencyPrices_Manager::FIELD_VARIABLE_REGULAR_CURRENCY_PRICES] = $_POST[self::FIELD_VARIATION_REGULAR_CURRENCY_PRICES];
		$_POST[WC_Aelia_CurrencyPrices_Manager::FIELD_VARIABLE_SALE_CURRENCY_PRICES] = $_POST[self::FIELD_VARIATION_SALE_CURRENCY_PRICES];

		$currencyprices_manager->woocommerce_process_product_meta_variable($post_id);
	}

	/**
	 * Alters the view used to allow entering prices manually, in each currency.
	 *
	 * @param string file_to_load The view/template file that should be loaded.
	 * @return string
	 */
	public function woocommerce_product_options_general_product_data() {
		$this->load_view('simplesubscription_currencyprices_view.php');
	}

	/**
	 * Loads the view that allows to set the prices for a subscription variation.
	 *
	 * @param string file_to_load The original file to load.
	 * @return string
	 */
	public function woocommerce_product_after_variable_attributes() {
		$this->load_view('subscriptionvariation_currencyprices_view.php');
	}

	/**
	 * Intercepts the recalculation of the cart, ensuring that subscriptions
	 * subtotals are calculated correctly.
	 */
	public function wc_aelia_currencyswitcher_recalculate_cart_totals_before() {
		if(!WC_Subscriptions_Cart::cart_contains_subscription() &&
			 !WC_Subscriptions_Cart::cart_contains_subscription_renewal()) {
			// Cart doesn't contain subscriptions
			return;
		}

		// If cart contains subscriptions, force the full recalculation of totals and
		// subtotals. This is required for the Subscriptions plugin to recalculate
		// the subtotal in the mini-cart and display the correct amounts
		if(!defined('WOOCOMMERCE_CART')) {
			define('WOOCOMMERCE_CART', true);
		}
	}

	/**
	 * Adds coupon types related to subscriptions, which should be converted into
	 * the selected currency when used.
	 *
	 * @param array coupon_types The original array of coupon types passed by the
	 * Currency Switcher.
	 * @return array
	 */
	public function wc_aelia_cs_coupon_types_to_convert($coupon_types) {
		$coupon_types[] = 'sign_up_fee';
		$coupon_types[] = 'recurring_fee';

		return $coupon_types;
	}
}
