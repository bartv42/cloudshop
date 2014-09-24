<?php
namespace Aelia\WC\TaxDisplayByCountry;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

//define('SCRIPT_DEBUG', 1);
//error_reporting(E_ALL);

require_once('lib/classes/definitions/definitions.php');

use Aelia\WC\Aelia_Plugin;
use Aelia\WC\Aelia_SessionManager;
use Aelia\WC\IP2Location;
use Aelia\WC\TaxDisplayByCountry\Settings;
use Aelia\WC\TaxDisplayByCountry\Settings_Renderer;
use Aelia\WC\Messages;


/**
 * Tax Display by Country plugin.
 **/
class WC_Aelia_Tax_Display_By_Country extends Aelia_Plugin {
	public static $version = '1.5.12.140924';

	public static $plugin_slug = Definitions::PLUGIN_SLUG;
	public static $text_domain = Definitions::TEXT_DOMAIN;
	public static $plugin_name = 'Aelia Tax Display by Country for WooCommerce';

	// @var string The billing country currently active
	protected $billing_country = null;

	public static function factory() {
		// Load Composer autoloader
		require_once(__DIR__ . '/vendor/autoload.php');

		$settings_controller = null;
		$messages_controller = null;
		// Example on how to initialise a settings controller and a messages controller
		$settings_page_renderer = new Settings_Renderer();
		$settings_controller = new Settings(Settings::SETTINGS_KEY,
																				self::$text_domain,
																				$settings_page_renderer);
		$messages_controller = new Messages();

		$plugin_instance = new self($settings_controller, $messages_controller);
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
	public function __construct($settings_controller = null,
															$messages_controller = null) {
		// Load Composer autoloader
		require_once(__DIR__ . '/vendor/autoload.php');

		parent::__construct($settings_controller, $messages_controller);
		$this->set_hooks();

		// The commented line below is needed for Codestyling Localization plugin to
		// understand what text domain is used by this plugin
		//load_plugin_textdomain(static::$text_domain, false, $this->path('languages') . '/');
	}

	/**
	 * Performs operation when woocommerce has been loaded.
	 */
	public function woocommerce_loaded() {
		// Run updates only when in Admin area. This should occur automatically when
		// plugin is activated, since it's done in the Admin area
		if(is_admin()) {
			$this->run_updates();
		}

		// If billing country has been explicitly selected, override the information
		// on customer details
		if($this->billing_country_selected()) {
			$this->set_customer_country($this->get_billing_country());
		}
	}

	/**
	 * Determines if user has selected a billing country using the widget.
	 *
	 * @return bool
	 */
	protected function billing_country_selected() {
		return isset($_POST[Definitions::ARG_AELIA_BILLING_COUNTRY]);
	}

	/**
	 * Sets the billing and shipping country on the user object.
	 *
	 * @param string country A country code.
	 */
	protected function set_customer_country($country) {
		$woocommerce = $this->wc();
		if(isset($woocommerce->customer)) {
			$woocommerce->customer->set_location($country, '');
			$woocommerce->customer->set_shipping_location($country);
		}
	}

	/**
	 * Sets the hooks required by the plugin.
	 */
	protected function set_hooks() {
		parent::set_hooks();

		if(!is_admin() || self::doing_ajax()) {
			// Add hooks to alter the tax display flag depending on user's country
			add_filter('option_woocommerce_tax_display_shop', array($this, 'option_woocommerce_tax_display_shop'));
			add_filter('option_woocommerce_tax_display_cart', array($this, 'option_woocommerce_tax_display_cart'));
			add_filter('woocommerce_get_price_suffix', array($this, 'woocommerce_get_price_suffix'), 10, 2);
			add_filter('woocommerce_countries_ex_tax_or_vat', array($this, 'woocommerce_countries_ex_tax_or_vat'), 10, 1);
			add_filter('woocommerce_countries_inc_tax_or_vat', array($this, 'woocommerce_countries_inc_tax_or_vat'), 10, 1);
		}

		// Register Widgets
		add_action('widgets_init', array($this, 'register_widgets'));

		// Set the default checkout country to the one selected by the user
		add_filter('default_checkout_country', array($this, 'default_checkout_country'));
		add_filter('get_user_metadata', array($this, 'get_user_metadata'), 10, 4);

		// Add hooks for shortcodes
		$this->set_shortcodes_hooks();
	}

	/**
	 * Returns the country code for the user, detecting it using the IP Address,
	 * if needed.
	 * IMPORTANT: WooCommerce stores the billing country in its "customer" property,
	 * while this method uses WooCommerce session when the billing country is selected.
	 * This must be done because the tax display option is retrieved by WooCommerce
	 * BEFORE the "customer" property is initialised. If we relied on such property,
	 * very often it would be empty, and we would return the incorrect country code.
	 *
	 * @return string
	 */
	public function get_billing_country() {
		if(!empty($this->billing_country)) {
			return $this->billing_country;
		}

		$woocommerce = $this->wc();
		$country = null;

		if(self::doing_ajax() && isset($_POST['action']) && ($_POST['action'] === 'woocommerce_update_order_review')) {
			// If user is on checkout page and changes the billing country, get the
			// country code and store it in the session
			check_ajax_referer('update-order-review', 'security');
			if(isset($_POST[Definitions::ARG_BILLING_COUNTRY])) {
				$country = $_POST[Definitions::ARG_BILLING_COUNTRY];
				Aelia_SessionManager::set_value(Definitions::SESSION_BILLING_COUNTRY, $country);
			}
		}

		if(empty($country)) {
			if(isset($_POST[Definitions::ARG_AELIA_BILLING_COUNTRY])) {
				$country = $_POST[Definitions::ARG_AELIA_BILLING_COUNTRY];
				Aelia_SessionManager::set_value(Definitions::SESSION_BILLING_COUNTRY, $country);
			}
		}

		// If no billing country was posted, check if one was stored in the session
		if(empty($country)) {
			$country = Aelia_SessionManager::get_value(Definitions::SESSION_BILLING_COUNTRY);
		}

		// If no valid currency could be retrieved from customer's details, detect
		// it using visitor's IP address
		if(empty($country)) {
			$country = IP2Location::factory()->get_visitor_country();
		}

		// If everything fails, take WooCommerce customer country or base country
		if(empty($country)) {
			$country = isset($woocommerce->customer) ? $woocommerce->customer->get_country() : $woocommerce->countries->get_base_country();
		}

		$this->billing_country = $country;
		return $country;
	}

	/**
	 * Processes the "woocommerce_tax_display_shop" option, eventually replacing
	 * it with the one configured for visitor's country.
	 *
	 * @param string value The original value.
	 * @return string
	 */
	public function option_woocommerce_tax_display_shop($value) {
		$user_country = $this->get_billing_country();

		$result = $this->settings_controller()->get_tax_display_for_country($user_country, 'shop_prices');
		// Debug
		//var_dump($user_country, 'shop', $result);
		return (empty($result)) ? $value : $result;
	}

	/**
	 * Processes the "woocommerce_tax_display_cart" option, eventually replacing
	 * it with the one configured for visitor's country.
	 *
	 * @param string value The original value.
	 * @return string
	 */
	public function option_woocommerce_tax_display_cart($value) {
		$user_country = $this->get_billing_country();

		$result = $this->settings_controller()->get_tax_display_for_country($user_country, 'cart_prices');
		// Debug
		//var_dump($user_country, 'cart', $result);
		return (empty($result)) ? $value : $result;
	}

	protected function get_tax_suffix($default_value) {
		$user_country = $this->get_billing_country();

		$result = $this->settings_controller()->get_price_suffix_for_country($user_country);
		// Debug
		//var_dump($user_country, $result);
		return (empty($result)) ? $default_value : $result;
	}

	/**
	 * Processes the "woocommerce_price_suffix" option, eventually replacing
	 * it with the one configured for visitor's country.
	 *
	 * @param string value The original value.
	 * @return string
	 */
	public function woocommerce_get_price_suffix($price_suffix, $product) {
		return $this->get_tax_suffix($price_suffix);
	}

	/**
	 * Processes the "excluding tax or vat" suffix, eventually replacing
	 * it with the one configured for visitor's country.
	 *
	 * @param string suffix The original value.
	 * @return string
	 */
	public function woocommerce_countries_ex_tax_or_vat($suffix) {
		return $this->get_tax_suffix($price_suffix);
	}

	/**
	 * Processes the "including tax or vat" suffix, eventually replacing
	 * it with the one configured for visitor's country.
	 *
	 * @param string suffix The original value.
	 * @return string
	 */
	public function woocommerce_countries_inc_tax_or_vat($suffix) {
		return $this->get_tax_suffix($price_suffix);
	}

	/**
	 * Determines if one of plugin's admin pages is being rendered. Override it
	 * if plugin implements pages in the Admin section.
	 *
	 * @return bool
	 */
	protected function rendering_plugin_admin_page() {
		$screen = get_current_screen();
		$page_id = $screen->id;

		return ($page_id == 'woocommerce_page_' . Definitions::MENU_SLUG);
	}

	/**
	 * Registers the script and style files needed by the admin pages of the
	 * plugin. Extend in descendant plugins.
	 */
	protected function register_plugin_admin_scripts() {
		// Scripts
		wp_register_script('jquery-ui',
											 '//code.jquery.com/ui/1.10.3/jquery-ui.js',
											 array('jquery'),
											 null,
											 true);
		wp_register_script('chosen',
											 '//cdnjs.cloudflare.com/ajax/libs/chosen/1.1.0/chosen.jquery.min.js',
											 array('jquery'),
											 null,
											 true);

		// Styles
		wp_register_style('chosen',
												'//cdnjs.cloudflare.com/ajax/libs/chosen/1.1.0/chosen.min.css',
												array(),
												null,
												'all');
		wp_register_style('jquery-ui',
											'//code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css',
											array(),
											null,
											'all');

		wp_enqueue_style('jquery-ui');
		wp_enqueue_style('chosen');

		wp_enqueue_script('jquery-ui');
		wp_enqueue_script('chosen');

		parent::register_plugin_admin_scripts();
	}

	/**
	 * Loads Styles and JavaScript for the frontend. Extend as needed in
	 * descendant classes.
	 */
	public function load_frontend_scripts() {
		// Enqueue the required Frontend stylesheets
		//wp_enqueue_style(static::$plugin_slug . '-frontend');

		// JavaScript
		wp_enqueue_script(static::$plugin_slug . '-frontend');
	}

	/**
	 * Returns the full path and file name of the specified template, if such file
	 * exists.
	 *
	 * @param string template_name The name of the template.
	 * @return string
	 */
	public function get_template_file($template_name) {
		$template = '';

		/* Look for the following:
		 * - yourtheme/woocommerce-aelia-currencyswitcher-{template_name}.php
		 * - yourtheme/woocommerce-aelia-currencyswitcher/{template_name}.php
		 */
		$template = locate_template(array(
			self::$plugin_slug . "-{$template_name}.php",
			self::$plugin_slug . '/' . "{$template_name}.php"
		));

		// If template could not be found, get default one
		if(empty($template)) {
			$default_template_file = $this->path('views') . '/' . "{$template_name}.php";

			if(file_exists($default_template_file)) {
				$template = $default_template_file;
			}
		}

		// If template does not exist, trigger a warning to inform the site administrator
		if(empty($template)) {
			$this->trigger_error(Definitions::INVALID_TEMPLATE,
													 E_USER_WARNING,
													 array(self::$plugin_slug, $template_name));
		}

		return $template;
	}

	/**
	 * Registers all the Widgets used by the plugin.
	 */
	public function register_widgets() {
		$this->register_widget('Aelia\WC\TaxDisplayByCountry\Billing_Country_Selector_Widget');
	}

	/**
	 * Returns the default checkout country.
	 *
	 * @param string checkout_country The country passed by WooCommerce.
	 * @return string
	 */
	public function default_checkout_country($checkout_country) {
		return $this->get_billing_country();
	}

	/**
	 * Intercepts the fetching of user metadata, to alter the billing address if
	 * needed.
	 *
	 * @param mixed value The original value of the user metadata.
	 * @param int user_id The user for whom the data is being retrieved.
	 * @param string meta_key Optional. Metadata key. If not specified, retrieve
	 * all metadata for the specified object.
	 * @param bool $single Optional, default is false. If true, return only the
	 * first value of the specified meta_key. This parameter has no effect if
	 * meta_key is not specified.
	 * @return string
	 */
	public function get_user_metadata($value, $user_id, $meta_key, $single) {
		// If we are on checkout page and the billing country is requested for
		// current user, retrieve the one he (eventually) selected
		if(($meta_key === 'billing_country') &&
			 (defined('WOOCOMMERCE_CHECKOUT') || is_checkout()) &&
			 ($user_id === wp_get_current_user()->ID)) {
			return $this->get_billing_country();
		}

		return $value;
	}

	/**
	 * Sets hooks to register shortcodes.
	 */
	protected function set_shortcodes_hooks() {
		// Shortcode to render the billing country selector
		add_shortcode('aelia_tdbc_billing_country_selector_widget',
									array('Aelia\WC\TaxDisplayByCountry\Billing_Country_Selector_Widget', 'render_billing_country_selector'));
	}

	/**
	 * Registers a widget class.
	 *
	 * @param string widget_class The class to register.
	 * @param bool stop_on_error Indicates if the function should raise an error
	 * if the Widget Class doesn't exist or cannot be loaded.
	 * @return bool True, if the Widget was registered correctly, False otherwise.
	 */
	protected function register_widget($widget_class, $stop_on_error = true) {
		register_widget($widget_class);

		return true;
	}
}

$GLOBALS[WC_Aelia_Tax_Display_By_Country::$plugin_slug] = WC_Aelia_Tax_Display_By_Country::factory();
