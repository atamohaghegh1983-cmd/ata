<?php
/**
 * هندلرهای AJAX پورتفولیو
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Portfolio_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_add_portfolio_item', array( __CLASS__, 'add_item' ) );
		add_action( 'wp_ajax_hs_update_portfolio_status', array( __CLASS__, 'update_status' ) );
		add_action( 'wp_ajax_hs_delete_portfolio_item', array( __CLASS__, 'delete_item' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'لطفاً وارد شوید.' ) );
		}
	}

	public static function add_item() {
		self::verify();

		$class_id   = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$student_id = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;
		$title      = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$desc       = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$item_type  = isset( $_POST['item_type'] ) ? sanitize_key( $_POST['item_type'] ) : 'work';
		$media_url  = isset( $_POST['media_url'] ) ? esc_url_raw( wp_unslash( $_POST['media_url'] ) ) : '';
		$status     = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'approved';

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		if ( ! $student_id || empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'دانش‌آموز و عنوان الزامی است.' ) );
		}

		// اعتبارسنجی عضویت در کلاس
		$students = get_post_meta( $class_id, '_hs_students', true );
		if ( ! is_array( $students ) || ! in_array( $student_id, array_map( 'absint', $students ), true ) ) {
			wp_send_json_error( array( 'message' => 'این دانش‌آموز عضو کلاس نیست.' ) );
		}

		$types = array_keys( HS_Portfolio_DB::types() );
		if ( ! in_array( $item_type, $types, true ) ) {
			$item_type = 'work';
		}

		$id = HS_Portfolio_DB::add( array(
			'class_id'    => $class_id,
			'student_id'  => $student_id,
			'title'       => $title,
			'description' => $desc,
			'item_type'   => $item_type,
			'media_url'   => $media_url,
			'status'      => in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ? $status : 'approved',
		) );

		$user = get_userdata( $student_id );
		$types_map = HS_Portfolio_DB::types();

		wp_send_json_success( array(
			'message' => 'اثر به پورتفولیو اضافه شد.',
			'item'    => array(
				'id'          => $id,
				'title'       => $title,
				'description' => $desc,
				'item_type'   => $item_type,
				'type_label'  => isset( $types_map[ $item_type ] ) ? $types_map[ $item_type ]['label'] : $item_type,
				'type_icon'   => isset( $types_map[ $item_type ] ) ? $types_map[ $item_type ]['icon'] : '📎',
				'media_url'   => $media_url,
				'status'      => $status,
				'student_id'  => $student_id,
				'student_name'=> $user ? $user->display_name : '',
				'created_at'  => current_time( 'mysql' ),
			),
		) );
	}

	public static function update_status() {
		self::verify();

		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		$item     = HS_Portfolio_DB::get( $id );

		if ( ! $item || ! HS_Roles::can_manage_class( $item['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		HS_Portfolio_DB::update_status( $id, $status );
		wp_send_json_success( array(
			'message' => 'وضعیت به‌روز شد.',
			'status'  => $status,
		) );
	}

	public static function delete_item() {
		self::verify();

		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$item = HS_Portfolio_DB::get( $id );

		if ( ! $item || ! HS_Roles::can_manage_class( $item['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		HS_Portfolio_DB::delete( $id );
		wp_send_json_success( array( 'message' => 'اثر حذف شد.' ) );
	}
}

HS_Portfolio_Handlers::init();
