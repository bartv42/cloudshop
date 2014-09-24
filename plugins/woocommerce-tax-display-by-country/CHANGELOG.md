# Aelia Tax Display by Country - Change Log

## Version 1.x
####1.5.12.140924
* Tweak to force WooCommerce cart to take customer's location into account for tax calculation.
* The country selected using the widget is now stored agains the user object.

####1.5.11.140825
* Added processing of widget titles through localisation functions.

####1.5.10.140819
* Updated logic used to for requirements checking.

####1.5.9.140805
* Improved user interface:
	* Added placeholder for price suffix field.
	* Added filters to display price suffix for shipping on cart and checkout.

####1.5.8.140729
* Fixed bug that caused the settings page to always be rendered empty.
* Fixed bug that caused the tax display settings not to be applied correctly during ajax calls.

####1.5.7.140715
* Fixed minor bug in Settings and Settings_Renderer classes, which caused a warning to be raised when the plugin ran for the first time.

####1.5.6.140714
* Improved documentation.

####1.5.4.140619
* Modified loading of Aelia_WC_RequirementsChecks class to work around quirks of Opcode Caching extensions, such as APC and XCache.

####1.5.3.140611
* Improved compatibility with Codestyling Localization plugin.

####1.5.2.140520
* Fixed bug in logic that stored the selected billing country in checkout page.

####1.5.1.140511
* Removed unneeded code.
* Changed billing country field name to aelia_billing_country, for easier integration with the Currency Switcher.

####1.5.0.140509
* Refactored plugin to use AFC for WooCommerce.

####1.2.0.140502
* Added possibility to specify a price suffix for each tax display rule.

####1.1.2.140427
* Improved UI of plugin settings page.
* Fixed incompatibility of drag & drop feature with Firefox.

####1.1.1.140427
* Fixed bug in geolocation detection.

####1.1.0.140419
* Added billing country selector widget.
* Fixed shortcode for billing country selector.

####1.0.0.140418
* Completed UI for plugin settings page.

## Version 0.x
####0.5.1.140417
* Updated Geolocation database to new format.

####0.5.0.140417
* Implemented plugin configuration page.
* Implemented logic to override the tax display settings depending on customer's country.
* Added frontend and backend scripts and styles.

####0.1.1.140416
* Implemented draft of plugin's settings page.
* Added link to settings page to WooCommerce menu.

####0.1.0.140416
* First plugin draft.
