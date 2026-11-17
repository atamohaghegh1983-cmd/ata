<?php
/**
 * ابزارهای کمکی: تاریخ شمسی، سال تحصیلی، محدودکننده تلاش (Rate limit)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Util {

	/** تبدیل میلادی به شمسی → [سال، ماه، روز] */
	public static function g2j( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 )
			+ (int) ( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * (int) ( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy   += (int) ( ( $days - 1 ) / 365 );
			$days  = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	public static function month_names() {
		return array( '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	}

	public static function fa_digits( $s ) {
		return strtr( (string) $s, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/** «۱۱ آبان ۱۴۰۵» از تاریخ میلادی Y-m-d */
	public static function jdate( $ymd, $with_year = true ) {
		if ( ! $ymd || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m ) ) {
			return '';
		}
		list( $jy, $jm, $jd ) = self::g2j( (int) $m[1], (int) $m[2], (int) $m[3] );
		$names = self::month_names();
		return self::fa_digits( $jd . ' ' . $names[ $jm ] . ( $with_year ? ' ' . $jy : '' ) );
	}

	public static function today_jalali() {
		return self::jdate( current_time( 'Y-m-d' ) );
	}

	/** سال تحصیلی جاری (از مهر تا شهریور) مثل «۱۴۰۵-۱۴۰۶» */
	/** فهرست استان‌های ایران */
	public static function provinces() {
		return array(
			'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز', 'ایلام', 'بوشهر', 'تهران',
			'چهارمحال و بختیاری', 'خراسان جنوبی', 'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان',
			'سمنان', 'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان', 'کرمان', 'کرمانشاه',
			'کهگیلویه و بویراحمد', 'گلستان', 'گیلان', 'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد',
		);
	}

	/** گزینه‌های انتخاب استان */
	public static function province_options( $selected = '' ) {
		$out = '<option value="">— انتخاب استان —</option>';
		foreach ( self::provinces() as $p ) {
			$out .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $p ), selected( $selected, $p, false ), esc_html( $p ) );
		}
		return $out;
	}

	public static function academic_year() {
		list( $jy, $jm ) = self::g2j( (int) current_time( 'Y' ), (int) current_time( 'n' ), (int) current_time( 'j' ) );
		$start = ( $jm >= 7 ) ? $jy : $jy - 1;
		return self::fa_digits( $start . '-' . ( $start + 1 ) );
	}

	/* ---------- محدودکننده تلاش ---------- */

	private static function rl_key( $key ) {
		return 'hs_rl_' . md5( $key );
	}

	public static function rl_blocked( $key, $max ) {
		return (int) get_transient( self::rl_key( $key ) ) >= $max;
	}

	public static function rl_hit( $key, $window ) {
		$k = self::rl_key( $key );
		set_transient( $k, (int) get_transient( $k ) + 1, $window );
	}

	public static function rl_reset( $key ) {
		delete_transient( self::rl_key( $key ) );
	}

	public static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	}

	/** کد تصادفی خوانا (بدون حروف مبهم) */
	public static function random_code( $len = 8 ) {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$out   = '';
		for ( $i = 0; $i < $len; $i++ ) {
			$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
		}
		return $out;
	}
}
