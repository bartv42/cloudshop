<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

use Aelia\WC\CurrencySwitcher\Subscriptions\Subscriptions_Integration;
use Aelia\WC\CurrencySwitcher\Subscriptions\WC_Aelia_CS_Subscriptions_Plugin;

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

$currencyprices_manager = WC_Aelia_CurrencyPrices_Manager::instance();
$enabled_currencies = WC_Aelia_CurrencySwitcher::settings()->get_enabled_currencies();
$base_currency = WC_Aelia_CurrencySwitcher::settings()->base_currency();
// The text domain for translations
$text_domain = WC_Aelia_CS_Subscriptions_Plugin::$text_domain;


$post_id = $currencyprices_manager->current_post->ID;
$loop = $currencyprices_manager->loop_idx;

echo '<tr>';
echo '<td colspan="2">';
echo '<div id="wc_aelia_cs_subscription_prices" class="variation clearfix show_if_variable-subscription">';
// Display header of currency pricing section
include('currencyprices_header.php');

// Get prices for each variation
$product_regular_prices = $currencyprices_manager->get_variation_regular_prices($post_id);
$product_sale_prices = $currencyprices_manager->get_variation_sale_prices($post_id);
$signup_prices = Subscriptions_Integration::get_subscription_variation_signup_prices($post_id);

echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>';
echo '</th>';
echo '<th>';
echo __('Sign-up fee', $text_domain);
echo '</th>';
echo '<th>';
echo __('Subscr. price', $text_domain);
echo '</th>';
echo '<th>';
echo __('Sale price', $text_domain);
echo '</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';
// Outputs the Product prices in the different Currencies
foreach($enabled_currencies as $currency) {
	if($currency == $base_currency) {
		continue;
	}
	echo '<tr>';
	echo '<td class="Currency">';
	echo $currency;
	echo '</td>';

	echo '<td class="SignupFee">';
	woocommerce_wp_text_input(array('id' => Subscriptions_Integration::FIELD_VARIATION_SIGNUP_FEE_CURRENCY_PRICES . "[$loop][$currency]",
																	'class' => 'wc_input_price short',
																	'label' => '',
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

	echo '<td class="Regular">';
	woocommerce_wp_text_input(array('id' => Subscriptions_Integration::FIELD_VARIATION_REGULAR_CURRENCY_PRICES . "[$loop][$currency]",
																	'class' => 'wc_input_price short',
																	'label' => '',
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
	woocommerce_wp_text_input(array('id' => Subscriptions_Integration::FIELD_VARIATION_SALE_CURRENCY_PRICES . "[$loop][$currency]",
																	'class' => 'wc_input_price short',
																	'label' => '',
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

	echo '</tr>';
	echo '</tbody>';
}
echo '</table>';
echo '</div>';
