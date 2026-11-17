<?php
/**
 * هندلرهای AJAX حضور و غیاب
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Attendance_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_save_attendance', array( __CLASS__, 'save_attendance' ) );
		add_action( 'wp_ajax_hs_save_student_order', array( __CLASS__, 'save_order' ) );
		add_action( 'wp_ajax_hs_get_attendance', array( __CLASS__, 'get_attendance' ) );
		add_action( 'wp_ajax_hs_get_monthly_report', array( __CLASS__, 'get_monthly_report' ) );
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
	 * ذخیره حضور و غیاب یک کلاس برای یک روز
	 */
	public static function save_attendance() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$date     = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$records  = isset( $_POST['records'] ) ? $_POST['records'] : array();

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		if ( empty( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( array( 'message' => 'تاریخ نامعتبر است.' ) );
		}

		if ( ! is_array( $records ) || empty( $records ) ) {
			wp_send_json_error( array( 'message' => 'هیچ رکوردی ارسال نشده.' ) );
		}

		// اعتبارسنجی دانش‌آموزان کلاس
		$students = get_post_meta( $class_id, '_hs_students', true );
		if ( ! is_array( $students ) ) {
			$students = array();
		}
		$student_ids = array_map( 'absint', $students );

		$clean = array();
		foreach ( $records as $sid => $data ) {
			$sid = absint( $sid );
			if ( ! in_array( $sid, $student_ids, true ) ) {
				continue;
			}
			$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'present';
			$note   = isset( $data['note'] ) ? sanitize_textarea_field( wp_unslash( $data['note'] ) ) : '';
			$clean[ $sid ] = array(
				'status' => $status,
				'note'   => $note,
			);
		}

		$count = HS_Attendance_DB::save_batch( $class_id, $date, $clean );

		// آمار خلاصه امروز
		$summary = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0 );
		foreach ( $clean as $r ) {
			if ( isset( $summary[ $r['status'] ] ) ) {
				$summary[ $r['status'] ]++;
			}
		}

		wp_send_json_success( array(
			'message' => sprintf( 'حضور و غیاب %d دانش‌آموز ذخیره شد.', $count ),
			'count'   => $count,
			'summary' => $summary,
			'date'    => $date,
		) );
	}

	/**
	 * دریافت وضعیت حضور یک کلاس در یک روز
	 */
	public static function get_attendance() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$date     = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : date( 'Y-m-d' );

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$data = HS_Attendance_DB::get_by_class_date( $class_id, $date );
		$recorded = HS_Attendance_DB::is_recorded( $class_id, $date );

		wp_send_json_success( array(
			'records'  => $data,
			'recorded' => $recorded,
			'date'     => $date,
		) );
	}

	/**
	 * گزارش ماهانه
	 */
	public static function get_monthly_report() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$year     = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : (int) date( 'Y' );
		$month    = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : (int) date( 'n' );

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$summary = HS_Attendance_DB::get_monthly_summary( $class_id, $year, $month );
		$days    = HS_Attendance_DB::count_recorded_days( $class_id, $year, $month );

		$students = HS_Class_Students::get_students( $class_id );
		$rows = array();
		foreach ( $students as $user ) {
			$sid = $user->ID;
			$s   = isset( $summary[ $sid ] ) ? $summary[ $sid ] : array(
				'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0,
			);
			$rows[] = array(
				'id'      => $sid,
				'name'    => $user->display_name,
				'present' => $s['present'],
				'absent'  => $s['absent'],
				'late'    => $s['late'],
				'excused' => $s['excused'],
			);
		}

		wp_send_json_success( array(
			'rows'           => $rows,
			'recorded_days'  => $days,
			'year'           => $year,
			'month'          => $month,
		) );
	}

	/** ذخیره‌ی ترتیب نمایش دانش‌آموزان (نوع مرتب‌سازی و ترتیب دلخواه) */
	public static function save_order() {
		check_ajax_referer( 'hs_class_nonce', 'nonce' );
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		$mode = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'first';
		if ( ! in_array( $mode, array( 'first', 'last', 'custom', 'added' ), true ) ) {
			$mode = 'first';
		}
		update_post_meta( $class_id, '_hs_sort', $mode );
		if ( 'custom' === $mode && isset( $_POST['order'] ) ) {
			$members = array_map( 'intval', (array) get_post_meta( $class_id, '_hs_students', true ) );
			$order   = array_values( array_intersect( array_map( 'absint', (array) wp_unslash( $_POST['order'] ) ), $members ) );
			update_post_meta( $class_id, '_hs_order', $order );
		}
		wp_send_json_success( array( 'message' => 'ترتیب نمایش ذخیره شد.' ) );
	}
}

HS_Attendance_Handlers::init();
