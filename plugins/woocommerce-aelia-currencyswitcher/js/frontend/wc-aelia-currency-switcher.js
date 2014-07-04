jQuery(document).ready(function($) {
	/**
	 * Updates the Price Filter Slider widget to reflect the price ranges using
	 * selected Currency.
	 */
	function update_price_filter_slider() {
		var $ = jQuery;
		if($('.price_slider').length <= 0) {
			return;
		}
		var $price_slider = $('.price_slider');
		var $price_slider_amount = $('.price_slider_amount');

		// Store the Currency used by the Price Filter. This will be used to determine
		// how to convert filter range into base Currency
		var selected_currency = wc_aelia_currency_switcher_params.selected_currency;
		var exchange_rate_from_base = wc_aelia_currency_switcher_params.current_exchange_rate_from_base;

		var price_filter_currency_field = $('<input>')
			.attr('id', 'price_filter_currency')
			.attr('name', 'price_filter_currency')
			.attr('value', selected_currency)
			.hide();
		$('.price_slider_amount').append(price_filter_currency_field);

		// Min and Max prices for the Slider are always in Base Currency and must be converted
		var min_price = $price_slider_amount.find('#min_price').attr('data-min');
		var max_price = $price_slider_amount.find('#max_price').attr('data-max');

		// Convert data-min and data-max to selected currency
		min_price = Math.floor(min_price * exchange_rate_from_base);
		max_price = Math.ceil(max_price * exchange_rate_from_base);

		$price_slider_amount.find('#min_price').attr('data-min', min_price);
		$price_slider_amount.find('#max_price').attr('data-max', max_price);

		if(typeof woocommerce_price_slider_params != 'undefined') {
			// Slider Min and Max values are also in Base Currency
			if(woocommerce_price_slider_params.min_price) {
				woocommerce_price_slider_params.min_price = Math.floor(woocommerce_price_slider_params.min_price * exchange_rate_from_base);
			}
			if(woocommerce_price_slider_params.max_price) {
				woocommerce_price_slider_params.max_price = Math.ceil(woocommerce_price_slider_params.max_price * exchange_rate_from_base);
			}
		}
	}

	update_price_filter_slider();

});

