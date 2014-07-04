<?php
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Wrapper for the logger. This wrapper just exposes the Aelia\CurrencySwitcher\Logger
 * in the global namespace. It is needed because the main plugin class tries to
 * refer to the namespaced class, throwing a cryptic error on PHP 5.3, before
 * the requirements check can be performed.
 */
class LoggerWrapper extends Aelia\CurrencySwitcher\Logger {

}
