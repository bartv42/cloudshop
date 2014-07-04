<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

// Since WooCommerce 2.1, class WC_Google_Analytics is no longer part of WC core
// and it may not exist in the environment
if(!class_exists('WC_Google_Analytics')) {
	return;
}

/**
 * Aelia Google Analytics integration class. Extends standard WC_Google_Analytics
 * class, to ensure that order currency is tracked as well.
 *
 */
class WC_Aelia_Google_Analytics_Integration extends WC_Google_Analytics {
	public function __construct() {
		parent::__construct();

		$this->method_title .= '&nbsp;(' . __('modified by Currency Switcher', AELIA_CS_PLUGIN_TEXTDOMAIN) . ')';
		$this->method_description .= '<br />';
		$this->method_description .= __('<strong>Note</strong>: this integration method has been extended by the ' .
																		'Currency Switcher plugin to ensure that order currency ' .
																		'is tracked correctly.', AELIA_CS_PLUGIN_TEXTDOMAIN);

		$this->set_hooks();
	}

	/**
	 * Set the hooks required by the class.
	 */
	protected function set_hooks() {
	}

	/**
	 * Add the integration to WooCommerce.
	 *
	 * @param array $integrations
	 * @return array
	 */
	public static function add_google_analytics_integration($integrations) {
		$ga_integration_idx = array_search('WC_Google_Analytics', $integrations);
		if($ga_integration_idx !== false) {
			unset($integrations[$ga_integration_idx]);
			$integrations[] = get_called_class();
		}

		return $integrations;
	}

	/**
	 * Google Analytics eCommerce tracking. This method replicates the logic of
	 * WC_Google_Analytics::ecommerce_tracking_code(), with the addition of tracking
	 * order currency as well.
	 *
	 * @param mixed $order_id
	 * @see WC_Google_Analytics::ecommerce_tracking_code()
	 */
	public function ecommerce_tracking_code($order_id) {
		global $woocommerce;

		$tracking_id = $this->ga_id;
		if(!$tracking_id) {
			return;
		}

		if($this->ga_ecommerce_tracking_enabled == "no" || current_user_can('manage_options') || get_post_meta($order_id, '_ga_tracked', true) == 1) {
			return;
		}

		// Doing eCommerce tracking so unhook standard tracking from the footer
		remove_action('wp_footer', array($this, 'google_tracking_code'));

		// Get the order and output tracking code. Use Aelia_Order class, which allows
		// to retrieve the order currency
		$order = new Aelia_Order($order_id);

		$loggedin = is_user_logged_in() ? 'yes' : 'no';

		if(is_user_logged_in()) {
			$user_id = get_current_user_id();
			$current_user = get_user_by('id', $user_id);
			$username = $current_user->user_login;
		}
		else {
			$user_id = '';
			$username = __('Guest', 'woocommerce');
		}

		if(! empty($this->ga_set_domain_name)) {
			$set_domain_name = "['_setDomainName', '" . esc_js($this->ga_set_domain_name) . "'],";
		}
		else {
			$set_domain_name = '';
		}

		$code = "
			var _gaq = _gaq || [];

			_gaq.push(
				['_setAccount', '" . esc_js($tracking_id) . "'], " . $set_domain_name . "
				['_setCustomVar', 1, 'logged-in', '" . esc_js($loggedin) . "', 1],
				['_trackPageview']
			);

			_gaq.push(['_addTrans',
				'" . esc_js($order->get_order_number()) . "', // order ID - required
				'" . esc_js(get_bloginfo('name')) . "', // affiliation or store name
				'" . esc_js($order->get_total()) . "', // total - required
				'" . esc_js($order->get_total_tax()) . "', // tax
				'" . esc_js($order->get_shipping()) . "', // shipping
				'" . esc_js($order->billing_city) . "', // city
				'" . esc_js($order->billing_state) . "', // state or province
				'" . esc_js($order->billing_country) . "' // country
			]);
		";

		// Order items
		if($order->get_items()) {
			foreach ($order->get_items() as $item) {
				$_product = $order->get_product_from_item($item);

				$code .= "_gaq.push(['_addItem',";
				$code .= "'" . esc_js($order->get_order_number()) . "',";
				$code .= "'" . esc_js($_product->get_sku() ? __('SKU:', 'woocommerce') . ' ' . $_product->get_sku() : $_product->id) . "',";
				$code .= "'" . esc_js($item['name']) . "',";

				if(isset($_product->variation_data)) {
					$code .= "'" . esc_js(woocommerce_get_formatted_variation($_product->variation_data, true)) . "',";
				}
				else {
					$out = array();
					$categories = get_the_terms($_product->id, 'product_cat');
					if($categories) {
						foreach ($categories as $category){
							$out[] = $category->name;
						}
					}
					$code .= "'" . esc_js(join("/", $out)) . "',";
				}

				$code .= "'" . esc_js($order->get_item_total($item, true, true)) . "',";
				$code .= "'" . esc_js($item['qty']) . "'";
				$code .= "]);";
			}
		}

		// Track order currency
		$order_currency = $order->get_order_currency();
		$code .= "
			_gaq.push(['_set', 'currencyCode', '" . esc_js($order_currency) . "']);
		";

		$code .= "
			_gaq.push(['_trackTrans']); // submits transaction to the Analytics servers

			(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
			})();
		";

		echo '<script type="text/javascript">' . $code . '</script>';

		update_post_meta($order_id, '_ga_tracked', 1);
	}
}
