<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Audit {

	public static function labels() {
		return array(
			'status'        => 'تغییر وضعیت عضویت',
			'role'          => 'تغییر نقش',
			'link_proposed' => 'پیشنهاد اتصال والد',
			'link_active'   => 'اتصال والد',
			'link_rejected' => 'رد اتصال والد',
			'link_removed'  => 'حذف اتصال والد',
			'invite'        => 'دعوت‌نامه',
			'request'       => 'درخواست اتصال',
			'import'        => 'ورود گروهی',
			'school_member' => 'عضویت در مدرسه',
		);
	}

	public static function log( $action, $target_id = 0, $details = array() ) {
		global $wpdb;
		$wpdb->insert( HSUM_DB::t( 'audit' ), array(
			'actor_id'   => get_current_user_id(),
			'action'     => $action,
			'target_id'  => (int) $target_id,
			'details'    => wp_json_encode( $details, JSON_UNESCAPED_UNICODE ),
			'created_at' => HSUM_DB::now(),
		) );
	}

	public static function recent( $limit = 200, $target = 0 ) {
		global $wpdb;
		$t = HSUM_DB::t( 'audit' );
		if ( $target ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE target_id = %d ORDER BY id DESC LIMIT %d", $target, $limit ), ARRAY_A ); // phpcs:ignore
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore
	}
}
