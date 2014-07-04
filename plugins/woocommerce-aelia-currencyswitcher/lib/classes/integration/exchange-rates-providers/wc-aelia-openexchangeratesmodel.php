<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Retrieves the Exchange Rates from Open Exchange Rates website.
 *
 * @link https://openexchangerates.org/
 */
class WC_Aelia_OpenExchangeRatesModel extends WC_Aelia_ExchangeRatesModel {
	// @var string The URL template to use to query Open Exchange Rates service
	private $_openexchangerates_url = 'http://openexchangerates.org/api/latest.json?app_id=%s';
	private $_base_currency_arg = '&base=%s';

	/**
	 * Fetches all Exchange Rates from Open Exchange Rates website.
	 *
	 * @param string base_currency The base currency. It can be left empty to
	 * retrieve exchange rates for Open Exchange default currency (USD as of May
	 * 2013).
	 * @return object|bool An object containing the response from Open Exchange, or
	 * False in case of failure.
	 */
	private function fetch_all_rates($base_currency = null) {
		$url = sprintf($this->_openexchangerates_url,
									 $this->_api_key);
		if(!empty($base_currency)) {
			$url .= sprintf($this->_base_currency_arg, $base_currency);
		}

		try {
			$response = \Httpful\Request::get($url)
				->expectsJson()
				->send();

			//var_dump($response); die();
			if($response->hasErrors()) {
				// OpenExchangeRates sends error details in response body
				if($response->hasBody()) {
					$response_data = $response->body;

					$this->add_error(self::ERR_ERROR_RETURNED,
													 sprintf(__('Error returned by Open Exchange Rates. ' .
																			'Base currency: %s. Error code: %s. Error message: %s - %s.',
																			AELIA_CS_PLUGIN_TEXTDOMAIN),
																	 $base_currency,
																	 $response_data->status,
																	 $response_data->message,
																	 $response_data->description));
				}
				return false;
			}

			return $response->body;
		}
		catch(Exception $e) {
			$this->add_error(self::ERR_EXCEPTION_OCCURRED,
											 sprintf(__('Exception occurred while retrieving the Exchange Rates from Open Exchange Rates. ' .
																	'Base currency: %s. Error message: %s.',
																	AELIA_CS_PLUGIN_TEXTDOMAIN),
															 $base_currency,
															 $e->getMessage()));
			return null;
		}
	}

	/**
	 * Returns current Exchange Rates for the specified currency.
	 *
	 * @param string base_currency The base currency.
	 * @return array An array of Currency => Exchange Rate pairs.
	 */
	private function current_rates($base_currency) {
		if(empty($this->_current_rates) ||
			 $this->_base_currency != $base_currency) {

			// Fetch exchange rates using USD as the base currency. This will work
			// whether the user holds a full API key or a free one.
			$openexchange_data = $this->fetch_all_rates();

			if($openexchange_data === false) {
				return null;
			}

			$exchange_rates = json_decode(json_encode($openexchange_data->rates), true);

			if(!is_array($exchange_rates)) {
				$this->add_error(self::ERR_UNEXPECTED_ERROR_FETCHING_EXCHANGE_RATES,
												 sprintf(__('An unexpected error occurred while fetching exchange rates ' .
																		'from Open Exchange Rates for base currency %s. The most common ' .
																		'causes of this issue are an invalid API key, or the absence of ' .
																		'PHP CURL extension. Please make sure that API key is correct, and ' .
																		'that PHP CURL is installed and configured in your system.',
																		AELIA_CS_PLUGIN_TEXTDOMAIN),
																 $base_currency));
				return array();
			}

			// Since we didn't get the Exchange Rates related to the base currency,
			// but in the default base currency used by OpenExchange, we need to
			// recalculate them against the base currency we would like to use
			$this->_current_rates = $this->rebase_rates($exchange_rates, $base_currency);
			$this->_base_currency = $base_currency;
		}
		return $this->_current_rates;
	}

	/**
	 * Recaculates the Exchange Rates using another base currency. This method
	 * is invoked when the rates fetched from Open Exchange are relative to their
	 * default base rate, but another one is used by WooCommerce.
	 *
	 * @param array exchange_rates The Exchange Rates retrieved from Open Exchange.
	 * @param string base_currency The base currency against which the rates should
	 * be recalculated.
	 * @return array An array of Currency => Exchange Rate pairs.
	 */
	private function rebase_rates(array $exchange_rates, $base_currency) {
		$recalc_rate = get_value($base_currency, $exchange_rates);
		//var_dump($base_currency, $exchange_rates);

		if(empty($recalc_rate)) {
			$this->add_error(self::ERR_BASE_CURRENCY_NOT_FOUND,
											 sprintf(__('Could not rebase rates against base currency "%s". ' .
																	'Currency not found in data returned by Open Exchange.',
																	AELIA_CS_PLUGIN_TEXTDOMAIN),
															 $base_currency));
			return null;
		}

		$result = array();
		foreach($exchange_rates as $currency => $rate) {
			$result[$currency] = $rate / $recalc_rate;
		}

		//var_dump($result); die();

		return $result;
	}

	/**
	 * Returns the Exchange Rate of a Currency in respect to a Base Currency.
	 *
	 * @param string base_currency The code of the Base Currency.
	 * @param string currency The code of the Currency for which to find the
	 * Exchange Rate.
	 * @return
	 */
	protected function get_rate($base_currency, $currency) {
		$current_rates = $this->current_rates($base_currency);

		return get_value($currency, $current_rates);
	}

	/**
	 * Class constructor.
	 *
	 * @param array An array of Settings that can be used to override the ones
	 * currently saved in the configuration.
	 * @return WC_Aelia_OpenExchangeRatesModel.
	 */
	public function __construct($settings = null) {
		parent::__construct($settings);

		// API Key is necessary for the Model to work correctly
		$this->_api_key = get_value(WC_Aelia_CurrencySwitcher_Settings::FIELD_OPENEXCHANGE_API_KEY,
																$settings,
																WC_Aelia_CurrencySwitcher::settings()->current_settings(WC_Aelia_CurrencySwitcher_Settings::FIELD_OPENEXCHANGE_API_KEY));
		if(empty($this->_api_key)) {
			throw new Exception(__('Open Exchange API Key has not been entered. Service cannot be used ' .
														 'without such key. See http://openexchangerates.org/ for more details.',
														 AELIA_CS_PLUGIN_TEXTDOMAIN));
		}
	}
}
