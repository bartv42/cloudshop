<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

// This view is designed to be loaded by an instance of
// WC_Aelia_CurrencyPrices_Manager. Such instance is what "$this" and "self"
// refer to.
$currencyprices_manager = $this;
$enabled_currencies = $currencyprices_manager->enabled_currencies();
$base_currency = WC_Aelia_CurrencySwitcher::settings()->base_currency();

$post_id = $currencyprices_manager->current_post->ID;

echo '<div id="wc_aelia_cs_product_prices" class="clearfix hide_if_subscription">';
// Display header of currency pricing section
include('product_currencyprices_header.php');

echo '<div id="regular_prices">';
$product_regular_prices = $currencyprices_manager->get_product_regular_prices($post_id);
// Outputs the Product prices in the different Currencies
foreach($enabled_currencies as $currency) {
	if($currency == $base_currency) {
		continue;
	}

	woocommerce_wp_text_input(array('id' => WC_Aelia_CurrencyPrices_Manager::FIELD_REGULAR_CURRENCY_PRICES . "[$currency]",
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

$product_sale_prices = $currencyprices_manager->get_product_sale_prices($post_id);
// Outputs the Product Sale prices in the different Currencies
foreach($enabled_currencies as $currency) {
	if($currency == $base_currency) {
		continue;
	}

	woocommerce_wp_text_input(array('id' => WC_Aelia_CurrencyPrices_Manager::FIELD_SALE_CURRENCY_PRICES . "[$currency]",
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
