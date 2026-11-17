<?php
/**
 * لایه دیتابیس تکالیف
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Homework_DB {

	const TABLE_HW      = 'hs_homework';
	const TABLE_STATUS  = 'hs_homework_status';

	public static function hw_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_HW;
	}

	public static function status_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_STATUS;
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$hw = self::hw_table();
		$st = self::status_table();

		$sql1 = "CREATE TABLE {$hw} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			subject varchar(120) DEFAULT '',
			due_date date DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY due_date (due_date)
		) {$charset};";

		$sql2 = "CREATE TABLE {$st} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			homework_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			feedback text DEFAULT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY hw_student (homework_id, student_id),
			KEY student_id (student_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	/** وضعیت‌های مجاز */
	public static function statuses() {
		return array(
			'pending'   => array( 'label' => 'در انتظار', 'color' => '#94A3B8' ),
			'done'      => array( 'label' => 'انجام‌شده', 'color' => '#10B981' ),
			'partial'   => array( 'label' => 'ناقص',     'color' => '#F59E0B' ),
			'missing'   => array( 'label' => 'انجام‌نشده', 'color' => '#EF4444' ),
		);
	}

	public static function create( $class_id, $title, $description = '', $subject = '', $due_date = null ) {
		global $wpdb;
		$wpdb->insert(
			self::hw_table(),
			array(
				'class_id'     => absint( $class_id ),
				'title'        => sanitize_text_field( $title ),
				'description'  => sanitize_textarea_field( $description ),
				'subject'      => sanitize_text_field( $subject ),
				'due_date'     => $due_date ? sanitize_text_field( $due_date ) : null,
				'created_by'   => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::hw_table() . ' WHERE id = %d', absint( $id ) ),
			ARRAY_A
		);
	}

	public static function get_class_homework( $class_id, $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::hw_table() .
			' WHERE class_id = %d ORDER BY due_date IS NULL, due_date DESC, id DESC LIMIT %d',
			absint( $class_id ),
			absint( $limit )
		), ARRAY_A );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::status_table(), array( 'homework_id' => absint( $id ) ), array( '%d' ) );
		return $wpdb->delete( self::hw_table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function set_status( $homework_id, $student_id, $status, $feedback = '' ) {
		global $wpdb;
		$table = self::status_table();
		$allowed = array_keys( self::statuses() );
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'pending';
		}

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE homework_id = %d AND student_id = %d",
			absint( $homework_id ),
			absint( $student_id )
		) );

		$data = array(
			'homework_id' => absint( $homework_id ),
			'student_id'  => absint( $student_id ),
			'status'      => $status,
			'feedback'    => sanitize_textarea_field( $feedback ),
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing ), array( '%d', '%d', '%s', '%s' ), array( '%d' ) );
			return (int) $existing;
		}
		$wpdb->insert( $table, $data, array( '%d', '%d', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	public static function set_status_batch( $homework_id, $records ) {
		$count = 0;
		foreach ( $records as $student_id => $data ) {
			$status   = isset( $data['status'] ) ? $data['status'] : 'pending';
			$feedback = isset( $data['feedback'] ) ? $data['feedback'] : '';
			self::set_status( $homework_id, $student_id, $status, $feedback );
			$count++;
		}
		return $count;
	}

	/** [ student_id => row ] */
	public static function get_statuses( $homework_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::status_table() . ' WHERE homework_id = %d',
			absint( $homework_id )
		), ARRAY_A );
		$out = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$out[ (int) $row['student_id'] ] = $row;
			}
		}
		return $out;
	}

	public static function get_summary( $homework_id ) {
		$statuses = self::get_statuses( $homework_id );
		$counts = array( 'pending' => 0, 'done' => 0, 'partial' => 0, 'missing' => 0 );
		foreach ( $statuses as $s ) {
			$key = $s['status'];
			if ( isset( $counts[ $key ] ) ) {
				$counts[ $key ]++;
			}
		}
		return $counts;
	}
}
