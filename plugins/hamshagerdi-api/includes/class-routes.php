<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HS_API_Routes {

	/* ---------- ثبت مسیرها ---------- */

	public static function register() {
		$ns = HS_API_NS;
		$cls = array( 'id' => array( 'validate_callback' => 'is_numeric' ) );

		// احراز هویت
		register_rest_route( $ns, '/auth/login', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'login' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/auth/refresh', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'refresh' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( $ns, '/auth/logout', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'logout' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );
		register_rest_route( $ns, '/me', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'me' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );
		register_rest_route( $ns, '/devices', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'device' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );

		// کلاس‌ها
		register_rest_route( $ns, '/classes', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'classes' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );
		$s = array( HS_API_Access::WRITE, HS_API_Access::SUPER );                // کادر
		$all = array( HS_API_Access::WRITE, HS_API_Access::SUPER, HS_API_Access::STUDENT, HS_API_Access::PARENT );
		$w = array( HS_API_Access::WRITE );
		$cid = '/classes/(?P<id>\d+)';
		register_rest_route( $ns, $cid . '/students', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'students' ), 'permission_callback' => self::class_perm( $s ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'student_create' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/attendance', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'attendance_get' ), 'permission_callback' => self::class_perm( $s ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'attendance_save' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/homework', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'homework_list' ), 'permission_callback' => self::class_perm( $all ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'homework_create' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/announcements', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'ann_list' ), 'permission_callback' => self::class_perm( $all ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'ann_create' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/points', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'points_get' ), 'permission_callback' => self::class_perm( $s ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'points_award' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, '/homework/(?P<id>\d+)/statuses', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'hw_statuses' ), 'permission_callback' => array( __CLASS__, 'hw_perm_read' ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'hw_statuses_save' ), 'permission_callback' => array( __CLASS__, 'hw_perm_write' ) ),
		) );

		// والدین
		register_rest_route( $ns, '/children', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'children' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );
		register_rest_route( $ns, '/children/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'child' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );

		// پیام‌ها
		register_rest_route( $ns, '/messages', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'inbox' ), 'permission_callback' => array( __CLASS__, 'authed' ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'send' ), 'permission_callback' => array( __CLASS__, 'authed' ) ),
		) );
		register_rest_route( $ns, '/messages/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'thread' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );

		// مدرسه
		register_rest_route( $ns, '/school', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'school' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );
		register_rest_route( $ns, '/school/tasks', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'tasks' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );
		register_rest_route( $ns, '/school/tasks/(?P<id>\d+)/status', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'task_status' ), 'permission_callback' => array( __CLASS__, 'authed' ) ) );
	}

	/* ---------- دسترسی ---------- */

	public static function authed() {
		return is_user_logged_in() ? true : new WP_Error( 'hs_unauthorized', 'ابتدا وارد شوید.', array( 'status' => 401 ) );
	}

	private static function class_perm( array $levels ) {
		return function ( WP_REST_Request $r ) use ( $levels ) {
			if ( ! is_user_logged_in() ) { return new WP_Error( 'hs_unauthorized', 'ابتدا وارد شوید.', array( 'status' => 401 ) ); }
			$lv = HS_API_Access::class_level( (int) $r['id'], get_current_user_id() );
			if ( ! $lv || ! in_array( $lv, $levels, true ) ) { return new WP_Error( 'hs_forbidden', 'به این بخش دسترسی ندارید.', array( 'status' => 403 ) ); }
			$r->set_param( '_level', $lv );
			return true;
		};
	}

	private static function hw_class( $hid ) { $h = HS_Homework_DB::get( $hid ); return $h ? (int) $h['class_id'] : 0; }

	public static function hw_perm_read( WP_REST_Request $r ) {
		$lv = HS_API_Access::class_level( self::hw_class( (int) $r['id'] ), get_current_user_id() );
		return in_array( $lv, array( HS_API_Access::WRITE, HS_API_Access::SUPER ), true ) ? true : new WP_Error( 'hs_forbidden', 'دسترسی ندارید.', array( 'status' => 403 ) );
	}
	public static function hw_perm_write( WP_REST_Request $r ) {
		return HS_API_Access::WRITE === HS_API_Access::class_level( self::hw_class( (int) $r['id'] ), get_current_user_id() ) ? true : new WP_Error( 'hs_forbidden', 'دسترسی ندارید.', array( 'status' => 403 ) );
	}

	private static function err( $msg, $status = 400 ) { return new WP_Error( 'hs_error', $msg, array( 'status' => $status ) ); }
	private static function p( WP_REST_Request $r, $k, $d = '' ) { $v = $r->get_param( $k ); return null === $v ? $d : $v; }
	private static function date( $s ) { return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $s ) ? $s : ''; }

	/* ---------- احراز هویت ---------- */

	public static function login( WP_REST_Request $r ) {
		$res = HS_API_Auth::login( (string) self::p( $r, 'username' ), (string) self::p( $r, 'password' ), (string) self::p( $r, 'device' ) );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}
	public static function refresh( WP_REST_Request $r ) {
		$res = HS_API_Auth::refresh( (string) self::p( $r, 'refresh_token' ), (string) self::p( $r, 'device' ) );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}
	public static function logout( WP_REST_Request $r ) {
		HS_API_Auth::logout( get_current_user_id(), (string) self::p( $r, 'refresh_token' ), (bool) self::p( $r, 'all', false ) );
		return array( 'ok' => true );
	}
	public static function me() {
		$u = wp_get_current_user();
		$out = HS_API_Access::profile( $u );
		$out['unread_messages'] = class_exists( 'HS_Comms_DB' ) ? HS_Comms_DB::count_unread( $u->ID ) : 0;
		return $out;
	}
	public static function device( WP_REST_Request $r ) {
		$tok = sanitize_text_field( (string) self::p( $r, 'token' ) );
		if ( ! $tok ) { return self::err( 'توکن دستگاه لازم است.' ); }
		$list = (array) get_user_meta( get_current_user_id(), 'hs_api_devices', true );
		$list[ md5( $tok ) ] = array( 'token' => $tok, 'platform' => sanitize_key( (string) self::p( $r, 'platform', 'android' ) ), 'at' => time() );
		update_user_meta( get_current_user_id(), 'hs_api_devices', array_slice( $list, -5, 5, true ) );
		return array( 'ok' => true );
	}

	/* ---------- کلاس‌ها ---------- */

	private static function class_row( $cid, $uid ) {
		return array(
			'id'       => $cid,
			'title'    => get_the_title( $cid ),
			'grade'    => (string) get_post_meta( $cid, '_hs_grade', true ),
			'teacher'  => ( $t = get_userdata( (int) get_post_meta( $cid, '_hs_teacher_id', true ) ) ) ? $t->display_name : '',
			'students' => class_exists( 'HS_Class_Students' ) ? HS_Class_Students::count( $cid ) : 0,
			'access'   => HS_API_Access::class_level( $cid, $uid ),
		);
	}
	public static function classes() {
		$uid = get_current_user_id();
		return array_map( function ( $cid ) use ( $uid ) { return self::class_row( $cid, $uid ); }, HS_API_Access::class_ids( $uid ) );
	}
	public static function students( WP_REST_Request $r ) {
		return array_map( function ( $u ) { return array( 'id' => $u->ID, 'name' => $u->display_name, 'avatar' => get_avatar_url( $u->ID, array( 'size' => 96 ) ) ); }, HS_Class_Students::get_students( (int) $r['id'] ) );
	}

	/* ---------- حضور و غیاب ---------- */

	public static function attendance_get( WP_REST_Request $r ) {
		$cid  = (int) $r['id'];
		$date = self::date( self::p( $r, 'date' ) ) ?: current_time( 'Y-m-d' );
		$rec  = HS_Attendance_DB::get_by_class_date( $cid, $date );
		$out  = array();
		foreach ( HS_Class_Students::get_students( $cid ) as $u ) {
			$out[] = array( 'student_id' => $u->ID, 'name' => $u->display_name, 'status' => isset( $rec[ $u->ID ] ) ? $rec[ $u->ID ]['status'] : null, 'note' => isset( $rec[ $u->ID ] ) ? $rec[ $u->ID ]['note'] : '' );
		}
		return array( 'date' => $date, 'recorded' => (bool) $rec, 'records' => $out );
	}
	public static function attendance_save( WP_REST_Request $r ) {
		$cid  = (int) $r['id'];
		$date = self::date( self::p( $r, 'date' ) );
		$recs = self::p( $r, 'records', array() );
		if ( ! $date || ! is_array( $recs ) || ! $recs ) { return self::err( 'تاریخ (YYYY-MM-DD) و records لازم است.' ); }
		$members = array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) );
		$batch   = array();
		foreach ( $recs as $x ) {
			$sid = isset( $x['student_id'] ) ? (int) $x['student_id'] : 0;
			if ( ! in_array( $sid, $members, true ) ) { continue; } // فقط دانش‌آموزان همین کلاس
			$st = isset( $x['status'] ) ? sanitize_key( $x['status'] ) : 'present';
			$batch[ $sid ] = array( 'status' => in_array( $st, array( 'present', 'absent', 'late', 'excused' ), true ) ? $st : 'present', 'note' => isset( $x['note'] ) ? (string) $x['note'] : '' );
		}
		return array( 'saved' => HS_Attendance_DB::save_batch( $cid, $date, $batch ) );
	}

	/* ---------- تکالیف ---------- */

	public static function homework_list( WP_REST_Request $r ) {
		$cid = (int) $r['id']; $uid = get_current_user_id();
		$list = HS_Homework_DB::get_class_homework( $cid, 50 );
		$staff = in_array( $r['_level'], array( HS_API_Access::WRITE, HS_API_Access::SUPER ), true );
		$mine = array();
		if ( ! $staff ) {
			$mine = 'parent' === HS_API_Access::view( $uid ) ? ( class_exists( 'HS_Parent_Link' ) ? HS_Parent_Link::get_children( $uid ) : array() ) : array( $uid );
		}
		return array_map( function ( $h ) use ( $staff, $mine ) {
			$o = array( 'id' => (int) $h['id'], 'title' => $h['title'], 'description' => $h['description'], 'subject' => $h['subject'], 'due_date' => $h['due_date'] );
			if ( $staff ) { $o['summary'] = HS_Homework_DB::get_summary( $h['id'] ); }
			else {
				$st = HS_Homework_DB::get_statuses( $h['id'] ); $o['my_status'] = array();
				foreach ( $mine as $sid ) { if ( isset( $st[ $sid ] ) ) { $o['my_status'][ $sid ] = array( 'status' => $st[ $sid ]['status'], 'feedback' => $st[ $sid ]['feedback'] ); } }
			}
			return $o;
		}, $list );
	}
	public static function homework_create( WP_REST_Request $r ) {
		$title = sanitize_text_field( (string) self::p( $r, 'title' ) );
		if ( '' === $title ) { return self::err( 'عنوان تکلیف لازم است.' ); }
		$due = self::date( self::p( $r, 'due_date' ) );
		$id  = HS_Homework_DB::create( (int) $r['id'], $title, (string) self::p( $r, 'description' ), (string) self::p( $r, 'subject' ), $due ?: null );
		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}
	public static function hw_statuses( WP_REST_Request $r ) {
		$hid = (int) $r['id']; $st = HS_Homework_DB::get_statuses( $hid ); $out = array();
		foreach ( HS_Class_Students::get_students( self::hw_class( $hid ) ) as $u ) {
			$out[] = array( 'student_id' => $u->ID, 'name' => $u->display_name, 'status' => isset( $st[ $u->ID ] ) ? $st[ $u->ID ]['status'] : 'pending', 'feedback' => isset( $st[ $u->ID ] ) ? $st[ $u->ID ]['feedback'] : '' );
		}
		return $out;
	}
	public static function hw_statuses_save( WP_REST_Request $r ) {
		$hid = (int) $r['id']; $members = array_map( 'intval', (array) get_post_meta( self::hw_class( $hid ), '_hs_students', true ) );
		$batch = array(); $valid = array_keys( HS_Homework_DB::statuses() );
		foreach ( (array) self::p( $r, 'records', array() ) as $x ) {
			$sid = isset( $x['student_id'] ) ? (int) $x['student_id'] : 0; $st = isset( $x['status'] ) ? sanitize_key( $x['status'] ) : '';
			if ( in_array( $sid, $members, true ) && in_array( $st, $valid, true ) ) { $batch[ $sid ] = array( 'status' => $st, 'feedback' => isset( $x['feedback'] ) ? (string) $x['feedback'] : '' ); }
		}
		return array( 'saved' => HS_Homework_DB::set_status_batch( $hid, $batch ) );
	}

	/* ---------- اطلاعیه‌ها ---------- */

	public static function ann_list( WP_REST_Request $r ) {
		return array_map( function ( $a ) {
			$au = get_userdata( (int) $a['author_id'] );
			return array( 'id' => (int) $a['id'], 'title' => $a['title'], 'body' => wp_strip_all_tags( $a['body'] ), 'pinned' => (bool) $a['is_pinned'], 'author' => $au ? $au->display_name : '', 'created_at' => $a['created_at'] );
		}, HS_Comms_DB::get_announcements( (int) $r['id'], 30 ) );
	}
	public static function ann_create( WP_REST_Request $r ) {
		$title = sanitize_text_field( (string) self::p( $r, 'title' ) ); $body = (string) self::p( $r, 'body' );
		if ( '' === $title || '' === trim( $body ) ) { return self::err( 'عنوان و متن لازم است.' ); }
		$id = HS_Comms_DB::add_announcement( (int) $r['id'], $title, $body, get_current_user_id(), (bool) self::p( $r, 'pinned', false ) );
		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/* ---------- ثبت دانش‌آموز (همان تابع مشترک پنل؛ بررسی تکراری در آن انجام می‌شود) ---------- */

	public static function student_create( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Student_Profile' ) || ! method_exists( 'HS_Student_Profile', 'find_duplicate' ) ) {
			return self::err( 'افزونه مدیریت کلاس نسخه 1.0.8 یا بالاتر لازم است.', 500 );
		}
		$d = array();
		foreach ( array( 'first_name', 'last_name', 'name', 'parent_name', 'parent_phone', 'class_number', 'birth_date' ) as $k ) {
			$d[ $k ] = sanitize_text_field( (string) self::p( $r, $k, '' ) );
		}
		$res = HS_Student_Profile::create_in_class( (int) $r['id'], $d );
		if ( is_wp_error( $res ) ) {
			$code = $res->get_error_code();
			$st   = in_array( $code, array( 'duplicate', 'duplicate_number' ), true ) ? 409 : ( 'busy' === $code ? 429 : 400 );
			return new WP_Error( 'hs_' . $code, $res->get_error_message(), array( 'status' => $st ) );
		}
		return new WP_REST_Response( array(
			'id'          => (int) $res['user_id'],
			'name'        => $res['profile']['display_name'],
			'username'    => $res['username'],
			'password'    => $res['password'],
			'parent_code' => class_exists( 'HS_Parent_Link' ) ? HS_Parent_Link::get_code( $res['user_id'] ) : '',
		), 201 );
	}

	/* ---------- امتیاز ---------- */

	public static function points_get( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		$rows = array();
		foreach ( HS_Class_Students::get_students( $cid ) as $u ) { $rows[] = array( 'student_id' => $u->ID, 'name' => $u->display_name, 'total' => HS_Points_DB::get_student_total( $cid, $u->ID ) ); }
		usort( $rows, function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
		$beh = array_map( function ( $b ) { return array( 'id' => (int) $b['id'], 'name' => $b['name'], 'icon' => $b['icon'], 'points' => (int) $b['points'] ); }, HS_Points_DB::get_behaviors( $cid ) );
		return array( 'leaderboard' => $rows, 'behaviors' => $beh );
	}
	public static function points_award( WP_REST_Request $r ) {
		$cid = (int) $r['id']; $bid = (int) self::p( $r, 'behavior_id' );
		$beh = $bid ? HS_Points_DB::get_behavior( $bid ) : null;
		if ( ! $beh || ( (int) $beh['class_id'] && (int) $beh['class_id'] !== $cid ) ) { return self::err( 'رفتار نامعتبر است.' ); }
		$members = array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) ); $n = 0;
		foreach ( (array) self::p( $r, 'student_ids', array() ) as $sid ) {
			if ( in_array( (int) $sid, $members, true ) ) { HS_Points_DB::award( $cid, (int) $sid, $bid, null, (string) self::p( $r, 'note' ), get_current_user_id() ); $n++; }
		}
		return array( 'awarded' => $n );
	}

	/* ---------- والدین ---------- */

	public static function children() {
		$uid = get_current_user_id();
		if ( 'parent' !== HS_API_Access::view( $uid ) || ! class_exists( 'HS_Parent_Link' ) ) { return array(); }
		return array_values( array_filter( array_map( function ( $sid ) {
			$u = get_userdata( $sid );
			return $u ? array( 'id' => $u->ID, 'name' => $u->display_name, 'avatar' => get_avatar_url( $u->ID, array( 'size' => 96 ) ), 'classes' => array_map( function ( $c ) { return array( 'id' => $c, 'title' => get_the_title( $c ) ); }, HS_API_Access::student_classes( $sid ) ) ) : null;
		}, HS_Parent_Link::get_children( $uid ) ) ) );
	}
	public static function child( WP_REST_Request $r ) {
		$uid = get_current_user_id(); $sid = (int) $r['id'];
		if ( 'parent' !== HS_API_Access::view( $uid ) || ! class_exists( 'HS_Parent_Link' ) || ! in_array( $sid, HS_Parent_Link::get_children( $uid ), true ) ) { return self::err( 'دسترسی ندارید.', 403 ); }
		$out = array( 'id' => $sid, 'name' => get_userdata( $sid )->display_name, 'classes' => array() );
		foreach ( HS_API_Access::student_classes( $sid ) as $cid ) {
			$sum = HS_Attendance_DB::get_monthly_summary( $cid, (int) current_time( 'Y' ), (int) current_time( 'n' ) );
			$hw  = array();
			foreach ( HS_Homework_DB::get_class_homework( $cid, 10 ) as $h ) { $st = HS_Homework_DB::get_statuses( $h['id'] ); $hw[] = array( 'title' => $h['title'], 'due_date' => $h['due_date'], 'status' => isset( $st[ $sid ] ) ? $st[ $sid ]['status'] : 'pending' ); }
			$out['classes'][] = array( 'id' => $cid, 'title' => get_the_title( $cid ), 'attendance_month' => isset( $sum[ $sid ] ) ? $sum[ $sid ] : new stdClass(), 'points' => HS_Points_DB::get_student_total( $cid, $sid ), 'homework' => $hw );
		}
		return $out;
	}

	/* ---------- پیام‌ها ---------- */

	public static function inbox() {
		$uid = get_current_user_id();
		return array_map( function ( $m ) use ( $uid ) {
			$o = (int) ( $m['sender_id'] === $uid ? $m['recipient_id'] : $m['sender_id'] ); $u = get_userdata( $o );
			return array( 'user_id' => $o, 'name' => $u ? $u->display_name : '', 'preview' => HS_Comms_DB::preview( $m ), 'at' => $m['created_at'], 'unread' => (int) $m['recipient_id'] === $uid && ! (int) $m['is_read'] );
		}, HS_Comms_DB::get_inbox( $uid, 40 ) );
	}
	public static function thread( WP_REST_Request $r ) {
		$uid = get_current_user_id(); $o = (int) $r['id'];
		if ( ! HS_API_Access::can_message( $uid, $o ) && ! HS_Comms_DB::get_conversation( $uid, $o, 0, 1 ) ) { return self::err( 'دسترسی ندارید.', 403 ); }
		HS_Comms_DB::mark_read( $uid, $o );
		return array_map( function ( $m ) use ( $uid ) { return array( 'id' => (int) $m['id'], 'mine' => (int) $m['sender_id'] === $uid, 'body' => $m['body'], 'image' => $m['media_url'], 'at' => $m['created_at'] ); }, HS_Comms_DB::get_conversation( $uid, $o, 0, 100 ) );
	}
	public static function send( WP_REST_Request $r ) {
		$uid = get_current_user_id(); $to = (int) self::p( $r, 'recipient_id' ); $body = trim( (string) self::p( $r, 'body' ) );
		if ( '' === $body ) { return self::err( 'متن پیام خالی است.' ); }
		if ( ! HS_API_Access::can_message( $uid, $to ) ) { return self::err( 'امکان ارسال پیام به این کاربر وجود ندارد.', 403 ); }
		return new WP_REST_Response( array( 'id' => HS_Comms_DB::send_message( $uid, $to, $body, (int) self::p( $r, 'class_id', 0 ) ) ), 201 );
	}

	/* ---------- مدرسه ---------- */

	private static function sid() { $sid = HS_API_Access::school_id( get_current_user_id() ); return $sid ? $sid : 0; }

	public static function school() {
		$sid = self::sid();
		if ( ! $sid ) { return self::err( 'مدرسه‌ای برای شما ثبت نشده است.', 404 ); }
		$s = HS_SO_Store::get_school( $sid );
		$s['can_supervise'] = HS_SO_Store::can_supervise( $sid );
		$s['task_counts']   = HS_SO_Store::task_counts( $sid );
		return $s;
	}
	public static function tasks() {
		$sid = self::sid(); $uid = get_current_user_id();
		if ( ! $sid ) { return array(); }
		$args = HS_SO_Store::is_manager( $sid, $uid ) ? array() : array( 'assignee' => $uid );
		return HS_SO_Store::tasks( $sid, $args );
	}
	public static function task_status( WP_REST_Request $r ) {
		$t = HS_SO_Store::task( (int) $r['id'] ); $uid = get_current_user_id(); $sid = self::sid();
		if ( ! $t || (int) $t['school_id'] !== $sid ) { return self::err( 'وظیفه پیدا نشد.', 404 ); }
		if ( (int) $t['assignee_id'] !== $uid ) { return self::err( 'فقط مسئول انجام وظیفه می‌تواند وضعیت را تغییر دهد.', 403 ); }
		$st = sanitize_key( (string) self::p( $r, 'status' ) );
		if ( ! isset( HS_SO_Structure::statuses()[ $st ] ) ) { return self::err( 'وضعیت نامعتبر.' ); }
		global $wpdb;
		$wpdb->update( HS_SO_Store::tasks_table(), array( 'status' => $st, 'report' => sanitize_textarea_field( (string) self::p( $r, 'report' ) ), 'updated_at' => current_time( 'mysql' ), 'done_at' => 'done' === $st ? current_time( 'mysql' ) : null ), array( 'id' => $t['id'] ) );
		return array( 'ok' => true );
	}
}
