<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

// Paths
define('AELIA_CS_PLUGIN_SLUG', 'woocommerce-aelia-currencyswitcher');
define('AELIA_CS_PLUGIN_TEXTDOMAIN', 'woocommerce-aelia-currencyswitcher');
define('AELIA_CS_LIB_PATH', AELIA_CS_PLUGIN_PATH . '/lib');
define('AELIA_CS_VIEWS_PATH', AELIA_CS_PLUGIN_PATH . '/views');
define('AELIA_CS_ADMIN_VIEWS_PATH', AELIA_CS_VIEWS_PATH . '/admin');
define('AELIA_CS_CLASSES_PATH', AELIA_CS_LIB_PATH . '/classes');
define('AELIA_CS_WIDGETS_PATH', AELIA_CS_CLASSES_PATH . '/widgets');

// Get/Post Arguments
define('AELIA_CS_ARG_CURRENCY', 'aelia_cs_currency');
define('AELIA_CS_ARG_BILLING_COUNTRY', 'aelia_billing_country');
define('AELIA_CS_ARG_ORDER_BILLING_COUNTRY', 'country');

// URLs
define('AELIA_CS_PLUGIN_URL', plugins_url() . '/' . AELIA_CS_PLUGIN_SLUG);

// Get/Post Arguments
define('AELIA_CS_ARG_PRICE_FILTER_CURRENCY', 'price_filter_currency');

// Slugs
define('AELIA_CS_SLUG_OPTIONS_PAGE', 'aelia_cs_options_page');

// Error codes
define('AELIA_CS_OK', 0);
define('AELIA_CS_ERR_FILE_NOT_FOUND', 100);
define('AELIA_CS_ERR_INVALID_CURRENCY', 101);
define('AELIA_CS_ERR_MISCONFIGURED_CURRENCIES', 102);
define('AELIA_CS_ERR_INVALID_SOURCE_CURRENCY', 103);
define('AELIA_CS_ERR_INVALID_DESTINATION_CURRENCY', 104);
define('AELIA_CS_ERR_INVALID_TEMPLATE', 105);
define('AELIA_CS_ERR_INVALID_WIDGET_CLASS', 106);

// Session/User Keys
define('AELIA_CS_USER_CURRENCY', 'aelia_cs_selected_currency');
define('AELIA_CS_RECALCULATE_CART_TOTALS', 'aelia_cs_recalculate_cart_totals');
define('AELIA_CS_SESSION_BILLING_COUNTRY', 'aelia_billing_country');
