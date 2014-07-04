<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Retrieves the Exchange Rates from WebServiceEx.
 *
 * @link http://webservicex.net/ws/default.aspx
 */
class WC_Aelia_WebServiceXModel extends WC_Aelia_ExchangeRatesModel {
	// @var string The URL template to use to query WebServiceX
	private $_webservicex_url = 'http://webservicex.net/CurrencyConvertor.asmx/ConversionRate?FromCurrency=%s&ToCurrency=%s';

	/**
	 * Returns the Exchange Rate of a Currency in respect to a Base Currency.
	 *
	 * @param string base_currency The code of the Base Currency.
	 * @param string currency The code of the Currency for which to find the
	 * Exchange Rate.
	 * @return string|bool An XML string containing the exchange rate, if successful,
	 * or False on failure.
	 */
	protected function get_rate($base_currency, $currency) {
		$url = sprintf($this->_webservicex_url,
									 strtoupper($base_currency),
									 strtoupper($currency));
		//var_dump($url);

		try {
			$response = \Httpful\Request::get($url)
				->expectsXml()
				->send();

			//var_dump($response); die();
			if($response->hasErrors()) {
				// TODO Find out how to determine what error occurred and add it to the Errors list
				return false;
			}

			return (string)$response->body;
		}
		catch(Exception $e) {
			$this->add_error(self::ERR_EXCEPTION_OCCURRED,
											 sprintf(__('Error(s) occurred while retrieving the Exchange Rates from WebServiceX. ' .
																	'Base currency: %s. Target Currency: %s. Error message: %s.',
																	AELIA_CS_PLUGIN_TEXTDOMAIN),
															 $base_currency,
															 $currency,
															 $e->getMessage()));
			return null;
		}
	}
}

