<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ورود با موبایل و کد پیامکی برای اپ.
 * از همان منطق بخش «ورود» افزونه‌ی «کاربران و عضویت» استفاده می‌کند:
 *   HSMA_OTP::send / verify  (کد ۵ رقمی، ۲ دقیقه، ۵ تلاش، فاصله‌ی ۶۰ ثانیه، سقف ساعتی، قفل) ← Pinova
 * پس از ورود، hsum_mobile_known اجرا می‌شود (اتصال خودکار والد با شماره، دعوت کادر و …).
 */
class HS_API_OTP {

	public static function available() {
		return class_exists( 'HSMA_OTP' ) && class_exists( 'HSMA_Util' );
	}

	private static function unavailable() {
		return HS_API_Util::err( 'ورود با پیامک روی سایت فعال نیست (افزونه‌ی «کاربران و عضویت» لازم است).', 503, 'hs_otp_unavailable' );
	}

	/** @return array|WP_Error */
	public static function request( $raw_mobile ) {
		if ( ! self::available() ) { return self::unavailable(); }
		$m = HSMA_Util::mobile( $raw_mobile );
		if ( ! $m ) {
			return HS_API_Util::err( 'شماره موبایل درست نیست. مثال: 09121234567', 400, 'hs_bad_mobile' );
		}
		if ( HSMA_Util::is_blocked( HSMA_Util::ip() ) ) {
			return HS_API_Util::err( 'دسترسی شما موقتاً مسدود است. با مدیر سایت تماس بگیرید.', 429, 'hs_blocked' );
		}
		$res = HSMA_OTP::send( $m );
		if ( is_wp_error( $res ) ) {
			$code   = $res->get_error_code();
			$status = in_array( $code, array( 'cooldown', 'limit', 'blocked' ), true ) ? 429 : ( 'sms_failed' === $code ? 502 : 400 );
			return HS_API_Util::err( $res->get_error_message(), $status, 'hs_otp_' . $code );
		}
		// وجود یا نبود حساب برای این شماره فاش نمی‌شود
		return array(
			'ok'      => true,
			'mobile'  => $m,
			'wait'    => (int) $res['wait'],
			'ttl'     => (int) HSMA_OTP::TTL,
			'length'  => 5,
			'message' => 'کد ۵ رقمی به ' . $m . ' فرستاده شد.',
		);
	}

	/** کاربرِ قابل ورود با این شماره (دانش‌آموز با کارت کلاس وارد می‌شود، نه با پیامک) */
	private static function find_user( $m ) {
		$cands = array();
		$u     = get_user_by( 'login', $m );
		if ( $u ) { $cands[] = $u; }
		foreach ( get_users( array( 'meta_key' => '_hs_mobile', 'meta_value' => $m, 'number' => 3 ) ) as $x ) { $cands[] = $x; }
		$x = HSMA_Util::user_by_mobile( $m );
		if ( $x instanceof WP_User ) { $cands[] = $x; }
		$student = false;
		foreach ( $cands as $c ) {
			if ( in_array( 'student', (array) $c->roles, true ) && ! array_intersect( (array) $c->roles, array( 'parent', 'teacher', 'school_admin' ) ) ) {
				$student = true;
				continue;
			}
			return $c;
		}
		return $student ? 'student' : null;
	}

	/** @return array|WP_Error */
	public static function verify( $raw_mobile, $code, $device = '' ) {
		if ( ! self::available() ) { return self::unavailable(); }
		$m = HSMA_Util::mobile( $raw_mobile );
		if ( ! $m ) {
			return HS_API_Util::err( 'شماره موبایل درست نیست.', 400, 'hs_bad_mobile' );
		}
		$ip  = HS_API_Util::ip();
		$lim = HS_API_Util::limited( 'otpv_ip_' . $ip, 30 );
		if ( $lim ) { return $lim; }
		if ( HSMA_Util::is_blocked( $m ) || HSMA_Util::is_blocked( $ip ) ) {
			return HS_API_Util::err( 'امکان ورود با این شماره در حال حاضر وجود ندارد. با مدیر سایت تماس بگیرید.', 429, 'hs_blocked' );
		}
		$ok = HSMA_OTP::verify( $m, HS_API_Util::digits( (string) $code ) );
		if ( is_wp_error( $ok ) ) {
			HS_API_Util::hit( 'otpv_ip_' . $ip, 15 * MINUTE_IN_SECONDS );
			$c = $ok->get_error_code();
			return HS_API_Util::err( $ok->get_error_message(), 'too_many' === $c ? 429 : 400, 'hs_otp_' . $c );
		}

		$user = self::find_user( $m );
		if ( 'student' === $user ) {
			return HS_API_Util::err( 'این شماره به حساب دانش‌آموز تعلق دارد. دانش‌آموز با کارت کلاس و رمز ۴ رقمی وارد می‌شود.', 409, 'hs_student_account' );
		}
		$new = false;
		if ( ! $user ) {
			if ( ! HSMA_Util::allowed_roles() ) {
				return HS_API_Util::err( 'ثبت‌نام در حال حاضر بسته است.', 403, 'hs_registration_closed' );
			}
			$uid = wp_insert_user( array(
				'user_login'   => $m,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $m . '@hamshagerdi.local',
				'display_name' => $m,
				'nickname'     => $m,
				'role'         => get_option( 'default_role', 'subscriber' ),
				'meta_input'   => array( '_hs_mobile' => $m, '_hs_registered_via' => 'app' ),
			) );
			if ( is_wp_error( $uid ) ) {
				return HS_API_Util::err( 'ساخت حساب انجام نشد. دوباره امتحان کنید.', 500, 'hs_register_failed' );
			}
			$user = get_userdata( $uid );
			$new  = true;
			do_action( 'hs_api_registered', $uid, $m );
		}

		$ok = HS_API_Auth::may_use_app( $user );
		if ( is_wp_error( $ok ) ) { return $ok; }

		// شماره‌ی تأییدشده ← اتصال‌های خودکار (والد با شماره، دعوت کادر، درخواست‌ها)
		if ( ! get_user_meta( $user->ID, '_hs_mobile', true ) ) {
			update_user_meta( $user->ID, '_hs_mobile', $m );
		}
		if ( ! $new ) {
			if ( class_exists( 'HSUM_Phone' ) ) {
				HSUM_Phone::sync( $user->ID ); // hsum_mobile_known را اجرا می‌کند
			} else {
				do_action( 'hsum_mobile_known', $user->ID, $m );
			}
		} elseif ( ! class_exists( 'HSUM_Phone' ) ) {
			do_action( 'hsum_mobile_known', $user->ID, $m ); // در حالت عادی user_register همین را اجرا کرده است
		}
		do_action( 'hs_api_login', $user, 'otp' );

		$user = get_userdata( $user->ID ); // نقش‌ها ممکن است با اتصال‌های خودکار عوض شده باشند
		$out  = HS_API_Auth::issue( $user, $device );
		$out['is_new']           = $new;
		$out['needs_onboarding'] = HS_API_Access::needs_onboarding( $user->ID );
		return $out;
	}
}
