<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * داشبورد هر نقش + ورود دانش‌آموز با کارت کلاس + خلاصه‌ی دانش‌آموز
 */
class HS_API_Routes_Dashboard {

	public static function register() {
		$ns    = HS_API_NS;
		$authd = array( 'HS_API_Routes', 'authed' );
		$open  = '__return_true';
		register_rest_route( $ns, '/dashboard', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'dashboard' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/auth/kid/class', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'kid_class' ), 'permission_callback' => $open ) );
		register_rest_route( $ns, '/auth/kid/login', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'kid_login' ), 'permission_callback' => $open ) );
		register_rest_route( $ns, '/student/summary', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'student_summary' ), 'permission_callback' => $authd ) );
		// حذف‌ها
		register_rest_route( $ns, '/children/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'child_remove' ), 'permission_callback' => array( 'HS_API_Routes_Parent', 'child_perm' ) ) );
		register_rest_route( $ns, '/classes/(?P<id>\d+)/students/(?P<sid>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'student_remove' ), 'permission_callback' => array( __CLASS__, 'class_write_perm' ) ) );
		register_rest_route( $ns, '/school/staff/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'staff_remove' ), 'permission_callback' => $authd ) );
	}

	/* ------------------------------ کمکی ------------------------------ */

	/** بازه‌ی یک روز (محلی) در ساعت پایگاه داده */
	public static function db_day_range( $ymd, $days = 1 ) {
		global $wpdb;
		$db_now = strtotime( (string) $wpdb->get_var( 'SELECT NOW()' ) );
		$diff   = $db_now ? (int) current_time( 'timestamp' ) - $db_now : 0;
		$start  = strtotime( $ymd . ' 00:00:00' ) - $diff;
		return array( gmdate( 'Y-m-d H:i:s', $start ), gmdate( 'Y-m-d H:i:s', $start + $days * DAY_IN_SECONDS ) );
	}

	private static function in( $ids ) {
		return implode( ',', array_map( 'intval', $ids ) );
	}

	/** حضور یک تاریخ برای چند کلاس: [class_id => [status => n]] */
	private static function attendance_counts( $class_ids, $date ) {
		global $wpdb;
		if ( ! $class_ids || ! class_exists( 'HS_Attendance_DB' ) ) { return array(); }
		$t    = HS_Attendance_DB::table_name();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT class_id, status, COUNT(*) n FROM {$t} WHERE class_id IN (" . self::in( $class_ids ) . ') AND att_date = %s GROUP BY class_id, status', $date ), ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['class_id'] ][ $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** مجموع امتیازهای داده‌شده در یک بازه برای چند کلاس */
	private static function points_counts( $class_ids, $from, $to ) {
		global $wpdb;
		if ( ! $class_ids || ! class_exists( 'HS_Points_DB' ) ) { return array(); }
		$t    = HS_Points_DB::points_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT class_id, SUM(points > 0) pos, SUM(points < 0) neg FROM {$t} WHERE class_id IN (" . self::in( $class_ids ) . ') AND created_at >= %s AND created_at < %s GROUP BY class_id', $from, $to ), ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['class_id'] ] = array( 'positive' => (int) $r['pos'], 'negative' => (int) $r['neg'] );
		}
		return $out;
	}

	private static function open_homework( $cid, $today ) {
		if ( ! class_exists( 'HS_Homework_DB' ) ) { return 0; }
		$n = 0;
		foreach ( (array) HS_Homework_DB::get_class_homework( $cid, 30 ) as $h ) {
			if ( ! $h['due_date'] || $h['due_date'] >= $today ) { $n++; }
		}
		return $n;
	}

	/* ------------------------------ داشبورد ------------------------------ */

	public static function dashboard() {
		$uid   = get_current_user_id();
		$view  = HS_API_Access::view( $uid );
		$out   = array(
			'role'            => $view,
			'date'            => HS_API_Util::today(),
			'unread_messages' => class_exists( 'HS_Comms_DB' ) ? (int) HS_Comms_DB::count_unread( $uid ) : 0,
		);
		if ( 'teacher' === $view ) {
			$out['teacher'] = self::teacher_dash( $uid );
		} elseif ( 'manager' === $view ) {
			$out['manager'] = self::manager_dash( $uid );
		}
		if ( HS_API_Access::children_ids( $uid ) || 'parent' === $view ) {
			$out['parent'] = self::parent_dash( $uid );
		}
		return $out;
	}

	private static function teacher_dash( $uid ) {
		$today = HS_API_Util::today();
		$ids   = array();
		foreach ( HS_API_Access::class_ids( $uid ) as $cid ) {
			if ( HS_API_Access::WRITE === HS_API_Access::class_level( $cid, $uid ) ) { $ids[] = $cid; }
		}
		list( $from, $to ) = self::db_day_range( $today );
		$att     = self::attendance_counts( $ids, $today );
		$pts     = self::points_counts( $ids, $from, $to );
		$classes = array();
		$tot     = array( 'classes' => count( $ids ), 'students' => 0, 'absent_today' => 0, 'late_today' => 0, 'not_recorded' => 0, 'unlinked_parents' => 0, 'open_homework' => 0, 'points_today' => 0 );
		foreach ( $ids as $cid ) {
			$members  = HS_API_Access::class_members( $cid );
			$unlinked = 0;
			foreach ( $members as $sid ) {
				if ( class_exists( 'HS_Parent_Link' ) && ! HS_Parent_Link::get_parents( $sid ) ) { $unlinked++; }
			}
			$a   = $att[ $cid ] ?? array();
			$rec = (bool) $a;
			$row = array(
				'id'               => $cid,
				'title'            => get_the_title( $cid ),
				'students'         => count( $members ),
				'recorded_today'   => $rec,
				'absent_today'     => (int) ( $a['absent'] ?? 0 ),
				'late_today'       => (int) ( $a['late'] ?? 0 ),
				'points_today'     => (int) ( ( $pts[ $cid ]['positive'] ?? 0 ) + ( $pts[ $cid ]['negative'] ?? 0 ) ),
				'open_homework'    => self::open_homework( $cid, $today ),
				'unlinked_parents' => $unlinked,
			);
			$classes[] = $row;
			$tot['students']         += $row['students'];
			$tot['absent_today']     += $row['absent_today'];
			$tot['late_today']       += $row['late_today'];
			$tot['not_recorded']     += $rec ? 0 : 1;
			$tot['unlinked_parents'] += $unlinked;
			$tot['open_homework']    += $row['open_homework'];
			$tot['points_today']     += $row['points_today'];
		}
		$requests = class_exists( 'HSUM_Requests' ) ? count( (array) HSUM_Requests::inbox( $uid ) ) : 0;
		return array( 'totals' => $tot, 'classes' => $classes, 'parent_requests' => $requests );
	}

	private static function parent_dash( $uid ) {
		$today = HS_API_Util::today();
		$wk    = gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) );
		list( $from, $to ) = self::db_day_range( $wk, 7 );
		$kids = array();
		foreach ( HS_API_Access::children_ids( $uid ) as $sid ) {
			$u   = get_userdata( $sid );
			$row = array( 'id' => $sid, 'name' => $u->display_name, 'avatar' => HS_API_Util::avatar( $sid ), 'attendance_today' => null, 'absent_week' => 0, 'late_week' => 0, 'points_week' => 0, 'positive_week' => 0, 'open_homework' => 0, 'locked' => array() );
			foreach ( HS_API_Access::student_classes( $sid ) as $cid ) {
				if ( 'publish' !== get_post_status( $cid ) ) { continue; }
				if ( HS_API_Access::parent_can( $uid, $sid, 'attendance', $cid ) && class_exists( 'HS_Attendance_DB' ) ) {
					global $wpdb;
					$t  = HS_Attendance_DB::table_name();
					$rs = $wpdb->get_results( $wpdb->prepare( "SELECT att_date, status FROM {$t} WHERE class_id = %d AND student_id = %d AND att_date >= %s", $cid, $sid, $wk ), ARRAY_A ); // phpcs:ignore
					foreach ( (array) $rs as $r ) {
						if ( $r['att_date'] === $today ) { $row['attendance_today'] = $r['status']; }
						if ( 'absent' === $r['status'] ) { $row['absent_week']++; }
						if ( 'late' === $r['status'] ) { $row['late_week']++; }
					}
				} else {
					$row['locked'][] = 'attendance';
				}
				if ( HS_API_Access::parent_can( $uid, $sid, 'points', $cid ) && class_exists( 'HS_Points_DB' ) ) {
					global $wpdb;
					$pt = HS_Points_DB::points_table();
					$r  = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(points),0) s, SUM(points > 0) pos FROM {$pt} WHERE class_id = %d AND student_id = %d AND created_at >= %s AND created_at < %s", $cid, $sid, $from, $to ), ARRAY_A ); // phpcs:ignore
					$row['points_week']   += (int) ( $r['s'] ?? 0 );
					$row['positive_week'] += (int) ( $r['pos'] ?? 0 );
				} else {
					$row['locked'][] = 'points';
				}
				if ( HS_API_Access::parent_can( $uid, $sid, 'homework', $cid ) && class_exists( 'HS_Homework_DB' ) ) {
					foreach ( (array) HS_Homework_DB::get_class_homework( $cid, 20 ) as $h ) {
						$st = HS_Homework_DB::get_statuses( $h['id'] );
						if ( ( ! $h['due_date'] || $h['due_date'] >= $today ) && 'done' !== ( $st[ $sid ]['status'] ?? 'pending' ) ) { $row['open_homework']++; }
					}
				} else {
					$row['locked'][] = 'homework';
				}
			}
			$row['locked'] = array_values( array_unique( $row['locked'] ) );
			$kids[]        = $row;
		}
		// آخرین اطلاعیه‌های کلاس‌هایی که بخش اطلاعیه برایشان باز است
		$anns = array();
		if ( class_exists( 'HS_Comms_DB' ) ) {
			$seen = array();
			foreach ( HS_API_Access::children_ids( $uid ) as $sid ) {
				foreach ( HS_API_Access::student_classes( $sid ) as $cid ) {
					if ( isset( $seen[ $cid ] ) || ! HS_API_Access::parent_can( $uid, $sid, 'announce', $cid ) ) { continue; }
					$seen[ $cid ] = 1;
					foreach ( (array) HS_Comms_DB::get_announcements( $cid, 3 ) as $a ) {
						$anns[] = array( 'id' => (int) $a['id'], 'class' => get_the_title( $cid ), 'title' => (string) $a['title'], 'body' => wp_trim_words( wp_strip_all_tags( $a['body'] ), 25, '…' ), 'created_at' => $a['created_at'] );
					}
				}
			}
			usort( $anns, function ( $a, $b ) { return strcmp( (string) $b['created_at'], (string) $a['created_at'] ); } );
		}
		$proposals = 0;
		if ( class_exists( 'HSUM_Links' ) ) {
			foreach ( (array) HSUM_Links::for_parent( $uid ) as $l ) {
				if ( 'awaiting' === $l['status'] ) { $proposals++; }
			}
		}
		return array( 'children' => $kids, 'announcements' => array_slice( $anns, 0, 5 ), 'proposals' => $proposals );
	}

	private static function manager_dash( $uid ) {
		$sid = HS_API_Access::school_id( $uid );
		if ( ! $sid || ! class_exists( 'HS_SO_Store' ) || ! HS_SO_Store::can_supervise( $sid, $uid ) || ! class_exists( 'HS_SO_Monitor' ) ) {
			return array( 'school' => null );
		}
		$today = HS_API_Util::today();
		$ids   = array_map( 'intval', HS_SO_Monitor::class_ids( $sid ) );
		$rows  = HS_SO_Monitor::class_rows( $ids );
		$att   = self::attendance_counts( $ids, $today );
		$tot   = array( 'classes' => count( $ids ), 'students' => 0, 'teachers' => 0, 'recorded_today' => 0, 'absent_today' => 0, 'att_rate_30' => null, 'parents_linked_pct' => null );
		$rates = array();
		$students = 0;
		$linked   = 0;
		foreach ( $rows as &$r ) {
			$a                   = $att[ $r['id'] ] ?? array();
			$r['recorded_today'] = (bool) $a;
			$r['absent_today']   = (int) ( $a['absent'] ?? 0 );
			$tot['recorded_today'] += $a ? 1 : 0;
			$tot['absent_today']   += $r['absent_today'];
			$tot['students']       += (int) $r['students'];
			if ( null !== $r['att_rate'] ) { $rates[] = $r['att_rate']; }
			foreach ( HS_API_Access::class_members( $r['id'] ) as $st ) {
				$students++;
				if ( class_exists( 'HS_Parent_Link' ) && HS_Parent_Link::get_parents( $st ) ) { $linked++; }
			}
		}
		unset( $r );
		$teachers = HS_SO_Monitor::teacher_rows( $sid, $rows );
		foreach ( $rows as &$r ) { unset( $r['last_ts'] ); }
		unset( $r );
		foreach ( $teachers as &$t ) { unset( $t['att'], $t['last_ts'] ); }
		unset( $t );
		$tot['teachers']           = count( $teachers );
		$tot['att_rate_30']        = $rates ? (int) round( array_sum( $rates ) / count( $rates ) ) : null;
		$tot['parents_linked_pct'] = $students ? (int) round( 100 * $linked / $students ) : null;
		$pending = 0;
		if ( class_exists( 'HSUM_Staff' ) && HS_SO_Store::is_manager( $sid, $uid ) ) {
			foreach ( (array) HSUM_Staff::rows( $sid ) as $x ) { $pending += 'active' === $x['status'] ? 1 : 0; }
		}
		return array(
			'school'         => array( 'id' => $sid, 'name' => get_the_title( $sid ) ),
			'is_manager'     => HS_SO_Store::is_manager( $sid, $uid ),
			'totals'         => $tot,
			'classes'        => $rows,
			'teachers'       => $teachers,
			'pending_invites' => $pending,
			'task_counts'    => method_exists( 'HS_SO_Store', 'task_counts' ) ? HS_SO_Store::task_counts( $sid ) : array(),
		);
	}

	/* ------------------------------ ورود دانش‌آموز ------------------------------ */

	private static function kid_ready() {
		return class_exists( 'HSSP_Login' ) && class_exists( 'HSSP_Util' );
	}

	private static function kid_name( WP_User $u ) {
		$f = trim( (string) $u->first_name );
		$l = trim( (string) $u->last_name );
		if ( $f ) { return $f . ( $l ? ' ' . mb_substr( $l, 0, 1 ) . '.' : '' ); }
		$p = preg_split( '/\s+/u', trim( $u->display_name ) );
		return $p[0] . ( isset( $p[1] ) ? ' ' . mb_substr( $p[1], 0, 1 ) . '.' : '' );
	}

	private static function kid_class_id( $raw ) {
		$code = HS_API_Util::extract_code( (string) $raw );
		return HSSP_Login::class_by_code( $code );
	}

	/** کد کارت کلاس ← فهرست نام‌ها (فقط دانش‌آموزانی که ورودشان باز است؛ نام کوچک + حرف اول فامیل) */
	public static function kid_class( WP_REST_Request $r ) {
		if ( ! self::kid_ready() ) { return HS_API_Util::err( 'ورود دانش‌آموز روی سایت فعال نیست.', 503, 'hs_unavailable' ); }
		$k   = 'kidc_ip_' . HS_API_Util::ip();
		$lim = HS_API_Util::limited( $k, 20, 'تلاش زیاد بود. کمی بعد دوباره امتحان کن.' );
		if ( $lim ) { return $lim; }
		$cid = self::kid_class_id( HS_API_Util::p( $r, 'code' ) );
		if ( ! $cid ) {
			HS_API_Util::hit( $k, HOUR_IN_SECONDS );
			return HS_API_Util::err( 'این کارت کلاس شناخته نشد. QR کارت را دوباره اسکن کن.', 404, 'hs_bad_code' );
		}
		$list = array();
		foreach ( HS_API_Access::class_members( $cid ) as $sid ) {
			$u = get_userdata( $sid );
			if ( $u && HSSP_Login::allowed( $cid, $sid ) ) {
				$list[] = array( 'id' => $sid, 'name' => self::kid_name( $u ), 'avatar' => HS_API_Util::avatar( $sid ), 'locked' => HSSP_Login::locked( $sid ) );
			}
		}
		usort( $list, function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
		return array( 'class' => array( 'id' => $cid, 'title' => get_the_title( $cid ) ), 'students' => $list );
	}

	public static function kid_login( WP_REST_Request $r ) {
		if ( ! self::kid_ready() ) { return HS_API_Util::err( 'ورود دانش‌آموز روی سایت فعال نیست.', 503, 'hs_unavailable' ); }
		$cid = self::kid_class_id( HS_API_Util::p( $r, 'code' ) );
		$sid = (int) HS_API_Util::p( $r, 'student_id' );
		$pin = HS_API_Util::digits( (string) HS_API_Util::p( $r, 'pin' ) );
		$bad = HS_API_Util::err( 'ورود ممکن نیست.', 403, 'hs_kid_denied' );
		if ( ! $cid || ! HSSP_Util::in_class( $sid, $cid ) || ! HSSP_Login::allowed( $cid, $sid ) ) { return $bad; }
		$day = 'kid_day_' . $sid;
		if ( HSSP_Login::locked( $sid ) || HS_API_Util::hits( $day ) >= 15 ) {
			return HS_API_Util::err( 'فعلاً قفل است. کمی بعد امتحان کن یا از پدر و مادرت بخواه رمز را دوباره بگذارند.', 429, 'hs_kid_locked' );
		}
		if ( ! HSSP_Login::check_pin( $sid, $pin ) ) {
			// همان شمارنده‌های ورود سایت (۵ اشتباه ← ۱۵ دقیقه) + سقف روزانه‌ی ۱۵ اشتباه
			$ipk = 'hssp_lip_' . md5( substr( preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 45 ) ); // phpcs:ignore
			foreach ( array( 'hssp_lf_' . $sid, $ipk ) as $k ) {
				set_transient( $k, (int) get_transient( $k ) + 1, 15 * MINUTE_IN_SECONDS );
			}
			HS_API_Util::hit( $day, DAY_IN_SECONDS );
			return HS_API_Util::err( 'رمز درست نبود. دوباره امتحان کن 🙂', 401, 'hs_kid_pin' );
		}
		delete_transient( 'hssp_lf_' . $sid );
		$u  = get_userdata( $sid );
		$ok = HS_API_Auth::may_use_app( $u );
		if ( is_wp_error( $ok ) ) { return $ok; }
		if ( class_exists( 'HSSP_DB' ) && method_exists( 'HSSP_DB', 'log' ) ) {
			HSSP_DB::log( 'kid_login', $sid, array( 'class' => $cid, 'via' => 'app' ) );
		}
		do_action( 'hs_api_login', $u, 'kid' );
		$out                     = HS_API_Auth::issue( $u, (string) HS_API_Util::p( $r, 'device' ) );
		$out['needs_onboarding'] = false;
		return $out;
	}

	/* ------------------------------ دانش‌آموز ------------------------------ */

	public static function student_summary() {
		$uid = get_current_user_id();
		if ( ! HS_API_Access::is_student( $uid ) ) {
			return HS_API_Util::err( 'این بخش برای دانش‌آموز است.', 403, 'hs_forbidden' );
		}
		$out = array( 'name' => wp_get_current_user()->display_name, 'classes' => array() );
		foreach ( HS_API_Access::student_classes( $uid ) as $cid ) {
			if ( 'publish' !== get_post_status( $cid ) ) { continue; }
			$t   = get_userdata( (int) get_post_meta( $cid, '_hs_teacher_id', true ) );
			$row = array( 'id' => $cid, 'title' => get_the_title( $cid ), 'teacher' => $t ? $t->display_name : '', 'points_total' => 0, 'points_recent' => array(), 'homework' => array() );
			if ( class_exists( 'HS_Points_DB' ) ) {
				$row['points_total'] = (int) HS_Points_DB::get_student_total( $cid, $uid );
				foreach ( HS_Points_DB::get_student_history( $cid, $uid, 15 ) as $p ) {
					$row['points_recent'][] = array( 'points' => (int) $p['points'], 'behavior' => (string) $p['behavior_name'], 'icon' => (string) $p['behavior_icon'], 'at' => $p['created_at'] );
				}
			}
			if ( class_exists( 'HS_Homework_DB' ) ) {
				foreach ( (array) HS_Homework_DB::get_class_homework( $cid, 20 ) as $h ) {
					$st = HS_Homework_DB::get_statuses( $h['id'] );
					$row['homework'][] = array( 'id' => (int) $h['id'], 'title' => (string) $h['title'], 'subject' => (string) $h['subject'], 'description' => (string) $h['description'], 'due_date' => $h['due_date'], 'status' => $st[ $uid ]['status'] ?? 'pending', 'feedback' => $st[ $uid ]['feedback'] ?? '' );
				}
			}
			$out['classes'][] = $row;
		}
		return $out;
	}

	/* ------------------------------ حذف ------------------------------ */

	public static function class_write_perm( WP_REST_Request $r ) {
		if ( ! is_user_logged_in() ) { return HS_API_Util::err( 'ابتدا وارد شوید.', 401, 'hs_unauthorized' ); }
		return HS_API_Access::WRITE === HS_API_Access::class_level( (int) $r['id'], get_current_user_id() ) ? true : HS_API_Util::err( 'فقط معلم همین کلاس.', 403, 'hs_forbidden' );
	}

	/** والد، فرزند را از حساب خودش جدا می‌کند (والد دیگر و سوابق دست‌نخورده) */
	public static function child_remove( WP_REST_Request $r ) {
		global $wpdb;
		$uid = get_current_user_id();
		$sid = (int) $r['id'];
		HS_Parent_Link::unlink( $uid, $sid );
		if ( class_exists( 'HSUM_DB' ) ) {
			$wpdb->update( HSUM_DB::t( 'links' ), array( 'status' => 'removed' ), array( 'parent_id' => $uid, 'student_id' => $sid ) );
		}
		if ( class_exists( 'HSUM_Audit' ) ) { HSUM_Audit::log( 'link_removed', $sid, array( 'by_parent' => $uid, 'via' => 'app' ) ); }
		return array( 'ok' => true, 'message' => 'فرزند از حساب شما جدا شد.' );
	}

	/** معلم، دانش‌آموز را از کلاس خودش بیرون می‌کند (حساب، سوابق و اتصال والدین می‌ماند؛ مثل سایت) */
	public static function student_remove( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		$sid = (int) $r['sid'];
		if ( ! in_array( $sid, HS_API_Access::class_members( $cid ), true ) ) {
			return HS_API_Util::err( 'این دانش‌آموز در این کلاس نیست.', 404, 'hs_not_found' );
		}
		HS_Class_Students::remove_student( $cid, $sid );
		do_action( 'hs_api_student_removed', $cid, $sid, get_current_user_id() );
		return array( 'ok' => true, 'message' => 'دانش‌آموز از کلاس حذف شد.' );
	}

	/** مدیرِ تأییدشده، معلم یا معاون را از کادر مدرسه حذف می‌کند (حساب و کلاس‌های او می‌ماند) */
	public static function staff_remove( WP_REST_Request $r ) {
		global $wpdb;
		$uid = get_current_user_id();
		if ( ! class_exists( 'HS_SO_Store' ) ) { return HS_API_Util::err( 'بخش مدرسه فعال نیست.', 503, 'hs_unavailable' ); }
		$sid = (int) HS_SO_Store::manager_school_id( $uid );
		if ( ! $sid || ! HS_SO_Store::is_manager( $sid, $uid ) ) {
			return HS_API_Util::err( 'فقط مدیر مدرسه می‌تواند کادر را حذف کند.', 403, 'hs_forbidden' );
		}
		$row = HS_SO_Store::staff_row( (int) $r['id'] );
		if ( ! $row || (int) $row['school_id'] !== $sid ) {
			return HS_API_Util::err( 'عضو کادر پیدا نشد.', 404, 'hs_not_found' );
		}
		$member = (int) $row['user_id'];
		HS_SO_Store::remove_staff( (int) $row['id'] );
		if ( class_exists( 'HSUM_DB' ) && $member ) {
			// عضویتِ از راه دعوت هم قطع شود تا کلاس‌های او دیگر زیر نظر این مدرسه نباشد
			$wpdb->update( HSUM_DB::t( 'school_members' ), array( 'status' => 'removed' ), array( 'school_id' => $sid, 'user_id' => $member ) );
			$wpdb->update( HSUM_DB::t( 'invites' ), array( 'status' => 'revoked' ), array( 'school_id' => $sid, 'user_id' => $member, 'status' => 'accepted' ) );
		}
		if ( class_exists( 'HSUM_Audit' ) ) { HSUM_Audit::log( 'staff_removed', $sid, array( 'user' => $member, 'by' => $uid, 'via' => 'app' ) ); }
		return array( 'ok' => true, 'message' => 'از کادر مدرسه حذف شد.' );
	}
}
