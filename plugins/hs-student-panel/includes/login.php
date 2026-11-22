<?php
/**
 * ورود ساده‌ی دانش‌آموز با «کارت کلاس»
 * کد کلاس (یا QR روی کارت) ← انتخاب نام ← رمز ۴ رقمی.
 * فقط برای حساب‌هایی که نقش «دانش‌آموز» دارند و هیچ دسترسی مدیریتی ندارند. تلاش اشتباه محدود است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_Login {

	const CODE = '_hssp_code';
	const PIN  = '_hssp_pin';
	const MAX_FAIL = 5;
	const PARENT_OK = '_hssp_parent_ok'; // والدین برای فرزند دسترسی ایجاد کرده‌اند

	public static function init() {
		add_shortcode( 'hs_kid_login', array( __CLASS__, 'page' ) );
		add_action( 'admin_post_nopriv_hssp_kid_login', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_hssp_kid_login', array( __CLASS__, 'handle' ) );
	}

	public static function url( $code = '', $sid = 0 ) {
		$u = HSSP_Integration::page_url( 'class-login' );
		if ( $code ) {
			$u = add_query_arg( 'c', $code, $u );
		}
		if ( $sid ) {
			$u = add_query_arg( 's', (int) $sid, $u );
		}
		return $u;
	}

	/* ---------- کد کلاس ---------- */

	public static function new_code() {
		$a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$c = '';
		for ( $i = 0; $i < 6; $i++ ) {
			$c .= $a[ random_int( 0, strlen( $a ) - 1 ) ];
		}
		return $c;
	}

	public static function class_code( $cid, $create = true ) {
		$c = (string) get_post_meta( $cid, self::CODE, true );
		if ( ! $c && $create ) {
			do {
				$c = self::new_code();
			} while ( self::class_by_code( $c ) );
			update_post_meta( $cid, self::CODE, $c );
		}
		return $c;
	}

	public static function reset_code( $cid ) {
		delete_post_meta( $cid, self::CODE );
		return self::class_code( $cid );
	}

	public static function class_by_code( $code ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
		if ( strlen( $code ) !== 6 ) {
			return 0;
		}
		$ids = get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => self::CODE, 'meta_value' => $code ) ); // phpcs:ignore
		return $ids ? (int) $ids[0] : 0;
	}

	/* ---------- رمز ۴ رقمی (رمزنگاری‌شده تا معلم بتواند کارت را دوباره چاپ کند) ---------- */

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . 'hssp', true );
	}

	private static function enc( $pin ) {
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv = random_bytes( 16 );
			return 'e:' . base64_encode( $iv . openssl_encrypt( $pin, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv ) ); // phpcs:ignore
		}
		return 'h:' . wp_hash_password( $pin );
	}

	public static function pin( $sid ) {
		$v = (string) get_user_meta( $sid, self::PIN, true );
		if ( 0 === strpos( $v, 'e:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $v, 2 ) ); // phpcs:ignore
			$p   = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
			return false === $p ? '' : $p;
		}
		return '';
	}

	public static function ensure_pin( $sid, $reset = false ) {
		$p = $reset ? '' : self::pin( $sid );
		if ( ! $p && ( $reset || '' === (string) get_user_meta( $sid, self::PIN, true ) ) ) {
			$p = str_pad( (string) random_int( 0, 9999 ), 4, '0', STR_PAD_LEFT );
			update_user_meta( $sid, self::PIN, self::enc( $p ) );
		}
		return $p;
	}

	/** والدین رمز ۴ رقمی انتخابی خودشان را برای فرزند ثبت می‌کنند (یا دسترسی را می‌بندند) */
	public static function set_pin( $sid, $pin ) {
		if ( ! preg_match( '/^\d{4}$/', (string) $pin ) ) {
			return new WP_Error( 'pin', 'رمز باید دقیقاً ۴ رقم باشد.' );
		}
		update_user_meta( $sid, self::PIN, self::enc( $pin ) );
		update_user_meta( $sid, self::PARENT_OK, 1 );
		delete_transient( 'hssp_lf_' . $sid );
		return true;
	}

	public static function revoke( $sid ) {
		delete_user_meta( $sid, self::PARENT_OK );
	}

	public static function parent_ok( $sid ) {
		return (bool) get_user_meta( $sid, self::PARENT_OK, true );
	}

	/** ورود این دانش‌آموز مجاز است؟ (کارت کلاس روشن باشد، یا والدین دسترسی داده باشند) */
	public static function allowed( $cid, $sid ) {
		return self::eligible( $sid ) && ( HSSP_Settings::get( $cid )['login'] || self::parent_ok( $sid ) );
	}

	public static function check_pin( $sid, $pin ) {
		$v = (string) get_user_meta( $sid, self::PIN, true );
		if ( '' === $v || ! preg_match( '/^\d{4}$/', (string) $pin ) ) {
			return false;
		}
		if ( 0 === strpos( $v, 'h:' ) ) {
			return wp_check_password( $pin, substr( $v, 2 ) );
		}
		return hash_equals( self::pin( $sid ), (string) $pin );
	}

	/** فقط حساب ساده‌ی دانش‌آموز (نه معلم/والد/مدیر) */
	public static function eligible( $sid ) {
		$u = get_userdata( $sid );
		if ( ! $u || ! in_array( 'student', (array) $u->roles, true ) ) {
			return false;
		}
		if ( array_diff( (array) $u->roles, array( 'student', 'subscriber' ) ) ) {
			return false;
		}
		return ! user_can( $sid, 'edit_posts' ) && ! user_can( $sid, 'manage_options' );
	}

	private static function ip() {
		return substr( preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 45 ); // phpcs:ignore
	}

	public static function locked( $sid ) {
		return (int) get_transient( 'hssp_lf_' . (int) $sid ) >= self::MAX_FAIL || (int) get_transient( 'hssp_lip_' . md5( self::ip() ) ) >= self::MAX_FAIL * 4;
	}

	private static function fail( $sid ) {
		foreach ( array( 'hssp_lf_' . (int) $sid, 'hssp_lip_' . md5( self::ip() ) ) as $k ) {
			set_transient( $k, (int) get_transient( $k ) + 1, 15 * MINUTE_IN_SECONDS );
		}
	}

	private static function first_name( $u ) {
		$f = trim( (string) $u->first_name );
		$l = trim( (string) $u->last_name );
		if ( $f ) {
			return $f . ( $l ? ' ' . mb_substr( $l, 0, 1 ) . '.' : '' );
		}
		$p = preg_split( '/\s+/u', trim( $u->display_name ) );
		return $p[0] . ( isset( $p[1] ) ? ' ' . mb_substr( $p[1], 0, 1 ) . '.' : '' );
	}

	/* ---------- صفحه ---------- */

	public static function page() {
		$code = isset( $_GET['c'] ) ? sanitize_text_field( wp_unslash( $_GET['c'] ) ) : ''; // phpcs:ignore
		$sid  = isset( $_GET['s'] ) ? (int) $_GET['s'] : 0; // phpcs:ignore
		$cid  = $code ? self::class_by_code( $code ) : 0;
		$h    = '<div class="hssp-login" dir="rtl">' . HSSP_Util::notice();
		if ( is_user_logged_in() ) {
			return $h . '<div class="hssp-login-card"><div class="hssp-login-emoji">👋</div><h2>وارد شده‌ای!</h2><a class="hs-kid-btn hs-kid-btn-big" href="' . esc_url( HSSP_Util::page_url( 'student' ) ) . '">برو به دنیای من 🚀</a></div></div>';
		}
		$on = $cid && ( HSSP_Settings::get( $cid )['login'] || array_filter( array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) ), array( __CLASS__, 'parent_ok' ) ) );
		if ( ! $on ) {
			return $h . '<div class="hssp-login-card"><div class="hssp-login-emoji">🎒</div><h2>ورود به کلاس</h2><p>کد کلاس را از روی کارت بنویس:</p>'
				. '<form method="get"><input class="hssp-code-in" name="c" maxlength="6" autocomplete="off" dir="ltr" placeholder="ABC123" value="' . esc_attr( $code ) . '" required><button class="hs-kid-btn hs-kid-btn-big">ادامه ←</button></form>'
				. ( $code ? '<p class="hssp-err">این کد درست نیست یا ورود با کارت برای این کلاس روشن نیست.</p>' : '' )
				. '<p class="hssp-small"><a href="' . esc_url( HSSP_Util::page_url( 'student' ) ) . '">ورود با موبایل یا نام کاربری</a></p></div></div>';
		}
		$students = array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) );
		if ( $sid && in_array( $sid, $students, true ) && self::allowed( $cid, $sid ) ) {
			$u  = get_userdata( $sid );
			$h .= '<div class="hssp-login-card"><a class="hssp-small" href="' . esc_url( self::url( self::class_code( $cid ) ) ) . '">← من نیستم</a>'
				. '<div class="hssp-login-av">' . esc_html( mb_substr( $u->display_name, 0, 1 ) ) . '</div><h2>سلام ' . esc_html( self::first_name( $u ) ) . '!</h2>';
			if ( self::locked( $sid ) ) {
				return $h . '<p class="hssp-err">چند بار اشتباه زدی. یک ربع دیگر دوباره امتحان کن یا از معلمت کمک بگیر.</p></div></div>';
			}
			return $h . '<p>رمز ۴ رقمی‌ات را بزن (از کارت کلاس یا از پدر و مادرت):</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hssp-pin-form">'
				. '<input type="hidden" name="action" value="hssp_kid_login"><input type="hidden" name="c" value="' . esc_attr( self::class_code( $cid ) ) . '"><input type="hidden" name="s" value="' . (int) $sid . '">'
				. wp_nonce_field( 'hssp_kid_login', '_wpnonce', true, false )
				. '<input class="hssp-pin-in" name="pin" inputmode="numeric" pattern="[0-9۰-۹]{4}" maxlength="4" autocomplete="off" required dir="ltr" autofocus>'
				. '<div class="hssp-pad">' . implode( '', array_map( function ( $n ) {
					return '<button type="button" data-n="' . $n . '">' . $n . '</button>';
				}, array( 1, 2, 3, 4, 5, 6, 7, 8, 9 ) ) ) . '<button type="button" data-n="del">⌫</button><button type="button" data-n="0">0</button><button class="hssp-pad-go">✓</button></div></form></div></div>';
		}
		$h .= '<div class="hssp-login-card hssp-login-wide"><h2>🏫 ' . esc_html( get_the_title( $cid ) ) . '</h2><p>اسمت را پیدا کن:</p><div class="hssp-names">';
		$list = array();
		foreach ( $students as $s ) {
			$u = get_userdata( $s );
			if ( $u && self::allowed( $cid, $s ) ) {
				$list[ $s ] = self::first_name( $u );
			}
		}
		asort( $list );
		$i = 0;
		foreach ( $list as $s => $n ) {
			$h .= '<a class="hssp-name c' . ( $i++ % 6 ) . '" href="' . esc_url( self::url( self::class_code( $cid ), $s ) ) . '"><span>' . esc_html( mb_substr( $n, 0, 1 ) ) . '</span>' . esc_html( $n ) . '</a>';
		}
		return $h . '</div></div></div>';
	}

	public static function handle() {
		$code = sanitize_text_field( wp_unslash( $_POST['c'] ?? '' ) );
		$sid  = (int) ( $_POST['s'] ?? 0 );
		$pin  = strtr( sanitize_text_field( wp_unslash( $_POST['pin'] ?? '' ) ), array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) );
		$cid  = self::class_by_code( $code );
		$back = self::url( $code, $sid );
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'hssp_kid_login' ) ) { // phpcs:ignore
			HSSP_Util::back( 'صفحه کهنه شده؛ دوباره امتحان کن.', $back );
		}
		if ( ! $cid || ! HSSP_Util::in_class( $sid, $cid ) || ! self::allowed( $cid, $sid ) ) {
			HSSP_Util::back( 'ورود ممکن نیست.', self::url() );
		}
		if ( self::locked( $sid ) ) {
			HSSP_Util::back( 'فعلاً قفل است. کمی بعد امتحان کن.', $back );
		}
		if ( ! self::check_pin( $sid, $pin ) ) {
			self::fail( $sid );
			HSSP_Util::back( 'رمز درست نبود. دوباره امتحان کن 🙂', $back );
		}
		delete_transient( 'hssp_lf_' . $sid );
		wp_set_current_user( $sid );
		wp_set_auth_cookie( $sid, false );
		HSSP_DB::log( 'kid_login', $sid, array( 'class' => $cid ) );
		wp_safe_redirect( HSSP_Util::page_url( 'student' ) );
		exit;
	}
}

HSSP_Login::init();
