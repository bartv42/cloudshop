<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Implements support for Cart Notices plugin.
 */
class WC_Aelia_CS_Cart_Notices_Integration {
	/**
	 * Returns the instance of the Currency Switcher plugin.
	 *
	 * @return WC_Aelia_CurrencySwitcher
	 */
	protected function currency_switcher() {
		return WC_Aelia_CurrencySwitcher::instance();
	}

	/**
	 * Returns the instance of the settings controller loaded by the plugin.
	 *
	 * @return WC_Aelia_CurrencySwitcher_Settings
	 */
	protected function settings_controller() {
		return WC_Aelia_CurrencySwitcher::settings();
	}

	public function __construct() {
		$this->set_hooks();
	}

	/**
	 * Set the hooks required by the class.
	 */
	protected function set_hooks() {
		// Compatibility with Cart Notices plugin
		add_filter('wc_cart_notices_order_thresholds', array($this, 'wc_cart_notices_order_thresholds'), 20);
		add_action('wc_cart_notices_process_notice_before', array($this, 'wc_cart_notices_process_notice_before'), 20);
	}

	/**
	 * Converts the thresholds used by the Cart Notices plugin into the selected
	 * currency.
	 *
	 * @param array order_thresholds An array containing the thresholds.
	 * @return array
	 */
	public function wc_cart_notices_order_thresholds($order_thresholds) {
		$minimum_order_amount = get_value('minimum_order_amount', $order_thresholds, 0);
		$threshold_order_amount = get_value('threshold_order_amount', $order_thresholds, 0);

		$order_thresholds['minimum_order_amount'] = $this->currency_switcher()
																									->convert($minimum_order_amount,
																														$this->settings_controller()->base_currency(),
																														$this->currency_switcher()->get_selected_currency());
		$order_thresholds['threshold_order_amount'] = $this->currency_switcher()
																									->convert($threshold_order_amount,
																														$this->settings_controller()->base_currency(),
																														$this->currency_switcher()->get_selected_currency());

		return $order_thresholds;
	}

	/**
	 * Recalculate cart totals, if needed, before the minimum amount Cart Notice
	 * is processed.
	 *
	 * @param object notice The Cart Notice to be processed.
	 */
	public function wc_cart_notices_process_notice_before($notice) {
		if(get_value('type', $notice) == 'minimum_amount') {
			$this->currency_switcher()->recalculate_cart_totals();
		}
	}
}
