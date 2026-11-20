<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * نسخه‌ی ۲.۶: برنامه‌ی هفتگی و زنگ‌بندی، رسید و ویرایش پیام، سنجاق دیوار کلاس، ترتیب دانش‌آموزان،
 * منابع درسی بودجه‌بندی، نسخه‌ی اپ برای دانلود از سایت، دیوار و اطلاعیه‌های داشبورد معلم.
 * (نیاز به «مدیریت کلاس» ۱.۷.۵)
 */
class HS_API_Routes_V26 {

	const APP_OPT = 'hs_api_app_release';

	public static function init() {
		add_shortcode( 'hamshagerdi_app_download', array( __CLASS__, 'download_shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_hs_api_app_save', array( __CLASS__, 'admin_save' ) );
	}

	public static function register() {
		$ns   = HS_API_NS;
		$a    = array( 'HS_API_Routes', 'authed' );
		$open = '__return_true';
		$r    = function ( $route, $method, $cb, $perm = null ) use ( $ns, $a ) {
			register_rest_route( $ns, $route, array( 'methods' => $method, 'callback' => array( __CLASS__, $cb ), 'permission_callback' => $perm ? $perm : $a ) );
		};
		// برنامه‌ی هفتگی
		$r( '/classes/(?P<id>\d+)/timetable', 'GET', 'timetable' );
		$r( '/classes/(?P<id>\d+)/timetable', 'POST', 'timetable_save' );
		$r( '/classes/(?P<id>\d+)/bells', 'POST', 'bells_save' );
		$r( '/school/bells', 'GET', 'school_bells' );
		$r( '/school/bells', 'POST', 'school_bells_save' );
		$r( '/me/timetable', 'GET', 'my_timetable' );
		// پیام
		$r( '/messages/item/(?P<id>\d+)', 'POST', 'message_edit' );
		// دیوار کلاس
		$r( '/portfolio/(?P<id>\d+)/pin', 'POST', 'wall_pin' );
		// باز/بسته کردن پذیرش عضو جدید با کد کلاس
		$r( '/classes/(?P<id>\d+)/join', 'POST', 'join_toggle' );
		// ترتیب دانش‌آموزان
		$r( '/classes/(?P<id>\d+)/students/order', 'POST', 'students_order' );
		// منابع درسی
		$r( '/me/budget-sources', 'POST', 'budget_sources' );
		$r( '/school/budget-subjects', 'GET', 'school_subjects' );
		$r( '/school/budget-subjects', 'POST', 'school_subjects_save' );
		// نسخه‌ی اپ (?app=family | teacher)
		$r( '/app/latest', 'GET', 'app_latest', $open );
		// ویرایش نام خودم
		$r( '/me/name', 'POST', 'me_name' );
		// داشبورد معلم: دیوار و اطلاعیه‌های اخیر
		$r( '/teacher/feed', 'GET', 'teacher_feed' );
	}

	/* ------------------------------ کمکی ------------------------------ */

	private static function err( $m, $s = 400, $c = 'hs_error', $d = array() ) { return HS_API_Util::err( $m, $s, $c, $d ); }
	private static function forbid() { return self::err( 'دسترسی ندارید.', 403, 'hs_forbidden' ); }
	private static function p( $r, $k, $d = '' ) { return HS_API_Util::p( $r, $k, $d ); }
	private static function ready() { return class_exists( 'HS_Timetable' ); }
	private static function need() { return self::err( 'افزونه‌ی «مدیریت کلاس» ۱.۷.۵ یا بالاتر لازم است.', 503, 'hs_unavailable' ); }

	/* ------------------------------ برنامه‌ی هفتگی ------------------------------ */

	private static function cell_row( $c ) {
		$t = get_userdata( (int) $c['teacher_id'] );
		return array(
			'day' => (int) $c['day'], 'period' => (int) $c['period'], 'span' => max( 1, (int) ( $c['span'] ?? 1 ) ), 'subject' => (string) $c['subject'],
			'teacher_id' => (int) $c['teacher_id'], 'teacher' => $t ? $t->display_name : '',
			'class_id' => (int) $c['class_id'], 'class' => get_the_title( (int) $c['class_id'] ),
		);
	}

	private static function bells_out( $b ) {
		return array( 'days' => (int) $b['days'], 'periods' => (int) $b['periods'], 'source' => $b['source'], 'slots' => array_values( $b['slots'] ), 'day_names' => array_slice( HS_Timetable::day_names(), 0, (int) $b['days'] ) );
	}

	private static function payload( $cid, $uid ) {
		$edit  = HS_Timetable::can_edit( $cid, $uid );
		$teach = array();
		if ( $edit ) {
			foreach ( HS_Timetable::teacher_options( $cid ) as $id => $name ) { $teach[] = array( 'id' => (int) $id, 'name' => $name ); }
		}
		return array(
			'class_id' => (int) $cid, 'class' => get_the_title( $cid ),
			'bells'    => self::bells_out( HS_Timetable::bells( $cid ) ),
			'cells'    => array_map( array( __CLASS__, 'cell_row' ), HS_Timetable::cells( $cid ) ),
			'can_edit' => $edit, 'teachers' => $teach,
			'is_manager' => HS_Timetable::is_school_manager( $cid, $uid ),
			'score'    => $edit ? HS_Timetable::score( $cid ) : null,
		);
	}

	public static function timetable( WP_REST_Request $r ) {
		if ( ! self::ready() ) { return self::need(); }
		$cid = (int) $r['id'];
		$uid = get_current_user_id();
		if ( 'hs_class' !== get_post_type( $cid ) || ! HS_Timetable::can_view( $cid, $uid ) ) { return self::forbid(); }
		return self::payload( $cid, $uid );
	}

	public static function timetable_save( WP_REST_Request $r ) {
		if ( ! self::ready() ) { return self::need(); }
		$cid = (int) $r['id'];
		$uid = get_current_user_id();
		if ( ! HS_Timetable::can_edit( $cid, $uid ) ) { return self::forbid(); }
		$res = HS_Timetable::save( $cid, (array) self::p( $r, 'cells', array() ), $uid );
		if ( is_wp_error( $res ) ) {
			$d = $res->get_error_data();
			return self::err( $res->get_error_message(), 409, 'hs_timetable_conflict', array( 'conflicts' => $d['conflicts'] ?? array() ) );
		}
		return self::payload( $cid, $uid );
	}

	private static function bells_input( WP_REST_Request $r ) {
		return array( 'days' => (int) self::p( $r, 'days', 5 ), 'slots' => (array) self::p( $r, 'slots', array() ) );
	}

	public static function bells_save( WP_REST_Request $r ) {
		if ( ! self::ready() ) { return self::need(); }
		$cid = (int) $r['id'];
		$uid = get_current_user_id();
		if ( ! HS_Timetable::can_edit( $cid, $uid ) ) { return self::forbid(); }
		if ( rest_sanitize_boolean( self::p( $r, 'reset', false ) ) ) {
			HS_Timetable::reset_bells( $cid );
		} else {
			$res = HS_Timetable::save_bells( 'class', $cid, self::bells_input( $r ), $uid );
			if ( is_wp_error( $res ) ) { return self::err( $res->get_error_message(), 'hs_forbidden' === $res->get_error_code() ? 403 : 400 ); }
		}
		return self::payload( $cid, $uid );
	}

	private static function manager_school( $uid ) {
		$sid = (int) HS_API_Access::school_id( $uid );
		return $sid && class_exists( 'HS_SO_Store' ) && HS_SO_Store::is_manager( $sid, $uid ) ? $sid : 0;
	}

	public static function school_bells() {
		if ( ! self::ready() ) { return self::need(); }
		$sid = self::manager_school( get_current_user_id() );
		if ( ! $sid ) { return self::forbid(); }
		return self::bells_out( HS_Timetable::school_bells( $sid ) );
	}

	public static function school_bells_save( WP_REST_Request $r ) {
		if ( ! self::ready() ) { return self::need(); }
		$uid = get_current_user_id();
		$sid = self::manager_school( $uid );
		if ( ! $sid ) { return self::forbid(); }
		$res = HS_Timetable::save_bells( 'school', $sid, self::bells_input( $r ), $uid );
		if ( is_wp_error( $res ) ) { return self::err( $res->get_error_message() ); }
		return self::bells_out( HS_Timetable::school_bells( $sid ) );
	}

	/**
	 * برنامه‌ی «من»: معلم ← زنگ‌های خودش در همه‌ی کلاس‌ها؛ دانش‌آموز ← کلاس خودش؛ والد ← ?child=ID
	 */
	public static function my_timetable( WP_REST_Request $r ) {
		if ( ! self::ready() ) { return self::need(); }
		$uid   = get_current_user_id();
		$child = (int) $r->get_param( 'child' );
		if ( $child ) {
			if ( ! HS_API_Access::is_parent_of( $uid, $child ) ) { return self::forbid(); }
			$cls = HS_API_Access::student_classes( $child );
			return $cls ? array_merge( self::payload( (int) $cls[0], $uid ), array( 'mode' => 'class' ) ) : array( 'mode' => 'none' );
		}
		if ( HS_API_Access::is_student( $uid ) ) {
			$cls = HS_API_Access::student_classes( $uid );
			return $cls ? array_merge( self::payload( (int) $cls[0], $uid ), array( 'mode' => 'class' ) ) : array( 'mode' => 'none' );
		}
		$cells = HS_Timetable::teacher_cells( $uid );
		$own   = HS_API_Routes_Teach::teacher_class_ids( $uid );
		$base  = $cells ? (int) $cells[0]['class_id'] : ( $own ? $own[0] : 0 );
		$cls   = array();
		foreach ( $own as $cid ) { $cls[] = array( 'id' => $cid, 'title' => get_the_title( $cid ) ); }
		return array(
			'mode'    => 'teacher',
			'bells'   => self::bells_out( $base ? HS_Timetable::bells( $base ) : array_merge( HS_Timetable::default_bells(), array( 'periods' => 5, 'source' => 'default' ) ) ),
			'cells'   => array_map( array( __CLASS__, 'cell_row' ), $cells ),
			'classes' => $cls,
		);
	}

	/* ------------------------------ ویرایش پیام ------------------------------ */

	public static function message_edit( WP_REST_Request $r ) {
		if ( ! method_exists( 'HS_Comms_DB', 'edit_message' ) ) { return self::need(); }
		$m = HS_Comms_DB::get_message( (int) $r['id'] );
		if ( ! $m ) { return self::err( 'پیام پیدا نشد.', 404, 'hs_not_found' ); }
		if ( (int) $m['sender_id'] !== get_current_user_id() ) { return self::err( 'فقط فرستنده می‌تواند پیام را ویرایش کند.', 403, 'hs_forbidden' ); }
		if ( self::age( $m ) > HS_Comms_DB::edit_window() ) {
			return self::err( 'مهلت ویرایش این پیام (۲۴ ساعت) گذشته است.', 400, 'hs_edit_expired' );
		}
		$body = HS_API_Util::textarea( self::p( $r, 'body' ), 2000 );
		if ( '' === trim( $body ) ) { return self::err( 'متن پیام خالی است.' ); }
		HS_Comms_DB::edit_message( (int) $m['id'], $body );
		return array( 'ok' => true, 'body' => $body );
	}

	/** سن پیام به ثانیه با ساعت خود پایگاه داده (created_at با ساعت پایگاه داده ثبت می‌شود) */
	private static function age( $m ) {
		static $now = null;
		if ( null === $now ) {
			global $wpdb;
			$now = strtotime( (string) $wpdb->get_var( 'SELECT NOW()' ) );
		}
		return $now - strtotime( (string) $m['created_at'] );
	}

	/** وضعیت و امکان ویرایش برای ردیف پیام (در گفت‌وگو) */
	public static function message_meta( $m, $uid ) {
		$mine = (int) $m['sender_id'] === (int) $uid;
		$age  = self::age( $m );
		return array(
			'receipt'      => method_exists( 'HS_Comms_DB', 'receipt' ) ? HS_Comms_DB::receipt( $m ) : ( (int) $m['is_read'] ? 'read' : 'sent' ),
			'delivered_at' => $m['delivered_at'] ?? null,
			'read_at'      => $m['read_at'] ?? null,
			'edited'       => ! empty( $m['edited_at'] ),
			'can_edit'     => $mine && method_exists( 'HS_Comms_DB', 'edit_message' ) && $age <= HS_Comms_DB::edit_window(),
		);
	}

	/* ------------------------------ سنجاق دیوار ------------------------------ */

	public static function wall_pin( WP_REST_Request $r ) {
		if ( ! method_exists( 'HS_Portfolio_DB', 'set_pinned' ) ) { return self::need(); }
		$p = HS_Portfolio_DB::get( (int) $r['id'] );
		if ( ! $p || HS_API_Access::WRITE !== HS_API_Access::class_level( (int) $p['class_id'], get_current_user_id() ) ) { return self::forbid(); }
		$on = rest_sanitize_boolean( self::p( $r, 'on', true ) );
		if ( $on && 'approved' !== $p['status'] ) { HS_Portfolio_DB::update_status( (int) $p['id'], 'approved' ); } // سنجاق یعنی روی دیوار هم باشد
		HS_Portfolio_DB::set_pinned( (int) $p['id'], $on );
		return array( 'ok' => true, 'pinned' => $on );
	}

	/* ------------------------------ بستن کلاس برای عضو جدید ------------------------------ */

	public static function join_toggle( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		if ( HS_API_Access::WRITE !== HS_API_Access::class_level( $cid, get_current_user_id() ) ) { return self::forbid(); }
		if ( ! method_exists( 'HS_Class_Students', 'set_join_closed' ) ) { return self::err( 'افزونه‌ی «مدیریت کلاس» ۱.۷.۷ یا بالاتر لازم است.', 503, 'hs_unavailable' ); }
		$open = rest_sanitize_boolean( self::p( $r, 'open', true ) );
		HS_Class_Students::set_join_closed( $cid, ! $open );
		return array( 'ok' => true, 'join_open' => $open, 'message' => $open ? 'پذیرش والد جدید با کد کلاس باز شد.' : 'پذیرش والد جدید با کد کلاس بسته شد.' );
	}

	/* ------------------------------ ترتیب دانش‌آموزان ------------------------------ */

	public static function students_order( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		if ( HS_API_Access::WRITE !== HS_API_Access::class_level( $cid, get_current_user_id() ) ) { return self::forbid(); }
		$mode = sanitize_key( (string) self::p( $r, 'mode', 'first' ) );
		if ( ! in_array( $mode, array( 'first', 'last', 'custom' ), true ) ) { return self::err( 'نوع مرتب‌سازی نامعتبر است.' ); }
		update_post_meta( $cid, '_hs_sort', $mode );
		if ( 'custom' === $mode ) {
			$members = HS_API_Access::class_members( $cid );
			$order   = array_values( array_intersect( array_map( 'intval', (array) self::p( $r, 'order', array() ) ), $members ) );
			update_post_meta( $cid, '_hs_order', $order );
		}
		return array( 'ok' => true, 'mode' => $mode );
	}

	/* ------------------------------ منابع درسی ------------------------------ */

	public static function budget_sources( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Budget' ) || ! method_exists( 'HS_Budget', 'set_teacher_sources' ) ) { return self::need(); }
		$uid = get_current_user_id();
		if ( ! HS_API_Routes_Teach::teacher_class_ids( $uid ) ) { return self::forbid(); }
		return array( 'mine' => HS_Budget::set_teacher_sources( $uid, (array) self::p( $r, 'sources', array() ) ) );
	}

	public static function school_subjects( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Budget' ) || ! method_exists( 'HS_Budget', 'school_subjects' ) ) { return self::need(); }
		$sid = self::manager_school( get_current_user_id() );
		if ( ! $sid ) { return self::forbid(); }
		$g    = (int) $r->get_param( 'grade' );
		$list = HS_Budget::school_subjects( $sid, $g );
		if ( ! $list ) {
			foreach ( HS_Budget::default_subjects( self::grade_label( $g ) ) as $n ) { $list[] = array( 'name' => $n, 'unit' => 'lesson', 'total' => 0 ); }
		}
		return array( 'grade' => $g, 'subjects' => $list, 'units' => HS_Budget::units() );
	}

	private static function grade_label( $n ) {
		$l = array( 'پیش‌دبستانی', 'اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم' );
		return $l[ max( 0, min( 12, (int) $n ) ) ];
	}

	public static function school_subjects_save( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Budget' ) || ! method_exists( 'HS_Budget', 'set_school_subjects' ) ) { return self::need(); }
		$sid = self::manager_school( get_current_user_id() );
		if ( ! $sid ) { return self::forbid(); }
		$g = (int) self::p( $r, 'grade', 1 );
		if ( $g < 0 || $g > 12 ) { return self::err( 'پایه نامعتبر است.' ); }
		return array( 'grade' => $g, 'subjects' => HS_Budget::set_school_subjects( $sid, $g, (array) self::p( $r, 'subjects', array() ) ) );
	}

	/* ------------------------------ داشبورد معلم: دیوار و اطلاعیه‌ها ------------------------------ */

	public static function teacher_feed( WP_REST_Request $r ) {
		global $wpdb;
		$uid = get_current_user_id();
		$ids = HS_API_Routes_Teach::teacher_class_ids( $uid );
		$one = (int) $r->get_param( 'class_id' );
		if ( $one ) {
			if ( ! in_array( $one, $ids, true ) ) { return self::forbid(); }
			$ids = array( $one );
		}
		if ( ! $ids ) { return array( 'wall' => array(), 'announcements' => array() ); }
		$in   = implode( ',', array_map( 'intval', $ids ) );
		$wall = array();
		if ( class_exists( 'HS_Portfolio_DB' ) ) {
			$order = method_exists( 'HS_Portfolio_DB', 'set_pinned' ) ? 'is_pinned DESC, pinned_at DESC, id DESC' : 'id DESC';
			$rows  = $wpdb->get_results( 'SELECT * FROM ' . HS_Portfolio_DB::table() . " WHERE class_id IN ($in) AND status <> 'rejected' ORDER BY $order LIMIT 8", ARRAY_A ); // phpcs:ignore
			foreach ( (array) $rows as $p ) {
				$s      = get_userdata( (int) $p['student_id'] );
				$wall[] = array(
					'id' => (int) $p['id'], 'class_id' => (int) $p['class_id'], 'class' => get_the_title( (int) $p['class_id'] ), 'student' => $s ? $s->display_name : '',
					'avatar' => HS_API_Util::avatar( (int) $p['student_id'], 96 ), 'title' => (string) $p['title'], 'image' => (string) $p['media_url'],
					'on_wall' => 'approved' === $p['status'], 'pinned' => ! empty( $p['is_pinned'] ), 'at' => $p['created_at'],
				);
			}
		}
		$anns = array();
		if ( class_exists( 'HS_Comms_DB' ) ) {
			foreach ( $ids as $cid ) {
				$list = method_exists( 'HS_Comms_DB', 'ann_state' ) ? HS_Comms_DB::get_announcements( $cid, 5, true ) : HS_Comms_DB::get_announcements( $cid, 5 );
				foreach ( (array) $list as $a ) {
					$anns[] = array(
						'id' => (int) $a['id'], 'class_id' => (int) $cid, 'class' => get_the_title( $cid ), 'title' => (string) $a['title'], 'body' => wp_trim_words( wp_strip_all_tags( $a['body'] ), 30, '…' ),
						'pinned' => (bool) $a['is_pinned'], 'created_at' => $a['created_at'], 'show_from' => $a['show_from'] ?? null, 'show_until' => $a['show_until'] ?? null,
						'state' => method_exists( 'HS_Comms_DB', 'ann_state' ) ? HS_Comms_DB::ann_state( $a ) : 'active', 'can_edit' => HS_API_Routes_More::can_edit_ann( $a, $uid ),
					);
				}
			}
			usort( $anns, function ( $x, $y ) { return strcmp( (string) $y['created_at'], (string) $x['created_at'] ); } );
		}
		return array( 'wall' => $wall, 'announcements' => array_slice( $anns, 0, 6 ) );
	}

	/* ------------------------------ نسخه‌ی اپ ------------------------------ */

	/** دو اپ: family (والدین و دانش‌آموزان) و teacher (کادر مدرسه) */
	public static function apps() {
		return array( 'family' => 'همشاگردی (والدین و دانش‌آموزان)', 'teacher' => 'همشاگردی معلم (کادر مدرسه)' );
	}

	public static function release( $app = 'family' ) {
		$app = 'teacher' === $app ? 'teacher' : 'family';
		$r   = get_option( 'teacher' === $app ? self::APP_OPT . '_teacher' : self::APP_OPT, array() );
		return is_array( $r ) ? $r : array();
	}

	public static function app_latest( WP_REST_Request $req ) {
		$app = 'teacher' === $req->get_param( 'app' ) ? 'teacher' : 'family';
		$r   = self::release( $app );
		$o   = self::release( 'teacher' === $app ? 'family' : 'teacher' );
		if ( empty( $r['url'] ) ) { return array( 'available' => false, 'app' => $app, 'other_url' => ! empty( $o['url'] ) ? esc_url_raw( $o['url'] ) : '' ); }
		return array(
			'available' => true, 'version' => (string) $r['version'], 'build' => (int) $r['build'], 'url' => esc_url_raw( $r['url'] ),
			'size' => (int) ( $r['size'] ?? 0 ), 'notes' => (string) ( $r['notes'] ?? '' ), 'min_build' => (int) ( $r['min_build'] ?? 0 ),
			'date' => (string) ( $r['date'] ?? '' ), 'app' => $app,
			'other_url' => ! empty( $o['url'] ) ? esc_url_raw( $o['url'] ) : '',
		);
	}

	public static function me_name( WP_REST_Request $r ) {
		$uid   = get_current_user_id();
		$first = HS_API_Util::text( self::p( $r, 'first_name' ), 60 );
		$last  = HS_API_Util::text( self::p( $r, 'last_name' ), 60 );
		if ( mb_strlen( $first ) < 2 || mb_strlen( $last ) < 2 ) { return self::err( 'نام و نام خانوادگی را کامل بنویسید.' ); }
		$lim = HS_API_Util::limited( 'name_' . $uid, 10 );
		if ( $lim ) { return $lim; }
		HS_API_Util::hit( 'name_' . $uid, DAY_IN_SECONDS );
		wp_update_user( array( 'ID' => $uid, 'first_name' => $first, 'last_name' => $last, 'display_name' => $first . ' ' . $last ) );
		return array( 'ok' => true, 'name' => $first . ' ' . $last );
	}

	public static function admin_menu() {
		add_options_page( 'اپ موبایل', 'اپ موبایل', 'manage_options', 'hs-api-app', array( __CLASS__, 'admin_page' ) );
	}

	public static function admin_page() {
		echo '<div class="wrap" dir="rtl"><h1>اپ‌های موبایل همشاگردی — فایل نصب</h1>';
		if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>ذخیره شد.</p></div>'; } // phpcs:ignore
		if ( isset( $_GET['err'] ) ) { echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['err'] ) ) ) . '</p></div>'; } // phpcs:ignore
		foreach ( self::apps() as $app => $label ) {
			$r = self::release( $app );
			echo '<hr><h2>' . esc_html( $label ) . '</h2>';
			if ( ! empty( $r['url'] ) ) {
				echo '<p>نسخه‌ی فعلی: <b>' . esc_html( $r['version'] ) . '</b> (ساخت ' . (int) $r['build'] . ') — <a href="' . esc_url( $r['url'] ) . '">دانلود</a> — ' . esc_html( size_format( (int) ( $r['size'] ?? 0 ) ) ) . '</p>';
			} else {
				echo '<p>هنوز فایلی بارگذاری نشده است.</p>';
			}
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'hs_api_app' );
			echo '<input type="hidden" name="action" value="hs_api_app_save"><input type="hidden" name="app" value="' . esc_attr( $app ) . '"><table class="form-table">';
			echo '<tr><th>فایل APK</th><td><input type="file" name="apk" accept=".apk"> <p class="description">' . ( 'teacher' === $app ? 'فایل hamshagerdi-teacher-apk از گیت‌هاب.' : 'فایل hamshagerdi-family-apk از گیت‌هاب.' ) . '</p></td></tr>';
			echo '<tr><th>نسخه</th><td><input name="version" value="' . esc_attr( $r['version'] ?? '' ) . '" placeholder="0.8.0" dir="ltr"></td></tr>';
			echo '<tr><th>شماره‌ی ساخت</th><td><input name="build" type="number" value="' . esc_attr( $r['build'] ?? '' ) . '" dir="ltr"> <p class="description">همان شماره‌ی اجرای گیت‌هاب.</p></td></tr>';
			echo '<tr><th>تغییرات</th><td><textarea name="notes" rows="3" class="large-text">' . esc_textarea( $r['notes'] ?? '' ) . '</textarea></td></tr>';
			echo '<tr><th>اجباری</th><td><label><input type="checkbox" name="force" value="1"' . checked( ! empty( $r['min_build'] ) && (int) $r['min_build'] >= (int) ( $r['build'] ?? 0 ), true, false ) . '> نسخه‌های قدیمی‌تر باید به‌روزرسانی کنند</label></td></tr>';
			echo '</table>';
			submit_button( 'ذخیره‌ی ' . ( 'teacher' === $app ? 'اپ معلم' : 'اپ والدین' ) );
			echo '</form>';
		}
		echo '<hr><p>دکمه‌ی دانلود: <code>[hamshagerdi_app_download]</code> (هر دو اپ)، یا <code>[hamshagerdi_app_download app="family"]</code> / <code>[hamshagerdi_app_download app="teacher"]</code></p></div>';
	}

	public static function admin_save() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hs_api_app' ) ) { wp_die( 'دسترسی ندارید.' ); }
		$app     = 'teacher' === ( $_POST['app'] ?? '' ) ? 'teacher' : 'family'; // phpcs:ignore
		$r       = self::release( $app );
		$version = sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) );
		$build   = absint( $_POST['build'] ?? 0 );
		$back    = admin_url( 'options-general.php?page=hs-api-app' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) || ! $build ) {
			wp_safe_redirect( add_query_arg( 'err', rawurlencode( 'نسخه (مثل 0.8.0) و شماره‌ی ساخت را وارد کنید.' ), $back ) );
			exit;
		}
		if ( ! empty( $_FILES['apk']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$f = $_FILES['apk']; // phpcs:ignore
			if ( 'apk' !== strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) ) ) {
				wp_safe_redirect( add_query_arg( 'err', rawurlencode( 'فقط فایل APK.' ), $back ) );
				exit;
			}
			$f['name'] = ( 'teacher' === $app ? 'hamshagerdi-teacher-' : 'hamshagerdi-' ) . $version . '.apk';
			$up        = wp_handle_upload( $f, array( 'test_form' => false, 'test_type' => false, 'mimes' => array( 'apk' => 'application/vnd.android.package-archive' ) ) );
			if ( isset( $up['error'] ) ) {
				wp_safe_redirect( add_query_arg( 'err', rawurlencode( $up['error'] ), $back ) );
				exit;
			}
			$r['url']  = $up['url'];
			$r['size'] = (int) filesize( $up['file'] );
		}
		if ( empty( $r['url'] ) ) {
			wp_safe_redirect( add_query_arg( 'err', rawurlencode( 'فایل APK را انتخاب کنید.' ), $back ) );
			exit;
		}
		$r['version']   = $version;
		$r['build']     = $build;
		$r['notes']     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
		$r['min_build'] = ! empty( $_POST['force'] ) ? $build : 0;
		$r['date']      = current_time( 'Y-m-d' );
		update_option( 'teacher' === $app ? self::APP_OPT . '_teacher' : self::APP_OPT, $r, false );
		wp_safe_redirect( add_query_arg( 'saved', 1, $back ) );
		exit;
	}

	/** دکمه‌ی دانلود اپ (برای صفحه‌ی اول یا هر صفحه‌ی دیگر) */
	public static function download_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'app' => '' ), (array) $atts, 'hamshagerdi_app_download' );
		$list = in_array( $atts['app'], array( 'family', 'teacher' ), true ) ? array( $atts['app'] ) : array( 'family', 'teacher' );
		$out  = '';
		foreach ( $list as $app ) {
			$r = self::release( $app );
			if ( empty( $r['url'] ) ) { continue; }
			$url   = esc_url( $r['url'] );
			$id    = 'hsapp' . $app . wp_rand( 100, 999 );
			$title = 'teacher' === $app ? '👩‍🏫 همشاگردی معلم' : '📱 همشاگردی';
			$desc  = 'teacher' === $app ? 'برای معلمان، مدیران و معاونان: حضور و غیاب، تکلیف، نمره، برنامه‌ی هفتگی و ارتباط با والدین.' : 'برای والدین و دانش‌آموزان: حضور، تکالیف، امتیازها، پیام به معلم و دیوار همشاگردی.';
			$out  .= '<div class="hs-app-dl" dir="rtl" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;background:#FFF8F2;border-radius:20px;padding:18px;margin-bottom:14px">'
				. '<div style="flex:1;min-width:220px"><h3 style="margin:0 0 6px">' . $title . '</h3>'
				. '<p style="margin:0 0 10px;color:#555">' . esc_html( $desc ) . ' نسخه ' . esc_html( $r['version'] ) . ' — ' . esc_html( size_format( (int) ( $r['size'] ?? 0 ) ) ) . '</p>'
				. '<a href="' . $url . '" style="display:inline-block;background:' . ( 'teacher' === $app ? '#16213E' : '#F57C00' ) . ';color:#fff;padding:12px 22px;border-radius:14px;text-decoration:none;font-weight:700">⬇️ دانلود برای اندروید</a>'
				. '<details style="margin-top:10px"><summary style="cursor:pointer">راهنمای نصب</summary><ol style="line-height:2">'
				. '<li>فایل را دانلود و باز کنید.</li><li>اگر گوشی پرسید، «اجازه‌ی نصب از این منبع» را روشن کنید.</li><li>«نصب» را بزنید و با شماره‌ی موبایل خود وارد شوید.</li></ol></details></div>'
				. '<div id="' . esc_attr( $id ) . '" style="background:#fff;padding:8px;border-radius:12px" title="با دوربین گوشی اسکن کنید"></div></div>';
			if ( defined( 'HS_CLASS_URL' ) ) {
				wp_enqueue_script( 'hs-qrcode', HS_CLASS_URL . 'assets/js/qrcode.js', array(), HS_API_VERSION, true );
				wp_add_inline_script( 'hs-qrcode', 'document.addEventListener("DOMContentLoaded",function(){try{var q=qrcode(0,"M");q.addData(' . wp_json_encode( $url ) . ');q.make();document.getElementById(' . wp_json_encode( $id ) . ').innerHTML=q.createSvgTag(4,4);}catch(e){}});' );
			}
		}
		return $out;
	}

}
