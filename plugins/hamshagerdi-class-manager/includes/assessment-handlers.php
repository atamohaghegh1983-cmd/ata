<?php
/**
 * هندلرهای AJAX ارزیابی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Assessment_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_create_assessment', array( __CLASS__, 'create_assessment' ) );
		add_action( 'wp_ajax_hs_save_assessment_results', array( __CLASS__, 'save_results' ) );
		add_action( 'wp_ajax_hs_delete_assessment', array( __CLASS__, 'delete_assessment' ) );
		add_action( 'wp_ajax_hs_get_assessment', array( __CLASS__, 'get_assessment' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'لطفاً وارد شوید.' ) );
		}
	}

	public static function create_assessment() {
		self::verify();

		$class_id   = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$title      = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$subject    = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$scale_type = isset( $_POST['scale_type'] ) ? sanitize_key( $_POST['scale_type'] ) : 'descriptive';
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : current_time( 'Y-m-d' );
		$notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'عنوان ارزیابی الزامی است.' ) );
		}

		$id = HS_Assessment_DB::create_assessment( $class_id, $title, $subject, $scale_type, $date, $notes );

		wp_send_json_success( array(
			'message'       => 'ارزیابی ساخته شد.',
			'assessment_id' => $id,
			'redirect'      => add_query_arg( array(
				'class_id'      => $class_id,
				'assessment_id' => $id,
			), home_url( '/panel/assessment/' ) ),
		) );
	}

	public static function save_results() {
		self::verify();

		$assessment_id = isset( $_POST['assessment_id'] ) ? absint( $_POST['assessment_id'] ) : 0;
		$results       = isset( $_POST['results'] ) ? $_POST['results'] : array();

		$assessment = HS_Assessment_DB::get_assessment( $assessment_id );
		if ( ! $assessment || ! HS_Roles::can_manage_class( $assessment['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		if ( ! is_array( $results ) || empty( $results ) ) {
			wp_send_json_error( array( 'message' => 'هیچ نتیجه‌ای ارسال نشده.' ) );
		}

		$class_students = get_post_meta( $assessment['class_id'], '_hs_students', true );
		if ( ! is_array( $class_students ) ) {
			$class_students = array();
		}
		$class_students = array_map( 'absint', $class_students );

		$clean = array();
		foreach ( $results as $sid => $data ) {
			$sid = absint( $sid );
			if ( ! in_array( $sid, $class_students, true ) ) {
				continue;
			}
			$clean[ $sid ] = array(
				'level_key' => isset( $data['level_key'] ) ? sanitize_key( $data['level_key'] ) : null,
				'score'     => isset( $data['score'] ) ? $data['score'] : null,
				'comment'   => isset( $data['comment'] ) ? sanitize_textarea_field( wp_unslash( $data['comment'] ) ) : '',
			);
		}

		$count = HS_Assessment_DB::save_results_batch( $assessment_id, $clean );
		$summary = HS_Assessment_DB::get_summary( $assessment_id );

		wp_send_json_success( array(
			'message' => sprintf( 'نتایج %d دانش‌آموز ذخیره شد.', $count ),
			'count'   => $count,
			'summary' => $summary,
		) );
	}

	public static function delete_assessment() {
		self::verify();

		$id = isset( $_POST['assessment_id'] ) ? absint( $_POST['assessment_id'] ) : 0;
		$assessment = HS_Assessment_DB::get_assessment( $id );
		if ( ! $assessment || ! HS_Roles::can_manage_class( $assessment['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		HS_Assessment_DB::delete_assessment( $id );
		wp_send_json_success( array( 'message' => 'ارزیابی حذف شد.' ) );
	}

	public static function get_assessment() {
		self::verify();

		$id = isset( $_POST['assessment_id'] ) ? absint( $_POST['assessment_id'] ) : 0;
		$assessment = HS_Assessment_DB::get_assessment( $id );
		if ( ! $assessment || ! HS_Roles::can_manage_class( $assessment['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$results = HS_Assessment_DB::get_results( $id );
		$summary = HS_Assessment_DB::get_summary( $id );

		wp_send_json_success( array(
			'assessment' => $assessment,
			'results'    => $results,
			'summary'    => $summary,
		) );
	}
}

HS_Assessment_Handlers::init();
