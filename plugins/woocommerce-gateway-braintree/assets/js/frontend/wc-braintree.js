jQuery(document).ready( function ( $ ) {

	"use strict";

	// setup new braintree and get form object
	var braintree = Braintree.create( braintree_params.cse_key );

	// checkout page
	if ( $( 'form.checkout' ).length ) {

		// handle saved cards, note this is bound to the updated_checkout trigger so it fires even when other parts
		// of the checkout are changed
		$( 'body' ).bind( 'updated_checkout', function() { handleSavedCards() } );

		// validate card data before order is submitted
		$( 'form.checkout' ).bind( 'checkout_place_order_braintree', function() { return validateCardData( $( this ) ) } );

	// checkout->pay page
	} else {

		// handle saved cards on checkout->pay page
		handleSavedCards();

		// validate card data before order is submitted when braintree is selected
		$( 'form#order_review' ).submit( function () {

			if ( 'braintree' == $( '#order_review input[name=payment_method]:checked' ).val() ) {
				return validateCardData( $( this ) )
			}
		} );
	}


	// Perform validation on the card info entered and encrypt the card info when successful
	function validateCardData( $form ) {

		var savedCardSelected = $( 'input[name=braintree-cc-token]:radio' ).filter( ':checked' ).val();

		var errors = [];

		var cardNumber = $( '#braintree-cc-number' ).val();
		var cvv        = $( '#braintree-cc-cvv' ).val();
		var expMonth   = $( '#braintree-cc-exp-month' ).val();
		var expYear    = $( '#braintree-cc-exp-year' ).val();

		// don't validate fields or encrypt data if a saved card is being used, unless CVV is required
		if ( 'undefined' !== typeof savedCardSelected && '' !== savedCardSelected ) {

			// validate CVV if present
			if ( 'undefined' !== typeof cvv ) {

				if ( ! cvv ) {
					errors.push( braintree_params.cvv_missing );
				} else if (/\D/.test( cvv ) ) {
					errors.push( braintree_params.cvv_invalid );
				} else if ( cvv.length < 3 || cvv.length > 4 ) {
					errors.push( braintree_params.cvv_length_invalid );
				}

				if ( errors.length > 0 ) {

					// hide and remove any previous errors
					$( '.woocommerce-error, .woocommerce-message' ).remove();

					// add errors
					$form.prepend( '<ul class="woocommerce-error"><li>' + errors.join( '</li><li>' ) + '</li></ul>' );

					// unblock UI
					$form.removeClass( 'processing' ).unblock();

					$form.find( '.input-text, select' ).blur();

					// scroll to top
					$( 'html, body' ).animate( {
						scrollTop: ( $form.offset().top - 100 )
					}, 1000 );

					return false;

				} else {

					// encrypt the credit card fields
					braintree.encryptForm( $form );

					return true;
				}
			}

			return true;
		}

		// replace any dashes or spaces in the card number
		cardNumber = cardNumber.replace( /-|\s/g, '' );

		// validate card number
		if ( ! cardNumber ) {

			errors.push( braintree_params.card_number_missing );

		} else if ( cardNumber.length < 12 || cardNumber.length > 19 || /\D/.test( cardNumber ) || ! luhnCheck( cardNumber ) ) {

			errors.push( braintree_params.card_number_invalid );
		}

		// validate expiration date
		var currentYear = new Date().getFullYear();
		if ( /\D/.test( expMonth ) || /\D/.test( expYear ) ||
				expMonth > 12 ||
				expMonth < 1 ||
				expYear < currentYear ||
				expYear > currentYear + 20 ) {
			errors.push( braintree_params.card_exp_date_invalid );
		}

		// validate CVV if present
		if ( 'undefined' !== typeof cvv ) {

			if ( ! cvv ) {
				errors.push( braintree_params.cvv_missing );
			} else if (/\D/.test( cvv ) ) {
				errors.push( braintree_params.cvv_invalid );
			} else if ( cvv.length < 3 || cvv.length > 4 ) {
				errors.push( braintree_params.cvv_length_invalid );
			}
		}

		if ( errors.length > 0 ) {

			// hide and remove any previous errors
			$( '.woocommerce-error, .woocommerce-message' ).remove();

			// add errors
			$form.prepend( '<ul class="woocommerce-error"><li>' + errors.join( '</li><li>' ) + '</li></ul>' );

			// unblock UI
			$form.removeClass( 'processing' ).unblock();

			$form.find( '.input-text, select' ).blur();

			// scroll to top
			$( 'html, body' ).animate( {
				scrollTop: ( $form.offset().top - 100 )
			}, 1000 );

			return false;

		} else {

			// get rid of any space/dash characters
			$( '#braintree-cc-number' ).val( cardNumber );

			// encrypt the credit card fields
			braintree.encryptForm( $form );

			return true;
		}
	}

	// show/hide the credit cards when a saved card is de-selected/selected
	function handleSavedCards() {

		$( 'input[name=braintree-cc-token]:radio' ).change(function () {

			var savedCreditCardSelected = $( this ).filter( ':checked' ).val(),
				$newCardSection = $( 'div.braintree-new-card' ),
				$cvvField = $( '#braintree-cc-cvv-section' );

			// if a saved card is selected, hide the credit card form
			if ( '' !== savedCreditCardSelected ) {
				$newCardSection.slideUp( 200 );
				$cvvField.removeClass( 'form-row-last' );
			} else {
				// otherwise show it so customer can enter new card
				$newCardSection.slideDown( 200 );
				$cvvField.addClass( 'form-row-last ');
			}
		} ).change();
	}

	// luhn check
	function luhnCheck( cardNumber ) {
		var sum = 0;
		for ( var i = 0, ix = cardNumber.length; i < ix - 1; i++ ) {
			var weight = parseInt( cardNumber.substr( ix - ( i + 2 ), 1 ) * ( 2 - ( i % 2 ) ) );
			sum += weight < 10 ? weight : weight - 9;
		}

		return cardNumber.substr( ix - 1 ) == ( ( 10 - sum % 10 ) % 10 );
	}

} );
