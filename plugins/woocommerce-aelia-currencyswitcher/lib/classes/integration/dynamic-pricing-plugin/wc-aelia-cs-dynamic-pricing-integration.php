<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

use Aelia\CurrencySwitcher\Logger as Logger;

/**
 * Implements support for Dynamic Pricing plugin.
 */
class WC_Aelia_CS_Dynamic_Pricing_Integration {
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

	/**
	 * Converts an amount from base currency to the currently selected currency.
	 *
	 * @param float amount The amount to convert.
	 * @param string from_currency The source currency. If empty, the base currency
	 * is taken.
	 * @param string to_currency The destination currency. If empty, the currently
	 * selected currency is taken.
	 * @return float The amount converted in the destination currency.
	 */
	protected function convert($amount, $from_currency = null, $to_currency = null) {
		if(empty($from_currency)) {
			$from_currency = $this->settings_controller()->base_currency();
		}

		if(empty($to_currency)) {
			$to_currency = $this->currency_switcher()->get_selected_currency();
		}

		return $this->currency_switcher()->convert($amount,
																							 $from_currency,
																							 $to_currency);
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->set_hooks();
	}

	/**
	 * Set the hooks required by the class.
	 */
	protected function set_hooks() {
		add_filter('woocommerce_dynamic_pricing_get_rule_amount', array($this, 'woocommerce_dynamic_pricing_get_rule_amount'), 20, 4);
		add_filter('wc_dynamic_pricing_load_modules', array($this, 'wc_dynamic_pricing_load_modules'), 20);
	}

	/**
	 * Handler for woocommerce_dynamic_pricing_get_rule_amount filter.
	 * When an absolute discount (e.g. $20) is added by Dynamic Pricing plugin,
	 * this method converts it into the currently selected currency.
	 *
	 * @param float amount The discount.
	 * @param array rule An array describing the rule being applied.
	 * @param cart_item The cart item on which the rule is being applied.
	 * @param WC_Dynamic_Pricing_Advanced_Product dynamic_pricing_product The
	 * product instance created by Dynamic Pricing plugin.
	 * @return float
	 */
	public function woocommerce_dynamic_pricing_get_rule_amount($amount, $rule, $cart_item, $dynamic_pricing_product) {
		$rule_type = get_value('type', $rule);

		if(in_array($rule_type, array('price_discount', 'fixed_price'))) {
			$amount = $this->convert($amount);
		}
		return $amount;
	}

	/**
	 * Intercept dynamic pricing modules and ensure that their fixed price values
	 * are properly converted into currency.
	 *
	 * @param array modules An array for dynamic pricing modules.
	 * @return array
	 */
	public function wc_dynamic_pricing_load_modules($modules) {
		// Debug
		//var_dump($modules);
		foreach($modules as $module_type => $module) {
			// Determine the method that will be used to process the module rules
			$module_process_method = 'process_' . $module_type . '_rules';

			if(method_exists($this, $module_process_method)) {
				$module = $this->$module_process_method($module);

				// Return the processed module
				$modules[$module_type] = $module;
			}
			else {
				Logger::log(sprintf(__('Attempted to process an unsupported Dynamic Pricing module. ' .
															 'Module type: "%s".'),
														$module_type));
			}
		}

		return $modules;
	}

	/**
	 * Processes the rules for a WC_Dynamic_Pricing_Simple_Category module,
	 * converting absolute discounts into the currently selected currency before
	 * they are applied.
	 *
	 * @param WC_Dynamic_Pricing_Simple_Category module The moduel to be processed.
	 * @return WC_Dynamic_Pricing_Simple_Category
	 */
	protected function process_simple_category_rules($module) {
		if(is_array($module->available_rulesets)) {
			foreach($module->available_rulesets as $set_id => $rule_set) {
				// Take a copy of the rules before processing them. This will allow to
				// process them again, if needed, starting from their initial state
				if(!isset($rule_set['aelia_cs_original_pricing_rules'])) {
					$pricing_rules = get_value('rules', $rule_set);
					$rule_set->aelia_cs_original_pricing_rules = $pricing_rules;
				}
				else {
					$pricing_rules = get_value('aelia_cs_original_pricing_rules', $rule_set);
				}

				if(!is_array($pricing_rules)) {
					Logger::log(sprintf(__('Category Pricing rule set "%s" - "rules" attribute is not an array. Skipping.'),
															$set_id), true);
					continue;
				}

				foreach($pricing_rules as $rule_idx => $rule_settings) {
					// If rule involves a fixed price, then such price must be converted into
					// the selected currency
					if(in_array($rule_settings['type'], array('fixed_product', 'fixed_price'))) {
						$rule_settings['amount'] = $this->convert($rule_settings['amount']);
					}
					$rule_set['rules'][$rule_idx] = $rule_settings;
				}

				// Replace the rule set with the one containing converted prices
				$module->available_rulesets[$set_id] = $rule_set;
			}
		}

		return $module;
	}

	/**
	 * Processes the rules for a WC_Dynamic_Pricing_Simple_Category module,
	 * converting absolute discounts into the currently selected currency before
	 * they are applied.
	 *
	 * @param WC_Dynamic_Pricing_Simple_Category module The moduel to be processed.
	 * @return WC_Dynamic_Pricing_Simple_Category
	 */
	protected function process_advanced_category_rules($module) {
		if(is_array($module->adjustment_sets)) {
			foreach($module->adjustment_sets  as $set_id => $adjustment_set) {
				// Take a copy of the rules before processing them. This will allow to
				// process them again, if needed, starting from their initial state
				if(empty($adjustment_set->aelia_cs_original_pricing_rules)) {
					$pricing_rules = get_value('pricing_rules', $adjustment_set);
					$adjustment_set->aelia_cs_original_pricing_rules = $pricing_rules;
				}
				else {
					$pricing_rules = get_value('aelia_cs_original_pricing_rules', $adjustment_set);
				}

				if(!is_array($pricing_rules)) {
					Logger::log(sprintf(__('Pricing Adjustment set "%s" - "pricing rules" attribute is not an array. Skipping.'),
															$set_id), true);
					continue;
				}

				foreach($pricing_rules as $rule_idx => $rule_settings) {
					// If rule involves a fixed price, then such price must be converted into
					// the selected currency
					if(in_array($rule_settings['type'], array('fixed_adjustment', 'fixed_price'))) {
						$rule_settings['amount'] = $this->convert($rule_settings['amount']);
					}

					$adjustment_set->pricing_rules[$rule_idx] = $rule_settings;
				}

				// Replace the rule set with the one containing converted prices
				$module->adjustment_sets[$set_id] = $adjustment_set;
			}
		}

		return $module;
	}

	/**
	 * Processes the rules for a WC_Dynamic_Pricing_Advanced_Totals module,
	 * converting absolute discounts into the currently selected currency before
	 * they are applied.
	 *
	 * @param WC_Dynamic_Pricing_Advanced_Totals module The moduel to be processed.
	 * @return WC_Dynamic_Pricing_Advanced_Totals
	 */
	protected function process_advanced_totals_rules($module) {
		if(is_array($module->adjustment_sets )) {
			foreach($module->adjustment_sets  as $set_id => $adjustment_set) {
				// Take a copy of the rules before processing them. This will allow to
				// process them again, if needed, starting from their initial state
				if(empty($adjustment_set->aelia_cs_original_pricing_rules)) {
					$pricing_rules = get_value('pricing_rules', $adjustment_set);
					$adjustment_set->aelia_cs_original_pricing_rules = $pricing_rules;
				}
				else {
					$pricing_rules = get_value('aelia_cs_original_pricing_rules', $adjustment_set);
				}

				if(!is_array($pricing_rules)) {
					Logger::log(sprintf(__('Pricing Adjustment set "%s" - "pricing rules" attribute is not an array. Skipping.'),
															$set_id), true);
					continue;
				}

				foreach($pricing_rules as $rule_idx => $rule_settings) {
					$order_total_from = get_value('from', $rule_settings);
					$rule_settings['from'] = is_numeric($order_total_from) ? $this->convert($order_total_from) : $rule_settings['from'];

					$order_total_to = get_value('to', $rule_settings);
					$rule_settings['to'] = is_numeric($order_total_to) ? $this->convert($order_total_to) : $rule_settings['to'];

					$adjustment_set->pricing_rules[$rule_idx] = $rule_settings;
				}

				// Replace the rule set with the one containing converted prices
				$module->adjustment_sets[$set_id] = $adjustment_set;
			}
		}

		return $module;
	}
}
