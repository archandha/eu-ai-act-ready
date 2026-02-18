<?php
/**
 * EU AI Act Ready - Data store helpers for custom tables.
 *
 * @package EUAIACTREADY
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles persistence of AI flags, scans, and activity logs.
 */
class EUAIACTREADY_Data_Store {
	/**
	 * Whether a bulk scan is active for this request.
	 *
	 * @var bool
	 */
	private static $bulk_scan_active = false;

	/**
	 * Transient key for buffering bulk scan results.
	 *
	 * @var string
	 */
	private static $bulk_scan_buffer_key = EUAIACTREADY_MEDIA_BULK_SCAN_BUFFER_KEY;

	/**
	 * Initialize data store and register hooks.
	 */
	public function __construct() {
		self::register_hooks();
	}

	/**
	 * Tracked meta keys for syncing item state.
	 *
	 * @var array
	 */
	private static $tracked_meta_keys = array(
		'_euaiactready_ai_content',
		'_euaiactready_ai_content_marked_date',
		'_euaiactready_ai_generated',
		'_euaiactready_ai_marked_method',
		'_euaiactready_ai_detection_source',
		'_euaiactready_ai_manually_unmarked',
	);

	/**
	 * Content meta keys (posts/pages/CPTs).
	 *
	 * @var array
	 */
	private static $content_meta_keys = array(
		'_euaiactready_ai_content',
		'_euaiactready_ai_content_marked_date',
	);

	/**
	 * Media meta keys (attachments).
	 *
	 * @var array
	 */
	private static $media_meta_keys = array(
		'_euaiactready_ai_generated',
		'_euaiactready_ai_marked_method',
		'_euaiactready_ai_detection_source',
		'_euaiactready_ai_manually_unmarked',
	);

	/**
	 * Register hooks for meta changes.
	 */
	public static function register_hooks() {
		add_action( 'added_post_meta', array( __CLASS__, 'handle_meta_change' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'handle_meta_change' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'handle_meta_delete' ), 10, 4 );
	}

	/**
	 * Mark the current request as a bulk scan run.
	 */
	public static function begin_bulk_scan() {
		self::$bulk_scan_active = true;
	}

	/**
	 * Clear bulk scan flag for the current request.
	 */
	public static function end_bulk_scan() {
		self::$bulk_scan_active = false;
	}

	/**
	 * Handle meta updates for tracked keys.
	 *
	 * @param int    $meta_id   Meta ID.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function handle_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		if ( ! self::is_tracked_meta( $meta_key ) ) {
			return;
		}

		$post = get_post( $object_id );
		if ( ! $post ) {
			return;
		}

		if ( 'attachment' === $post->post_type ) {
			if ( self::is_media_meta( $meta_key ) ) {
				if ( self::$bulk_scan_active ) {
					return;
				}
				self::sync_media_state( $object_id );
			}
			return;
		}

		if ( self::is_content_meta( $meta_key ) ) {
			self::sync_content_state( $object_id );
		}
	}

	/**
	 * Handle meta deletes for tracked keys.
	 *
	 * @param array  $meta_ids  Meta IDs.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function handle_meta_delete( $meta_ids, $object_id, $meta_key, $meta_value ) {
		unset( $meta_ids, $meta_value );

		if ( ! self::is_tracked_meta( $meta_key ) ) {
			return;
		}

		$post = get_post( $object_id );
		if ( ! $post ) {
			return;
		}

		if ( 'attachment' === $post->post_type ) {
			if ( self::is_media_meta( $meta_key ) ) {
				if ( self::$bulk_scan_active ) {
					return;
				}
				self::sync_media_state( $object_id );
			}
			return;
		}

		if ( self::is_content_meta( $meta_key ) ) {
			self::sync_content_state( $object_id );
		}
	}

	/**
	 * Log a media scan run.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $detection     Detection result.
	 * @param string $trigger       Scan trigger identifier.
	 */
	public static function log_media_scan( $attachment_id, array $detection, $trigger = 'auto' ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		if ( 'bulk_scan' === $trigger ) {
			self::buffer_bulk_media_scan( $attachment_id, $detection, $trigger );
			return;
		}

		global $wpdb;
		$euaiactready_media_scans_table = $wpdb->prefix . EUAIACTREADY_MEDIA_SCANS_TABLE;

		$confidence = isset( $detection['confidence'] ) ? (float) $detection['confidence'] : 0;
		$is_ai      = ! empty( $detection['is_ai'] ) ? 1 : 0;
		$indicators = isset( $detection['indicators'] ) ? $detection['indicators'] : array();
		$source     = isset( $detection['source'] ) ? (string) $detection['source'] : '';

		$data = array(
			'attachment_id'    => $attachment_id,
			'is_ai'            => $is_ai,
			'confidence_score' => $confidence,
			'indicators'       => wp_json_encode( $indicators ),
			'source'           => $source,
			'scan_trigger'     => sanitize_text_field( $trigger ),
			'detection_data'   => wp_json_encode( $detection ),
			'scanned_by'       => get_current_user_id() ? (int) get_current_user_id() : null,
			'scanned_at'       => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%d', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Persist scan history.
		$wpdb->insert( $euaiactready_media_scans_table, $data, $formats );

		self::sync_media_state( $attachment_id );
		self::increment_media_scan_count( $attachment_id );
	}

	/**
	 * Buffer bulk scan results in a single transient.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $detection     Detection result.
	 * @param string $trigger       Scan trigger identifier.
	 */
	private static function buffer_bulk_media_scan( $attachment_id, array $detection, $trigger ) {
		$buffer = get_transient( self::$bulk_scan_buffer_key );
		if ( ! is_array( $buffer ) ) {
			$buffer = array();
		}

		$confidence      = isset( $detection['confidence'] ) ? (float) $detection['confidence'] : 0;
		$is_ai           = ! empty( $detection['is_ai'] ) ? 1 : 0;
		$indicators      = isset( $detection['indicators'] ) ? $detection['indicators'] : array();
		$source          = isset( $detection['source'] ) ? (string) $detection['source'] : '';
		$now             = current_time( 'mysql' );
		$user_id         = get_current_user_id();
		$user_id         = $user_id ? (int) $user_id : 0;
		$marked_method   = $is_ai ? 'auto' : 'none';
		$detected_source = wp_json_encode(
			array(
				'confidence' => $confidence,
				'indicators' => $indicators,
				'source'     => $source,
			)
		);

		$buffer[ $attachment_id ] = array(
			'attachment_id'       => $attachment_id,
			'is_ai'               => $is_ai,
			'confidence_score'    => $confidence,
			'indicators'          => wp_json_encode( $indicators ),
			'source'              => $source,
			'scan_trigger'        => sanitize_text_field( $trigger ),
			'detection_data'      => wp_json_encode( $detection ),
			'scanned_by'          => $user_id,
			'scanned_at'          => $now,
			'ai_generated'        => $is_ai,
			'ai_marked_method'    => $marked_method,
			'ai_detection_source' => $detected_source,
			'user_id'             => $user_id,
			'last_scanned_at'     => $now,
			'scan_count'          => 1,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		set_transient( self::$bulk_scan_buffer_key, $buffer, 6 * HOUR_IN_SECONDS );
	}

	/**
	 * Flush buffered bulk scan results to the database.
	 */
	public static function flush_bulk_media_scan_buffer() {
		$buffer = get_transient( self::$bulk_scan_buffer_key );
		if ( ! is_array( $buffer ) || empty( $buffer ) ) {
			return 0;
		}

		global $wpdb;
		$euaiactready_media_scans_table = $wpdb->prefix . EUAIACTREADY_MEDIA_SCANS_TABLE;
		$euaiactready_media_state_table = $wpdb->prefix . EUAIACTREADY_MEDIA_STATE_TABLE;

		$scan_rows    = array_values( $buffer );
		$placeholders = array();
		$values       = array();

		foreach ( $scan_rows as $row ) {
			$placeholders[] = '( %d, %d, %f, %s, %s, %s, %s, %d, %s )';
			$values[]       = (int) $row['attachment_id'];
			$values[]       = (int) $row['is_ai'];
			$values[]       = (float) $row['confidence_score'];
			$values[]       = $row['indicators'];
			$values[]       = $row['source'];
			$values[]       = $row['scan_trigger'];
			$values[]       = $row['detection_data'];
			$values[]       = (int) $row['scanned_by'];
			$values[]       = $row['scanned_at'];
		}

		$scan_sql = 'INSERT INTO ' . $euaiactready_media_scans_table .
			"\n\t\t\t(attachment_id, is_ai, confidence_score, indicators, source, scan_trigger, detection_data, scanned_by, scanned_at)\n\t\t\tVALUES " . implode( ', ', $placeholders );
		$wpdb->query( $wpdb->prepare( $scan_sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Bulk insert scan history.

		$state_placeholders = array();
		$state_values       = array();
		foreach ( $scan_rows as $row ) {
			$state_placeholders[] = '( %s, %d, %d, %s, %s, %d, %s, %d, %d, %s, %s )';
			$state_values[]       = 'attachment';
			$state_values[]       = (int) $row['attachment_id'];
			$state_values[]       = (int) $row['ai_generated'];
			$state_values[]       = $row['ai_marked_method'];
			$state_values[]       = $row['ai_detection_source'];
			$state_values[]       = 0;
			$state_values[]       = $row['last_scanned_at'];
			$state_values[]       = (int) $row['scan_count'];
			$state_values[]       = (int) $row['user_id'];
			$state_values[]       = $row['created_at'];
			$state_values[]       = $row['updated_at'];
		}

		$state_sql = 'INSERT INTO ' . $euaiactready_media_state_table .
			"\n\t\t\t(item_type, item_id, ai_generated, ai_marked_method, ai_detection_source, ai_manually_unmarked, last_scanned_at, scan_count, user_id, created_at, updated_at)\n\t\t\tVALUES " . implode( ', ', $state_placeholders ) . '
			ON DUPLICATE KEY UPDATE
			ai_generated = VALUES(ai_generated),
			ai_marked_method = VALUES(ai_marked_method),
			ai_detection_source = VALUES(ai_detection_source),
			ai_manually_unmarked = VALUES(ai_manually_unmarked),
			last_scanned_at = VALUES(last_scanned_at),
			user_id = VALUES(user_id),
			updated_at = VALUES(updated_at),
			scan_count = scan_count + VALUES(scan_count)';
		$wpdb->query( $wpdb->prepare( $state_sql, $state_values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Bulk upsert media state.

		delete_transient( self::$bulk_scan_buffer_key );

		return count( $scan_rows );
	}

	/**
	 * Get the number of buffered bulk scan results.
	 *
	 * @return int
	 */
	public static function get_bulk_scan_buffer_count() {
		$buffer = get_transient( self::$bulk_scan_buffer_key );
		if ( ! is_array( $buffer ) ) {
			return 0;
		}

		return count( $buffer );
	}

	/**
	 * Clear buffered bulk scan results.
	 */
	public static function clear_bulk_scan_buffer() {
		delete_transient( self::$bulk_scan_buffer_key );
	}

	/**
	 * Sync content state row for a post/page/CPT.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function sync_content_state( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'attachment' === $post->post_type ) {
			return;
		}

		$item_type = $post->post_type;
		$user_id   = get_current_user_id();
		$user_id   = $user_id ? (int) $user_id : null;
		$now       = current_time( 'mysql' );

		$ai_content_exists = metadata_exists( 'post', $post_id, '_euaiactready_ai_content' );
		$ai_content        = $ai_content_exists ? get_post_meta( $post_id, '_euaiactready_ai_content', true ) : null;
		$ai_content_value  = null;
		if ( null !== $ai_content ) {
			$ai_content_value = ( '1' === (string) $ai_content ) ? 1 : 0;
		}

		$marked_date_exists = metadata_exists( 'post', $post_id, '_euaiactready_ai_content_marked_date' );
		$marked_date        = $marked_date_exists ? get_post_meta( $post_id, '_euaiactready_ai_content_marked_date', true ) : null;
		$marked_at          = null;
		if ( $marked_date && is_numeric( $marked_date ) ) {
			$marked_at = wp_date( 'Y-m-d H:i:s', (int) $marked_date );
		}

		global $wpdb;
		$euaiactready_content_state_table = $wpdb->prefix . EUAIACTREADY_CONTENT_STATE_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Small lookup for upsert.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, user_id FROM ' . $euaiactready_content_state_table . ' WHERE item_type = %s AND item_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is safely concatenated.
				$item_type,
				$post_id
			)
		);

		if ( null === $user_id && $existing && ! empty( $existing->user_id ) ) {
			$user_id = (int) $existing->user_id;
		}

		$data = array(
			'item_type'            => $item_type,
			'item_id'              => $post_id,
			'user_id'              => $user_id,
			'ai_content'           => $ai_content_value,
			'ai_content_marked_at' => $marked_at,
			'updated_at'           => $now,
		);

		$formats = array( '%s', '%d', '%d', '%d', '%s', '%s' );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Upsert content state.
			$wpdb->update(
				$euaiactready_content_state_table,
				$data,
				array( 'id' => (int) $existing->id ),
				$formats,
				array( '%d' )
			);
		} else {
			$data['created_at'] = $now;
			$formats            = array( '%s', '%d', '%d', '%d', '%s', '%s', '%s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Initial insert for content state.
			$wpdb->insert( $euaiactready_content_state_table, $data, $formats );
		}
	}

	/**
	 * Sync media state row for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function sync_media_state( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return;
		}

		$item_type = $post->post_type;
		$user_id   = get_current_user_id();
		$user_id   = $user_id ? (int) $user_id : null;
		$now       = current_time( 'mysql' );

		$ai_generated_exists = metadata_exists( 'post', $attachment_id, '_euaiactready_ai_generated' );
		$ai_generated        = $ai_generated_exists ? get_post_meta( $attachment_id, '_euaiactready_ai_generated', true ) : null;
		$ai_generated_value  = null;
		if ( null !== $ai_generated ) {
			$ai_generated_value = ( '1' === (string) $ai_generated ) ? 1 : 0;
		}

		$marked_method_exists = metadata_exists( 'post', $attachment_id, '_euaiactready_ai_marked_method' );
		$marked_method        = $marked_method_exists ? get_post_meta( $attachment_id, '_euaiactready_ai_marked_method', true ) : null;

		$detection_source_exists = metadata_exists( 'post', $attachment_id, '_euaiactready_ai_detection_source' );
		$detection_source        = $detection_source_exists ? get_post_meta( $attachment_id, '_euaiactready_ai_detection_source', true ) : null;

		$manually_unmarked_exists = metadata_exists( 'post', $attachment_id, '_euaiactready_ai_manually_unmarked' );
		$manually_unmarked        = $manually_unmarked_exists ? get_post_meta( $attachment_id, '_euaiactready_ai_manually_unmarked', true ) : null;
		$manually_unmarked_value  = null;
		if ( null !== $manually_unmarked ) {
			$manually_unmarked_value = ( '1' === (string) $manually_unmarked ) ? 1 : 0;
		}

		global $wpdb;
		$euaiactready_media_state_table = $wpdb->prefix . EUAIACTREADY_MEDIA_STATE_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Small lookup for upsert.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, user_id FROM ' . $euaiactready_media_state_table . ' WHERE item_type = %s AND item_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is safely concatenated.
				$item_type,
				$attachment_id
			)
		);

		if ( null === $user_id && $existing && ! empty( $existing->user_id ) ) {
			$user_id = (int) $existing->user_id;
		}

		$data = array(
			'item_type'            => $item_type,
			'item_id'              => $attachment_id,
			'ai_generated'         => $ai_generated_value,
			'ai_marked_method'     => $marked_method,
			'ai_detection_source'  => $detection_source,
			'ai_manually_unmarked' => $manually_unmarked_value,
			'user_id'              => $user_id,
			'updated_at'           => $now,
		);

		$formats = array( '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s' );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Upsert media state.
			$wpdb->update(
				$euaiactready_media_state_table,
				$data,
				array( 'id' => (int) $existing->id ),
				$formats,
				array( '%d' )
			);
		} else {
			$data['created_at']      = $now;
			$data['scan_count']      = 0;
			$data['last_scanned_at'] = null;
			$formats                 = array( '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Initial insert for media state.
			$wpdb->insert( $euaiactready_media_state_table, $data, $formats );
		}
	}

	/**
	 * Determine whether a meta key should be tracked.
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	private static function is_tracked_meta( $meta_key ) {
		return in_array( $meta_key, self::$tracked_meta_keys, true );
	}

	/**
	 * Check if a meta key belongs to content state.
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	private static function is_content_meta( $meta_key ) {
		return in_array( $meta_key, self::$content_meta_keys, true );
	}

	/**
	 * Check if a meta key belongs to media state.
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	private static function is_media_meta( $meta_key ) {
		return in_array( $meta_key, self::$media_meta_keys, true );
	}

	/**
	 * Increment scan count and update last scanned timestamp for media.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private static function increment_media_scan_count( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		global $wpdb;
		$euaiactready_media_state_table = $wpdb->prefix . EUAIACTREADY_MEDIA_STATE_TABLE;
		$now                            = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Increment scan stats.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $euaiactready_media_state_table . ' SET scan_count = scan_count + 1, last_scanned_at = %s, updated_at = %s WHERE item_type = %s AND item_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is safely concatenated.
				$now,
				$now,
				'attachment',
				$attachment_id
			)
		);
	}

	/**
	 * Synchronize all AI assets between post meta and custom tables.
	 *
	 * Finds items with AI meta but no record in custom tables and syncs them.
	 *
	 * @return int Number of newly synchronized items.
	 */
	public static function sync_all_ai_assets() {
		$meta_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for this logic.
			'meta_query'     => array(
				array(
					'key'     => '_euaiactready_ai_generated',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for this logic.
		$meta_query = new WP_Query( $meta_args );
		$meta_ids   = $meta_query->posts;
		wp_reset_postdata();

		if ( empty( $meta_ids ) ) {
			return 0;
		}

		global $wpdb;
		$euaiactready_media_state_table = $wpdb->prefix . EUAIACTREADY_MEDIA_STATE_TABLE;
		$euaiactready_media_scans_table = $wpdb->prefix . EUAIACTREADY_MEDIA_SCANS_TABLE;

		$query_ai_generated = "SELECT item_id FROM {$euaiactready_media_state_table} WHERE item_type = %s AND ai_generated = %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching not applicable.
		$db_ids = $wpdb->get_col( $wpdb->prepare( $query_ai_generated, 'attachment', 1 ) );

		$missing_ids = array_diff( $meta_ids, (array) $db_ids );

		if ( empty( $missing_ids ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $missing_ids as $id ) {
			// Sync media state.
			self::sync_media_state( $id );

			// Check if scan record exists.
			$query_has_scan = "SELECT id FROM {$euaiactready_media_scans_table} WHERE attachment_id = %d LIMIT 1";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query, caching not applicable.
			$has_scan = $wpdb->get_var( $wpdb->prepare( $query_has_scan, $id ) );

			if ( ! $has_scan ) {
				$detection_source = get_post_meta( $id, '_euaiactready_ai_detection_source', true );
				$detection        = json_decode( $detection_source, true );

				if ( ! is_array( $detection ) ) {
					$detection = array(
						'is_ai'      => true,
						'confidence' => 1.0,
						'indicators' => array( 'sync' ),
						'source'     => __( 'Synchronized Record', 'eu-ai-act-ready' ),
					);
				}
				self::log_media_scan( $id, $detection, 'sync' );
			}
			++$count;
		}

		return $count;
	}
}
