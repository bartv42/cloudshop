<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/* Define some exception types used by Aelia plugins. This file declares multiple
 * classes, which is not usually good practice, but this is just because such
 * classes don't really implement anything and are just "aliases" of the base
 * Exception. If an exception needs to add any specific implementation, it should
 * be put in its own separate file.
 */
class NotImplementedException extends \Exception {}
// TODO Add more exception "aliases" here
