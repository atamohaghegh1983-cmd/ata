<?php
/** ابزارهای مشترک */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Util {

	/** تاریخ شمسی */
	public static function d( $ymd, $year = true ) {
		$ymd = substr( (string) $ymd, 0, 10 );
		if ( ! $ymd || '0000-00-00' === $ymd ) {
			return '—';
		}
		return class_exists( 'HS_Util' ) ? HS_Util::jdate( $ymd, $year ) : $ymd;
	}

	public static function weekday( $ymd ) {
		$w = array( 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' );
		return $w[ (int) gmdate( 'w', strtotime( $ymd . ' 12:00:00' ) ) ];
	}

	/** کلاس‌های یک معلم */
	public static function teacher_classes( $uid ) {
		$ids = get_posts( array(
			'post_type'      => 'hs_class',
			'post_status'    => 'any',
			'numberposts'    => 200,
			'fields'         => 'ids',
			'meta_key'       => '_hs_teacher_id', // phpcs:ignore
			'meta_value'     => (int) $uid,       // phpcs:ignore
		) );
		return array_map( 'intval', (array) $ids );
	}

	/** کلاس‌های فرزند که این بخش برای والد باز است */
	public static function child_classes( $pid, $sid, $feature ) {
		$out = array();
		foreach ( HS_Parent_Link::student_classes( $sid ) as $c ) {
			if ( HSPP_Perms::can( $pid, $sid, $feature, $c['id'] ) ) {
				$out[] = $c;
			}
		}
		return $out;
	}

	public static function notice() {
		$m = isset( $_GET['hspp_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['hspp_msg'] ) ) : ''; // phpcs:ignore
		return $m ? '<div class="hspp-note">' . esc_html( $m ) . '</div>' : '';
	}

	public static function back( $msg ) {
		$ref = wp_get_referer();
		$ref = $ref ? remove_query_arg( 'hspp_msg', $ref ) : home_url( '/panel/' );
		wp_safe_redirect( add_query_arg( 'hspp_msg', rawurlencode( $msg ), $ref ) );
		exit;
	}

	/** پیامک (اگر «ورود و پیامک» تنظیم شده باشد) */
	public static function sms( $uid, $text ) {
		if ( ! class_exists( 'HSMA_Gateways' ) ) {
			return false;
		}
		$m = (string) get_user_meta( $uid, '_hs_mobile', true );
		return $m ? true === HSMA_Gateways::send( $m, $text ) : false;
	}
}
