<?php
namespace Aelia\CurrencySwitcher;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Writes to the log used by the plugin.
 */
class Logger {
	// @var string The log id.
	public static $log_id = 'aelia_currencyswitcher';
	// @var WC_Logger The logger instance.
	private static $_logger;
	// @var bool Indicates if debug mode is active
	private static $_debug_mode = null;

	/**
	 * Returns the logger instance, creating one on the fly if needed.
	 *
	 * @return WC_Logger
	 */
	protected static function wc_logger() {
		if(empty(self::$_logger)) {
			global $woocommerce;

			self::$_logger = new \WC_Logger();
		}

		return self::$_logger;
	}

	/**
	 * Indicates if debug mode is active.
	 *
	 * @return bool
	 */
	protected static function debug_mode() {
		if(self::$_debug_mode === null) {
			self::$_debug_mode = \WC_Aelia_CurrencySwitcher::settings()->debug_mode();
		}

		return self::$_debug_mode;
	}

	/**
	 * Determines if WordPress maintenance mode is active.
	 *
	 * @return bool
	 */
	protected static function maintenance_mode() {
		return file_exists(ABSPATH . '.maintenance') || defined('WP_INSTALLING');
	}

	/**
	 * Adds a message to the log.
	 *
	 * @param string message The message to log.
	 * @param bool is_debug_msg Indicates if the message should only be logged
	 * while debug mode is true.
	 */
	public static function log($message, $is_debug_msg = true) {
		// Don't log messages while maintenance mode is active. This is necessary
		// because log messages are being logged by WooCommerce, which WordPress
		// could be trying to remove. Logging in that phase could cause the plugin
		// update to fail
		if(self::maintenance_mode()) {
			return true;
		}

		if($is_debug_msg && !self::debug_mode()) {
			return;
		}

		$message = sprintf('[PID %s] %s', getmypid(), $message);

		self::wc_logger()->add(self::$log_id, $message);
	}
}
