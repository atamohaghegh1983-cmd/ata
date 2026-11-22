<?php
/**
 * AJAX چت والدین
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Parent_Chat_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_pchat_send', array( __CLASS__, 'send' ) );
		add_action( 'wp_ajax_hs_pchat_fetch', array( __CLASS__, 'fetch' ) );
		add_action( 'wp_ajax_hs_pchat_upload_voice', array( __CLASS__, 'upload_voice' ) );
		add_action( 'wp_ajax_hs_pchat_upload_image', array( __CLASS__, 'upload_image' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'وارد شوید.' ) );
		}
	}

	/**
	 * آیا کاربر می‌تواند در چت این کلاس شرکت کند؟
	 * والدِ دانش‌آموز کلاس، یا معلم کلاس، یا ادمین
	 */
	public static function can_access_class( $class_id, $user_id = null ) {
		$user_id = $user_id ?: get_current_user_id();
		$class_id = absint( $class_id );

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( $class_id && HS_Roles::can_manage_class( $class_id, $user_id ) ) {
			return true;
		}

		// اتاق عمومی والدین (class_id = 0)
		if ( $class_id === 0 ) {
			// هر والد یا معلم
			$user = get_userdata( $user_id );
			$roles = $user ? (array) $user->roles : array();
			if ( array_intersect( $roles, array( 'parent', 'teacher', 'school_admin', 'administrator' ) ) ) {
				return true;
			}
			// یا کسی که حداقل یک فرزند دارد
			if ( class_exists( 'HS_Parent_Link' ) && HS_Parent_Link::get_children( $user_id ) ) {
				return true;
			}
			return false;
		}

		// والد یکی از دانش‌آموزان کلاس
		$students = get_post_meta( $class_id, '_hs_students', true );
		if ( ! is_array( $students ) ) {
			return false;
		}
		if ( HSPP_Group::is_assistant( $class_id, $user_id ) ) {
			return true;
		}
		$set = HSPP_Group::settings( $class_id );
		if ( ! $set['enabled'] ) {
			return false; // گروه این کلاس خاموش است
		}
		foreach ( $students as $sid ) {
			// والدِ تأییدشده‌ی یکی از دانش‌آموزان کلاس، با مجوز «گروه والدین»
			if ( HS_Parent_Link::can_view( $user_id, $sid ) && HSPP_Perms::can( $user_id, (int) $sid, 'group', $class_id ) ) {
				return true;
			}
		}
		return false;
	}

	public static function send() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$body     = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		$reply_to = isset( $_POST['reply_to'] ) ? absint( $_POST['reply_to'] ) : 0;
		$msg_type = isset( $_POST['msg_type'] ) ? sanitize_key( $_POST['msg_type'] ) : 'text';
		$media_url = isset( $_POST['media_url'] ) ? esc_url_raw( wp_unslash( $_POST['media_url'] ) ) : '';
		$media_id  = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;

		if ( ! self::can_access_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی به این گفتگو ندارید.' ) );
		}

		$ok = HSPP_Group::can_post( $class_id, get_current_user_id(), $msg_type );
		if ( is_wp_error( $ok ) ) {
			wp_send_json_error( array( 'message' => $ok->get_error_message() ) );
		}

		if ( $msg_type === 'text' && $body === '' ) {
			wp_send_json_error( array( 'message' => 'متن پیام خالی است.' ) );
		}
		if ( in_array( $msg_type, array( 'voice', 'image' ), true ) && ! $media_url ) {
			wp_send_json_error( array( 'message' => 'فایل پیوست نشده.' ) );
		}

		if ( $reply_to ) {
			$parent_msg = HS_Parent_Chat_DB::get( $reply_to );
			if ( ! $parent_msg || (int) $parent_msg['class_id'] !== $class_id ) {
				$reply_to = 0;
			}
		}

		$id = HS_Parent_Chat_DB::insert( array(
			'class_id'  => $class_id,
			'room'      => 'class',
			'sender_id' => get_current_user_id(),
			'body'      => $body,
			'msg_type'  => $msg_type,
			'media_url' => $media_url,
			'media_id'  => $media_id,
			'reply_to'  => $reply_to,
		) );

		$msg = HS_Parent_Chat_DB::get( $id );
		wp_send_json_success( array(
			'message' => 'ارسال شد',
			'msg'     => self::format_msg( $msg ),
		) );
	}

	public static function fetch() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$after_id = isset( $_POST['after_id'] ) ? absint( $_POST['after_id'] ) : 0;

		if ( ! self::can_access_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) );
		}

		$rows = HS_Parent_Chat_DB::get_messages( $class_id, 'class', $after_id, 60 );
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = self::format_msg( $r );
		}

		wp_send_json_success( array(
			'messages' => $out,
			'deleted'  => HS_Parent_Chat_DB::recent_deleted( $class_id ),
			'state'    => HSPP_Group::state( $class_id, get_current_user_id() ),
		) );
	}

	public static function upload_voice() {
		self::verify();
		self::handle_media_upload( array( 'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav' ), 'voice' );
	}

	public static function upload_image() {
		self::verify();
		self::handle_media_upload( array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' ), 'image' );
	}

	private static function handle_media_upload( $allowed, $kind ) {
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => 'فایلی ارسال نشد.' ) );
		}

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! self::can_access_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// محدودیت حجم: صوت ۵ مگ، تصویر ۳ مگ
		$max = $kind === 'voice' ? 5 * 1024 * 1024 : 3 * 1024 * 1024;
		if ( ! empty( $_FILES['file']['size'] ) && $_FILES['file']['size'] > $max ) {
			wp_send_json_error( array( 'message' => 'حجم فایل زیاد است.' ) );
		}

		$overrides = array( 'test_form' => false, 'mimes' => $allowed );
		$movefile = wp_handle_upload( $_FILES['file'], $overrides );
		if ( isset( $movefile['error'] ) ) {
			wp_send_json_error( array( 'message' => $movefile['error'] ) );
		}

		$attachment = array(
			'post_mime_type' => $movefile['type'],
			'post_title'     => sanitize_file_name( pathinfo( $_FILES['file']['name'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $movefile['file'] );
		if ( ! is_wp_error( $attach_id ) && $kind === 'image' ) {
			$meta = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
			wp_update_attachment_metadata( $attach_id, $meta );
		}

		wp_send_json_success( array(
			'url'       => $movefile['url'],
			'attach_id' => is_wp_error( $attach_id ) ? 0 : $attach_id,
			'type'      => $kind,
		) );
	}

	public static function format_msg( $row ) {
		if ( ! $row ) {
			return null;
		}
		$sender = get_userdata( $row['sender_id'] );
		$reply = null;
		if ( ! empty( $row['reply_to'] ) ) {
			$pm = HS_Parent_Chat_DB::get( $row['reply_to'] );
			if ( $pm ) {
				$rs = get_userdata( $pm['sender_id'] );
				$reply = array(
					'id'     => (int) $pm['id'],
					'name'   => $pm['class_id'] ? HSPP_Names::name( (int) $pm['sender_id'], (int) $pm['class_id'] ) : ( $rs ? $rs->display_name : 'کاربر' ),
					'body'   => ! empty( $pm['deleted'] ) ? 'پیام حذف شده' : mb_substr( wp_strip_all_tags( $pm['body'] ?: '' ), 0, 80 ),
					'type'   => $pm['msg_type'],
				);
			}
		}

		$cid  = (int) $row['class_id'];
		$me   = get_current_user_id();
		$name = $cid ? HSPP_Names::name( (int) $row['sender_id'], $cid ) : ( $sender ? $sender->display_name : 'کاربر' );
		$mod  = HSPP_Group::is_mod( $cid, $me );
		return array(
			'id'         => (int) $row['id'],
			'sender_id'  => (int) $row['sender_id'],
			'name'       => $name,
			'avatar'     => mb_substr( preg_replace( '/^(پدرِ|مادرِ|والدِ|سرپرستِ)\s*/u', '', $name ), 0, 1 ),
			'can_delete' => $mod || ( (int) $row['sender_id'] === $me && strtotime( $row['created_at'] ) > current_time( 'timestamp' ) - 15 * MINUTE_IN_SECONDS ),
			'can_mod'    => $mod,
			'body'       => $row['body'],
			'msg_type'   => $row['msg_type'],
			'media_url'  => $row['media_url'],
			'reply'      => $reply,
			'is_mine'    => (int) $row['sender_id'] === get_current_user_id(),
			'created_at' => $row['created_at'],
			'time'       => mysql2date( 'H:i', $row['created_at'] ),
			'date'       => mysql2date( 'Y/m/d', $row['created_at'] ),
		);
	}
}

HS_Parent_Chat_Handlers::init();
