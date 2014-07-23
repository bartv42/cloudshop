jQuery(document).ready(function($){

	if(arePointersEnabled())
		showSubscriptionPointers();

	$('select#product-type').change(function(){
		if(arePointersEnabled())
			$('#product-type').pointer('close');
	});

	$('#_subscription_price, #_subscription_period, #_subscription_length').change(function(){
		if(arePointersEnabled()){
			$('.options_group.subscription_pricing').pointer('close');
			$('#product-type').pointer('close');
		}
	});

	function arePointersEnabled(){
		if($.getParameterByName('subscription_pointers')=='true')
			return true;
		else
			return false;
	}

	function showSubscriptionPointers(){
		$('#product-type').pointer({
			content: WCSPointers.typePointerContent,
			position: 'bottom',
			close: function() {
				if ($('select#product-type').val()==WCSubscriptions.productType){
					$('.options_group.subscription_pricing').pointer({
						content: WCSPointers.pricePointerContent,
						position: 'bottom',
						close: function() {
							dismissSubscriptionPointer();
						}
					}).pointer('open');
				}
				dismissSubscriptionPointer();
			}
		}).pointer('open');
	}

	function dismissSubscriptionPointer(){
		$.post( ajaxurl, {
			pointer: 'wcs_pointer',
			action: 'dismiss-wp-pointer'
		});
	}

});