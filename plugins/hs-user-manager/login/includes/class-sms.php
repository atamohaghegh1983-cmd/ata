<?php
/**
 * ارسال کد ورود
 *
 * ترتیب: پیام‌رسان بله (اگر فعال باشد) ← پیامک.
 * روش پیامک (otp_mode):
 *  - meli_otp : سرویس اختصاصی کد تأیید ملی‌پیامک (بدون الگو)
 *  - pattern  : الگو/پترن سامانه (کد الگو + نام متغیر)
 *  - text     : متن عادی
 *  - pwsms    : از طریق افزونه‌ی «پیامک ووکامرس» اگر نصب است
 *  - auto     : ملی‌پیامک ← meli_otp، اگر کد الگو هست ← pattern، وگرنه text
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_SMS {

	public static function text_for( $code ) {
		$t = (string) HSMA_Util::opt( 'msg_text' );
		return strtr( $t ? $t : 'کد ورود شما به {site}: {code}', array(
			'{code}' => $code,
			'{site}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		) );
	}

	public static function mode() {
		return 'pinova';
	}

	/** @return true|WP_Error  ('channel' در داده‌ی خطا/موفقیت: sms یا bale) */
	public static function send_code( $mobile, $code ) {
		$o    = HSMA_Util::opt();
		$text = self::text_for( $code );

		$pre = apply_filters( 'hsma_sms_send', null, $mobile, $code, $text );
		if ( null !== $pre ) {
			return $pre;
		}

		if ( ! empty( $o['test_mode'] ) ) {
			$log = (array) get_option( 'hsma_test_log', array() );
			array_unshift( $log, array( 't' => time(), 'm' => $mobile, 'c' => $code ) );
			update_option( 'hsma_test_log', array_slice( $log, 0, 20 ), false );
			return true;
		}

		$errors = array();

		if ( ! empty( $o['bale_on'] ) ) {
			$b = self::bale( $mobile, $code );
			if ( true === $b ) {
				return true;
			}
			$errors[] = 'بله: ' . $b;
		}

		$r = self::sms( $mobile, $code, $text );
		if ( true === $r ) {
			return true;
		}
		$errors[] = $r;
		return self::fail( implode( ' | ', $errors ) );
	}

	/** @return true|string */
	public static function sms( $mobile, $code, $text = null ) {
		$text = null === $text ? self::text_for( $code ) : $text;
		return HSMA_Gateways::otp( $mobile, $code );
	}

	/* ---------- پیام‌رسان بله (سرویس سفیر) ---------- */

	public static function bale( $mobile, $code ) {
		$token = trim( (string) HSMA_Util::opt( 'bale_token' ) );
		$bot   = trim( (string) HSMA_Util::opt( 'bale_bot' ) );
		if ( ! $token || ! $bot ) {
			return 'توکن یا شناسه‌ی بات بله وارد نشده است.';
		}
		$res = wp_remote_post( 'https://safir.bale.ai/api/v3/send_message', array(
			'timeout' => 12,
			'headers' => array( 'api-access-key' => $token, 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'request_id'   => wp_generate_uuid4(),
				'bot_id'       => (int) $bot,
				'phone_number' => '98' . substr( $mobile, 1 ),
				'message_data' => array( 'otp_message' => array( 'otp' => (string) $code ) ),
			) ),
		) );
		if ( is_wp_error( $res ) ) {
			return 'اتصال: ' . $res->get_error_message();
		}
		$j = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $j['message_id'] ) ) {
			return true;
		}
		return isset( $j['error_data'][0]['description'] ) ? (string) $j['error_data'][0]['description'] : 'پاسخ نامعتبر: ' . wp_html_excerpt( wp_remote_retrieve_body( $res ), 200 );
	}

	/* ---------- افزونه‌ی «پیامک ووکامرس» (اختیاری) ---------- */

	public static function pwsms_available() {
		return function_exists( 'PWSMS' ) && is_callable( array( PWSMS(), 'send_sms' ) );
	}

	private static function via_pwsms( $mobile, $text ) {
		if ( ! self::pwsms_available() ) {
			return 'افزونه‌ی «پیامک ووکامرس» فعال نیست.';
		}
		try {
			$r = PWSMS()->send_sms( array( 'post_id' => 0, 'type' => 0, 'mobile' => $mobile, 'message' => $text ) );
		} catch ( \Throwable $e ) {
			return $e->getMessage();
		}
		return true === $r ? true : ( is_string( $r ) ? $r : wp_json_encode( $r ) );
	}

	private static function fail( $detail ) {
		update_option( 'hsma_last_error', array( 't' => time(), 'msg' => substr( $detail, 0, 500 ) ), false );
		return new WP_Error( 'sms_failed', 'ارسال کد انجام نشد. چند دقیقه بعد دوباره امتحان کنید.' );
	}
}
