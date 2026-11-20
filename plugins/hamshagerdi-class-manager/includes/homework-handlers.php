<?php
/**
 * هندلرهای AJAX تکالیف
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Homework_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_create_homework', array( __CLASS__, 'create' ) );
		add_action( 'wp_ajax_hs_save_homework_status', array( __CLASS__, 'save_status' ) );
		add_action( 'wp_ajax_hs_delete_homework', array( __CLASS__, 'delete' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'لطفاً وارد شوید.' ) );
		}
	}

	public static function create() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$desc     = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$due      = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'عنوان تکلیف الزامی است.' ) );
		}

		$id = HS_Homework_DB::create( $class_id, $title, $desc, $subject, $due ?: null );

		wp_send_json_success( array(
			'message'     => 'تکلیف ثبت شد.',
			'homework_id' => $id,
			'redirect'    => add_query_arg( array(
				'class_id'    => $class_id,
				'homework_id' => $id,
			), home_url( '/panel/homework/' ) ),
		) );
	}

	public static function save_status() {
		self::verify();

		$homework_id = isset( $_POST['homework_id'] ) ? absint( $_POST['homework_id'] ) : 0;
		$records     = isset( $_POST['records'] ) ? $_POST['records'] : array();

		$hw = HS_Homework_DB::get( $homework_id );
		if ( ! $hw || ! HS_Roles::can_manage_class( $hw['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$class_students = get_post_meta( $hw['class_id'], '_hs_students', true );
		if ( ! is_array( $class_students ) ) {
			$class_students = array();
		}
		$class_students = array_map( 'absint', $class_students );

		$clean = array();
		if ( is_array( $records ) ) {
			foreach ( $records as $sid => $data ) {
				$sid = absint( $sid );
				if ( ! in_array( $sid, $class_students, true ) ) {
					continue;
				}
				$clean[ $sid ] = array(
					'status'   => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'pending',
					'feedback' => isset( $data['feedback'] ) ? sanitize_textarea_field( wp_unslash( $data['feedback'] ) ) : '',
				);
			}
		}

		$count   = HS_Homework_DB::set_status_batch( $homework_id, $clean );
		$summary = HS_Homework_DB::get_summary( $homework_id );

		wp_send_json_success( array(
			'message' => sprintf( 'وضعیت %d دانش‌آموز ذخیره شد.', $count ),
			'count'   => $count,
			'summary' => $summary,
		) );
	}

	public static function delete() {
		self::verify();

		$id = isset( $_POST['homework_id'] ) ? absint( $_POST['homework_id'] ) : 0;
		$hw = HS_Homework_DB::get( $id );
		if ( ! $hw || ! HS_Roles::can_manage_class( $hw['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		HS_Homework_DB::delete( $id );
		wp_send_json_success( array( 'message' => 'تکلیف حذف شد.' ) );
	}
}

HS_Homework_Handlers::init();
