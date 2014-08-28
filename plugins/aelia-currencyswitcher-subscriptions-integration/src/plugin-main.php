<?php
namespace Aelia\WC\CurrencySwitcher\Subscriptions;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

require_once('lib/classes/definitions/definitions.php');

use Aelia\WC\Aelia_Plugin;
use Aelia\WC\Aelia_SessionManager;
use Aelia\WC\Messages;

/**
 * Aelia Currency Switcher Subscriptions Integration plugin.
 **/
class WC_Aelia_CS_Subscriptions_Plugin extends Aelia_Plugin {
	public static $version = '1.2.6.140820';

	public static $plugin_slug = Definitions::PLUGIN_SLUG;
	public static $text_domain = Definitions::TEXT_DOMAIN;
	public static $plugin_name = 'Aelia Currency Switcher - Subscriptions Integration';

	public static function factory() {
		// Load Composer autoloader
		require_once(__DIR__ . '/vendor/autoload.php');

		$settings_key = self::$plugin_slug;

		$messages_controller = new Messages();

		$plugin_instance = new self(null, $messages_controller);
		return $plugin_instance;
	}

	/**
	 * Constructor.
	 *
	 * @param \Aelia\WC\Settings settings_controller The controller that will handle
	 * the plugin settings.
	 * @param \Aelia\WC\Messages messages_controller The controller that will handle
	 * the messages produced by the plugin.
	 */
	public function __construct($settings_controller = null, $messages_controller = null) {
		// Load Composer autoloader
		require_once(__DIR__ . '/vendor/autoload.php');

		parent::__construct($settings_controller, $messages_controller);

		$this->initialize_integration();
	}

	protected function initialize_integration() {
		$this->subscriptions_integration = new Subscriptions_Integration();
	}

	/**
	 * Determines if one of plugin's admin pages is being rendered. Override it
	 * if plugin implements pages in the Admin section.
	 *
	 * @return bool
	 */
	protected function rendering_plugin_admin_page() {
		global $post, $woocommerce;

		return isset($post) && ($post->post_type == 'product');
	}
}


$GLOBALS[WC_Aelia_CS_Subscriptions_Plugin::$plugin_slug] = WC_Aelia_CS_Subscriptions_Plugin::factory();
