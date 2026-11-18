<?php
/**
 * تقویم مناسبتی مدرسه
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Calendar_DB {

	const TABLE = 'hs_calendar_events';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function create_table() {
		global $wpdb;
		$t = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			event_date date NOT NULL,
			event_type varchar(40) NOT NULL DEFAULT 'occasion',
			audience varchar(40) NOT NULL DEFAULT 'teachers',
			class_id bigint(20) unsigned DEFAULT 0,
			school_id bigint(20) unsigned DEFAULT 0,
			level varchar(20) NOT NULL DEFAULT '',
			end_date date DEFAULT NULL,
			remind_days int(11) NOT NULL DEFAULT 7,
			reminded tinyint(1) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_date (event_date),
			KEY reminded (reminded),
			KEY audience (audience)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function types() {
		return array(
			'academic'   => 'تقویم آموزشی',
			'cultural'   => 'پرورشی و مناسبتی',
			'occasion'   => 'مناسبت',
			'holiday'    => 'تعطیلی',
			'exam'       => 'آزمون',
			'meeting'    => 'جلسه',
			'ceremony'   => 'جشن / مراسم',
			'deadline'   => 'مهلت مهم',
			'other'      => 'سایر',
		);
	}

	public static function audiences() {
		return array(
			'teachers' => 'کادر و معلمان',
			'parents'  => 'والدین',
			'all'      => 'همه',
			'class'    => 'یک کلاس خاص',
		);
	}

	public static function insert( $data ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'title'       => sanitize_text_field( $data['title'] ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'event_date'  => sanitize_text_field( $data['event_date'] ),
				'event_type'  => sanitize_key( $data['event_type'] ?? 'occasion' ),
				'audience'    => sanitize_key( $data['audience'] ?? 'teachers' ),
				'class_id'    => absint( $data['class_id'] ?? 0 ),
				'school_id'   => absint( $data['school_id'] ?? 0 ),
				'level'       => sanitize_key( $data['level'] ?? '' ),
				'end_date'    => ! empty( $data['end_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['end_date'] ) ? $data['end_date'] : null,
				'remind_days' => isset( $data['remind_days'] ) ? absint( $data['remind_days'] ) : 7,
				'created_by'  => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$fields = array();
		$map = array(
			'title' => '%s', 'description' => '%s', 'event_date' => '%s',
			'event_type' => '%s', 'audience' => '%s', 'class_id' => '%d',
			'remind_days' => '%d', 'reminded' => '%d', 'level' => '%s', 'end_date' => '%s', 'school_id' => '%d',
		);
		$formats = array();
		foreach ( $map as $k => $f ) {
			if ( array_key_exists( $k, $data ) ) {
				$fields[ $k ] = $data[ $k ];
				$formats[] = $f;
			}
		}
		if ( empty( $fields ) ) {
			return false;
		}
		return $wpdb->update( self::table(), $fields, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			absint( $id )
		), ARRAY_A );
	}

	public static function upcoming( $limit = 30, $from = null ) {
		global $wpdb;
		$from = $from ?: current_time( 'Y-m-d' );
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . '
			 WHERE event_date >= %s
			 ORDER BY event_date ASC LIMIT %d',
			$from,
			absint( $limit )
		), ARRAY_A );
	}

	public static function in_range( $start, $end ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . '
			 WHERE event_date BETWEEN %s AND %s
			 ORDER BY event_date ASC',
			$start,
			$end
		), ARRAY_A );
	}

	/**
	 * رویدادهایی که باید امروز یادآوری شوند (event_date - remind_days == امروز)
	 */
	public static function due_for_reminder() {
		global $wpdb;
		$today = current_time( 'Y-m-d' );
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . '
			 WHERE reminded = 0
			   AND DATE_SUB(event_date, INTERVAL remind_days DAY) <= %s
			   AND event_date >= %s',
			$today,
			$today
		), ARRAY_A );
	}

	public static function mark_reminded( $id ) {
		return self::update( $id, array( 'reminded' => 1 ) );
	}
}
