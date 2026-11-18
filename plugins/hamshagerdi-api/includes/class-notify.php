<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * اعلان‌ها: مرکز اعلان داخل اپ (جدول جدا) + ارسال Push با Firebase Cloud Messaging (HTTP v1).
 *
 * کلید سرور Firebase (فایل JSON حساب سرویس) فقط در پیشخوان ← تنظیمات ← «اعلان اپ» یا ثابت HS_API_FCM_JSON
 * در wp-config ذخیره می‌شود؛ هرگز در کد یا گیت نیست. تا تنظیم نشود، فقط مرکز اعلان داخل اپ کار می‌کند.
 *
 * هر اعلان فقط برای کسی ساخته می‌شود که اجازه‌ی دیدن همان داده را دارد (مجوزهای والدین رعایت می‌شود).
 */
class HS_API_Notify {
	const DB_VER = '1';
	const OPT    = 'hs_api_fcm_json';

	public static function t() {
		global $wpdb;
		return $wpdb->prefix . 'hs_api_notifications';
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'hs_api_attendance_saved', array( __CLASS__, 'on_attendance' ), 10, 4 );
		// هم از اپ و هم از سایت (افزونه‌ی مدیریت کلاس ۱.۷.۴+)؛ تکراری‌ها یک بار حساب می‌شوند
		add_action( 'hs_api_homework_created', array( __CLASS__, 'on_homework' ), 10, 3 );
		add_action( 'hs_homework_created', array( __CLASS__, 'on_homework' ), 10, 3 );
		add_action( 'hs_api_announcement_created', array( __CLASS__, 'on_announcement' ), 10, 3 );
		add_action( 'hs_announcement_created', array( __CLASS__, 'on_announcement' ), 10, 3 );
		// هر پیام خصوصی (اپ، سایت، پیام گروهی معلم)
		add_action( 'hs_message_sent', array( __CLASS__, 'on_site_message' ), 10, 6 );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 0 );
		add_action( 'hs_api_points_awarded_to', array( __CLASS__, 'on_points' ), 10, 4 );
		add_action( 'hs_api_push', array( __CLASS__, 'push_now' ), 10, 1 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_hs_api_fcm_save', array( __CLASS__, 'admin_save' ) );
	}

	public static function maybe_install() {
		if ( self::DB_VER === get_option( 'hs_api_notify_db' ) ) { return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::t() . ' (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(30) NOT NULL DEFAULT \'\',
			title varchar(190) NOT NULL DEFAULT \'\',
			body varchar(500) NOT NULL DEFAULT \'\',
			data text NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_read (user_id, is_read),
			KEY created_at (created_at)
		) ' . $wpdb->get_charset_collate() . ';' );
		update_option( 'hs_api_notify_db', self::DB_VER, false );
		HS_API_Routes_More::install_cheers();
	}

	/* ---------- ساخت اعلان ---------- */

	public static function add( $user_ids, $type, $title, $body, $data = array() ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $user_ids ) ) ) );
		if ( ! $ids ) { return; }
		$now  = current_time( 'mysql' );
		$push = array();
		foreach ( $ids as $uid ) {
			$wpdb->insert( self::t(), array(
				'user_id' => $uid, 'type' => sanitize_key( $type ), 'title' => mb_substr( $title, 0, 190 ), 'body' => mb_substr( $body, 0, 500 ),
				'data' => wp_json_encode( $data ), 'is_read' => 0, 'created_at' => $now,
			) );
			$push[] = (int) $wpdb->insert_id;
		}
		self::queue( $push );
	}

	/** شناسه‌های اعلانی که باید به گوشی فرستاده شوند (در پایان همین درخواست) */
	private static $queue = array();
	private static $seen  = array();

	private static function queue( $ids ) {
		if ( $ids && self::fcm_ready() ) {
			self::$queue = array_merge( self::$queue, $ids );
		}
	}

	/** یک رویداد را فقط یک بار حساب کن (اپ و سایت هر دو خبر می‌دهند) */
	private static function once( $key ) {
		if ( isset( self::$seen[ $key ] ) ) { return false; }
		self::$seen[ $key ] = true;
		return true;
	}

	/**
	 * ارسال فوری بعد از فرستادن پاسخ (نه با کران وردپرس که در سایت کم‌بازدید دیر اجرا می‌شود)
	 */
	public static function flush() {
		if ( ! self::$queue ) { return; }
		$ids         = array_values( array_unique( self::$queue ) );
		self::$queue = array();
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}
		ignore_user_abort( true );
		self::push_now( $ids );
	}

	/** والدینی از این دانش‌آموز که این بخش برایشان باز است */
	private static function parents_for( $sid, $feature, $cid ) {
		$out = array();
		if ( ! class_exists( 'HS_Parent_Link' ) ) { return $out; }
		foreach ( HS_Parent_Link::get_parents( $sid ) as $pid ) {
			if ( HS_API_Access::parent_can( $pid, $sid, $feature, $cid ) ) { $out[] = (int) $pid; }
		}
		return $out;
	}

	private static function first( $sid ) {
		$u = get_userdata( $sid );
		if ( ! $u ) { return ''; }
		return trim( (string) $u->first_name ) ? $u->first_name : $u->display_name;
	}

	public static function on_attendance( $cid, $date, $batch, $by ) {
		$labels = array( 'absent' => 'غایب', 'late' => 'با تأخیر حاضر' );
		foreach ( (array) $batch as $sid => $r ) {
			$st = $r['status'] ?? '';
			if ( ! isset( $labels[ $st ] ) ) { continue; }
			self::add( self::parents_for( $sid, 'attendance', $cid ), 'attendance', 'حضور و غیاب', self::first( $sid ) . ' امروز ' . $labels[ $st ] . ' بود.', array( 'child' => (int) $sid, 'class' => (int) $cid ) );
		}
	}

	public static function on_homework( $hid, $cid, $by ) {
		if ( ! self::once( 'hw' . $hid ) ) { return; }
		$h     = class_exists( 'HS_Homework_DB' ) ? HS_Homework_DB::get( $hid ) : null;
		$title = $h ? (string) $h['title'] : 'تکلیف تازه';
		$users = array();
		foreach ( HS_API_Access::class_members( $cid ) as $sid ) {
			$users[] = $sid;
			$users   = array_merge( $users, self::parents_for( $sid, 'homework', $cid ) );
		}
		self::add( $users, 'homework', 'تکلیف تازه — ' . get_the_title( $cid ), $title, array( 'class' => (int) $cid, 'homework' => (int) $hid ) );
	}

	public static function on_announcement( $aid, $cid, $by ) {
		if ( ! self::once( 'ann' . $aid ) ) { return; }
		$users = array();
		foreach ( HS_API_Access::class_members( $cid ) as $sid ) {
			$users = array_merge( $users, self::parents_for( $sid, 'announce', $cid ) );
		}
		global $wpdb;
		$a = class_exists( 'HS_Comms_DB' ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT title FROM ' . HS_Comms_DB::announcements_table() . ' WHERE id = %d', $aid ), ARRAY_A ) : null; // phpcs:ignore
		self::add( $users, 'announce', 'اطلاعیه‌ی ' . get_the_title( $cid ), $a ? (string) $a['title'] : 'اطلاعیه‌ی تازه', array( 'class' => (int) $cid ) );
	}

	public static function on_site_message( $from, $to, $body, $cid = 0, $sid = 0, $mid = 0 ) {
		self::on_message( $mid, $from, $to, $body );
	}

	/**
	 * پیام‌های یک فرستنده در یک اعلان جمع می‌شوند: «پیام از فلانی — ۵ پیام جدید»
	 */
	public static function on_message( $mid, $from, $to, $body = '' ) {
		global $wpdb;
		$from = (int) $from;
		$to   = (int) $to;
		if ( ! $from || ! $to || ! self::once( 'msg' . $mid . '_' . $to ) ) { return; }
		$u     = get_userdata( $from );
		$name  = $u ? $u->display_name : '';
		$label = HS_API_Routes_Teach::partner_label( $to, $from );
		$title = 'پیام از ' . $name . ( $label ? ' (' . $label . ')' : '' );
		$prev  = trim( wp_strip_all_tags( (string) $body ) );
		$prev  = mb_strlen( $prev ) > 80 ? mb_substr( $prev, 0, 80 ) . '…' : $prev;
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT id, data FROM ' . self::t() . " WHERE user_id = %d AND type = 'message' AND is_read = 0 AND data LIKE %s ORDER BY id DESC LIMIT 1", $to, '%"user":' . $from . '%' ), ARRAY_A ); // phpcs:ignore
		if ( $row ) {
			$d      = json_decode( (string) $row['data'], true );
			$d      = is_array( $d ) ? $d : array();
			$n      = max( 1, (int) ( $d['count'] ?? 1 ) ) + 1;
			$d['count'] = $n;
			$wpdb->update( self::t(), array(
				'title'      => mb_substr( $title, 0, 190 ),
				'body'       => mb_substr( self::fa( $n ) . ' پیام جدید' . ( $prev ? ' — آخرین: ' . $prev : '' ), 0, 500 ),
				'data'       => wp_json_encode( $d ),
				'created_at' => current_time( 'mysql' ),
			), array( 'id' => (int) $row['id'] ) );
			self::queue( array( (int) $row['id'] ) );
			return;
		}
		self::add( array( $to ), 'message', $title, $prev ? $prev : 'پیام تازه', array( 'user' => $from, 'count' => 1 ) );
	}

	public static function fa( $n ) {
		return strtr( (string) $n, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/** خواندن گفت‌وگو ← اعلان پیام‌های همان فرستنده خوانده شود */
	public static function read_messages_from( $uid, $from ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET is_read = 1 WHERE user_id = %d AND type = 'message' AND is_read = 0 AND data LIKE %s", (int) $uid, '%"user":' . (int) $from . '%' ) ); // phpcs:ignore
	}

	/** فقط امتیاز منفی به والدین خبر داده می‌شود (امتیاز مثبت در خلاصه‌ی روز دیده می‌شود) */
	public static function on_points( $cid, $sid, $points, $behavior ) {
		if ( (int) $points >= 0 ) { return; }
		self::add( self::parents_for( $sid, 'points', $cid ), 'points', 'امتیاز رفتاری', self::first( $sid ) . ': ' . $behavior . ' (' . (int) $points . ')', array( 'child' => (int) $sid, 'class' => (int) $cid ) );
	}

	/* ---------- خواندن (برای اپ) ---------- */

	public static function unread( $uid ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE user_id = %d AND is_read = 0', $uid ) ); // phpcs:ignore
	}

	public static function list_for( $uid, $limit = 50 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, title, body, data, is_read, created_at FROM ' . self::t() . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d', $uid, $limit ), ARRAY_A ); // phpcs:ignore
		return array_map( function ( $r ) {
			$r['id']      = (int) $r['id'];
			$r['is_read'] = (bool) $r['is_read'];
			$r['data']    = json_decode( (string) $r['data'], true ) ?: new stdClass();
			return $r;
		}, (array) $rows );
	}

	public static function mark_read( $uid, $ids = array() ) {
		global $wpdb;
		if ( $ids ) {
			$in = implode( ',', array_map( 'intval', $ids ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET is_read = 1 WHERE user_id = %d AND id IN ($in)", $uid ) ); // phpcs:ignore
		} else {
			$wpdb->update( self::t(), array( 'is_read' => 1 ), array( 'user_id' => $uid ) );
		}
	}

	/* ---------- Firebase Cloud Messaging ---------- */

	private static function sa() {
		$raw = defined( 'HS_API_FCM_JSON' ) ? HS_API_FCM_JSON : (string) get_option( self::OPT, '' );
		$j   = json_decode( (string) $raw, true );
		return is_array( $j ) && ! empty( $j['private_key'] ) && ! empty( $j['client_email'] ) && ! empty( $j['project_id'] ) ? $j : null;
	}

	public static function fcm_ready() {
		return null !== self::sa() && function_exists( 'openssl_sign' );
	}

	private static function access_token() {
		$cached = get_transient( 'hs_api_fcm_tok' );
		if ( $cached ) { return $cached; }
		$sa  = self::sa();
		$now = time();
		$h   = HS_API_JWT::b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$c   = HS_API_JWT::b64( wp_json_encode( array( 'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600 ) ) );
		$sig = '';
		if ( ! openssl_sign( $h . '.' . $c, $sig, $sa['private_key'], 'sha256WithRSAEncryption' ) ) { return ''; }
		$res = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 15,
			'body'    => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $h . '.' . $c . '.' . HS_API_JWT::b64( $sig ) ),
		) );
		$j = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $j['access_token'] ) ) { return ''; }
		set_transient( 'hs_api_fcm_tok', $j['access_token'], 50 * MINUTE_IN_SECONDS );
		return $j['access_token'];
	}

	public static function push_now( $ids ) {
		global $wpdb;
		$sa = self::sa();
		if ( ! $sa ) { return; }
		$tok = self::access_token();
		if ( ! $tok ) {
			update_option( 'hs_api_fcm_last', array( 'at' => current_time( 'mysql' ), 'ok' => false, 'msg' => 'گرفتن مجوز از گوگل ناموفق بود (کلید سرور یا اتصال سرور به گوگل را بررسی کنید).' ), false );
			return;
		}
		$in   = implode( ',', array_map( 'intval', (array) $ids ) );
		$rows = $in ? $wpdb->get_results( 'SELECT id, user_id, type, title, body, data FROM ' . self::t() . " WHERE id IN ($in)", ARRAY_A ) : array(); // phpcs:ignore
		$url  = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $sa['project_id'] ) . '/messages:send';
		foreach ( (array) $rows as $n ) {
			foreach ( HS_API_Devices::tokens( (int) $n['user_id'], 'fcm' ) as $device ) {
				$res  = wp_remote_post( $url, array(
					'timeout' => 10,
					'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( array( 'message' => array(
						'token'        => $device,
						'notification' => array( 'title' => $n['title'], 'body' => $n['body'] ),
						'data'         => array( 'type' => (string) $n['type'], 'id' => (string) $n['id'], 'data' => (string) $n['data'] ),
						// برچسب ثابت: اعلان تازه‌ی همان فرستنده جای قبلی را می‌گیرد (نه زیر هم)
						'android'      => array( 'priority' => 'high', 'notification' => array( 'channel_id' => 'hs_default', 'tag' => 'hs' . (int) $n['id'] ) ),
					) ) ),
				) );
				$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
				$msg  = 200 === $code ? 'ارسال شد' : ( is_wp_error( $res ) ? $res->get_error_message() : 'کد ' . $code . ': ' . substr( (string) wp_remote_retrieve_body( $res ), 0, 200 ) );
				update_option( 'hs_api_fcm_last', array( 'at' => current_time( 'mysql' ), 'ok' => 200 === $code, 'msg' => $msg ), false );
				if ( 404 === $code || 400 === $code ) {
					HS_API_Devices::remove( (int) $n['user_id'], $device ); // توکن کهنه
				}
			}
		}
	}

	/* ---------- پیشخوان: کلید سرور ---------- */

	public static function admin_menu() {
		add_options_page( 'اعلان اپ', 'اعلان اپ', 'manage_options', 'hs-api-fcm', array( __CLASS__, 'admin_page' ) );
	}

	public static function admin_page() {
		$ok = self::fcm_ready();
		echo '<div class="wrap" dir="rtl"><h1>اعلان اپ (Firebase)</h1>';
		if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>ذخیره شد.</p></div>'; } // phpcs:ignore
		echo '<p>وضعیت: ' . ( $ok ? '✅ فعال' : '⏸ تنظیم نشده (فقط مرکز اعلان داخل اپ کار می‌کند)' ) . '</p>';
		$last = get_option( 'hs_api_fcm_last' );
		if ( is_array( $last ) ) {
			echo '<p>آخرین ارسال: ' . esc_html( (string) $last['at'] ) . ' — ' . ( ! empty( $last['ok'] ) ? '✅ ' : '⚠️ ' ) . esc_html( (string) $last['msg'] ) . '</p>';
		}
		if ( defined( 'HS_API_FCM_JSON' ) ) {
			echo '<p>کلید از ثابت HS_API_FCM_JSON در wp-config خوانده می‌شود.</p></div>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'hs_api_fcm' );
		echo '<input type="hidden" name="action" value="hs_api_fcm_save">';
		echo '<p>محتوای فایل JSON «حساب سرویس» (Service account) پروژه‌ی Firebase را اینجا بچسبانید. محتوای فعلی برای امنیت نمایش داده نمی‌شود.</p>';
		echo '<textarea name="fcm_json" rows="10" class="large-text code" dir="ltr" placeholder="{ &quot;type&quot;: &quot;service_account&quot;, ... }"></textarea>';
		echo '<p><label><input type="checkbox" name="clear" value="1"> پاک کردن کلید فعلی</label></p>';
		submit_button( 'ذخیره' );
		echo '</form></div>';
	}

	public static function admin_save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
		check_admin_referer( 'hs_api_fcm' );
		if ( ! empty( $_POST['clear'] ) ) {
			delete_option( self::OPT );
		} else {
			$raw = isset( $_POST['fcm_json'] ) ? trim( (string) wp_unslash( $_POST['fcm_json'] ) ) : ''; // phpcs:ignore
			$j   = json_decode( $raw, true );
			if ( is_array( $j ) && ! empty( $j['private_key'] ) && ! empty( $j['client_email'] ) && ! empty( $j['project_id'] ) ) {
				update_option( self::OPT, $raw, false );
			}
		}
		delete_transient( 'hs_api_fcm_tok' );
		wp_safe_redirect( admin_url( 'options-general.php?page=hs-api-fcm&saved=1' ) );
		exit;
	}
}
