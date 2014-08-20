/* JavaScript for Frontend pages */
jQuery(document).ready(function($) {
	// Invalidate cache of WooCommerce minicart when billing country changes.
	// This will ensure that the minicart is updated correctly
	supports_html5_storage = ('sessionStorage' in window && window['sessionStorage'] !== null);

	if(supports_html5_storage) {
		$('.checkout').delegate('#billing_country', 'change', function() {
			sessionStorage.removeItem('wc_fragments', '');
		});
	}

	// Hide the "Change country" button and submit the Widget form when billing
	// country changes
	$('.widget_wc_aelia_billing_country_selector_widget')
		.find('.change_country')
		.hide()
		.end()
		.delegate('.countries', 'change', function(event) {
			var widget_form = $(this).closest('form');
			$(widget_form).submit();
			event.stopPropagation();
			return false;
		});
});
