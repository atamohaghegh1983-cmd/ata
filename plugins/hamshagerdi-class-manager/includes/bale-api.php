<?php
/**
 * ارتباط با ربات بله (Bale Bot API)
 * مستندات: مشابه تلگرام — https://tapi.bale.ai
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Bale_API {

	const OPT_TOKEN   = 'hs_bale_bot_token';
	const OPT_ENABLED = 'hs_bale_enabled';
	const META_CHAT   = '_hs_bale_chat_id';
	const META_CODE   = '_hs_bale_link_code';

	public static function is_enabled() {
		return (bool) get_option( self::OPT_ENABLED, false ) && self::token();
	}

	public static function token() {
		return trim( (string) get_option( self::OPT_TOKEN, '' ) );
	}

	/**
	 * درخواست به API بله
	 */
	public static function request( $method, $params = array() ) {
		$token = self::token();
		if ( ! $token ) {
			return new WP_Error( 'no_token', 'توکن ربات بله تنظیم نشده است.' );
		}

		$url = 'https://tapi.bale.ai/bot' . $token . '/' . ltrim( $method, '/' );

		$args = array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $params ),
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || empty( $body['ok'] ) ) {
			$msg = isset( $body['description'] ) ? $body['description'] : 'خطای API بله';
			return new WP_Error( 'bale_api', $msg, $body );
		}

		return $body['result'] ?? $body;
	}

	/**
	 * ارسال پیام متنی
	 */
	public static function send_message( $chat_id, $text, $reply_markup = null ) {
		$chat_id = sanitize_text_field( $chat_id );
		if ( ! $chat_id || ! $text ) {
			return new WP_Error( 'invalid', 'شناسه چت یا متن خالی است.' );
		}

		$params = array(
			'chat_id'    => $chat_id,
			'text'       => mb_substr( $text, 0, 4000 ),
		);
		if ( $reply_markup ) {
			$params['reply_markup'] = $reply_markup;
		}

		return self::request( 'sendMessage', $params );
	}

	/**
	 * ارسال به کاربر وردپرس (اگر chat_id داشته باشد)
	 */
	public static function notify_user( $user_id, $text ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'disabled', 'ربات بله غیرفعال است.' );
		}
		$chat_id = get_user_meta( absint( $user_id ), self::META_CHAT, true );
		if ( ! $chat_id ) {
			return new WP_Error( 'no_chat', 'کاربر ربات بله را متصل نکرده است.' );
		}
		return self::send_message( $chat_id, $text );
	}

	/**
	 * کد یک‌بارمصرف برای اتصال حساب
	 */
	public static function generate_link_code( $user_id ) {
		$code = HS_Util::random_code( 8 );
		update_user_meta( $user_id, self::META_CODE, $code );
		update_user_meta( $user_id, self::META_CODE . '_time', time() );
		return $code;
	}

	/** کلید مخفی آدرس وب‌هوک */
	public static function webhook_secret() {
		$s = get_option( 'hs_bale_webhook_secret' );
		if ( ! $s ) {
			$s = wp_generate_password( 32, false, false );
			update_option( 'hs_bale_webhook_secret', $s, false );
		}
		return $s;
	}

	public static function webhook_url() {
		return add_query_arg( 'key', self::webhook_secret(), rest_url( 'hamshagerdi/v1/bale-webhook' ) );
	}

	public static function get_link_code( $user_id ) {
		$code = get_user_meta( $user_id, self::META_CODE, true );
		$time = (int) get_user_meta( $user_id, self::META_CODE . '_time', true );
		if ( ! $code || ( time() - $time ) > 3600 ) {
			return self::generate_link_code( $user_id );
		}
		return $code;
	}

	/**
	 * اتصال با کد (از webhook یا دستور /start CODE)
	 */
	public static function link_by_code( $code, $chat_id ) {
		$code = strtoupper( sanitize_text_field( $code ) );
		$chat_id = sanitize_text_field( $chat_id );
		if ( ! $code || ! $chat_id ) {
			return new WP_Error( 'invalid', 'کد یا چت نامعتبر.' );
		}

		$users = get_users( array(
			'meta_key'   => self::META_CODE,
			'meta_value' => $code,
			'number'     => 1,
			'fields'     => 'ID',
		) );
		if ( empty( $users ) ) {
			return new WP_Error( 'not_found', 'کد یافت نشد یا منقضی شده.' );
		}

		$user_id = (int) $users[0];
		$time = (int) get_user_meta( $user_id, self::META_CODE . '_time', true );
		if ( ( time() - $time ) > 3600 ) {
			return new WP_Error( 'expired', 'کد منقضی شده. کد جدید بگیرید.' );
		}

		// هر چت بله فقط به یک حساب وصل باشد
		$others = get_users( array( 'meta_key' => self::META_CHAT, 'meta_value' => $chat_id, 'fields' => 'ID' ) );
		foreach ( $others as $oid ) {
			delete_user_meta( (int) $oid, self::META_CHAT );
		}
		update_user_meta( $user_id, self::META_CHAT, $chat_id );
		delete_user_meta( $user_id, self::META_CODE );
		delete_user_meta( $user_id, self::META_CODE . '_time' );

		return $user_id;
	}

	public static function unlink( $user_id ) {
		delete_user_meta( $user_id, self::META_CHAT );
		return true;
	}

	public static function get_chat_id( $user_id ) {
		return get_user_meta( absint( $user_id ), self::META_CHAT, true );
	}
}
