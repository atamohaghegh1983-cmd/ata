<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** ابزارهای مشترک API: خطا، پارامتر، تاریخ، محدودیت تعداد درخواست */
class HS_API_Util {

	public static function err( $msg, $status = 400, $code = 'hs_error', $data = array() ) {
		return new WP_Error( $code, $msg, array_merge( array( 'status' => $status ), (array) $data ) );
	}

	public static function p( WP_REST_Request $r, $k, $d = '' ) {
		$v = $r->get_param( $k );
		return null === $v ? $d : $v;
	}

	/** رشته‌ی کوتاه و تمیز */
	public static function text( $v, $max = 190 ) {
		$v = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
		return mb_substr( $v, 0, $max );
	}

	/** متن چندخطی تمیز */
	public static function textarea( $v, $max = 5000 ) {
		$v = is_scalar( $v ) ? sanitize_textarea_field( (string) $v ) : '';
		return mb_substr( $v, 0, $max );
	}

	public static function digits( $s ) {
		return strtr( (string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) );
	}

	/** تاریخ میلادی YYYY-MM-DD معتبر یا '' */
	public static function date( $s ) {
		$s = self::digits( (string) $s );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $s;
	}

	public static function today() {
		return current_time( 'Y-m-d' );
	}

	/** تاریخ در بازه‌ی مجاز ثبت (حداکثر ۱ روز آینده، حداکثر ۴۰۰ روز گذشته) */
	public static function date_in_range( $ymd ) {
		$t   = strtotime( $ymd . ' 00:00:00' );
		$now = strtotime( self::today() . ' 00:00:00' );
		return $t && $t <= $now + DAY_IN_SECONDS && $t >= $now - 400 * DAY_IN_SECONDS;
	}

	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : ''; // phpcs:ignore
		return substr( preg_replace( '/[^0-9a-fA-F:.]/', '', (string) $ip ), 0, 45 );
	}

	/* ---------- محدودیت تعداد (شمارنده با transient) ---------- */

	private static function rl_key( $key ) {
		return 'hs_api_rl_' . md5( $key );
	}

	public static function hits( $key ) {
		return (int) get_transient( self::rl_key( $key ) );
	}

	public static function hit( $key, $ttl ) {
		$k = self::rl_key( $key );
		$n = (int) get_transient( $k ) + 1;
		set_transient( $k, $n, $ttl );
		return $n;
	}

	public static function reset( $key ) {
		delete_transient( self::rl_key( $key ) );
	}

	/** اگر از سقف گذشته باشد خطای ۴۲۹ */
	public static function limited( $key, $max, $msg = '' ) {
		if ( self::hits( $key ) >= $max ) {
			return self::err( $msg ? $msg : 'تعداد درخواست‌ها زیاد بود. چند دقیقه بعد دوباره امتحان کنید.', 429, 'hs_throttled' );
		}
		return false;
	}

	/** کد کلاس از متن خام، لینک /join/CODE یا لینک ورود کارت کلاس (?c=CODE) */
	public static function extract_code( $raw ) {
		$raw = trim( self::digits( (string) $raw ) );
		if ( preg_match( '#/join/([A-Za-z0-9-]{4,32})#', $raw, $m ) ) {
			return strtoupper( $m[1] );
		}
		if ( preg_match( '#[?&]c=([A-Za-z0-9]{4,12})#', $raw, $m ) ) {
			return strtoupper( $m[1] );
		}
		return strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', $raw ) );
	}

	/**
	 * عکس اختصاصی کاربر یا '' (اپ حرف اول نام را در دایره‌ی رنگی نشان می‌دهد).
	 * Gravatar برگردانده نمی‌شود: هش ایمیلِ ساخته‌شده از موبایل، شماره را لو می‌دهد.
	 */
	public static function avatar( $uid, $size = 128 ) {
		$url = (string) get_user_meta( (int) $uid, '_hs_avatar_url', true );
		return $url ? esc_url_raw( $url ) : '';
	}
}
