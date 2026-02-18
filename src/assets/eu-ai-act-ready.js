/**
 * EU AI Act Ready - Frontend Transparency Features
 *
 * @param $
 * @package
 */

( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// Close banner notices.
		$( '.ai-notice-close' ).on( 'click', function () {
			$( this )
				.closest( '.eu-ai-act-ready-notice' )
				.fadeOut( 300, function () {
					$( this ).remove();
				} );
		} );

		// Modal functionality.
		$( '.eu-ai-act-ready-modal-trigger' ).on( 'click', function () {
			const message = $( this ).data( 'message' );

			$( '#ai-modal-message' ).html( message );
			const $modal = $( '#eu-ai-act-ready-modal' );
			$modal.css( 'display', 'flex' );
			// Force reflow to ensure the display change is applied before adding the class.
			$modal[ 0 ].offsetHeight;
			$modal.addClass( 'show' );
		} );

		// Close modal.
		$( '.ai-modal-close, .eu-ai-act-ready-modal' ).on(
			'click',
			function ( e ) {
				if ( e.target === this ) {
					const $modal = $( '#eu-ai-act-ready-modal' );
					$modal.removeClass( 'show' );
					setTimeout( function () {
						$modal.css( 'display', 'none' );
					}, 300 ); // Match transition duration.
				}
			}
		);

		// Escape key closes modal.
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				const $modal = $( '#eu-ai-act-ready-modal' );
				if ( $modal.hasClass( 'show' ) ) {
					$modal.removeClass( 'show' );
					setTimeout( function () {
						$modal.css( 'display', 'none' );
					}, 300 ); // Match transition duration.
				}
			}
		} );

		// Remember closed notices (optional, uses localStorage).
		const rememberClosed = false; // Set to true to remember user preference.

		if ( rememberClosed ) {
			// Check if user previously closed notices.
			if ( localStorage.getItem( 'ai_notices_dismissed' ) === 'true' ) {
				$( '.eu-ai-act-ready-notice' ).hide();
			}

			$( '.ai-notice-close' ).on( 'click', function () {
				localStorage.setItem( 'ai_notices_dismissed', 'true' );
			} );
		}

		// Accessibility: Add keyboard navigation for modal.
		$( '#eu-ai-act-ready-modal' ).on( 'keydown', function ( e ) {
			if ( e.key === 'Tab' ) {
				// Trap focus within modal.
				const $focusable = $( this ).find(
					'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
				);
				const $first = $focusable.first();
				const $last = $focusable.last();

				if ( e.shiftKey ) {
					if ( document.activeElement === $first[ 0 ] ) {
						e.preventDefault();
						$last.focus();
					}
				} else if ( document.activeElement === $last[ 0 ] ) {
					e.preventDefault();
					$first.focus();
				}
			}
		} );
	} );
} )( jQuery );
