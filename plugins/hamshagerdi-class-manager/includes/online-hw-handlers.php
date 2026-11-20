<?php
/**
 * هندلرهای AJAX تکلیف آنلاین
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Online_HW_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_create_online_homework', array( __CLASS__, 'create_online' ) );
		add_action( 'wp_ajax_hs_submit_online_hw', array( __CLASS__, 'submit' ) );
		add_action( 'wp_ajax_hs_get_online_results', array( __CLASS__, 'get_results' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'لطفاً وارد شوید.' ) );
		}
	}

	/**
	 * ساخت تکلیف آنلاین با سؤالات
	 * questions: [{type, prompt, options[], correct, points}, ...]
	 */
	public static function create_online() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$due      = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';
		$desc     = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$questions = isset( $_POST['questions'] ) ? $_POST['questions'] : array();

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'عنوان الزامی است.' ) );
		}
		if ( ! is_array( $questions ) || count( $questions ) < 1 ) {
			wp_send_json_error( array( 'message' => 'حداقل یک سؤال لازم است.' ) );
		}

		$hw_id = HS_Homework_DB::create( $class_id, $title, $desc, $subject, $due ?: null );

		// mode = online
		global $wpdb;
		$wpdb->update(
			HS_Homework_DB::hw_table(),
			array( 'mode' => 'online' ),
			array( 'id' => $hw_id ),
			array( '%s' ),
			array( '%d' )
		);

		$clean_qs = array();
		foreach ( $questions as $q ) {
			if ( empty( $q['prompt'] ) ) {
				continue;
			}
			$opts = array();
			if ( ! empty( $q['options'] ) && is_array( $q['options'] ) ) {
				foreach ( $q['options'] as $o ) {
					$opts[] = sanitize_text_field( is_array( $o ) ? ( $o['text'] ?? $o['label'] ?? '' ) : $o );
				}
			}
			$clean_qs[] = array(
				'question_type'  => sanitize_key( $q['type'] ?? $q['question_type'] ?? 'multiple_choice' ),
				'prompt'         => sanitize_textarea_field( $q['prompt'] ),
				'options'        => $opts,
				'correct_answer' => sanitize_text_field( $q['correct'] ?? $q['correct_answer'] ?? '' ),
				'points'         => isset( $q['points'] ) ? absint( $q['points'] ) : 1,
				'image_url'      => isset( $q['image_url'] ) ? esc_url_raw( $q['image_url'] ) : '',
			);
		}

		HS_Online_HW_DB::set_questions( $hw_id, $clean_qs );

		wp_send_json_success( array(
			'message'     => 'تکلیف آنلاین با ' . count( $clean_qs ) . ' سؤال ساخته شد.',
			'homework_id' => $hw_id,
			'redirect'    => add_query_arg( array(
				'class_id'    => $class_id,
				'homework_id' => $hw_id,
			), home_url( '/panel/homework/' ) ),
		) );
	}

	/**
	 * ارسال پاسخ توسط دانش‌آموز (یا معلم به‌جای او)
	 */
	public static function submit() {
		self::verify();

		$homework_id = isset( $_POST['homework_id'] ) ? absint( $_POST['homework_id'] ) : 0;
		$student_id  = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : get_current_user_id();
		$answers     = isset( $_POST['answers'] ) ? $_POST['answers'] : array();
		$image_url   = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';

		$hw = HS_Homework_DB::get( $homework_id );
		if ( ! $hw ) {
			wp_send_json_error( array( 'message' => 'تکلیف یافت نشد.' ) );
		}

		$current = get_current_user_id();
		$can_as_teacher = HS_Roles::can_manage_class( $hw['class_id'] );
		$is_self = ( $current === $student_id );

		// دانش‌آموز فقط برای خودش؛ معلم می‌تواند برای هر دانش‌آموز کلاس
		if ( ! $is_self && ! $can_as_teacher ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		// عضویت در کلاس
		$students = get_post_meta( $hw['class_id'], '_hs_students', true );
		if ( ! is_array( $students ) || ! in_array( $student_id, array_map( 'absint', $students ), true ) ) {
			if ( ! $can_as_teacher ) {
				wp_send_json_error( array( 'message' => 'شما عضو این کلاس نیستید.' ) );
			}
		}

		$clean_answers = array();
		if ( is_array( $answers ) ) {
			foreach ( $answers as $qid => $ans ) {
				$clean_answers[ absint( $qid ) ] = sanitize_text_field( is_array( $ans ) ? '' : wp_unslash( $ans ) );
			}
		}

		$result = HS_Online_HW_DB::submit( $homework_id, $student_id, $clean_answers, $image_url );

		wp_send_json_success( array(
			'message' => sprintf( 'پاسخ ثبت شد. نمره خودکار: %s از %s', $result['score'], $result['max'] ),
			'score'   => $result['score'],
			'max'     => $result['max'],
		) );
	}

	public static function get_results() {
		self::verify();

		$homework_id = isset( $_POST['homework_id'] ) ? absint( $_POST['homework_id'] ) : 0;
		$hw = HS_Homework_DB::get( $homework_id );
		if ( ! $hw || ! HS_Roles::can_manage_class( $hw['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$subs = HS_Online_HW_DB::get_submissions( $homework_id );
		$rows = array();
		foreach ( $subs as $s ) {
			$user = get_userdata( $s['student_id'] );
			$rows[] = array(
				'student_id'   => (int) $s['student_id'],
				'name'         => $user ? $user->display_name : '',
				'score'        => (float) $s['auto_score'],
				'max'          => (float) $s['max_score'],
				'image_url'    => $s['image_url'],
				'submitted_at' => $s['submitted_at'],
				'status'       => $s['status'],
			);
		}

		wp_send_json_success( array(
			'rows'     => $rows,
			'max_score'=> HS_Online_HW_DB::max_score( $homework_id ),
			'count'    => count( $rows ),
		) );
	}
}

HS_Online_HW_Handlers::init();
