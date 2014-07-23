<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

/* This file contains several functions that have been introduced in WooCommerce
 * 2.0, which are loaded only when the plugin is running within an earlier version.
 */
global $woocommerce;

if(!function_exists('get_raw_number')) {
	/**
	 * Given a number formatted by WooCommerce, it returns the raw number. Input
	 * number must not include the Currency symbol (i.e. it must only contain digits,
	 * the decimal separator and the thousand separator) .
	 *
	 * @param string formatted_number A number containing a decimal separator and,
	 * optionally, the thousand separator.
	 * @return double A raw number.
	 */
	function get_raw_number($formatted_number) {
		//$num_decimals = (int)get_option('woocommerce_price_num_decimals');
		$decimal_sep = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_decimal_sep')), ENT_QUOTES);
		$thousands_sep = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_thousand_sep')), ENT_QUOTES);

		// Remove Thousands separator
		$raw_number = str_replace($thousands_sep, '', $formatted_number);
		// Replace whatever Decimal separator with the dot. At this point, number is
		// raw, i.e. in format "12345.67"
		$raw_number = str_replace($decimal_sep, '.', $raw_number);

		return $raw_number;
	}
}

if(!function_exists('default_currency_decimals')) {
	/**
	 * Returns the decimals used by a currency.
	 *
	 * @param string currency A currency code.
	 * @param int default_decimals The value return by default if the number of
	 * decimals cannot be determined.
	 * @return int
	 */
	function default_currency_decimals($currency, $default_decimals = 2) {
		$currency_decimals = array(
			'AED' => 2, // UAE Dirham
			'AFN' => 2, // Afghanistan Afghani
			'ALL' => 2, // Albanian Lek
			'AMD' => 2, // Armenian Dram
			'ANG' => 2, // Netherlands Antillian Guilder
			'AOA' => 2, // Angolan Kwanza
			'ARS' => 2, // Argentine Peso
			'AUD' => 2, // Australian Dollar
			'AWG' => 2, // Aruban Guilder
			'AZM' => 2, // Azerbaijanian Manat
			'BAM' => 2, // Bosnia and Herzegovina Convertible Marks
			'BBD' => 2, // Barbados Dollar
			'BDT' => 2, // Bangladesh Taka
			'BGN' => 2, // Bulgarian Lev
			'BHD' => 3, // Bahraini Dinar
			'BIF' => 0, // Burundi Franc
			'BMD' => 2, // Bermudian Dollar
			'BND' => 2, // Brunei Dollar
			'BOB' => 2, // Bolivian Boliviano
			'BRL' => 2, // Brazilian Real
			'BSD' => 2, // Bahamian Dollar
			'BTN' => 2, // Bhutan Ngultrum
			'BWP' => 2, // Botswana Pula
			'BYR' => 0, // Belarussian Ruble
			'BZD' => 2, // Belize Dollar
			'CAD' => 2, // Canadian Dollar
			'CDF' => 2, // Franc Congolais
			'CHF' => 2, // Swiss Franc
			'CLP' => 0, // Chilean Peso
			'CNY' => 2, // Chinese Yuan Renminbi
			'COP' => 2, // Colombian Peso
			'CRC' => 2, // Costa Rican Colon
			'CSD' => 2, // Serbian Dinar
			'CUP' => 2, // Cuban Peso
			'CVE' => 2, // Cape Verde Escudo
			'CYP' => 2, // Cyprus Pound
			'CZK' => 2, // Czech Koruna
			'DJF' => 0, // Djibouti Franc
			'DKK' => 2, // Danish Krone
			'DOP' => 2, // Dominican Peso
			'DZD' => 2, // Algerian Dinar
			'EEK' => 2, // Estonian Kroon
			'EGP' => 2, // Egyptian Pound
			'ERN' => 2, // Eritrea Nafka
			'ETB' => 2, // Ethiopian Birr
			'EUR' => 2, // euro
			'FJD' => 2, // Fiji Dollar
			'FKP' => 2, // Falkland Islands Pound
			'GBP' => 2, // Pound Sterling
			'GEL' => 2, // Georgian Lari
			'GHC' => 2, // Ghana Cedi
			'GIP' => 2, // Gibraltar Pound
			'GMD' => 2, // Gambian Dalasi
			'GNF' => 0, // Guinea Franc
			'GTQ' => 2, // Guatemala Quetzal
			'GYD' => 2, // Guyana Dollar
			'HKD' => 2, // Hong Kong Dollar
			'HNL' => 2, // Honduras Lempira
			'HRK' => 2, // Croatian Kuna
			'HTG' => 2, // Haiti Gourde
			'HUF' => 2, // Hungarian Forint
			'IDR' => 2, // Indonesian Rupiah
			'ILS' => 2, // New Israeli Shekel
			'INR' => 2, // Indian Rupee
			'IQD' => 3, // Iraqi Dinar
			'IRR' => 2, // Iranian Rial
			'ISK' => 0, // Iceland Krona
			'JMD' => 2, // Jamaican Dollar
			'JOD' => 3, // Jordanian Dinar
			'JPY' => 0, // Japanese Yen
			'KES' => 2, // Kenyan Shilling
			'KGS' => 2, // Kyrgyzstan Som
			'KHR' => 2, // Cambodia Riel
			'KMF' => 0, // Comoro Franc
			'KPW' => 2, // North Korean Won
			'KRW' => 0, // Korean Won
			'KWD' => 3, // Kuwaiti Dinar
			'KYD' => 2, // Cayman Islands Dollar
			'KZT' => 2, // Kazakhstan Tenge
			'LAK' => 2, // Lao Kip
			'LBP' => 2, // Lebanese Pound
			'LKR' => 2, // Sri Lanka Rupee
			'LRD' => 2, // Liberian Dollar
			'LSL' => 2, // Lesotho Loti
			'LTL' => 2, // Lithuanian Litas
			'LVL' => 2, // Latvian Lats
			'LYD' => 3, // Libyan Dinar
			'MAD' => 2, // Moroccan Dirham
			'MDL' => 2, // Moldovan Leu
			'MGA' => 2, // Malagasy Ariary
			'MKD' => 2, // Macedonian Denar
			'MMK' => 2, // Myanmar Kyat
			'MNT' => 2, // Mongolian Tugrik
			'MOP' => 2, // Macau Pataca
			'MRO' => 2, // Mauritania Ouguiya
			'MTL' => 2, // Maltese Lira
			'MUR' => 2, // Mauritius Rupee
			'MVR' => 2, // Maldives Rufiyaa
			'MWK' => 2, // Malawi Kwacha
			'MXN' => 2, // Mexican Peso
			'MYR' => 2, // Malaysian Ringgit
			'MZM' => 2, // Mozambique Metical
			'NAD' => 2, // Namibia Dollar
			'NGN' => 2, // Nigerian Naira
			'NIO' => 2, // Nicaragua Cordoba Oro
			'NOK' => 2, // Norwegian Krone
			'NPR' => 2, // Nepalese Rupee
			'NZD' => 2, // New Zealand Dollar
			'OMR' => 3, // Rial Omani
			'PAB' => 2, // Panama Balboa
			'PEN' => 2, // Peruvian Nuevo Sol
			'PGK' => 2, // Papua New Guinea Kina
			'PHP' => 2, // Philippine Peso
			'PKR' => 2, // Pakistan Rupee
			'PLN' => 2, // Polish Zloty
			'PYG' => 0, // Paraguayan Guarani
			'QAR' => 2, // Qatari Rial
			'RON' => 2, // New Romanian Leu
			'RUB' => 2, // Russian Ruble
			'RWF' => 0, // Rwanda Franc
			'SAR' => 2, // Saudi Riyal
			'SBD' => 2, // Solomon Islands Dollar
			'SCR' => 2, // Seychelles Rupee
			'SDD' => 2, // Sudanese Dinar
			'SEK' => 2, // Swedish Krona
			'SGD' => 2, // Singapore Dollar
			'SHP' => 2, // St Helena Pound
			'SIT' => 2, // Slovenian Tolar
			'SKK' => 2, // Slovak Koruna
			'SLL' => 2, // Sierra Leone Leone
			'SOS' => 2, // Somali Shilling
			'SRD' => 2, // Surinam Dollar
			'STD' => 2, // São Tome and Principe Dobra
			'SVC' => 2, // El Salvador Colon
			'SYP' => 2, // Syrian Pound
			'SZL' => 2, // Swaziland Lilangeni
			'THB' => 2, // Thai Baht
			'TJS' => 2, // Tajik Somoni
			'TMM' => 2, // Turkmenistan Manat
			'TND' => 3, // Tunisian Dinar
			'TOP' => 2, // Tonga Pa'anga
			'TRY' => 2, // Turkish Lira
			'TTD' => 2, // Trinidad and Tobago Dollar
			'TWD' => 2, // New Taiwan Dollar
			'TZS' => 2, // Tanzanian Shilling
			'UAH' => 2, // Ukraine Hryvnia
			'UGX' => 2, // Uganda Shilling
			'USD' => 2, // US Dollar
			'UYU' => 2, // Peso Uruguayo
			'UZS' => 2, // Uzbekistan Sum
			'VEB' => 2, // Venezuelan Bolivar
			'VND' => 2, // Vietnamese Dong
			'VUV' => 0, // Vanuatu Vatu
			'WST' => 2, // Samoa Tala
			'XAF' => 0, // CFA Franc BEAC
			'XCD' => 2, // East Caribbean Dollar
			'XDR' => 5, // SDR (Special Drawing Rights)
			'XOF' => 0, // CFA Franc BCEAO
			'XPF' => 0, // CFP Franc
			'YER' => 2, // Yemeni Rial
			'ZAR' => 2, // South African Rand
			'ZMK' => 2, // Zambian Kwacha
			'ZWD' => 2, // Zimbabwe Dollar
		);

		return get_value($currency, $currency_decimals, $default_decimals);
	}
}
