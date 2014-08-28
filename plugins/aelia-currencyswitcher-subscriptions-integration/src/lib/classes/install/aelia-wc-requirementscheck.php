<?php
if(!defined('ABSPATH')) exit; // Exit if accessed directly

// Do not try to redeclare the class
if(!class_exists('Aelia_WC_RequirementsChecks')) {
	/**
	 * Checks that plugin's requirements are met.
	 */
	class Aelia_WC_RequirementsChecks {
		// @var string The namespace for the messages displayed by the class.
		protected $text_domain = 'wc_aelia';
		// @var string The plugin for which the requirements are being checked. Change it in descendant classes.
		protected $plugin_name = 'WC Template Plugin';

		// @var array An array of PHP extensions required by the plugin
		protected $required_extensions = array(
			//'curl',
		);

		// @var array A list of all the installed plugins.
		protected static $_installed_plugins;

		// @var array An array of WordPress plugins (name => version) required by the plugin.
		protected $required_plugins = array(
			'WooCommerce' => '2.0.10',
			//'Aelia Foundation Classes for WooCommerce' => array(
			//	'version' => '1.0.0.140508',
			//	'extra_info' => 'You can get the plugin <a href="http://dev.pathtoenlightenment.net/downloads/wc-aelia-foundation-classes.zip">from our site</a>, free of charge.',
			//),
		);

		// @var array An array with the details of the required plugins.
		protected $required_plugins_info = array();

		// @var array Holds a list of the errors related to missing requirements
		protected $requirements_errors = array();

		/**
		 * Factory method. It MUST be copied to every descendant class, as it has to
		 * be compatible with PHP 5.2 and earlier, so that the class can be instantiated
		 * in any case and and gracefully tell the user if PHP version is insufficient.
		 *
		 * @return Aelia_WC_RequirementsChecks
		 */
		public static function factory() {
			$instance = new self();
			return $instance;
		}

		/**
		 * Checks that one or more PHP extensions are loaded.
		 *
		 * @return array An array of error messages containing one entry for each
		 * extension that is not loaded.
		 */
		protected function check_required_extensions() {
			foreach($this->required_extensions as $extension) {
				if(!extension_loaded($extension)) {
					$this->requirements_errors[] = sprintf(__('Plugin requires "%s" PHP extension.', $this->text_domain),
																								 $extension);
				}
			}
		}

		/**
		 * Checks that the necessary plugins are installed, and that their version is
		 * the expected one.
		 *
		 * @param bool autoload_plugins Indicates if the required plugins should be
		 * loaded automatically, if requirements checks pass.
		 */
		protected function check_required_plugins($autoload_plugins = true) {
			foreach($this->required_plugins as $plugin_name => $plugin_requirements) {
				$plugin_info = $this->is_plugin_active($plugin_name);

				// If plugin_details is not an array, it's assumed to be a string containing
				// the required plugin version
				if(!is_array($plugin_requirements)) {
					$plugin_requirements = array(
						'version' => $plugin_requirements,
					);
				}

				$error_message = '';
				if(is_array($plugin_info)) {
					if(version_compare($plugin_info['Version'], $plugin_requirements['version'], '<')) {
						$error_message = sprintf(__('Plugin "%s" must be version "%s" or later.', $this->text_domain),
																		 $plugin_name,
																		 $plugin_requirements['version']);
					}
					else {
						// If plugin must be loaded automatically, without waiting for WordPress to load it,
						// add it to the autoload queue
						if(isset($plugin_requirements['autoload']) && ($plugin_requirements['autoload'] == true)) {
							$this->required_plugins_info[$plugin_name] = $plugin_info;
						}
					}
				}
				else {
					$error_message = sprintf(__('Plugin "%s" must be installed and activated.', $this->text_domain),
																	 $plugin_name);
				}

				if(!empty($error_message)) {
					if(isset($plugin_requirements['extra_info'])) {
						$error_message .= ' ' . $plugin_requirements['extra_info'];
					}
					$this->requirements_errors[] = $error_message;
				}
			}
		}

		/**
		 * Checks that plugin requirements are satisfied.
		 *
		 * @return bool
		 */
		public function check_requirements() {
			$this->requirements_errors = array();
			if(PHP_VERSION < '5.3') {
				$this->requirements_errors[] = __('Plugin requires PHP 5.3 or greater.', $this->text_domain);
			}

			$this->check_required_extensions();
			$this->check_required_plugins();

			$result = empty($this->requirements_errors);

			if($result) {
				$this->load_required_plugins();
			}
			else {
				// If requirements are missing, display the appropriate notices
				add_action('admin_notices', array($this, 'plugin_requirements_notices'));
			}

			return $result;
		}

		/**
		 * Checks if WC plugin is active, either for the single site or, in
		 * case of WPMU, for the whole network.
		 *
		 * @return bool
		 */
		public static function is_wc_active() {
			if(defined('WC_ACTIVE')) {
				return WC_ACTIVE;
			}

			// Test if WC is installed and active
			if(self::factory()->is_plugin_active('WooCommerce')) {
				define('WC_ACTIVE', true);
				return true;
			}

			return false;
		}

		/**
		 * Returns a list of the installed plugins.
		 *
		 * @return array
		 */
		protected function installed_plugins() {
			if(empty(self::$_installed_plugins)) {
				self::$_installed_plugins = get_plugins();
			}

			return self::$_installed_plugins;
		}

		/**
		 * Checks if a plugin is active and returns a value to indicate it.
		 *
		 * @param string plugin_key The key of the plugin to check.
		 * @return bool
		 */
		public function is_plugin_active($plugin_name) {
			// Require necessary WP Core files
			if(!function_exists('get_plugins')) {
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			}

			foreach($this->installed_plugins() as $path => $plugin_info){
				if((strcasecmp($plugin_info['Name'], $plugin_name) === 0) && is_plugin_active($path)) {
					$plugin_info['Path'] = $path;
					return $plugin_info;
				}
			}

			return false;
		}

		/**
		 * Display requirements errors that prevented the plugin from being loaded.
		 */
		public function plugin_requirements_notices() {
			if(empty($this->requirements_errors)) {
				return;
			}

			// Inline CSS styles have to be used because plugin is not loaded if
			// requirements are missing, therefore the plugin's CSS files are ignored
			echo '<div class="error fade">';
			echo '<h4 class="wc_aeliamessage_header" style="margin: 1em 0 0 0">';
			echo sprintf(__('Plugin "%s" could not be loaded due to missing requirements.', $this->text_domain),
									 $this->plugin_name);
			echo '</h4>';
			echo '<div class="info">';
			echo __('<b>Note</b>: even though the plugin might be showing as "<b><i>active</i></b>", it will not load ' .
							'and its features will not be available until its requirements are met. If you need assistance, ' .
							'on this matter, please <a href="https://aelia.freshdesk.com/helpdesk/tickets/new">contact our ' .
							'Support team</a>.',
							$this->text_domain);
			echo '</div>';
			echo '<ul style="list-style: disc inside">';
			echo '<li>';
			echo implode('</li><li>', $this->requirements_errors);
			echo '</li>';
			echo '</ul>';
			echo '</div>';
		}

		/**
		 * Loads the required plugins.
		 */
		public function load_required_plugins() {
			foreach($this->required_plugins_info as $plugin_name => $plugin_info) {
				// Debug
				//var_dump(ABSPATH . 'wp-content/plugins/' . $plugin_info['Path']);
				require_once(ABSPATH . 'wp-content/plugins/' . $plugin_info['Path']);
			}
		}
	}
}
