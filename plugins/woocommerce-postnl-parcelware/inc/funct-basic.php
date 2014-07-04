<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
* Admin messages
* @since 0.1
*/
if(!function_exists('showMessage')) {
	function showMessage($message, $errormsg = false)
	{
		if ($errormsg) { echo '<div id="message" class="error">';}
		else {echo '<div id="message" class="updated fade">';}
		echo "<p>$message</p></div>";
	}
}
?>