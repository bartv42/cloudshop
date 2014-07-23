<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

//define('SCRIPT_DEBUG', 1);
//error_reporting(E_ALL);

require_once('lib/classes/base/plugin/aelia-plugin.php');

/**
 * Aelia Foundation Classes for WooCommerce.
 **/
class WC_AeliaFoundationClasses extends Aelia_Plugin {
	public static $version = '1.0.9.140717';

	public static $plugin_slug = 'wc-aelia-foundation-classes';
	public static $text_domain = 'wc-aelia-foundation-classes';
	public static $plugin_name = 'Aelia Foundation Classes for WooCommerce';

	public static function factory() {
		// Load Composer autoloader
		require_once(__DIR__ . '/vendor/autoload.php');

		$settings_key = self::$plugin_slug;

		$settings_controller = null;
		$messages_controller = null;

		$plugin_instance = new self($settings_controller, $messages_controller);
		return $plugin_instance;
	}

	/**
	 * Constructor.
	 *
	 * @param Aelia\WC\Settings settings_controller The controller that will handle
	 * the plugin settings.
	 * @param Aelia\WC\Messages messages_controller The controller that will handle
	 * the messages produced by the plugin.
	 */
	public function __construct($settings_controller,
															$messages_controller) {
		// Load Composer autoloader
		require_once(__DIR__ . '/vendor/autoload.php');
		require_once('lib/wc-core-aux-functions.php');

		parent::__construct($settings_controller, $messages_controller);
	}
}

// Instantiate plugin and add it to the set of globals
$GLOBALS[WC_AeliaFoundationClasses::$plugin_slug] = WC_AeliaFoundationClasses::factory();
