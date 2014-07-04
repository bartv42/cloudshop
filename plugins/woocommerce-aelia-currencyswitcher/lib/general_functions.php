<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

// General functions used throughout the plugin.

if(!function_exists('get_value')) {
	/**
	 * Return the value from an associative array or an object.
	 *
	 * @param string $key The key or property name of the value.
	 * @param mixed $collection The array or object to search.
	 * @param mixed $default The value to return if the key does not exist.
	 * @param bool $remove Whether or not to remove the item from the collection.
	 * @return mixed The value from the array or object.
	 */
	function get_value($key, &$collection, $default = FALSE, $remove = FALSE) {
		$result = $default;
		if(is_array($collection) && array_key_exists($key, $collection)) {
			$result = $collection[$key];
			if($remove) {
				unset($collection[$key]);
			}
		} elseif(is_object($collection) && property_exists($collection, $key)) {
			$result = $collection->$key;
			if($remove) {
				unset($collection->$key);
			}
		}

		return $result;
	}
}

if(!function_exists('get_datetime_format')) {
	/**
	 * Returns a concatenation of WordPress settings for date and time formats.
	 *
	 * @param string separator A string to separate date and time formatting
	 * strings.
	 * @return string The concatenation of date_format, separator and time_format.
	 */
	function get_datetime_format($separator = ' ') {
		return get_option('date_format') . $separator . get_option('time_format');
	}
}

if(!function_exists('coalesce')) {
	/**
	 * Returns the value of the first non-empty argument received.
	 *
	 * @param mixed Any arguments.
	 * @return mixed The value of the first non-empty argument.
	 */
	function coalesce() {
		$args = func_get_args();
		foreach($args as $arg) {
			if(!empty($arg)) {
				return $arg;
			}
		}
		return null;
	}
}

if(!function_exists('in_array_ci')) {
   /**
    * Case-insensitive version of php's native in_array function.
    */
   function in_array_ci($needle, $haystack) {
      $needle = strtolower($needle);
      foreach ($haystack as $item) {
         if (strtolower($item) == $needle)
            return true;
      }
      return false;
   }
}
