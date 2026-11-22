<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * توکن دستگاه برای اعلان (Push).
 * - هر توکن فقط به یک کاربر تعلق دارد (اگر گوشی دست کس دیگری افتاد، اعلان حساب قبلی نمی‌آید).
 * - خروج از حساب یا ابطال نشست‌ها، توکن‌ها را پاک می‌کند.
 */
class HS_API_Devices {
	const META     = 'hs_api_devices';
	const IDX      = 'hs_api_dev_';      // + md5(token)  ← نمایه‌ی مالک
	const MAX      = 5;
	const PLATFORMS = array( 'android', 'ios', 'web' );
	const PROVIDERS = array( 'fcm', 'pushe', 'najva', 'other' );

	public static function valid_token( $tok ) {
		return is_string( $tok ) && preg_match( '/^[A-Za-z0-9_\-:.]{16,512}$/', $tok );
	}

	public static function all( $uid ) {
		$l = get_user_meta( (int) $uid, self::META, true );
		return is_array( $l ) ? $l : array();
	}

	/** @return true|WP_Error */
	public static function register( $uid, $tok, $platform = 'android', $provider = 'fcm', $app_version = '' ) {
		$tok = trim( (string) $tok );
		if ( ! self::valid_token( $tok ) ) {
			return HS_API_Util::err( 'توکن دستگاه نامعتبر است.', 400, 'hs_bad_device' );
		}
		$platform = in_array( $platform, self::PLATFORMS, true ) ? $platform : 'android';
		$provider = in_array( $provider, self::PROVIDERS, true ) ? $provider : 'other';
		$h        = md5( $tok );
		// همین توکن از حساب‌های دیگر برداشته شود
		foreach ( get_users( array( 'meta_key' => self::IDX . $h, 'fields' => 'ID', 'number' => 20 ) ) as $other ) {
			if ( (int) $other !== (int) $uid ) {
				self::remove( (int) $other, $tok );
			}
		}
		$list       = self::all( $uid );
		unset( $list[ $h ] );
		$list[ $h ] = array(
			'token'    => $tok,
			'platform' => $platform,
			'provider' => $provider,
			'app'      => mb_substr( sanitize_text_field( (string) $app_version ), 0, 20 ),
			'at'       => time(),
		);
		$list = array_slice( $list, -self::MAX, self::MAX, true );
		update_user_meta( $uid, self::META, $list );
		update_user_meta( $uid, self::IDX . $h, 1 );
		return true;
	}

	public static function remove( $uid, $tok ) {
		$h    = md5( (string) $tok );
		$list = self::all( $uid );
		unset( $list[ $h ] );
		update_user_meta( (int) $uid, self::META, $list );
		delete_user_meta( (int) $uid, self::IDX . $h );
	}

	public static function clear( $uid ) {
		foreach ( array_keys( self::all( $uid ) ) as $h ) {
			delete_user_meta( (int) $uid, self::IDX . $h );
		}
		delete_user_meta( (int) $uid, self::META );
	}

	/** برای سرویس اعلان (مرحله‌ی ۶): توکن‌های یک کاربر */
	public static function tokens( $uid, $provider = '' ) {
		$out = array();
		foreach ( self::all( $uid ) as $d ) {
			if ( is_array( $d ) && ! empty( $d['token'] ) && ( ! $provider || $provider === ( $d['provider'] ?? '' ) ) ) {
				$out[] = $d['token'];
			}
		}
		return $out;
	}
}
