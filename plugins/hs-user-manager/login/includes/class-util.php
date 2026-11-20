<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_Util {

	/** تنظیمات با مقدار پیش‌فرض */
	public static function opt( $key = null ) {
		$d = array(
			'test_mode'     => 1, // تا سامانه پیامک آزمایش نشده، کدها فقط در تنظیمات نمایش داده می‌شود
			'otp_mode'      => 'auto', // auto | meli_otp | pattern | text | pwsms
			'pattern_code'  => '',
			'pattern_var'   => 'code',
			'bale_on'       => 0,
			'bale_token'    => '',
			'bale_bot'      => '',
			'woo_login'     => 1,
			'msg_text'      => 'کد ورود شما به {site}: {code}',
			'roles'         => array( 'parent', 'student', 'teacher', 'school_admin' ),
			'redirect'      => '/panel/',
			'replace_login' => 1,
			'page_id'       => 0,
			'menu_locations' => array( '__auto__' ),
			'menu_label'    => 'ثبت‌نام / ورود',
		);
		$o = (array) get_option( 'hsma_settings', array() );
		if ( isset( $o['provider'] ) && ! isset( $o['test_mode'] ) ) { // از نسخه‌های ۱.x
			$o['test_mode'] = 'test' === $o['provider'] ? 1 : 0;
		}
		$o = wp_parse_args( $o, $d );
		return null === $key ? $o : ( isset( $o[ $key ] ) ? $o[ $key ] : null );
	}

	/** تنظیمات درگاه پیامک: gateway, username, password, sender */
	public static function gw( $key ) {
		$o = (array) get_option( 'hsma_gateway', array() );
		return isset( $o[ $key ] ) ? $o[ $key ] : '';
	}

	/* ---------- فهرست مسدودی (موبایل / IP) ---------- */

	public static function blocks() {
		$b   = (array) get_option( 'hsma_blocks', array() );
		$now = time();
		$out = array_filter( $b, function ( $until ) use ( $now ) {
			return 0 === (int) $until || (int) $until > $now;
		} );
		if ( count( $out ) !== count( $b ) ) {
			update_option( 'hsma_blocks', $out, false );
		}
		return $out;
	}

	public static function is_blocked( $id ) {
		return '' !== (string) $id && isset( self::blocks()[ $id ] );
	}

	/** $minutes = 0 → دائمی */
	public static function block( $id, $minutes = 0 ) {
		$b        = self::blocks();
		$b[ $id ] = $minutes ? time() + $minutes * 60 : 0;
		update_option( 'hsma_blocks', $b, false );
	}

	public static function unblock( $id ) {
		$b = self::blocks();
		unset( $b[ $id ] );
		update_option( 'hsma_blocks', $b, false );
	}

	/** ارقام فارسی/عربی → لاتین */
	public static function en_digits( $s ) {
		return strtr( (string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) );
	}

	/** موبایل ایران به شکل 09xxxxxxxxx؛ نامعتبر ← '' */
	public static function mobile( $s ) {
		$d = preg_replace( '/\D+/', '', self::en_digits( $s ) );
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

	public static function ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:.]/', '', wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	}

	/* ---------- محدودیت تعداد (شمارنده با transient) ---------- */

	public static function hits( $key ) {
		return (int) get_transient( 'hsma_rl_' . md5( $key ) );
	}

	public static function hit( $key, $ttl ) {
		$k = 'hsma_rl_' . md5( $key );
		set_transient( $k, (int) get_transient( $k ) + 1, $ttl );
	}

	public static function reset( $key ) {
		delete_transient( 'hsma_rl_' . md5( $key ) );
	}

	/** نقش‌های قابل انتخاب در فرم */
	public static function role_labels() {
		return array(
			'parent'       => 'والد',
			'student'      => 'دانش‌آموز',
			'teacher'      => 'معلم',
			'school_admin' => 'مدیر مدرسه',
		);
	}

	public static function allowed_roles() {
		$all = self::role_labels();
		$out = array();
		foreach ( (array) self::opt( 'roles' ) as $r ) {
			if ( isset( $all[ $r ] ) && get_role( $r ) ) {
				$out[ $r ] = $all[ $r ];
			}
		}
		return $out;
	}

	/** کاربر با این موبایل (نام کاربری = موبایل، یا متای موبایل) */
	public static function user_by_mobile( $mobile ) {
		$u = get_user_by( 'login', $mobile );
		if ( ! $u ) {
			$q = get_users( array( 'meta_key' => '_hs_mobile', 'meta_value' => $mobile, 'number' => 1 ) );
			$u = $q ? $q[0] : false;
		}
		// افزونه‌های دیگر (مثلاً مدیریت کاربران با نگاشت فیلد موبایل) می‌توانند کاربر را پیدا کنند
		return apply_filters( 'hsma_user_by_mobile', $u, $mobile );
	}

	public static function redirect_url( $requested = '' ) {
		$def = home_url( (string) self::opt( 'redirect' ) );
		if ( $requested ) {
			$safe = wp_validate_redirect( $requested, '' );
			if ( $safe ) {
				return $safe;
			}
		}
		return $def;
	}
}
