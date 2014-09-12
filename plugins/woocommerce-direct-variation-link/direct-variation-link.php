<?php
/*
Plugin Name: WooCommerce Direct Variation Link 
Plugin URI: http://www.wpbackoffice.com/plugins/woocommerce-direct-variation-link/
Description: Link directly to a specific WooCommerce product variation using get variables (yoursite.com/your-single-product?size=small&color=blue).
Version: 1.0.2
Author: WP BackOffice
Author URI: http://www.wpbackoffice.com
*/ 

/**
* 	Output the variable product add to cart area.
*
*	@access public
* 	@subpackage  Product
* 	@return void
*/
if ( ! function_exists( 'woocommerce_variable_add_to_cart' ) ) {

	function woocommerce_variable_add_to_cart() {
		global $product; 
		
		// Enqueue variation scripts
		wp_enqueue_script( 'wc-add-to-cart-variation' );
		
		$varation_names = wpbo_get_variation_values();
		$start_vals = wpbo_get_variation_start_values( $varation_names );
				
		// If there are start values use them, otherwise use the default attribute function
		if ( $start_vals != null ) {
			woocommerce_get_template( 'single-product/add-to-cart/variable.php', array(
				'available_variations'  => $product->get_available_variations(),
				'attributes'            => $product->get_variation_attributes(),
				'selected_attributes'   => $start_vals
			) );
		} else {
			woocommerce_get_template( 'single-product/add-to-cart/variable.php', array(
				'available_variations'  => $product->get_available_variations(),
				'attributes'            => $product->get_variation_attributes(),
				'selected_attributes'   => $product->get_variation_default_attributes()
			) );
		}
	}
}

/*
*	Returns an array of variations related to a product
*
*	@access 		public 
*	@subpackage  	Product
*	@return array	variation_names
*
*/		
function wpbo_get_variation_values() {
	global $product;
	
	// Create an array of possible variations
	$available_variations = $product->get_variation_attributes();
	$varation_names = array();
	
	foreach ( $available_variations as $key => $variations ) {
		array_push( $varation_names, $key );
	}
	
	return $varation_names;
}

/*
*	Returns an array of variations related to a product
*
*	@access 		public 
*	@subpackage  	Product
*	@param	array	variation_names
*	@return array	start_vals
*
*/	
if( !defined( 'ENCRYPTION_KEY' ) {
	define("ENCRYPTION_KEY", "dsaouh");
}

function wpbo_get_variation_start_values( $varation_names ) {
	global $product;

	$all_variations = $product->get_variation_attributes();

	if( !in_array( 'bkey', array_keys($_GET) ) ) 
		return $_GET;
	
	
	$decrypted_get = bo_simple_crypt( ENCRYPTION_KEY, $_GET['bkey'], 'decrypt' );

	$get_array = unserialize( $decrypted_get );

	// stop hackers
	if( !isset( $get_array['pa_missed-months'] ) )
		die;

	$_GET_lower = array_change_key_case($get_array, CASE_LOWER);

	// Check to see if any of the attributes are in $_GET vars
	$start_vals = array();

	foreach ( $varation_names as $name ) {
	
		// Get the lower case name and remove the pa_ if they have it
		$lower_name = strtolower( $name );
		$clean_name = str_replace( 'pa_', '', $lower_name );
		$flag = false;
		
		// Grab the right variation based on the full name
		if ( isset( $_GET_lower[ $lower_name ] ) ) {
		
			foreach( $all_variations[ $name ] as $val ) {		
				if ( strtolower( $val ) == strtolower( $_GET_lower[ $lower_name ] ) ) {
					$flag = true;
				}			
			}

			if ( $flag == true ) {
				$start_vals[ $lower_name ] = $_GET_lower[ $lower_name ];
			}
		
		// Grab the right variation if they attribute has a pa_ infronnt of it
		} elseif ( isset( $_GET_lower[ $clean_name ] ) ) {
		
			foreach( $all_variations[ $name ] as $val ) {		
				if ( strtolower( $val ) == strtolower( $_GET_lower[ $clean_name ] ) ) {
					$flag = true;
				}			
			}

			if ( $flag == true ) {
				$start_vals[ $lower_name ] = $_GET_lower[ $clean_name ];
			}
		}
	}
	
	return $start_vals;
}


define("ENCRYPTION_KEY", "dsaouh");
function bo_simple_crypt($key, $string, $action = 'encrypt'){
        $res = '';
        if($action !== 'encrypt'){
            $string = base64_decode($string);
        } 
        for( $i = 0; $i < strlen($string); $i++){
                $c = ord(substr($string, $i));
                if($action == 'encrypt'){
                    $c += ord(substr($key, (($i + 1) % strlen($key))));
                    $res .= chr($c & 0xFF);
                }else{
                    $c -= ord(substr($key, (($i + 1) % strlen($key))));
                    $res .= chr(abs($c) & 0xFF);
                }
        }
        if($action == 'encrypt'){
            $res = base64_encode($res);
        } 
        return $res;
}
