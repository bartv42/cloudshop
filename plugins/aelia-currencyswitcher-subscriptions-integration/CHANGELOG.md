# WooCommerce Currency Switcher - Subscriptions Integration

## Version 1.x
####1.2.6.140820
* Fixed minor bugs in user interface:
	* Removed notice messages from pricing interface for simple and variable subscriptions.
	* Fixed reference to text domain variable in variable subscriptions pricing interface.

####1.2.5.140819
* Updated logic used to for requirements checking.

####1.2.4.140724
* Removed deprecated method `WC_Aelia_CS_Subscriptions_Plugin::check_requirements()`.

####1.2.3.140715
* Fixed bug that prevented currency prices for non-subscription products from being saved.

####1.2.2.140704
* Fixed reference to root WC_Product class in Aelia\WC\CurrencySwitcher\Subscriptions\Subscriptions_Integration.

####1.2.1.140623
* Redesigned plugin to use Aelia Foundation Classes.

####1.2.0.140619
* Added support for variable subscriptions.

####1.1.8.140519-beta
* Added subscription coupons to the list of the coupons to be converted by the Currency Switcher.

####1.1.7.140419-beta
* Updated base classes.

####1.1.6.140414-beta
* Redesigned interface for manual pricing of simple subscriptions.

####1.1.5.140331-beta
* Implemented handling of manual prices for simple subscriptions.
* Cleaned up unneeded code.

####1.1.1.140331-beta
* Removed unneeded hook.

####1.1.0.140324-beta
* Implemented basic conversion of simple subscriptions.

####1.0.1.140318
* Updated base classes.

####1.0.0.140220
* Initial release.
