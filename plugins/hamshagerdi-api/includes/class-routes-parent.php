<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * مسیرهای والدین.
 * هر داده‌ی فرزند فقط وقتی برمی‌گردد که:
 *   ۱) اتصال واقعیِ والد ↔ فرزند برقرار باشد (HS_Parent_Link::get_children)، و
 *   ۲) آن بخش برای این والد/فرزند/کلاس باز باشد (HSPP_Perms::can).
 * بخش بسته در پاسخ نمی‌آید و در فهرست locked نام برده می‌شود.
 */
class HS_API_Routes_Parent {

	public static function register() {
		$ns    = HS_API_NS;
		$authd = array( 'HS_API_Routes', 'authed' );
		$child = array( __CLASS__, 'child_perm' );
		register_rest_route( $ns, '/children', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'children' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/children/join', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'join' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/children/requests', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'requests' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/children/proposals', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'proposals' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/children/proposals/(?P<id>\d+)', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'proposal_answer' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/children/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'child' ), 'permission_callback' => $child ) );
		register_rest_route( $ns, '/children/(?P<id>\d+)/today', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'today' ), 'permission_callback' => $child ) );
		register_rest_route( $ns, '/children/(?P<id>\d+)/week', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'week' ), 'permission_callback' => $child ) );
		register_rest_route( $ns, '/children/(?P<id>\d+)/pin', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'pin_get' ), 'permission_callback' => $child ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'pin_set' ), 'permission_callback' => $child ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'pin_revoke' ), 'permission_callback' => $child ),
		) );
	}

	/** فقط والدِ واقعیِ همین فرزند (معلم و مدیر از این مسیرها رد نمی‌شوند) */
	public static function child_perm( WP_REST_Request $r ) {
		if ( ! is_user_logged_in() ) { return HS_API_Util::err( 'ابتدا وارد شوید.', 401, 'hs_unauthorized' ); }
		if ( ! HS_API_Access::is_parent_of( get_current_user_id(), (int) $r['id'] ) ) {
			return HS_API_Util::err( 'این دانش‌آموز به حساب شما متصل نیست.', 403, 'hs_forbidden' );
		}
		return true;
	}

	/* ---------- کمکی ---------- */

	private static function classes_of( $sid ) {
		$out = array();
		foreach ( HS_API_Access::student_classes( $sid ) as $cid ) {
			if ( 'hs_class' !== get_post_type( $cid ) || 'publish' !== get_post_status( $cid ) ) { continue; }
			$t     = get_userdata( (int) get_post_meta( $cid, '_hs_teacher_id', true ) );
			$out[] = array( 'id' => (int) $cid, 'title' => get_the_title( $cid ), 'grade' => (string) get_post_meta( $cid, '_hs_grade', true ), 'teacher' => $t ? $t->display_name : '', 'teacher_id' => $t ? (int) $t->ID : 0 );
		}
		return $out;
	}

	/** «امروز» در ساعت پایگاه داده (created_at با CURRENT_TIMESTAMP خود MySQL ذخیره می‌شود) */
	private static function db_day_range( $ymd ) {
		global $wpdb;
		$db_now = strtotime( (string) $wpdb->get_var( 'SELECT NOW()' ) );
		$wp_now = (int) current_time( 'timestamp' );
		$diff   = $db_now ? $wp_now - $db_now : 0;
		$start  = strtotime( $ymd . ' 00:00:00' ) - $diff;
		return array( gmdate( 'Y-m-d H:i:s', $start ), gmdate( 'Y-m-d H:i:s', $start + DAY_IN_SECONDS ) );
	}

	private static function points_between( $cid, $sid, $from, $to ) {
		global $wpdb;
		if ( ! class_exists( 'HS_Points_DB' ) ) { return array( 'total' => 0, 'items' => array() ); }
		$pts  = HS_Points_DB::points_table();
		$beh  = HS_Points_DB::behaviors_table();
		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
			"SELECT p.id, p.points, p.note, p.created_at, b.name AS behavior_name, b.icon AS behavior_icon FROM {$pts} p LEFT JOIN {$beh} b ON p.behavior_id = b.id WHERE p.class_id = %d AND p.student_id = %d AND p.created_at >= %s AND p.created_at < %s ORDER BY p.created_at DESC LIMIT 100", // phpcs:ignore
			$cid, $sid, $from, $to
		), ARRAY_A );
		$total = 0;
		$items = array();
		foreach ( (array) $rows as $p ) {
			$total  += (int) $p['points'];
			$items[] = array( 'points' => (int) $p['points'], 'behavior' => (string) $p['behavior_name'], 'icon' => (string) $p['behavior_icon'], 'note' => (string) $p['note'], 'at' => $p['created_at'] );
		}
		return array( 'total' => $total, 'items' => $items );
	}

	private static function open_homework( $cid, $sid, $today ) {
		$out = array();
		if ( ! class_exists( 'HS_Homework_DB' ) ) { return $out; }
		foreach ( (array) HS_Homework_DB::get_class_homework( $cid, 20 ) as $h ) {
			$st     = HS_Homework_DB::get_statuses( $h['id'] );
			$status = isset( $st[ $sid ] ) ? $st[ $sid ]['status'] : 'pending';
			$open   = ! in_array( $status, array( 'done' ), true ) && ( ! $h['due_date'] || $h['due_date'] >= $today );
			if ( $open ) {
				$out[] = array( 'id' => (int) $h['id'], 'title' => (string) $h['title'], 'subject' => (string) $h['subject'], 'description' => (string) $h['description'], 'due_date' => $h['due_date'], 'status' => $status, 'attachments' => method_exists( 'HS_Homework_DB', 'attachments' ) ? HS_Homework_DB::attachments( $h ) : array() );
			}
		}
		return $out;
	}

	/* ---------- فهرست فرزندان ---------- */

	public static function children() {
		$uid = get_current_user_id();
		$out = array();
		foreach ( HS_API_Access::children_ids( $uid ) as $sid ) {
			$u = get_userdata( $sid );
			if ( ! $u ) { continue; }
			$classes = self::classes_of( $sid );
			foreach ( $classes as &$c ) {
				$c['features'] = HS_API_Access::feature_map( $uid, $sid, $c['id'] );
			}
			unset( $c );
			$out[] = array(
				'id'        => $u->ID,
				'name'      => $u->display_name,
				'first_name' => (string) $u->first_name,
				'avatar'    => HS_API_Util::avatar( $u->ID, 128 ),
				'classes'   => $classes,
				'relation'  => class_exists( 'HSPP_Names' ) ? (string) HSPP_Names::relation_label( $uid, $sid ) : '',
				'pin_login' => class_exists( 'HSSP_Login' ) ? (bool) HSSP_Login::parent_ok( $sid ) : false,
			);
		}
		return $out;
	}

	/* ---------- جزئیات (سازگار با نسخه‌ی ۱، با رعایت مجوزها) ---------- */

	public static function child( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		$sid = (int) $r['id'];
		$u   = get_userdata( $sid );
		$out = array( 'id' => $sid, 'name' => $u->display_name, 'avatar' => HS_API_Util::avatar( $sid, 128 ), 'classes' => array() );
		foreach ( self::classes_of( $sid ) as $c ) {
			$cid    = $c['id'];
			$locked = array();
			$row    = $c;
			if ( HS_API_Access::parent_can( $uid, $sid, 'attendance', $cid ) && class_exists( 'HS_Attendance_DB' ) ) {
				$sum = HS_Attendance_DB::get_monthly_summary( $cid, (int) current_time( 'Y' ), (int) current_time( 'n' ) );
				$row['attendance_month'] = isset( $sum[ $sid ] ) ? $sum[ $sid ] : new stdClass();
			} else {
				$locked[] = 'attendance';
			}
			if ( HS_API_Access::parent_can( $uid, $sid, 'points', $cid ) && class_exists( 'HS_Points_DB' ) ) {
				$row['points'] = (int) HS_Points_DB::get_student_total( $cid, $sid );
			} else {
				$locked[] = 'points';
			}
			if ( HS_API_Access::parent_can( $uid, $sid, 'homework', $cid ) && class_exists( 'HS_Homework_DB' ) ) {
				$hw = array();
				foreach ( (array) HS_Homework_DB::get_class_homework( $cid, 10 ) as $h ) {
					$st   = HS_Homework_DB::get_statuses( $h['id'] );
					$hw[] = array( 'id' => (int) $h['id'], 'title' => (string) $h['title'], 'due_date' => $h['due_date'], 'status' => isset( $st[ $sid ] ) ? $st[ $sid ]['status'] : 'pending' );
				}
				$row['homework'] = $hw;
			} else {
				$locked[] = 'homework';
			}
			$row['locked']   = $locked;
			$row['features'] = HS_API_Access::feature_map( $uid, $sid, $cid );
			$out['classes'][] = $row;
		}
		return $out;
	}

	/* ---------- خلاصه‌ی امروز ---------- */

	public static function today( WP_REST_Request $r ) {
		$uid   = get_current_user_id();
		$sid   = (int) $r['id'];
		$date  = HS_API_Util::date( HS_API_Util::p( $r, 'date' ) );
		$date  = $date ? $date : HS_API_Util::today();
		$today = HS_API_Util::today();
		list( $from, $to ) = self::db_day_range( $date );
		$u   = get_userdata( $sid );
		$out = array( 'id' => $sid, 'name' => $u->display_name, 'avatar' => HS_API_Util::avatar( $sid, 128 ), 'date' => $date, 'classes' => array() );
		foreach ( self::classes_of( $sid ) as $c ) {
			$cid    = $c['id'];
			$row    = $c;
			$locked = array();
			if ( HS_API_Access::parent_can( $uid, $sid, 'attendance', $cid ) && class_exists( 'HS_Attendance_DB' ) ) {
				$rec = (array) HS_Attendance_DB::get_by_class_date( $cid, $date );
				$row['attendance'] = isset( $rec[ $sid ] ) ? array( 'status' => $rec[ $sid ]['status'], 'note' => (string) $rec[ $sid ]['note'] ) : null;
			} else {
				$locked[] = 'attendance';
			}
			if ( HS_API_Access::parent_can( $uid, $sid, 'points', $cid ) ) {
				$p = self::points_between( $cid, $sid, $from, $to );
				$row['points_today'] = $p['total'];
				$row['points_items'] = $p['items'];
				$row['points_total'] = class_exists( 'HS_Points_DB' ) ? (int) HS_Points_DB::get_student_total( $cid, $sid ) : 0;
			} else {
				$locked[] = 'points';
			}
			if ( HS_API_Access::parent_can( $uid, $sid, 'homework', $cid ) ) {
				$row['homework_open'] = self::open_homework( $cid, $sid, $today );
			} else {
				$locked[] = 'homework';
			}
			if ( HS_API_Access::parent_can( $uid, $sid, 'announce', $cid ) && class_exists( 'HS_Comms_DB' ) ) {
				$row['announcements'] = array_map( function ( $a ) {
					return array( 'id' => (int) $a['id'], 'title' => (string) $a['title'], 'pinned' => (bool) $a['is_pinned'], 'created_at' => $a['created_at'] );
				}, array_slice( (array) HS_Comms_DB::get_announcements( $cid, 3 ), 0, 3 ) );
			} else {
				$locked[] = 'announce';
			}
			$row['can_message'] = $c['teacher_id'] ? HS_API_Access::can_message( $uid, $c['teacher_id'] ) : false;
			$row['locked']      = $locked;
			$out['classes'][]   = $row;
		}
		return $out;
	}

	/** گزارش هفتگی: حضور و امتیاز ۷ روز (از from تا from+6) */
	public static function week( WP_REST_Request $r ) {
		$uid  = get_current_user_id();
		$sid  = (int) $r['id'];
		$from = HS_API_Util::date( HS_API_Util::p( $r, 'from' ) );
		$from = $from ? $from : gmdate( 'Y-m-d', strtotime( HS_API_Util::today() . ' -6 days' ) );
		$out  = array( 'id' => $sid, 'from' => $from, 'classes' => array() );
		foreach ( self::classes_of( $sid ) as $c ) {
			$cid    = $c['id'];
			$row    = $c;
			$locked = array();
			$days   = array();
			$att    = HS_API_Access::parent_can( $uid, $sid, 'attendance', $cid ) && class_exists( 'HS_Attendance_DB' );
			$pts    = HS_API_Access::parent_can( $uid, $sid, 'points', $cid );
			for ( $i = 0; $i < 7; $i++ ) {
				$d   = gmdate( 'Y-m-d', strtotime( $from . ' +' . $i . ' days' ) );
				$day = array( 'date' => $d );
				if ( $att ) {
					$rec               = (array) HS_Attendance_DB::get_by_class_date( $cid, $d );
					$day['attendance'] = isset( $rec[ $sid ] ) ? $rec[ $sid ]['status'] : null;
				}
				if ( $pts ) {
					list( $a, $b )     = self::db_day_range( $d );
					$day['points']     = self::points_between( $cid, $sid, $a, $b )['total'];
				}
				$days[] = $day;
			}
			if ( ! $att ) { $locked[] = 'attendance'; }
			if ( ! $pts ) { $locked[] = 'points'; }
			$row['days']      = $days;
			$row['locked']    = $locked;
			$out['classes'][] = $row;
		}
		return $out;
	}

	/* ---------- افزودن فرزند با کد کلاس یا QR ---------- */

	public static function join( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		if ( ! class_exists( 'HSUM_Invites' ) ) {
			return HS_API_Util::err( 'اتصال با کد کلاس روی سایت فعال نیست.', 503, 'hs_unavailable' );
		}
		if ( HS_API_Access::is_student( $uid ) && ! array_intersect( (array) wp_get_current_user()->roles, array( 'parent', 'teacher' ) ) ) {
			return HS_API_Util::err( 'حساب دانش‌آموز نمی‌تواند فرزند اضافه کند.', 403, 'hs_forbidden' );
		}
		$k_u = 'join_u_' . $uid;
		$k_i = 'join_ip_' . HS_API_Util::ip();
		$lim = HS_API_Util::limited( $k_u, 8, 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه بعد دوباره امتحان کنید.' );
		if ( ! $lim ) { $lim = HS_API_Util::limited( $k_i, 30 ); }
		if ( $lim ) { return $lim; }
		$code = HS_API_Util::extract_code( (string) HS_API_Util::p( $r, 'code' ) );
		$name = HS_API_Util::text( HS_API_Util::p( $r, 'child_name' ), 100 );
		if ( strlen( $code ) < 4 ) {
			return HS_API_Util::err( 'کد اتصال یا کد کلاس را وارد کنید، یا QR را اسکن کنید.', 400, 'hs_bad_code' );
		}
		// ۱) کد اتصال والدِ مخصوص یک دانش‌آموز (معلم به خانواده می‌دهد) ← اتصال مستقیم
		$sid = self::student_by_parent_code( $code );
		if ( $sid ) {
			HS_API_Util::reset( $k_u );
			if ( HS_API_Access::is_parent_of( $uid, $sid ) ) {
				return array( 'ok' => true, 'status' => 'linked', 'message' => 'این فرزند قبلاً به حساب شما وصل است.', 'children' => self::children() );
			}
			$cls = HS_API_Access::student_classes( $sid );
			if ( class_exists( 'HSUM_Links' ) ) {
				$res = HSUM_Links::link_direct( $sid, $uid, $cls ? $cls[0] : 0, 'parent_code' );
			} else {
				$res = HS_Parent_Link::link( $uid, $sid );
			}
			if ( is_wp_error( $res ) ) {
				return HS_API_Util::err( $res->get_error_message(), 400, 'hs_join_' . $res->get_error_code() );
			}
			$u = get_userdata( $sid );
			return array( 'ok' => true, 'status' => 'linked', 'message' => 'به حساب ' . $u->display_name . ' وصل شدید.', 'children' => self::children() );
		}
		// ۲) کد کلاس ← تطبیق با شماره‌ی شما یا درخواست برای معلم
		if ( mb_strlen( $name ) < 3 ) {
			if ( ! HSUM_Invites::by_code( $code ) ) {
				HS_API_Util::hit( $k_u, 15 * MINUTE_IN_SECONDS );
				HS_API_Util::hit( $k_i, 15 * MINUTE_IN_SECONDS );
				return HS_API_Util::err( 'این کد معتبر نیست. کد اتصال را دوباره از معلم بگیرید.', 404, 'hs_bad_code' );
			}
			return HS_API_Util::err( 'این کد کلاس است؛ نام و نام خانوادگی فرزند را هم بنویسید.', 400, 'hs_need_name' );
		}
		if ( ! HSUM_Invites::by_code( $code ) ) {
			HS_API_Util::hit( $k_u, 15 * MINUTE_IN_SECONDS );
			HS_API_Util::hit( $k_i, 15 * MINUTE_IN_SECONDS );
			return HS_API_Util::err( 'کد دعوت معتبر نیست یا منقضی شده است.', 404, 'hs_bad_code' );
		}
		$res = HSUM_Invites::parent_join( $code, $uid, $name );
		if ( is_wp_error( $res ) ) {
			return HS_API_Util::err( $res->get_error_message(), 400, 'hs_join_' . $res->get_error_code() );
		}
		HS_API_Util::reset( $k_u );
		return array(
			'ok'       => true,
			'status'   => $res['status'],   // linked | request
			'message'  => $res['message'],
			'children' => self::children(),
		);
	}

	/** دانش‌آموزِ صاحب این کد اتصال والد (۸ نویسه، ساخته‌شده توسط HS_Parent_Link) */
	private static function student_by_parent_code( $code ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
		if ( ! preg_match( '/^[A-Z0-9]{8}$/', $code ) ) {
			return 0;
		}
		$ids = get_users( array( 'meta_key' => '_hs_parent_code', 'meta_value' => $code, 'fields' => 'ID', 'number' => 2, 'role' => 'student' ) );
		return 1 === count( $ids ) ? (int) $ids[0] : 0;
	}

	/** پیشنهادهای اتصال (معلم شماره‌ی شما را در پرونده‌ی دانش‌آموز ثبت کرده) */
	public static function proposals() {
		if ( ! class_exists( 'HSUM_Links' ) ) { return array(); }
		$uid = get_current_user_id();
		$out = array();
		foreach ( (array) HSUM_Links::for_parent( $uid ) as $l ) {
			if ( 'awaiting' !== $l['status'] ) { continue; }
			$u = get_userdata( (int) $l['student_id'] );
			if ( ! $u ) { continue; }
			$t = $l['class_id'] ? get_userdata( (int) get_post_meta( (int) $l['class_id'], '_hs_teacher_id', true ) ) : null;
			$out[] = array(
				'id'       => (int) $l['id'],
				'student'  => $u->display_name,
				'class'    => $l['class_id'] ? get_the_title( (int) $l['class_id'] ) : '',
				'teacher'  => $t ? $t->display_name : '',
				'relation' => (string) $l['relation'],
			);
		}
		return $out;
	}

	public static function proposal_answer( WP_REST_Request $r ) {
		if ( ! class_exists( 'HSUM_Links' ) ) { return HS_API_Util::err( 'این بخش روی سایت فعال نیست.', 503, 'hs_unavailable' ); }
		$uid = get_current_user_id();
		$id  = (int) $r['id'];
		$res = rest_sanitize_boolean( HS_API_Util::p( $r, 'accept', false ) ) ? HSUM_Links::confirm( $id, $uid ) : HSUM_Links::reject( $id, $uid );
		if ( is_wp_error( $res ) ) {
			return HS_API_Util::err( $res->get_error_message(), 400, 'hs_proposal' );
		}
		return array( 'ok' => true, 'children' => self::children() );
	}

	/** درخواست‌های اتصالِ در انتظار تأیید معلم */
	public static function requests() {
		if ( ! class_exists( 'HSUM_Requests' ) ) { return array(); }
		$out = array();
		foreach ( (array) HSUM_Requests::for_parent( get_current_user_id() ) as $q ) {
			$out[] = array(
				'id'         => (int) $q['id'],
				'child_name' => (string) ( $q['child_name'] ?? '' ),
				'class'      => ! empty( $q['class_id'] ) ? get_the_title( (int) $q['class_id'] ) : '',
				'status'     => (string) ( $q['status'] ?? '' ),
				'created_at' => $q['created_at'] ?? null,
			);
		}
		return $out;
	}

	/* ---------- رمز ۴ رقمی ورود فرزند ---------- */

	private static function pin_ready( $sid ) {
		if ( ! class_exists( 'HSSP_Login' ) ) {
			return HS_API_Util::err( 'ورود دانش‌آموز با کارت کلاس روی سایت فعال نیست.', 503, 'hs_unavailable' );
		}
		if ( ! HSSP_Login::eligible( $sid ) ) {
			return HS_API_Util::err( 'برای این حساب نمی‌شود رمز ورود ساده گذاشت.', 400, 'hs_not_eligible' );
		}
		return true;
	}

	public static function pin_get( WP_REST_Request $r ) {
		$sid = (int) $r['id'];
		$ok  = self::pin_ready( $sid );
		if ( is_wp_error( $ok ) ) { return $ok; }
		// خود رمز هرگز برگردانده نمی‌شود
		return array( 'enabled' => HSSP_Login::parent_ok( $sid ), 'has_pin' => '' !== (string) get_user_meta( $sid, HSSP_Login::PIN, true ) );
	}

	public static function pin_set( WP_REST_Request $r ) {
		$sid = (int) $r['id'];
		$ok  = self::pin_ready( $sid );
		if ( is_wp_error( $ok ) ) { return $ok; }
		$pin = HS_API_Util::digits( (string) HS_API_Util::p( $r, 'pin' ) );
		if ( ! preg_match( '/^\d{4}$/', $pin ) ) {
			return HS_API_Util::err( 'رمز باید دقیقاً ۴ رقم باشد.', 400, 'hs_bad_pin' );
		}
		if ( in_array( $pin, array( '0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999', '1234', '4321', '0123', '9876' ), true ) ) {
			return HS_API_Util::err( 'این رمز خیلی ساده است؛ رمز دیگری انتخاب کنید.', 400, 'hs_weak_pin' );
		}
		$lim = HS_API_Util::limited( 'pin_' . get_current_user_id(), 10 );
		if ( $lim ) { return $lim; }
		HS_API_Util::hit( 'pin_' . get_current_user_id(), HOUR_IN_SECONDS );
		$res = HSSP_Login::set_pin( $sid, $pin );
		if ( is_wp_error( $res ) ) { return HS_API_Util::err( $res->get_error_message(), 400, 'hs_bad_pin' ); }
		if ( class_exists( 'HSSP_DB' ) && method_exists( 'HSSP_DB', 'log' ) ) {
			HSSP_DB::log( 'parent_pin', $sid, array( 'by' => get_current_user_id(), 'via' => 'app' ) );
		}
		return array( 'ok' => true, 'enabled' => true );
	}

	public static function pin_revoke( WP_REST_Request $r ) {
		$sid = (int) $r['id'];
		$ok  = self::pin_ready( $sid );
		if ( is_wp_error( $ok ) ) { return $ok; }
		HSSP_Login::revoke( $sid );
		return array( 'ok' => true, 'enabled' => false );
	}
}
