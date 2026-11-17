<?php
/**
 * شماره موبایل: استانداردسازی و نگاشت فیلد
 * هر افزونه‌ی ثبت‌نامی (ووکامرس billing_phone، دیجیتس، پینوا، ورود و پیامک…) که شماره را در یک user_meta نگه دارد،
 * با افزودن کلید آن در تنظیمات شناخته می‌شود. شماره‌ی استاندارد در _hs_mobile هم نگه داشته می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Phone {

	public static function init() {
		add_action( 'user_register', array( __CLASS__, 'sync' ), 20 );
		add_action( 'profile_update', array( __CLASS__, 'sync' ), 20 );
		add_action( 'wp_login', function ( $login, $user ) {
			self::sync( $user->ID );
		}, 5, 2 );
		// افزونه‌ی «ورود و پیامک»: کاربری که شماره‌اش در فیلد دیگری است هم پیدا شود
		add_filter( 'hsma_user_by_mobile', function ( $user, $mobile ) {
			return $user ? $user : self::user_by_mobile( $mobile );
		}, 10, 2 );
	}

	/** 09xxxxxxxxx یا '' */
	public static function norm( $s ) {
		$s = strtr( (string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) );
		$d = preg_replace( '/\D+/', '', $s );
		if ( 0 === strpos( $d, '0098' ) ) {
			$d = substr( $d, 4 );
		} elseif ( 0 === strpos( $d, '98' ) && 12 === strlen( $d ) ) {
			$d = substr( $d, 2 );
		}
		if ( 10 === strlen( $d ) && '9' === $d[0] ) {
			$d = '0' . $d;
		}
		return preg_match( '/^09\d{9}$/', $d ) ? $d : '';
	}

	public static function keys() {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) hsum_opt( 'phone_keys' ) ) ) ) );
	}

	public static function user_mobile( $user_id ) {
		foreach ( self::keys() as $k ) {
			$m = self::norm( get_user_meta( $user_id, $k, true ) );
			if ( $m ) {
				return $m;
			}
		}
		$u = get_userdata( $user_id );
		return $u ? self::norm( $u->user_login ) : '';
	}

	/** کاربرِ صاحب این شماره (در هر کدام از فیلدهای نگاشت‌شده) */
	public static function user_by_mobile( $mobile ) {
		$m = self::norm( $mobile );
		if ( ! $m ) {
			return false;
		}
		$u = get_user_by( 'login', $m );
		if ( $u ) {
			return $u;
		}
		global $wpdb;
		$keys = self::keys();
		if ( ! $keys ) {
			return false;
		}
		$tail = substr( $m, 1 ); // 9xxxxxxxxx
		$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ($in) AND meta_value LIKE %s LIMIT 20", // phpcs:ignore
			array_merge( $keys, array( '%' . $wpdb->esc_like( substr( $tail, -9 ) ) ) )
		) );
		foreach ( (array) $rows as $r ) {
			if ( self::norm( $r->meta_value ) === $m ) {
				return get_userdata( (int) $r->user_id );
			}
		}
		return false;
	}

	/** شماره‌ی استاندارد را در _hs_mobile نگه می‌دارد و پیشنهادهای اتصال والد را تطبیق می‌دهد */
	public static function sync( $user_id ) {
		$m = self::user_mobile( $user_id );
		if ( ! $m ) {
			return;
		}
		if ( get_user_meta( $user_id, '_hs_mobile', true ) !== $m ) {
			$other = self::user_by_mobile( $m );
			if ( $other && (int) $other->ID !== (int) $user_id ) {
				return; // شماره متعلق به حساب دیگری است؛ یکتایی حفظ شود
			}
			update_user_meta( $user_id, '_hs_mobile', $m );
		}
		do_action( 'hsum_mobile_known', $user_id, $m );
	}
}
