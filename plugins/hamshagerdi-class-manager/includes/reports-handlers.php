<?php
/**
 * هندلرهای AJAX گزارش پیشرفت
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Reports_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_get_student_report', array( __CLASS__, 'get_student_report' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'لطفاً وارد شوید.' ) );
		}
	}

	public static function get_student_report() {
		self::verify();

		$class_id   = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$student_id = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;
		$year       = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : (int) date( 'Y' );
		$month      = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : (int) date( 'n' );

		if ( ! $class_id || ! $student_id || ! HS_Roles::can_manage_class( $class_id ) || ! HS_Class_Students::is_member( $class_id, $student_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$report = HS_Reports::student_report( $class_id, $student_id, $year, $month );
		if ( ! $report ) {
			wp_send_json_error( array( 'message' => 'دانش‌آموز یافت نشد.' ) );
		}

		// آماده‌سازی تاریخچه امتیاز برای JSON
		$history = array();
		foreach ( $report['points_history'] as $h ) {
			$history[] = array(
				'points'   => (int) $h['points'],
				'behavior' => ( $h['behavior_icon'] ?: '⭐' ) . ' ' . ( $h['behavior_name'] ?: 'امتیاز' ),
				'note'     => $h['note'],
				'date'     => $h['created_at'],
			);
		}
		$report['points_history'] = $history;

		wp_send_json_success( array( 'report' => $report ) );
	}
}

HS_Reports_Handlers::init();
