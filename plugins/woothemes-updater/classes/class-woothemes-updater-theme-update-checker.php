<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooThemes Updater - Single Theme Updater Class
 *
 * The WooThemes Updater - theme updater class.
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
 */
class WooThemes_Updater_Theme_Update_Checker extends WooThemes_Updater_Update_Checker {
	/**
	 * Constructor.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function __construct ( $file, $product_id, $file_id, $license_hash = '' ) {
		parent::__construct( $file, $product_id, $file_id, $license_hash );
	} // End __construct()

	/**
	 * Initialise the update check process.
	 * @access  public
	 * @since   1.2.0
	 * @return  void
	 */
	public function init () {
		// Check For Updates
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'update_check' ) );
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

	    $theme = str_replace( '/style.css', '', $this->file );

	    // The transient contains the 'checked' information
	    // Now append to it information form your own API
	    $args = array(
	        'request' => 'themeupdatecheck',
	        'theme_name' => $theme,
	        'version' => $transient->checked[ $theme ],
	        'product_id' => $this->product_id,
	        'file_id' => $this->file_id,
	        'license_hash' => $this->license_hash,
	        'url' => esc_url( home_url( '/' ) )
	    );

	    // Send request checking for an update
	    $response = $this->request( $args );

	    // If response is false, don't alter the transient
	    if( false !== $response ) {

	    	if( isset( $response->errors ) && isset ( $response->errors->woo_updater_api_license_deactivated ) ){

	    		add_action('admin_notices', array( $this, 'error_notice_for_deactivated_plugin') );

	    	}else{

	        	$transient->response[$theme]['new_version'] = $response->new_version;
	        	$transient->response[$theme]['url'] = 'http://www.woothemes.com/';
	        	$transient->response[$theme]['package'] = $response->package;


	        }

	    }

	    return $transient;
	} // End update_check()

	/**
	 * Display an error notice
	 * @param  strin $message The message
	 * @return void
	 */
	public function error_notice_for_deactivated_theme ( $message ) {
		$themes = wp_get_themes();

		$theme_name = isset( $themes[$this->file] ) ? $themes[$this->file]->__get( 'Name' ) : $this->file;

		echo sprintf( '<div id="message" class="error"><p>The license for the theme %s has been deactivated. You can reactivate the license on your <a href="https://www.woothemes.com/my-account/my-licenses" target="_blank">dashboard</a>.</p></div>', $theme_name );
	} // End error_notice_for_deactivated_theme()
} // End Class
?>