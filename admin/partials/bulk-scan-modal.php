<?php
/**
 * EU AI Act Ready - Bulk Scan Modal Partial
 *
 * @package EUAIACTREADY
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$euaiactready_buffer_count = absint( EUAIACTREADY_Data_Store::get_bulk_scan_buffer_count() );
$euaiactready_is_active    = $euaiactready_buffer_count > 0;
$euaiactready_modal_class  = 'euaiactready-modal' . ( $euaiactready_is_active ? ' is-active' : '' );
$euaiactready_aria_hidden  = $euaiactready_is_active ? 'false' : 'true';
?>

<div id="euaiactready-bulk-scan-modal" class="<?php echo esc_attr( $euaiactready_modal_class ); ?>" aria-hidden="<?php echo esc_attr( $euaiactready_aria_hidden ); ?>" data-buffer-count="<?php echo esc_attr( $euaiactready_buffer_count ); ?>">
	<div class="euaiactready-bulk-scan-modal-overlay" aria-hidden="true"></div>
	<div class="euaiactready-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="euaiactready-bulk-scan-title">
		<h2 id="euaiactready-bulk-scan-title"><?php esc_html_e( 'Scan Interrupted', 'eu-ai-act-ready' ); ?></h2>
		<p class="euaiactready-bulk-scan-message-spinner">
			<span class="spinner is-active"></span>
		</p>
		<p id="euaiactready-bulk-scan-message">
			<?php esc_html_e( 'Updating information from last scan...', 'eu-ai-act-ready' ); ?>
		</p>
	</div>
</div>
