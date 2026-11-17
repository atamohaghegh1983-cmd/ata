<?php
/**
 * ارسال پیامک: فقط از طریق افزونه‌ی «پینوا».
 * تنظیم سامانه‌ی پیامک (ملی‌پیامک، کاوه‌نگار، فراز، مکس‌اس‌ام‌اس، پنلچی، پیامک ووکامرس)
 * در منوی خود پینوا انجام می‌شود.
 *
 * برای افزونه‌های دیگر (بدون تغییر نسبت به قبل):
 *   hsma_send_sms( '0912…' یا آرایه، 'متن' ) → true | WP_Error
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_Gateways {

	const LOG = 'hsma_sms_log';

	/** پینوا نصب و فعال است؟ */
	public static function pinova() {
		return class_exists( '\\Pinova\\Channels\\SMS' );
	}

	public static function all() {
		return array( 'pinova' => 'پینوا' );
	}

	/** برای سازگاری با افزونه‌های دیگر: اگر پینوا فعال باشد مقدار غیرخالی برمی‌گرداند */
	public static function current_class() {
		return self::pinova() ? 'pinova' : '';
	}

	public static function is_meli() {
		return false;
	}

	/** @return true|string */
	public static function send( $mobiles, $message ) {
		$pre = apply_filters( 'hsma_gateway_send', null, $mobiles, (string) $message );
		if ( null !== $pre ) {
			return $pre;
		}
		if ( ! self::pinova() ) {
			return self::err( 'افزونه‌ی «پینوا» فعال نیست؛ پیامک ارسال نشد.' );
		}
		$mobiles = array_values( array_filter( array_map( array( 'HSMA_Util', 'mobile' ), (array) $mobiles ) ) );
		if ( ! $mobiles ) {
			return 'شماره موبایل نامعتبر است.';
		}
		if ( '' === trim( (string) $message ) ) {
			return 'متن پیامک خالی است.';
		}
		foreach ( $mobiles as $m ) {
			try {
				if ( ! \Pinova\Channels\SMS::send_message( $m, (string) $message ) ) {
					return self::err( 'سامانه‌ی پیامک (از طریق پینوا) پیام را نپذیرفت.' );
				}
			} catch ( \Throwable $e ) {
				return self::err( $e->getMessage() );
			}
		}
		self::log( count( $mobiles ) );
		return true;
	}

	/**
	 * ارسال کد ورود با تنظیمات خود پینوا (متن یا الگوی کد تأیید که در پینوا تعریف شده).
	 * @return true|string
	 */
	public static function otp( $mobile, $code ) {
		if ( ! self::pinova() ) {
			return self::err( 'افزونه‌ی «پینوا» فعال نیست؛ کد ورود ارسال نشد.' );
		}
		try {
			return \Pinova\Channels\SMS::send_code( (string) $mobile, (int) $code ) ? true : self::err( 'کد ورود ارسال نشد (پاسخ منفی سامانه).' );
		} catch ( \Throwable $e ) {
			return self::err( $e->getMessage() );
		}
	}

	public static function credit() {
		return null;
	}

	private static function err( $msg ) {
		update_option( 'hsma_last_error', array( 't' => time(), 'msg' => $msg ), false );
		return $msg;
	}

	private static function log( $n ) {
		$c = (int) get_option( self::LOG, 0 );
		update_option( self::LOG, $c + $n, false );
	}
}

/**
 * @param string|array $mobiles
 * @param string       $message
 * @return true|WP_Error
 */
if ( ! function_exists( 'hsma_send_sms' ) ) {
	function hsma_send_sms( $mobiles, $message ) {
		$r = HSMA_Gateways::send( $mobiles, $message );
		return true === $r ? true : new WP_Error( 'hsma_sms', $r );
	}
}
