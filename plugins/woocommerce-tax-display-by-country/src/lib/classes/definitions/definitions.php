<?php
namespace Aelia\WC\TaxDisplayByCountry;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \Aelia\WC\Messages;
use \WP_Error;

/**
 * Implements a base class to store and handle the messages returned by the
 * plugin. This class is used to extend the basic functionalities provided by
 * standard WP_Error class.
 */
class Definitions {
	// @var string The menu slug for plugin's settings page.
	const MENU_SLUG = 'wc_aelia_tax_display_by_country';
	// @var string The plugin slug
	const PLUGIN_SLUG = 'wc-aelia-tax-display-by-country';
	// @var string The plugin text domain
	const TEXT_DOMAIN = 'wc-aelia-tax-display-by-country';

	const ARG_BILLING_COUNTRY = 'country';
	const ARG_AELIA_BILLING_COUNTRY = 'aelia_billing_country';

	const SESSION_BILLING_COUNTRY = 'aelia_billing_country';

	const ERR_INVALID_TEMPLATE = 1001;
}
