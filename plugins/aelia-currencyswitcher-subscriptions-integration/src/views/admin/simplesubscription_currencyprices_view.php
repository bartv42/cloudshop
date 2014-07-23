<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

use Aelia\WC\CurrencySwitcher\Subscriptions\Subscriptions_Integration;
use Aelia\WC\CurrencySwitcher\Subscriptions\WC_Aelia_CS_Subscriptions_Plugin;

$currencyprices_manager = WC_Aelia_CurrencyPrices_Manager::instance();
$enabled_currencies = WC_Aelia_CurrencySwitcher::settings()->get_enabled_currencies();
$base_currency = WC_Aelia_CurrencySwitcher::settings()->base_currency();
// The text domain for translations
$text_domain = WC_Aelia_CS_Subscriptions_Plugin::$text_domain;

$post_id = $currencyprices_manager->current_post->ID;

echo '<div id="wc_aelia_cs_subscription_prices" class="clearfix show_if_subscription">';
// Debug
echo 'DEBUG - SUBSCRIPTION';
// Display header of currency pricing section
include('currencyprices_header.php');

$product_regular_prices = $currencyprices_manager->get_product_regular_prices($post_id);
$product_sale_prices = $currencyprices_manager->get_product_sale_prices($post_id);
$signup_prices = Subscriptions_Integration::get_subscription_signup_prices($post_id);

echo '<table>';
echo '<tr>';
echo '<th>';
echo '</th>';
echo '<th>';
echo __('Regular price', $text_domain);
echo '</th>';
echo '<th>';
echo __('Sale price', $text_domain);
echo '</th>';
echo '<th>';
echo __('Sign-up fee', $text_domain);
echo '</th>';
echo '</tr>';

// Outputs the Product prices in the different Currencies
foreach($enabled_currencies as $currency) {
	if($currency == $base_currency) {
		continue;
	}
	echo '<tr>';
	echo '<td class="Currency">';
	echo $currency , ' (' . get_woocommerce_currency_symbol($currency) . ')';
	echo '</td>';

	echo '<td class="Regular">';
	woocommerce_wp_text_input(array('id' => Subscriptions_Integration::FIELD_REGULAR_CURRENCY_PRICES . "[$currency]",
																	'class' => 'wc_input_price short',
																	//'label' => __('Subscription Price', 'woocommerce-subscriptions') . ' (' . get_woocommerce_currency_symbol($currency) . ')',
																	'type' => 'number',
																	'value' => get_value($currency, $product_regular_prices, null),
																	'placeholder' => __('Auto',
																											$text_domain),
																	'custom_attributes' => array('step' => 'any',
																															 'min' => '0'
																															 ),
																	)
														);
	echo '</td>';

	echo '<td class="Sale">';
	woocommerce_wp_text_input(array('id' => Subscriptions_Integration::FIELD_SALE_CURRENCY_PRICES . "[$currency]",
																	'class' => 'wc_input_price short',
																	//'label' => __('Sale Price', 'woocommerce') . ' (' . get_woocommerce_currency_symbol($currency) . ')',
																	'type' => 'number',
																	'value' => get_value($currency, $product_sale_prices, null),
																	'placeholder' => __('Auto',
																											$text_domain),
																	'custom_attributes' => array('step' => 'any',
																															 'min' => '0'
																															 ),
																	)
														);
	echo '</td>';

	echo '<td class="SignupFee">';
	woocommerce_wp_text_input(array('id' => Subscriptions_Integration::FIELD_SIGNUP_FEE_CURRENCY_PRICES . "[$currency]",
																	'class' => 'wc_input_price short',
																	//'label' => __('Sign-up fee', $text_domain) . ' (' . get_woocommerce_currency_symbol($currency) . ')',
																	'type' => 'number',
																	'value' => get_value($currency, $signup_prices, null),
																	'placeholder' => __('Auto',
																											$text_domain),
																	'custom_attributes' => array('step' => 'any',
																															 'min' => '0'
																															 ),
																	)
														);
	echo '</td>';

	echo '</tr>';
}
echo '</table>';
echo '</div>';
