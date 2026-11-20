<?php
/**
 * کد یک‌بار مصرف: ۵ رقمی، ۲ دقیقه اعتبار، حداکثر ۵ تلاش؛ کد به‌صورت هش ذخیره می‌شود.
 * پس از تأیید، یک «بلیت» تصادفی ۱۵ دقیقه‌ای صادر می‌شود که برای تکمیل ثبت‌نام لازم است.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_OTP {

	const TTL      = 120;  // اعتبار کد (ثانیه)
	const COOLDOWN = 60;   // فاصله ارسال دوباره
	const TRIES    = 5;

	private static function key( $mobile ) {
		return 'hsma_otp_' . md5( $mobile );
	}

	/** @return array{ok:true, wait:int}|WP_Error */
	public static function send( $mobile ) {
		$ip = HSMA_Util::ip();

		if ( HSMA_Util::is_blocked( $mobile ) || HSMA_Util::is_blocked( $ip ) ) {
			return new WP_Error( 'blocked', 'امکان ورود با این شماره در حال حاضر وجود ندارد. با مدیر سایت تماس بگیرید.' );
		}

		if ( get_transient( 'hsma_cd_' . md5( $mobile ) ) ) {
			return new WP_Error( 'cooldown', 'کد قبلاً ارسال شده؛ یک دقیقه صبر کنید.' );
		}
		if ( HSMA_Util::hits( 'send_m_' . $mobile ) >= 5 ) {
			return new WP_Error( 'limit', 'تعداد درخواست کد برای این شماره زیاد بود. یک ساعت بعد امتحان کنید.' );
		}
		if ( HSMA_Util::hits( 'send_ip_' . $ip ) >= 15 ) {
			return new WP_Error( 'limit', 'تعداد درخواست‌ها زیاد بود. کمی بعد امتحان کنید.' );
		}

		$code = (string) wp_rand( 10000, 99999 );
		$sent = HSMA_SMS::send_code( $mobile, $code );
		if ( is_wp_error( $sent ) ) {
			return $sent;
		}

		set_transient( self::key( $mobile ), array(
			'h'   => wp_hash( $mobile . '|' . $code, 'nonce' ),
			'try' => 0,
		), self::TTL );
		set_transient( 'hsma_cd_' . md5( $mobile ), 1, self::COOLDOWN );
		HSMA_Util::hit( 'send_m_' . $mobile, HOUR_IN_SECONDS );
		HSMA_Util::hit( 'send_ip_' . $ip, HOUR_IN_SECONDS );

		return array( 'ok' => true, 'wait' => self::COOLDOWN );
	}

	/** @return true|WP_Error */
	public static function verify( $mobile, $code ) {
		$k    = self::key( $mobile );
		$data = get_transient( $k );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'expired', 'کد منقضی شده است. دوباره کد بگیرید.' );
		}
		$code = preg_replace( '/\D+/', '', HSMA_Util::en_digits( $code ) );
		if ( $code && hash_equals( $data['h'], wp_hash( $mobile . '|' . $code, 'nonce' ) ) ) {
			delete_transient( $k );
			return true;
		}
		$data['try']++;
		if ( $data['try'] >= self::TRIES ) {
			delete_transient( $k );
			// سه بار پشت سر هم کد غلط (هر بار ۵ تلاش) ← یک ساعت مسدود
			HSMA_Util::hit( 'tm_' . $mobile, HOUR_IN_SECONDS );
			if ( HSMA_Util::hits( 'tm_' . $mobile ) >= 3 ) {
				HSMA_Util::block( $mobile, 60 );
			}
			return new WP_Error( 'too_many', 'کد چند بار اشتباه وارد شد. دوباره کد بگیرید.' );
		}
		set_transient( $k, $data, self::TTL );
		return new WP_Error( 'wrong', 'کد وارد شده درست نیست.' );
	}

	/* ---------- بلیت تأیید شماره ---------- */

	public static function issue_ticket( $mobile ) {
		$t = wp_generate_password( 32, false );
		set_transient( 'hsma_tk_' . md5( $t ), $mobile, 15 * MINUTE_IN_SECONDS );
		return $t;
	}

	/** موبایلِ بلیت یا '' */
	public static function ticket_mobile( $ticket ) {
		$ticket = preg_replace( '/[^A-Za-z0-9]/', '', (string) $ticket );
		return $ticket ? (string) get_transient( 'hsma_tk_' . md5( $ticket ) ) : '';
	}

	public static function burn_ticket( $ticket ) {
		delete_transient( 'hsma_tk_' . md5( preg_replace( '/[^A-Za-z0-9]/', '', (string) $ticket ) ) );
	}
}
