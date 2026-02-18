/**
 * EU AI Act Ready - Chatbot Transparency Frontend Script
 *
 * @package
 */

( function () {
	'use strict';

	// Wait for euaiactreadyChatbotTransparencyConfig to be available.
	if ( typeof euaiactreadyChatbotTransparencyConfig === 'undefined' ) {
		return;
	}

	const EUAIACTREAD_CHATBOT_TRANSPARENCY_CONST = {
		platform: euaiactreadyChatbotTransparencyConfig.platform,
		style: euaiactreadyChatbotTransparencyConfig.style,
		injected: false,

		/**
		 * Initialize transparency system
		 */
		init() {
			// Wait for chatbot to load.
			this.waitForChatbot();
		},

		/**
		 * Wait for chatbot widget to load
		 */
		waitForChatbot() {
			const self = this;
			let attempts = 0;
			const maxAttempts = 100; // 20 seconds (longer wait for Formilla).

			const checkInterval = setInterval( function () {
				attempts++;

				if ( self.detectChatbot() ) {
					clearInterval( checkInterval );
					self.injectTransparency();
				} else if ( attempts >= maxAttempts ) {
					clearInterval( checkInterval );
					self.injectFallback();
				}
			}, 200 );
		},

		/**
		 * Detect if chatbot is loaded
		 */
		detectChatbot() {
			switch ( this.platform ) {
				case 'formilla':
					// Check for Formilla object.
					if ( typeof Formilla !== 'undefined' ) {
						return true;
					}
					// Check for Formilla chat elements.
					const formillaSelectors = [
						'#formilla-chat-button',
						'#formilla-chat-iframe',
						'iframe[src*="formilla"]',
						'[class*="formilla"]',
						'[id*="formilla"]',
					];
					for ( const selector of formillaSelectors ) {
						const element = document.querySelector( selector );
						if ( element ) {
							return true;
						}
					}
					return false;

				case 'intercom':
					return (
						typeof Intercom !== 'undefined' ||
						document.querySelector( '#intercom-container' )
					);

				case 'drift':
					return (
						typeof drift !== 'undefined' ||
						document.querySelector( '#drift-widget' )
					);

				case 'tidio':
					return (
						typeof tidioChatApi !== 'undefined' ||
						document.querySelector( '#tidio-chat' )
					);

				case 'tawk':
					return (
						typeof Tawk_API !== 'undefined' ||
						document.querySelector( '#tawkchat-container' )
					);

				case 'zendesk':
					return (
						typeof zE !== 'undefined' ||
						document.querySelector( '#launcher' )
					);

				case 'livechat':
					return (
						typeof LiveChatWidget !== 'undefined' ||
						document.querySelector( '#chat-widget-container' )
					);

				case 'crisp':
					return (
						typeof $crisp !== 'undefined' ||
						document.querySelector( '[data-crisp-website-id]' )
					);

				case 'freshchat':
					return (
						typeof fcWidget !== 'undefined' ||
						document.querySelector( '#fc_frame' )
					);

				case 'custom':
					// For custom chatbots, check for common selectors.
					return (
						document.querySelector( '.chatbot-widget' ) ||
						document.querySelector( '#chat-widget' ) ||
						document.querySelector( '[data-chatbot]' )
					);

				default:
					return false;
			}
		},

		/**
		 * Get chatbot widget element
		 */
		getChatbotWidget() {
			const selectors = {
				formilla:
					'#formilla-chat-button, #formilla-chat-iframe, iframe[src*="formilla"], [class*="formilla"], [id*="formilla"]',
				intercom: '#intercom-container',
				drift: '#drift-widget, #drift-widget-container',
				tidio: '#tidio-chat',
				tawk: '#tawkchat-container, .tawk-button',
				zendesk: '#launcher, .zEWidget-launcher',
				livechat: '#chat-widget-container',
				crisp: '.crisp-client',
				freshchat: '#fc_frame',
				custom: '.chatbot-widget, #chat-widget, [data-chatbot]',
			};

			const selector = selectors[ this.platform ];
			return selector ? document.querySelector( selector ) : null;
		},

		/**
		 * Inject transparency notice
		 */
		injectTransparency() {
			if ( this.injected ) {
				return;
			}

			const container = document.getElementById(
				'ai-chatbot-transparency-container'
			);
			if ( ! container ) {
				return;
			}

			const widget = this.getChatbotWidget();
			if ( ! widget ) {
				return;
			}

			// Clone and show the notice.
			const notice = container.querySelector( '.ai-chatbot-notice' );
			if ( notice ) {
				const clonedNotice = notice.cloneNode( true );

				// Position near widget (always).
				this.positionNearWidget( clonedNotice, widget );

				// Ensure notice is always visible (backup).
				if (
					clonedNotice.style.display !== 'flex' &&
					clonedNotice.style.display !== 'block'
				) {
					clonedNotice.style.display = 'flex';
				}
				clonedNotice.style.visibility = 'visible';
				clonedNotice.style.opacity = '1';

				// Setup event listeners.
				this.setupEventListeners( clonedNotice );

				this.injected = true;
			}
		},

		/**
		 * Position notice near chatbot widget
		 *
		 * @param notice The transparency notice element to position.
		 * @param widget The chatbot widget element to position near.
		 */
		positionNearWidget( notice, widget ) {
			notice.style.position = 'fixed';
			notice.style.zIndex = '999998'; // Just below typical chatbot z-index.
			notice.style.display = 'flex'; // Ensure visibility.
			notice.style.visibility = 'visible';
			notice.style.opacity = '1';

			widget.getBoundingClientRect();

			// Use simple bottom-right positioning instead of complex calculation.
			// This works better with chat widgets that may be hidden or have unusual positioning.
			notice.style.bottom = '90px'; // Standard position above typical chat button.
			notice.style.right = '20px';

			document.body.appendChild( notice );
		},

		/**
		 * Setup event listeners
		 *
		 * @param notice The transparency notice element to setup listeners for.
		 */
		setupEventListeners( notice ) {
			// Close button.
			const closeBtn = notice.querySelector(
				'.ai-chatbot-notice-close, .ai-chatbot-modal-close'
			);
			if ( closeBtn ) {
				closeBtn.addEventListener( 'click', function () {
					notice.style.display = 'none';
				} );
			}

			// Modal trigger button.
			const modalTrigger = notice.querySelector(
				'.ai-chatbot-disclosure-btn'
			);
			if ( modalTrigger ) {
				modalTrigger.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					e.stopPropagation();

					// Find or create the modal.
					let modal = document.getElementById( 'ai-chatbot-modal' );

					// If modal not found in body, look in container and clone it.
					if (
						! modal ||
						modal.parentElement.id ===
							'ai-chatbot-transparency-container'
					) {
						const container = document.getElementById(
							'ai-chatbot-transparency-container'
						);
						if ( container ) {
							const hiddenModal =
								container.querySelector( '#ai-chatbot-modal' );
							if ( hiddenModal ) {
								// Remove any existing modal in body first.
								const existingModal =
									document.body.querySelector(
										'#ai-chatbot-modal'
									);
								if (
									existingModal &&
									existingModal.parentElement ===
										document.body
								) {
									existingModal.remove();
								}

								// Clone and append modal to body.
								modal = hiddenModal.cloneNode( true );
								modal.id = 'ai-chatbot-modal'; // Ensure ID is set.
								document.body.appendChild( modal );

								// Setup modal close button.
								const modalCloseBtn = modal.querySelector(
									'.ai-chatbot-modal-close'
								);
								if ( modalCloseBtn ) {
									modalCloseBtn.addEventListener(
										'click',
										function ( e ) {
											e.preventDefault();
											e.stopPropagation();
											modal.style.display = 'none';
										}
									);
								}

								// Close on background click.
								modal.addEventListener(
									'click',
									function ( e ) {
										if ( e.target === modal ) {
											modal.style.display = 'none';
										}
									}
								);

								// Close on ESC key.
								document.addEventListener(
									'keydown',
									function ( e ) {
										if (
											e.key === 'Escape' &&
											modal.style.display === 'flex'
										) {
											modal.style.display = 'none';
										}
									}
								);
							}
						}
					}

					if ( modal ) {
						modal.style.display = 'flex';
						modal.style.visibility = 'visible';
						modal.style.opacity = '1';
					}
				} );
			}
		},

		/**
		 * Fallback injection if chatbot not detected
		 */
		injectFallback() {
			if ( this.injected ) {
				return;
			}

			const container = document.getElementById(
				'ai-chatbot-transparency-container'
			);
			if ( ! container ) {
				return;
			}

			const notice = container.querySelector( '.ai-chatbot-notice' );
			if ( notice ) {
				const clonedNotice = notice.cloneNode( true );

				// Position in bottom-right (typical chat location).
				clonedNotice.style.position = 'fixed';
				clonedNotice.style.bottom = '80px';
				clonedNotice.style.right = '20px';
				clonedNotice.style.zIndex = '999998';
				clonedNotice.style.display = 'flex';
				clonedNotice.style.visibility = 'visible';
				clonedNotice.style.opacity = '1';

				document.body.appendChild( clonedNotice );

				this.setupEventListeners( clonedNotice );
				this.injected = true;
			}
		},
	};

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			EUAIACTREAD_CHATBOT_TRANSPARENCY_CONST.init();
		} );
	} else {
		EUAIACTREAD_CHATBOT_TRANSPARENCY_CONST.init();
	}
} )();
