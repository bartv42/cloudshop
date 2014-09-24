<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

use Aelia\WC\TaxDisplayByCountry\WC_Aelia_Tax_Display_By_Country;
use Aelia\WC\TaxDisplayByCountry\Definitions;

// $widget_args is passed when widget is initialised
echo get_value('before_widget', $widget_args);

// This wrapper is needed for widget JavaScript to work correctly
echo '<div class="widget_wc_aelia_billing_country_selector_widget">';

// Title is set in Aelia\TaxDisplayByCountry\Country_Selector_Widget::widget()
$widget_title = get_value('title', $widget_args);
if(!empty($widget_title)) {
	echo get_value('before_title', $widget_args);
	echo apply_filters('widget_title', __($widget_title, $this->text_domain));
	echo get_value('after_title', $widget_args);
}

echo '<!-- Tax Display by Country v.' . WC_Aelia_Tax_Display_By_Country::$version . ' - Billing Country Selector Widget -->';
echo '<form method="post" class="billing_country_selector_form">';
echo '<select class="countries" name="' . Definitions::ARG_AELIA_BILLING_COUNTRY . '">';
foreach($widget_args['countries'] as $country_code => $country_name) {
	$selected_attr = '';
	if($country_code === $widget_args['selected_country']) {
		$selected_attr = 'selected="selected"';
	}
	echo '<option value="' . $country_code . '" ' . $selected_attr . '>' . $country_name. '</option>';
}
echo '</select>';

// Display the "change country" button only when JavaScript is disabled. When it's enabled, selecting a
// country in the dropdown will automatically trigger the billing country switch
echo '<noscript>';
echo '<button type="submit" class="button change_country">' . __('Change Country', $this->text_domain) . '</button>';
echo '</noscript>';
echo '</form>';

echo '</div>';

echo get_value('after_widget', $widget_args);
