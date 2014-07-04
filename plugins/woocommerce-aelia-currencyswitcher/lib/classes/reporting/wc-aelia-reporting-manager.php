<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

interface IWC_Aelia_Reporting_Manager  {

}

class WC_Aelia_Reporting_Manager implements IWC_Aelia_Reporting_Manager {
	// @var Aelia\CurrencySwitcher\Reports An instance of the class that will override the reports
	protected $reports;

	// @var array An array of WooCommerce version => Namespace pairs. The namespace will be used to load the appropriate class to override reports
	protected $reports_classes = array(
		'2.0' => 'WC20',
		'2.1' => 'WC21',
	);

	/**
	 * Loads the class that will override the reports.
	 *
	 * @param string class_namespace The namespace from which the class will be
	 * loaded. All classes share the same name, and they are separated in different
	 * namespaces.
	 * @return bool True if the class was loaded correctly, false otherwise.
	 */
	protected function load_reports($class_namespace) {
		if(empty($class_namespace)) {
			return false;
		}

		$reports_class = 'Aelia\\CurrencySwitcher\\' . $class_namespace . '\\Reports';
		if(class_exists($reports_class)) {
			$this->reports = new $reports_class();
			return true;
		}

		return false;
	}

	/**
	 * WC Reports assume Order Totals to be in base currency and simply sum them
	 * together. This is incorrect when Currency Switcher is installed, as each
	 * order total is saved in the currency in which the transaction was completed.
	 * It's therefore necessary, during reporting, to convert all order totals into
	 * the base currency.
	 */
	public function __construct() {
		global $woocommerce;
		krsort($this->reports_classes);

		$class_namespace = null;
		foreach($this->reports_classes as $supported_version => $namespace) {
			if(version_compare($woocommerce->version, $supported_version, '>=')) {
				$class_namespace = $namespace;
				break;
			}
		}

		if(!$this->load_reports($class_namespace)) {
			trigger_error(sprintf(__('Reports could not be found for this WooCommerce version. ' .
															 'Supported version are from %s to: %s.', AELIA_CS_PLUGIN_TEXTDOMAIN),
														min(array_keys($this->reports_classes)),
														max(array_keys($this->reports_classes))),
										E_USER_WARNING);
		}
	}
}
