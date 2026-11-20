<?php
/**
 * لایه دیتابیس ارزیابی گروهی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Assessment_DB {

	const TABLE_ASSESSMENTS = 'hs_assessments';
	const TABLE_RESULTS     = 'hs_assessment_results';

	public static function assessments_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_ASSESSMENTS;
	}

	public static function results_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_RESULTS;
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$a = self::assessments_table();
		$r = self::results_table();

		$sql1 = "CREATE TABLE {$a} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			subject varchar(120) DEFAULT '',
			scale_type varchar(20) NOT NULL DEFAULT 'descriptive',
			assess_date date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY assess_date (assess_date)
		) {$charset};";

		$sql2 = "CREATE TABLE {$r} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			assessment_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned NOT NULL,
			level_key varchar(40) DEFAULT NULL,
			score decimal(5,2) DEFAULT NULL,
			comment text DEFAULT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY assessment_student (assessment_id, student_id),
			KEY student_id (student_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	/**
	 * سطوح توصیفی پیش‌فرض (مقطع ابتدایی ایران)
	 */
	public static function descriptive_levels() {
		return array(
			'excellent' => array( 'label' => 'خیلی خوب', 'color' => '#10B981' ),
			'good'      => array( 'label' => 'خوب',       'color' => '#079E91' ),
			'acceptable'=> array( 'label' => 'قابل قبول', 'color' => '#F59E0B' ),
			'needs_work'=> array( 'label' => 'نیاز به تلاش', 'color' => '#EF4444' ),
			'absent'    => array( 'label' => 'غایب',      'color' => '#94A3B8' ),
		);
	}

	public static function create_assessment( $class_id, $title, $subject = '', $scale_type = 'descriptive', $date = null, $notes = '' ) {
		global $wpdb;
		if ( ! $date ) {
			$date = current_time( 'Y-m-d' );
		}
		$wpdb->insert(
			self::assessments_table(),
			array(
				'class_id'     => absint( $class_id ),
				'title'        => sanitize_text_field( $title ),
				'subject'      => sanitize_text_field( $subject ),
				'scale_type'   => in_array( $scale_type, array( 'descriptive', 'numeric' ), true ) ? $scale_type : 'descriptive',
				'assess_date'  => sanitize_text_field( $date ),
				'notes'        => sanitize_textarea_field( $notes ),
				'created_by'   => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_assessment( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::assessments_table() . ' WHERE id = %d', absint( $id ) ),
			ARRAY_A
		);
	}

	public static function get_class_assessments( $class_id, $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::assessments_table() .
			' WHERE class_id = %d ORDER BY assess_date DESC, id DESC LIMIT %d',
			absint( $class_id ),
			absint( $limit )
		), ARRAY_A );
	}

	public static function delete_assessment( $id ) {
		global $wpdb;
		$wpdb->delete( self::results_table(), array( 'assessment_id' => absint( $id ) ), array( '%d' ) );
		return $wpdb->delete( self::assessments_table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * ذخیره نتیجه یک دانش‌آموز
	 */
	public static function save_result( $assessment_id, $student_id, $level_key = null, $score = null, $comment = '' ) {
		global $wpdb;
		$table = self::results_table();
		$assessment_id = absint( $assessment_id );
		$student_id    = absint( $student_id );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE assessment_id = %d AND student_id = %d",
			$assessment_id, $student_id
		) );

		$data = array(
			'assessment_id' => $assessment_id,
			'student_id'    => $student_id,
			'level_key'     => $level_key ? sanitize_key( $level_key ) : null,
			'score'         => $score !== null && $score !== '' ? floatval( $score ) : null,
			'comment'       => sanitize_textarea_field( $comment ),
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing ), array( '%d', '%d', '%s', '%f', '%s' ), array( '%d' ) );
			return (int) $existing;
		}

		$wpdb->insert( $table, $data, array( '%d', '%d', '%s', '%f', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * ذخیره دسته‌ای نتایج
	 * $results = [ student_id => [ 'level_key' => ..., 'score' => ..., 'comment' => ... ], ... ]
	 */
	public static function save_results_batch( $assessment_id, $results ) {
		$count = 0;
		foreach ( $results as $student_id => $data ) {
			$level   = isset( $data['level_key'] ) ? $data['level_key'] : null;
			$score   = isset( $data['score'] ) ? $data['score'] : null;
			$comment = isset( $data['comment'] ) ? $data['comment'] : '';
			self::save_result( $assessment_id, $student_id, $level, $score, $comment );
			$count++;
		}
		return $count;
	}

	/**
	 * نتایج یک ارزیابی: [ student_id => row, ... ]
	 */
	public static function get_results( $assessment_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::results_table() . ' WHERE assessment_id = %d',
			absint( $assessment_id )
		), ARRAY_A );

		$out = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$out[ (int) $row['student_id'] ] = $row;
			}
		}
		return $out;
	}

	/**
	 * خلاصه آماری یک ارزیابی
	 */
	public static function get_summary( $assessment_id ) {
		$assessment = self::get_assessment( $assessment_id );
		if ( ! $assessment ) {
			return array();
		}
		$results = self::get_results( $assessment_id );

		if ( $assessment['scale_type'] === 'numeric' ) {
			$scores = array();
			foreach ( $results as $r ) {
				if ( $r['score'] !== null ) {
					$scores[] = (float) $r['score'];
				}
			}
			return array(
				'type'  => 'numeric',
				'count' => count( $scores ),
				'avg'   => $scores ? round( array_sum( $scores ) / count( $scores ), 1 ) : 0,
				'min'   => $scores ? min( $scores ) : 0,
				'max'   => $scores ? max( $scores ) : 0,
			);
		}

		$levels = self::descriptive_levels();
		$counts = array();
		foreach ( array_keys( $levels ) as $k ) {
			$counts[ $k ] = 0;
		}
		foreach ( $results as $r ) {
			$key = $r['level_key'];
			if ( $key && isset( $counts[ $key ] ) ) {
				$counts[ $key ]++;
			}
		}
		return array(
			'type'   => 'descriptive',
			'count'  => count( $results ),
			'levels' => $counts,
		);
	}
}
