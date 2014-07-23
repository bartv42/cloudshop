<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Implements a base class to store and handle the messages returned by the
 * plugin. This class is used to extend the basic functionalities provided by
 * standard WP_Error class.
 */
class Messages {
	const DEFAULT_TEXTDOMAIN = 'wc-aelia';

	// Result constants
	const RES_OK = 0;
	const ERR_FILE_NOT_FOUND = 100;
	const ERR_NOT_IMPLEMENTED = 101;
	const ERR_INVALID_TEMPLATE = 102;
	const ERR_INVALID_WIDGET_CLASS = 103;

	// @var WP_Error Holds the error messages registered by the plugin
	protected $wp_error;

	// @var string The text domain used by the class
	protected $text_domain = self::DEFAULT_TEXTDOMAIN;

	public function __construct($text_domain = self::DEFAULT_TEXTDOMAIN) {
		$this->text_domain = $text_domain;
		$this->wp_error = new \WP_Error();
		$this->load_error_messages();
	}

	/**
	 * Loads all the messages used by the plugin. This class should be
	 * extended during implementation, to add all error messages used by
	 * the plugin.
	 */
	public function load_messages() {
		$this->add_message(self::ERR_FILE_NOT_FOUND, __('File not found: "%s".', $this->text_domain));
		$this->add_message(self::ERR_NOT_IMPLEMENTED, __('Not implemented.', $this->text_domain));
		$this->add_error_message(self::ERR_INVALID_TEMPLATE,
														 __('Rendering - Requested template could not be found in either plugin\'s ' .
																'folders, nor in your theme. Plugin slug: "%s". Template name: "%s".'.
																$this->text_domain));

		// TODO Add here all the error messages used by the plugin
	}

	/**
	 * Registers an error message in the internal wp_error object.
	 *
	 * @param mixed error_code The Error Code.
	 * @param string error_message The Error Message.
	 */
	public function add_message($error_code, $error_message) {
		$this->wp_error->add($error_code, $error_message);
	}

	/**
	 * Retrieves an error message from the internal wp_error object.
	 *
	 * @param mixed error_code The Error Code.
	 * @return string The Error Message corresponding to the specified Code.
	 */
	public function get_message($error_code) {
		return $this->wp_error->get_error_message($error_code);
	}

	/**
	 * Calls Aelia\WC\Messages::load_messages(). Implemented for backward
	 * compatibility.
	 */
	public function load_error_messages() {
		$this->load_messages();
	}

	/**
	 * Calls Aelia\WC\Messages::add_message(). Implemented for backward
	 * compatibility.
	 */
	public function add_error_message($error_code, $error_message) {
		$this->add_message($error_code, $error_message);
	}

	/**
	 * Calls Aelia\WC\Messages::get_message(). Implemented for backward
	 * compatibility.
	 */
	public function get_error_message($error_code) {
		return $this->get_message($error_code);
	}
}
