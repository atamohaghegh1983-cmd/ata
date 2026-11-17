<?php
/**
 * تکلیف آنلاین: سؤالات، پاسخ‌ها، ارسال تصویر
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Online_HW_DB {

	const TABLE_QUESTIONS  = 'hs_hw_questions';
	const TABLE_SUBMISSIONS = 'hs_hw_submissions';

	public static function questions_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_QUESTIONS;
	}

	public static function submissions_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUBMISSIONS;
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$q = self::questions_table();
		$s = self::submissions_table();

		$sql1 = "CREATE TABLE {$q} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			homework_id bigint(20) unsigned NOT NULL,
			question_type varchar(40) NOT NULL DEFAULT 'multiple_choice',
			prompt text NOT NULL,
			options_json text DEFAULT NULL,
			correct_answer varchar(500) DEFAULT NULL,
			points int(11) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			image_url varchar(500) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY homework_id (homework_id)
		) {$charset};";

		$sql2 = "CREATE TABLE {$s} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			homework_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned NOT NULL,
			answers_json longtext DEFAULT NULL,
			auto_score decimal(6,2) DEFAULT NULL,
			max_score decimal(6,2) DEFAULT NULL,
			image_url varchar(500) DEFAULT NULL,
			teacher_note text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'submitted',
			submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY hw_student (homework_id, student_id),
			KEY student_id (student_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );

		// ستون mode روی جدول تکالیف (در صورت نبود)
		$hw = HS_Homework_DB::hw_table();
		$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$hw} LIKE %s", 'mode' ) );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$hw} ADD COLUMN mode varchar(20) NOT NULL DEFAULT 'simple' AFTER subject" );
		}
	}

	public static function question_types() {
		return array(
			'multiple_choice' => 'چندگزینه‌ای',
			'true_false'      => 'درست / نادرست',
			'fill_blank'      => 'جای خالی',
			'image_choice'    => 'انتخاب تصویر',
			'open'            => 'تشریحی (بررسی معلم)',
		);
	}

	/* ---------- سؤالات ---------- */

	public static function add_question( $homework_id, $data ) {
		global $wpdb;
		$wpdb->insert(
			self::questions_table(),
			array(
				'homework_id'    => absint( $homework_id ),
				'question_type'  => sanitize_key( $data['question_type'] ?? 'multiple_choice' ),
				'prompt'         => wp_kses_post( $data['prompt'] ?? '' ),
				'options_json'   => isset( $data['options'] ) ? wp_json_encode( $data['options'], JSON_UNESCAPED_UNICODE ) : null,
				'correct_answer' => isset( $data['correct_answer'] ) ? sanitize_text_field( $data['correct_answer'] ) : '',
				'points'         => isset( $data['points'] ) ? absint( $data['points'] ) : 1,
				'sort_order'     => isset( $data['sort_order'] ) ? absint( $data['sort_order'] ) : 0,
				'image_url'      => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : '',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_questions( $homework_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::questions_table() .
			' WHERE homework_id = %d ORDER BY sort_order ASC, id ASC',
			absint( $homework_id )
		), ARRAY_A );

		foreach ( $rows as &$r ) {
			$r['options'] = $r['options_json'] ? json_decode( $r['options_json'], true ) : array();
			if ( ! is_array( $r['options'] ) ) {
				$r['options'] = array();
			}
		}
		return $rows;
	}

	public static function delete_questions( $homework_id ) {
		global $wpdb;
		return $wpdb->delete( self::questions_table(), array( 'homework_id' => absint( $homework_id ) ), array( '%d' ) );
	}

	public static function set_questions( $homework_id, $questions ) {
		self::delete_questions( $homework_id );
		$order = 0;
		$ids = array();
		foreach ( $questions as $q ) {
			$q['sort_order'] = $order++;
			$ids[] = self::add_question( $homework_id, $q );
		}
		return $ids;
	}

	public static function max_score( $homework_id ) {
		global $wpdb;
		$total = $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(points), 0) FROM ' . self::questions_table() . ' WHERE homework_id = %d',
			absint( $homework_id )
		) );
		return (float) $total;
	}

	/* ---------- ارسال پاسخ ---------- */

	/**
	 * تصحیح خودکار
	 * $answers = [ question_id => answer_string ]
	 */
	public static function grade( $homework_id, $answers ) {
		$questions = self::get_questions( $homework_id );
		$score = 0;
		$max = 0;
		$details = array();

		foreach ( $questions as $q ) {
			$qid = (int) $q['id'];
			$pts = (int) $q['points'];
			$max += $pts;
			$student_ans = isset( $answers[ $qid ] ) ? trim( (string) $answers[ $qid ] ) : '';
			$correct = trim( (string) $q['correct_answer'] );
			$is_correct = null;

			if ( $q['question_type'] === 'open' ) {
				$is_correct = null; // نیاز به بررسی معلم
			} elseif ( $q['question_type'] === 'fill_blank' ) {
				$is_correct = ( mb_strtolower( $student_ans ) === mb_strtolower( $correct ) );
			} else {
				$is_correct = ( $student_ans === $correct );
			}

			if ( $is_correct === true ) {
				$score += $pts;
			}

			$details[ $qid ] = array(
				'answer'     => $student_ans,
				'correct'    => $is_correct,
				'points'     => $is_correct === true ? $pts : 0,
				'max_points' => $pts,
			);
		}

		return array(
			'score'   => $score,
			'max'     => $max,
			'details' => $details,
		);
	}

	public static function submit( $homework_id, $student_id, $answers, $image_url = '' ) {
		global $wpdb;
		$graded = self::grade( $homework_id, $answers );

		$table = self::submissions_table();
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE homework_id = %d AND student_id = %d",
			absint( $homework_id ),
			absint( $student_id )
		) );

		$data = array(
			'homework_id'  => absint( $homework_id ),
			'student_id'   => absint( $student_id ),
			'answers_json' => wp_json_encode( array(
				'answers' => $answers,
				'details' => $graded['details'],
			), JSON_UNESCAPED_UNICODE ),
			'auto_score'   => $graded['score'],
			'max_score'    => $graded['max'],
			'image_url'    => $image_url ? esc_url_raw( $image_url ) : '',
			'status'       => 'submitted',
			'submitted_at' => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => $existing ) );
			$id = (int) $existing;
		} else {
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		// به‌روزرسانی وضعیت در جدول status تکلیف
		if ( class_exists( 'HS_Homework_DB' ) ) {
			HS_Homework_DB::set_status( $homework_id, $student_id, 'done', 'ارسال آنلاین — نمره ' . $graded['score'] . '/' . $graded['max'] );
		}

		return array(
			'id'    => $id,
			'score' => $graded['score'],
			'max'   => $graded['max'],
		);
	}

	public static function get_submission( $homework_id, $student_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::submissions_table() .
			' WHERE homework_id = %d AND student_id = %d',
			absint( $homework_id ),
			absint( $student_id )
		), ARRAY_A );
		if ( $row && $row['answers_json'] ) {
			$row['parsed'] = json_decode( $row['answers_json'], true );
		}
		return $row;
	}

	public static function get_submissions( $homework_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::submissions_table() .
			' WHERE homework_id = %d ORDER BY submitted_at DESC',
			absint( $homework_id )
		), ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['parsed'] = $r['answers_json'] ? json_decode( $r['answers_json'], true ) : null;
		}
		return $rows;
	}

	/**
	 * دفتر تکالیف یک دانش‌آموز در یک کلاس
	 */
	public static function student_notebook( $class_id, $student_id ) {
		$homeworks = HS_Homework_DB::get_class_homework( $class_id, 40 );
		$items = array();
		foreach ( $homeworks as $hw ) {
			$sub = self::get_submission( $hw['id'], $student_id );
			$status_row = null;
			$statuses = HS_Homework_DB::get_statuses( $hw['id'] );
			if ( isset( $statuses[ $student_id ] ) ) {
				$status_row = $statuses[ $student_id ];
			}
			$items[] = array(
				'homework'   => $hw,
				'submission' => $sub,
				'status'     => $status_row,
				'questions'  => (int) count( self::get_questions( $hw['id'] ) ),
			);
		}
		return $items;
	}
}
