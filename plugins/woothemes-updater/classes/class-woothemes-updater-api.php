<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooThemes Updater API Class
 *
 * API class for the WooThemes Updater.
 *
 * @package WordPress
 * @subpackage WooThemes Updater
 * @category Core
 * @author WooThemes
 * @since 1.0.0
 *
 * TABLE OF CONTENTS
 *
 * private $token
 * private $api_url
 * private $errors
 *
 * - __construct()
 * - activate()
 * - deactivate()
 * - check()
 * - request()
 * - log_request_error()
 * - store_error_log()
 * - get_error_log()
 * - clear_error_log()
 */
class WooThemes_Updater_API {
	private $token;
	private $api_url;
	private $errors;

	public function __construct () {
		$this->token = 'woothemes-updater';
		$this->api_url = 'http://www.woothemes.com/wc-api/product-key-api';
		$this->errors = array();
	} // End __construct()

	/**
	 * Activate a given license key for this installation.
	 * @since    1.0.0
	 * @param   string $key 		 	The license key to be activated.
	 * @param   string $product_id	 	Product ID to be activated.
	 * @return boolean      			Whether or not the activation was successful.
	 */
	public function activate ( $key, $product_id, $plugin_file = '' ) {
		$response = false;

		//Ensure we have a correct product id.
		$product_id = trim( $product_id );
		if( ! is_numeric( $product_id ) ){
			$plugins = get_plugins();
			$plugin_name = isset( $plugins[ $plugin_file ]['Name'] ) ? $plugins[ $plugin_file ]['Name'] : $plugin_file;
			$error = '<strong>There seems to be incorrect data for the plugin ' . $plugin_name . '. Please contact <a href="https://support.woothemes.com" target="_blank">WooThemes Support</a> with this message.</strong>';
			$this->log_request_error( $error );
			return false;
		}

		$request = $this->request( 'activation', array( 'licence_key' => $key, 'product_id' => $product_id, 'home_url' => esc_url( home_url( '/' ) ) ) );

		return ! isset( $request->error );
	} // End activate()

	/**
	 * Deactivate a given license key for this installation.
	 * @since    1.0.0
	 * @param   string $key  The license key to be deactivated.
	 * @return boolean      Whether or not the deactivation was successful.
	 */
	public function deactivate ( $key ) {
		$response = false;

		$request = $this->request( 'deactivation', array( 'licence_key' => $key, 'home_url' => esc_url( home_url( '/' ) ) ) );

		return ! isset( $request->error );
	} // End deactivate()

	/**
	 * Check if the license key is valid.
	 * @since    1.0.0
	 * @param   string $key The license key to be validated.
	 * @return boolean      Whether or not the license key is valid.
	 */
	public function check ( $key ) {
		$response = false;

		$request = $this->request( 'check', array( 'licence_key' => $key ) );

		return ! isset( $request->error );
	} // End check()

	public function ping () {
		$response = false;

		$request = $this->request( 'ping' );

		return isset( $request->success );
	}
	/**
	 * Make a request to the WooThemes API.
	 *
	 * @access private
	 * @since 1.0.0
	 * @param string $endpoint (must include / prefix)
	 * @param array $params
	 * @return array $data
	 */
	private function request ( $endpoint = 'check', $params = array() ) {
		// $url = add_query_arg( 'wc-api', 'product-key-api', $this->api_url );
		$url = $this->api_url;

		$supported_methods = array( 'check', 'activation', 'deactivation', 'ping' );
		$supported_params = array( 'licence_key', 'file_id', 'product_id', 'home_url' );

		if ( in_array( $endpoint, $supported_methods ) ) {
			$url = add_query_arg( 'request', $endpoint, $url );
		}

		if ( 0 < count( $params ) ) {
			foreach ( $params as $k => $v ) {
				if ( in_array( $k, $supported_params ) ) {
					$url = add_query_arg( $k, $v, $url );
				}
			}
		}

		// Pass if this is a network install on all requests
		$url = add_query_arg( 'network', is_multisite() ? 1 : 0, $url );

		$response = wp_remote_get( $url, array(
			'method' => 'GET',
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(),
			'cookies' => array(),
			'ssl_verify' => false,
			'user-agent' => 'WooThemes Updater; http://www.woothemes.com'
		    )
		);

		if( is_wp_error( $response ) ) {
			$data = new StdClass;
			$data->error = __( 'WooThemes Request Error', 'woothemes-updater' );
		} else {
			$data = $response['body'];
			$data = json_decode( $data );
		}

		// Store errors in a transient, to be cleared on each request.
		if ( isset( $data->error ) && ( '' != $data->error ) ) {
			$error = esc_html( $data->error );
			$error = '<strong>' . $error . '</strong>';
			if ( isset( $data->additional_info ) ) { $error .= '<br /><br />' . esc_html( $data->additional_info ); }
			$this->log_request_error( $error );
		}elseif ( empty( $data ) ) {
			$error = '<strong>There was an error making your request, please try again.</strong>';
			$this->log_request_error( $error );
		}

		return $data;
	} // End request()

	/**
	 * Log an error from an API request.
	 *
	 * @access private
	 * @since 1.0.0
	 * @param string $error
	 */
	public function log_request_error ( $error ) {
		$this->errors[] = $error;
	} // End log_request_error()

	/**
	 * Store logged errors in a temporary transient, such that they survive a page load.
	 * @since  1.0.0
	 * @return  void
	 */
	public function store_error_log () {
		set_transient( $this->token . '-request-error', $this->errors );
	} // End store_error_log()

	/**
	 * Get the current error log.
	 * @since  1.0.0
	 * @return  void
	 */
	public function get_error_log () {
		return get_transient( $this->token . '-request-error' );
	} // End get_error_log()

	/**
	 * Clear the current error log.
	 * @since  1.0.0
	 * @return  void
	 */
	public function clear_error_log () {
		return delete_transient( $this->token . '-request-error' );
	} // End clear_error_log()
} // End Class
?>