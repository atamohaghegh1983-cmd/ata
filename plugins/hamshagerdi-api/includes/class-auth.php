<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * احراز هویت با JWT
 * - توکن دسترسی ۱ ساعته (HS256)، فقط برای مسیرهای /hamshagerdi/v1 پذیرفته می‌شود.
 * - توکن رفرش ۳۰ روزه، به‌صورت هش در متای کاربر، با هر استفاده عوض می‌شود؛ حداکثر ۵ دستگاه.
 * - هر بار: تعلیق حساب، حساب مدیریتی و نسخه‌ی توکن (tv) بررسی می‌شود.
 * - تغییر رمز، تعلیق یا «خروج از همه» ← tv یکی بالا می‌رود و همه‌ی توکن‌ها باطل می‌شوند.
 */
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
		// باطل‌کردن همه‌ی نشست‌های اپ
		add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ), 10, 2 );
		add_action( 'after_password_reset', array( __CLASS__, 'revoke_all' ) );
		add_action( 'hsum_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 2 );
		add_action( 'delete_user', array( __CLASS__, 'revoke_all' ) );
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

	/* ---------- سیاست ورود ---------- */

	/** حساب‌های مدیریتی سایت از اپ وارد نمی‌شوند (فقط با رمز در خود سایت) */
	public static function is_admin_account( $user ) {
		return user_can( $user, 'manage_options' ) || user_can( $user, 'edit_others_posts' ) || user_can( $user, 'promote_users' );
	}

	public static function is_suspended( $uid ) {
		return class_exists( 'HSUM_Membership' ) && 'suspended' === HSUM_Membership::status( (int) $uid );
	}

	/**
	 * این کاربر اجازه‌ی گرفتن/استفاده از توکن دارد؟
	 * @return true|WP_Error
	 */
	public static function may_use_app( $user ) {
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return HS_API_Util::err( 'حساب پیدا نشد.', 401, 'hs_bad_login' );
		}
		if ( self::is_admin_account( $user ) ) {
			return HS_API_Util::err( 'این حساب مدیریتی است و از اپ وارد نمی‌شود؛ از خود سایت با رمز وارد شوید.', 403, 'hs_admin_account' );
		}
		if ( self::is_suspended( $user->ID ) ) {
			return HS_API_Util::err( 'حساب شما تعلیق شده است. با مدیر سایت تماس بگیرید.', 403, 'hs_suspended' );
		}
		// افزونه‌های دیگر (مثل ورود پیامکی سایت) می‌توانند ورود را ببندند
		$ok = apply_filters( 'hsma_can_login', true, $user );
		if ( is_wp_error( $ok ) ) {
			return HS_API_Util::err( $ok->get_error_message(), 403, 'hs_login_blocked' );
		}
		$ok = apply_filters( 'hs_api_can_login', true, $user );
		return is_wp_error( $ok ) ? $ok : true;
	}

	/* ---------- خواندن توکن از درخواست ---------- */

	/** فقط مسیرهای همین افزونه (نه /wp/v2 و بقیه) با JWT احراز می‌شوند */
	public static function is_our_request() {
		$prefix = '/' . trim( HS_API_NS, '/' ) . '/';
		if ( isset( $_GET['rest_route'] ) ) { // phpcs:ignore
			$route = '/' . ltrim( (string) wp_unslash( $_GET['rest_route'] ), '/' ); // phpcs:ignore
			return 0 === strpos( $route . '/', $prefix );
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$base = (string) wp_parse_url( home_url( '/' . rest_get_url_prefix() ), PHP_URL_PATH );
		$base = '/' . trim( $base, '/' );
		if ( 0 !== strpos( $path, $base . '/' ) ) {
			return false;
		}
		$route = substr( $path, strlen( $base ) );
		return 0 === strpos( rtrim( $route, '/' ) . '/', $prefix );
	}

	private static function bearer() {
		$h = '';
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'HTTP_X_HS_AUTHORIZATION' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) { $h = (string) wp_unslash( $_SERVER[ $k ] ); break; } // phpcs:ignore
		}
		if ( ! $h && function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $k => $v ) {
				if ( 0 === strcasecmp( $k, 'authorization' ) || 0 === strcasecmp( $k, 'x-hs-authorization' ) ) { $h = (string) $v; break; }
			}
		}
		return ( $h && preg_match( '/^Bearer\s+([A-Za-z0-9_\-\.]{20,2048})$/i', trim( $h ), $m ) ) ? $m[1] : '';
	}

	public static function determine_user( $user_id ) {
		if ( $user_id || ! self::is_our_request() ) { return $user_id; }
		$tok = self::bearer();
		if ( ! $tok ) { return $user_id; }
		$c = HS_API_JWT::decode( $tok, self::secret() );
		if ( is_wp_error( $c ) ) { self::$error = $c; return $user_id; }
		if ( empty( $c['sub'] ) || empty( $c['typ'] ) || 'access' !== $c['typ'] || ( isset( $c['iss'] ) && home_url() !== $c['iss'] ) ) {
			self::$error = new WP_Error( 'hs_bad_token', 'توکن نامعتبر است.' );
			return $user_id;
		}
		$u = get_userdata( (int) $c['sub'] );
		if ( ! $u || (int) get_user_meta( $u->ID, self::META_TV, true ) !== (int) ( isset( $c['tv'] ) ? $c['tv'] : -1 ) ) {
			self::$error = new WP_Error( 'hs_token_revoked', 'نشست شما پایان یافته است. دوباره وارد شوید.' );
			return $user_id;
		}
		if ( self::is_admin_account( $u ) || self::is_suspended( $u->ID ) ) {
			self::$error = new WP_Error( 'hs_token_revoked', 'دسترسی این حساب از اپ بسته است.' );
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

	/* ---------- ابطال ---------- */

	public static function revoke_all( $user ) {
		$uid = $user instanceof WP_User ? $user->ID : (int) $user;
		if ( ! $uid ) { return; }
		delete_user_meta( $uid, self::META_RT );
		update_user_meta( $uid, self::META_TV, (int) get_user_meta( $uid, self::META_TV, true ) + 1 );
		if ( class_exists( 'HS_API_Devices' ) ) { HS_API_Devices::clear( $uid ); }
	}

	public static function on_profile_update( $uid, $old ) {
		$new = get_userdata( $uid );
		if ( $old instanceof WP_User && $new && $old->user_pass !== $new->user_pass ) {
			self::revoke_all( $uid );
		}
	}

	public static function on_status_changed( $uid, $status ) {
		if ( in_array( $status, array( 'suspended', 'rejected' ), true ) ) {
			self::revoke_all( $uid );
		}
	}

	/* ---------- ورود با رمز (سازگاری با نسخه‌ی ۱) ---------- */

	public static function normalize( $s ) {
		return trim( HS_API_Util::digits( $s ) );
	}

	public static function login( $username, $password, $device = '' ) {
		$username = self::normalize( $username );
		$ip       = HS_API_Util::ip();
		$k_user   = 'pw_u_' . strtolower( $username );
		$k_ip     = 'pw_ip_' . $ip;
		// سقف برای هر حساب (از هر IP) و برای هر IP (روی همه‌ی حساب‌ها)
		$lim = HS_API_Util::limited( $k_user, 5, 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه دیگر دوباره تلاش کنید، یا با کد پیامکی وارد شوید.' );
		if ( ! $lim ) { $lim = HS_API_Util::limited( $k_ip, 20 ); }
		if ( $lim ) { return $lim; }
		if ( '' === $username || '' === (string) $password ) {
			return HS_API_Util::err( 'نام کاربری و رمز را وارد کنید.', 400, 'hs_bad_login' );
		}
		$u = wp_authenticate( $username, (string) $password );
		if ( is_wp_error( $u ) ) {
			HS_API_Util::hit( $k_user, 15 * MINUTE_IN_SECONDS );
			HS_API_Util::hit( $k_ip, 15 * MINUTE_IN_SECONDS );
			if ( 'hsum_suspended' === $u->get_error_code() ) {
				return HS_API_Util::err( 'حساب شما تعلیق شده است. با مدیر سایت تماس بگیرید.', 403, 'hs_suspended' );
			}
			return HS_API_Util::err( 'نام کاربری یا رمز عبور درست نیست.', 401, 'hs_bad_login' );
		}
		$ok = self::may_use_app( $u );
		if ( is_wp_error( $ok ) ) { return $ok; }
		HS_API_Util::reset( $k_user );
		return self::issue( $u, $device );
	}

	/** صدور جفت توکن برای یک دستگاه */
	public static function issue( WP_User $u, $device = '', $replace_hash = '' ) {
		$now = time();
		$tv  = (int) get_user_meta( $u->ID, self::META_TV, true );
		if ( '' === get_user_meta( $u->ID, self::META_TV, true ) ) {
			update_user_meta( $u->ID, self::META_TV, 0 );
		}
		$access = HS_API_JWT::encode( array(
			'iss' => home_url(), 'sub' => $u->ID, 'iat' => $now, 'exp' => $now + self::ACCESS_TTL, 'typ' => 'access', 'tv' => $tv,
		), self::secret() );
		$refresh = bin2hex( random_bytes( 32 ) );
		$list    = (array) get_user_meta( $u->ID, self::META_RT, true );
		$list    = array_filter( $list, function ( $r ) use ( $now, $replace_hash ) {
			return is_array( $r ) && isset( $r['exp'], $r['h'] ) && $r['exp'] > $now && ( ! $replace_hash || $r['h'] !== $replace_hash );
		} );
		$list[] = array( 'h' => hash( 'sha256', $refresh ), 'exp' => $now + self::REFRESH_TTL, 'dev' => mb_substr( sanitize_text_field( (string) $device ), 0, 60 ), 'at' => $now );
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
		$bad   = HS_API_Util::err( 'نشست منقضی شده است. دوباره وارد شوید.', 401, 'hs_bad_refresh' );
		$parts = explode( '.', (string) $rt, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! preg_match( '/^[a-f0-9]{64}$/', $parts[1] ) ) { return $bad; }
		$lim = HS_API_Util::limited( 'rf_ip_' . HS_API_Util::ip(), 60 );
		if ( $lim ) { return $lim; }
		$u = get_userdata( (int) $parts[0] );
		if ( ! $u ) { HS_API_Util::hit( 'rf_ip_' . HS_API_Util::ip(), HOUR_IN_SECONDS ); return $bad; }
		$h    = hash( 'sha256', $parts[1] );
		$list = (array) get_user_meta( $u->ID, self::META_RT, true );
		foreach ( $list as $r ) {
			if ( is_array( $r ) && isset( $r['h'], $r['exp'] ) && hash_equals( $r['h'], $h ) && $r['exp'] > time() ) {
				$ok = self::may_use_app( $u );
				if ( is_wp_error( $ok ) ) {
					self::revoke_all( $u->ID );
					return $ok;
				}
				return self::issue( $u, $device ? $device : $r['dev'], $h ); // چرخش توکن: قدیمی باطل می‌شود
			}
		}
		HS_API_Util::hit( 'rf_ip_' . HS_API_Util::ip(), HOUR_IN_SECONDS );
		return $bad;
	}

	public static function logout( $user_id, $rt = '', $all = false ) {
		if ( $all ) {
			self::revoke_all( $user_id );
			return;
		}
		$parts = explode( '.', (string) $rt, 2 );
		if ( 2 === count( $parts ) && (int) $parts[0] === (int) $user_id ) {
			$h    = hash( 'sha256', $parts[1] );
			$list = array_filter( (array) get_user_meta( $user_id, self::META_RT, true ), function ( $r ) use ( $h ) { return is_array( $r ) && isset( $r['h'] ) && $r['h'] !== $h; } );
			update_user_meta( $user_id, self::META_RT, array_values( $list ) );
		}
	}
}
