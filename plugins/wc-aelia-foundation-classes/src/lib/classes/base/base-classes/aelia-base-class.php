<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use Aelia\WC\Logger as Logger;

class Base_Class {
	// @var Aelia\WC\Logger The logger used by the class.
	protected $logger;

	/**
	 * Logs a message.
	 *
	 * @param string message The message to log.
	 * @param bool debug Indicates if the message is for debugging. Debug messages
	 * are not saved if the "debug mode" flag is turned off.
	 */
	protected function log($message, $debug = true) {
		$this->logger->log($message, $debug);
	}

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
	 * Class constructor.
	 */
	public function __construct() {
		$this->logger = new Logger(get_class());
	}
}
