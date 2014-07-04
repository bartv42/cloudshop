<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * Aelia KISSMetrics integration class. Adds logic to replace the order totals
 * with their counterpart in base currency. This is needed because KISSMetrics
 * does not support multiple currencies.
 */
class WC_Aelia_KISSMetrics_Integration {
	public function __construct() {
		$this->set_hooks();
	}

	/**
	 * Set the hooks required by the class.
	 */
	protected function set_hooks() {
		add_filter('wc_kissmetrics_completed_purchase_properties', array($this, 'wc_kissmetrics_completed_purchase_properties'), 50);
	}

	/**
	 * Intercepts the data about to be passed to KISSMetrics, replacing the order
	 * totals with their counterpart in base currency.
	 *
	 * @param array kissmetrics_properties The data to be passed to KISSMetrics.
	 * @return array
	 */
	public function wc_kissmetrics_completed_purchase_properties($kissmetrics_properties) {
		$order_id = get_value('order_id', $kissmetrics_properties);

		if(!empty($order_id)) {
			$order = new Aelia_Order($order_id);
			$kissmetrics_properties['order_total'] = $order->get_total_in_base_currency();
			$kissmetrics_properties['shipping_total'] = $order->get_shipping_in_base_currency();
		}

		return $kissmetrics_properties;
	}
}
