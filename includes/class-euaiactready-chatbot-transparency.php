<?php
/**
 * EU AI Act Ready - Chatbot transparency overlay handling.
 *
 * @package EUAIACTREADY
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds transparency notices when AI chatbots interact with users.
 */
class EUAIACTREADY_Chatbot_Transparency {

	/**
	 * Initialize chatbot transparency.
	 */
	public function __construct() {
		$this->euaiactready_init_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function euaiactready_init_hooks() {
		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'euaiactready_enqueue_scripts' ) );

		// Add chatbot transparency notice.
		add_action( 'wp_footer', array( $this, 'euaiactready_add_chatbot_transparency' ), 999 );
	}

	/**
	 * Check if chatbot transparency is enabled.
	 *
	 * @return bool
	 */
	private function euaiactready_is_enabled() {
		return get_option( 'euaiactready_chatbot_transparency', true );
	}

	/**
	 * Get active chatbot platform.
	 *
	 * @return string|false Platform slug or false.
	 */
	private function euaiactready_get_active_platform() {
		return get_option( 'euaiactready_chatbot_platform', 'formilla' );
	}

	/**
	 * Enqueue chatbot transparency scripts and styles.
	 */
	public function euaiactready_enqueue_scripts() {
		if ( ! $this->euaiactready_is_enabled() ) {
			return;
		}

		$platform = $this->euaiactready_get_active_platform();
		$style    = get_option( 'euaiactready_chatbot_notice_style', 'badge' );

		// Enqueue chatbot transparency script.
		wp_enqueue_script(
			'euaiactready-chatbot-transparency',
			EUAIACTREADY_PLUGIN_URL . 'build/assets/chatbot-transparency.js',
			array(),
			EUAIACTREADY_VERSION,
			true
		);

		// Localize script with configuration.
		wp_localize_script(
			'euaiactready-chatbot-transparency',
			'euaiactreadyChatbotTransparencyConfig',
			array(
				'platform' => $platform,
				'style'    => $style,
			)
		);
	}

	/**
	 * Add chatbot transparency notice to footer.
	 */
	public function euaiactready_add_chatbot_transparency() {
		if ( ! $this->euaiactready_is_enabled() ) {
			return;
		}

		$platform       = $this->euaiactready_get_active_platform();
		$style          = get_option( 'euaiactready_chatbot_notice_style', 'badge' );
		$custom_message = sanitize_text_field( get_option( 'euaiactready_chatbot_notice_message', '' ) );

		$default_message = __( 'This chat uses AI assistance.', 'eu-ai-act-ready' );
		$message         = $custom_message ? $custom_message : $default_message;

		// Generate the transparency notice HTML.
		$this->euaiactready_render_transparency_notice( $message, $style, $platform );
	}

	/**
	 * Render transparency notice HTML.
	 *
	 * @param string $message  Notice message.
	 * @param string $style    Notice style.
	 * @param string $platform Chatbot platform.
	 */
	private function euaiactready_render_transparency_notice( $message, $style, $platform ) {
		?>
		<!-- EU AI Act Ready: Chatbot Transparency Notice -->
		<div id="ai-chatbot-transparency-container"
			data-platform="<?php echo esc_attr( $platform ); ?>"
			data-style="<?php echo esc_attr( $style ); ?>"
			style="display:none;">

			<?php if ( 'banner' === $style ) : ?>
				<!-- Banner Style -->
				<div class="ai-chatbot-notice ai-chatbot-banner">
					<div class="ai-chatbot-notice-content">
						<span class="ai-chatbot-icon">
							<?php echo wp_kses( EUAIACTREADY::euaiactready_get_ai_icon( 18, '#ffffff' ), EUAIACTREADY::euaiactready_get_svg_allowed_html() ); ?>
						</span>
						<span class="ai-chatbot-message"><?php echo esc_html( $message ); ?></span>
						<button class="ai-chatbot-notice-close" aria-label="<?php echo esc_attr__( 'Close notice', 'eu-ai-act-ready' ); ?>">&times;</button>
					</div>
				</div>

			<?php elseif ( 'badge' === $style ) : ?>
				<!-- Badge Style -->
				<div class="ai-chatbot-notice ai-chatbot-badge" title="<?php echo esc_attr( $message ); ?>">
					<span class="ai-chatbot-icon">
						<?php echo wp_kses( EUAIACTREADY::euaiactready_get_ai_icon( 14, '#ffffff' ), EUAIACTREADY::euaiactready_get_svg_allowed_html() ); ?>
					</span>
					<span class="ai-chatbot-text"><?php echo esc_html__( 'AI Assistant', 'eu-ai-act-ready' ); ?></span>
				</div>

			<?php elseif ( 'inline' === $style ) : ?>
				<!-- Inline Message Style -->
				<div class="ai-chatbot-notice ai-chatbot-inline">
					<span class="ai-chatbot-icon">
						<?php echo wp_kses( EUAIACTREADY::euaiactready_get_ai_icon( 16, '#667eea' ), EUAIACTREADY::euaiactready_get_svg_allowed_html() ); ?>
					</span>
					<span class="ai-chatbot-message"><?php echo esc_html( $message ); ?></span>
				</div>

			<?php elseif ( 'modal' === $style ) : ?>
				<!-- Modal Style -->
				<div class="ai-chatbot-notice ai-chatbot-modal-trigger">
					<button class="ai-chatbot-disclosure-btn">
						<span class="ai-chatbot-icon">
							<?php echo wp_kses( EUAIACTREADY::euaiactready_get_ai_icon( 14, '#ffffff' ), EUAIACTREADY::euaiactready_get_svg_allowed_html() ); ?>
						</span>
						<?php echo esc_html__( 'AI Disclosure', 'eu-ai-act-ready' ); ?>
					</button>
				</div>
				<div class="ai-chatbot-modal" id="ai-chatbot-modal" style="display:none;">
					<div class="ai-chatbot-modal-content">
						<span class="ai-chatbot-modal-close">&times;</span>
						<h3>
							<?php echo wp_kses( EUAIACTREADY::euaiactready_get_ai_icon( 20, '#667eea' ), EUAIACTREADY::euaiactready_get_svg_allowed_html() ); ?>
							<?php echo esc_html__( 'AI Disclosure', 'eu-ai-act-ready' ); ?>
						</h3>
						<p><?php echo esc_html( $message ); ?></p>
					</div>
				</div>

			<?php elseif ( 'tooltip' === $style ) : ?>
				<!-- Tooltip Style -->
				<div class="ai-chatbot-notice ai-chatbot-tooltip">
					<span class="ai-chatbot-icon" data-tooltip="<?php echo esc_attr( $message ); ?>">
						<?php echo wp_kses( EUAIACTREADY::euaiactready_get_ai_icon( 24, '#ffffff' ), EUAIACTREADY::euaiactready_get_svg_allowed_html() ); ?>
					</span>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}
}
