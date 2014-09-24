<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \ReflectionClass;
use \Exception;

if(!class_exists('Aelia\WC\Aelia_Plugin')) {
	interface IAelia_Plugin {
		public function settings_controller();
		public function messages_controller();
		public static function instance();
		public static function settings();
		public function setup();
		public static function cleanup();
	}

	// Load general functions file
	require_once('general_functions.php');

	/**
	 * Implements a base plugin class to be used to implement WooCommerce plugins.
	 */
	class Aelia_Plugin implements IAelia_Plugin {
		// @var string The plugin version.
		public static $version = '0.1.0';

		// @var string The plugin slug
		public static $plugin_slug = 'wc-aelia-plugin';
		// @var string The plugin text domain
		public static $text_domain = 'wc-aelia-plugin';
		// @var string The plugin name
		public static $plugin_name = 'wc-aelia-plugin';

		// @var string The base name of the plugin directory
		protected $plugin_directory;

		// @var Aelia\WC\Settings The object that will handle plugin's settings.
		protected $_settings_controller;
		// @var Aelia\WC\Messages The object that will handle plugin's messages.
		protected $_messages_controller;
		// @var Aelia_SessionManager The session manager
		protected $_session;

		protected $paths = array(
			// This array will contain the paths used by the plugin
		);

		protected $urls = array(
			// This array will contain the URLs used by the plugin
		);

		/**
		 * Returns the URL to use to check for plugin updates.
		 *
		 * @param string plugin_slug The plugin slug.
		 * @return string
		 */
		protected function get_update_url($plugin_slug) {
			return 'http://wpupdate.aelia.co?action=get_metadata&slug=' . $plugin_slug;
		}

		/**
		 * Checks for plugin updates.
		 */
		public function check_for_updates($plugin_file, $plugin_slug = null) {
			if(empty($plugin_slug)) {
				$plugin_slug = static::$plugin_slug;
			}

			// Debug
			//var_dump($this->path('vendor') . '/yahnis-elsts/plugin-update-checker/plugin-update-checker.php');die();

			require_once($this->path('vendor') . '/yahnis-elsts/plugin-update-checker/plugin-update-checker.php');
			$MyUpdateChecker = \PucFactory::buildUpdateChecker(
					$this->get_update_url($plugin_slug),
					$plugin_file,
					$plugin_slug
			);
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
		 * Returns the session manager.
		 *
		 * @return Aelia_SessionManager The session manager instance.
		 */
		protected function session() {
			if(empty($this->_session)) {
				$this->_session = new Aelia_SessionManager();
			}
			return $this->_session;
		}

		/**
		 * Returns the instance of the Settings Controller used by the plugin.
		 *
		 * @return Aelia_Settings.
		 */
		public function settings_controller() {
			return $this->_settings_controller;
		}

		/**
		 * Returns the instance of the Messages Controller used by the plugin.
		 *
		 * @return Aelia_Messages.
		 */
		public function messages_controller() {
			return $this->_messages_controller;
		}

		/**
		 * Returns the instance of the plugin.
		 *
		 * @return Aelia_Plugin.
		 */
		public static function instance() {
			return $GLOBALS[static::$plugin_slug];
		}

		/**
		 * Returns the plugin path.
		 *
		 * @return string
		 */
		public static function plugin_path() {
			$reflection_class = new ReflectionClass(get_called_class());

			return dirname($reflection_class->getFileName());
		}

		/**
		 * Returns the Settings Controller used by the plugin.
		 *
		 * @return Aelia\WC\Settings.
		 */
		public static function settings() {
			return self::instance()->settings_controller();
		}

		/**
		 * Returns the Messages Controller used by the plugin.
		 *
		 * @return Aelia\WC\Messages.
		 */
		public static function messages() {
			return self::instance()->messages_controller();
		}

		/**
		 * Retrieves an error message from the internal Messages object.
		 *
		 * @param mixed error_code The Error Code.
		 * @return string The Error Message corresponding to the specified Code.
		 */
		public function get_error_message($error_code) {
			return $this->_messages_controller->get_error_message($error_code);
		}

		/**
		 * Triggers an error displaying the message associated to an error code.
		 *
		 * @param mixed error_code The Error Code.
		 * @param int error_type The type of Error to raise.
		 * @param array error_args An array of arguments to pass to the vsprintf()
		 * function which will format the error message.
		 * @param bool show_backtrace Indicates if a backtrace should be displayed
		 * after the error message.
		 * @return string The formatted error message.
		 */
		public function trigger_error($error_code, $error_type = E_USER_NOTICE, array $error_args = array(), $show_backtrace = false) {
			$error_message = $this->get_error_message($error_code);

			$message = vsprintf($error_message, $error_args);
			if($show_backtrace) {
				$e = new Exception();
				$backtrace = $e->getTraceAsString();
				$message .= " \n" . $backtrace;
			}

			return trigger_error($message, $error_type);
		}

			/**
		 * Sets the hook handlers for WC and WordPress.
		 */
		protected function set_hooks() {
			add_action('init', array($this, 'wordpress_loaded'));
			// Called after all plugins have loaded
			add_action('plugins_loaded', array($this, 'plugins_loaded'));
			add_action('woocommerce_init', array($this, 'woocommerce_loaded'), 1);

			add_action('admin_enqueue_scripts', array($this, 'load_admin_scripts'));
			add_action('wp_enqueue_scripts', array($this, 'load_frontend_scripts'));

			// Register Widgets
			add_action('widgets_init', array($this, 'register_widgets'));
		}

		/**
		 * Returns the full path corresponding to the specified key.
		 *
		 * @param key The path key.
		 * @return string
		 */
		public function path($key) {
			return get_value($key, $this->paths, '');
		}

		/**
		 * Builds and stores the paths used by the plugin.
		 */
		protected function set_paths() {
			$this->paths['plugin'] = WP_PLUGIN_DIR . '/' . $this->plugin_dir()  . '/src';
			$this->paths['languages'] = WP_PLUGIN_DIR . '/' . $this->plugin_dir()  . '/languages';
			$this->paths['lib'] = $this->path('plugin') . '/lib';
			$this->paths['views'] = $this->path('plugin') . '/views';
			$this->paths['admin_views'] = $this->path('views') . '/admin';
			$this->paths['classes'] = $this->path('lib') . '/classes';
			$this->paths['widgets'] = $this->path('classes') . '/widgets';
			$this->paths['vendor'] = $this->path('plugin') . '/vendor';

			$this->paths['design'] = $this->path('plugin') . '/design';
			$this->paths['css'] = $this->path('design') . '/css';
			$this->paths['images'] = $this->path('design') . '/images';

			$this->paths['js'] = $this->path('plugin') . '/js';
			$this->paths['js_admin'] = $this->path('js') . '/admin';
			$this->paths['js_frontend'] = $this->path('js') . '/frontend';
		}

		/**
		 * Builds and stores the URLs used by the plugin.
		 */
		protected function set_urls() {
			$this->urls['plugin'] = plugins_url() . '/' . $this->plugin_dir() . '/src';

			$this->urls['design'] = $this->url('plugin') . '/design';
			$this->urls['css'] = $this->url('design') . '/css';
			$this->urls['images'] = $this->url('design') . '/images';
			$this->urls['js'] = $this->url('plugin') . '/js';
			$this->urls['js_admin'] = $this->url('js') . '/admin';
			$this->urls['js_frontend'] = $this->url('js') . '/frontend';
		}

		/**
		 * Returns the URL corresponding to the specified key.
		 *
		 * @param key The URL key.
		 * @return string
		 */
		public function url($key) {
			return get_value($key, $this->urls, '');
		}

		/**
		 * Returns the directory in which the plugin is stored. Only the base name of
		 * the directory is returned (i.e. without path).
		 *
		 * @return string
		 */
		public function plugin_dir() {
			if(empty($this->plugin_directory)) {
				$reflector = new ReflectionClass($this);
				$this->plugin_directory = basename(dirname(dirname($reflector->getFileName())));
			}

			return $this->plugin_directory;
		}

		/**
		 * Constructor.
		 *
		 * @param Aelia\WC\Settings settings_controller The controller that will handle
		 * the plugin settings.
		 * @param Aelia\WC\Messages messages_controller The controller that will handle
		 * the messages produced by the plugin.
		 */
		public function __construct($settings_controller = null, $messages_controller = null) {
			// Set plugin's paths
			$this->set_paths();
			// Set plugin's URLs
			$this->set_urls();

			$this->_settings_controller = $settings_controller;
			$this->_messages_controller = empty($messages_controller) ? new Messages : $messages_controller;

			// Uncomment line below to debug the activation hook when using symlinks
			// TODO Review activation and uninstallation hook.
			// Currently, hooks will nto be triggered, as this file will never be "activated"
			// by WordPress. The activated file is actually the wrapper that calls this file.
			register_activation_hook(__FILE__, array($this, 'setup'));
			register_uninstall_hook(__FILE__, array(get_class($this), 'cleanup'));

			// Set all required hooks
			$this->set_hooks();

			// indicates we are running the admin
			if(is_admin()) {
				// ...
			}

			// indicates we are being served over ssl
			if(is_ssl()) {
				// ...
			}
		}

		/**
		 * Run the updates required by the plugin. This method runs at every load, but
		 * the updates are executed only once. This allows the plugin to run the
		 * updates automatically, without requiring deactivation and rectivation.
		 *
		 * @return bool
		 */
		protected function run_updates() {
			$installer_class = get_class($this) . '_Install';
			if(!class_exists($installer_class)) {
				return;
			}

			$installer = new $installer_class();
			return $installer->update(static::$plugin_slug, static::$version);
		}

		/**
		 * Returns an instance of the class. This method should be implemented by
		 * descendant classes to return a pre-configured instance of the plugin class,
		 * complete with the appropriate settings controller.
		 *
		 * @return Aelia\WC\Aelia_Plugin
		 * @throws Aelia\WC\NotImplementedException
		 */
		public static function factory() {
			throw new NotImplementedException();
		}

		/**
		 * Take care of anything that needs to be done as soon as WordPress finished
		 * loading.
		 */
		public function wordpress_loaded() {
			$this->register_common_frontend_scripts();
		}

		/**
		 * Performs operation when all plugins have been loaded.
		 */
		public function plugins_loaded() {
			load_plugin_textdomain(static::$text_domain, false, $this->path('languages') . '/');

			// Run updates only when in Admin area. This should occur automatically when
			// plugin is activated, since it's done in the Admin area
			if(is_admin()) {
				$this->run_updates();
			}
		}

		/**
		 * Performs operation when woocommerce has been loaded.
		 */
		public function woocommerce_loaded() {
			// To be implemented by descendant classes
		}

		/**
		 * Registers all the Widgets used by the plugin.
		 */
		public function register_widgets() {
			// Register the required widgets
			//$this->register_widget('Aelia\WC\Template_Widget');
		}

		/**
		 * Determines if one of plugin's admin pages is being rendered. Override it
		 * if plugin implements pages in the Admin section.
		 *
		 * @return bool
		 */
		protected function rendering_plugin_admin_page() {
			return false;
		}

		/**
		 * Registers the script and style files required in the backend (even outside
		 * of plugin's pages). Extend in descendant plugins.
		 */
		protected function register_common_admin_scripts() {
			// Dummy
		}

		/**
		 * Registers the script and style files needed by the admin pages of the
		 * plugin. Extend in descendant plugins.
		 */
		protected function register_plugin_admin_scripts() {
			// Admin scripts
			wp_register_script(static::$plugin_slug . '-admin',
												 $this->url('plugin') . '/js/admin/admin.js',
												 array('jquery'),
												 null,
												 false);
			// Admin styles
			wp_register_style(static::$plugin_slug . '-admin',
												$this->url('plugin') . '/design/css/admin.css',
												array(),
												null,
												'all');
		}

		/**
		 * Registers the script and style files required in the frontend (even outside
		 * of plugin's pages).
		 */
		protected function register_common_frontend_scripts() {
			// Scripts
			wp_register_script(static::$plugin_slug . '-frontend',
												 $this->url('plugin') . '/js/frontend/frontend.js',
												 array('jquery'),
												 null,
												 true);
			// Styles
			wp_register_style(static::$plugin_slug . '-frontend',
												$this->url('plugin') . '/design/css/frontend.css',
												array(),
												null,
												'all');
		}

		/**
		 * Loads Styles and JavaScript for the Admin pages.
		 */
		public function load_admin_scripts() {
			// Register common JS for the backend
			$this->register_common_admin_scripts();
			if($this->rendering_plugin_admin_page()) {
				// Load Admin scripts only on plugin settings page
				$this->register_plugin_admin_scripts();

				// Styles - Enqueue styles required for plugin Admin page
				wp_enqueue_style(static::$plugin_slug . '-admin');

				// JavaScript - Enqueue scripts required for plugin Admin page
				// Enqueue the required Admin scripts
				wp_enqueue_script(static::$plugin_slug . '-admin');
			}
		}


		/**
		 * Loads Styles and JavaScript for the frontend. Extend as needed in
		 * descendant classes.
		 */
		public function load_frontend_scripts() {
			// Enqueue the required Frontend stylesheets
			//wp_enqueue_style(static::$plugin_slug . '-frontend');

			// JavaScript
			//wp_enqueue_script(static::$plugin_slug . '-frontend');
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
			 * - yourtheme/{plugin_slug}-{template_name}.php
			 * - yourtheme/{plugin_slug}/{template_name}.php
			 */
			$template = locate_template(array(
				static::$plugin_slug . "-{$template_name}.php",
				static::$plugin_slug . '/' . "{$template_name}.php"
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
				$this->trigger_error(Messages::ERR_INVALID_TEMPLATE,
														 E_USER_WARNING,
														 array(static::$plugin_slug, $template_name));
			}

			return $template;
		}

		/**
		 * Setup function. Called when plugin is enabled.
		 */
		public function setup() {
		}

		/**
		 * Cleanup function. Called when plugin is uninstalled.
		 */
		public static function cleanup() {
			if(!defined('WP_UNINSTALL_PLUGIN')) {
				return;
			}
		}

		/**
		 * Registers a widget class.
		 *
		 * @param string widget_class The widget class to register.
		 * @param bool stop_on_error Indicates if the function should raise an error
		 * if the Widget Class doesn't exist or cannot be loaded.
		 * @return bool True, if the Widget was registered correctly, False otherwise.
		 */
		protected function register_widget($widget_class, $stop_on_error = true) {
			if(!class_exists($widget_class)) {
				if($stop_on_error === true) {
					$this->trigger_error(Aelia\WC\Messages::ERR_INVALID_WIDGET_CLASS,
															 E_USER_ERROR, array($widget_class));
				}
				return false;
			}
			register_widget($widget_class);

			return true;
		}

		/**
		 * Indicates if we are processing an Ajax call.
		 *
		 * @return bool
		 */
		public static function doing_ajax() {
			return defined('DOING_AJAX') && DOING_AJAX;
		}
	}
}
