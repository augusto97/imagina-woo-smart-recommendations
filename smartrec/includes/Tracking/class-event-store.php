<?php
/**
 * Database operations for events.
 *
 * @package SmartRec\Tracking
 */

namespace SmartRec\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Class EventStore
 *
 * Handles buffered event storage with deduplication.
 */
class EventStore {

	/**
	 * Event buffer.
	 *
	 * @var array
	 */
	private static $buffer = array();

	/**
	 * Whether shutdown hook is registered.
	 *
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * Valid event types.
	 *
	 * @var array
	 */
	const VALID_EVENT_TYPES = array( 'view', 'cart_add', 'cart_remove', 'purchase', 'click', 'wishlist' );

	/**
	 * Add an event to the buffer.
	 *
	 * @param array $event Event data.
	 * @return bool
	 */
	public function add_event( array $event ) {
		if ( ! $this->validate_event( $event ) ) {
			return false;
		}

		// Check for duplicates within 5-minute window.
		if ( $this->is_duplicate( $event ) ) {
			return false;
		}

		$event = apply_filters( 'smartrec_track_event_data', $event, $event['event_type'] );

		self::$buffer[] = $event;

		if ( ! self::$shutdown_registered ) {
			add_action( 'shutdown', array( $this, 'flush_buffer' ) );
			self::$shutdown_registered = true;
		}

		return true;
	}

	/**
	 * Flush the event buffer to the database.
	 *
	 * @return int Number of events inserted.
	 */
	public function flush_buffer() {
		if ( empty( self::$buffer ) ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'smartrec_events';

		$values      = array();
		$placeholders = array();

		foreach ( self::$buffer as $event ) {
			$placeholders[] = '(%s, %d, %s, %d, %d, %d, %s, %s)';
			$values[]       = $event['session_id'];
			$values[]       = $event['user_id'] ?? 0;
			$values[]       = $event['event_type'];
			$values[]       = $event['product_id'];
			$values[]       = $event['source_product_id'] ?? 0;
			$values[]       = $event['quantity'] ?? 1;
			$values[]       = $event['context'] ?? '';
			$values[]       = $event['created_at'] ?? current_time( 'mysql' );
		}

		$placeholder_string = implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (session_id, user_id, event_type, product_id, source_product_id, quantity, context, created_at)
				VALUES {$placeholder_string}",
				$values
			)
		);

		$count        = count( self::$buffer );
		self::$buffer = array();

		return false !== $result ? $count : 0;
	}

	/**
	 * Record an event immediately (bypass buffer).
	 *
	 * @param array $event Event data.
	 * @return int|false Inserted ID or false.
	 */
	public function record_event( array $event ) {
		if ( ! $this->validate_event( $event ) ) {
			return false;
		}

		if ( $this->is_duplicate( $event ) ) {
			return false;
		}

		$event = apply_filters( 'smartrec_track_event_data', $event, $event['event_type'] );

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'smartrec_events',
			array(
				'session_id'        => $event['session_id'],
				'user_id'           => $event['user_id'] ?? 0,
				'event_type'        => $event['event_type'],
				'product_id'        => $event['product_id'],
				'source_product_id' => $event['source_product_id'] ?? 0,
				'quantity'          => $event['quantity'] ?? 1,
				'context'           => $event['context'] ?? '',
				'created_at'        => $event['created_at'] ?? current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return false !== $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get events for a session.
	 *
	 * @param string $session_id Session ID.
	 * @param string $event_type Optional event type filter.
	 * @param int    $limit      Max results.
	 * @return array
	 */
	public function get_session_events( $session_id, $event_type = '', $limit = 50 ) {
		global $wpdb;

		$where = $wpdb->prepare( 'WHERE session_id = %s', $session_id );
		if ( ! empty( $event_type ) ) {
			$where .= $wpdb->prepare( ' AND event_type = %s', $event_type );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}smartrec_events {$where} ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get events for a user.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $event_type Optional event type filter.
	 * @param int    $limit      Max results.
	 * @return array
	 */
	public function get_user_events( $user_id, $event_type = '', $limit = 50 ) {
		global $wpdb;

		$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );
		if ( ! empty( $event_type ) ) {
			$where .= $wpdb->prepare( ' AND event_type = %s', $event_type );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}smartrec_events {$where} ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Check if event is a duplicate within 5-minute window.
	 *
	 * @param array $event Event data.
	 * @return bool
	 */
	private function is_duplicate( array $event ) {
		global $wpdb;

		$five_minutes_ago = gmdate( 'Y-m-d H:i:s', time() - 300 );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_events
				WHERE session_id = %s AND event_type = %s AND product_id = %d AND created_at > %s",
				$event['session_id'],
				$event['event_type'],
				$event['product_id'],
				$five_minutes_ago
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Validate event data.
	 *
	 * @param array $event Event data.
	 * @return bool
	 */
	private function validate_event( array $event ) {
		if ( empty( $event['session_id'] ) || empty( $event['event_type'] ) || empty( $event['product_id'] ) ) {
			return false;
		}

		if ( ! in_array( $event['event_type'], self::VALID_EVENT_TYPES, true ) ) {
			return false;
		}

		if ( (int) $event['product_id'] <= 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get event count for analytics.
	 *
	 * @param string $event_type Event type.
	 * @param string $since      Date string.
	 * @return int
	 */
	public function get_event_count( $event_type = '', $since = '' ) {
		global $wpdb;

		$where = 'WHERE 1=1';
		$args  = array();

		if ( ! empty( $event_type ) ) {
			$where .= ' AND event_type = %s';
			$args[] = $event_type;
		}

		if ( ! empty( $since ) ) {
			$where .= ' AND created_at >= %s';
			$args[] = $since;
		}

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_events {$where}",
					$args
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_events" );
	}
}
