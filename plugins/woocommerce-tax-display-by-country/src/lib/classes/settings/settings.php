<?php
namespace Aelia\WC\TaxDisplayByCountry;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Handles the settings for the Blacklister plugin and provides convenience
 * methods to read and write them.
 */
class Settings extends \Aelia\WC\Settings {
	/*** Settings Key ***/
	// @var string The key to identify plugin settings amongst WP options.
	const SETTINGS_KEY = 'wc_aelia_tax_display_by_country';

	/*** Settings fields ***/
	const FIELD_TAX_DISPLAY_SETTINGS = 'tax_display_settings';
	const FIELD_TAX_DISPLAY_SETTINGS_MESSAGE = 'tax_display_settings_message';

	// @var array A list of tax display settings for each country
	protected $tax_display_for_country = array();

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
		// TODO Implement method
		$default_options = array(
			self::FIELD_TAX_DISPLAY_SETTINGS => array(
			),
		);

		if(empty($key)) {
			return $default_options;
		}
		else {
			return get_value($key, $default_options, $default);
		}
	}

	/**
	 * Returns an array containing the IP addresses that have been blacklisted.
	 *
	 * @return array
	 */
	public function get_tax_display_settings() {
		$result = $this->current_settings(self::FIELD_TAX_DISPLAY_SETTINGS);
		if(!is_array($result)) {
			$result = array();
		}

		return $result;
	}

	/**
	 * Returns the tax display method (including/excluding taxes) to use for the
	 * specified country.
	 *
	 * @param string country_code The country code.
	 * @param string tax_display_type The display type to retrieve. It can have
	 * one of the following values:
	 * - woocommerce_tax_display_shop
	 * - woocommerce_tax_display_cart
	 * @return string|null
	 */
	public function get_tax_display_for_country($country_code, $tax_display_type) {
		$tax_display_key = sprintf('%s-%s', $country_code, $tax_display_type);
		if(isset($this->tax_display_for_country[$tax_display_key])) {
			return $this->tax_display_for_country[$tax_display_key];
		}

		$result = null;
		foreach($this->get_tax_display_settings() as $index => $settings) {
			// Extract the tax display setting from the first group that matches the
			// passed country
			if(in_array($country_code, $settings['countries'])) {
				$result = get_value($tax_display_type, $settings);

				// If a setting is found, stop and return it. If not, look for another
				// group that might match the country. This is useful in case of "dirty"
				// data, where a group might match the country, but not contain the
				// setting
				if(!empty($result)) {
					break;
				}
			}
		}

		$this->tax_display_for_country[$tax_display_key] = $result;

		return $result;
	}

	/**
	 * Returns the price suffix (including/excluding taxes) to use for the
	 * specified country.
	 *
	 * @param string country_code The country code.
	 * @return string|null
	 */
	public function get_price_suffix_for_country($country_code) {
		$result = null;
		foreach($this->get_tax_display_settings() as $index => $settings) {
			// Extract the tax display setting from the first group that matches the
			// passed country
			if(in_array($country_code, $settings['countries'])) {
				$result = get_value('price_suffix', $settings);

				// If a setting is found, stop and return it. If not, look for another
				// group that might match the country. This is useful in case of "dirty"
				// data, where a group might match the country, but not contain the
				// setting
				if(!empty($result)) {
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Validates the settings specified via the Options page.
	 *
	 * @param array settings An array of settings.
	 */
	public function validate_settings($settings) {
		// Debug
		//var_dump($settings);die();
		$processed_settings = $this->current_settings();

		$tax_display_settings = get_value(self::FIELD_TAX_DISPLAY_SETTINGS, $settings, array());
		// Remove invalid rows (e.g. ones without selected countries)
		$processed_settings[self::FIELD_TAX_DISPLAY_SETTINGS] = $this->cleanup_invalid_entries($tax_display_settings);

		// Return the array processing any additional functions filtered by this action.
		return apply_filters('wc_aelia_tax_display_by_country_settings', $processed_settings, $settings);
	}

	/**
	 * Class constructor.
	 */
	public function __construct($settings_key = self::SETTINGS_KEY,
															$textdomain = '',
															\Aelia\WC\Settings_Renderer $renderer = null) {
		if(empty($renderer)) {
			// Instantiate the render to be used to generate the settings page
			$renderer = new \Aelia\WC\Settings_Renderer();
		}
		parent::__construct($settings_key, $textdomain, $renderer);

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
	 * @return \Aelia\WC\Settings
	 */
	public static function factory($settings_key = self::SETTINGS_KEY,
																 $textdomain = '') {
		$class = get_called_class();
		$settings_manager = new $class($settings_key, $textdomain);

		return $settings_manager;
	}

	/*** Validation methods ***/
	protected function cleanup_invalid_entries($tax_display_settings) {
		$field_error_messages = array();

		// TODO Validate tax display settings
		foreach($tax_display_settings as $index => $settings) {
			$countries = get_value('countries', $settings);

			if(empty($countries) || !is_array($countries)) {
				$field_error_messages[self::FIELD_TAX_DISPLAY_SETTINGS_MESSAGE] =
					sprintf(__('Groups without any selected countries have been removed.',
										 $this->textdomain),
									$index);
					unset($tax_display_settings[$index]);
			}
		}

		return $tax_display_settings;
	}
}
