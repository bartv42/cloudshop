jQuery(document).ready(function($) {
	/**
	 * Prompts for a price and sets it on all variations.
	 *
	 * @param string price_type The price type (regular or sale).
	 * @param string currency The currency for which the price has been specified.
	 */
	function SetVariationsPrices(price_type, currency) {
		var new_price = window.prompt(woocommerce_admin_meta_boxes_variations.i18n_enter_a_value);
	  $(':input[name^="variable_' + price_type + '_currency_prices"][name$="[' + currency + ']"]')
			.val(new_price)
			.change();
	}

	// Set JS hooks to bulk-set prices for variations
	var enabled_currencies = aelia_cs_woocommerce_writepanel_params['enabled_currencies'] || [];
	for(var idx = 0; idx < enabled_currencies.length; idx++) {
		var currency = enabled_currencies[idx];
		// No need to add an option for the base currency, it already exists in standard WooCommerce menu
		if(currency == aelia_cs_woocommerce_writepanel_params['base_currency']) {
			continue;
		}

		// Hook for variations regular prices
		$('select#field_to_edit').on('variable_regular_currency_prices_' + currency, function() {
			SetVariationsPrices('regular', $(this).find("option:selected").attr('currency'));
		});
		// Hook for variations sale prices
		$('select#field_to_edit').on('variable_sale_currency_prices_' + currency, function() {
			SetVariationsPrices('sale', $(this).find("option:selected").attr('currency'));
		});
	}
});


/* The following code must be outside the jQuery.ready().
 * This snippet overwrites some of the parameters set for WooCommerce Writepanel,
 * mostly to ensure that orders' currency details are displayed correctly in
 * Admin pages (WooCommerce ignores the order currency and sets the base currency).
 *
 * The reason why this code is outside jQuery.ready() is that the parameters
 * must be overwritten before they are used, and that happens inside jQuery.ready()
 * event.
 */
if((typeof aelia_cs_woocommerce_writepanel_params != 'undefined') &&
	 (typeof woocommerce_writepanel_params != 'undefined')){
	//console.log(aelia_cs_woocommerce_writepanel_params);
	for(param_name in aelia_cs_woocommerce_writepanel_params) {
		woocommerce_writepanel_params[param_name] = aelia_cs_woocommerce_writepanel_params[param_name];
	}
}

// WooCommerce 2.1 uses a different object
if((typeof aelia_cs_woocommerce_writepanel_params != 'undefined') &&
	 (typeof woocommerce_admin_meta_boxes != 'undefined')){
	//console.log(woocommerce_admin_meta_boxes);
	for(param_name in aelia_cs_woocommerce_writepanel_params) {
		woocommerce_admin_meta_boxes[param_name] = aelia_cs_woocommerce_writepanel_params[param_name];
	}
}
