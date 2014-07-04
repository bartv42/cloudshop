<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Tweak for WC2.0.x
 * WC2.0.x doesn't always load the writepanels-init.php file when adding a
 * variation. In such case, we load it ourselves.
 */
if(!function_exists('woocommerce_wp_text_input')) {
	global $woocommerce;
	if(version_compare($woocommerce->version, '2.1', '<')) {
		require_once($woocommerce->plugin_path . '/admin/post-types/writepanels/writepanels-init.php');
	}
}

echo '<tr>';
echo '<td colspan="2">';
// This view is designed to be loaded by an instance of
// WC_Aelia_CurrencyPrices_Manager. Such instance is what "$this" and "self"
// refer to.
$currencyprices_manager = $this;
$enabled_currencies = $currencyprices_manager->enabled_currencies();
$base_currency = WC_Aelia_CurrencySwitcher::settings()->base_currency();

$post_id = $currencyprices_manager->current_post->ID;
$loop = $currencyprices_manager->loop_idx;

echo '<div id="wc_aelia_cs_product_prices" class="clearfix hide_if_variable-subscription">';
// Display header of currency pricing section
include('product_currencyprices_header.php');

echo '<div id="regular_prices">';
$product_regular_prices = $currencyprices_manager->get_variation_regular_prices($post_id);
// Outputs the Product Variation prices in the different Currencies
foreach($enabled_currencies as $currency) {
	if($currency == $base_currency) {
		continue;
	}

	woocommerce_wp_text_input(array('id' => WC_Aelia_CurrencyPrices_Manager::FIELD_VARIABLE_REGULAR_CURRENCY_PRICES . "[$loop][$currency]",
																	'class' => 'wc_input_price short',
																	'label' => __('Regular Price', 'woocommerce') . ' (' . get_woocommerce_currency_symbol($currency) . ')',
																	'type' => 'number',
																	'value' => get_value($currency, $product_regular_prices, null),
																	'placeholder' => __('Auto',
																											AELIA_CS_PLUGIN_TEXTDOMAIN),
																	'custom_attributes' => array('step' => 'any',
																															 'min' => '0'
																															 ),
																	)
														);
}
echo '</div>';
echo '<div id="sale_prices">';

$product_sale_prices = $currencyprices_manager->get_variation_sale_prices($post_id);
// Outputs the Product Variation Sale prices in the different Currencies
foreach($enabled_currencies as $currency) {
	if($currency == $base_currency) {
		continue;
	}

	woocommerce_wp_text_input(array('id' => WC_Aelia_CurrencyPrices_Manager::FIELD_VARIABLE_SALE_CURRENCY_PRICES . "[$loop][$currency]",
																	'class' => 'wc_input_price short',
																	'label' => __('Sale Price', 'woocommerce') . ' (' . get_woocommerce_currency_symbol($currency) . ')',
																	'type' => 'number',
																	'value' => get_value($currency, $product_sale_prices, null),
																	'placeholder' => __('Auto',
																											AELIA_CS_PLUGIN_TEXTDOMAIN),
																	'custom_attributes' => array('step' => 'any',
																															 'min' => '0'
																															 ),
																	)
														);
}
echo '</div>';
echo '</div>';

echo '</td>';
echo '</tr>';
