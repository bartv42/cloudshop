<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use GeoIp2\Database\Reader;

/**
 * Handles the retrieval of Geolocation information from an IP Address.
 */
class IP2Location {
	private $text_domain = 'aelia_ip2location';

	protected static $geoip_db_file = 'geolite-db/GeoLite2-Country.mmdb';
	protected $errors = array();

	public static function geoip_db_file() {
		return __DIR__ . '/' . self::$geoip_db_file;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
	}

	/**
	 * Factory method.
	 *
	 * @return Aelia\WC\IP2Location.
	 */
	public static function factory() {
		return new self();
	}

	public function get_errors(){
		return implode("\n", $this->errors);
	}

	/**
	 * Returns the 2-digit Country Code matching a given IP address.
	 *
	 * @param string ip_address The IP address for which to retrieve the Country Code.
	 * @return string|bool A Country Code on success, or False on failure.
	 */
	public function get_country_code($ip_address){
		//$ip_address = @gethostbyname($host);

		$country_code = '';
		// IP address must be either an IPv4 or an IPv6
		if((filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) &&
			 (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false)) {
			$this->errors[] = sprintf(__('Method IP2Location::get_country_code() expects a valid IPv4 or IPv6 ' .
																	 'address (it will not work with host names). "%s" was passed, which is ' .
																	 'not a valid address.',
																	 $this->text_domain),
																$ip_address);
			$country_code = false;
		}

		if($country_code !== false) {
			try {
				// Create the Reader object, which should be reused across lookups.
				$reader = new Reader(self::geoip_db_file());
				$record = $reader->country($ip_address);

				$country_code = $record->country->isoCode;
			}
			catch(\Exception $e) {
				$this->errors[] = sprintf(__('Error(s) occurred while retrieving Geolocation information ' .
																		 'for IP Address "%s". Error: %s.',
																		 $this->text_domain),
																	$ip_address,
																	$e->getMessage());
				$country_code = false;
			}
		}

		return apply_filters('wc_aelia_ip2location_country_code', $country_code, $host);
	}

	/**
	 * Returns the visitor's IP address, handling the case in which a standard
	 * reverse proxy is used.
	 *
	 * @return string
	 */
	public function get_visitor_ip_address() {
		$forwarded_for = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

		// Field HTTP_X_FORWARDED_FOR may contain multiple addresses, separated by a
		// comma. The first one is the real client, followed by intermediate proxy
		// servers
		$ip_addresses = explode(',', $forwarded_for);
		$visitor_ip = trim(array_shift($ip_addresses));

		$visitor_ip = apply_filters('wc_aelia_visitor_ip', $visitor_ip, $forwarded_for);
		return $visitor_ip;
	}

	/**
	 * Returns the visitor's country, deriving it from his IP address.
	 *
	 * @return string
	 */
	public function get_visitor_country() {
		return $this->get_country_code($this->get_visitor_ip_address());
	}
}
