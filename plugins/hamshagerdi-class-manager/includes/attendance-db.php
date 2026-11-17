<?php
/**
 * لایه دیتابیس حضور و غیاب
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Attendance_DB {

	const TABLE = 'hs_attendance';

	/**
	 * نام کامل جدول
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * ایجاد جدول هنگام فعال‌سازی
	 */
	public static function create_table() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned NOT NULL,
			att_date date NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'present',
			note text DEFAULT NULL,
			recorded_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY class_student_date (class_id, student_id, att_date),
			KEY class_date (class_id, att_date),
			KEY student_date (student_id, att_date)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * ذخیره یا به‌روزرسانی یک رکورد
	 */
	public static function save( $class_id, $student_id, $date, $status, $note = '', $recorded_by = null ) {
		global $wpdb;
		$table = self::table_name();

		$class_id   = absint( $class_id );
		$student_id = absint( $student_id );
		$date       = sanitize_text_field( $date );
		$status     = sanitize_key( $status );
		$note       = sanitize_textarea_field( $note );
		$recorded_by = $recorded_by ? absint( $recorded_by ) : get_current_user_id();

		$allowed = array( 'present', 'absent', 'late', 'excused' );
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'present';
		}

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE class_id = %d AND student_id = %d AND att_date = %s",
			$class_id, $student_id, $date
		) );

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'status'      => $status,
					'note'        => $note,
					'recorded_by' => $recorded_by,
				),
				array( 'id' => $existing ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);
			return (int) $existing;
		}

		$wpdb->insert(
			$table,
			array(
				'class_id'    => $class_id,
				'student_id'  => $student_id,
				'att_date'    => $date,
				'status'      => $status,
				'note'        => $note,
				'recorded_by' => $recorded_by,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * ذخیره دسته‌ای برای یک کلاس و یک روز
	 *
	 * @param int    $class_id
	 * @param string $date     Y-m-d
	 * @param array  $records  [ student_id => [ 'status' => ..., 'note' => ... ], ... ]
	 */
	public static function save_batch( $class_id, $date, $records ) {
		$saved = 0;
		foreach ( $records as $student_id => $data ) {
			$status = isset( $data['status'] ) ? $data['status'] : 'present';
			$note   = isset( $data['note'] ) ? $data['note'] : '';
			self::save( $class_id, $student_id, $date, $status, $note );
			$saved++;
		}
		return $saved;
	}

	/**
	 * دریافت حضور یک کلاس در یک روز
	 * خروجی: [ student_id => [ 'status' => ..., 'note' => ..., 'id' => ... ], ... ]
	 */
	public static function get_by_class_date( $class_id, $date ) {
		global $wpdb;
		$table = self::table_name();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, student_id, status, note FROM {$table}
			 WHERE class_id = %d AND att_date = %s",
			absint( $class_id ),
			sanitize_text_field( $date )
		), ARRAY_A );

		$result = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$result[ (int) $row['student_id'] ] = array(
					'id'     => (int) $row['id'],
					'status' => $row['status'],
					'note'   => $row['note'],
				);
			}
		}
		return $result;
	}

	/**
	 * خلاصه ماهانه یک کلاس
	 * خروجی: [ student_id => [ 'present' => n, 'absent' => n, 'late' => n, 'excused' => n ], ... ]
	 */
	public static function get_monthly_summary( $class_id, $year, $month ) {
		global $wpdb;
		$table = self::table_name();

		$start = sprintf( '%04d-%02d-01', $year, $month );
		$end   = date( 'Y-m-t', strtotime( $start ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT student_id, status, COUNT(*) as cnt
			 FROM {$table}
			 WHERE class_id = %d AND att_date BETWEEN %s AND %s
			 GROUP BY student_id, status",
			absint( $class_id ),
			$start,
			$end
		), ARRAY_A );

		$summary = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$sid = (int) $row['student_id'];
				if ( ! isset( $summary[ $sid ] ) ) {
					$summary[ $sid ] = array(
						'present' => 0,
						'absent'  => 0,
						'late'    => 0,
						'excused' => 0,
					);
				}
				$key = $row['status'];
				if ( isset( $summary[ $sid ][ $key ] ) ) {
					$summary[ $sid ][ $key ] = (int) $row['cnt'];
				}
			}
		}
		return $summary;
	}

	/**
	 * تعداد روزهای ثبت‌شده یک کلاس در ماه
	 */
	public static function count_recorded_days( $class_id, $year, $month ) {
		global $wpdb;
		$table = self::table_name();
		$start = sprintf( '%04d-%02d-01', $year, $month );
		$end   = date( 'Y-m-t', strtotime( $start ) );

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT att_date) FROM {$table}
			 WHERE class_id = %d AND att_date BETWEEN %s AND %s",
			absint( $class_id ),
			$start,
			$end
		) );
	}

	/**
	 * آیا برای این کلاس و این روز قبلاً ثبت شده؟
	 */
	public static function is_recorded( $class_id, $date ) {
		global $wpdb;
		$table = self::table_name();
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE class_id = %d AND att_date = %s",
			absint( $class_id ),
			sanitize_text_field( $date )
		) );
		return (int) $count > 0;
	}
}
