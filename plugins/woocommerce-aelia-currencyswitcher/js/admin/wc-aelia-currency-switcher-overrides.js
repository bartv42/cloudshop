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
