#WooCommerce Currency Switcher
**Tags**: woocommerce, currency switcher, multiple currencies
**Requires at least**: 3.6
**Tested up to**: 3.8.1

Allows to set prices in multiple currencies. The plugin allows customers to see prices and place orders in any of the enabled currencies, thus avoiding conversion fees.

##Description

This Plugin allows you to configure a list of the Currencies you would like to accept. Such Currencies will then appear in a list, displayed as a widget, which your Users can use to choose their preferred Currency. Upon selection, the shop will be both displaying prices and and completing transactions in the new Currency. That is, the prices displayed on the shop will be the ones that the Client will pay upon completing the order.

Starting from version 1.9, when a User visits your shop for the first time, the plugin tries to automatically set the Currency to the one used in Visitor's Country. When such Currency is not enabled, the plugin selects the default one. Such feature can be disabled, if needed, and it relies on MaxMind GeoIP database service.

Since version 2.x, the Currency Switcher allows to manually set prices in different currencies for each product, without having to rely on an exchange rate.

This product includes GeoLite data created by MaxMind, available from http://www.maxmind.com.

##Requirements

* WordPress 3.6+
* PHP 5.3+
* PHP Extensions
    * CURL
* WooCommerce 2.0.x/2.1.x/2.2.x

##Installation

1. Extract the zip file and drop the contents in the wp-content/plugins/ directory of your WordPress installation.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Currency Switcher Options to configure the enabled Currencies and related exchange rates. IMPORTANT: exchange rates MUST BE SET, or the currency switcher won't work properly.
4. Go to Appearance > Widgets and display the WooCommerce Currency Switcher widget wherever you like. Alternatively, you can display the widget usinga shortcode. Please refer to the instructions on the Currency Switcher Options page for more details.
5. That's it! Now your Customers will be able to switch currency when they visit your shop, and place the orders in their favourite Currency.

##Customisation
You can customise the look and feel of the currency selector widget provided with the plugin in several ways:
* You can set its type to "dropdown" or "buttons", depending on how you would like to display the available currencies.
* You can alter the CSS styles which apply to the widget by following the documentation that you can find in our knowledge base, in the following article: [How can I customise the look and feel of the Currency Selector widget?](https://aelia.freshdesk.com/support/solutions/articles/121622-how-can-i-customise-the-look-and-feel-of-the-currency-selector).
* You can override the templates used to render the widget by copying any of the following files to your theme, in `{your theme folder}/woocommerce-aelia-currencyswitcher` folder:
  * currency-selector-widget-buttons.php (used to display the "dropdown" style selector).
	* currency-selector-widget-dropdown.php (used to display the "buttons" style selector).

###IMPORTANT
The Currency Switcher allows you to potentially receive payments in all Currencies supported by WooCommerce. However, it's up to you to ensure that the Payment Gateway(s) you use are configured to accept the Currencies you enabled.

## Change Log
See file *CHANGELOG.md*.
