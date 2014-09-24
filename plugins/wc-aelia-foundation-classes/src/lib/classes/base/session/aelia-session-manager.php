<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit ifaccessed directly

if(!class_exists('Aelia\WC\Aelia_SessionManager')) {
	/**
	 * A simple Session handler. Compatible with both WooCommerce 2.0 and earlier.
	 */
	class Aelia_SessionManager {
		/**
		 * Returns the instance of WooCommerce session.
		 *
		 * @return WC_Session
		 */
		protected static function session() {
			return self::wc()->session;
		}

		/**
		 * Returns global instance of WooCommerce.
		 *
		 * @return object The global instance of WC.
		 */
		protected static function wc() {
			global $woocommerce;
			return $woocommerce;
		}

		/**
		 * Safely store data into the session. Compatible with WooCommerce 2.0+ and
		 * backwards compatible with previous versions.
		 *
		 * @param string key The Key of the value to retrieve.
		 * @param mixed value The value to set.
		 */
		public static function set_value($key, $value) {
			$woocommerce = self::wc();

			// WooCommerce 2.1
			if(version_compare($woocommerce->version, '2.1', '>=')) {
				if(isset($woocommerce->session)) {
					$woocommerce->session->set($key, $value);
				}
				return;
			}

			// WooCommerce 2.0
			if(version_compare($woocommerce->version, '2.0', '>=')) {
				if(isset($woocommerce->session)) {
					$woocommerce->session->$key = $value;
				}
				return;
			}

			// WooCommerce < 2.0
			$_SESSION[$key] = $value;
		}

		/**
		 * Safely retrieve data from the session. Compatible with WooCommerce 2.0+ and
		 * backwards compatible with previous versions.
		 *
		 * @param string key The Key of the value to retrieve.
		 * @param mixed default The default value to return if the key is not found.
		 * @param bool remove_after_get Indicates if the value should be removed after
		 * having been retrieved.
		 * @return mixed The value associated with the key, or the default.
		 */
		public static function get_value($key, $default = null, $remove_after_get = false) {
			$woocommerce = self::wc();
			$result = null;

			// WooCommerce 2.1
			if(is_null($result) && version_compare($woocommerce->version, '2.1', '>=')) {
				if(!isset($woocommerce->session)) {
					return $default;
				}
				$result = @$woocommerce->session->get($key);
			}

			// WooCommerce 2.0
			if(is_null($result) && version_compare($woocommerce->version, '2.0', '>=')) {
				if(!isset($woocommerce->session)) {
					return $default;
				}
				$result = @$woocommerce->session->$key;
			}

			if(is_null($result) && version_compare($woocommerce->version, '1.6', '<=')) {
				// WooCommerce < 2.0
				$result = @$_SESSION[$key];
			}

			if($remove_after_get) {
				self::delete_value($key);
			}

			return empty($result) ? $default : $result;
		}

		/**
		 * Safely remove data from the session. Compatible with WooCommerce 2.0+ and
		 * backwards compatible with previous versions.
		 *
		 * @param string key The Key of the value to retrieve.
		 */
		public static function delete_value($key) {
			$woocommerce = self::wc();

			// WooCommerce 2.1
			if(version_compare($woocommerce->version, '2.1', '>=')) {
				if(isset($woocommerce->session)) {
					$woocommerce->session->set($key, null);
				}
				return;
			}

			// WooCommerce 2.0
			if(version_compare($woocommerce->version, '2.0', '>=')) {
				if(isset($woocommerce->session)) {
					unset($woocommerce->session->$key);
				}
				return;
			}

			// WooCommerce < 2.0
			if(version_compare($woocommerce->version, '1.6', '<=')) {
				unset($_SESSION[$key]);
				return;
			}
		}
	}
}
