# Aelia Currency Switcher - Change Log

## Version 3.x
####3.4.5.140717
* Modified logic of "currency by billing country" feature, so that the default GeoIP currency is taken when a billing country uses an unsupported currency.
* Optimised semaphore logic used for auto-updates to improve performance.

####3.4.3.140717
* Added support for bulk edit of variation prices.

####3.4.2.140711
* Updated GeoIP database.
* Set visibility of `WC_Aelia_CurrencySwitcher::is_valid_currency()` to public, to simplify integration with addons.

####3.4.1.140707
* Fixed bug in conversion of coupons.
* Removed legacy function used for WooCommerce 1.6 (no longer supported).

####3.4.0.140706
* Added possibility to specify a currency symbol for each currency.
* Redesigned Currency Switcher Options page.
* Added world currencies to the list of the available currencies.

####3.3.14.140704
* Fixed bug in loading of auxiliary functions.
* Removed unneeded legacy functions.

####3.3.13.140704
* Fixed bug in handling of currency decimals.
* Removed unneeded warning message, originally displayed when the detected currency for a user was not amongst the enabled ones.

####3.3.12.140626
* Added "wc_aelia_ip2location_country_code" filter, to allow overriding the country code detected by the plugin.
* Updated Geolite database file.

####3.3.11.140619
* Improved compatibility with Subscription Integration Add-on.
* Fixed bug that caused incorrect formatting of prices when base currency was active.

####3.3.10.140615
* Fixed loading of JavaScript for Billing Country Selector widget.

####3.3.9.140613
* Fixed bug in JavaScript that handles the price filter widget.
* Improved UI for Currency Selection Options tab in plugin settings page.
* Fixed bug that caused the PayPal checkout to fail in some circumstances.

####3.3.8.140612
* Improved compatibility with Subscription Integration Add-on:
	* Hidden variation product prices.
* Added generic conversion function for unsupported product types.

####3.3.7.140611
* Fixed bug that caused order line totals in base currency not to be calculated in some circumstances.

####3.3.6.140530
* Fixed "Sales overview - This month's sales" report.

####3.3.5.140529
* Optimised auto-update logic to reduce the amount of queries.

####3.3.4.140528
* Fixed bug that raised a warning during geolocation resolution.

####3.3.3.140526
* Fixed bug that caused a notice error to appear in Dashboard/Recent Orders widget in WooCommerce 2.0.

####3.3.2.140520
* Fixed bug that prevented the display of price suffix for products on sale.
* Fixed CSS for currency selector widget (dropdown) and billing country widget (dropdown).
* Fixed bug in logic that stored the selected billing country in checkout page.

####3.3.1.140520
* Completed implementation of "currency by billing country" feature:
	* Added settings page.
	* Added check to determine if feature is enabled.
	* Added conditional loading of billing country selector widget.
* Added instructions on how to use the billing country selector widget.

####3.3.0.140513
* Implemented selection of currency using billing country.
	* Added billing country selector widget.
	* Added logic to store and retrieve the billing country.
* Cleaned up code.

####3.2.36.140519
* Added "wc_aelia_cs_coupon_types_to_convert" filter. This filter will allow to alter the list of coupon types whose amount should be converted in the selected currency.

####3.2.35.140512
* Updated plugin metadata, for integration with Mijireh multi-currency plugin.

####3.2.34.140511
* Improved logic to determine when to run auto-updates.

####3.2.33.140506
* Optimised auto-update code. Added check to improve performance and reduce the chances of deadlocks.
* Updated GeoIP database.

####3.2.32.140429
* Fixed bug that caused payment gateways to be filtered by active currency, rather than order currency, when paying for an existing order.

####3.2.30.140425
* Fixed minor bug that caused a notice message to appear on checkout page in some circumstances.

####3.2.29.140423
* Altered currency selector widget. Added "active" CSS class to the button matching the active currency.

####3.2.28.140414
* Improved error messages when plugin requirements are not met.

####3.2.27.140409
* Altered WC_Aelia_CurrencyPrices_Manager to facilitate integration with Subscriptions.

####3.2.26.140403
* Made plugin more flexible, so that it can be installed in a directory different from "woocommerce-aelia-currencyswitcher".
* Fixed bug in Customer List report.

####3.2.25.140331
* Removed minor "strict standards" messages.

####3.2.24.140327
* Defaulted all log messages as "debug" by default, to reduce the amount of logging.

####3.2.23.140326
* Refactored logging mechanism to remove conflict with WooCommerce auto-update.

####3.2.22.140324
* Added and modified filters:
	* Replaced "wc_currencyswitcher_product_admin_view_load" with "wc_aelia_currencyswitcher_product_pricing_view_load".
	* Replaced "wc_currencyswitcher_product_convert_callback" with "wc_aelia_currencyswitcher_product_convert_callback".
	* Added "wc_aelia_currencyswitcher_variation_product_pricing_view_load" and "wc_aelia_currencyswitcher_simple_product_admin_view_load".
* Refactored product pricing UI to allow integration with WooCommerce Subscriptions.
* Added new events:
	* "wc_aelia_currencyswitcher_recalculate_cart_totals_before", fired before the recalculation of cart totals.
	* "wc_aelia_currencyswitcher_recalculate_cart_totals_after", fired after the recalculation of cart totals.

####3.2.21.140320
* Extended Aelia_Order class to maintain compatibility with both WooCommerce 2.1.x and 2.0.x.

####3.2.20.140319
* Added explicit loading of WooCommerce Admin CSS when rendering plugin settings page.

####3.2.19.140319
* Corrected invalid reference to settings key in WC_Aelia_CurrencySwitcher_Settings class.
* Refactored logic used to instantiate a WooCommerce Logger.
* Removed notice messages.

####3.2.18.140318
* Added "wc_currencyswitcher_product_admin_view_load" filter, to allow overriding the views being loaded by WC_Aelia_CurrencyPrices_Manager class.
* Reduced the amount of logging when debug mode is disabled.

####3.2.17.140313
* Fixed display issues caused by jQuery Chosen library.

####3.2.16.140312
* Commented out SCRIPT_DEBUG line.

####3.2.15.140311
* Updated Chosen library.

####3.2.14.140310
* Improved compatibility with WooCommerce 2.1:
	* Fixed bug that prevented price suffix from being displayed for variable products.
* Improved error handling in Admin UI integration class.

####3.2.13.1402128
* Removed notice related to WooCommerce 2.1 incompatibilities.

####3.2.12.1402127
* Improved compatibility with WooCommerce 2.1:
	* Implemented "Sales by product" report.
	* Implemented "Sales by category" report.

####3.2.11.1402127
* Highlighted order total in base currency in orders list page, to clarify what it represents.
* Fixed display of order currency in order details page.
* Improved description of Exchange Rates Settings in plugin's options page.

####3.2.10.1402126
* Fixed calculation of order totals in base currency.
* Improved session management to making it more compatible with WooCommerce 2.1.x.
* Added display of order total in base currency in orders list page.

####3.2.8.1402124
* Implemented preliminary (partial) compatibility with Product Addons plugin:
	* The plugin is now preserving the absolute values of product addons (i.e. they are not converted) instead of discarding them when a product is added to the cart.
* Improved compatibility with Dynamic Pricing plugin:
	* Improved handling of global Advanced Category rules.

####3.2.7.1402123
* Improved compatibility with WooCommerce 2.1:
	* Updated session handler to use new methods introduced in WooCommerce 2.1.
	* Fixed bug in loading session data, caused by WooCommerce not setting the session cookie until the cart was loaded.

####3.2.6.1402121
* Improved compatibility with Dynamic Pricing plugin:
	* Improved handling of global Order Totals rules.
	* Improved handling of global Category rules.

####3.2.5.1402120
* Fixed bug in savings of settings on first install of the plugin.
* Improved validation of settings related to exchange rates providers.

####3.2.4.140219
* Added locking mechanism to prevent plugin's autoupdate code from causing race conditions.
* Improved logging of minor error conditions, such as an invalid IP address during geolocation detection.

####3.2.3.140218
* Fixed bug in rendering of "Add Variation" user interface on WooCommerce 2.0.x.
* Fixed bug in Aelia\Logger class.
* Added debug mode information in Support section, on plugin settings page.

####3.2.2.140218
* Improved compatibility with WooCommerce 2.1:
	* Fixed bug that caused prices for variable products to be displayed incorrectly when "I will enter prices inclusive of tax" and "display product prices excluding tax" were both enabled.

####3.2.1.140215
* Added logging mechanism.
* Fixed "on the fly" calculation of order tax and order shipping tax.
* Improved compatibility with WooCommerce 2.1:
	* Implemented logic to override standard reports to return consistent data in a multi-currency environment.
	* Implemented base sales report.
	* Implemented sales by date report.
	* Implemented customers list report.
	* Implemented tax by date report.
* Added autoupdate script to recalculate order values in base currency.

####3.2.0.140214
* Restructured reporting classes.
* Implemented override of "WooCommerce Status" dashboard widget.

####3.1.3.140214
* Improved compatibility with WooCommerce 2.1:
	* Fixed bug that prevented the checkout from completing correctly in some circumstances. Issue was caused by a clash with new WC_Order::get_order_currency() method.

####3.1.2.140213
* Fixed bug in calculation of min and max variation prices when the base currency is selected.

####3.1.1.140212
* Added filter "wc_aelia_currencyswitcher_country_currencies", to allow altering the currency associated with each country.

####3.1.0.140212
* Added possibility of choosing the currency when manually creating orders in the backend.
* Fixed bug in display of order currencies in Recent Orders dashboard widget.
* Improved compatibility with WooCommerce 2.1:
	* Fixed bug in display of prices for variable products in products list page, which was caused by the new logic added to WooCommerce 2.1.

####3.0.6.140212
* Removed total in base currency from Recent Orders dashboard widget for orders that were placed in base currency.

####3.0.5.140211
* Changed versioning system to reduce confusion.
* Added warning related to the compatibility with WooCommerce 2.1.
* Added handling of multiple IP addresses in X-Forwarded-For header.

####3.0.4.140210-WC21-Beta
* Improved compatibility with WooCommerce 2.1:
	* Added new filters in WC_Aelia_CurrencyPrices_Manager, to handle the new way prices are retrieved for variable products.

####3.0.3.140210-Beta
* Improved compatibility with WooCommerce 2.1:
	* Fixed bug in display of sale prices for variable products.

####3.0.2.140210-Beta
* Improved compatibility with WooCommerce 2.1:
	* Fixed issue in displaying order totals in orders list page.
	* Refactored logic to override WooCommerce integrations, to ensure proper loading of Google Analytics integration with support for multiple currencies.

####3.0.1.140210-Beta
* Improved compatibility with WooCommerce 2.1:
	* Modified Aelia_Order class to use WC2.1 functions to retrieve order currency, when available, while maintaining compatibility with WC2.0.x.
* Removed unneeded file.

####3.0.0.140206-Beta
* Improved compatibility with WooCommerce 2.1:
	* Added check for existence of WC_Google_Analytics.

## Version 2.x
####2.6.3.140208
* Fixed CSS for admin pages.
* Fixed bug in price conversion for product bundles.
* Fixed minor bug in accessing an order property in "pay" page.

####2.6.2.140205
* Optimised data retrieval from Open Exchange Rates.
* Overridden WooCommerce dashboard sales report widget to display totals in base currency.
* Overridden WooCommerce recent orders report widget to display totals in both order currency and base currency.

####2.6.1.140204
* Fixed bug in rendering of currency selector widget.
* Fixed bug in retrieval of currently selected currency.

####2.6.0.140131
* Implemented shortcode for currency selector widget.
* Implemented dynamically loaded templates for the currency selector widget.
* Improved documentation in both README file and Currency Switcher options page.

####2.5.11.140129
* Improved loading of JavaScript and CSS files to reduce clashes with other plugins

####2.5.10.140124
* Fixed logic used to load the code to update the price filter widget when the selected currency changes

####2.5.9.140122
* Removed filter "wc_aelia_cs_get_selected_currency" as it's redundant. Filter "woocommerce_currency" can be used instead

####2.5.8.140117
* Fixed bug in logic selecting the default currency for geolocation

####2.5.7.140115
* Added wc_aelia_cs_get_selected_currency filter, to allow 3rd parties to retrieve and/or alter the selected currency
* Improved compatibility with Opulence theme

####2.5.6.140113
* Added wc_aelia_cs_enabled_currencies filter, to allow 3rd parties to retrieve the list of enabled currencies

####2.5.5.140110
* Fixed rendering bug in WC_Aelia_CurrencySwitcher::get_shipping_to_display()
* Updated GeoIP database

####2.5.4.140107
* Improved code that renders plugin settings page to reduce clashes with other plugins

####2.5.3.131228
* Removed debugging code from class WC_Aelia_Google_Analytics_Integration

####2.5.2.131227
* Added new filter: wc_aelia_currencyswitcher_visitor_ip

####2.5.1.131219
* Fixed minor bug that caused WC_Aelia_CurrencySwitcher_Settings::get_enabled_currencies() not to return an array in some edge conditions

####2.5.0.131211
* Added integration with WooCommerce Bundles plugin

####2.4.12.131125
* Added integration with SkyVerge KISSMetrics plugin

####2.4.10.131118
* Added default geolocation currency setting. It's used to specify which currency should be used by default when the one detected by geolocation feature is not available

####2.4.8.131110
* Fixed minor bug introduced in v2.4.8.131108, which caused products to be displayed as "free" when they were put on sale

####2.4.7.131108
* Fixed minor bug that caused the incorrect original price to be displayed when a product was on sale with a sale price of zero

####2.4.6.131103
* Improved compatibility with category discounts introduced by Dynamic Pricing Plugin

####2.4.5.131102
* Relaxed check used to determine if a payment plugin is enabled. This increases compatibility with 3rd party payment plugins, such as MercadoPago

####2.4.4.131029
* Modified Google Analytics order tracking to ensure that order currency is recorded correctly
* Improved error handling in price filter JS file

####2.4.3.131028
* Removed unused hook handler
* Improved hint for exchange rate markup field

####2.4.2.131026
* Fixed bug in loading the price filter override script, which caused other scripts to fail during load in some circumstances

####2.4.1.131023
* Fixed bug in conversion of prices for external products
* Corrected labels in plugin configuration page

####2.4.0.131022
* Fixed bug in detection of payment step when paying a past order

####2.3.9.131022
* Fixed bug that caused the currently selected currency, instead of the order currency, to be retrieved by the payment gateway when paying for an order placed in the past and left unpaid

####2.3.8.131021
* Updated GeoIP library to v1.24

####2.3.7.131017
* Corrected checking of JavaScript variables

####2.3.6.131016
* Updated GeoIP library to v1.22
* Corrected loading of JavaScript files used by the currency selector widget

####2.3.5.131016
* Updated GeoIP library to v1.21

####2.3.4.131016
* Updated GeoIP library to v1.20

####2.3.3.131016
* Improved checking of plugin requirements to prevent fatal errors upon activation

####2.3.2.131015
* Improved handling of errors while fetching exchange rates from remote providers

####2.3.0.131015
* Fixed bug in calculation of shipping taxes

####2.2.17.131014
* Fixed minor bug that prevented the price filter widget from working properly

####2.2.15.131011
* Fixed minor bug in rendering of minimum price for grouped products

####2.2.14.131008
* Improved detection of visitor's IP address when site is behind a reverse proxy

####2.2.13.131003
* Corrected bug that a warning to be raised when some prices were left empty on Variable Products

####2.2.12.131002
* Corrected bug that caused Product Search field not to work properly in Order Add/Edit page

####2.2.11.131002
* Handled edge case in which a product is on sale and no regular price is specified

####2.2.10.131001
* Fixed minor bug that caused a warning to be raised when product prices were left empty

####2.2.8.130927
* Fixed minor bug that caused a warning to be raised when the number of decimals for a currency set in Currency Switcher configuration was not valid

####2.2.7.130927
* Rewritten code that handles compatibility with Cart Notices plugin
* Added code to handle absolute discounts generated by Dynamic Pricing plugin

####2.2.6.130926
* Fixed bug that caused an error to be thrown, in some circumstances, when the Currency Switcher plugin was installed on a site which already contained several products

####2.2.5.130925
* Rewritten price conversion logic to improve performance, stability and compatibility
* Improved compatibility with Dynamic Pricing plugin
* Fixed bug that caused manually entered prices to be ignored, in some circumstances, for variable products

####2.1.0.130915
* First official release of version 2.x

####2.0.24.130903-Beta
* Merged fixes and improvements from version 1.9.24.130903

####2.0.0.130820-Beta
* Implemented support for currency pricing for each product

## Version 1.x

####1.9.30.130915
* Refactored settings classes

####1.9.29.130914
* Fixed minor bug related to the display of the schedule for automatic update of exchange rates

####1.9.28.130912
* Fixed minor bug that caused a warning to be displayed when plugin settings page was loaded
* Fixed error in rendering the Support section in plugin settings page which caused the Save buttons to be linked to dev.pathtoenlightement.net contact page

####1.9.27.130911
* Added Support section in plugin settings page, to make it more convenient for the User to contact us

####1.9.26.130908
* Fixed minor bug that interfered with the "enhanced country select boxes" feature of WooCommerce

####1.9.25.130905
* Improved compatibility with Cart Notices plugin

####1.9.24.130903
* Fixed bug that caused shipping details to be displayed incorrectly in order review page and confirmation email
* Updated GeoIP library
* Optimised code that retrieves the currency used to place an order

####1.9.23.130829
* Added support for markup to be added to an exchange rate
* Refactored Settings class to simplify maintenance and increase flexibility
* Renamed WC_Aelia_CurrencySwitcher::set_currency_symbol() to WC_Aelia_CurrencySwitcher::woocommerce_currency

####1.9.22.130827
* Improved handling of unexpected conditions when exchange rates cannot be retrieved from provider

####1.9.21.130823
* Added hiding of database errors unrelated to the Currency Switcher when WP_DEBUG is not enabled

####1.9.20.130821
* Fixed minor bug that caused the PayPal Pro payment gateway not to be displayed when the Admin area was accessed without using HTTPS protocol

####1.9.19.130820
* Fixed issue with formatting of Shipping and Tax Totals when displaying past orders

####1.9.18.130816
* Implemented support for decimals for each currency

####1.9.17.130814
* Implemented filtering of payment gateways based on selected currency

####1.9.16.130814
* Implemented tabbed interface for plugin settings

####1.9.15.130809
* Improved compatibility with WPMU
* Fixed minor bug that caused update functions to be called when not necessary

####1.9.14.130731
* Fixed minor incompatibility with WPML and WooCommerce Multilingual that caused minicart totals not to be updated correctly

####1.9.13.130729
* Fixed minor bug that caused a warning to appear in certain circumstances when placing a new order

####1.9.12.130726
* Fixed incompatibility with WooCommerce 1.6 related to price formatting
* Rewritten logic used to format prices on product pages
* Updated GeoIP library to v1.16, modified to prevent clashes with PHP geoip extension

####1.9.11.130724
* Fixed bug that caused prices to be formatted incorrectly when WooCommerce was set to display the currency symbol on the right of the price

####1.9.10.190713
* Improved logic to display messages generated by the plugin to make it more polished and user friendly

####1.9.9.190713
* Fixed bug that caused currency selection not to work in some circumstances

####1.9.8.170713
* Improved error handling during currency conversion. New handler displays more details about currency conversion errors that may eventually occur
* Fixed bug that caused an error to be raised when a new order was placed

####1.9.7.170713
* Improved error handling during calculation of past orders' totals in base currency

####1.9.6.160713
* Fixed minor bug with currency symbols in generation of order receipts

####1.9.5.150713
* Rewritten Geolocation feature using MaxMind GeoIP library and GeoLite database
* Improved validation of plugin options upon Save
* Fixed bug in Exchange Rates retrieval that occurred occasionally when "Save and Update Exchange Rates" was clicked and Open Exchange Rates was selected as a provider
* Improved requirement checking

####1.9.3.130712
* Fixed bug in calculation of minimum requirements for shipping methods

####1.9.2.130711
* Added requirement checking when plugin is activated
* Added mechanism to automatically apply updates required by new plugin versions
* Fixed minor bug in currency display on Order Edit page

####1.9.1.130626
* Fixed minor bug that caused a warning to be displayed when using the Currency Switcher in some configurations
* Fixed bug in validation of Currency Geolocation parameters

####1.9.130624
* Added automatic selection of Currency based on Visitors' Country (detected using their IP Address)

####1.8.7.130613
* Fixed minor bug that caused a warning to be raised when viewing past orders, depending on environment configuration

####1.8.6.130610
* Fixed incompatibility with Menu Cart Plugin and Wootique theme

####1.8.5.130602
* Fixed bug in calculation of shipping charges

####1.8.3.130601
* Improved support for coupons in WooCommerce 1.6

####1.8.2.130601
* Fixed bug in currency conversion which occurred during some Ajax calls
* Improved support for coupons in WooCommerce 2.0

####1.8.130601
* Fixed bug in currency conversion during cart update on Checkout page

####1.7.130531
* Removed woocommerce_price_filter hook (no longer needed)

####1.6.130530
* Added support for Variable Products
* Corrected handling of formatted prices over the value of 999.99

####1.5.130529
* Fixed bug that prevented the Mini Cart widget from displaying prices in correct currency in WooCommerce 2.0+

####1.4.130529
* Fixed bug that caused Sale prices to be displayed in base currency, rather than the one selected by the User

####1.3.130528
* Fixed bug that prevented WooCommerce's Price Filter Slider widget from working correctly when switching currency

####1.2.130526
* Optimised code and improved documentation

####1.2.130523
* Ensured that prices are not converted when managing products in the Admin section

####1.1.130522
* Bug fixes

####1.0.130518
* Improved handling of misconfigured Currencies
* Fixed bug in Open Exchange Rates model, which prevented the rates from being retrieved in certain conditions
* Improved UI

####1.0.130517
* Initial Release
