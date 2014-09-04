=== WooCommerce Direct Variation Link  ===
Contributors: wpbackoffice
Tags: woocommerce, product variations, direct link, products, variable products, 
Donate Link: http://wpbackoffice.com/plugins/woocommerce-direct-variation-link/
Requires at least: 2.3
Tested up to: 3.81
Stable tag: 1.0.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Link directly to a specific WooCommerce product variation using get variables (yoursite.com/your-single-product?size=small&color=blue).

== Description ==

Link directly to a specific WooCommerce product variation using get variables - yoursite.com/your-single-product/?size=small&color=blue. This is useful if you want to send an email to customers and send them directly to a specific variation without them having to enter it in themselves.

Usage/Examples: 

Attach: ‘?[variation_name]=[variation_value]‘ to the end of your product link to have the variations automatically appear on the user’s screen.

* mysite.com/product/happy-ninja/?color=blue
* mysite.com/product/happy-ninja/?color=blue&size=small (additional variations should be separated by '&')
* mysite.com/product/happy-ninja/?color=blue+green (where the variation value is "Blue Green" with the space replaced by a '+')

Features:

* No configuration needed, just install and start using
* Supports an infinite number of variations
* Fails gracefully, if the option or value doesn't exist, the default value will appear instead
* Default selected variations work if no product is specified in url
* 100% case insensitive
* Works with any variation settings you have ie. custom images, prices etc.

[Plugin's Official Documentation and Support Page](http://wpbackoffice.com/plugins/woocommerce-direct-variation-link/)

== Installation ==

Automatic WordPress Installation

1. Log-in to your WordPress Site
2. Under the plugin sidebar tab, click ‘Add New’
3. Search for ‘WooCommerce Direct Variation Link'
4. Install and Activate the Plugin
5. That's it, you should be able to use get variables with your variable products now

Manual Installation

1. Download the latest version of the plugin from WooCommerce Direct Variation Link Wordpress page.
3. Uncompress the file
4. Upload the uncompressed directory to ‘/wp-content/plugins/’ via FTP
5. Active the plugin from your Wordpress backend ‘Plugins -> Installed Plugins’
6. That's it, you should be able to use get variables with your variable products now

== Changelog ==

= 1.0.2 =
* Removed the need to put 'pa_' infront variables.

= 1.0.1 =
* Contributor consolidation.

= 1.0.0 =
* Initial Commit
