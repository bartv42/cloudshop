<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Implements a base class to store and handle the messages returned by the
 * plugin.
 */
class Settings {
	// @var string The decimal separator used by WC
	protected static $wc_decimal_separator;
	// @var string The thousand separator used by WC
	protected static $wc_thousand_separator;
	// @var string The amount of price decimals used by WC
	protected static $wc_price_decimals;
	// @var string The base currency used by WC
	protected static $wc_base_currency;

	// @var int The default amount of decimals to use for prices, if such information cannot be found or retrieved
	const DEFAULT_DECIMALS = 2;

	// @var string The text domain to be used for localisation.
	public $textdomain = '';
	// @var string The key to identify plugin settings amongst WP options.
	public $settings_key;
	// @var string The renderer used to render the settings page.
	protected $_renderer;

	// @var int Value indicating ifa feature was enabled
	const ENABLED_YES = 1;
	// @var int Value indicating ifa feature was disabled
	const ENABLED_NO = 0;

	// @var array Stores current plugin settings.
	protected $_current_settings;

	/**
	 * Class constructor.
	 *
	 * @param string settings_key The key used to store and retrieve the plugin settings.
	 * @param string textdomain The text domain used for localisation.
	 * @param string renderer The renderer to use to generate the settings page.
	 * @return Aelia\WC\Settings
	 */
	public function __construct($settings_key, $textdomain = '', Settings_Renderer $renderer) {
		$this->settings_key = $settings_key;
		$this->textdomain = $textdomain;
		$this->_renderer = $renderer;

		add_action('admin_init', array($this, 'init_settings'));
	}

	/**
	 * Returns the base currency used by WooCommerce.
	 *
	 * @return string A currency code.
	 */
	public function base_currency() {
		if(empty(self::$wc_base_currency)) {
			self::$wc_base_currency = get_option('woocommerce_currency');
		}

		return self::$wc_base_currency;
	}

	/**
	 * Returns the decimal separator used by WC.
	 *
	 * @return string
	 */
	public static function decimal_separator() {
		if(empty(self::$wc_decimal_separator)) {
			self::$wc_decimal_separator = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_decimal_sep')), ENT_QUOTES);
		}

		return self::$wc_decimal_separator;
	}

	/**
	 * Returns the thousand separator used by WC.
	 *
	 * @return string
	 */
	public static function thousand_separator() {
		if(empty(self::$wc_thousand_separator)) {
			self::$wc_thousand_separator = wp_specialchars_decode(stripslashes(get_option('woocommerce_price_thousand_sep')), ENT_QUOTES);
		}

		return self::$wc_thousand_separator;
	}

	/**
	 * Returns the amount of price decimals configured in WooCommerce.
	 *
	 * @return int
	 */
	public function price_decimals() {
		if(empty(self::$wc_price_decimals)) {
			self::$wc_price_decimals = (int)get_option('woocommerce_price_num_decimals');
		}

		return self::$wc_price_decimals;
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
	 */
	public function default_settings($key = null, $default = array()) {
		return $default;
	}

	/**
	 * Returns current plugin settings, or the value a specific setting.
	 *
	 * @param string key If specified, method will return only the setting identified
	 * by the key.
	 * @param mixed default The default value to return if the setting requested
	 * via the "key" argument is not found.
	 * @return array|mixed The plugin settings, or the value of the specified
	 * setting.
	 */
	public function current_settings($key = null, $default = null) {
		if(empty($this->_current_settings)) {
			$this->_current_settings = $this->load();
		}

		if(empty($key)) {
			return $this->_current_settings;
		}
		else {
			return get_value($key, $this->_current_settings, $default);
		}
	}

	/**
	 * Returns current plugin settings, or the value a specific setting.
	 *
	 * @param string key If specified, method will return only the setting identified
	 * by the key.
	 * @param mixed default The default value to return if the setting requested
	 * via the "key" argument is not found.
	 * @return array|mixed The plugin settings, or the value of the specified
	 * setting.
	 */
	public function get($key = null, $default = null) {
		return $this->current_settings($key, $default);
	}

	/**
	 * Loads plugin settings from WP database.
	 *
	 * @return array An array containing the plugin settings.
	 */
	public function load() {
		return get_option($this->settings_key);
	}

	/**
	 * Saves plugin settings to WP database. Existing settings are merged with
	 * the ones passed as a parameter.
	 *
	 * @param array settings An array of settings.
	 */
	public function save(array $settings = array()) {
		$current_settings = $this->load();
		$current_settings = empty($current_settings) ? $this->default_settings() : $current_settings;

		$settings = array_merge($current_settings, $settings);

		update_option($this->settings_key, $settings);
		// Invalidate cached settings, so that they will be loaded again when requested
		unset($this->_current_settings);
	}

	/**
	 * Deletes plugin settings from WP database.
	 */
	public function delete() {
		delete_option($this->settings_key);
	}

	/**
	 * Initialises plugin's settings.
	 */
	public function init_settings() {
		// Prepare settings page for rendering
		$this->_renderer->init_settings_page($this);

	  // Register settings.
		register_setting($this->settings_key, $this->settings_key, array($this, 'validate_settings'));
	}

	/**
	 * Validates the settings specified via the Options page. This method should
	 * be overridden by descendant classes.
	 *
	 * @param array settings An array of settings.
	 */
	public function validate_settings($settings) {
		return $settings;
	}

	/**
	 * Given an array of Code => Message pairs, adds them to the settings errors.
	 *
	 * @param array errors An array of Code => Message pairs.
	 */
	protected function add_multiple_settings_errors(array $errors) {
		foreach($errors as $code => $message) {
			add_settings_error($this->settings_key, $code, $message);
		}
	}
}
