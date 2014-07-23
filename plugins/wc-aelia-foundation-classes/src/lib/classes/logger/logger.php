<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Writes to the log used by the plugin.
 */
class Logger {
	// @var string The log id.
	public $log_id = '';
	// @var bool Indicates if debug mode is active.
	protected $_debug_mode = null;
	// @var WC_Logger The WooCommerce logger used to log messages
	protected $wc_logger;

	/**
	 * Returns global instance of WooCommerce.
	 *
	 * @return object The global instance of WC.
	 */
	protected function wc() {
		global $woocommerce;
		return $woocommerce;
	}

	/**
	 * Returns the logger instance, creating one on the fly if needed.
	 *
	 * @return WC_Logger
	 */
	protected function wc_logger() {
		if(empty($this->wc_logger)) {
			$this->wc_logger = new \WC_Logger();
		}

		return $this->wc_logger;
	}

	/**
	 * Retrieves the "debug mode" setting. To be implemented by descendant classes.
	 *
	 * @return bool
	 */
	protected function get_debug_mode() {
		return false;
	}

	/**
	 * Indicates if debug mode is active.
	 *
	 * @return bool
	 */
	protected function debug_mode() {
		if($this->_debug_mode === null) {
			$this->_debug_mode = static::get_debug_mode();
		}

		return $this->_debug_mode;
	}

	/**
	 * Determines if WordPress maintenance mode is active.
	 *
	 * @return bool
	 */
	protected function maintenance_mode() {
		return file_exists(ABSPATH . '.maintenance') || defined('WP_INSTALLING');
	}

	/**
	 * Adds a message to the log.
	 *
	 * @param string message The message to log.
	 * @param bool is_debug_msg Indicates if the message should only be logged
	 * while debug mode is true.
	 */
	public function log($message, $is_debug_msg = true) {
		// Don't log messages while maintenance mode is active. This is necessary
		// because log messages are being logged by WooCommerce, which WordPress
		// could be trying to remove. Logging in that phase could cause the plugin
		// update to fail
		if($this->maintenance_mode()) {
			return true;
		}

		if($is_debug_msg && !$this->debug_mode()) {
			return;
		}

		$message = sprintf('%s - [PID %s] %s', $this->log_id, getmypid(), $message);
		$this->wc_logger()->add($this->log_id, $message);
	}

	/**
	 * Class constructor.
	 *
	 * @param string log_id The identifier for the log.
	 */
	public function __construct($log_id) {
		$this->log_id = $log_id;
	}

	/**
	 * Factory method.
	 *
	 * @param string log_id The identifier for the log.
	 * @return Aelia\WC\Logger.
	 */
	public static function factory($log_id) {
		$class = get_called_class();
		return new $class($log_id);
	}
}
