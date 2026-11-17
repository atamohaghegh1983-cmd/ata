<?php
/**
 * هندلرهای AJAX ارتباط (اطلاعیه، فید، پیام)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Comms_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_add_announcement', array( __CLASS__, 'add_announcement' ) );
		add_action( 'wp_ajax_hs_delete_announcement', array( __CLASS__, 'delete_announcement' ) );
		add_action( 'wp_ajax_hs_add_feed_post', array( __CLASS__, 'add_feed_post' ) );
		add_action( 'wp_ajax_hs_delete_feed_post', array( __CLASS__, 'delete_feed_post' ) );
		add_action( 'wp_ajax_hs_send_message', array( __CLASS__, 'send_message' ) );
		add_action( 'wp_ajax_hs_get_conversation', array( __CLASS__, 'get_conversation' ) );
		add_action( 'wp_ajax_hs_mark_messages_read', array( __CLASS__, 'mark_read' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'لطفاً وارد شوید.' ) );
		}
	}

	public static function add_announcement() {
		self::verify();
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$body     = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		$pinned   = ! empty( $_POST['pinned'] );

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		if ( empty( $body ) && empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'متن اطلاعیه را وارد کنید.' ) );
		}

		$id = HS_Comms_DB::add_announcement( $class_id, $title, $body, get_current_user_id(), $pinned );
		$user = wp_get_current_user();

		wp_send_json_success( array(
			'message' => 'اطلاعیه ثبت شد.',
			'item'    => array(
				'id'         => $id,
				'title'      => $title,
				'body'       => $body,
				'is_pinned'  => $pinned ? 1 : 0,
				'author'     => $user->display_name,
				'created_at' => current_time( 'mysql' ),
			),
		) );
	}

	public static function delete_announcement() {
		self::verify();
		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $id || ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		HS_Comms_DB::delete_announcement( $id );
		wp_send_json_success( array( 'message' => 'اطلاعیه حذف شد.' ) );
	}

	public static function add_feed_post() {
		self::verify();
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$body     = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		if ( empty( trim( wp_strip_all_tags( $body ) ) ) ) {
			wp_send_json_error( array( 'message' => 'متن پست را وارد کنید.' ) );
		}

		$id = HS_Comms_DB::add_feed_post( $class_id, $body );
		$user = wp_get_current_user();

		wp_send_json_success( array(
			'message' => 'در فید کلاس منتشر شد.',
			'item'    => array(
				'id'         => $id,
				'body'       => $body,
				'author'     => $user->display_name,
				'created_at' => current_time( 'mysql' ),
			),
		) );
	}

	public static function delete_feed_post() {
		self::verify();
		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $id || ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		HS_Comms_DB::delete_feed_post( $id );
		wp_send_json_success( array( 'message' => 'پست حذف شد.' ) );
	}

	/**
	 * آیا فرستنده مجاز است به گیرنده پیام بدهد؟
	 * مدیر سایت: همه. معلم/مدیر مدرسه: دانش‌آموزان و والدین کلاس‌های خود.
	 * والد/دانش‌آموز: فقط معلم کلاس‌های فرزند/خودش. همچنین پاسخ به کسی که قبلاً پیام داده است.
	 */
	private static function can_message( $sender, $recipient ) {
		$sender    = absint( $sender );
		$recipient = absint( $recipient );
		if ( ! $sender || ! $recipient || $sender === $recipient || ! get_userdata( $recipient ) ) {
			return false;
		}
		if ( user_can( $sender, 'manage_options' ) ) {
			return true;
		}
		// پاسخ به مکالمه‌ای که طرف مقابل آغاز کرده
		global $wpdb;
		$t = HS_Comms_DB::messages_table();
		if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE sender_id = %d AND recipient_id = %d", $recipient, $sender ) ) > 0 ) { // phpcs:ignore
			return true;
		}
		if ( HS_Roles::is_teacher_or_admin( $sender ) ) {
			$cids = get_posts( array(
				'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 300, 'no_found_rows' => true,
			) );
			foreach ( $cids as $cid ) {
				if ( ! HS_Roles::can_manage_class( $cid, $sender ) ) {
					continue;
				}
				$ids = get_post_meta( $cid, '_hs_students', true );
				$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
				if ( in_array( $recipient, $ids, true ) ) {
					return true;
				}
				foreach ( $ids as $sid ) {
					if ( in_array( $recipient, HS_Parent_Link::get_parents( $sid ), true ) ) {
						return true;
					}
				}
			}
			return false;
		}
		// والد یا دانش‌آموز → فقط معلم‌های مرتبط
		$students = HS_Parent_Link::get_children( $sender );
		$students[] = $sender;
		foreach ( $students as $sid ) {
			foreach ( HS_Parent_Link::student_classes( $sid ) as $c ) {
				if ( (int) $c['teacher_id'] === $recipient ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function send_message() {
		self::verify();
		$recipient_id = isset( $_POST['recipient_id'] ) ? absint( $_POST['recipient_id'] ) : 0;
		$body         = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		$class_id     = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$student_id   = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;
		$media_url    = isset( $_POST['media_url'] ) ? esc_url_raw( wp_unslash( $_POST['media_url'] ) ) : '';
		if ( $media_url && 0 !== strpos( $media_url, wp_upload_dir()['baseurl'] . '/' ) ) {
			$media_url = ''; // فقط عکس‌های آپلودشده در همین سایت
		}

		if ( ! $recipient_id || ( '' === $body && '' === $media_url ) ) {
			wp_send_json_error( array( 'message' => 'گیرنده و متن یا عکس پیام الزامی است.' ) );
		}

		$sender_id = get_current_user_id();
		if ( ! self::can_message( $sender_id, $recipient_id ) ) {
			wp_send_json_error( array( 'message' => 'شما اجازه ارسال پیام به این کاربر را ندارید.' ), 403 );
		}
		$id = HS_Comms_DB::send_message( $sender_id, $recipient_id, $body, $class_id, $student_id, $media_url );

		wp_send_json_success( array(
			'message' => 'پیام ارسال شد.',
			'item'    => array(
				'id'           => $id,
				'body'         => $body,
				'media_url'    => $media_url,
				'sender_id'    => $sender_id,
				'recipient_id' => $recipient_id,
				'created_at'   => current_time( 'mysql' ),
			),
		) );
	}

	public static function get_conversation() {
		self::verify();
		$other_id = isset( $_POST['other_id'] ) ? absint( $_POST['other_id'] ) : 0;
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $other_id ) {
			wp_send_json_error( array( 'message' => 'مخاطب نامعتبر.' ) );
		}

		$me = get_current_user_id();
		if ( ! self::can_message( $me, $other_id ) && ! self::can_message( $other_id, $me ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی به این گفتگو مجاز نیست.' ), 403 );
		}
		$msgs = HS_Comms_DB::get_conversation( $me, $other_id, $class_id );
		HS_Comms_DB::mark_read( $me, $other_id );

		$items = array();
		foreach ( $msgs as $m ) {
			$items[] = array(
				'id'         => (int) $m['id'],
				'body'       => $m['body'],
				'media_url'  => isset( $m['media_url'] ) ? (string) $m['media_url'] : '',
				'is_mine'    => (int) $m['sender_id'] === $me,
				'created_at' => $m['created_at'],
			);
		}

		$other = get_userdata( $other_id );
		wp_send_json_success( array(
			'other_name' => $other ? $other->display_name : '',
			'messages'   => $items,
		) );
	}

	public static function mark_read() {
		self::verify();
		$other_id = isset( $_POST['other_id'] ) ? absint( $_POST['other_id'] ) : 0;
		if ( $other_id ) {
			HS_Comms_DB::mark_read( get_current_user_id(), $other_id );
		}
		wp_send_json_success();
	}
}

HS_Comms_Handlers::init();
