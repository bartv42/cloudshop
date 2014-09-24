<?php if(!defined('ABSPATH')) exit; // Exit ifaccessed directly

/**
 * Handles the settings for the Currency Switcher plugin and provides convenience
 * methods to read and write them.
 */
class WC_Aelia_CurrencySwitcher_Settings extends WC_Aelia_Settings {
	/*** Settings Key ***/
	// @var string The key to identify plugin settings amongst WP options.
	const SETTINGS_KEY = 'wc_aelia_currency_switcher';

	/*** Settings fields ***/
	// @var string The name of "enabled currencies" field.
	const FIELD_ENABLED_CURRENCIES = 'enabled_currencies';
	// @var string The name of "exchange rates" field.
	const FIELD_EXCHANGE_RATES = 'exchange_rates';
	// @var string The name of "exchanges rates update enabled" field.
	const FIELD_EXCHANGE_RATES_UPDATE_ENABLE = 'exchange_rates_update_enable';
	// @var string The name of "exchanges rates update schedule" field.
	const FIELD_EXCHANGE_RATES_UPDATE_SCHEDULE = 'exchange_rates_update_schedule';
	// @var string The name of "last update of Exchange Rates" field
	const FIELD_EXCHANGE_RATES_LAST_UPDATE = 'exchange_rates_last_update';
	// @var string The name of "Exchange Rates Provider" field
	const FIELD_EXCHANGE_RATES_PROVIDER = 'exchange_rates_provider';
	// @var string The name of "Open Exchange Rates API Key" field
	const FIELD_OPENEXCHANGE_API_KEY = 'openexchange_api_key';
	// @var string The name of "IP Geolocation enabled" field
	const FIELD_IPGEOLOCATION_ENABLED = 'ipgeolocation_enabled';
	// @var string The default currency to use if the geolocation fails, or if the visitor comes from a country using an unsupported currency
	const FIELD_IPGEOLOCATION_DEFAULT_CURRENCY = 'ipgeolocation_default_currency';
	// @var string The name of "currency payment gateways" field.
	const FIELD_PAYMENT_GATEWAYS = 'payment_gateways';
	// @var string The name of "allow currency selection by billing country" field.
	const FIELD_CURRENCY_BY_BILLING_COUNTRY_ENABLED = 'currency_by_billing_country_enabled';
	// @var string The name of the "debug mode" field
	const FIELD_DEBUG_MODE_ENABLED = 'debug_mode';

	// @var string The default Exchange Rates Model class to use when the configured one is not valid.
	const DEFAULT_EXCHANGE_RATES_PROVIDER = 'WC_Aelia_WebServiceXModel';

	// @var array A list of the currencies supported by WooCommerce
	private $_woocommerce_currencies;

	// @var WC_Aelia_ExchangeRatesModel The model which will retrieve the latest Exchange Rates.
	private $_exchange_rates_model;

	// @var array The definition of the hook that will be called to update the Exchange Rates on a scheduled basis.
	protected $_exchange_rates_update_hook = 'exchange_rates_update_hook';

	// @var array A fist of available Exchange Rates Models.
	protected $exchange_rates_models = array();

	// @var array Stores the price decimals of the currencies. Used for caching purposes.
	protected $prices_decimals = array();

	// @var int The number of decimals configured in WooCommerce.
	public $woocommerce_currency_decimals = 2;

	/**
	 * Registers a model used to retrieve Exchange Rates.
	 */
	protected function register_exchange_rates_model($class_name, $label) {
		if(!class_exists($class_name) ||
			 !in_array('IWC_Aelia_ExchangeRatesModel', class_implements($class_name))) {
			throw new Exception(sprintf(__('Attempted to register class "%s" as an Exchange Rates ' .
																		 'model, but the class does not exist, or does not implement '.
																		 'IWC_Aelia_ExchangeRatesModel interface.', $this->textdomain)));
		}

		$model_id = md5($class_name);
		$model_info = new stdClass();
		$model_info->class_name = $class_name;
		$model_info->label = $label;
		$this->exchange_rates_models[$model_id] = $model_info;
	}

	/**
	 * Registers all the available models to retrieve Exchange Rates.
	 */
	protected function register_exchange_rates_models() {
		$this->register_exchange_rates_model('WC_Aelia_WebServiceXModel', __('WebServiceX', $this->textdomain));
		$this->register_exchange_rates_model('WC_Aelia_OpenExchangeRatesModel', __('Open Exchange Rates', $this->textdomain));
	}

	/**
	 * Returns the options related to the various Exchange Rates providers
	 */
	public function exchange_rates_providers_options() {
		$result = array();
		foreach($this->exchange_rates_models as $key => $properties) {
			$result[$key] = get_value('label', $properties);
		}

		return $result;
	}

	/**
	 * Returns the Key used to register an Exchange Rates Model. This function
	 * is mainly used to identify which Sections in the Options Page contain
	 * settings for each Exchange Rates Model.
	 *
	 * @param string class_name The class of the Exchange Rates Model.
	 * @return string|null The key used to register the Model, or null ifnot found.
	 */
	public function get_exchange_rates_model_key($class_name) {
		foreach($this->exchange_rates_models as $key => $properties) {
			if(get_value('class_name', $properties) == $class_name) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Get the instance of the Exchange Rate Model to use to retrieve the rates.
	 *
	 * @param string key The key identifying the Exchange Rate Model Class.
	 * @param array An array of Settings that can be used to override the ones
	 * currently saved in the configuration.
	 * @param string default_class The Exchange Rates Model class to use as a default.
	 * @return WC_Aelia_ExchangeRatesModel.
	 */
	protected function get_exchange_rates_model_instance($key,
																											 array $settings = null,
																											 $default_class = self::DEFAULT_EXCHANGE_RATES_PROVIDER) {
		$model_info = get_value($key, $this->exchange_rates_models);
		$model_class = get_value('class_name', $model_info, $default_class);

		return new $model_class($settings);
	}

	/**
	 * Returns the instance of the Exchange Rate Model.
	 *
	 * @param array settings An array of Settings.
	 * @return WC_Aelia_ExchangeRatesModel.
	 */
	protected function exchange_rates_model(array $settings = array()) {
		if(empty($this->_exchange_rates_model)) {
			$exchange_rates_model_key = get_value(self::FIELD_EXCHANGE_RATES_PROVIDER,
																						$settings,
																						$this->current_settings(self::FIELD_EXCHANGE_RATES_PROVIDER));
			$this->_exchange_rates_model = $this->get_exchange_rates_model_instance($exchange_rates_model_key,
																																							$settings);
		}

		return $this->_exchange_rates_model;
	}

	/**
	 * Indicates if debug mode is active.
	 *
	 * @return bool
	 */
	public function debug_mode() {
		return $this->current_settings(self::FIELD_DEBUG_MODE_ENABLED, false);
	}

	/**
	 * Returns the base currency used by WooCommerce.
	 *
	 * @return string A currency code.
	 */
	public function base_currency() {
		if(empty($this->_base_currency)) {
			$this->_base_currency = get_option('woocommerce_currency');
		}

		return $this->_base_currency;
	}

	/**
	 * Returns the default currency to use when Geolocation featue is eanbled and
	 * visitor's currency doesn't match any of the enabled ones.
	 *
	 * @return string A currency code.
	 */
	public function default_geoip_currency() {
		if(empty($this->_default_geoip_currency)) {
			// Get the configured default currency, defaulting to the base currency if
			// none was set
			$this->_default_geoip_currency = $this->current_settings(self::FIELD_IPGEOLOCATION_DEFAULT_CURRENCY,
																															 $this->base_currency());
			// Default to base currency if the one set as a default is not enabled.
			// This can only happen if a currency is enabled, set as default, then
			// disabled, but it's worth handling the condition to avoid causing issues
			if(!$this->is_currency_enabled($this->_default_geoip_currency)) {
				$this->_default_geoip_currency = $this->base_currency();
			}
		}

		return $this->_default_geoip_currency;
	}

	/**
	 * Returns the decimal separator used by WooCommerce.
	 *
	 * @return string
	 */
	public function decimal_separator() {
		if(empty($this->_decimal_separator)) {
			$this->_decimal_separator = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_decimal_sep')), ENT_QUOTES);
		}

		return $this->_decimal_separator;
	}

	/**
	 * Returns the thousand separator used by WooCommerce.
	 *
	 * @return string
	 */
	public function thousand_separator() {
		if(empty($this->_thousand_separator)) {
			$this->_thousand_separator = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_thousand_sep')), ENT_QUOTES);
		}

		return $this->_thousand_separator;
	}

	/**
	 * Returns the amount of decimals to be used for a specific currency.
	 *
	 * @param string currency A currency code.
	 * @return int
	 */
	public function price_decimals($currency = null) {
		$currency = empty($currency) ? $this->base_currency() : $currency;

		if(get_value($currency, $this->price_decimals, null) == null) {
			$this->price_decimals[$currency] = $this->get_currency_decimals($currency);
		}

		return $this->price_decimals[$currency];
	}

	/**
	 * Getter for private "_exchange_rates_update_hook" property.
	 *
	 * @return string Value of "_exchange_rates_update_hook" property.
	 */
	public function exchange_rates_update_hook() {
		return $this->_exchange_rates_update_hook;
	}

	/**
	 * Returns the default settings for the plugin. Used mainly at first
	 * installation.
	 *
	 * @param string key If specified, method will return only the setting identified
	 * by the key.
	 * @param mixed default The default value to return if the setting requested
	 * via the "key" argument is not found.
	 * @return array|mixed The default settings, or the value of the specified
	 * setting.
	 *
	 * @see WC_Aelia_Settings:default_settings().
	 */
	public function default_settings($key = null, $default = null) {
		$woocommerce_base_currency = $this->base_currency();

		$default_options = array(
			self::FIELD_ENABLED_CURRENCIES => array(
				$woocommerce_base_currency,
			),
			self::FIELD_EXCHANGE_RATES => array(
				$woocommerce_base_currency => array(
					'rate' => 1,
					'set_manually' => 1,
					// For base currency, take the decimals from the WooCommerce configuration
					'decimals' => $this->woocommerce_currency_decimals,
					'rate_markup' => 0,
				),
			),
			self::FIELD_PAYMENT_GATEWAYS => array(
				$woocommerce_base_currency => array_keys($this->woocommerce_payment_gateways()),
			),
			self::FIELD_CURRENCY_BY_BILLING_COUNTRY_ENABLED => false,
			self::FIELD_DEBUG_MODE_ENABLED => false,
		);

		if(empty($key)) {
			return $default_options;
		}
		else {
			return get_value($key, $default_options, $default);
		}
	}

	/**
	 * Returns the Currency Settings to apply when a Currency is selected for
	 * the first time and has no settings.
	 *
	 * @return array An array of settings.
	 */
	public function default_currency_settings() {
		return array(
			'rate' => '',
			'rate_markup' => '',
			'set_manually' => 0,
			'enabled_gateways' => array_keys($this->woocommerce_payment_gateways()),
			// The default decimals will have to be retrieved using default_currency_decimals() function
			//'decimals',
		);
	}

	/**
	 * Returns a list of Schedule options, retrieved from WordPress list.
	 *
	 * @return array An array of Schedule ID => Schedule Name pairs.
	 */
	public function get_schedule_options() {
		$wp_schedules = wp_get_schedules();
		uasort($wp_schedules, array($this, 'sort_schedules'));

		$result = array();
		foreach($wp_schedules as $schedule_id => $settings) {
			$result[$schedule_id] = $settings['display'];
		}
		return $result;
	}

	/**
	 * Returns an array of Currency => Exchange Rate pairs.
	 *
	 * @param bool include_markup Indicates if the returned exchange rates should
	 * include the markup (if one was specified).
	 * @return array
	 */
	public function get_exchange_rates($include_markup = true) {
		$result = array();
		$exchange_rates_settings = $this->current_settings(self::FIELD_EXCHANGE_RATES);

		if(!is_array($exchange_rates_settings)) {
			$exchange_rates_settings = array();
		}

		// Return all exchange rates, excluding the invalid ones
		foreach($exchange_rates_settings as $currency => $settings) {
			if($currency == $this->base_currency()) {
				continue;
			}

			if(is_numeric(get_value('rate', $settings))) {
				$exchange_rate = (float)$settings['rate'];
			}
			// Add the markup to the exchange rate, if one was specified
			if($include_markup && is_numeric(get_value('rate_markup', $settings))) {
				$exchange_rate += (float)$settings['rate_markup'];
			}

			$result[$currency] = $exchange_rate;
		}
		// Exchange rate for WooCommerce base currency is always 1
		$result[$this->base_currency()] = 1;

		return $result;
	}

	/**
	 * Returns the number of decimals to be used for a specifc currency.
	 *
	 * @param string currency A currency code
	 * @return array
	 */
	public function get_currency_decimals($currency) {
		$default_decimals = default_currency_decimals($currency, $this->woocommerce_currency_decimals);

		// Decimals for the base currency are stored in WooCommerce settings
		if($currency == $this->base_currency()) {
			return $this->woocommerce_currency_decimals;
		}

		$exchange_rates = $this->current_settings(self::FIELD_EXCHANGE_RATES);
		$currency_settings = get_value($currency, $exchange_rates);

		// If no decimals are configured, return default setting
		if(!is_array($currency_settings)) {
			return $default_decimals;
		}

		$decimals = get_value('decimals', $currency_settings, $default_decimals);
		return is_numeric($decimals) ? $decimals : $default_decimals;
	}

	/**
	 * Returns the symbol to be used for a specifc currency.
	 *
	 * @param string currency A currency code.
	 * @return array
	 */
	public function get_currency_symbol($currency, $default_symbol = null) {
		$exchange_rates = $this->current_settings(self::FIELD_EXCHANGE_RATES);
		$currency_settings = get_value($currency, $exchange_rates);

		// If no settings are found for the currency, return the default
		if(!is_array($currency_settings) ||
			 !isset($currency_settings['symbol']) ||
			 empty($currency_settings['symbol'])) {
			return $default_symbol;
		}

		return $currency_settings['symbol'];
	}

	/**
	 * Returns an the Exchange Rate of a Currency relative to the base currency.
	 *
	 * @param bool include_markup Indicates if the returned exchange rates should
	 * include the markup (if one was specified).
	 * @return mixed A number indicating the Exchange Rate, or false if the currency
	 * is not configured properly.
	 */
	public function get_exchange_rate($currency, $include_markup = true) {
		$exchange_rates = $this->get_exchange_rates($include_markup);
		return get_value($currency, $exchange_rates, false);
	}

	/**
	 * Returns an array containing the Currencies that have been enabled.
	 *
	 * @return array
	 */
	public function get_enabled_currencies() {
		$enabled_currencies = $this->current_settings(self::FIELD_ENABLED_CURRENCIES);
		if(!is_array($enabled_currencies)) {
			$enabled_currencies	= array();
		}

		return array_unique($enabled_currencies);
	}

	/**
	 * Indicates if a specific Currency is Enabled.
	 *
	 * @param string currency_code The currency code to verify.
	 * @return bool
	 */
	public function is_currency_enabled($currency_code) {
		$enabled_currencies = $this->get_enabled_currencies();
		return in_array($currency_code, $enabled_currencies);
	}

	/**
	 * Indicates if the automatic selection of the Currency based on User's
	 * geographical location is enabled.
	 *
	 * @return bool
	 */
	public function currency_geolocation_enabled() {
		return ($this->current_settings(self::FIELD_IPGEOLOCATION_ENABLED) == 1);
	}

	/**
	 * Callback method, used with uasort() function.
	 * Sorts WordPress Scheduling options by interval (ascending). In case of two
	 * identical intervals, it sorts them by label (comparison is case-insensitive).
	 *
	 * @param array a First Schedule Option.
	 * @param array b Second Schedule Option.
	 * @return int Zero if (a == b), -1 if (a < b), 1 if (a > b).
	 *
	 * @see uasort().
	 */
	public function sort_schedules($a, $b) {
		if($a['interval'] == $b['interval']) {
			return strcasecmp($a['display'], $b['display']);
		}

		return ($a['interval'] < $b['interval']) ? -1 : 1;
	}

	/**
	 * Returns an array of the Currencies supported by WooCommerce.
	 *
	 * @return array An array of Currencies.
	 */
	public function woocommerce_currencies() {
		if(empty($this->_woocommerce_currencies)) {
			$this->_woocommerce_currencies = get_woocommerce_currencies();
		}
		return $this->_woocommerce_currencies;
	}

	/**
	 * Returns an array of the Payment Gateways enabled in WooCommerce. This method
	 * is used in place of standard WC_Payment_Gateways::get_available_payment_gateways()
	 * because the latter fires an apply_filter, which is then intercepted by
	 * the Currency Switcher to remove unavailable gateways depending on the
	 * selected currency. If the standard method were to be used, we would risk
	 * to trigger an infinite loop.
	 *
	 * @return array An array of payment gateways.
	 */
	public function woocommerce_payment_gateways() {
		if(empty($this->_woocommerce_payment_gateways)) {
			global $woocommerce;

			$this->_woocommerce_payment_gateways = array();
			foreach($woocommerce->payment_gateways->payment_gateways as $gateway) {
				if($gateway->enabled == 'yes') {
					$this->_woocommerce_payment_gateways[$gateway->id] = $gateway;
				}
			}
		}
		return $this->_woocommerce_payment_gateways;
	}

	/**
	 * Returns the payment gateways enabled for a currency.
	 *
	 * @param string currency The currency.
	 * @return array
	 */
	public function currency_payment_gateways($currency) {
		$current_settings = $this->current_settings(self::FIELD_PAYMENT_GATEWAYS);
		$currency_gateways = get_value($currency, $current_settings);
		return get_value('enabled_gateways', $currency_gateways);
	}

	/**
	 * Returns the description of a Currency.
	 *
	 * @param string currency The currency code.
	 * @return string The Currency description.
	 * @return string The Currency description.
	 */
	public function get_currency_description($currency) {
		$currencies = $this->woocommerce_currencies();
		return get_value($currency, $currencies);
	}

	/**
	 * Retrieves the latest Exchange Rates from a remote provider.
	 *
	 * @param array settings Current Plugin settings.
	 * @return array An array of Currency => Exchange Rate pairs.
	 */
	protected function fetch_latest_exchange_rates(array $settings = null) {
		$settings = isset($settings) ? $settings : $this->current_settings();

		$enabled_currencies = get_value(self::FIELD_ENABLED_CURRENCIES, $settings);
		//var_dump($enabled_currencies, $_POST);
		$exchange_rates = get_value(self::FIELD_EXCHANGE_RATES, $settings);

		$currencies_to_update = array();
		$current_exchange_rates = array();
		// If a Currency is configured to have its Exchange Rate set manually,
		// remove it from the list of the Currencies for which to retrieve the
		// Exchange Rate
		foreach($enabled_currencies as $currency) {
			if(get_value('set_manually', $exchange_rates[$currency], 0) != 1) {
				$currencies_to_update[] = $currency;
				$current_exchange_rates[$currency] = get_value('rate', $exchange_rates[$currency]);
			}
		}

		if(empty($currencies_to_update)) {
			return array();
		}

		$latest_exchange_rates = $this->exchange_rates_model($settings)->get_exchange_rates($this->base_currency(),
																																												$currencies_to_update);
		$result = array_merge($current_exchange_rates, $latest_exchange_rates);

		return $result;
	}

	/**
	 * Updates a list of Exchange Rates settings by replacing the rates with new
	 * ones passed as a parameter.
	 *
	 * @param array exchange_rates The list of Exchange Rate settings to be updated.
	 * @param array new_exchange_rates The new Exchange Rates.
	 * @return array The updated Exchange Rate settings.
	 */
	protected function set_exchange_rates($exchange_rates, array $new_exchange_rates) {
		$exchange_rates = empty($exchange_rates) ? array() : $exchange_rates;

		foreach($new_exchange_rates as $currency => $rate) {
			// Base currency has a fixed exchange rate of 1 (it doesn't need to be
			// converted)
			if($currency == $this->base_currency()) {
				$exchange_rates[$currency] = array(
					'rate' => 1
				);
				continue;
			}

			$currency_settings = get_value($currency, $exchange_rates, $this->default_currency_settings());
			// Update the exchange rate unless the currency is set to "set manually"
			// to prevent automatic updates
			if(get_value('set_manually', $currency_settings, 0) !== 1) {
				$currency_settings['rate'] = $rate;
			}
			$exchange_rates[$currency] = $currency_settings;
		}
		return $exchange_rates;
	}

	/**
	 * Updates the Plugin Settings received as an argument with the latest Exchange
	 * Rates, adding a settings error if the operation fails.
	 *
	 * @param array settings Current Plugin settings.
	 */
	public function update_exchange_rates(array &$settings, &$errors = array()) {
		$latest_exchange_rates = $this->fetch_latest_exchange_rates($settings);
		//var_dump($settings, $latest_exchange_rates);die();

		$exchange_rates_model_errors = $this->exchange_rates_model()->get_errors();

		if(($latest_exchange_rates === null) ||
			 !empty($exchange_rates_model_errors)) {
			$result = empty($exchange_rates_model_errors);

			foreach($exchange_rates_model_errors as $code => $message) {
				$errors['exchange-rates-error-' . $code] = $message;
			}
		}
		else {
			$exchange_rates = get_value(self::FIELD_EXCHANGE_RATES, $settings);
			// Update the exchange rates and add them to the settings to be saved
			$settings[self::FIELD_EXCHANGE_RATES] = $this->set_exchange_rates($exchange_rates, $latest_exchange_rates);

			$result = true;
		}

		return $result;
	}

	/**
	 * Validates the settings specified via the Options page.
	 *
	 * @param array settings An array of settings.
	 */
	public function validate_settings($settings) {
		// Tweak, to be reviewed
		// WordPress seems to trigger the validation multiple times under some
		// circumstances. This trick will avoid re-validating the data that was
		// already processed earlier
		if(get_value('validation_complete', $processed_settings, false)) {
			return $settings;
		}

		//var_dump($settings);die();
		$processed_settings = $this->current_settings();
		$woocommerce_currency = $this->base_currency();
		$enabled_currencies = get_value(self::FIELD_ENABLED_CURRENCIES, $settings, array());

		// Retrieve the new currencies eventually added to the "enabled" list
		$currencies_diff = array_diff($enabled_currencies, get_value(self::FIELD_ENABLED_CURRENCIES, $processed_settings, array()));

		// Validate Exchange Rates Provider settings
		$exchange_rates_provider_ok = $this->_validate_exchange_rates_provider_settings($settings);
		if($exchange_rates_provider_ok === true) {
			// Save Exchange Rates providers settings
			$processed_settings[self::FIELD_EXCHANGE_RATES_PROVIDER] = get_value(self::FIELD_EXCHANGE_RATES_PROVIDER, $settings);
			$processed_settings[self::FIELD_OPENEXCHANGE_API_KEY] = trim(get_value(self::FIELD_OPENEXCHANGE_API_KEY, $settings));
		}

		// Validate enabled currencies
		if($this->_validate_enabled_currencies($enabled_currencies) === true) {
			//var_dump($enabled_currencies);die();
			$processed_settings[self::FIELD_ENABLED_CURRENCIES] = $enabled_currencies;

			// Validate Exchange Rates
			$exchange_rates = get_value(self::FIELD_EXCHANGE_RATES, $settings, array());
			if($this->_validate_exchange_rates($exchange_rates) === true) {
				$processed_settings[self::FIELD_EXCHANGE_RATES] = $exchange_rates;
			}

			// We can update exchange rates only if an exchange rates provider has been
			// configured correctly
			if($exchange_rates_provider_ok === true) {
				// Update Exchange Rates in three cases:
				// - If none is present
				// - If one or more new currencies have been enabled
				// - If button "Save and update Exchange Rates" has been clicked
				if(empty($processed_settings[self::FIELD_EXCHANGE_RATES]) ||
					 !empty($currencies_diff) ||
					 get_value('update_exchange_rates_button', $_POST['wc_aelia_currency_switcher'])) {
					if($this->update_exchange_rates($processed_settings, $errors) === true) {
						// This is not an "error", but a confirmation message. Unfortunately,
						// WordPress only has "add_settings_error" to add messages of any type
						add_settings_error(self::SETTINGS_KEY,
										 'exchange-rates-updated',
										 __('Settings saved. Exchange Rates have been updated.', $this->textdomain),
										 'updated');
						$processed_settings[self::FIELD_EXCHANGE_RATES_LAST_UPDATE] = current_time('timestamp');
					}
					else {
						$this->add_multiple_settings_errors($errors);
					}
				}
			}
		}

		// Validate enabled payment gateways for each currency
		$enabled_payment_gateways = get_value(self::FIELD_PAYMENT_GATEWAYS, $settings, array());
		//var_dump($enabled_payment_gateways);die();
		if($this->_validate_payment_gateways($enabled_currencies, $enabled_payment_gateways) === true) {
			$processed_settings[self::FIELD_PAYMENT_GATEWAYS] = $enabled_payment_gateways;
		}

		$this->set_exchange_rates_update_schedule($processed_settings, $settings);

		// Save Exchange Rates Auto-update settings
		$processed_settings[self::FIELD_EXCHANGE_RATES_UPDATE_ENABLE] = get_value(self::FIELD_EXCHANGE_RATES_UPDATE_ENABLE, $settings);
		$processed_settings[self::FIELD_EXCHANGE_RATES_UPDATE_SCHEDULE] = get_value(self::FIELD_EXCHANGE_RATES_UPDATE_SCHEDULE, $settings);

		// Save IP Geolocation Settings
		$processed_settings[self::FIELD_IPGEOLOCATION_ENABLED] = get_value(self::FIELD_IPGEOLOCATION_ENABLED, $settings);
		$processed_settings[self::FIELD_IPGEOLOCATION_DEFAULT_CURRENCY] = get_value(self::FIELD_IPGEOLOCATION_DEFAULT_CURRENCY, $settings, $woocommerce_currency);

		// "Currency by billing country" settings
		$processed_settings[self::FIELD_CURRENCY_BY_BILLING_COUNTRY_ENABLED] = get_value(self::FIELD_CURRENCY_BY_BILLING_COUNTRY_ENABLED, $settings, 0);

		// Debug settings
		$processed_settings[self::FIELD_DEBUG_MODE_ENABLED] = get_value(self::FIELD_DEBUG_MODE_ENABLED, $settings, 0);

		$processed_settings['validation_complete'] = true;

		// Return the array processing any additional functions filtered by this action.
		return apply_filters('wc_aelia_currencyswitcher_validate_settings', $processed_settings, $settings);
	}

	/**
	 * Class constructor.
	 */
	public function __construct($settings_key = self::SETTINGS_KEY,
															$textdomain = AELIA_CS_PLUGIN_TEXTDOMAIN,
															WC_Aelia_Settings_Renderer $renderer = null) {
		if(empty($renderer)) {
			// Instantiate the render to be used to generate the settings page
			$renderer = new WC_Aelia_CurrencySwitcher_Settings_Renderer();
		}
		parent::__construct($settings_key, $textdomain, $renderer);

		// Register available Exchange Rates models
		$this->register_exchange_rates_models();
		// Store the number of decimals used by WooCommerce
		$this->woocommerce_currency_decimals = (int)get_option('woocommerce_price_num_decimals');

		add_action('admin_init', array($this, 'init_settings'));

		// If no settings are registered, save the default ones
		if($this->load() === null) {
			$this->save();
		}
	}

	/**
	 * Factory method.
	 *
	 * @param string settings_key The key used to store and retrieve the plugin settings.
	 * @param string textdomain The text domain used for localisation.
	 * @param string renderer The renderer to use to generate the settings page.
	 * @return WC_Aelia_Settings.
	 */
	public static function factory($settings_key = self::SETTINGS_KEY,
															$textdomain = AELIA_CS_PLUGIN_TEXTDOMAIN) {
		$settings_manager = new WC_Aelia_CurrencySwitcher_Settings($settings_key, $textdomain, $renderer);

		return $settings_manager;
	}
	/**
	 * Updates the Exchange Rates. Triggered by a Scheduled Task.
	 */
	public function scheduled_update_exchange_rates() {
		$settings = $this->current_settings();
		if($this->update_exchange_rates($settings) === true) {
			// Save the timestamp of last update
			$settings[self::FIELD_EXCHANGE_RATES_LAST_UPDATE] = current_time('timestamp');
		}

		$this->save($settings);
	}

	/*** Validation methods ***/
	/**
	 * Validates a list of enabled currencies.
	 *
	 * @param array A list of currencies.
	 * @return bool True, if the validation succeeds, False otherwise.
	 */
	protected function _validate_enabled_currencies(&$enabled_currencies) {
		$woocommerce_currency = $this->base_currency();
		if(empty($enabled_currencies)) {
			$enabled_currencies = array();
		}

		// WooCommerce Base Currency must be enabled, therefore it's forcibly added
		// to the list
		if(!array_search($woocommerce_currency, $enabled_currencies)) {
			$enabled_currencies[] = $woocommerce_currency;
		}
		return true;
	}

	/**
	 * Validates a list of Exchange Rates.
	 *
	 * @param array A list of Exchange Rates.
	 * @return bool True, if the validation succeeds, False otherwise.
	 */
	protected function _validate_exchange_rates(&$exchange_rates) {
		$result = true;
		foreach($exchange_rates as $currency => $settings) {
			$exchange_rate = get_value('rate', $settings);
			if((get_value('set_manually', $settings, 0) === 1) &&
				!is_numeric($exchange_rate)) {
				add_settings_error(self::SETTINGS_KEY,
													 'invalid-rate',
													 sprintf(__('You chose to manually set the exchange rate for currency %s, ' .
																			'but the specified rate "%s" is not valid.',
																			$this->textdomain),
																 $currency,
																 $exchange_rate));
				$result = false;
			}
		}
		return $result;
	}

	/**
	 * Validates settings for the selected Exchange Rates provider.
	 *
	 * @param array settings An array of settings.
	 * @return bool
	 */
	protected function _validate_exchange_rates_provider_settings($settings) {
		// Validate settings for Open Exchange Rates
		$selected_provider = get_value(self::FIELD_EXCHANGE_RATES_PROVIDER, $settings);
		if(empty($selected_provider)) {
			return false;
		}

		if($selected_provider == $this->get_exchange_rates_model_key('WC_Aelia_OpenExchangeRatesModel')) {
			return $this->_validate_openexchangerates_settings($settings);
		}
		return true;
	}

	/**
	 * Validates settings provided for Open Exchange Rates.
	 *
	 * @param array settings An array of settings.
	 * @return bool
	 */
	protected function _validate_openexchangerates_settings($settings) {
		$api_key = trim(get_value(self::FIELD_OPENEXCHANGE_API_KEY, $settings));
		if(empty($api_key)) {
			add_settings_error(self::SETTINGS_KEY,
								 'invalid-openexchangerates-api-key',
								 __('You must specify an API Key to use Open Exchange Rates service.',
										$this->textdomain));
			return false;
		}

		return true;
	}

	// TODO Document method
	protected function _validate_payment_gateways($enabled_currencies, $enabled_payment_gateways) {
		$result = true;

		$available_payment_gateways_ids = array_keys($this->woocommerce_payment_gateways());
		foreach($enabled_currencies as $currency) {
			$currency_gateways = get_value('enabled_gateways', $enabled_payment_gateways[$currency], array());

			if(empty($currency_gateways)) {
				add_settings_error(self::SETTINGS_KEY,
													 'no-payment-gateways-for-currency',
													 sprintf(__('You have to enable at least one payment gateway for ' .
																			'currency "%s".',
																			$this->textdomain),
																 $currency));
				$result = false;
				continue;
			}

			// Check that all payment gateways exist amongst the enabled ones
			$invalid_gateways = array();
			foreach($currency_gateways as $gateway_id) {
				if(!in_array($gateway_id, $available_payment_gateways_ids)) {
					$invalid_gateways[] = $gateway_id;
				}
			}
			if(!empty($invalid_gateways)) {
				add_settings_error(self::SETTINGS_KEY,
													 'invalid-payment-gateways-for-currency',
													 sprintf(__('The following payment gateways, selected for currency ' .
																			'"%s", are not valid: %s',
																			$this->textdomain),
																 $currency,
																 implode(', ', $invalid_gateways)));
				$result = false;
			}
		}
		return $result;
	}

	/**
	 * Configures the schedule to automatically update the Exchange Rates.
	 *
	 * @param array current_settings An array containing current plugin settings.
	 * @param array new_settings An array containing new plugin settings.
	 */
	protected function set_exchange_rates_update_schedule(array $current_settings, array $new_settings) {
		// Clear Exchange Rates Update schedule, if it was disabled
		$exchange_rates_schedule_enabled = get_value(self::FIELD_EXCHANGE_RATES_UPDATE_ENABLE, $new_settings, 0);
		if($exchange_rates_schedule_enabled != self::ENABLED_YES) {
			wp_clear_scheduled_hook($this->_exchange_rates_update_hook);
		}
		else {
			// If Exchange Rates Update is still scheduled, check if its schedule changed.
			// If it changed, remove old schedule and set a new one.
			$new_exchange_rates_update_schedule = get_value(self::FIELD_EXCHANGE_RATES_UPDATE_SCHEDULE, $new_settings);
			if(($current_settings[self::FIELD_EXCHANGE_RATES_UPDATE_SCHEDULE] != $new_exchange_rates_update_schedule) ||
				 ($current_settings[self::FIELD_EXCHANGE_RATES_UPDATE_ENABLE] != $exchange_rates_schedule_enabled)) {
				wp_clear_scheduled_hook($this->_exchange_rates_update_hook);
				//var_dump($new_exchange_rates_update_schedule);die();
				wp_schedule_event(current_time('timestamp'), $new_exchange_rates_update_schedule, $this->_exchange_rates_update_hook);
			}
		}
	}
}
