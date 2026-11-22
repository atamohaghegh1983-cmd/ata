<?php
/**
 * عکس پروفایل: آپلود، برش مربع ۲۵۶ پیکسل، حذف و جایگزینی گراواتار.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Avatar {

	const META_URL  = '_hs_avatar_url';
	const META_FILE = '_hs_avatar_file';
	const MAX_BYTES = 3145728; // ۳ مگابایت

	public static function init() {
		add_filter( 'pre_get_avatar_data', array( __CLASS__, 'filter_avatar' ), 10, 2 );
		add_action( 'wp_ajax_hs_avatar_upload', array( __CLASS__, 'ajax_upload' ) );
		add_action( 'wp_ajax_hs_avatar_remove', array( __CLASS__, 'ajax_remove' ) );
	}

	private static function user_id_from( $id_or_email ) {
		if ( is_numeric( $id_or_email ) ) {
			return (int) $id_or_email;
		}
		if ( $id_or_email instanceof WP_User ) {
			return (int) $id_or_email->ID;
		}
		if ( $id_or_email instanceof WP_Post ) {
			return (int) $id_or_email->post_author;
		}
		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$u = get_user_by( 'email', $id_or_email );
			return $u ? (int) $u->ID : 0;
		}
		return 0;
	}

	public static function filter_avatar( $args, $id_or_email ) {
		$uid = self::user_id_from( $id_or_email );
		if ( $uid ) {
			$url = get_user_meta( $uid, self::META_URL, true );
			if ( $url ) {
				$args['url']          = esc_url_raw( $url );
				$args['found_avatar'] = true;
			}
		}
		return $args;
	}

	private static function fail( $msg, $code = 400 ) {
		wp_send_json_error( array( 'message' => $msg ), $code );
	}

	private static function check() {
		if ( ! check_ajax_referer( 'hs_shell', 'nonce', false ) || ! is_user_logged_in() ) {
			self::fail( 'نشست نامعتبر. صفحه را رفرش کنید.', 403 );
		}
	}

	private static function delete_old( $uid ) {
		$old = get_user_meta( $uid, self::META_FILE, true );
		if ( $old && is_string( $old ) ) {
			$base = wp_normalize_path( wp_upload_dir()['basedir'] );
			$real = wp_normalize_path( $old );
			if ( 0 === strpos( $real, $base . '/hs-avatars/' ) && file_exists( $old ) ) {
				wp_delete_file( $old );
			}
		}
		delete_user_meta( $uid, self::META_URL );
		delete_user_meta( $uid, self::META_FILE );
	}

	public static function upload_dir( $dirs ) {
		$dirs['subdir'] = '/hs-avatars';
		$dirs['path']   = $dirs['basedir'] . '/hs-avatars';
		$dirs['url']    = $dirs['baseurl'] . '/hs-avatars';
		return $dirs;
	}

	public static function ajax_upload() {
		self::check();
		if ( empty( $_FILES['avatar'] ) || ! is_array( $_FILES['avatar'] ) ) {
			self::fail( 'فایلی انتخاب نشده است.' );
		}
		$file = $_FILES['avatar']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
			self::fail( 'آپلود ناموفق بود. دوباره تلاش کنید.' );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			self::fail( 'حجم عکس باید کمتر از ۳ مگابایت باشد.' );
		}
		$mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			self::fail( 'فقط عکس JPG، PNG یا WebP پذیرفته می‌شود.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uid = get_current_user_id();
		$ext = $check['ext'];
		add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		$moved = wp_handle_upload( $file, array(
			'test_form'                => false,
			'mimes'                    => $mimes,
			'unique_filename_callback' => function () use ( $uid, $ext ) {
				return 'u' . $uid . '-' . strtolower( wp_generate_password( 10, false ) ) . '.' . $ext;
			},
		) );
		remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );

		if ( ! is_array( $moved ) || ! empty( $moved['error'] ) ) {
			self::fail( 'ذخیره عکس ممکن نشد.' );
		}
		$editor = wp_get_image_editor( $moved['file'] );
		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $moved['file'] );
			self::fail( 'این فایل یک عکس معتبر نیست.' );
		}
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}
		$editor->resize( 256, 256, true );
		$saved = $editor->save( $moved['file'] );
		if ( is_wp_error( $saved ) ) {
			wp_delete_file( $moved['file'] );
			self::fail( 'پردازش عکس ناموفق بود.' );
		}

		self::delete_old( $uid );
		update_user_meta( $uid, self::META_URL, esc_url_raw( $moved['url'] ) );
		update_user_meta( $uid, self::META_FILE, $moved['file'] );
		wp_send_json_success( array( 'message' => 'عکس پروفایل ذخیره شد ✅', 'url' => esc_url_raw( $moved['url'] ) ) );
	}

	public static function ajax_remove() {
		self::check();
		self::delete_old( get_current_user_id() );
		wp_send_json_success( array( 'message' => 'عکس پروفایل حذف شد.' ) );
	}
}

HS_Avatar::init();
