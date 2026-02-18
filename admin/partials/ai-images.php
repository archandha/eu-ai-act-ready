<?php
/**
 * EU AI Act Ready - AI Images Page
 *
 * @package EUAIACTREADY
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Get AI images (Detected/Marked).
$euaiactready_ai_images_args  = array(
	'post_type'      => 'attachment',
	'post_mime_type' => 'image',
	'post_status'    => 'inherit',
	'posts_per_page' => -1,
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering flagged posts requires metadata lookup.
	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	'meta_key'       => '_euaiactready_ai_generated',
	'meta_value'     => '1',
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	'orderby'        => 'modified',
	'order'          => 'DESC',
);
$euaiactready_ai_images_query = new WP_Query( $euaiactready_ai_images_args );
$euaiactready_ai_images       = $euaiactready_ai_images_query->posts;

// Get manually unmarked images (Ignored).
$euaiactready_unmarked_args   = array(
	'post_type'      => 'attachment',
	'post_mime_type' => 'image',
	'post_status'    => 'inherit',
	'posts_per_page' => -1,
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering flagged posts requires metadata lookup.
	'meta_query'     => array(
		array(
			'key'     => '_euaiactready_ai_manually_unmarked',
			'value'   => '1',
			'compare' => '=',
		),
	),
	'orderby'        => 'modified',
	'order'          => 'DESC',
);
$euaiactready_unmarked_query  = new WP_Query( $euaiactready_unmarked_args );
$euaiactready_unmarked_images = $euaiactready_unmarked_query->posts;

wp_reset_postdata();

// Determine active tab.
$euaiactready_active_tab = 'detected';
if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin tab selection.
	$euaiactready_active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin tab selection.
}

$euaiactready_current_page = '';
if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page slug derived from menu URL.
	$euaiactready_current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page slug derived from menu URL.
}
$euaiactready_detected_url = add_query_arg(
	array(
		'page' => $euaiactready_current_page,
		'tab'  => 'detected',
	),
	admin_url( 'admin.php' )
);
$euaiactready_unmarked_url = add_query_arg(
	array(
		'page' => $euaiactready_current_page,
		'tab'  => 'unmarked',
	),
	admin_url( 'admin.php' )
);
?>

<div class="wrap euaiactready-dashboard">
	<div class="euaiactready-header">
		<h1><?php esc_html_e( 'AI Images', 'eu-ai-act-ready' ); ?></h1>
		<p><?php esc_html_e( 'Manage images detected as AI or marked manually.', 'eu-ai-act-ready' ); ?></p>
	</div>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( $euaiactready_detected_url ); ?>" class="nav-tab<?php echo ( 'detected' === $euaiactready_active_tab ) ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Detected AI Images', 'eu-ai-act-ready' ); ?>
			<span class="count">(<?php echo esc_html( number_format_i18n( count( $euaiactready_ai_images ) ) ); ?>)</span>
		</a>
		<a href="<?php echo esc_url( $euaiactready_unmarked_url ); ?>" class="nav-tab<?php echo ( 'unmarked' === $euaiactready_active_tab ) ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Unmarked / Ignored', 'eu-ai-act-ready' ); ?>
			<span class="count">(<?php echo esc_html( number_format_i18n( count( $euaiactready_unmarked_images ) ) ); ?>)</span>
		</a>
	</nav>

	<div class="euaiactready-detections euaiactready-section-images">

		<!-- Tab: Detected Images -->
		<?php if ( 'detected' === $euaiactready_active_tab ) : ?>
			<div id="ai-images-container">
			<?php if ( empty( $euaiactready_ai_images ) ) : ?>
				<div class="euaiactready-empty-state">
					<span class="dashicons dashicons-format-image"></span>
					<p><?php esc_html_e( 'No images have been detected as AI-generated yet.', 'eu-ai-act-ready' ); ?></p>
					<p class="hint"><?php esc_html_e( 'Scan your library from the Dashboard to find AI content.', 'eu-ai-act-ready' ); ?></p>
				</div>
			<?php else : ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select id="euaiactready-bulk-action-images-top">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'eu-ai-act-ready' ); ?></option>
							<option value="unmark_ai"><?php esc_html_e( 'Unmark as AI', 'eu-ai-act-ready' ); ?></option>
						</select>
						<button type="button" id="euaiactready-doaction-images-top" class="button action"><?php esc_html_e( 'Apply', 'eu-ai-act-ready' ); ?></button>
					</div>
				</div>
				<table id="euaiactready-images-table" class="euaiactready-table">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" id="euaiactready-cb-select-all-images" /></th>
							<th class="column-thumbnail"></th>
							<th class="column-title"><?php esc_html_e( 'Filename', 'eu-ai-act-ready' ); ?></th>
							<th class="column-type"><?php esc_html_e( 'Format', 'eu-ai-act-ready' ); ?></th>
							<th class="column-author"><?php esc_html_e( 'Uploaded By', 'eu-ai-act-ready' ); ?></th>
							<th class="column-date"><?php esc_html_e( 'Upload Date', 'eu-ai-act-ready' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $euaiactready_ai_images as $euaiactready_image ) : ?>
							<?php
							$euaiactready_thumb     = wp_get_attachment_image( $euaiactready_image->ID, array( 40, 40 ), true );
							$euaiactready_mime_type = get_post_mime_type( $euaiactready_image->ID );
							$euaiactready_file_type = '';
							if ( false !== strpos( $euaiactready_mime_type, 'jpeg' ) || false !== strpos( $euaiactready_mime_type, 'jpg' ) ) {
								$euaiactready_file_type = 'JPEG';
							} elseif ( false !== strpos( $euaiactready_mime_type, 'png' ) ) {
								$euaiactready_file_type = 'PNG';
							} elseif ( false !== strpos( $euaiactready_mime_type, 'gif' ) ) {
								$euaiactready_file_type = 'GIF';
							} elseif ( false !== strpos( $euaiactready_mime_type, 'webp' ) ) {
								$euaiactready_file_type = 'WebP';
							} else {
								$euaiactready_file_type = strtoupper( pathinfo( get_attached_file( $euaiactready_image->ID ), PATHINFO_EXTENSION ) );
							}
							?>
							<tr>
								<td class="check-column">
									<input type="checkbox" name="image_ids[]" value="<?php echo esc_attr( $euaiactready_image->ID ); ?>" />
								</td>
								<td class="column-thumbnail"><?php echo wp_kses_post( $euaiactready_thumb ); ?></td>
								<td class="column-title">
									<a href="<?php echo esc_url( get_edit_post_link( $euaiactready_image->ID ) ); ?>">
										<?php echo esc_html( get_the_title( $euaiactready_image ) ); ?>
									</a>
									<div class="row-actions">
										<a href="<?php echo esc_url( wp_get_attachment_url( $euaiactready_image->ID ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'eu-ai-act-ready' ); ?></a>
										<span class="separator">|</span>
										<a href="<?php echo esc_url( get_edit_post_link( $euaiactready_image->ID ) ); ?>"><?php esc_html_e( 'Edit', 'eu-ai-act-ready' ); ?></a>
										<span class="separator">|</span>
									<button type="button" class="euaiactready-unmark-image action-danger" data-attachment-id="<?php echo esc_attr( $euaiactready_image->ID ); ?>">
											<?php esc_html_e( 'Unmark as AI', 'eu-ai-act-ready' ); ?>
										</button>
									</div>
								</td>
								<td class="column-type">
									<span class="type-indicator">
										<span class="dashicons dashicons-format-image"></span>
										<?php echo esc_html( $euaiactready_file_type ); ?>
									</span>
								</td>
								<td class="column-author">
									<?php echo esc_html( get_the_author_meta( 'display_name', $euaiactready_image->post_author ) ); ?>
								</td>
								<?php $euaiactready_upload_timestamp = strtotime( $euaiactready_image->post_date ); ?>
								<td class="column-date" data-order="<?php echo esc_attr( $euaiactready_upload_timestamp ); ?>">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $euaiactready_upload_timestamp ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php if ( ! empty( $euaiactready_ai_images ) ) : ?>
				<div class="tablenav bottom">
					<div class="alignleft actions bulkactions">
						<select id="euaiactready-bulk-action-images">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'eu-ai-act-ready' ); ?></option>
							<option value="unmark_ai"><?php esc_html_e( 'Unmark as AI', 'eu-ai-act-ready' ); ?></option>
						</select>
						<button type="button" id="euaiactready-doaction-images" class="button action"><?php esc_html_e( 'Apply', 'eu-ai-act-ready' ); ?></button>
					</div>
				</div>
			<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- Tab: Unmarked / Ignored Images -->
	<?php if ( 'unmarked' === $euaiactready_active_tab ) : ?>
		<div id="unmarked-content-container">
		<?php if ( empty( $euaiactready_unmarked_images ) ) : ?>
				<div class="euaiactready-empty-state">
					<span class="dashicons dashicons-saved"></span>
					<p><?php esc_html_e( 'No content currently unmarked.', 'eu-ai-act-ready' ); ?></p>
					<p class="hint"><?php esc_html_e( 'Images you unmark will appear here.', 'eu-ai-act-ready' ); ?></p>
				</div>
			<?php else : ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select id="euaiactready-bulk-action-unmarked-top">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'eu-ai-act-ready' ); ?></option>
							<option value="restore_scan"><?php esc_html_e( 'Include in next Scan', 'eu-ai-act-ready' ); ?></option>
							<option value="mark_ai"><?php esc_html_e( 'Mark as AI', 'eu-ai-act-ready' ); ?></option>
						</select>
						<button type="button" id="euaiactready-doaction-unmarked-top" class="button action"><?php esc_html_e( 'Apply', 'eu-ai-act-ready' ); ?></button>
					</div>
				</div>
				<table id="euaiactready-unmarked-table" class="euaiactready-table">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" id="euaiactready-cb-select-all-unmarked"></th>
							<th class="column-thumbnail"><?php esc_html_e( 'Image', 'eu-ai-act-ready' ); ?></th>
							<th class="column-filename"><?php esc_html_e( 'Filename', 'eu-ai-act-ready' ); ?></th>
							<th class="column-type"><?php esc_html_e( 'Format', 'eu-ai-act-ready' ); ?></th>
							<th class="column-date"><?php esc_html_e( 'Date Unmarked', 'eu-ai-act-ready' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'eu-ai-act-ready' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $euaiactready_unmarked_images as $euaiactready_image ) : ?>
							<?php
							$euaiactready_thumb     = wp_get_attachment_image_src( $euaiactready_image->ID, 'thumbnail' );
							$euaiactready_thumb_url = $euaiactready_thumb ? $euaiactready_thumb[0] : '';
							$euaiactready_filename  = wp_basename( get_attached_file( $euaiactready_image->ID ) );
							?>
							<tr id="post-<?php echo esc_attr( $euaiactready_image->ID ); ?>">
								<td class="check-column">
									<input type="checkbox" name="post[]" value="<?php echo esc_attr( $euaiactready_image->ID ); ?>">
								</td>
								<td class="column-thumbnail">
								<?php if ( $euaiactready_thumb_url ) : ?>
								<div class="euaiactready-thumbnail">
										<img src="<?php echo esc_url( $euaiactready_thumb_url ); ?>" alt="<?php echo esc_attr( get_the_title( $euaiactready_image ) ); ?>">
										</div>
									<?php else : ?>
										<span class="dashicons dashicons-format-image"></span>
									<?php endif; ?>
								</td>
								<td class="column-filename">
									<strong>
									<?php echo esc_html( $euaiactready_filename ); ?>
									</strong>
									<div class="row-actions">
									<span class="view"><a href="<?php echo esc_url( wp_get_attachment_url( $euaiactready_image->ID ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr__( 'View image', 'eu-ai-act-ready' ); ?>"><?php esc_html_e( 'View', 'eu-ai-act-ready' ); ?></a></span>
									<span class="separator">|</span>
									<span class="edit"><a href="<?php echo esc_url( get_edit_post_link( $euaiactready_image->ID ) ); ?>"><?php esc_html_e( 'Edit', 'eu-ai-act-ready' ); ?></a></span>
										<span class="separator">|</span>
										<span class="mark-ai">
								<button type="button" class="button-link euaiactready-mark-image action-danger" data-attachment-id="<?php echo esc_attr( $euaiactready_image->ID ); ?>">
												<?php esc_html_e( 'Mark as AI', 'eu-ai-act-ready' ); ?>
											</button>
										</span>
									</div>
								</td>
								<td class="column-type">
									<?php
									$euaiactready_mime_type = get_post_mime_type( $euaiactready_image->ID );
									$euaiactready_file_type = '';
									if ( false !== strpos( $euaiactready_mime_type, 'jpeg' ) || false !== strpos( $euaiactready_mime_type, 'jpg' ) ) {
										$euaiactready_file_type = 'JPEG';
									} elseif ( false !== strpos( $euaiactready_mime_type, 'png' ) ) {
										$euaiactready_file_type = 'PNG';
									} elseif ( false !== strpos( $euaiactready_mime_type, 'gif' ) ) {
										$euaiactready_file_type = 'GIF';
									} elseif ( false !== strpos( $euaiactready_mime_type, 'webp' ) ) {
										$euaiactready_file_type = 'WebP';
									} else {
										$euaiactready_file_type = strtoupper( pathinfo( get_attached_file( $euaiactready_image->ID ), PATHINFO_EXTENSION ) );
									}
									?>
									<span class="type-indicator">
										<span class="dashicons dashicons-format-image"></span>
									<?php echo esc_html( $euaiactready_file_type ); ?>
									</span>
								</td>
								<td class="column-date">
								<?php echo esc_html( get_the_modified_date( get_option( 'date_format' ), $euaiactready_image->ID ) ); ?>
								</td>
								<td class="column-actions">
								<button type="button" class="button button-small euaiactready-restore-btn"
										data-id="<?php echo esc_attr( $euaiactready_image->ID ); ?>"
											data-nonce="<?php echo esc_attr( wp_create_nonce( 'euaiactready_nonce' ) ); ?>">
										<?php esc_html_e( 'Include in next Scan', 'eu-ai-act-ready' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php if ( ! empty( $euaiactready_unmarked_images ) ) : ?>
				<div class="tablenav bottom">
					<div class="alignleft actions bulkactions">
						<select id="euaiactready-bulk-action-unmarked">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'eu-ai-act-ready' ); ?></option>
							<option value="restore_scan"><?php esc_html_e( 'Include in next Scan', 'eu-ai-act-ready' ); ?></option>
							<option value="mark_ai"><?php esc_html_e( 'Mark as AI', 'eu-ai-act-ready' ); ?></option>
						</select>
						<button type="button" id="euaiactready-doaction-unmarked" class="button action"><?php esc_html_e( 'Apply', 'eu-ai-act-ready' ); ?></button>
					</div>
				</div>
			<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php require EUAIACTREADY_PLUGIN_DIR . 'admin/partials/bulk-scan-modal.php'; ?>
</div>


