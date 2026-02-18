/**
 * EU AI Act Ready - Media Re-check Button Handler
 *
 * @param $
 * @package
 */

( function ( $ ) {
	'use strict';

	/**
	 * Handle checkbox change via event delegation
	 */
	$( document ).on( 'change', '.ai-compliance-checkbox', function () {
		const hidden = document.getElementById(
			'_euaiactready_ai_generated_hidden'
		);
		if ( hidden ) {
			hidden.value = this.checked ? '1' : '0';
		}
	} );

	/**
	 * Initialize re-check button handlers
	 */
	function initRecheckButtons() {
		if ( typeof euaiactreadyRecheck === 'undefined' ) {
			return;
		}

		const config = euaiactreadyRecheck;

		// Handle dynamic button initialization.
		$( document ).on( 'click', '.ai-recheck-btn', function ( event ) {
			event.preventDefault();

			const button = $( this );
			const attachmentId = button.data( 'attachment-id' );
			const nonce = button.data( 'nonce' );
			const isPopup = button.hasClass( 'ai-recheck-popup' );
			const messageSelector = button.data( 'message-selector' );
			const message = $( messageSelector );
			const reloadDelay = isPopup ? 1500 : 1000;

			button.prop( 'disabled', true );
			button.html( config.loadingHtml );
			message.hide();

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'euaiactready_recheck_detection',
					attachment_id: attachmentId,
					nonce,
				},
				success( response ) {
					if ( response.success ) {
						message
							.removeClass( 'error' )
							.addClass( 'success' )
							.html(
								config.successIcon + ' ' + response.data.message
							)
							.fadeIn();

						setTimeout( function () {
							location.reload();
						}, reloadDelay );
					} else {
						message
							.removeClass( 'success' )
							.addClass( 'error' )
							.html(
								config.errorIcon + ' ' + response.data.message
							)
							.fadeIn();

						button.prop( 'disabled', false );
						button.html( config.idleHtml );
					}
				},
				error() {
					message
						.removeClass( 'success' )
						.addClass( 'error' )
						.html( config.errorIcon + ' ' + config.defaultError )
						.fadeIn();

					button.prop( 'disabled', false );
					button.html( config.idleHtml );
				},
			} );
		} );
	}

	// Initialize when ready.
	$( document ).ready( function () {
		initRecheckButtons();
	} );
} )( jQuery );
