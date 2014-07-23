<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \Aelia\CurrencySwitcher\Logger as Logger;

/**
 * Allows to select a Currency based on a geographic region.
 */
class WC_Aelia_Currencies_Manager {
	// @var array A list of the Currencies used by all Countries
	protected $_country_currencies = array(
		'AD' => 'EUR', // Andorra - Euro
		'AE' => 'AED', // United Arab Emirates - Arab Emirates Dirham
		'AF' => 'AFA', // Afghanistan - Afghanistan Afghani
		'AG' => 'XCD', // Antigua and Barbuda - East Caribbean Dollar
		'AI' => 'XCD', // Anguilla - East Caribbean Dollar
		'AL' => 'ALL', // Albania - Albanian Lek
		'AM' => 'AMD', // Armenia - Armenian Dram
		'AN' => 'ANG', // Netherlands Antilles - Netherlands Antillean Guilder
		'AO' => 'AOA', // Angola - Angolan Kwanza
		'AQ' => 'ATA', // Antarctica - Dollar
		'AR' => 'ARS', // Argentina - Argentine Peso
		'AS' => 'USD', // American Samoa - US Dollar
		'AT' => 'EUR', // Austria - Euro
		'AU' => 'AUD', // Australia - Australian Dollar
		'AW' => 'AWG', // Aruba - Aruban Florin
		'AX' => 'EUR', // Aland Islands - Euro
		'AZ' => 'AZN', // Azerbaijan - Azerbaijani Manat
		'BA' => 'BAM', // Bosnia-Herzegovina - Marka
		'BB' => 'BBD', // Barbados - Barbados Dollar
		'BD' => 'BDT', // Bangladesh - Bangladeshi Taka
		'BE' => 'EUR', // Belgium - Euro
		'BF' => 'XOF', // Burkina Faso - CFA Franc BCEAO
		'BG' => 'BGL', // Bulgaria - Bulgarian Lev
		'BH' => 'BHD', // Bahrain - Bahraini Dinar
		'BI' => 'BIF', // Burundi - Burundi Franc
		'BJ' => 'XOF', // Benin - CFA Franc BCEAO
		'BL' => 'EUR', // Saint Barthelemy - Euro
		'BM' => 'BMD', // Bermuda - Bermudian Dollar
		'BN' => 'BND', // Brunei Darussalam - Brunei Dollar
		'BO' => 'BOB', // Bolivia - Boliviano
		'BQ' => 'USD', // Bonaire, Sint Eustatius and Saba - US Dollar
		'BR' => 'BRL', // Brazil - Brazilian Real
		'BS' => 'BSD', // Bahamas - Bahamian Dollar
		'BT' => 'BTN', // Bhutan - Bhutan Ngultrum
		'BV' => 'NOK', // Bouvet Island - Norwegian Krone
		'BW' => 'BWP', // Botswana - Botswana Pula
		'BY' => 'BYR', // Belarus - Belarussian Ruble
		'BZ' => 'BZD', // Belize - Belize Dollar
		'CA' => 'CAD', // Canada - Canadian Dollar
		'CC' => 'AUD', // Cocos (Keeling) Islands - Australian Dollar
		'CD' => 'CDF', // Democratic Republic of Congo - Francs
		'CF' => 'XAF', // Central African Republic - CFA Franc BEAC
		'CG' => 'XAF', // Republic of the Congo - CFA Franc BEAC
		'CH' => 'CHF', // Switzerland - Swiss Franc
		'CI' => 'XOF', // Ivory Coast - CFA Franc BCEAO
		'CK' => 'NZD', // Cook Islands - New Zealand Dollar
		'CL' => 'CLP', // Chile - Chilean Peso
		'CM' => 'XAF', // Cameroon - CFA Franc BEAC
		'CN' => 'CNY', // China - Yuan Renminbi
		'CO' => 'COP', // Colombia - Colombian Peso
		'CR' => 'CRC', // Costa Rica - Costa Rican Colon
		'CU' => 'CUP', // Cuba - Cuban Peso
		'CV' => 'CVE', // Cape Verde - Cape Verde Escudo
		'CW' => 'ANG', // Curacao - Netherlands Antillean Guilder
		'CX' => 'AUD', // Christmas Island - Australian Dollar
		'CY' => 'EUR', // Cyprus - Euro
		'CZ' => 'CZK', // Czech Rep. - Czech Koruna
		'DE' => 'EUR', // Germany - Euro
		'DJ' => 'DJF', // Djibouti - Djibouti Franc
		'DK' => 'DKK', // Denmark - Danish Krone
		'DM' => 'XCD', // Dominica - East Caribbean Dollar
		'DO' => 'DOP', // Dominican Republic - Dominican Peso
		'DZ' => 'DZD', // Algeria - Algerian Dinar
		'EC' => 'ECS', // Ecuador - Ecuador Sucre
		'EE' => 'EUR', // Estonia - Euro
		'EG' => 'EGP', // Egypt - Egyptian Pound
		'EH' => 'MAD', // Western Sahara - Moroccan Dirham
		'ER' => 'ERN', // Eritrea - Eritrean Nakfa
		'ES' => 'EUR', // Spain - Euro
		'ET' => 'ETB', // Ethiopia - Ethiopian Birr
		'FI' => 'EUR', // Finland - Euro
		'FJ' => 'FJD', // Fiji - Fiji Dollar
		'FK' => 'FKP', // Falkland Islands - Falkland Islands Pound
		'FM' => 'USD', // Micronesia - US Dollar
		'FO' => 'DKK', // Faroe Islands - Danish Krone
		'FR' => 'EUR', // France - Euro
		'GA' => 'XAF', // Gabon - CFA Franc BEAC
		'GB' => 'GBP', // United Kingdom - Pound Sterling
		'GD' => 'XCD', // Grenada - East Carribean Dollar
		'GE' => 'GEL', // Georgia - Georgian Lari
		'GF' => 'EUR', // French Guiana - Euro
		'GG' => 'GBP', // Guernsey - Pound Sterling
		'GH' => 'GHS', // Ghana - Ghanaian Cedi
		'GI' => 'GIP', // Gibraltar - Gibraltar Pound
		'GL' => 'DKK', // Greenland - Danish Krone
		'GM' => 'GMD', // Gambia - Gambian Dalasi
		'GN' => 'GNF', // Guinea - Guinea Franc
		'GP' => 'EUR', // Guadeloupe (French) - Euro
		'GQ' => 'XAF', // Equatorial Guinea - CFA Franc BEAC
		'GR' => 'EUR', // Greece - Euro
		'GS' => 'GBP', // South Georgia & South Sandwich Islands - Pound Sterling
		'GT' => 'GTQ', // Guatemala - Guatemalan Quetzal
		'GU' => 'USD', // Guam (USA) - US Dollar
		'GW' => 'XAF', // Guinea Bissau - CFA Franc BEAC
		'GY' => 'GYD', // Guyana - Guyana Dollar
		'HK' => 'HKD', // Hong Kong - Hong Kong Dollar
		'HM' => 'AUD', // Heard Island and McDonald Islands - Australian Dollar
		'HN' => 'HNL', // Honduras - Honduran Lempira
		'HR' => 'HRK', // Croatia - Croatian Kuna
		'HT' => 'HTG', // Haiti - Haitian Gourde
		'HU' => 'HUF', // Hungary - Hungarian Forint
		'ID' => 'IDR', // Indonesia - Indonesian Rupiah
		'IE' => 'EUR', // Ireland - Euro
		'IL' => 'ILS', // Israel - Israeli New Shekel
		'IM' => 'GBP', // Isle of Man - Pound Sterling
		'IN' => 'INR', // India - Indian Rupee
		'IO' => 'USD', // British Indian Ocean Territory - US Dollar
		'IQ' => 'IQD', // Iraq - Iraqi Dinar
		'IR' => 'IRR', // Iran - Iranian Rial
		'IS' => 'ISK', // Iceland - Iceland Krona
		'IT' => 'EUR', // Italy - Euro
		'JE' => 'GBP', // Jersey - Pound Sterling
		'JM' => 'JMD', // Jamaica - Jamaican Dollar
		'JO' => 'JOD', // Jordan - Jordanian Dinar
		'JP' => 'JPY', // Japan - Japanese Yen
		'KE' => 'KES', // Kenya - Kenyan Shilling
		'KG' => 'KGS', // Kyrgyzstan - Som
		'KH' => 'KHR', // Cambodia - Kampuchean Riel
		'KI' => 'AUD', // Kiribati - Australian Dollar
		'KM' => 'KMF', // Comoros - Comoros Franc
		'KN' => 'XCD', // Saint Kitts & Nevis Anguilla - East Caribbean Dollar
		'KP' => 'KPW', // Korea, North - North Korean Won
		'KR' => 'KRW', // Korea, South - Korean Won
		'KW' => 'KWD', // Kuwait - Kuwaiti Dinar
		'KY' => 'KYD', // Cayman Islands - Cayman Islands Dollar
		'KZ' => 'KZT', // Kazakhstan - Kazakhstan Tenge
		'LA' => 'LAK', // Laos - Lao Kip
		'LB' => 'LBP', // Lebanon - Lebanese Pound
		'LC' => 'XCD', // Saint Lucia - East Caribbean Dollar
		'LI' => 'CHF', // Liechtenstein - Swiss Franc
		'LK' => 'LKR', // Sri Lanka - Sri Lanka Rupee
		'LR' => 'LRD', // Liberia - Liberian Dollar
		'LS' => 'LSL', // Lesotho - Lesotho Loti
		'LT' => 'LTL', // Lithuania - Lithuanian Litas
		'LU' => 'EUR', // Luxembourg - Euro
		'LV' => 'LVL', // Latvia - Latvian Lats
		'LY' => 'LYD', // Libya - Libyan Dinar
		'MA' => 'MAD', // Morocco - Moroccan Dirham
		'MC' => 'EUR', // Monaco - Euro
		'MD' => 'MDL', // Moldova - Moldovan Leu
		'ME' => 'EUR', // Montenegro - Euro
		'MF' => 'EUR', // Saint Martin (French Part) - Euro
		'MG' => 'MGA', // Madagascar - Malagasy Ariary
		'MH' => 'USD', // Marshall Islands - US Dollar
		'MK' => 'MKD', // Macedonia - Denar
		'ML' => 'XOF', // Mali - CFA Franc BCEAO
		'MM' => 'MMK', // Myanmar - Myanmar Kyat
		'MN' => 'MNT', // Mongolia - Mongolian Tugrik
		'MO' => 'MOP', // Macau - Macau Pataca
		'MP' => 'USD', // Northern Mariana Islands - US Dollar
		'MQ' => 'EUR', // Martinique (French) - Euro
		'MR' => 'MRO', // Mauritania - Mauritanian Ouguiya
		'MS' => 'XCD', // Montserrat - East Caribbean Dollar
		'MT' => 'EUR', // Malta - Euro
		'MU' => 'MUR', // Mauritius - Mauritius Rupee
		'MV' => 'MVR', // Maldives - Maldive Rufiyaa
		'MW' => 'MWK', // Malawi - Malawi Kwacha
		'MX' => 'MXN', // Mexico - Mexican Peso
		'MY' => 'MYR', // Malaysia - Malaysian Ringgit
		'MZ' => 'MZN', // Mozambique - Mozambique Metical
		'NA' => 'NAD', // Namibia - Namibian Dollar
		'NC' => 'XPF', // New Caledonia (French) - CFP Franc
		'NE' => 'XOF', // Niger - CFA Franc BCEAO
		'NF' => 'AUD', // Norfolk Island - Australian Dollar
		'NG' => 'NGN', // Nigeria - Nigerian Naira
		'NI' => 'NIO', // Nicaragua - Nicaraguan Cordoba Oro
		'NL' => 'EUR', // Netherlands - Euro
		'NO' => 'NOK', // Norway - Norwegian Krone
		'NP' => 'NPR', // Nepal - Nepalese Rupee
		'NR' => 'AUD', // Nauru - Australian Dollar
		'NU' => 'NZD', // Niue - New Zealand Dollar
		'NZ' => 'NZD', // New Zealand - New Zealand Dollar
		'OM' => 'OMR', // Oman - Omani Rial
		'PA' => 'PAB', // Panama - Panamanian Balboa
		'PE' => 'PEN', // Peru - Peruvian Nuevo Sol
		'PF' => 'XPF', // Polynesia (French) - CFP Franc
		'PG' => 'PGK', // Papua New Guinea - Papua New Guinea Kina
		'PH' => 'PHP', // Philippines - Philippine Peso
		'PK' => 'PKR', // Pakistan - Pakistan Rupee
		'PL' => 'PLN', // Poland - Polish Zloty
		'PM' => 'EUR', // Saint Pierre and Miquelon - Euro
		'PN' => 'NZD', // Pitcairn Island - New Zealand Dollar
		'PR' => 'USD', // Puerto Rico - US Dollar
		'PS' => 'ILS', // Palestinian Territories - Israeli New Shekel
		'PT' => 'EUR', // Portugal - Euro
		'PW' => 'USD', // Palau - US Dollar
		'PY' => 'PYG', // Paraguay - Paraguay Guarani
		'QA' => 'QAR', // Qatar - Qatari Rial
		'RE' => 'EUR', // Reunion (French) - Euro
		'RO' => 'RON', // Romania - Romanian New Leu
		'RS' => 'RSD', // Serbia - Serbian Dinar
		'RU' => 'RUB', // Russia - Russian Ruble
		'RW' => 'RWF', // Rwanda - Rwanda Franc
		'SA' => 'SAR', // Saudi Arabia - Saudi Riyal
		'SB' => 'SBD', // Solomon Islands - Solomon Islands Dollar
		'SC' => 'SCR', // Seychelles - Seychelles Rupee
		'SD' => 'SDG', // Sudan - Sudanese Pound
		'SE' => 'SEK', // Sweden - Swedish Krona
		'SG' => 'SGD', // Singapore - Singapore Dollar
		'SH' => 'SHP', // Saint Helena - St. Helena Pound
		'SI' => 'EUR', // Slovenia - Euro
		'SJ' => 'NOK', // Svalbard and Jan Mayen Islands - Norwegian Krone
		'SK' => 'EUR', // Slovakia - Euro
		'SL' => 'SLL', // Sierra Leone - Sierra Leone Leone
		'SM' => 'EUR', // San Marino - Euro
		'SN' => 'XOF', // Senegal - CFA Franc BCEAO
		'SO' => 'SOS', // Somalia - Somali Shilling
		'SR' => 'SRD', // Suriname - Surinamese Dollar
		'SS' => 'SSP', // South Sudan - South Sudanese Pound
		'ST' => 'STD', // Sao Tome and Principe - Dobra
		'SV' => 'USD', // El Salvador - US Dollar
		'SX' => 'ANG', // Sint Maarten (Dutch Part) - Netherlands Antillean Guilder
		'SY' => 'SYP', // Syria - Syrian Pound
		'SZ' => 'SZL', // Swaziland - Swaziland Lilangeni
		'TC' => 'USD', // Turks and Caicos Islands - US Dollar
		'TD' => 'XAF', // Chad - CFA Franc BEAC
		'TF' => 'EUR', // French Southern Territories - Euro
		'TG' => 'XOF', // Togo - CFA Franc BCEAO
		'TH' => 'THB', // Thailand - Thai Baht
		'TJ' => 'TJS', // Tajikistan - Tajik Somoni
		'TK' => 'NZD', // Tokelau - New Zealand Dollar
		'TL' => 'USD', // Timor-Leste - US Dollar
		'TM' => 'TMM', // Turkmenistan - Manat
		'TN' => 'TND', // Tunisia - Tunisian Dinar
		'TO' => 'TOP', // Tonga - Tongan Pa&#699;anga
		'TR' => 'TRY', // Turkey - Turkish Lira
		'TT' => 'TTD', // Trinidad and Tobago - Trinidad and Tobago Dollar
		'TV' => 'AUD', // Tuvalu - Australian Dollar
		'TW' => 'TWD', // Taiwan - New Taiwan Dollar
		'TZ' => 'TZS', // Tanzania - Tanzanian Shilling
		'UA' => 'UAH', // Ukraine - Ukraine Hryvnia
		'UG' => 'UGX', // Uganda - Uganda Shilling
		'UM' => 'USD', // USA Minor Outlying Islands - US Dollar
		'US' => 'USD', // USA - US Dollar
		'UY' => 'UYU', // Uruguay - Uruguayan Peso
		'UZ' => 'UZS', // Uzbekistan - Uzbekistan Sum
		'VA' => 'EUR', // Vatican - Euro
		'VC' => 'XCD', // Saint Vincent & Grenadines - East Caribbean Dollar
		'VE' => 'VEF', // Venezuela - Venezuelan Bolivar Fuerte
		'VG' => 'USD', // Virgin Islands (British) - US Dollar
		'VI' => 'USD', // Virgin Islands (USA) - US Dollar
		'VN' => 'VND', // Vietnam - Vietnamese Dong
		'VU' => 'VUV', // Vanuatu - Vanuatu Vatu
		'WF' => 'XPF', // Wallis and Futuna Islands - CFP Franc
		'WS' => 'WST', // Samoa - Samoan Tala
		'YE' => 'YER', // Yemen - Yemeni Rial
		'YT' => 'EUR', // Mayotte - Euro
		'ZA' => 'ZAR', // South Africa - South African Rand
		'ZM' => 'ZMK', // Zambia - Zambian Kwacha
		'ZW' => 'USD', // Zimbabwe - US Dollar
	);

	/**
	 * Returns a list containing the currency to be used for each country. The
	 * method implements a filter to allow altering the currency for each country,
	 * if needed.
	 *
	 * @return array
	 */
	protected function country_currencies() {
		return apply_filters('wc_aelia_currencyswitcher_country_currencies', $this->_country_currencies);
	}

	/**
	 * Returns a list containing all world currencies.
	 *
	 * @return array
	 */
	public static function world_currencies() {
		return apply_filters('wc_aelia_currencyswitcher_world_currencies', self::$_world_currencies);
	}

	/**
	 * Returns the Currency used in a specific Country.
	 *
	 * @param string country_code The Country Code.
	 * @return string A Currency Code.
	 */
	public function get_country_currency($country_code) {
		$country_currencies = $this->country_currencies();
		return get_value($country_code, $country_currencies);
	}

	/**
	 * Returns the Currency used in the Country to which a specific IP Address
	 * belongs.
	 *
	 * @param string host A host name or IP Address.
	 * @param string default_currency The Currency to use as a default in case the
	 * Country currency could not be detected.
	 * @return string|bool A currency code, or False if an error occurred.
	 */
	public function get_currency_by_host($host, $default_currency) {
		$ip2location = WC_Aelia_IP2Location::factory();
		$country_code = $ip2location->get_country_code($host);

		if($country_code === false) {
			Logger::log(sprintf(__('Could not retrieve Country Code for host "%s". Using '.
														 'default currency: %s. Error messages (JSON): %s.',
														 AELIA_CS_PLUGIN_TEXTDOMAIN),
													$host,
													$default_currency,
													json_encode($ip2location->get_errors())));
			return $default_currency;
		}

		$country_currency = $this->get_country_currency($country_code);

		if(WC_Aelia_CurrencySwitcher::settings()->is_currency_enabled($country_currency)) {
			return $country_currency;
		}
		else {
			return $default_currency;
		}
	}

	/**
	 * Given a currency code, it returns the currency's name. If currency is not
	 * found amongst the available ones, its code is returned instead.
	 *
	 * @param string currency The currency code.
	 * @return string
	 */
	public static function get_currency_name($currency) {
		$available_currencies = get_woocommerce_currencies();
		return get_value($currency, $available_currencies, $currency);
	}

	/**
	 * Factory method.
	 *
	 * return WC_Aelia_Currencies_Manager
	 */
	public static function factory() {
		return new self();
	}

	// @var array A list of all world currencies
	protected static $_world_currencies = array(
		'AED' => 'United Arab Emirates dirham',
		'AFN' => 'Afghan afghani',
		'ALL' => 'Albanian lek',
		'AMD' => 'Armenian dram',
		'ANG' => 'Netherlands Antillean guilder',
		'AOA' => 'Angolan kwanza',
		'ARS' => 'Argentine peso',
		'AUD' => 'Australian dollar',
		'AWG' => 'Aruban florin',
		'AZN' => 'Azerbaijani manat',
		'BAM' => 'Bosnia and Herzegovina convertible mark',
		'BBD' => 'Barbadian dollar',
		'BDT' => 'Bangladeshi taka',
		'BGN' => 'Bulgarian lev',
		'BHD' => 'Bahraini dinar',
		'BIF' => 'Burundian franc',
		'BMD' => 'Bermudian dollar',
		'BND' => 'Brunei dollar',
		'BOB' => 'Bolivian boliviano',
		'BRL' => 'Brazilian real',
		'BSD' => 'Bahamian dollar',
		'BTN' => 'Bhutanese ngultrum',
		'BWP' => 'Botswana pula',
		'BYR' => 'Belarusian ruble',
		'BZD' => 'Belize dollar',
		'CAD' => 'Canadian dollar',
		'CDF' => 'Congolese franc',
		'CHF' => 'Swiss franc',
		'CLP' => 'Chilean peso',
		'CNY' => 'Chinese yuan',
		'COP' => 'Colombian peso',
		'CRC' => 'Costa Rican colón',
		'CUC' => 'Cuban convertible peso',
		'CUP' => 'Cuban peso',
		'CVE' => 'Cape Verdean escudo',
		'CZK' => 'Czech koruna',
		'DJF' => 'Djiboutian franc',
		'DKK' => 'Danish krone',
		'DOP' => 'Dominican peso',
		'DZD' => 'Algerian dinar',
		'EGP' => 'Egyptian pound',
		'ERN' => 'Eritrean nakfa',
		'ETB' => 'Ethiopian birr',
		'EUR' => 'Euro',
		'FJD' => 'Fijian dollar',
		'FKP' => 'Falkland Islands pound',
		'GBP' => 'British pound',
		'GEL' => 'Georgian lari',
		'GGP' => 'Guernsey pound',
		'GHS' => 'Ghana cedi',
		'GIP' => 'Gibraltar pound',
		'GMD' => 'Gambian dalasi',
		'GNF' => 'Guinean franc',
		'GTQ' => 'Guatemalan quetzal',
		'GYD' => 'Guyanese dollar',
		'HKD' => 'Hong Kong dollar',
		'HNL' => 'Honduran lempira',
		'HRK' => 'Croatian kuna',
		'HTG' => 'Haitian gourde',
		'HUF' => 'Hungarian forint',
		'IDR' => 'Indonesian rupiah',
		'ILS' => 'Israeli new shekel',
		'IMP' => 'Manx pound',
		'INR' => 'Indian rupee',
		'IQD' => 'Iraqi dinar',
		'IRR' => 'Iranian rial',
		'ISK' => 'Icelandic króna',
		'JEP' => 'Jersey pound',
		'JMD' => 'Jamaican dollar',
		'JOD' => 'Jordanian dinar',
		'JPY' => 'Japanese yen',
		'KES' => 'Kenyan shilling',
		'KGS' => 'Kyrgyzstani som',
		'KHR' => 'Cambodian riel',
		'KMF' => 'Comorian franc',
		'KPW' => 'North Korean won',
		'KRW' => 'South Korean won',
		'KWD' => 'Kuwaiti dinar',
		'KYD1' => 'Cayman Islands dollar',
		'KZT' => 'Kazakhstani tenge',
		'LAK' => 'Lao kip',
		'LBP' => 'Lebanese pound',
		'LKR' => 'Sri Lankan rupee',
		'LRD' => 'Liberian dollar',
		'LSL' => 'Lesotho loti',
		'LTL' => 'Lithuanian litas',
		'LYD' => 'Libyan dinar',
		'MAD' => 'Moroccan dirham',
		'MDL' => 'Moldovan leu',
		'MGA' => 'Malagasy ariary',
		'MKD' => 'Macedonian denar',
		'MMK' => 'Burmese kyat',
		'MNT' => 'Mongolian tögrög',
		'MOP' => 'Macanese pataca',
		'MRO' => 'Mauritanian ouguiya',
		'MUR' => 'Mauritian rupee',
		'MVR' => 'Maldivian rufiyaa',
		'MWK' => 'Malawian kwacha',
		'MXN' => 'Mexican peso',
		'MYR' => 'Malaysian ringgit',
		'MZN' => 'Mozambican metical',
		'NAD' => 'Namibian dollar',
		'NGN' => 'Nigerian naira',
		'NIO' => 'Nicaraguan córdoba',
		'NOK' => 'Norwegian krone',
		'NPR' => 'Nepalese rupee',
		'NZD' => 'New Zealand dollar',
		'OMR' => 'Omani rial',
		'PAB' => 'Panamanian balboa',
		'PEN' => 'Peruvian nuevo sol',
		'PGK' => 'Papua New Guinean kina',
		'PHP' => 'Philippine peso',
		'PKR' => 'Pakistani rupee',
		'PLN' => 'Polish złoty',
		'PRB' => 'Transnistrian ruble',
		'PYG' => 'Paraguayan guaraní',
		'QAR' => 'Qatari riyal',
		'RON' => 'Romanian leu',
		'RSD' => 'Serbian dinar',
		'RUB' => 'Russian ruble',
		'RWF' => 'Rwandan franc',
		'SAR' => 'Saudi riyal',
		'SBD' => 'Solomon Islands dollar',
		'SCR' => 'Seychellois rupee',
		'SDG' => 'Sudanese pound',
		'SEK' => 'Swedish krona',
		'SGD' => 'Singapore dollar',
		'SHP' => 'Saint Helena pound',
		'SLL' => 'Sierra Leonean leone',
		'SOS' => 'Somali shilling',
		'SRD' => 'Surinamese dollar',
		'SSP' => 'South Sudanese pound',
		'STD' => 'São Tomé and Príncipe dobra',
		'SYP' => 'Syrian pound',
		'SZL' => 'Swazi lilangeni',
		'THB' => 'Thai baht',
		'TJS' => 'Tajikistani somoni',
		'TMT' => 'Turkmenistan manat',
		'TND' => 'Tunisian dinar',
		'TOP' => 'Tongan paʻanga',
		'TRY' => 'Turkish lira',
		'TTD' => 'Trinidad and Tobago dollar',
		'TWD' => 'New Taiwan dollar',
		'TZS' => 'Tanzanian shilling',
		'UAH' => 'Ukrainian hryvnia',
		'UGX' => 'Ugandan shilling',
		'USD' => 'United States dollar',
		'UYU' => 'Uruguayan peso',
		'UZS' => 'Uzbekistani som',
		'VEF' => 'Venezuelan bolívar',
		'VND' => 'Vietnamese đồng',
		'VUV' => 'Vanuatu vatu',
		'WST' => 'Samoan tālā',
		'XAF' => 'Central African CFA franc',
		'XCD' => 'East Caribbean dollar',
		'XOF' => 'West African CFA franc',
		'XPF' => 'CFP franc',
		'YER' => 'Yemeni rial',
		'ZAR' => 'South African rand',
		'ZMW' => 'Zambian kwacha',
	);
}
