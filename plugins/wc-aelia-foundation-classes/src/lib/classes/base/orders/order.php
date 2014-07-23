<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Aelia Order. Extends standard WC_Order, providing convenience methods to
 * handle multi-currency environments.
 *
 */
class Order extends \WC_Order {
	/**
	 * Get the order if ID is passed, otherwise the order is new and empty.
	 *
	 * @see WC_Order::__construct()
	 */
	public function __construct($id = '') {
		add_filter('woocommerce_load_order_data', array($this, 'woocommerce_load_order_data'));

		parent::__construct($id);
	}

	/**
	 * Alters the order attributes loaded when the class is instantiated, to ensure
	 * that base currency attributes are included.
	 *
	 * @param array load_data The original order attributes loaded by base WC_Order
	 * class.
	 * @return array
	 */
	public function woocommerce_load_order_data($load_data) {
		$extra_data = array(
			'order_total_base_currency' => '',
			'order_discount_base_currency' => '',
			'cart_discount_base_currency' => '',
			'order_shipping_base_currency' => '',
			'order_shipping_tax_base_currency' => '',
			'order_tax_base_currency' => '',
		);

		$result = array_merge($load_data, $extra_data);
		return $result;
	}

	/**
	 * Sets a meta attribute for the order.
	 *
	 * @param string meta_key The key of the meta value to set.
	 * @param mixed value The value to set.
	 */
	public function set_meta($meta_key, $value) {
		update_post_meta($this->id, $meta_key, $value);
	}

	/**
	 * Sets the currency for the order.
	 *
	 * @param string currency The currency to set against the order.
	 * @return string
	 */
	public function set_order_currency($currency) {
		$original_order_currency = $this->get_order_currency();
		$this->set_meta('_order_currency', $currency);

		if(!empty($original_order_currency) && ($currency != $original_order_currency)) {
			// TODO If order had a different currency, recalculate totals in base currency
		}
	}

	/**
	 * Returns the order total, in order currency.
	 *
	 * @since WooCommerce 2.1.
	 */
	public function get_total() {
		// If parent has this method, let's use it. It means we are in WooCommerce 2.1+
		if(method_exists(get_parent_class($this), __FUNCTION__)) {
			return parent::get_total();
		}

		// Fall back to legacy method in WooCommerce 2.0 and earlier
		return $this->get_order_total();
	}

	/**
	 * Returns the shipping total, in order currency.
	 *
	 * @since WooCommerce 2.1.
	 */
	public function get_total_shipping() {
		// If parent has this method, let's use it. It means we are in WooCommerce 2.1+
		if(method_exists(get_parent_class($this), __FUNCTION__)) {
			return parent::get_total_shipping();
		}

		// Fall back to legacy method in WooCommerce 2.0 and earlier
		return $this->get_shipping();
	}

	/**
	 * Returns the currency in which an order was placed.
	 *
	 * @return string
	 */
	public function get_order_currency($default_if_empty = null) {
		if(method_exists(get_parent_class($this), __FUNCTION__)) {
			$order_currency = parent::get_order_currency();
			return (empty($order_currency)) ? $default_if_empty : $order_currency;
		}

		// Extract the currency settings from the order
		$order_custom_fields = get_value('order_custom_fields', $this);
		$order_currency = get_value('_order_currency', $order_custom_fields);
		// Order currency is a post property, stored as a one element array
		// (i.e. 0 => value). When that is not the case, we are dealing with a new
		// order, which was not saved yet, therefore the currently selected currency
		// is used instead.
		$order_currency = is_array($order_currency) ? array_shift($order_currency) : $default_if_empty;

		return $order_currency;
	}

	/**
	 * Gets order total in base currency.
	 *
	 * @return float
	 */
	public function get_total_in_base_currency() {
		return apply_filters('woocommerce_order_amount_total_base_currency',
												 number_format((double)$this->order_total_base_currency, 2, '.', ''),
												 $this);
	}

	/**
	 * Alias for get_total_in_base_currency().
	 *
	 * @return float
	 */
	public function get_order_total_in_base_currency() {
		return $this->get_total_in_base_currency();
	}

	/**
	 * Gets shipping and product tax in base currency.
	 *
	 * @return float
	 */
	public function get_total_tax_in_base_currency() {
		return apply_filters('woocommerce_order_amount_total_tax_base_currency',
												 number_format((double)$this->order_tax_base_currency + (double)$this->order_shipping_tax_base_currency, 2, '.', ''),
												 $this);
	}

	/**
	 * Gets the total (product) discount amount  in base currency..
	 *
	 * @return float
	 */
	public function get_cart_discount_in_base_currency() {
		return apply_filters('woocommerce_order_amount_cart_discount_base_currency',
												 number_format((double)$this->cart_discount_base_currency, 2, '.', ''),
												 $this);
	}

	/**
	 * Gets the total (product) discount amount in base currency.
	 *
	 * @return float
	 */
	public function get_order_discount_in_base_currency() {
		return apply_filters('woocommerce_order_amount_order_discount_base_currency',
												 number_format((double)$this->order_discount_base_currency, 2, '.', ''),
												 $this);
	}

	/**
	 * Gets the total discount amount in base currency.
	 *
	 * @return float
	 */
	public function get_total_discount_in_base_currency() {
		if ($this->order_discount_base_currency || $this->cart_discount_base_currency)
			return apply_filters('woocommerce_order_amount_total_discount_base_currency',
													 number_format((double)$this->order_discount_base_currency + (double)$this->cart_discount_base_currency, 2, '.', ''),
													 $this);
	}

	/**
	 * Gets shipping total in base currency.
	 *
	 * @return float
	 */
	public function get_shipping_in_base_currency() {
		return apply_filters('woocommerce_order_amount_shipping_base_currency',
												 number_format((double)$this->order_shipping_base_currency, 2, '.', ''),
												 $this);
	}

	/**
	 * Gets shipping tax amount in base currency.
	 *
	 * @return float
	 */
	public function get_shipping_tax_in_base_currency() {
		return apply_filters('woocommerce_order_amount_shipping_tax_base_currency',
												 number_format((double)$this->order_shipping_tax_base_currency, 2, '.', ''),
												 $this);
	}

	/**
	 * Retrieves the order containing the item with the specified ID.
	 *
	 * @param int item_id The item ID.
	 * @return Aelia_Order
	 */
	public static function get_by_item_id($item_id) {
		global $wpdb;

		$SQL = "
			SELECT
				OI.order_id
			FROM
				{$wpdb->prefix}woocommerce_order_items OI
			WHERE
				(OI.order_item_id = %d)
		";

		$order_id = $wpdb->get_var($wpdb->prepare($SQL, $item_id));
		$class = get_called_class();
		$order = new $class($order_id);

		return $order;
	}
}
