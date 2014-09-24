Aelia Foundation Classes for WooCommerce
===
**Tags**: woocommerce, plugins, aelia
**Requires at least**: 3.6
**Tested up to**: 4.0

Adds a set of convenience classes that can simplify the development of other plugins.

Description
---
The Aelia Foundation Classes add several classes that can simplify the development of plugins for WooCommerce. The available classes include:

###Namespace Aelia\WC

* IP2Location. Implements methods to determine visitor's country. Library relies on MaxMind GeoLite2 library.
* Order. An extended Order class, which includes methods to retrieve attributes of orders generated in multi-currency setups.
* Settings. Allows to manage the settings of a plugin. The class does not rely on WooCommerce Settings API.
* Settings_Renderer. Allows to render the settings interface for a plugin.
* Logger. A convenience Logger class.
* Aelia_Plugin. A base plugin class, which other plugins can extend. The class implements convenience methods to access plugin settings, WooCommerce settings, common paths and URLs, and automatically load CSS and JavaScript files when needed.
* Semaphore. Implements a simple semaphore logic, which can be used to prevent race conditions in operations that cannot run concurrently.

Global namespace

* Aelia_WC_RequirementsChecks. Implements the logic to use for requirement checking. When requirements are not met, a message is displayed to the site administrators and the plugin doesn't run. Everything is handled gracefully, and displayed messages are clear also to non-technical users.

This product includes GeoLite2 data created by MaxMind, available from
[http://www.maxmind.com](http://www.maxmind.com).

**Requirements**

* WordPress 3.6 or later.
* PHP 5.3 or later.
* WooCommerce 2.0.20 or later

Installation
---

1. Extract the zip file and just drop the contents in the ```wp-content/plugins/``` directory of your WordPress installation and then activate the plugin from **Plugins** page.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Done!

