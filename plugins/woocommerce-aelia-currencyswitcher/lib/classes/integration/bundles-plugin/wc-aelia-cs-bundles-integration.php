<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Implements support for WooThemes Bundles plugin.
 */
class WC_Aelia_CS_Bundles_Integration {
	// @var WC_Aelia_CurrencyPrices_Manager The object that handles Currency Prices for the Products.
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

	public function __construct() {
		$this->currencyprices_manager = WC_Aelia_CurrencyPrices_Manager::Instance();
		$this->set_hooks();
	}

	/**
	 * Set the hooks required by the class.
	 */
	protected function set_hooks() {
		add_filter('wc_aelia_currencyswitcher_product_convert_callback', array($this, 'wc_aelia_currencyswitcher_product_convert_callback'), 10, 2);
		add_action('woocommerce_process_product_meta_bundle', array($this->currencyprices_manager, 'process_product_meta'));
		add_filter('woocommerce_bundle_price_html', array($this, 'woocommerce_bundle_price_html'), 10, 2);
		add_filter('woocommerce_bundle_sale_price_html', array($this, 'woocommerce_bundle_sale_price_html'), 10, 2);
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
			$product = $this->currencyprices_manager->convert_product_prices($product, $selected_currency);
			$product->currency = $selected_currency;
		}

		return $product;
	}

	/**
	 * Converts the price for a bundled product. With bundled products, price
	 * is passed "as-is" and it doesn't get converted into currency.
	 *
	 * @param string bundle_price_html The HTML snippet containing a
	 * bundle's regular price in base currency.
	 * @param WC_Product product The product being displayed.
	 * @return string The HTML snippet with the price converted into currently
	 * selected currency.
	 */
	public function woocommerce_bundle_price_html($bundle_price_html, $product) {
		$product = $this->convert_product_prices($product);

		$bundle_price_html = $product->get_price_html_from_text();
		$bundle_price_html .= woocommerce_price($product->min_bundle_price);
		return $bundle_price_html;
	}

	/**
	 * Converts the price for a bundled Products on sale. With sales, the regular
	 * price is passed "as-is" and it doesn't get converted into currency.
	 *
	 * @param string bundle_sale_price_html The HTML snippet containing a
	 * Product's regular price and sale price.
	 * @param WC_Product product The product being displayed.
	 * @return string The HTML snippet with the sale price converted into
	 * currently selected currency.
	 */
	public function woocommerce_bundle_sale_price_html($bundle_sale_price_html, $product) {
		$product = $this->convert_product_prices($product);

		$min_bundle_regular_price_in_currency = $this->currency_switcher()->format_price($product->min_bundle_regular_price);
		$min_bundle_sale_price_in_currency = $product->min_bundle_price;
		if($min_bundle_sale_price_in_currency <= 0) {
			$min_bundle_sale_price_in_currency = __('Free!', 'woocommerce');
		} else{
			$min_bundle_sale_price_in_currency = $this->currency_switcher()->format_price($min_bundle_sale_price_in_currency);
		}

		$bundle_sale_price_html = $product->get_price_html_from_text();
		return '<del>' . $min_bundle_regular_price_in_currency . '</del> <ins>' . $min_bundle_sale_price_in_currency . '</ins>';
	}

	/**
	 * Callback to perform the conversion of bundle prices into selected currencu.
	 *
	 * @param callable $convert_callback A callable, or null.
	 * @param WC_Product The product to examine.
	 * @return callable
	 */
	public function wc_aelia_currencyswitcher_product_convert_callback($convert_callback, $product) {
		$method_keys = array(
			'WC_Product_Bundle' => 'bundle',
		);

		// Determine the conversion method to use
		$method_key = get_value(get_class($product), $method_keys, '');
		$convert_method = 'convert_' . $method_key . '_product_prices';

		if(!method_exists($this, $convert_method)) {
			return $convert_callback;
		}

		return array($this, $convert_method);
	}

	/**
	 * Resetrs bundle's prices, prior to recalculating the.
	 *
	 * @param WC_Product_Bundle product The bundle whose prices will be reset.
	 */
	protected function reset_bundle_prices(WC_Product_Bundle $product) {
		$product->min_bundle_price = 0;
		$product->min_bundle_regular_price = 0;
		$product->max_bundle_price = 0;
		$product->max_bundle_regular_price = 0;
	}

	/**
	 * Recalculates bundle's prices, based on selected currency.
	 *
	 * @param WC_Product_Bundle product The bundle whose prices will be converted.
	 */
	protected function recalculate_bundle_prices(WC_Product_Bundle $product) {
		$this->reset_bundle_prices($product);

		$bundled_products = get_value('bundled_products', $product, array());
		foreach($bundled_products as $bundled_product_id => $bundled_product) {
			$product_class = get_class($bundled_product);
			$bundled_product_quantity = $product->bundled_item_quantities[$bundled_product_id];

			switch($product_class) {
				case 'WC_Product_Variable':
					$bundled_product->get_price();
					$product->min_bundle_price += $bundled_product_quantity * $bundled_product->min_variation_price;
					$product->min_bundle_regular_price += $bundled_product_quantity * $bundled_product->min_variation_regular_price;
					$product->max_bundle_price += $bundled_product_quantity * $bundled_product->max_variation_price;
					$product->max_bundle_regular_price += $bundled_product_quantity * $bundled_product->max_variation_regular_price;
					break;
				default:
					$product->min_bundle_price += $bundled_product_quantity * $bundled_product->get_price();
					$product->min_bundle_regular_price += $bundled_product_quantity * $bundled_product->regular_price;
					$product->max_bundle_price += $bundled_product_quantity * $bundled_product->get_price();
					$product->max_bundle_regular_price += $bundled_product_quantity * $bundled_product->regular_price;
					break;
			}
		}
	}

	/**
	 * Converts the prices of a bundle product to the specified currency.
	 *
	 * @param WC_Product_Bundle product A variable product.
	 * @param string currency A currency code.
	 * @return WC_Product_Bundle The product with converted prices.
	 */
	public function convert_bundle_product_prices(WC_Product_Bundle $product, $currency) {
		$bundled_products = get_value('bundled_products', $product, array());

		if($product->per_product_pricing_active) {
			$this->recalculate_bundle_prices($product);
		}
		else {
			$product = $this->currencyprices_manager->convert_simple_product_prices($product, $currency);
		}

		return $product;
	}
}
