<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_Util {

	public static function d( $ymd, $year = true ) {
		$ymd = substr( (string) $ymd, 0, 10 );
		if ( ! $ymd || '0000-00-00' === $ymd ) {
			return '';
		}
		return class_exists( 'HS_Util' ) ? HS_Util::jdate( $ymd, $year ) : $ymd;
	}

	public static function weekday( $ymd ) {
		$w = array( 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' );
		return $w[ (int) gmdate( 'w', strtotime( substr( $ymd, 0, 10 ) . ' 12:00:00' ) ) ];
	}

	/** «اول» ← 1 ، پیش‌دبستانی ← 0 ، نامشخص ← -1 */
	public static function grade_num( $class_id ) {
		$g   = trim( (string) get_post_meta( $class_id, '_hs_grade', true ) );
		$map = array( 'پیش‌دبستانی' => 0, 'پیش دبستانی' => 0, 'اول' => 1, 'دوم' => 2, 'سوم' => 3, 'چهارم' => 4, 'پنجم' => 5, 'ششم' => 6, 'هفتم' => 7, 'هشتم' => 8, 'نهم' => 9, 'دهم' => 10, 'یازدهم' => 11, 'دوازدهم' => 12 );
		if ( isset( $map[ $g ] ) ) {
			return $map[ $g ];
		}
		$n = (int) strtr( $g, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) );
		return ( $n >= 1 && $n <= 12 ) ? $n : -1;
	}

	public static function student_classes( $sid ) {
		$c = get_user_meta( $sid, '_hs_classes', true );
		return is_array( $c ) ? array_values( array_unique( array_filter( array_map( 'intval', $c ) ) ) ) : array();
	}

	public static function in_class( $sid, $cid ) {
		$s = get_post_meta( $cid, '_hs_students', true );
		return is_array( $s ) && in_array( (int) $sid, array_map( 'intval', $s ), true );
	}

	public static function is_class_teacher( $uid, $cid ) {
		return user_can( $uid, 'manage_options' ) || (int) get_post_meta( $cid, '_hs_teacher_id', true ) === (int) $uid
			|| ( class_exists( 'HS_Roles' ) && (int) get_current_user_id() === (int) $uid && HS_Roles::can_manage_class( $cid ) );
	}

	/** آیا کاربر اجازه‌ی دیدن «دنیای» این دانش‌آموز را دارد؟ (خودش، معلمِ کلاسش، مدیر) */
	public static function can_view_student( $uid, $sid ) {
		if ( (int) $uid === (int) $sid || user_can( $uid, 'manage_options' ) ) {
			return true;
		}
		foreach ( self::student_classes( $sid ) as $c ) {
			if ( self::is_class_teacher( $uid, $c ) ) {
				return true;
			}
		}
		return false;
	}

	public static function teacher_classes( $uid ) {
		if ( user_can( $uid, 'manage_options' ) ) {
			return array_map( 'intval', get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'any', 'numberposts' => 300, 'fields' => 'ids' ) ) );
		}
		return array_map( 'intval', get_posts( array(
			'post_type'   => 'hs_class',
			'post_status' => 'any',
			'numberposts' => 300,
			'fields'      => 'ids',
			'meta_key'    => '_hs_teacher_id', // phpcs:ignore
			'meta_value'  => (int) $uid,       // phpcs:ignore
		) ) );
	}

	public static function notice() {
		$m = isset( $_GET['hssp_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['hssp_msg'] ) ) : ''; // phpcs:ignore
		return $m ? '<div class="hssp-note" role="status">' . esc_html( $m ) . '</div>' : '';
	}

	public static function back( $msg, $url = '' ) {
		$ref = $url ? $url : wp_get_referer();
		$ref = $ref ? remove_query_arg( 'hssp_msg', $ref ) : home_url( '/panel/' );
		wp_safe_redirect( add_query_arg( 'hssp_msg', rawurlencode( $msg ), $ref ) );
		exit;
	}

	public static function page_url( $slug ) {
		return class_exists( 'HS_Shell' ) ? HS_Shell::url( $slug ) : home_url( '/panel/' . $slug . '/' );
	}
}
