<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HS_API_Auth {
	const ACCESS_TTL  = 3600;            // ۱ ساعت
	const REFRESH_TTL = 2592000;         // ۳۰ روز
	const MAX_DEVICES = 5;
	const META_RT     = 'hs_api_refresh';
	const META_TV     = 'hs_api_tv';

	private static $error = null;

	public static function init() {
		add_filter( 'determine_current_user', array( __CLASS__, 'determine_user' ), 20 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'auth_errors' ), 20 );
	}

	public static function ensure_secret() {
		if ( ! get_option( 'hs_api_secret' ) ) {
			add_option( 'hs_api_secret', bin2hex( random_bytes( 32 ) ), '', 'no' );
		}
	}

	private static function secret() {
		if ( defined( 'HS_API_JWT_SECRET' ) && HS_API_JWT_SECRET ) { return HS_API_JWT_SECRET; }
		self::ensure_secret();
		return get_option( 'hs_api_secret' );
	}

	/* ---------- خواندن توکن از درخواست ---------- */

	private static function is_our_request() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
		return false !== strpos( $uri, HS_API_NS ) ;
	}

	private static function bearer() {
		$h = '';
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'HTTP_X_HS_AUTHORIZATION' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) { $h = wp_unslash( $_SERVER[ $k ] ); break; } // phpcs:ignore
		}
		if ( ! $h && function_exists( 'getallheaders' ) ) {
			foreach ( getallheaders() as $k => $v ) {
				if ( 0 === strcasecmp( $k, 'authorization' ) || 0 === strcasecmp( $k, 'x-hs-authorization' ) ) { $h = $v; break; }
			}
		}
		return ( $h && preg_match( '/^Bearer\s+(\S+)$/i', trim( $h ), $m ) ) ? $m[1] : '';
	}

	public static function determine_user( $user_id ) {
		if ( $user_id || ! self::is_our_request() ) { return $user_id; }
		$tok = self::bearer();
		if ( ! $tok ) { return $user_id; }
		$c = HS_API_JWT::decode( $tok, self::secret() );
		if ( is_wp_error( $c ) ) { self::$error = $c; return $user_id; }
		if ( empty( $c['sub'] ) || empty( $c['typ'] ) || 'access' !== $c['typ'] ) {
			self::$error = new WP_Error( 'hs_bad_token', 'توکن نامعتبر است.' );
			return $user_id;
		}
		$u = get_userdata( (int) $c['sub'] );
		if ( ! $u || (int) get_user_meta( $u->ID, self::META_TV, true ) !== (int) ( isset( $c['tv'] ) ? $c['tv'] : 0 ) ) {
			self::$error = new WP_Error( 'hs_token_revoked', 'نشست شما پایان یافته است. دوباره وارد شوید.' );
			return $user_id;
		}
		return $u->ID;
	}

	public static function auth_errors( $result ) {
		if ( null !== $result ) { return $result; }
		if ( self::$error ) {
			return new WP_Error( self::$error->get_error_code(), self::$error->get_error_message(), array( 'status' => 401 ) );
		}
		return $result;
	}

	/* ---------- ورود و توکن‌ها ---------- */

	public static function normalize( $s ) {
		return trim( strtr( (string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) ) );
	}

	private static function ip() { return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; }

	public static function login( $username, $password, $device = '' ) {
		$username = self::normalize( $username );
		$key      = 'hs_api_fail_' . md5( strtolower( $username ) . '|' . self::ip() );
		$fails    = (int) get_transient( $key );
		if ( $fails >= 5 ) {
			return new WP_Error( 'hs_throttled', 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه دیگر دوباره تلاش کنید.', array( 'status' => 429 ) );
		}
		$u = wp_authenticate( $username, (string) $password );
		if ( is_wp_error( $u ) ) {
			set_transient( $key, $fails + 1, 15 * MINUTE_IN_SECONDS );
			return new WP_Error( 'hs_bad_login', 'نام کاربری یا رمز عبور درست نیست.', array( 'status' => 401 ) );
		}
		delete_transient( $key );
		return self::issue( $u, $device );
	}

	/** صدور جفت توکن برای یک دستگاه */
	public static function issue( WP_User $u, $device = '', $replace_hash = '' ) {
		$now = time();
		$tv  = (int) get_user_meta( $u->ID, self::META_TV, true );
		$access = HS_API_JWT::encode( array(
			'iss' => home_url(), 'sub' => $u->ID, 'iat' => $now, 'exp' => $now + self::ACCESS_TTL, 'typ' => 'access', 'tv' => $tv,
		), self::secret() );
		$refresh = bin2hex( random_bytes( 32 ) );
		$list    = (array) get_user_meta( $u->ID, self::META_RT, true );
		$list    = array_filter( $list, function ( $r ) use ( $now, $replace_hash ) {
			return is_array( $r ) && $r['exp'] > $now && ( ! $replace_hash || $r['h'] !== $replace_hash );
		} );
		$list[] = array( 'h' => hash( 'sha256', $refresh ), 'exp' => $now + self::REFRESH_TTL, 'dev' => mb_substr( sanitize_text_field( $device ), 0, 60 ), 'at' => $now );
		$list   = array_slice( array_values( $list ), -self::MAX_DEVICES );
		update_user_meta( $u->ID, self::META_RT, $list );
		return array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => self::ACCESS_TTL,
			'refresh_token' => $u->ID . '.' . $refresh,
			'user'          => HS_API_Access::profile( $u ),
		);
	}

	public static function refresh( $rt, $device = '' ) {
		$parts = explode( '.', (string) $rt, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) { return new WP_Error( 'hs_bad_refresh', 'نشست معتبر نیست.', array( 'status' => 401 ) ); }
		$u = get_userdata( (int) $parts[0] );
		if ( ! $u ) { return new WP_Error( 'hs_bad_refresh', 'نشست معتبر نیست.', array( 'status' => 401 ) ); }
		$h    = hash( 'sha256', $parts[1] );
		$list = (array) get_user_meta( $u->ID, self::META_RT, true );
		foreach ( $list as $r ) {
			if ( is_array( $r ) && hash_equals( $r['h'], $h ) && $r['exp'] > time() ) {
				return self::issue( $u, $device ? $device : $r['dev'], $h ); // چرخش توکن: قدیمی باطل می‌شود
			}
		}
		return new WP_Error( 'hs_bad_refresh', 'نشست منقضی شده است. دوباره وارد شوید.', array( 'status' => 401 ) );
	}

	public static function logout( $user_id, $rt = '', $all = false ) {
		if ( $all ) {
			delete_user_meta( $user_id, self::META_RT );
			update_user_meta( $user_id, self::META_TV, (int) get_user_meta( $user_id, self::META_TV, true ) + 1 ); // همهٔ accessها باطل
			return;
		}
		$parts = explode( '.', (string) $rt, 2 );
		if ( 2 === count( $parts ) ) {
			$h    = hash( 'sha256', $parts[1] );
			$list = array_filter( (array) get_user_meta( $user_id, self::META_RT, true ), function ( $r ) use ( $h ) { return is_array( $r ) && $r['h'] !== $h; } );
			update_user_meta( $user_id, self::META_RT, array_values( $list ) );
		}
	}
}
