<?php
/**
 * WooCommerce Gateway Braintree
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Gateway Braintree to newer
 * versions in the future. If you wish to customize WooCommerce Gateway Braintree for your
 * needs please refer to http://docs.woothemes.com/document/braintree/ for more information.
 *
 * @package     WC-Gateway-Braintree/Classes
 * @author      SkyVerge
 * @copyright   Copyright (c) 2012-2014, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Haandles all gateway-related errors
 *
 * @since 2.0
 * @extends Exception
 */
class WC_Gateway_Braintree_Exception extends Exception {


	/** @var string the exception type */
	private $type;

	/** @var null|object the braintree response object */
	private $response;


	/**
	 * Setup the exception
	 *
	 * @since 2.0
	 * @param string $type the type of exception being thrown, currently:
	 *  + `customer` - an error during braintree customer creation
	 *  + `credit_card` - an error during braintree credit card creation
	 *  + `transaction` - an error during transaction processing
	 * @param object|null $response the optional braintree response object
	 * @return \WC_Gateway_Braintree_Exception
	 */
	public function __construct( $type, $response = null ) {

		$this->type     = $type;
		$this->response = $response;

		// instantiate base exception class so getMessage(), etc work as expected
		// note that an instance is passed, which calls the toString() method below to set the base exception message,
		// as getMessage() cannot be overridden
		parent::__construct( $this );
	}


	/**
	 * Builds the exception message when casted as a string. This is primarily used to set the base exception class
	 * message, so getMessage() works as expected
	 *
	 * @since 2.0
	 * @return string the exception message
	 */
	public function __toString() {

		// set a generic error message
		switch ( $this->type ) {

			case 'customer':
				$message = __( 'Failed to create customer', WC_Braintree::TEXT_DOMAIN );
				break;

			case 'credit_card':
				$message = __( 'Failed to create credit card', WC_Braintree::TEXT_DOMAIN );
				break;

			case 'transaction':
				$message = __( 'Transaction error', WC_Braintree::TEXT_DOMAIN );
				break;

			default:
				$message = __( 'Error', WC_Braintree::TEXT_DOMAIN );
				break;
		}

		/**
		 * if processing a transaction or performing a card verification, there may be a processor or gateway-specific
		 * decline message, which is formed here.
		 */

		// generate decline message for transaction
		if ( ! empty( $this->response->transaction->status ) ) {

			if ( 'processor_declined' == $this->response->transaction->status ) {

				$decline_message = $this->generate_decline_message( $this->response->transaction->status, $this->response->transaction->processorResponseText, $this->response->transaction->processorResponseCode );

			} elseif ( 'gateway_rejected' == $this->response->transaction->status ) {

				$decline_message = $this->generate_decline_message( $this->response->transaction->status, $this->response->transaction->gatewayRejectionReason );

			} else {

				$decline_message = $this->generate_decline_message();
			}

		// generate decline message for card verification
		} elseif ( ! empty( $this->response->creditCardVerification ) ) {

			if ( 'processor_declined' == $this->response->creditCardVerification->status ) {

				$decline_message = $this->generate_decline_message( $this->response->creditCardVerification->status, $this->response->creditCardVerification->processorResponseText, $this->response->creditCardVerification->processorResponseCode );

			} elseif ( 'gateway_rejected' == $this->response->creditCardVerification->status ) {

				$decline_message = $this->generate_decline_message( $this->response->creditCardVerification->status, $this->response->creditCardVerification->gatewayRejectionReason );

			} else {

				$decline_message = $this->generate_decline_message();
			}
		}

		// add decline message if available
		if ( isset ( $decline_message ) ) {
			$message = sprintf( '%s - %s', $message, $decline_message );
		}

		return $message;
	}


	/**
	 * A custom method for retrieving specific error messages. Braintree may return multiple error messages for a single
	 * API call.
	 *
	 * @since 2.0
	 * @return array error messages
	 */
	public function getErrors() {

		$errors = array();

		foreach ( $this->response->errors->deepAll() as  $error ) {
			$errors[] = $error->code . " : " . $error->message;
		}

		return $errors;
	}


	/**
	 * Form the decline message string based on the transaction/verification status
	 *
	 * @since 2.0
	 * @param string $status optional, the transaction or verification status
	 * @param string $reason optional, the reason for decline
	 * @param string $code optional, the code associated with the decline
	 * @return string the decline message
	 */
	private function generate_decline_message( $status = '', $reason = '', $code = '' ) {

		switch ( $status ) {

			case 'processor_declined':
				$message = sprintf( __( 'Processor Declined : Code %s - %s', WC_Braintree::TEXT_DOMAIN ), $code, $reason );
				break;

			case 'gateway_rejected':
				$message = sprintf( __( 'Gateway Rejected : Reason - %s', WC_Braintree::TEXT_DOMAIN ), $reason );
				break;

			default:
				$message = __( 'Gateway Validation Failure', WC_Braintree::TEXT_DOMAIN );
				break;
		}

		return $message;
	}


} // end \WC_Gateway_Braintree_Exception class
