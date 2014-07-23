<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooThemes Updater - Single Updater Class
 *
 * The WooThemes Updater - updater class.
 *
 * @package WordPress
 * @subpackage WooThemes Updater
 * @category Core
 * @author WooThemes
 * @since 1.0.0
 *
 * TABLE OF CONTENTS
 *
 * private $file
 * private $api_url
 * private $product_key
 * private $license_hash
 *
 * - __construct()
 * - update_check()
 * - plugin_information()
 * - request()
 */
class WooThemes_Updater_Update_Checker {
	protected $file;
	protected $api_url;
	protected $product_id;
	protected $file_id;
	protected $license_hash;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function __construct ( $file, $product_id, $file_id, $license_hash = '' ) {
		$this->api_url = 'http://www.woothemes.com/wc-api/woothemes-installer-api';
		$this->file = $file;
		$this->product_id = $product_id;
		$this->file_id = $file_id;
		$this->license_hash = $license_hash;
		$this->init();
	} // End __construct()

	/**
	 * Initialise the update check process.
	 * @access  public
	 * @since   1.2.0
	 * @return  void
	 */
	public function init () {
		// Check For Updates
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_check' ) );

		// Check For Plugin Information
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
	} // End init()


	/**
	 * Check for updates against the remote server.
	 *
	 * @access public
	 * @since  1.0.0
	 * @param  object $transient
	 * @return object $transient
	 */
	public function update_check ( $transient ) {

	    // Check if the transient contains the 'checked' information
	    // If no, just return its value without hacking it
	    if( empty( $transient->checked ) )
	        return $transient;

	    // The transient contains the 'checked' information
	    // Now append to it information form your own API
	    $args = array(
	        'request' => 'pluginupdatecheck',
	        'plugin_name' => $this->file,
	        'version' => $transient->checked[$this->file],
	        'product_id' => $this->product_id,
	        'file_id' => $this->file_id,
	        'license_hash' => $this->license_hash,
	        'url' => esc_url( home_url( '/' ) )
	    );

	    // Send request checking for an update
	    $response = $this->request( $args );

	    // If response is false, don't alter the transient
	    if( false !== $response ) {

	    	if( isset( $response->errors ) && isset ( $response->errors->woo_updater_api_license_deactivated ) ) {

	    		add_action('admin_notices', array( $this, 'error_notice_for_deactivated_plugin') );

	    	}else{

	        	$transient->response[$this->file] = $response;

	        }

	    }

	    return $transient;
	} // End update_check()

	/**
	 * Display an error notice
	 * @param  strin $message The message
	 * @return void
	 */
	public function error_notice_for_deactivated_plugin ( $message ) {

		$plugins = get_plugins();

		$plugin_name = isset( $plugins[$this->file] ) ? $plugins[$this->file]['Name'] : $this->file;

		echo sprintf( '<div id="message" class="error"><p>The license for the plugin %s has been deactivated. You can reactivate the license on your <a href="https://www.woothemes.com/my-account/my-licenses" target="_blank">dashboard</a>.</p></div>', $plugin_name ) ;

	}
	/**
	 * Check for the plugin's data against the remote server.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return object $response
	 */
	public function plugin_information ( $false, $action, $args ) {
		$transient = get_site_transient( 'update_plugins' );

		// Check if this plugins API is about this plugin
		if( ! isset( $args->slug ) || ( $args->slug != $this->file ) ) {
			return $false;
		}

		// POST data to send to your API
		$args = array(
			'request' => 'plugininformation',
			'plugin_name' => $this->file,
			'version' => $transient->checked[$this->file],
			'product_id' => $this->product_id,
			'file_id' => $this->file_id,
	        'license_hash' => $this->license_hash,
	        'url' => esc_url( home_url( '/' ) )
		);

		// Send request for detailed information
		$response = $this->request( $args );

		$response->sections = (array)$response->sections;
		$response->compatibility = (array)$response->compatibility;
		$response->tags = (array)$response->tags;
		$response->contributors = (array)$response->contributors;

		if ( count( $response->compatibility ) > 0 ) {
			foreach ( $response->compatibility as $k => $v ) {
				$response->compatibility[$k] = (array)$v;
			}
		}

		return $response;
	} // End plugin_information()

	/**
	 * Generic request helper.
	 *
	 * @access protected
	 * @since  1.0.0
	 * @param  array $args
	 * @return object $response or boolean false
	 */
	protected function request ( $args ) {
	    // Send request
	    $request = wp_remote_post( $this->api_url, array(
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array( 'user-agent' => 'WooThemesUpdater/1.3.0' ),
			'body' => $args,
			'cookies' => array(),
			'sslverify' => false
		    ) );
	    // Make sure the request was successful
	    if( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
	        // Request failed
	        return false;
	    }
	    // Read server response, which should be an object
	    if ( $request != '' ) {
	    	$response = json_decode( wp_remote_retrieve_body( $request ) );
	    } else {
	    	$response = false;
	    }

	    if( is_object( $response ) && isset( $response->payload ) ) {
	        return $response->payload;
	    } else {
	        // Unexpected response
	        return false;
	    }
	} // End request()
} // End Class
?>