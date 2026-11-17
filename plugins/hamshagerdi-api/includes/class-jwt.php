<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** پیاده‌سازی کوچک و بدون وابستگی JWT (HS256) */
class HS_API_JWT {

	public static function b64( $s ) { return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); }
	public static function unb64( $s ) { return base64_decode( strtr( $s, '-_', '+/' ) ); }

	public static function encode( array $claims, $secret ) {
		$h = self::b64( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'HS256' ) ) );
		$p = self::b64( wp_json_encode( $claims ) );
		return $h . '.' . $p . '.' . self::b64( hash_hmac( 'sha256', $h . '.' . $p, $secret, true ) );
	}

	/** @return array|WP_Error */
	public static function decode( $jwt, $secret ) {
		$parts = explode( '.', (string) $jwt );
		if ( 3 !== count( $parts ) ) { return new WP_Error( 'hs_bad_token', 'توکن نامعتبر است.' ); }
		list( $h, $p, $s ) = $parts;
		$head = json_decode( self::unb64( $h ), true );
		if ( ! is_array( $head ) || ( isset( $head['alg'] ) ? $head['alg'] : '' ) !== 'HS256' ) {
			return new WP_Error( 'hs_bad_token', 'توکن نامعتبر است.' ); // جلوگیری از alg=none
		}
		$sig = hash_hmac( 'sha256', $h . '.' . $p, $secret, true );
		if ( ! hash_equals( $sig, (string) self::unb64( $s ) ) ) { return new WP_Error( 'hs_bad_token', 'توکن نامعتبر است.' ); }
		$c = json_decode( self::unb64( $p ), true );
		if ( ! is_array( $c ) ) { return new WP_Error( 'hs_bad_token', 'توکن نامعتبر است.' ); }
		if ( empty( $c['exp'] ) || time() >= (int) $c['exp'] ) { return new WP_Error( 'hs_token_expired', 'توکن منقضی شده است.' ); }
		return $c;
	}
}
