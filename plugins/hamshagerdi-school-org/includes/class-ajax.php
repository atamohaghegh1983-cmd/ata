<?php
/**
 * AJAX پنل مدرسه
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_SO_Ajax {

	public static function init() {
		foreach ( array( 'save_school', 'staff_add', 'staff_update', 'staff_remove', 'task_save', 'task_delete', 'task_status', 'class_detail' ) as $a ) {
			add_action( 'wp_ajax_hs_so_' . $a, array( __CLASS__, $a ) );
		}
	}

	private static function fail( $msg, $code = 400 ) {
		wp_send_json_error( array( 'message' => $msg ), $code );
	}

	/** بررسی نشست؛ شناسهٔ مدرسهٔ کاربر را برمی‌گرداند */
	private static function ctx() {
		if ( ! is_user_logged_in() || ! check_ajax_referer( 'hs_so_nonce', 'nonce', false ) ) {
			self::fail( 'نشست نامعتبر است. صفحه را رفرش کنید.', 403 );
		}
		$sid = isset( $_POST['school_id'] ) ? absint( $_POST['school_id'] ) : 0;
		if ( $sid && ! HS_SO_Store::is_admin_user() ) {
			$mine = HS_SO_Store::school_id_for_user( get_current_user_id() );
			if ( $mine !== $sid ) {
				self::fail( 'دسترسی غیرمجاز.', 403 );
			}
		}
		return $sid ? $sid : HS_SO_Store::school_id_for_user( get_current_user_id() );
	}

	private static function manager_ctx() {
		$sid = self::ctx();
		if ( ! $sid || ! HS_SO_Store::is_manager( $sid ) ) {
			self::fail( 'فقط مدیر مدرسه می‌تواند این کار را انجام دهد.', 403 );
		}
		return $sid;
	}

	private static function post( $k ) {
		return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	public static function save_school() {
		if ( ! is_user_logged_in() || ! check_ajax_referer( 'hs_so_nonce', 'nonce', false ) ) {
			self::fail( 'نشست نامعتبر است.', 403 );
		}
		$uid = get_current_user_id();
		$sid = HS_SO_Store::school_id_for_user( $uid );
		if ( $sid ) {
			if ( ! HS_SO_Store::is_manager( $sid ) ) {
				self::fail( 'فقط مدیر مدرسه می‌تواند اطلاعات را ویرایش کند.', 403 );
			}
		} else {
			$u = wp_get_current_user();
			if ( ! array_intersect( (array) $u->roles, array( 'school_admin', 'administrator' ) ) ) {
				self::fail( 'فقط مدیر مدرسه می‌تواند مدرسه را معرفی کند.', 403 );
			}
			if ( HS_SO_Store::staff_row_by_user( $uid ) ) {
				self::fail( 'شما عضو کادر یک مدرسه هستید.', 403 );
			}
		}
		$data = array();
		foreach ( array( 'name', 'province', 'city', 'address', 'postal', 'code', 'phone', 'level' ) as $k ) {
			$data[ $k ] = self::post( $k );
		}
		$pc_new = preg_replace( '/\D/', '', HS_SO_Store::en( self::post( 'personnel_code' ) ) );
		if ( ! HS_SO_Store::is_admin_user() && strlen( $pc_new ) < 6 ) {
			self::fail( 'کد پرسنلی معتبر وارد کنید (فقط عدد).' );
		}
		$res = HS_SO_Store::save_school( $sid, $data, $uid );
		if ( is_wp_error( $res ) ) {
			self::fail( $res->get_error_message() );
		}
		$pc_old = (string) get_user_meta( $uid, '_hs_personnel_code', true );
		$recheck = false;
		if ( $pc_new && $pc_new !== $pc_old ) {
			update_user_meta( $uid, '_hs_personnel_code', $pc_new );
			// کد پرسنلی عوض شد: نقش مدیر تا بررسی دوباره‌ی مدیر سایت معلق می‌شود
			$u = wp_get_current_user();
			if ( $pc_old && in_array( 'school_admin', (array) $u->roles, true ) && ! HS_SO_Store::is_admin_user() && class_exists( 'HSUM_Membership' ) ) {
				$u->remove_role( 'school_admin' );
				HSUM_Membership::request_role( $uid, 'school_admin' );
				$recheck = true;
			}
		}
		wp_send_json_success( array( 'message' => $recheck ? 'ذخیره شد. چون کد پرسنلی تغییر کرد، نقش مدیر تا تأیید دوباره‌ی مدیر سایت معلق است.' : 'اطلاعات مدرسه ذخیره شد ✅', 'school_id' => $res, 'new' => ! $sid ) );
	}

	/* ---------------- کادر ---------------- */

	private static function new_username( $phone, $first ) {
		$base = $phone ? $phone : 'staff' . wp_rand( 10000, 99999 );
		$name = $base;
		$i    = 1;
		while ( username_exists( $name ) ) {
			$name = $base . '-' . ( $i++ );
		}
		return $name;
	}

	public static function staff_add() {
		$sid      = self::manager_ctx();
		$position = sanitize_key( self::post( 'position' ) );
		$title    = sanitize_text_field( self::post( 'title' ) );
		$can_view = self::post( 'can_view' ) ? 1 : 0;
		$mode     = 'existing' === self::post( 'mode' ) ? 'existing' : 'new';
		$creds    = null;

		if ( 'existing' === $mode ) {
			$q = trim( sanitize_text_field( self::post( 'lookup' ) ) );
			if ( '' === $q ) {
				self::fail( 'نام کاربری یا ایمیل را وارد کنید.' );
			}
			$user = get_user_by( 'login', $q );
			if ( ! $user && is_email( $q ) ) {
				$user = get_user_by( 'email', $q );
			}
			if ( ! $user ) {
				self::fail( 'کاربری با این مشخصات پیدا نشد.' );
			}
			$roles = (array) $user->roles;
			if ( array_intersect( $roles, array( 'student' ) ) ) {
				self::fail( 'دانش‌آموز را نمی‌توان به کادر افزود.' );
			}
			if ( array_intersect( $roles, array( 'administrator', 'school_admin' ) ) ) {
				self::fail( 'این کاربر مدیر است و به‌عنوان کادر قابل افزودن نیست.' );
			}
			$uid = (int) $user->ID;
			if ( ! in_array( 'teacher', $roles, true ) ) {
				$user->add_role( 'teacher' );
			}
		} else {
			$first = sanitize_text_field( self::post( 'first_name' ) );
			$last  = sanitize_text_field( self::post( 'last_name' ) );
			$phone = preg_replace( '/\D/', '', HS_SO_Store::en( self::post( 'phone' ) ) );
			if ( '' === trim( $first . $last ) ) {
				self::fail( 'نام و نام خانوادگی را وارد کنید.' );
			}
			$login = self::new_username( $phone, $first );
			$pass  = wp_generate_password( 12, true, false );
			$uid   = wp_insert_user( array(
				'user_login'   => $login,
				'user_pass'    => $pass,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ),
				'role'         => 'teacher',
			) );
			if ( is_wp_error( $uid ) ) {
				self::fail( $uid->get_error_message() );
			}
			update_user_meta( $uid, '_hs_temp_pass', 1 );
			if ( $phone ) {
				update_user_meta( $uid, '_hs_phone', $phone );
			}
			$creds = array( 'username' => $login, 'password' => $pass );
		}

		$res = HS_SO_Store::add_staff( $sid, $uid, $position, $title, $can_view );
		if ( is_wp_error( $res ) ) {
			if ( 'new' === $mode ) {
				wp_delete_user( $uid );
			}
			self::fail( $res->get_error_message() );
		}
		wp_send_json_success( array( 'message' => 'به کادر مدرسه اضافه شد ✅', 'creds' => $creds ) );
	}

	public static function staff_update() {
		$sid = self::manager_ctx();
		$row = HS_SO_Store::staff_row( absint( self::post( 'id' ) ) );
		if ( ! $row || (int) $row['school_id'] !== $sid ) {
			self::fail( 'عضو کادر پیدا نشد.' );
		}
		$pos = sanitize_key( self::post( 'position' ) );
		if ( ! isset( HS_SO_Structure::positions()[ $pos ] ) ) {
			$pos = $row['position'];
		}
		global $wpdb;
		$wpdb->update( HS_SO_Store::staff_table(), array(
			'position' => $pos,
			'title'    => sanitize_text_field( self::post( 'title' ) ),
			'job_desc' => sanitize_textarea_field( self::post( 'job_desc' ) ),
			'can_view' => self::post( 'can_view' ) ? 1 : 0,
		), array( 'id' => $row['id'] ) );
		wp_send_json_success( array( 'message' => 'ذخیره شد ✅' ) );
	}

	public static function staff_remove() {
		$sid = self::manager_ctx();
		$row = HS_SO_Store::staff_row( absint( self::post( 'id' ) ) );
		if ( ! $row || (int) $row['school_id'] !== $sid ) {
			self::fail( 'عضو کادر پیدا نشد.' );
		}
		HS_SO_Store::remove_staff( $row['id'] ); // حساب کاربری و کلاس‌های او دست‌نخورده می‌ماند
		wp_send_json_success( array( 'message' => 'از کادر مدرسه حذف شد.' ) );
	}

	/* ---------------- وظایف ---------------- */

	public static function task_save() {
		$sid   = self::manager_ctx();
		$id    = absint( self::post( 'id' ) );
		$title = sanitize_text_field( self::post( 'title' ) );
		$to    = absint( self::post( 'assignee_id' ) );
		if ( '' === $title ) {
			self::fail( 'عنوان وظیفه را وارد کنید.' );
		}
		$row = HS_SO_Store::staff_row_by_user( $to );
		if ( ! $row || (int) $row['school_id'] !== $sid ) {
			self::fail( 'مسئول انجام وظیفه را از کادر مدرسه انتخاب کنید.' );
		}
		$due = sanitize_text_field( self::post( 'due_date' ) );
		$due = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ? $due : null;
		$dom = sanitize_key( self::post( 'domain' ) );
		$pri = sanitize_key( self::post( 'priority' ) );
		$data = array(
			'title'       => $title,
			'description' => sanitize_textarea_field( self::post( 'description' ) ),
			'domain'      => isset( HS_SO_Structure::domains()[ $dom ] ) ? $dom : '',
			'assignee_id' => $to,
			'due_date'    => $due,
			'priority'    => isset( HS_SO_Structure::priorities()[ $pri ] ) ? $pri : 'normal',
			'updated_at'  => current_time( 'mysql' ),
		);
		global $wpdb;
		if ( $id ) {
			$old = HS_SO_Store::task( $id );
			if ( ! $old || (int) $old['school_id'] !== $sid ) {
				self::fail( 'وظیفه پیدا نشد.' );
			}
			$wpdb->update( HS_SO_Store::tasks_table(), $data, array( 'id' => $id ) );
		} else {
			$data['school_id']   = $sid;
			$data['assigned_by'] = get_current_user_id();
			$data['status']      = 'todo';
			$wpdb->insert( HS_SO_Store::tasks_table(), $data );
			$id = (int) $wpdb->insert_id;
			do_action( 'hs_so_task_assigned', $id, $to, $sid );
		}
		wp_send_json_success( array( 'message' => 'وظیفه ذخیره شد ✅', 'id' => $id ) );
	}

	public static function task_delete() {
		$sid = self::manager_ctx();
		$t   = HS_SO_Store::task( absint( self::post( 'id' ) ) );
		if ( ! $t || (int) $t['school_id'] !== $sid ) {
			self::fail( 'وظیفه پیدا نشد.' );
		}
		global $wpdb;
		$wpdb->delete( HS_SO_Store::tasks_table(), array( 'id' => $t['id'] ) );
		wp_send_json_success( array( 'message' => 'حذف شد.' ) );
	}

	/** مسئول انجام وظیفه وضعیت و گزارش خودش را به‌روز می‌کند (مدیر نمی‌تواند به‌جای او انجام دهد) */
	public static function task_status() {
		$sid = self::ctx();
		$t   = HS_SO_Store::task( absint( self::post( 'id' ) ) );
		if ( ! $t || (int) $t['school_id'] !== (int) $sid ) {
			self::fail( 'وظیفه پیدا نشد.' );
		}
		if ( (int) $t['assignee_id'] !== get_current_user_id() && ! HS_SO_Store::is_admin_user() ) {
			self::fail( 'فقط مسئول انجام وظیفه می‌تواند وضعیت را تغییر دهد.', 403 );
		}
		$st = sanitize_key( self::post( 'status' ) );
		if ( ! isset( HS_SO_Structure::statuses()[ $st ] ) ) {
			self::fail( 'وضعیت نامعتبر.' );
		}
		global $wpdb;
		$wpdb->update( HS_SO_Store::tasks_table(), array(
			'status'     => $st,
			'report'     => sanitize_textarea_field( self::post( 'report' ) ),
			'updated_at' => current_time( 'mysql' ),
			'done_at'    => 'done' === $st ? current_time( 'mysql' ) : null,
		), array( 'id' => $t['id'] ) );
		wp_send_json_success( array( 'message' => 'وضعیت وظیفه ثبت شد ✅' ) );
	}

	/* ---------------- نظارت ---------------- */

	public static function class_detail() {
		$sid = self::ctx();
		if ( ! $sid || ! HS_SO_Store::can_supervise( $sid ) ) {
			self::fail( 'دسترسی نظارت ندارید.', 403 );
		}
		$cid = absint( self::post( 'class_id' ) );
		if ( ! in_array( $cid, HS_SO_Monitor::class_ids( $sid ), true ) ) {
			self::fail( 'این کلاس متعلق به مدرسهٔ شما نیست.', 403 );
		}
		wp_send_json_success( array( 'title' => get_the_title( $cid ), 'html' => HS_SO_Monitor::class_detail_html( $cid ) ) );
	}
}
