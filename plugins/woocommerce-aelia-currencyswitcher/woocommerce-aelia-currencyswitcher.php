<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

//define('SCRIPT_DEBUG', 1);
//error_reporting(E_ALL);

/*
Plugin Name: Aelia Currency Switcher for WooCommerce
Plugin URI: http://dev.pathtoenlightenment.net/shop
Description: WooCommerce Currency Switcher. Allows to switch currency on the fly and perform all transactions in such currency.
Author: Aelia (Diego Zanella)
Author URI: http://dev.pathtoenlightenment.net
Version: 3.4.5.140717
License: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
*/

/**
 * Checks if WooCommerce plugin is active, either for the single site or, in
 * case of WPMU, for the whole network.
 *
 * @return bool
 */
function is_woocommerce_plugin_active() {
	$woocommerce_plugin_key = 'woocommerce/woocommerce.php';
	$result = in_array($woocommerce_plugin_key, get_option('active_plugins'));

	if(!$result && function_exists('is_multisite') && is_multisite()) {
		$result = array_key_exists($woocommerce_plugin_key, get_site_option('active_sitewide_plugins'));
	}

	return $result;
}

if(!is_woocommerce_plugin_active()) {
	return;
}

if(class_exists('WC_Aelia_CurrencySwitcher')) {
	return;
}

define('AELIA_CS_PLUGIN_PATH', dirname(__FILE__));

// Load plugin's definitions
require_once('lib/general_functions.php');
require_once('lib/wc-aelia-currencyswitcher-defines.php');

interface IWC_Aelia_CurrencySwitcher {
	public function woocommerce_get_price($price, $product = null);
	public function woocommerce_currency($currency);
}

/**
 * Allows to display prices and accept payments in multiple currencies.
 */
class WC_Aelia_CurrencySwitcher implements IWC_Aelia_CurrencySwitcher {
	// @var string The plugin version
	const VERSION = '3.4.5.140717';

	// @var string The plugin slug
	public static $plugin_slug = AELIA_CS_PLUGIN_SLUG;
	public static $text_domain = AELIA_CS_PLUGIN_TEXTDOMAIN;

	// @var string The key used to register the plugin in WordPress (obsolete)
	const INSTANCE_KEY = 'wc_aelia_currencyswitcher';

	// @var WP_Error Holds the error messages registered by the plugin
	private $_wp_error;
	// @var WC_Aelia_CurrencySwitcher_Settings The object that handles plugin's settings.
	private $_settings_controller;
	// @var WC_Aelia_Reporting_Manager The object that handles the recalculations needed for reporting
	private $_reporting_manager;
	// @var WC_Aelia_CS_Admin_Interface_Manager The object that handles the rendering of the admin interface components
	private $_admin_interface_manager;
	// @var WC_Aelia_CurrencyPrices_Manager The object that handles Currency Prices for the Products.
	private $_currencyprices_manager;

	// @var array Holds a list of integration classes that add or improve support for 3rd party plugins and themes
	private $_integration_classes = array();

	// @var array Holds a list of the errors related to missing requirements
	public static $requirements_errors = array();

	// @var array A list of orders corresponding to item IDs. Used to retrieve the order ID starting from one of the items it contains.
	private $items_orders = array();

	// @var int The amount of decimals to use when formatting prices.
	protected $price_decimals = null;

	/**
	 * Adds a message to the log.
	 *
	 * @param string message The message to log.
	 * @param bool is_debug_msg Indicates if the message should only be logged
	 * while debug mode is true.
	 */
	protected function log($message, $debug = false) {
		LoggerWrapper::log($message, $debug);
	}


	/**
	 * Returns global instance of woocommerce.
	 *
	 * @return object The global instance of woocommerce.
	 * @deprecated since 3.3
	 */
	private function woocommerce() {
		return $this->wc();
	}

	/**
	 * Returns global instance of woocommerce.
	 *
	 * @return object The global instance of woocommerce.
	 * @since 3.3
	 */
	protected function wc() {
		global $woocommerce;
		return $woocommerce;
	}

	/**
	 * Returns the instance of the Settings Controller used by the plugin.
	 *
	 * @return WC_Aelia_CurrencySwitcher_Settings.
	 */
	public function settings_controller() {
		return $this->_settings_controller;
	}

	/**
	 * Returns the instance of the plugin.
	 *
	 * @return WC_Aelia_CurrencySwitcher.
	 */
	public static function instance() {
		return $GLOBALS[WC_Aelia_CurrencySwitcher::$plugin_slug];
	}

	/**
	 * Returns the Settings Controller used by the plugin.
	 *
	 * @return WC_Aelia_CurrencySwitcher_Settings.
	 */
	public static function settings() {
		return self::instance()->settings_controller();
	}

	/**
	 * Returns the decimal separator used by WooCommerce. This is a wrapper used
	 * for convenience.
	 *
	 * @return string
	 * @see WC_Aelia_CurrencySwitcher_Settings::decimal_separator().
	 */
	protected function decimal_separator() {
		return $this->settings_controller()->decimal_separator();
	}

	/**
	 * Returns the thousand separator used by WooCommerce. This is a wrapper used
	 * for convenience.
	 *
	 * @return string
	 * @see WC_Aelia_CurrencySwitcher_Settings::thousand_separator().
	 */
	protected function thousand_separator() {
		return $this->settings_controller()->thousand_separator();
	}

	/**
	 * Returns the amount of decimals used by WooCommerce for prices.
	 *
	 * @param string currency A currency code.
	 * @return int
	 * @see WC_Aelia_CurrencySwitcher_Settings::price_decimals().
	 */
	protected function price_decimals($currency = null) {
		$currency = empty($currency) ? $this->get_selected_currency() : $currency;

		return $this->settings_controller()->price_decimals($currency);
	}

	/**
	 * Formats a raw price using WooCommerce settings.
	 *
	 * @param float raw_price The price to format.
	 * @param string currency The currency code. If empty, currently selected
	 * currency is taken.
	 * @return string
	 */
	public function format_price($raw_price, $currency = null) {
		// Prices may be left empty. In such case, there's no need to format them
		if(!is_numeric($raw_price)) {
			return $raw_price;
		}

		if(empty($currency)) {
			$currency = $this->get_selected_currency();
		}
		$price = number_format($raw_price,
													 $this->price_decimals($currency),
													 $this->decimal_separator(),
													 $this->thousand_separator());

		if((get_option('woocommerce_price_trim_zeros') == 'yes') &&
			 ($this->price_decimals() > 0)) {
			$price = $this->trim_zeroes($price);
		}

		$currency_symbol = get_woocommerce_currency_symbol($currency);

		return '<span class="amount">' . sprintf(get_woocommerce_price_format(), $currency_symbol, $price) . '</span>';
	}

	/**
	 * Trims the trailing zeroes from a formatted floating point value.
	 *
	 * @param string value The falue to format.
	 * @param string decimal_separator The decimal separator.
	 * @return string
	 */
	protected function trim_zeroes($value, $decimal_separator = null) {
		if(empty($decimal_separator)) {
			$decimal_separator = $this->decimal_separator();
		}

		$trim_regex = '/' . preg_quote($decimal_separator, '/') . '0++$/';
		return preg_replace($trim_regex, '', $value);
	}

	/**
	 * Converts a float to a string without locale formatting which PHP adds when
	 * changing floats to strings.
	 *
	 * @param float value The value to convert.
	 * @return string
	 */
	public function float_to_string($value) {
		if(!is_float($value)) {
			return $value;
		}

		$locale = localeconv();
		$string = strval($value);
		$string = str_replace($locale['decimal_point'], '.', $string);

		return $string;
	}

	/**
	 * Registers an error message in the internal WP_Error object.
	 *
	 * @param mixed error_code The Error Code.
	 * @param string error_message The Error Message.
	 */
	private function add_error_message($error_code, $error_message) {
		$this->_wp_error->add($error_code, $error_message);
	}

	/**
	 * Retrieves an error message from the internal WP_Error object.
	 *
	 * @param mixed error_code The Error Code.
	 * @return string The Error Message corresponding to the specified Code.
	 */
	public function get_error_message($error_code) {
		return $this->_wp_error->get_error_message($error_code);
	}

	/**
	 * Loads all the error message used by the plugin.
	 */
	private function load_error_messages() {
		$this->add_error_message(AELIA_CS_ERR_FILE_NOT_FOUND, __('File not found: "%s".', AELIA_CS_PLUGIN_TEXTDOMAIN));
		$this->add_error_message(AELIA_CS_ERR_INVALID_CURRENCY, __('Currency not valid: "%s".', AELIA_CS_PLUGIN_TEXTDOMAIN));
		$this->add_error_message(AELIA_CS_ERR_MISCONFIGURED_CURRENCIES,
														 __('One or more Currencies are not configured correctly (e.g. ' .
																'Exchange Rates may be missing, or set to zero). Please ' .
																'check Currency Switcher Options and ensure that all enabled ' .
																'Currencies have been configured correctly. If the problem ' .
																'persists, please contact Support.',
																AELIA_CS_PLUGIN_TEXTDOMAIN));
		$this->add_error_message(AELIA_CS_ERR_INVALID_SOURCE_CURRENCY,
														 __('Currency Conversion - Source Currency not valid or exchange rate ' .
																'not found for: "%s". Please make sure that the Currency '.
																'Switcher plugin is configured correctly and that an Exchange ' .
																'Rate has been specified for each of the enabled currencies.',
																AELIA_CS_PLUGIN_TEXTDOMAIN));
		$this->add_error_message(AELIA_CS_ERR_INVALID_DESTINATION_CURRENCY,
														 __('Currency Conversion - Destination Currency not valid: "%s" or exchange rate' .
																'not found for: "%s". Please make sure that the Currency '.
																'Switcher plugin is configured correctly and that an Exchange ' .
																'Rate has been specified for each of the enabled currencies.',
																AELIA_CS_PLUGIN_TEXTDOMAIN));
		$this->add_error_message(AELIA_CS_ERR_INVALID_TEMPLATE,
														 __('Rendering - Requested template could not be found in either plugin\'s ' .
																'folders, nor in your theme. Plugin slug: "%s". Template name: "%s".'.
																AELIA_CS_PLUGIN_TEXTDOMAIN));
	}

	/**
	 * Loads the class that will handle prices in different currencies for each
	 * product.
	 */
	private function load_currencyprices_manager() {
		$this->_currencyprices_manager = WC_Aelia_CurrencyPrices_Manager::Instance();
	}

	/**
	 * Loads additional classes that implement integration with 3rd party
	 * plugins and themes.
	 */
	private function load_integration_classes() {
		$this->_integration_classes = array(
			'wc_cart_notices' => new WC_Aelia_CS_Cart_Notices_Integration(),
			'wc_dynamic_pricing' => new WC_Aelia_CS_Dynamic_Pricing_Integration(),
			'wc_kissmetrics' => new WC_Aelia_KISSMetrics_Integration(),
			'wc_bundles' => new WC_Aelia_CS_Bundles_Integration(),
		);
	}

	/**
	 * Loads the class that will handle reporting calls to recalculate sales
	 * totals in Base currency.
	 */
	private function load_reporting_manager() {
		$this->_reporting_manager = new WC_Aelia_Reporting_Manager();
	}

	/**
	 * Loads the components and widgets that will be applied to the admin
	 * interface.
	 */
	private function load_admin_interface_manager() {
		$this->_admin_interface_manager = new WC_Aelia_CS_Admin_Interface_Manager();
	}

	/**
	 * Triggers an error displaying the message associated to an error code.
	 *
	 * @param mixed error_code The Error Code.
	 * @param int error_type The type of Error to raise.
	 * @param array error_args An array of arguments to pass to the vsprintf()
	 * function which will format the error message.
	 * @return string The formatted error message.
	 */
	private function trigger_error($error_code, $error_type = E_NOTICE, array $error_args = array()) {
		$error_message = $this->get_error_message($error_code);

		return trigger_error(vsprintf($error_message, $error_args), $error_type);
	}

	/**
	 * Returns the Exchange Rate to convert the default woocommerce currency into
	 * the one currently selected by the User.
	 *
	 * @param string selected_currency The code of the Currency selected by the
	 * User.
	 * @return double The Exchange Rate to convert the default woocommerce
	 * currency into the one currently selected by the User.
	 */
	private function _get_exchange_rate($selected_currency) {
		// Retrieve exchange rates from the configuration
		$exchange_rates = $this->_settings_controller->get_exchange_rates();

		$result = get_value($selected_currency, $exchange_rates);
		if(empty($result)) {
			$this->trigger_error(AELIA_CS_ERR_INVALID_CURRENCY, E_USER_WARNING, array($selected_currency));
		}

		return $result;
	}

	/**
	 * Converts a product price in the currently selected currency.
	 *
	 * @param double price The original product price.
	 * @param WC_Product product The product to process.
	 * @return double The price, converted in the currency selected by the User.
	 */
	public function woocommerce_get_price($price, $product = null) {
		$product = $this->convert_product_prices($product);

		return $product->price;
	}

	/**
	 * Converts all the prices of a given product in the currently selected
	 * currency.
	 *
	 * @param WC_Product product The product whose prices should be converted.
	 * @return WC_Product
	 */
	protected function convert_product_prices($product) {
		$selected_currency = $this->get_selected_currency();
		$base_currency = self::settings()->base_currency();

		if(get_value('currency', $product) != $selected_currency) {
			$product = $this->_currencyprices_manager->convert_product_prices($product, $selected_currency);
			$product->currency = $selected_currency;
		}

		return $product;
	}

	/**
	 * Returns the Exchange Rate currently applied, based on selected currency.
	 *
	 * @return float An exchange rate.
	 */
	public function current_exchange_rate() {
		return $this->_get_exchange_rate($this->get_selected_currency());
	}

	/**
	 * Converts an amount from a Currency to another.
	 *
	 * @param float amount The amount to convert.
	 * @param string from_currency The source Currency.
	 * @param string to_currency The destination Currency.
	 * @param int price_decimals The amount of decimals to use when rounding the
	 * converted result.
	 * @param bool include_markup Indicates if the exchange rates used for conversion
	 * should include the markup (if one was specified).
	 * @return float The amount converted in the destination currency.
	 */
	public function convert($amount, $from_currency, $to_currency, $price_decimals = null, $include_markup = true) {
		// No need to try converting an amount that is not numeric. This can happen
		// quite easily, as "no value" is passed as an empty string
		if(!is_numeric($amount)) {
			return $amount;
		}

		// No need to spend time converting a currency to itself
		if($from_currency == $to_currency) {
			return $amount;
		}

		if(empty($price_decimals)) {
			$price_decimals = $this->price_decimals($to_currency);
		}

		// Retrieve exchange rates from the configuration
		$exchange_rates = $this->_settings_controller->get_exchange_rates($include_markup);
		//var_dump($exchange_rates);

		try {
			$from_currency_rate = get_value($from_currency, $exchange_rates, null);
			if(empty($from_currency_rate)) {
				throw new InvalidArgumentException(sprintf($this->get_error_message(AELIA_CS_ERR_INVALID_SOURCE_CURRENCY),
																									 $from_currency));
			}

			$to_currency_rate = get_value($to_currency, $exchange_rates, null);
			if(empty($to_currency_rate)) {
				throw new InvalidArgumentException(sprintf($this->get_error_message(AELIA_CS_ERR_INVALID_DESTINATION_CURRENCY),
																									 $to_currency));
			}

			$exchange_rate = $to_currency_rate / $from_currency_rate;
		}
		catch(Exception $e) {
			$full_message = $e->getMessage() .
											sprintf(__('Stack trace: %s', AELIA_CS_PLUGIN_TEXTDOMAIN),
															$e->getTraceAsString());
			trigger_error($full_message, E_USER_ERROR);
		}

		return round($amount * $exchange_rate, $price_decimals);
	}

	/**
	 * Returns a value indicating if user is currently paying for an order.
	 *
	 * @return bool
	 */
	protected function user_is_paying() {
		global $post;

		$paying_for_order = get_value('pay_for_order', $_GET, false);
		$current_page_id = get_value('ID', $post, false);
		$payment_page_id = woocommerce_get_page_id('pay');
		//var_dump("CURRENT PAGE ID: " . $post->ID);
		//var_dump("WC PAY PAGE ID: " . woocommerce_get_page_id('pay'));

		// As of WooCommerce 2.0.14, checking if we are on the "pay" page is the only
		// way to determine if the user is paying for an order
		return ($paying_for_order != false) || ($current_page_id == $payment_page_id);
	}

	/**
	 * Overrides the currency symbol by loading the one configured in the settings.
	 *
	 * @param string currency_symbol The symbol passed by WooCommerce.
	 * @param string currency The currency for which the symbol is requested.
	 * @return string
	 */
	public function woocommerce_currency_symbol($currency_symbol, $currency) {
		if(defined('AELIA_CS_SETTINGS_PAGE')) {
			return $currency_symbol;
		}

		return self::settings()->get_currency_symbol($currency, $currency_symbol);
	}

	/**
	 * Adds more currencies to the list of the available ones.
	 *
	 * @param array currencies The list of currencies passed by WooCommerce.
	 * @return array
	 */
	public function woocommerce_currencies($currencies) {
		return array_merge(WC_Aelia_Currencies_Manager::world_currencies(), $currencies);
	}

	/**
	 * Returns the currently selected currency.
	 *
	 * @param string currency The currency used by default by WooCommerce.
	 * @return string The symbol of the currency selected by the User.
	 */
	public function woocommerce_currency($currency) {
		// If user is paying for a previously placed, but unpaid, order, then we have
		// to return the currency in which the order was placed
		if(isset($this->wc()->session)) {
			$order_id = $this->wc()->session->order_awaiting_payment;
			if(is_numeric($order_id) &&
				 $this->user_is_paying()) {
				$order = new Aelia_Order($order_id);
				$order_currency = $order->get_order_currency();

				if($this->is_valid_currency($order_currency)) {
					return $order_currency;
				}
			}
		}

		$selected_currency = $this->get_selected_currency();
		//var_dump($selected_currency);

		return $selected_currency;
	}

	/**
	 * Replaces the currency within an HTML element with the one associated with
	 * an order. This method is invoked before displaying an order summary, to
	 * replace the default WooCommerce currency that is normally displayed with
	 * the currency in which the Order was placed.
	 *
	 * @param string formatted_value The HTML containing the currency to replace.
	 * @param WC_Order The WooCommerce order from which to retrieve the currency.
	 * @return string The HTML received as an input, with its currency replaced
	 * with the one used for the Order.
	 */
	private function _display_with_order_currency($formatted_value, $order) {
		//var_dump($subtotal, $item, $order, get_woocommerce_currency_symbol());
		$order = new Aelia_Order($order->id);
		$order_currency = $order->get_order_currency();

		return str_replace(get_woocommerce_currency_symbol(), get_woocommerce_currency_symbol($order_currency), $formatted_value);
	}

	/**
	 * Displays the Order Currency for a line of Order details.
	 *
	 * @param string subtotal The Order Line subtotal, already formatted as HTML.
	 * @param object Item An object describing the product in the Order Line.
	 * @param WC_Order The Order that is being processed.
	 * @return string The subtotal HTML, displaying the Order Currency.
	 * @see WC_Order::get_formatted_line_subtotal()
	 */
	public function format_order_line($subtotal, $item, $order) {
		return $this->_display_with_order_currency($subtotal, $order);
	}

	/**
	 * Displays the Order Currency for a line of Order SubTotal.
	 *
	 * @param string subtotal The Order Subtotal, already formatted as HTML.
	 * @param bool compound A flag indicating if the total is compound.
	 * @param WC_Order The Order that is being processed.
	 * @return string The subtotal HTML, displaying the Order Currency.
	 * @see WC_Order::get_subtotal_to_display()
	 */
	public function format_order_subtotal($subtotal, $compound, $order) {
		return $this->_display_with_order_currency($subtotal, $order);
	}

	/**
	 * Converts the price for a simple Products on sale. With sales, the regular
	 * price is passed "as-is" and it doesn't get converted into currency.
	 *
	 * @param string sale_price_html The HTML snippet containing a Product's regular
	 * price and sale price.
	 * @param WC_Product product The product being displayed.
	 * @return string The HTML snippet with the sale price converted into
	 * currently selected Currency.
	 */
	public function woocommerce_sale_price_html($sale_price_html, $product) {
		$product = $this->convert_product_prices($product);

		// WC2.1 displays prices differently, depending if they already include tax
		// and if they should be displayed with or without tax
		if(version_compare($this->wc()->version, '2.1', '>=')) {
			$regular_price_in_currency = $this->_currencyprices_manager->process_product_price_tax($product, $product->regular_price);
			$sale_price_in_currency = $this->_currencyprices_manager->process_product_price_tax($product, $product->get_price());
		}
		else {
			$regular_price_in_currency = $product->regular_price;
			$sale_price_in_currency = $product->get_price();
		}

		$regular_price_in_currency = $this->format_price($regular_price_in_currency);
		if($sale_price_in_currency <= 0) {
			$sale_price_in_currency = __('Free!', 'woocommerce');
		} else{
			$sale_price_in_currency = $this->format_price($sale_price_in_currency);
		}

		return '<del>' . $regular_price_in_currency . '</del> <ins>' . $sale_price_in_currency . '</ins>' . $this->get_price_suffix($product);
	}

	/**
	 * Converts the price for a variable Products on sale. With sales, the regular
	 * price is passed "as-is" and it doesn't get converted into currency.
	 *
	 * @param string sale_price_html The HTML snippet containing a Product's regular
	 * price and sale price.
	 * @param WC_Product product The product being displayed.
	 * @return string The HTML snippet with the sale price converted into
	 * currently selected Currency.
	 */
	public function woocommerce_variable_sale_price_html($sale_price_html, $product) {
		$product = $this->convert_product_prices($product);

		// WC2.1 displays prices differently, depending if they already include tax
		// and if they should be displayed with or without tax
		if(version_compare($this->wc()->version, '2.1', '>=')) {
			$min_regular_price_in_currency = $this->_currencyprices_manager
				->process_variation_price_tax($product->min_variation_regular_price,
																			$product,
																			'min',
																			true);
			$min_sale_price_in_currency = $this->_currencyprices_manager
				->process_variation_price_tax($product->min_variation_sale_price,
																			$product,
																			'min',
																			true);
		}
		else {
			$min_regular_price_in_currency = $product->min_variation_regular_price;
			$min_sale_price_in_currency = $product->get_price();
		}

		$min_regular_price_in_currency = $this->format_price($min_regular_price_in_currency);

		if($min_sale_price_in_currency <= 0) {
			$min_sale_price_in_currency = __('Free!', 'woocommerce');
		} else{
			$min_sale_price_in_currency = $this->format_price($min_sale_price_in_currency);
		}

		return '<del>' . $min_regular_price_in_currency . '</del> <ins>' . $min_sale_price_in_currency . '</ins>' . $this->get_price_suffix($product);
	}

	/**
	 * Overrides the number of decimals used to format prices.
	 *
	 * @param int decimals The number of decimals passed by WooCommerce.
	 * @return int
	 */
	public function pre_option_woocommerce_price_num_decimals($decimals) {
		if(empty($this->price_decimals)) {
			$this->price_decimals = $this->price_decimals($this->get_selected_currency());
		}
		return $this->price_decimals;
	}

	/**
	 * Converts the price for a Product Variation.
	 *
	 * @param string sale_price_html The HTML snippet containing a Product's regular
	 * price and sale price.
	 * @param WC_Product product The product being displayed.
	 * @return string The HTML snippet with the prices converted into currently
	 * selected Currency.
	 */
	public function woocommerce_variation_price_html($price_html, $product) {
		//var_dump($product, $product->regular_price, $product->sale_price);
		$product = $this->convert_product_prices($product);
		$regular_price_in_currency = $this->format_price($product->regular_price);

		return $regular_price_in_currency . $this->get_price_suffix($product);
	}

	/**
	 * Converts the price for a Product Variation on sale.
	 *
	 * @param string sale_price_html The HTML snippet containing a Product's regular
	 * price and sale price.
	 * @param WC_Product product The product being displayed.
	 * @return string The HTML snippet with the prices converted into currently
	 * selected Currency.
	 */
	public function woocommerce_variation_sale_price_html($sale_price_html, $product) {
		//var_dump($product, $product->regular_price, $product->sale_price);
		$product = $this->convert_product_prices($product);
		$regular_price_in_currency = $this->format_price($product->regular_price);
		$sale_price_in_currency = $product->sale_price;
		if($sale_price_in_currency <= 0) {
			$sale_price_in_currency = __('Free!', 'woocommerce');
		} else{
			$sale_price_in_currency = $this->format_price($sale_price_in_currency);
		}

		return '<del>' . $regular_price_in_currency . '</del> <ins>' . $sale_price_in_currency . '</ins>' . $this->get_price_suffix($product);
	}

	/**
	 * Adds more scheduling options to WordPress Cron.
	 *
	 * @param array schedules Existing Cron scheduling options.
	 */
	public function set_cron_schedules($schedules) {
		// Adds "weekly" to the existing schedules
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __('Weekly', AELIA_CS_PLUGIN_TEXTDOMAIN),
		);
		// Adds "monthly" to the existing schedules
		$schedules['monthly'] = array(
			'interval' => 2592000,
			'display' => __('Monthly (every 30 days)', AELIA_CS_PLUGIN_TEXTDOMAIN),
		);
		return $schedules;
	}

	/**
	 * Displays the Order Currency for a line of Order Total.
	 *
	 * @param string total The Order Total, already formatted as HTML.
	 * @param WC_Order The Order that is be_shing processed.
	 * @return string The total HTML, displaying the Order Currency.
	 * @see WC_Order::get_formatted_order_total()
	 */
	public function format_order_total($total, $order) {
		return $this->_display_with_order_currency($total, $order);
	}

	/**
	 * Returns the string that indicates the shipping applied with an order. This
	 * method is an almost exact copy of WC_Order::get_shipping_to_display().
	 *
	 * @param WC_Order order The WooCommerce order.
	 * @param string tax_display Purpose unknown, undocumented in original
	 * file and never passed when method is called.
	 * @return string
	 *
	 * @see WC_Order::get_shipping_to_display().
	 */
	public function get_shipping_to_display($order, $tax_display = '') {
		if(empty($tax_display)) {
			$tax_display = $order->tax_display_cart;
		}

		$order = new Aelia_Order($order->id);
		if($order->order_shipping > 0) {
			$order_currency = $order->get_order_currency();
			$tax_text = '';

			if($tax_display == 'excl') {
				// Show shipping excluding tax
				$shipping = $this->format_price($order->order_shipping, $order_currency);

				if(($order->order_shipping_tax > 0) &&
					 ($order->prices_include_tax)) {
					$tax_text = $this->wc()->countries->ex_tax_or_vat() . ' ';
				}
			}
			else {
				// Show shipping including tax
				$shipping = $this->format_price($order->order_shipping + $order->order_shipping_tax, $order_currency);

				if(($order->order_shipping_tax > 0) &&
					 (!$order->prices_include_tax)) {
					$tax_text = $this->wc()->countries->inc_tax_or_vat() . ' ';
				}
			}

			$shipping .= sprintf(__('&nbsp;<small>%svia %s</small>', 'woocommerce'), $tax_text, $order->get_shipping_method());
		} elseif($order->get_shipping_method()) {
			$shipping = $order->get_shipping_method();
		} else {
			$shipping = __('Free!', 'woocommerce');
		}

		return $shipping;
	}

	/**
	 * Reformats Shipping Price printed on Order Receipts by replacing the
	 * currency symbol with the one for the currency used to place the order.
	 *
	 * @param string shipping_html The original HTML containing the shipping.
	 * @param WC_Order order The order for which the receipt is being generated.
	 * @return string The reformatted shipping HTML.
	 */
	public function woocommerce_order_shipping_to_display($shipping_html, $order) {
		return $this->get_shipping_to_display($order);
	}

	/**
	 * Reformats Tax Totals printed on Order Receipts by replacing the
	 * currency symbol with the one for the currency used to place the order.
	 *
	 * @param array tax_totals An array of Tax totals.
	 * @param WC_Order order The order for which the receipt is being generated.
	 * @return array
	 */
	public function woocommerce_order_tax_totals($tax_totals, $order) {
		$order = new Aelia_Order($order->id);
		$order_currency = $order->get_order_currency();

		// No need to re-format if if the currency in use is the base one
		if($order_currency == self::settings()->base_currency()) {
			return $tax_totals;
		}

		foreach($tax_totals as $tax_id => $tax_details) {
			$tax_amount = get_value('amount', $tax_details);
			if(is_numeric($tax_amount)) {
				$tax_details->formatted_amount = $this->format_price($tax_amount, $order_currency);
			}
		}

		return $tax_totals;
	}

	/**
	 * Fired after an order is saved. It checks that the order currency has been
	 * stored against the post, adding it if it's missing. This method is needed
	 * because, for some reason, WooCommerce does not store the order currency when
	 * an order is created from the backend.
	 *
	 * @param int post_id The post (order) ID.
	 * @param WC_Order The order that has just been saved.
	 */
	public function woocommerce_process_shop_order_meta($post_id, $post) {
		$order = new Aelia_Order($post_id);

		// Check if order currency is saved. If not, set it to currently selected currency
		$order_currency = $order->get_order_currency();
		if(empty($order_currency)) {
			$order->set_order_currency($this->get_selected_currency());
		}
	}

	/**
	 * Loads the settings for the currency used when an order was placed. They
	 * will then be used to reconfigure the JavaScript used in Order edit page.
	 *
	 * @param int order_id The Order ID.
	 * @param array woocommerce_admin_params An array of parameters to pass to the
	 * admin scripts.
	 * @return array
	 */
	public function load_order_currency_settings($order_id, array $woocommerce_admin_params = array()) {
		// Add filter to retrieve the currently selected currency. This will be used
		// when creating a new order, to associate the proper currency to it
		add_filter('woocommerce_currency', array($this, 'woocommerce_currency'), 5);
		// Display prices with the amount of decimals configured for the active currency
		add_filter('pre_option_woocommerce_price_num_decimals', array($this, 'pre_option_woocommerce_price_num_decimals'), 10, 1);

		$order = new Aelia_Order($order_id);

		// Extract the currency from the order
		$order_currency = $order->get_order_currency();

		$woocommerce_writepanel_params = array(
			'currency_format_symbol' => get_woocommerce_currency_symbol($order_currency),
			// TODO Load the decimal places from the Currency Switcher settings
			// TODO Load the thousand separator from the Currency Switcher settings
		);

		$woocommerce_admin_params = array_merge($woocommerce_admin_params, $woocommerce_writepanel_params);
	}

	/**
	 * Loads the localization for the scripts in the Admin section.
	 *
	 * @param array woocommerce_admin_params An array of parameters to pass to the
	 * admin scripts.
	 */
	protected function localize_admin_scripts($woocommerce_admin_params) {
		wp_localize_script('wc-aelia-currency-switcher-admin-overrides',
											 'aelia_cs_woocommerce_writepanel_params',
											 $woocommerce_admin_params);
	}

	/**
	 * Sets several hooks that will take care of updating the Currency and its
	 * Exchange Rate when WooCommerce Ajax Functions are invoked. These hooks are
	 * needed because, for some reasons, Ajax calls are issued within the Admin
	 * environment (i.e. is_admin() returns true for an Ajax call) and the Currency
	 * should not be updated within the Admin section (i.e. when reviewing products,
	 * orders, and so on). This means that an Ajax call would be treated as an Admin
	 * page, and the currency would revert to the base one, without the hooks specified
	 * in this method.
	 */
	protected function set_frontend_ajax_hooks() {
		$callback = array($this, 'woocommerce_checkout_order_review');
		add_action('wp_ajax_nopriv_woocommerce_get_refreshed_fragments', $callback, 5);
		add_action('wp_ajax_woocommerce_get_refreshed_fragments', $callback, 5);
		add_action('wp_ajax_woocommerce_apply_coupon', $callback, 5);
		add_action('wp_ajax_nopriv_woocommerce_apply_coupon', $callback, 5);
		add_action('wp_ajax_woocommerce_update_shipping_method', $callback, 5);
		add_action('wp_ajax_nopriv_woocommerce_update_shipping_method', $callback, 5);
		add_action('wp_ajax_woocommerce_update_order_review', $callback, 5);
		add_action('wp_ajax_nopriv_woocommerce_update_order_review', $callback, 5);
		add_action('wp_ajax_woocommerce_add_to_cart', $callback, 5);
		add_action('wp_ajax_nopriv_woocommerce_add_to_cart', $callback, 5);
		add_action('wp_ajax_woocommerce-checkout', $callback, 5);
		add_action('wp_ajax_nopriv_woocommerce-checkout', $callback, 5);
		// Display prices with the amount of decimals configured for the active currency
		add_filter('pre_option_woocommerce_price_num_decimals', array($this, 'pre_option_woocommerce_price_num_decimals'), 10, 1);
	}

	/**
	 * Sets hooks related to discount coupons.
	 */
	protected function set_coupon_hooks() {
		add_action('woocommerce_coupon_loaded', array($this, 'wc_coupon_loaded'));
	}

	/**
	 * Sets hooks related to shipping methods.
	 */
	protected function set_shipping_methods_hooks() {
		add_filter('woocommerce_available_shipping_methods', array($this, 'woocommerce_available_shipping_methods'));
		add_filter('woocommerce_shipping_methods', array($this, 'woocommerce_shipping_methods'), 50);
	}

	/**
	 * Sets hooks related to scheduled tasks.
	 */
	protected function set_scheduled_tasks_hooks() {
		// Add hooks to automatically update Exchange Rates
		add_filter('cron_schedules', array($this, 'set_cron_schedules'));
		add_action($this->_settings_controller->exchange_rates_update_hook(),
							 array($this->_settings_controller, 'scheduled_update_exchange_rates'));
	}

	/**
	 * Sets hooks related to cart.
	 */
	protected function set_cart_hooks() {
		// Add the hooks to recalculate cart total when needed
		add_action('woocommerce_cart_contents_total', array($this, 'woocommerce_cart_contents_total'));
		add_action('woocommerce_before_cart_table', array($this, 'woocommerce_before_cart_table'), 10);
		add_filter('woocommerce_calculated_total', array($this, 'reset_recalculate_cart_flag'), 10, 2);
		add_filter('woocommerce_add_cart_item', array($this, 'woocommerce_add_cart_item'), 15, 1);
		add_filter('woocommerce_get_cart_item_from_session', array($this, 'woocommerce_get_cart_item_from_session'), 15, 3);

		//add_filter('woocommerce_view_order', array($this, 'view_order'));

		// Add the hook to be fired when cart is loaded from session
		add_action('woocommerce_cart_loaded_from_session', array($this, 'woocommerce_cart_loaded_from_session'), 1);
	}

	/**
	 * Sets hooks to register shortcodes.
	 */
	protected function set_shortcodes_hooks() {
		// Shortcode to render the currency selector
		add_shortcode('aelia_currency_selector_widget', array('WC_Aelia_CurrencySwitcher_Widget', 'render_currency_selector'));
		// Shortcode to render the billing country selector
		add_shortcode('aelia_cs_billing_country_selector_widget',
									array('Aelia\CurrencySwitcher\Billing_Country_Selector_Widget', 'render_billing_country_selector'));
	}

	/**
	 * Filters Post metadata being retrieved before it's returned to caller.
	 *
	 * @param mixed metadata The original metadata.
	 * @param int object_id The post ID.
	 * @param meta_key The metadata to be retrieved.
	 * @return mixed The metadata value.
	 */
	public function get_post_metadata($metadata, $object_id, $meta_key) {
		if(version_compare($this->wc()->version, '2.1', '>=')) {
			return $metadata;
		}

		// WooCommerce 2.0.x only
		// This method is only called during generation of "Sales overview" report
		// page. Order totals in base currency should be used for reporting.
		// NOTE: this method of returning the order totals in base currency is a hack,
		// but there is no other way to do it, because the "Sales overview" reports
		// don't call a hook that allows to alter the values, or the fields retrieved,
		// but they just call get_post_meta() for every order.
		// See file woocommerce-admin-reports.php, method woocommerce_sales_overview(),
		// line ~472.
		if($meta_key == '_order_total') {
			$meta_cache = update_meta_cache('post', array($object_id));
			$obj_cache = $meta_cache[$object_id];

			$order = new Aelia_Order($object_id);
			return $order->get_total_in_base_currency();
		}

		return $metadata;
	}

	/**
	 * Filters Post metadata being saved before it's returned to caller.
	 *
	 * @param mixed metadata The original metadata.
	 * @param int object_id The post ID.
	 * @param meta_key The metadata to be saved.
	 * @param meta_value The value to be saved.
	 * @return mixed The metadata value.
	 */
	public function update_post_metadata($metadata, $object_id, $meta_key, $meta_value) {
		// Convert  totals into base Currency (they are saved in the currency used
		// to complete the transaction)
		if(in_array($meta_key,
								array('_order_total',
											'_order_discount',
											'_cart_discount',
											'_order_shipping',
											'_order_tax',
											'_order_shipping_tax',
											)
								)
			 ) {

			$order = new Aelia_Order($object_id);

			// If Order Currency is empty, it means that we are in checkout phase.
			// WooCommerce saves the Order Currency AFTER the Order Total (a bit
			// nonsensical, but that's the way it is). In such case, we can take the
			// currency currently selected to place the Order and set it as the default
			$default_order_currency = get_woocommerce_currency();
			$order_currency = $order->get_order_currency($default_order_currency);

			// Save the amount in base currency. This will be used to correct the reports
			$amount_in_base_currency = $this->convert($meta_value,
																								$order_currency,
																								$this->settings_controller()->base_currency(),
																								null,
																								false);
			$order->set_meta($meta_key . '_base_currency', $amount_in_base_currency);
		}

		return $metadata;
	}

	/**
	 * Retrieves the order to which an order item belongs.
	 *
	 * @param int order_item_id The order item.
	 * @return Aelia_Order
	 */
	protected function get_order_from_item($order_item_id) {
		// Check if the order is stored in the internal list
		$order = get_value($order_item_id, $this->items_orders);
		if(empty($order)) {
			// Cache the order after retrieving it. This will reduce the amount of queries executed
			$this->items_orders[$order_item_id] = Aelia_Order::get_by_item_id($order_item_id);
		}

		return $this->items_orders[$order_item_id];
	}

	/**
	 * Adds line totals in base currency for each product in an order.
	 *
	 * @param null $check
	 * @param int $order_item_id The ID of the order item.
	 * @param string $meta_key The meta key being saved.
	 * @param mixed $meta_value The value being saved.
	 * @return null|bool
	 *
	 * @see update_metadata().
	 */
	public function update_order_item_metadata($check, $order_item_id, $meta_key, $meta_value) {
		// Convert line totals into base Currency (they are saved in the currency used
		// to complete the transaction)
		if(in_array($meta_key, array(
														'_line_subtotal',
														'_line_subtotal_tax',
														'_line_tax',
														'_line_total',
														'tax_amount',
														'shipping_tax_amount',
														)
								)
			 ) {

			$order = $this->get_order_from_item($order_item_id);

			if(empty($order->id)) {
				// An empty order id indicates that something is not right. Without it,
				// we cannot calculate the amounts in base currency
				$this->log(sprintf(__('Order not found for order item id %s. Calculation of ' .
															'metadata "%s" in base currency skipped.'),
													 $order_item_id,
													 $meta_key));
			}
			else {
				// Retrieve the order currency
				// If Order Currency is empty, it means that we are in checkout phase.
				// WooCommerce saves the Order Currency AFTER the Order Total (a bit
				// nonsensical, but that's the way it is). In such case, we can take the
				// currency currently selected to place the Order and set it as the default
				$default_order_currency = get_woocommerce_currency();
				$order_currency = $order->get_order_currency($default_order_currency);


				// Save the amount in base currency. This will be used to correct the reports
				$amount_in_base_currency = $this->convert($meta_value,
																									$order_currency,
																									$this->settings_controller()->base_currency(),
																									null,
																									false);
				$amount_in_base_currency = $this->float_to_string($amount_in_base_currency);
				// Update meta value with new string
				update_metadata('order_item', $order_item_id, $meta_key . '_base_currency', $amount_in_base_currency);
			}
		}

		return $check;
	}

	/**
	 * Add custom item metadata added by the plugin.
	 *
	 * @param array item_meta The original metadata to hide.
	 * @return array
	 */
	public function woocommerce_hidden_order_itemmeta($item_meta) {
		$custom_order_item_meta = array(
			'_line_subtotal_base_currency',
			'_line_subtotal_tax_base_currency',
			'_line_tax_base_currency',
			'_line_total_base_currency',
			'tax_amount_base_currency',
			'shipping_tax_amount_base_currency',
		);

		return array_merge($item_meta, $custom_order_item_meta);
	}

	/**
	 * Filters the available payment gateways based on the selected currency.
	 *
	 * @param array available_gateways A list of the available gateways to filter.
	 * @return array
	 */
	public function woocommerce_available_payment_gateways($available_gateways) {
		global $wp;

		$payment_currency = null;
		// If customer is paying for an existing order, take its currency
		if($this->user_is_paying() && is_numeric($order_id = get_value('order-pay', $wp->query_vars))) {
			// Debug
			//var_dump("PAYING ORDER " . $order_id);
			$order = new Aelia_Order($order_id);
			$payment_currency = $order->get_order_currency();
		}

		// If payment currency is empty, then customer is paying for a new order. In
		// such case, take the active currency
		if(empty($payment_currency)) {
			$payment_currency = $this->get_selected_currency();
		}

		// Debug
		//var_dump($payment_currency);

		$currency_gateways = self::settings()->currency_payment_gateways($payment_currency);

		// If no payment gateway has been enabled for a currency, it most probably
		// means that the Currency Switcher has not been configured properly. In such
		// case, return all payment gateways originally passed by WooCommerce, to
		// allow the Customer to complete the order.
		if(empty($currency_gateways)) {
			return $available_gateways;
		}

		//var_dump($currency_gateways, $available_gateways);
		foreach($available_gateways as $gateway_id => $gateway) {
			if(!in_array($gateway_id, $currency_gateways)) {
				unset($available_gateways[$gateway_id]);
			}
		}

		return $available_gateways;
	}

	/**
	 * Returns a list of enabled currencies.
	 */
	public function enabled_currencies() {
		return $this->_settings_controller->get_enabled_currencies();
	}

	/**
	 * Sets the hook handlers for WooCommerce and WordPress.
	 */
	private function set_hooks() {
		// called only after woocommerce has finished loading
		add_action('init', array($this, 'wordpress_loaded'));
		add_action('woocommerce_init', array($this, 'woocommerce_loaded'), 1);
		add_action('woocommerce_integrations_init', array($this, 'woocommerce_integrations_init'), 1);
		add_action('woocommerce_integrations', array($this, 'woocommerce_integrations_override'), 20);

		// Override currency symbol
		add_action('woocommerce_currency_symbol', array($this, 'woocommerce_currency_symbol'), 5, 2);
		add_action('woocommerce_currencies', array($this, 'woocommerce_currencies'), 5, 1);

		// called after all plugins have loaded
		add_action('plugins_loaded', array($this, 'plugins_loaded'));

		// WooCommerce 2.1 - Force setting of cart cookie, to ensure that session
		// data is loaded
		do_action('woocommerce_set_cart_cookies', true);

		add_action('admin_enqueue_scripts', array($this, 'load_admin_scripts'));
		add_action('wp_enqueue_scripts', array($this, 'load_frontend_scripts'));

		// Product prices should not be converted in the Admin section
		if(!is_admin()) {
			// Add filter to display selected currency
			add_filter('woocommerce_currency', array($this, 'woocommerce_currency'), 5);

			// Add filter to alter price based on selected currency
			add_filter('woocommerce_get_price', array($this, 'woocommerce_get_price'), 5, 2);
			// Display prices with the amount of decimals configured for the active currency
			add_filter('pre_option_woocommerce_price_num_decimals', array($this, 'pre_option_woocommerce_price_num_decimals'), 10, 1);

			add_filter('woocommerce_sale_price_html', array($this, 'woocommerce_sale_price_html'), 5, 2);
			add_filter('woocommerce_free_sale_price_html', array($this, 'woocommerce_sale_price_html'), 5, 2);

			// Display variation prices taking into account manually entered ones
			add_filter('woocommerce_variation_price_html', array($this, 'woocommerce_variation_price_html'), 5, 2);
			add_filter('woocommerce_variation_sale_price_html', array($this, 'woocommerce_variation_sale_price_html'), 5, 2);
			add_filter('woocommerce_variable_sale_price_html', array($this, 'woocommerce_variable_sale_price_html'), 5, 2);
			add_filter('woocommerce_variable_free_sale_price_html', array($this, 'woocommerce_variable_sale_price_html'), 5, 2);
		}

		// When viewing orders, we need to take the order's currency
		add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'format_order_line'), 10, 3);
		add_filter('woocommerce_order_subtotal_to_display', array($this, 'format_order_subtotal'), 10, 3);
		add_filter('woocommerce_get_formatted_order_total', array($this, 'format_order_total'), 10, 2);
		add_filter('woocommerce_order_shipping_to_display', array($this, 'woocommerce_order_shipping_to_display'), 10, 2);
		add_filter('woocommerce_order_tax_totals', array($this, 'woocommerce_order_tax_totals'), 10, 2);
		add_action('woocommerce_process_shop_order_meta', array($this, 'woocommerce_process_shop_order_meta'), 10, 2);

		// Register Widgets
		add_action('widgets_init', array($this, 'register_widgets'));

		// Add cart hooks
		$this->set_cart_hooks();

		// Add hooks for WooCommerce Frontend Ajax operations
		$this->set_frontend_ajax_hooks();

		// Add hooks for coupons
		$this->set_coupon_hooks();

		// Add hooks for shipping methods
		$this->set_shipping_methods_hooks();

		// Add hooks for scheduled tasks
		$this->set_scheduled_tasks_hooks();

		// Add hooks for shortcodes
		$this->set_shortcodes_hooks();

		// Add hooks to handle Order totals in base currency
		add_filter('get_post_metadata', array($this, 'get_post_metadata'), 1, 3);
		add_filter('update_post_metadata', array($this, 'update_post_metadata'), 1, 4);

		// Handle totals in base currency for order items
		add_filter('update_order_item_metadata', array($this, 'update_order_item_metadata'), 10, 4);
		add_filter('add_order_item_metadata', array($this, 'update_order_item_metadata'), 10, 4);
		add_filter('woocommerce_hidden_order_itemmeta', array($this, 'woocommerce_hidden_order_itemmeta'), 10, 1);

		// Add hooks to filter payment gateways based on the selected currency
		add_filter('woocommerce_available_payment_gateways', array($this, 'woocommerce_available_payment_gateways'), 20);

		// Add a filter for 3rd parties to retrieve the list of enabled currencies
		add_filter('wc_aelia_cs_enabled_currencies', array($this, 'enabled_currencies'));

	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		/**
		 * Localisation
		 **/
		load_plugin_textdomain(AELIA_CS_PLUGIN_TEXTDOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');

		// Load required support classes
		require_once(dirname(__FILE__) . '/vendor/autoload.php');
		require_once('lib/woocommerce-core-aux-functions.php');

		$this->_wp_error = new WP_Error();
		$this->load_error_messages();

		$this->_settings_controller = new WC_Aelia_CurrencySwitcher_Settings();

		// Uncomment line below to debug the activation hook when using symlinks
		//register_activation_hook(basename(dirname(__FILE__)).'/'.basename(__FILE__), array($this, 'setup'));
		register_activation_hook(__FILE__, array($this, 'setup'));
		register_uninstall_hook(__FILE__, array('WC_Aelia_CurrencySwitcher', 'cleanup'));

		// Set all required hooks
		$this->set_hooks();

		// indicates we are running the admin
		if(is_admin()) {
			// ...
		}

		// indicates we are being served over ssl
		if(is_ssl()) {
			// ...
		}
	}

	/**
	 * Run the updates required by the plugin. This method runs at every load, but
	 * the updates are executed only once. This allows the plugin to run the
	 * updates automatically, without requiring deactivation and rectivation.
	 *
	 * @return bool
	 */
	protected function run_updates() {
		/* This value identifies the plugin and it's used to determine the currently
		 * installed version. Normally, self::$plugin_slug is used, but we cannot use
		 * it here because the plugin slug changed in v.3.3. Using the plugin slug
		 * would make the installer think that the Currency Switcher was not installed
		 * and make it run a whole lot of updates that may not be required. Sticking
		 * to the old plugin ID, just for version checking, is an acceptable compromise.
		 */
		$plugin_id = 'wc_aelia_currencyswitcher';

		$installer = new WC_Aelia_CurrencySwitcher_Install();
		return $installer->update($plugin_id, self::VERSION);
	}

	/**
	 * Checks that a Currency is valid.
	 *
	 * @param string currency The currency to check.
	 * @return bool True, if the Currency is valid, False otherwise.
	 */
	public function is_valid_currency($currency) {
		if(empty($currency)) {
			return false;
		}

		// Retrieve enabled currencies from settings
		$valid_currencies = $this->_settings_controller->get_enabled_currencies();

		// To be valid, a Currency must be amongst the enabled ones and have an
		// Exchange Rate greater than zero
		$is_valid = in_array($currency, $valid_currencies) &&
								($this->settings_controller()->get_exchange_rate($currency) > 0);
		return $is_valid;
	}

	/**
	 * Saves the Currency selected by the User against his profile, if he is
	 * logged in, and stores such Currency in User's session.
	 *
	 * @param string selected_currency The selected Currency code.
	 */
	private function save_user_selected_currency($selected_currency) {
		$user_id = get_current_user_id();
		if(!empty($user_id)) {
			update_user_meta($user_id, AELIA_CS_USER_CURRENCY, $selected_currency);
		}

		Aelia_SessionManager::set_value(AELIA_CS_USER_CURRENCY, $selected_currency);
	}

	/**
	 * Returns the visitor's IP address, handling the case in which a standard
	 * reverse proxy is used. This method is maintained for backward compatibility,
	 * just to allow firing "wc_aelia_currencyswitcher_visitor_ip" filter, but it
	 * should not be used anymore.
	 *
	 * @return string
	 * @deprecated since 3.3
	 */
	protected function get_visitor_ip_address() {
		$forwarded_for = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
		$visitor_ip = WC_Aelia_IP2Location::factory()->get_visitor_ip_address();

		// Filter "wc_aelia_currencyswitcher_visitor_ip" is a legacy filter, maintained
		// for backward compatibility
		$visitor_ip = apply_filters('wc_aelia_currencyswitcher_visitor_ip', $visitor_ip, $forwarded_for);

		return $visitor_ip;
	}

	/**
	 * Returns the Currency selected by the User, returned as the first valid value
	 * from the following:
	 * - Currency stored in session
	 * - User's last selected currency
	 * - Main WooCommerce currency
	 *
	 * @return string The code of currently selected Currency.
	 */
	public function get_selected_currency() {
		$user_id = get_current_user_id();
		$user_currency = !empty($user_id) ? get_user_meta($user_id, AELIA_CS_USER_CURRENCY, true) : null;

		$base_currency = self::settings()->base_currency();
		// Try to get the Currency that User selected manually
		$selected_currency = coalesce(Aelia_SessionManager::get_value(AELIA_CS_USER_CURRENCY),
																	$user_currency);

		if(empty($selected_currency)) {
			if(self::settings()->currency_geolocation_enabled()) {
				// Try to set the Currency to the one used in the Country from which the
				// User is connecting
				$selected_currency = WC_Aelia_Currencies_Manager::factory()->get_currency_by_host($this->get_visitor_ip_address(),
																																													self::settings()->default_geoip_currency());
				//var_dump($selected_currency);die();

				// Save the currency detected by geolocation
				if($this->is_valid_currency($selected_currency)) {
					$this->save_user_selected_currency($selected_currency);
				}
			}
			else {
				$selected_currency = $base_currency;
			}
		}

		// Currently selected currency may be invalid for a number of reasons (e.g.
		// User had selected a Currency in the past, and it's no longer supported).
		// In such case, revert to WooCommerce base currency
		if(!$this->is_valid_currency($selected_currency)) {
			$selected_currency = $base_currency;
		}

		return $selected_currency;
	}

	/**
	 * Take care of anything that needs to be done as soon as WordPress finished
	 * loading.
	 */
	public function wordpress_loaded() {
		$this->register_common_frontend_scripts();
	}

	/**
	 * Recalculates the Cart totals, if the appropriate flag is set.
	 *
	 * @param bool force_recalculation Forces the recalculation of the cart totals,
	 * no matter what the value of the "Recalculate Totals" flag is.
	 */
	public function recalculate_cart_totals($force_recalculation = false) {
		if($force_recalculation ||
			 (Aelia_SessionManager::get_value(AELIA_CS_RECALCULATE_CART_TOTALS, 0, true) === 1)) {
			do_action('wc_aelia_currencyswitcher_recalculate_cart_totals_before');
			$this->wc()->cart->calculate_totals();
			do_action('wc_aelia_currencyswitcher_recalculate_cart_totals_after');
		}
	}

	/**
	 * Hook invoked before the cart table is displayed on the cart page.
	 */
	public function woocommerce_before_cart_table() {
		$this->recalculate_cart_totals();
	}

	/**
	 * Hook invoked when using a Menu Cart or some 3rd party themes. It will
	 * trigger the recalculation of Cart Totals before displayin the menu cart.
	 */
	public function woocommerce_cart_contents_total($cart_contents_total) {
		$this->recalculate_cart_totals();
		return $cart_contents_total;
	}

	/**
	 * Resets the "Recalculate Cart Totals" flag. It's called after the
	 * recalculation to avoid it from happening multiple times.
	 */
	public function reset_recalculate_cart_flag($order_total, $cart) {
		Aelia_SessionManager::delete_value(AELIA_CS_RECALCULATE_CART_TOTALS);
		return $order_total;
	}

	/**
	 * Converts product prices when they are added to the cart. This is required
	 * for compatibility with some 3rd party plugins, which will need this
	 * information to perform their duty.
	 *
	 * @param array cart_item The cart item, which contains, amongst other things,
	 * the product added to cart.
	 * @return array The processed cart item, with the product prices converted in
	 * the selected currency.
	 */
	public function woocommerce_add_cart_item($cart_item) {
		// $cart_item['data'] contains the product added to the cart.
		$product = $cart_item['data'];

		if(is_object($product) && $product instanceof WC_Product) {
			$cart_item['data'] = $this->convert_product_prices($product);
		}

		return $cart_item;
	}

	/**
	 * Converts product prices when they are loaded from the user's session.
	 * This is required for compatibility with some 3rd party plugins, which will
	 * need this information to perform their duty.
	 *
	 * @param array cart_item The cart item, which contains, amongst other things,
	 * the product added to cart.
	 * @param array values The values associated to the cart item key.
	 * @param string key The cart item key.
	 * @return array The processed cart item, with the product prices converted in
	 * the selected currency.
	 */
	public function woocommerce_get_cart_item_from_session($cart_item, $values, $key) {
		return $this->woocommerce_add_cart_item($cart_item);
	}

	/**
	 * It executes some actions as soon as the cart is loaded from the session.
	 */
	public function woocommerce_cart_loaded_from_session() {
		// Add filter to display selected currency
		add_filter('woocommerce_currency', array($this, 'woocommerce_currency'), 50);
		// Add filter to alter price based on selected currency
		add_filter('woocommerce_get_price', array($this, 'woocommerce_get_price'), 50, 2);
		// Display prices with the amount of decimals configured for the active currency
		add_filter('pre_option_woocommerce_price_num_decimals', array($this, 'pre_option_woocommerce_price_num_decimals'), 10, 1);

		$this->recalculate_cart_totals(true);
	}

	/**
	 * Executes some actions before the Order Review page is displayed.
	 */
	public function woocommerce_checkout_order_review() {
		// Add filter to display selected currency
		add_filter('woocommerce_currency', array($this, 'woocommerce_currency'), 50);
		// Add filter to alter price based on selected currency
		add_filter('woocommerce_get_price', array($this, 'woocommerce_get_price'), 50, 2);
		// Display prices with the amount of decimals configured for the active currency
		add_filter('pre_option_woocommerce_price_num_decimals', array($this, 'pre_option_woocommerce_price_num_decimals'), 10, 1);
	}

	/**
	 * Processes a coupon before it's passed to WooCommerce. Used to convert fixed
	 * amount coupons into the selected Currency.
	 *
	 * @param WC_Coupon $coupon The coupon to process.
	 * @return WC_Coupon
	 */
	public function wc_coupon_loaded($coupon) {
		$coupon_types_to_convert = array('fixed_cart', 'fixed_product');
		$coupon_types_to_convert = apply_filters('wc_aelia_cs_coupon_types_to_convert', $coupon_types_to_convert);

		if(in_array($coupon->type, $coupon_types_to_convert)) {
			$coupon->amount = $this->convert($coupon->amount,
																			 $this->settings_controller()->base_currency(),
																			 $this->get_selected_currency());
		}

		// Convert minimum amount to the selected currency
		if((int)$coupon->minimum_amount > 0) {
			$coupon->minimum_amount = $this->convert($coupon->minimum_amount,
																							 $this->settings_controller()->base_currency(),
																							 $this->get_selected_currency());
		}

		return $coupon;
	}

	/**
	 * Processes shipping methods before they are used by WooCommerce. Used to
	 * convert shipping costs into the selected Currency.
	 *
	 * @param array An array of WC_Shipping_Method classes.
	 * @return array An array of WC_Shipping_Method classes, with their costs
	 * converted into Currency.
	 */
	public function woocommerce_available_shipping_methods($available_shipping_methods) {
		$selected_currency = $this->get_selected_currency();
		$base_currency = self::settings()->base_currency();

		// TODO Improve calculation of shipping taxes so that decimals are preserved
		foreach($available_shipping_methods as $shipping_method) {
			if(get_value('converted_to_currency', $shipping_method, false) === true) {
				continue;
			}

			// Convert shipping cost
			if(!is_array($shipping_method->cost)) {
				// Convert a simple total cost into currency
				$shipping_method->cost = $this->convert($shipping_method->cost,
																								$base_currency,
																								$selected_currency);
			}
			else {
				// Based on documentation, class can contain an array of costs in case
				// of shipping costs applied per item. In such case, each one has to
				// be converted
				foreach($shipping_method->cost as $cost_key => $cost_value) {
					$shipping_method->cost[$cost_key] = $this->convert($cost_value,
																														 $base_currency,
																														 $selected_currency);
				}
			}

			// Convert shipping taxes
			if(!is_array($shipping_method->taxes)) {
				// Convert a simple total taxes into currency
				$shipping_method->taxes = $this->convert($shipping_method->taxes,
																								$base_currency,
																								$selected_currency);
			}
			else {
				// Based on documentation, class can contain an array of taxes in case
				// of shipping taxes applied per item. In such case, each one has to
				// be converted
				foreach($shipping_method->taxes as $taxes_key => $taxes_value) {
					$shipping_method->taxes[$taxes_key] = $this->convert($taxes_value,
																															 $base_currency,
																															 $selected_currency);
				}
			}

			// Flag the shipping method to keep track of the fact that its costs have
			// been converted into selected Currency. This is necessary because this
			// is often called multiple times within the same page load, passing the
			// same data that was already processed
			$shipping_method->converted_to_currency = true;
		}

		return $available_shipping_methods;
	}

	/**
	 * Loads the shipping methods. This hook handler is implemented to make sure
	 * that all shipping methods' parameters related to pricing (e.g. the minimum
	 * purchase order) are properly converted into selected currency.
	 *
	 * @param array shipping_methods_to_load An array of Shipping Methods class
	 * names or object instances.
	 * @return array
	 */
	public function woocommerce_shipping_methods($shipping_methods_to_load) {
		$shipping_methods = array();
		foreach($shipping_methods_to_load as $method) {
			if(!is_object($method)) {
				$method = new $method();
			}

			$shipping_method_min_amount = get_value('min_amount', $method, 0);
			if($shipping_method_min_amount > 0) {
				$method->min_amount = $this->convert($shipping_method_min_amount,
																						 $this->settings_controller()->base_currency(),
																						 $this->get_selected_currency());
			}
			$shipping_methods[] = $method;
		}

		return $shipping_methods;
	}

	/**
	 * Intercepts the titles to apply to the Widgets.
	 *
	 * @param string title
	 * @return string The widget title
	 */
	public function widget_title($title) {
		// When displaying the shopping cart widget, recalculate the totals using
		// currently selected Currency
		if($id == 'shopping_cart') {
			$this->recalculate_cart_totals();
		}

		return $title;
	}

	/**
	 * Converts into the Base Currency the price range passed by the Price Filter
	 * widget.
	 */
	protected function convert_price_filter_amounts() {
    if(isset($_GET['max_price']) && isset($_GET['min_price'])) {
			$base_currency = $this->settings_controller()->base_currency();
			$price_filter_currency = get_value(AELIA_CS_ARG_PRICE_FILTER_CURRENCY, $_GET, $base_currency);

			$_GET['max_price'] = floor($this->convert($_GET['max_price'],
																								$price_filter_currency,
																								$base_currency));
			$_GET['min_price'] = ceil($this->convert($_GET['min_price'],
																							 $price_filter_currency,
																							 $base_currency));
		}
	}

	/**
	 * Performs operations when WooCommerce is initialising its integration classes.
	 */
	public function woocommerce_integrations_init() {
		// Load the classes that will add or improve support for 3rd party plugins and themes
		$this->load_integration_classes();
	}

	/**
	 * Overrides WooCommerce integration classes.
	 *
	 * @param array integrations An array of integrations to be loaded by WooCommerce
	 * @return array
	 */
	public function woocommerce_integrations_override($integrations) {
		// Since WooCommerce 2.1, an external plugin is used for Google Analytics
		// integration and may not be always available
		if(class_exists('WC_Google_Analytics')) {
			// Set improved Google Analytics integration
			$integrations = WC_Aelia_Google_Analytics_Integration::add_google_analytics_integration($integrations);
		}

		return $integrations;
	}

	/**
	 * Performs operations when woocommerce has been loaded.
	 */
	public function woocommerce_loaded() {
		// Load the class that will handle currency prices for current WooCommerce version
		$this->load_currencyprices_manager();

		// Reporting Manager will handle calculations for WooCommerce reports
		$this->load_reporting_manager();

		// Admin Interface Manager will handle the components and widgets for the WP admin pages
		$this->load_admin_interface_manager();

		// Check if user explicitly selected a currency
		$selected_currency = get_value(AELIA_CS_ARG_CURRENCY, $_POST);

		// If no currency was explicitly selected and currency by billing country is
		// enabled, determine the one to use from the billing country
		if(empty($selected_currency) && $this->currency_by_billing_country_enabled()) {
			$selected_currency = $this->get_currency_by_billing_country();
		}

		// Update selected Currency
		if(!empty($selected_currency)) {
			// If the selected currency is not valid, go back to WooCommerce base currency
			if(!$this->is_valid_currency($selected_currency)) {
				// Debug
				//$this->trigger_error(AELIA_CS_ERR_INVALID_CURRENCY, E_USER_NOTICE, array($selected_currency));

				$selected_currency = $this->settings_controller()->base_currency();
			}

			$this->save_user_selected_currency($selected_currency);
			/* Set a flag that will trigger the recalculation of the cart totals using
			 * the new currency. This operation cannot be performed right now because
			 * we might not be on a cart page, in which case the cart would not be
			 * available.
			 */
			Aelia_SessionManager::set_value(AELIA_CS_RECALCULATE_CART_TOTALS, 1);
		}

		// Convert Price Filters amounts into base currency
		$this->convert_price_filter_amounts();

		// Run updates only when in Admin area. This should occur automatically when
		// plugin is activated, since it's done in the Admin area
		if(is_admin() && !self::doing_ajax()) {
			$this->run_updates();
		}
	}

	/**
	 * Performs operation when all plugins have been loaded.
	 */
	public function plugins_loaded() {
		// ...
	}

	/**
	 * Registers a widget class.
	 *
	 * @param string widget_class The class to register.
	 * @param bool stop_on_error Indicates if the function should raise an error
	 * if the Widget Class doesn't exist or cannot be loaded.
	 * @return bool True, if the Widget was registered correctly, False otherwise.
	 */
	private function register_widget($widget_class, $stop_on_error = true) {
		if(!class_exists($widget_class)) {
			if($stop_on_error === true) {
				$this->trigger_error(AELIA_CS_ERR_INVALID_WIDGET_CLASS,
														 E_USER_ERROR, array($widget_class));
			}
			return false;
		}
		register_widget($widget_class);

		return true;
	}

	/**
	 * Registers all the Widgets used by the plugin.
	 */
	public function register_widgets() {
		$this->register_widget('WC_Aelia_CurrencySwitcher_Widget');

		// Register the billing country selector
		if($this->currency_by_billing_country_enabled()) {
			$this->register_widget('Aelia\CurrencySwitcher\Billing_Country_Selector_Widget');
		}
	}

	/**
	 * Registers the script and style files required in the backend (even outside
	 * of plugin's pages).
	 */
	protected function register_common_admin_scripts() {
		// Scripts
		wp_register_script('wc-aelia-currency-switcher-admin',
											 AELIA_CS_PLUGIN_URL . '/js/admin/wc-aelia-currency-switcher-admin.js',
											 array('jquery'),
											 self::VERSION,
											 true);
		wp_register_script('wc-aelia-currency-switcher-admin-overrides',
											 AELIA_CS_PLUGIN_URL . '/js/admin/wc-aelia-currency-switcher-overrides.js',
											 array(),
											 self::VERSION,
											 true);

		// Styles
		wp_register_style('wc-aelia-cs-admin',
											AELIA_CS_PLUGIN_URL . '/design/css/admin.css',
											array(),
											self::VERSION,
											'all');
	}

	/**
	 * Registers the script and style files required in the frontend (even outside
	 * of plugin's pages).
	 */
	protected function register_common_frontend_scripts() {
		// Scripts
		wp_register_script('wc-aelia-currency-switcher-widget',
											 AELIA_CS_PLUGIN_URL . '/js/frontend/wc-aelia-currency-switcher-widget.js',
											 array('jquery'),
											 self::VERSION,
											 false);
		wp_register_script('wc-aelia-currency-switcher',
											 AELIA_CS_PLUGIN_URL . '/js/frontend/wc-aelia-currency-switcher.js',
											 array('jquery'),
											 self::VERSION,
											 true);
		// Styles
		wp_register_style('wc-aelia-cs-frontend',
											AELIA_CS_PLUGIN_URL . '/design/css/frontend.css',
											array(),
											self::VERSION,
											'all');
	}

	/**
	 * Registers the script and style files needed by the admin pages of the
	 * plugin.
	 */
	protected function register_plugin_admin_scripts() {
		// Scripts
		wp_register_script('jquery-ui',
											 '//code.jquery.com/ui/1.10.3/jquery-ui.js',
											 array('jquery'),
											 null,
											 true);
		wp_register_script('chosen',
											 '//cdnjs.cloudflare.com/ajax/libs/chosen/1.1.0/chosen.jquery.min.js',
											 array('jquery'),
											 null,
											 true);

		// Styles
		wp_register_style('chosen',
												'//cdnjs.cloudflare.com/ajax/libs/chosen/1.1.0/chosen.min.css',
												array(),
												null,
												'all');
		wp_register_style('jquery-ui',
											'//code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css',
											array(),
											null,
											'all');
	}

	/**
	 * Determines if one of plugin's admin pages is being rendered.
	 *
	 * @return bool
	 */
	protected function rendering_plugin_admin_page() {
		$screen = get_current_screen();
		$page_id = $screen->id;

		// If page id matches the plugin's slug, then we are in plugin's admin page
		return ($page_id == 'woocommerce_page_' . AELIA_CS_SLUG_OPTIONS_PAGE);
	}

	/**
	 * Loads Styles and JavaScript for the Admin pages.
	 */
	public function load_admin_scripts() {
		global $post;

		// Register common JS for the backend
		$this->register_common_admin_scripts();
		if($this->rendering_plugin_admin_page()) {
			// Load Admin scripts only on plugin settings page
			$this->register_plugin_admin_scripts();

			// Styles
			wp_enqueue_style('woocommerce_admin_styles', $this->wc()->plugin_url() . '/assets/css/admin.css');
			wp_enqueue_style('jquery-ui');
			wp_enqueue_style('chosen');

			// JavaScript
			// Placeholder - Enable and pass the values to localise the Admin page script
			wp_enqueue_script('jquery-ui');
			wp_enqueue_script('chosen');
			wp_enqueue_script('wc-aelia-currency-switcher-admin');
		}

		wp_enqueue_style('wc-aelia-cs-admin');
		// Load overrides. These must be loaded every time, as they will alter
		// WooCommerce behaviour by making its pages aware of multi-currency orders
		wp_enqueue_script('wc-aelia-currency-switcher-admin-overrides');

		// Prepare parameters for common admin scripts
		$woocommerce_admin_params = array(
			'base_currency' => self::settings()->base_currency(),
			'enabled_currencies' => $this->enabled_currencies(),
		);

		// When viewing an Order, load the settings for the currency used when it
		// was placed
		if(get_value('post_type', $post) == 'shop_order') {
			$woocommerce_admin_params = $this->load_order_currency_settings($post->ID, $woocommerce_admin_params);
		}

		// Add localization for admin scripts
		$this->localize_admin_scripts($woocommerce_admin_params);
	}

	/**
	 * Loads Styles and JavaScript for the Frontend.
	 */
	public function load_frontend_scripts() {
		// Styles
		wp_enqueue_style('wc-aelia-cs-frontend');

		// Scripts
		wp_enqueue_script('wc-aelia-currency-switcher');
		wp_localize_script('wc-aelia-currency-switcher',
											 'wc_aelia_currency_switcher_params',
											 array(
												// Set the Exchange Rate to convert from Base Currency to currently selected Currency
												'current_exchange_rate_from_base' => $this->current_exchange_rate(),
												// Set currently selected currency
												'selected_currency' => $this->get_selected_currency()
											 ));

	}

	/**
	 * Checks that one or more PHP extensions are loaded.
	 *
	 * @param array required_extensions An array of extension names.
	 * @return array An array of error messages containing one entry for each
	 * extension that is not loaded.
	 */
	protected static function check_required_extensions(array $required_extensions) {
		$errors = array();
		foreach($required_extensions as $extension) {
			if(!extension_loaded($extension)) {
				$errors[] =
					sprintf(__('Plugin requires "%s" PHP extension. Please install such extension, ' .
										 'or ask your hosting provider to install it for you.',
										 AELIA_CS_PLUGIN_TEXTDOMAIN),
														$extension);
			}
		}

		return $errors;
	}

	/**
	 * Checks that plugin requirements are satisfied.
	 *
	 * @return bool
	 */
	public static function check_requirements() {
		self::$requirements_errors = array();
		if(PHP_VERSION < '5.3') {
			self::$requirements_errors[] =
				__('Plugin requires PHP 5.3 or greater. Please upgrade PHP to version 5.3 ' .
					 'or higher, or ask your hosting provider to do so. Of course, please make sure ' .
					 'that you make a backup of your site(s) before proceeding. The upgrade to PHP ' .
					 '5.3 is fairly safe, but it is always better to keep backups before performing ' .
					 'any important operation.',
					 AELIA_CS_PLUGIN_TEXTDOMAIN);
		}

		// Check that all required extensions are loaded
		$required_extensions = array(
			'curl',
		);
		$extension_errors = self::check_required_extensions($required_extensions);

		self::$requirements_errors = array_merge(self::$requirements_errors, $extension_errors);

		return empty(self::$requirements_errors);
	}

	/**
	 * Display requirements errors that prevented the plugin from being loaded.
	 */
	public static function plugin_requirements_notices() {
		if(empty(self::$requirements_errors)) {
			return;
		}

		// Inline CSS styles have to be used because plugin is not loaded if
		// requirements are missing, therefore the plugin's CSS files are ignored
		echo '<div class="error fade">';
		echo '<h4 class="wc_aelia message_header" style="margin: 1em 0 0 0">';
		echo __('Currency Switcher could not be loaded due to missing requirements', AELIA_CS_PLUGIN_TEXTDOMAIN);
		echo '</h4>';
		echo '<ul style="list-style: disc inside">';
		echo '<li>';
		echo implode('</li><li>', self::$requirements_errors);
		echo '</li>';
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Setup function. Called when plugin is enabled.
	 */
	public function setup() {
		if(!empty(self::$requirements_errors)) {
			die(implode('<br>', self::$requirements_errors));
		}
	}

	/**
	 * Cleanup function. Called when plugin is uninstalled.
	 */
	public static function cleanup() {
		if(!defined('WP_UNINSTALL_PLUGIN')) {
			return;
		}
	}

	/**
	 * Returns the full path and file name of the specified template, if such file
	 * exists.
	 *
	 * @param string template_name The name of the template.
	 * @return string
	 */
	public function get_template_file($template_name) {
		$template = '';

		/* Look for the following:
		 * - yourtheme/woocommerce-aelia-currencyswitcher-{template_name}.php
		 * - yourtheme/woocommerce-aelia-currencyswitcher/{template_name}.php
		 */
		$template = locate_template(array(
			self::$plugin_slug . "-{$template_name}.php",
			self::$plugin_slug . '/' . "{$template_name}.php"
		));

		// If template could not be found, get default one
		if(empty($template)) {
			$default_template_file = AELIA_CS_VIEWS_PATH . '/' . "{$template_name}.php";

			if(file_exists($default_template_file)) {
				$template = $default_template_file;
			}
		}

		// If template does not exist, trigger a warning to inform the site administrator
		if(empty($template)) {
			$this->trigger_error(AELIA_CS_ERR_INVALID_TEMPLATE,
													 E_USER_WARNING,
													 array(self::$plugin_slug, $template_name));
		}

		return $template;
	}

	/**
	 * Returns the suffix to be appended to a product price. Used when displaying
	 * products on the front page.
	 *
	 * @param WC_Product product The product for which the price suffix should be
	 * returned.
	 * @return string
	 * @since WooCommerce 2.1
	 */
	protected function get_price_suffix(WC_Product $product) {
		if(method_exists($product, 'get_price_suffix')) {
			return $product->get_price_suffix();
		}

		return '';
	}

	/**
	 * Indicates if we are processing an Ajax call.
	 *
	 * @return bool
	 */
	public static function doing_ajax() {
		return defined('DOING_AJAX') && DOING_AJAX;
	}

	/**
	 * Returns the country code for the user, detecting it using the IP Address,
	 * if needed.
	 * IMPORTANT: WooCommerce stores the billing country in its "customer" property,
	 * while this method uses WooCommerce session when the billing country is selected.
	 * This must be done because the tax display option is retrieved by WooCommerce
	 * BEFORE the "customer" property is initialised. If we relied on such property,
	 * very often it would be empty, and we would return the incorrect country code.
	 *
	 * @return string
	 */
	public function get_billing_country() {
		if(!empty($this->billing_country)) {
			return $this->billing_country;
		}

		$woocommerce = $this->wc();

		$result = null;

		if(self::doing_ajax() && isset($_POST['action']) && ($_POST['action'] === 'woocommerce_update_order_review')) {
			// If user is on checkout page and changes the billing country, get the
			// country code and store it in the session
			check_ajax_referer('update-order-review', 'security');
			if(isset($_POST[AELIA_CS_ARG_ORDER_BILLING_COUNTRY])) {
				$result = $_POST[AELIA_CS_ARG_ORDER_BILLING_COUNTRY];
				Aelia_SessionManager::set_value(AELIA_CS_SESSION_BILLING_COUNTRY, $result);
			}
		}

		if(empty($result)) {
			if(isset($_POST[AELIA_CS_ARG_BILLING_COUNTRY])) {
				$result = $_POST[AELIA_CS_ARG_BILLING_COUNTRY];
				Aelia_SessionManager::set_value(AELIA_CS_SESSION_BILLING_COUNTRY, $result);
			}
		}

		// If no billing country was posted, check if one was stored in the session
		if(empty($result)) {
			$result = Aelia_SessionManager::get_value(AELIA_CS_SESSION_BILLING_COUNTRY);
		}

		// If no valid currency could be retrieved from customer's details, detect
		// it using visitor's IP address
		if(empty($result)) {
			$result = WC_Aelia_IP2Location::factory()->get_visitor_country();
		}

		// If everything fails, take WooCommerce customer country or base country
		if(empty($result)) {
			$result = isset($woocommerce->customer) ? $woocommerce->customer->get_country() : $woocommerce->countries->get_base_country();
		}

		$this->billing_country = $result;

		return $result;
	}

	/**
	 * Gets the active currency using the billing country.
	 *
	 * @return string
	 */
	protected function get_currency_by_billing_country() {
		$billing_country = $this->get_billing_country();
		$currency = WC_Aelia_Currencies_Manager::factory()->get_country_currency($billing_country);

		// If currency used in the billing country is not enabled, take the default
		// used for GeoIP
		if(!$this->is_valid_currency($currency)) {
			$currency = self::settings()->default_geoip_currency();
		}

		// Debug
		//var_dump("CURRENCY BY BILLING COUNTRY: $currency");

		return $currency;
	}

	/**
	 * Indicates if currency selection by billing country has been enabled.
	 *
	 * @return bool
	 */
	protected function currency_by_billing_country_enabled() {
		return self::settings()->current_settings(WC_Aelia_CurrencySwitcher_Settings::FIELD_CURRENCY_BY_BILLING_COUNTRY_ENABLED, false);
	}
}

if(WC_Aelia_CurrencySwitcher::check_requirements() == true) {
	// Instantiate plugin and add it to the set of globals
	$GLOBALS[WC_Aelia_CurrencySwitcher::$plugin_slug] = new WC_Aelia_CurrencySwitcher();
}
else {
	// If requirements are missing, display the appropriate notices
	add_action('admin_notices', array('WC_Aelia_CurrencySwitcher', 'plugin_requirements_notices'));
}
