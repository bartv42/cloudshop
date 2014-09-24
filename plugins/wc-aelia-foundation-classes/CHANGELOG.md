Aelia Foundation Classes for WooCommerce
===

###Version 1.x
####1.1.3.140910
* Updated reference to `plugin-update-checker` library.

####1.1.2.140909
* Updated `readme.txt`.

####1.1.1.140909
* Cleaned up build file.

####1.1.0.140909
* Added automatic update mechanism.

####1.0.12.140908
* Added `Aelia_SessionManager::session()` method, as a convenience to retrieve WC session

####1.0.11.140825
* Fixed minor bug in `IP2Location` class that generated a notice message.

####1.0.10.140819
* Fixed logic used to check and load plugin dependencies in `Aelia_WC_RequirementsChecks` class.

####1.0.9.140717
* Refactored semaphore class:
	* Optimised logic used for auto-updates to improve performance.
	* Fixed logic to determine the lock ID for the semaphore.

####1.0.8.140711
* Improved semaphore used for auto-updates:
	* Reduced timeout to forcibly release a lock to 180 seconds.
* Modified loading of several classes to work around quirks of Opcode Caching extensions, such as APC and XCache.

####1.0.7.140626
* Added geolocation resolution for IPv6 addresses.
* Updated Geolite database.

####1.0.6.140619
* Modified loading of Aelia_WC_RequirementsChecks class to work around quirks of Opcode Caching extensions, such as APC and XCache.

####1.0.5.140611
* Corrected loading of plugin's text domain.

####1.0.4.140607
* Modified logic used to load main class to allow dependent plugins to load AFC for unit testing.

####1.0.3.140530
* Optimised auto-update logic to reduce the amount of queries.

####1.0.2.140509
* Updated Composer dependencies.
* Removed unneeded code.
* Corrected reference to global WooCommerce instance in Aelia\WC\Aelia_SessionManager class.

####1.0.1.140509
* First public release
