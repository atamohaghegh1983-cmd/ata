<?php
/**
 * هندلرهای AJAX سیستم امتیاز
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Points_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_award_points', array( __CLASS__, 'award_points' ) );
		add_action( 'wp_ajax_hs_get_leaderboard', array( __CLASS__, 'get_leaderboard' ) );
		add_action( 'wp_ajax_hs_get_student_points', array( __CLASS__, 'get_student_points' ) );
		add_action( 'wp_ajax_hs_add_behavior', array( __CLASS__, 'add_behavior' ) );
		add_action( 'wp_ajax_hs_delete_point', array( __CLASS__, 'delete_point' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'لطفاً وارد شوید.' ) );
		}
	}

	/**
	 * اعطای امتیاز به یک یا چند دانش‌آموز
	 */
	public static function award_points() {
		self::verify();

		$class_id    = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$behavior_id = isset( $_POST['behavior_id'] ) ? absint( $_POST['behavior_id'] ) : 0;
		$student_ids = isset( $_POST['student_ids'] ) ? (array) $_POST['student_ids'] : array();
		$note        = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		if ( ! $behavior_id ) {
			wp_send_json_error( array( 'message' => 'رفتار را انتخاب کنید.' ) );
		}

		$beh = HS_Points_DB::get_behavior( $behavior_id );
		if ( ! $beh ) {
			wp_send_json_error( array( 'message' => 'رفتار نامعتبر است.' ) );
		}

		$student_ids = array_map( 'absint', $student_ids );
		$student_ids = array_filter( $student_ids );
		if ( empty( $student_ids ) ) {
			wp_send_json_error( array( 'message' => 'حداقل یک دانش‌آموز انتخاب کنید.' ) );
		}

		// فقط دانش‌آموزان همین کلاس
		$class_students = get_post_meta( $class_id, '_hs_students', true );
		if ( ! is_array( $class_students ) ) {
			$class_students = array();
		}
		$class_students = array_map( 'absint', $class_students );
		$student_ids = array_intersect( $student_ids, $class_students );

		if ( empty( $student_ids ) ) {
			wp_send_json_error( array( 'message' => 'دانش‌آموز معتبری انتخاب نشده.' ) );
		}

		$count = HS_Points_DB::award_batch( $class_id, $student_ids, $behavior_id, $note );

		// مجموع‌های به‌روز
		$totals = array();
		foreach ( $student_ids as $sid ) {
			$totals[ $sid ] = HS_Points_DB::get_student_total( $class_id, $sid );
		}

		$sign = (int) $beh['points'] >= 0 ? '+' : '';
		wp_send_json_success( array(
			'message'  => sprintf(
				'%s %s (%s%d) برای %d دانش‌آموز ثبت شد.',
				$beh['icon'],
				$beh['name'],
				$sign,
				(int) $beh['points'],
				$count
			),
			'count'    => $count,
			'totals'   => $totals,
			'behavior' => array(
				'id'     => (int) $beh['id'],
				'name'   => $beh['name'],
				'icon'   => $beh['icon'],
				'points' => (int) $beh['points'],
			),
		) );
	}

	/**
	 * جدول رتبه‌بندی
	 */
	public static function get_leaderboard() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$students = HS_Class_Students::get_students( $class_id );
		$scores   = HS_Points_DB::get_class_leaderboard( $class_id );

		$rows = array();
		foreach ( $students as $user ) {
			$sid = $user->ID;
			$rows[] = array(
				'id'    => $sid,
				'name'  => $user->display_name,
				'total' => isset( $scores[ $sid ] ) ? $scores[ $sid ] : 0,
			);
		}

		// مرتب‌سازی نزولی
		usort( $rows, function( $a, $b ) {
			return $b['total'] - $a['total'];
		} );

		wp_send_json_success( array( 'rows' => $rows ) );
	}

	/**
	 * تاریخچه امتیاز یک دانش‌آموز
	 */
	public static function get_student_points() {
		self::verify();

		$class_id   = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$student_id = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;

		if ( ! $class_id || ! $student_id || ! HS_Roles::can_manage_class( $class_id ) || ! HS_Class_Students::is_member( $class_id, $student_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$history = HS_Points_DB::get_student_history( $class_id, $student_id, 40 );
		$total   = HS_Points_DB::get_student_total( $class_id, $student_id );
		$user    = get_userdata( $student_id );

		$items = array();
		foreach ( $history as $h ) {
			$items[] = array(
				'id'       => (int) $h['id'],
				'points'   => (int) $h['points'],
				'note'     => $h['note'],
				'behavior' => ( $h['behavior_icon'] ?: '⭐' ) . ' ' . ( $h['behavior_name'] ?: 'امتیاز' ),
				'date'     => $h['created_at'],
			);
		}

		wp_send_json_success( array(
			'name'    => $user ? $user->display_name : '',
			'total'   => $total,
			'history' => $items,
		) );
	}

	/**
	 * افزودن رفتار سفارشی
	 */
	public static function add_behavior() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$icon     = isset( $_POST['icon'] ) ? sanitize_text_field( wp_unslash( $_POST['icon'] ) ) : '⭐';
		$points   = isset( $_POST['points'] ) ? (int) $_POST['points'] : 1;

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => 'نام رفتار الزامی است.' ) );
		}

		$is_positive = $points >= 0 ? 1 : 0;
		$id = HS_Points_DB::add_behavior( $class_id, $name, $icon, $points, $is_positive );

		wp_send_json_success( array(
			'message'  => 'رفتار جدید اضافه شد.',
			'behavior' => array(
				'id'     => $id,
				'name'   => $name,
				'icon'   => $icon,
				'points' => $points,
			),
		) );
	}

	/**
	 * حذف یک رکورد امتیاز
	 */
	public static function delete_point() {
		self::verify();

		$point_id = isset( $_POST['point_id'] ) ? absint( $_POST['point_id'] ) : 0;
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;

		if ( ! $point_id || ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		HS_Points_DB::delete_point( $point_id );
		wp_send_json_success( array( 'message' => 'امتیاز حذف شد.' ) );
	}
}

HS_Points_Handlers::init();
