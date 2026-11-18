<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ابزارهای معلم و تقویم (نسخه‌ی ۲.۵): ارزیابی و نمرات، بودجه‌بندی، تقویم آموزشی/مناسبتی،
 * کتابخانه‌ی کاربرگ، پیوست تکلیف، حذف پیام، برچسب طرف گفت‌وگو، گزارش و تقویم والدین.
 * همه‌ی دسترسی‌ها اینجا در سرور بررسی می‌شود.
 */
class HS_API_Routes_Teach {

	public static function register() {
		$ns = HS_API_NS;
		$a  = array( 'HS_API_Routes', 'authed' );
		$r  = function ( $route, $method, $cb ) use ( $ns, $a ) {
			register_rest_route( $ns, $route, array( 'methods' => $method, 'callback' => array( __CLASS__, $cb ), 'permission_callback' => $a ) );
		};
		// ارزیابی و نمرات
		$r( '/classes/(?P<id>\d+)/assessments', 'GET', 'assessments' );
		$r( '/classes/(?P<id>\d+)/assessments', 'POST', 'assessment_create' );
		$r( '/assessments/(?P<id>\d+)', 'GET', 'assessment' );
		$r( '/assessments/(?P<id>\d+)/results', 'POST', 'assessment_save' );
		$r( '/assessments/(?P<id>\d+)', 'DELETE', 'assessment_delete' );
		// بودجه‌بندی
		$r( '/classes/(?P<id>\d+)/budget', 'GET', 'budget' );
		$r( '/classes/(?P<id>\d+)/budget', 'POST', 'budget_save' );
		// تقویم
		$r( '/calendar', 'GET', 'calendar' );
		$r( '/calendar', 'POST', 'calendar_add' );
		$r( '/calendar/(?P<id>\d+)', 'DELETE', 'calendar_delete' );
		// کاربرگ
		$r( '/worksheets', 'GET', 'worksheets' );
		$r( '/worksheets/(?P<id>\d+)/assign', 'POST', 'worksheet_assign' );
		// پیوست
		$r( '/uploads', 'POST', 'upload' );
		// پیام
		$r( '/messages/item/(?P<id>\d+)', 'DELETE', 'message_delete' );
		// والدین
		$r( '/children/(?P<id>\d+)/progress', 'GET', 'child_progress' );
		$r( '/children/(?P<id>\d+)/calendar', 'GET', 'child_calendar' );
	}

	/* ------------------------------ کمکی ------------------------------ */

	private static function err( $m, $s = 400, $c = 'hs_error' ) { return HS_API_Util::err( $m, $s, $c ); }
	private static function forbid() { return self::err( 'دسترسی ندارید.', 403, 'hs_forbidden' ); }
	private static function p( $r, $k, $d = '' ) { return HS_API_Util::p( $r, $k, $d ); }
	private static function level( $cid ) { return HS_API_Access::class_level( (int) $cid, get_current_user_id() ); }
	private static function can_write( $cid ) { return HS_API_Access::WRITE === self::level( $cid ); }
	private static function can_see_staff( $cid ) { return in_array( self::level( $cid ), array( HS_API_Access::WRITE, HS_API_Access::SUPER ), true ); }

	/** کلاس‌هایی که کاربر معلم آن است */
	public static function teacher_class_ids( $uid ) {
		$out = array();
		foreach ( HS_API_Access::class_ids( $uid ) as $cid ) {
			if ( HS_API_Access::WRITE === HS_API_Access::class_level( $cid, $uid ) ) { $out[] = (int) $cid; }
		}
		return $out;
	}

	/** پایه‌ی کلاس ← مقطع (preschool | primary | middle | high) */
	public static function grade_level( $grade ) {
		$g = str_replace( array( 'ي', 'ك' ), array( 'ی', 'ک' ), trim( (string) $grade ) );
		if ( '' === $g ) { return ''; }
		if ( false !== mb_strpos( $g, 'پیش' ) ) { return 'preschool'; }
		$map = array( 'اول' => 1, 'دوم' => 2, 'سوم' => 3, 'چهارم' => 4, 'پنجم' => 5, 'ششم' => 6, 'هفتم' => 7, 'هشتم' => 8, 'نهم' => 9, 'دهم' => 10, 'یازدهم' => 11, 'دوازدهم' => 12 );
		$n   = 0;
		foreach ( $map as $k => $v ) {
			if ( false !== mb_strpos( $g, $k ) ) { $n = max( $n, $v ); }
		}
		if ( ! $n && preg_match( '/\d+/', HS_API_Util::digits( $g ), $m ) ) { $n = (int) $m[0]; }
		if ( ! $n ) { return ''; }
		return $n <= 6 ? 'primary' : ( $n <= 9 ? 'middle' : 'high' );
	}

	public static function levels() {
		return array( 'preschool' => 'پیش‌دبستانی', 'primary' => 'ابتدایی', 'middle' => 'متوسطه اول', 'high' => 'متوسطه دوم' );
	}

	/* ------------------------------ ارزیابی و نمرات ------------------------------ */

	private static function levels_list() {
		$out = array();
		foreach ( HS_Assessment_DB::descriptive_levels() as $k => $v ) {
			$out[] = array( 'key' => $k, 'label' => $v['label'], 'color' => $v['color'] );
		}
		return $out;
	}

	public static function assessments( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		if ( ! class_exists( 'HS_Assessment_DB' ) ) { return self::err( 'بخش ارزیابی فعال نیست.', 503, 'hs_unavailable' ); }
		if ( ! self::can_see_staff( $cid ) ) { return self::forbid(); }
		$total = count( HS_API_Access::class_members( $cid ) );
		$out   = array();
		foreach ( (array) HS_Assessment_DB::get_class_assessments( $cid, 100 ) as $a ) {
			$res   = HS_Assessment_DB::get_results( $a['id'] );
			$out[] = array(
				'id' => (int) $a['id'], 'title' => $a['title'], 'subject' => (string) $a['subject'], 'scale' => $a['scale_type'],
				'date' => (string) ( $a['assess_date'] ? $a['assess_date'] : substr( (string) $a['created_at'], 0, 10 ) ),
				'recorded' => count( (array) $res ), 'students' => $total,
			);
		}
		return array( 'items' => $out, 'levels' => self::levels_list(), 'can_edit' => self::can_write( $cid ) );
	}

	public static function assessment_create( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		if ( ! class_exists( 'HS_Assessment_DB' ) ) { return self::err( 'بخش ارزیابی فعال نیست.', 503, 'hs_unavailable' ); }
		if ( ! self::can_write( $cid ) ) { return self::forbid(); }
		$title = HS_API_Util::text( self::p( $r, 'title' ), 190 );
		if ( '' === $title ) { return self::err( 'عنوان ارزیابی را بنویسید.' ); }
		$scale = 'numeric' === self::p( $r, 'scale' ) ? 'numeric' : 'descriptive';
		$date  = HS_API_Util::date( (string) self::p( $r, 'date' ) );
		$id    = HS_Assessment_DB::create_assessment( $cid, $title, HS_API_Util::text( self::p( $r, 'subject' ), 120 ), $scale, $date ? $date : null, HS_API_Util::textarea( self::p( $r, 'notes' ), 1000 ) );
		return new WP_REST_Response( array( 'id' => (int) $id ), 201 );
	}

	private static function assessment_row( $id ) {
		return class_exists( 'HS_Assessment_DB' ) ? HS_Assessment_DB::get_assessment( (int) $id ) : null;
	}

	public static function assessment( WP_REST_Request $r ) {
		$a = self::assessment_row( $r['id'] );
		if ( ! $a || ! self::can_see_staff( $a['class_id'] ) ) { return self::forbid(); }
		$res  = HS_Assessment_DB::get_results( $a['id'] );
		$rows = array();
		foreach ( HS_API_Access::class_members( (int) $a['class_id'] ) as $sid ) {
			$u  = get_userdata( $sid );
			$x  = $res[ $sid ] ?? null;
			$rows[] = array(
				'id' => $sid, 'name' => $u ? $u->display_name : '', 'avatar' => HS_API_Util::avatar( $sid, 96 ),
				'level' => $x ? (string) $x['level_key'] : '', 'score' => $x && null !== $x['score'] ? (float) $x['score'] : null, 'comment' => $x ? (string) $x['comment'] : '',
			);
		}
		usort( $rows, function ( $x, $y ) { return strcmp( $x['name'], $y['name'] ); } );
		return array(
			'id' => (int) $a['id'], 'class_id' => (int) $a['class_id'], 'title' => $a['title'], 'subject' => (string) $a['subject'], 'scale' => $a['scale_type'],
			'date' => (string) $a['assess_date'], 'notes' => (string) $a['notes'], 'levels' => self::levels_list(), 'students' => $rows,
			'summary' => HS_Assessment_DB::get_summary( $a['id'] ), 'can_edit' => self::can_write( $a['class_id'] ),
		);
	}

	public static function assessment_save( WP_REST_Request $r ) {
		$a = self::assessment_row( $r['id'] );
		if ( ! $a || ! self::can_write( $a['class_id'] ) ) { return self::forbid(); }
		$members = HS_API_Access::class_members( (int) $a['class_id'] );
		$levels  = HS_Assessment_DB::descriptive_levels();
		$n       = 0;
		foreach ( (array) self::p( $r, 'results', array() ) as $sid => $d ) {
			$sid = (int) $sid;
			if ( ! in_array( $sid, $members, true ) || ! is_array( $d ) ) { continue; }
			$lvl   = sanitize_key( (string) ( $d['level'] ?? '' ) );
			$score = isset( $d['score'] ) && '' !== $d['score'] && null !== $d['score'] ? (float) HS_API_Util::digits( (string) $d['score'] ) : null;
			if ( null !== $score && ( $score < 0 || $score > 100 ) ) { return self::err( 'نمره باید بین ۰ تا ۱۰۰ باشد.' ); }
			HS_Assessment_DB::save_result( $a['id'], $sid, isset( $levels[ $lvl ] ) ? $lvl : null, $score, HS_API_Util::textarea( $d['comment'] ?? '', 500 ) );
			$n++;
		}
		return array( 'saved' => $n );
	}

	public static function assessment_delete( WP_REST_Request $r ) {
		$a = self::assessment_row( $r['id'] );
		if ( ! $a || ! self::can_write( $a['class_id'] ) ) { return self::forbid(); }
		HS_Assessment_DB::delete_assessment( $a['id'] );
		return array( 'ok' => true );
	}

	/* ------------------------------ بودجه‌بندی ------------------------------ */

	public static function budget_data( $cid ) {
		$rows     = class_exists( 'HS_Budget' ) ? HS_Budget::get( $cid ) : array();
		$expected = class_exists( 'HS_Budget' ) ? (int) round( HS_Budget::elapsed_fraction() * 100 ) : 0;
		$out      = array();
		foreach ( $rows as $x ) {
			$pct   = $x['planned'] ? (int) round( $x['done'] * 100 / $x['planned'] ) : 0;
			$out[] = array_merge( $x, array( 'pct' => $pct, 'status' => $pct - $expected >= 5 ? 'ahead' : ( $pct - $expected <= -10 ? 'behind' : 'on_track' ) ) );
		}
		return array( 'rows' => $out, 'expected' => $expected );
	}

	public static function budget( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		if ( ! class_exists( 'HS_Budget' ) ) { return self::err( 'بودجه‌بندی روی سایت فعال نیست.', 503, 'hs_unavailable' ); }
		if ( ! self::can_see_staff( $cid ) ) { return self::forbid(); }
		return array_merge( self::budget_data( $cid ), array( 'subjects' => HS_Budget::default_subjects(), 'can_edit' => self::can_write( $cid ) ) );
	}

	public static function budget_save( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		if ( ! class_exists( 'HS_Budget' ) ) { return self::err( 'بودجه‌بندی روی سایت فعال نیست.', 503, 'hs_unavailable' ); }
		if ( ! self::can_write( $cid ) ) { return self::forbid(); }
		$rows = array();
		foreach ( array_slice( (array) self::p( $r, 'rows', array() ), 0, 30 ) as $x ) {
			$sub = HS_API_Util::text( $x['subject'] ?? '', 60 );
			if ( '' === $sub ) { continue; }
			$pl     = max( 0, min( 999, (int) HS_API_Util::digits( (string) ( $x['planned'] ?? 0 ) ) ) );
			$rows[] = array( 'subject' => $sub, 'planned' => $pl, 'done' => max( 0, min( $pl, (int) HS_API_Util::digits( (string) ( $x['done'] ?? 0 ) ) ) ) );
		}
		update_post_meta( $cid, HS_Budget::META, $rows );
		return self::budget_data( $cid );
	}

	/* ------------------------------ تقویم ------------------------------ */

	private static function class_school( $cid ) {
		return (int) get_post_meta( (int) $cid, '_hs_school_id', true );
	}

	/** آیا رویداد برای کاربر قابل دیدن است؟ */
	private static function event_visible( $e, $uid, $ctx ) {
		$cid = (int) $e['class_id'];
		$sch = (int) $e['school_id'];
		$lvl = (string) ( $e['level'] ?? '' );
		if ( (int) $e['created_by'] === $uid ) { return true; }
		if ( $cid ) { return in_array( $cid, $ctx['classes'], true ); }
		if ( $sch && ! in_array( $sch, $ctx['schools'], true ) ) { return false; }
		if ( $lvl && $ctx['levels'] && ! in_array( $lvl, $ctx['levels'], true ) && ! $ctx['manager'] ) { return false; }
		$aud = (string) $e['audience'];
		if ( $ctx['staff'] ) { return in_array( $aud, array( 'teachers', 'all', 'parents' ), true ); }
		return in_array( $aud, array( 'parents', 'all' ), true );
	}

	/** کلاس‌ها، مدرسه‌ها و مقطع‌های مرتبط با کاربر (برای خودش یا فرزندش) */
	private static function context( $uid, $child = 0 ) {
		$classes = $child ? HS_API_Access::student_classes( $child ) : HS_API_Access::class_ids( $uid );
		$classes = array_values( array_map( 'intval', (array) $classes ) );
		$schools = array();
		$levels  = array();
		foreach ( $classes as $cid ) {
			$s = self::class_school( $cid );
			if ( $s ) { $schools[] = $s; }
			$l = self::grade_level( get_post_meta( $cid, '_hs_grade', true ) );
			if ( $l ) { $levels[] = $l; }
		}
		$own = HS_API_Access::school_id( $uid );
		$mgr = $own && class_exists( 'HS_SO_Store' ) && HS_SO_Store::can_supervise( $own, $uid );
		if ( $own && ! $child ) { $schools[] = (int) $own; }
		$staff = ! $child && ( self::teacher_class_ids( $uid ) || $mgr );
		return array( 'classes' => $classes, 'schools' => array_values( array_unique( $schools ) ), 'levels' => array_values( array_unique( $levels ) ), 'staff' => $staff, 'manager' => $mgr, 'own_school' => (int) $own );
	}

	private static function event_row( $e, $uid, $ctx ) {
		$types = HS_Calendar_DB::types();
		$lv    = self::levels();
		$can   = (int) $e['created_by'] === $uid || ( $ctx['manager'] && (int) $e['school_id'] === $ctx['own_school'] && $ctx['own_school'] ) || ( (int) $e['class_id'] && HS_API_Access::WRITE === HS_API_Access::class_level( (int) $e['class_id'], $uid ) );
		return array(
			'id' => (int) $e['id'], 'title' => $e['title'], 'description' => (string) $e['description'], 'date' => $e['event_date'], 'end_date' => (string) ( $e['end_date'] ?? '' ),
			'type' => $e['event_type'], 'type_label' => $types[ $e['event_type'] ] ?? '', 'audience' => $e['audience'],
			'class_id' => (int) $e['class_id'], 'class' => (int) $e['class_id'] ? get_the_title( (int) $e['class_id'] ) : '',
			'level' => (string) ( $e['level'] ?? '' ), 'level_label' => $lv[ $e['level'] ?? '' ] ?? '', 'scope' => (int) $e['class_id'] ? 'class' : ( (int) $e['school_id'] ? 'school' : 'site' ),
			'holiday' => 'holiday' === $e['event_type'], 'can_delete' => $can, 'source' => 'hs',
		);
	}

	/** رویدادهای افزونه‌ی «تقویم مناسبتی» سایت (فقط خواندنی) */
	private static function oc_events( $from, $to ) {
		if ( ! function_exists( 'hs_oc_upcoming' ) ) { return array(); }
		$out = array();
		foreach ( (array) hs_oc_upcoming( 60 ) as $i => $e ) {
			$d = (string) ( $e['date'] ?? '' );
			if ( ! $d || $d < $from || $d > $to ) { continue; }
			$out[] = array(
				'id' => 0, 'title' => (string) ( $e['title'] ?? '' ), 'description' => '', 'date' => $d, 'end_date' => '', 'type' => ! empty( $e['is_holiday'] ) ? 'holiday' : 'occasion',
				'type_label' => ! empty( $e['is_holiday'] ) ? 'تعطیل' : 'مناسبت', 'audience' => 'all', 'class_id' => 0, 'class' => '', 'level' => '', 'level_label' => '',
				'scope' => 'site', 'holiday' => ! empty( $e['is_holiday'] ), 'can_delete' => false, 'source' => 'oc',
			);
		}
		return $out;
	}

	private static function range( WP_REST_Request $r ) {
		$from = HS_API_Util::date( (string) $r->get_param( 'from' ) );
		$to   = HS_API_Util::date( (string) $r->get_param( 'to' ) );
		$from = $from ? $from : HS_API_Util::today();
		$to   = $to ? $to : gmdate( 'Y-m-d', strtotime( $from . ' +60 days' ) );
		if ( strtotime( $to ) - strtotime( $from ) > 400 * DAY_IN_SECONDS ) { $to = gmdate( 'Y-m-d', strtotime( $from . ' +400 days' ) ); }
		return array( $from, $to );
	}

	public static function events_for( $uid, $from, $to, $child = 0 ) {
		if ( ! class_exists( 'HS_Calendar_DB' ) ) { return array(); }
		$ctx = self::context( $uid, $child );
		$out = array();
		foreach ( (array) HS_Calendar_DB::in_range( $from, $to ) as $e ) {
			if ( self::event_visible( $e, $uid, $ctx ) ) { $out[] = self::event_row( $e, $uid, $ctx ); }
		}
		$out = array_merge( $out, self::oc_events( $from, $to ) );
		usort( $out, function ( $x, $y ) { return strcmp( $x['date'], $y['date'] ); } );
		return $out;
	}

	public static function calendar( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		list( $from, $to ) = self::range( $r );
		$ctx = self::context( $uid );
		$cls = array();
		foreach ( self::teacher_class_ids( $uid ) as $cid ) { $cls[] = array( 'id' => $cid, 'title' => get_the_title( $cid ) ); }
		return array(
			'from' => $from, 'to' => $to, 'events' => self::events_for( $uid, $from, $to ),
			'types' => HS_Calendar_DB::types(), 'levels' => self::levels(),
			'can_add_school' => $ctx['manager'], 'can_add_class' => (bool) $cls, 'classes' => $cls,
		);
	}

	public static function calendar_add( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Calendar_DB' ) ) { return self::err( 'تقویم روی سایت فعال نیست.', 503, 'hs_unavailable' ); }
		$uid   = get_current_user_id();
		$ctx   = self::context( $uid );
		$title = HS_API_Util::text( self::p( $r, 'title' ), 190 );
		$date  = HS_API_Util::date( (string) self::p( $r, 'date' ) );
		$end   = HS_API_Util::date( (string) self::p( $r, 'end_date' ) );
		if ( '' === $title || ! $date ) { return self::err( 'عنوان و تاریخ لازم است.' ); }
		if ( $end && $end < $date ) { return self::err( 'تاریخ پایان قبل از شروع است.' ); }
		$type = sanitize_key( (string) self::p( $r, 'type', 'occasion' ) );
		$type = isset( HS_Calendar_DB::types()[ $type ] ) ? $type : 'occasion';
		$cid  = (int) self::p( $r, 'class_id', 0 );
		$lvl  = sanitize_key( (string) self::p( $r, 'level' ) );
		$aud  = sanitize_key( (string) self::p( $r, 'audience', 'all' ) );
		$aud  = in_array( $aud, array( 'teachers', 'parents', 'all' ), true ) ? $aud : 'all';
		$data = array( 'title' => $title, 'description' => HS_API_Util::textarea( self::p( $r, 'description' ), 1000 ), 'event_date' => $date, 'end_date' => $end ? $end : '', 'event_type' => $type, 'remind_days' => 1 );
		if ( $cid ) {
			if ( HS_API_Access::WRITE !== HS_API_Access::class_level( $cid, $uid ) ) { return self::forbid(); }
			$data += array( 'audience' => 'class', 'class_id' => $cid, 'school_id' => self::class_school( $cid ) );
		} else {
			if ( ! $ctx['manager'] ) { return self::err( 'فقط مدیر مدرسه رویداد مدرسه را ثبت می‌کند؛ برای کلاس خود، کلاس را انتخاب کنید.', 403, 'hs_forbidden' ); }
			$data += array( 'audience' => $aud, 'class_id' => 0, 'school_id' => $ctx['own_school'], 'level' => isset( self::levels()[ $lvl ] ) ? $lvl : '' );
		}
		$id = HS_Calendar_DB::insert( $data );
		return new WP_REST_Response( array( 'id' => (int) $id ), 201 );
	}

	public static function calendar_delete( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Calendar_DB' ) ) { return self::err( 'تقویم روی سایت فعال نیست.', 503, 'hs_unavailable' ); }
		$uid = get_current_user_id();
		$e   = HS_Calendar_DB::get( (int) $r['id'] );
		if ( ! $e ) { return self::err( 'رویداد پیدا نشد.', 404, 'hs_not_found' ); }
		$row = self::event_row( $e, $uid, self::context( $uid ) );
		if ( ! $row['can_delete'] ) { return self::forbid(); }
		HS_Calendar_DB::delete( $e['id'] );
		return array( 'ok' => true );
	}

	/* ------------------------------ کاربرگ ------------------------------ */

	public static function worksheets( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Worksheet_CPT' ) ) { return self::err( 'کتابخانه‌ی کاربرگ فعال نیست.', 503, 'hs_unavailable' ); }
		$uid = get_current_user_id();
		if ( ! self::teacher_class_ids( $uid ) && ! ( class_exists( 'HS_Roles' ) && HS_Roles::is_teacher_or_admin( $uid ) ) ) { return self::forbid(); }
		$q     = HS_Worksheet_CPT::query( array(
			'grade' => sanitize_text_field( (string) $r->get_param( 'grade' ) ), 'subject' => sanitize_text_field( (string) $r->get_param( 'subject' ) ),
			's' => sanitize_text_field( (string) $r->get_param( 's' ) ), 'paged' => max( 1, (int) $r->get_param( 'page' ) ), 'limit' => 20,
		) );
		$items = array();
		foreach ( (array) $q->posts as $p ) {
			$c       = HS_Worksheet_CPT::get_card_data( $p->ID );
			$items[] = array(
				'id' => (int) $c['id'], 'title' => $c['title'], 'excerpt' => wp_strip_all_tags( (string) $c['excerpt'] ), 'thumb' => (string) $c['thumb'],
				'grade' => (string) $c['grade_label'], 'subject' => (string) $c['subject_label'], 'topic' => (string) $c['topic'], 'difficulty' => (string) $c['diff_label'],
				'pdf' => (string) $c['pdf'], 'interactive' => (string) $c['interactive'], 'url' => get_permalink( $p->ID ),
			);
		}
		$cls = array();
		foreach ( self::teacher_class_ids( $uid ) as $cid ) { $cls[] = array( 'id' => $cid, 'title' => get_the_title( $cid ) ); }
		return array( 'items' => $items, 'page' => max( 1, (int) $r->get_param( 'page' ) ), 'pages' => (int) $q->max_num_pages, 'grades' => HS_Worksheet_CPT::grades(), 'subjects' => HS_Worksheet_CPT::subjects(), 'classes' => $cls );
	}

	public static function worksheet_assign( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Worksheet_CPT' ) || ! class_exists( 'HS_Homework_DB' ) ) { return self::err( 'این بخش فعال نیست.', 503, 'hs_unavailable' ); }
		$cid = (int) self::p( $r, 'class_id' );
		if ( ! self::can_write( $cid ) ) { return self::forbid(); }
		$ws = get_post( (int) $r['id'] );
		if ( ! $ws || HS_Worksheet_CPT::POST_TYPE !== $ws->post_type || 'publish' !== $ws->post_status ) { return self::err( 'کاربرگ پیدا نشد.', 404, 'hs_not_found' ); }
		$c    = HS_Worksheet_CPT::get_card_data( $ws->ID );
		$note = HS_API_Util::textarea( self::p( $r, 'note' ), 1000 );
		$desc = array_filter( array( $note, $c['topic'] ? 'موضوع: ' . $c['topic'] : '' ) );
		$att  = array();
		if ( $c['pdf'] ) { $att[] = array( 'url' => $c['pdf'], 'name' => 'کاربرگ (PDF)', 'type' => 'pdf' ); }
		if ( $c['interactive'] ) { $att[] = array( 'url' => $c['interactive'], 'name' => 'نسخه‌ی آنلاین', 'type' => 'link' ); }
		if ( ! $att ) { $att[] = array( 'url' => get_permalink( $ws->ID ), 'name' => 'کاربرگ', 'type' => 'link' ); }
		$due = HS_API_Util::date( (string) self::p( $r, 'due_date' ) );
		$id  = HS_Homework_DB::create( $cid, $ws->post_title, implode( "\n", $desc ), (string) $c['subject_label'], $due ? $due : null, $att );
		update_option( 'hs_hw_ws_' . $id, $ws->ID, false );
		do_action( 'hs_api_homework_created', (int) $id, $cid, get_current_user_id() );
		return new WP_REST_Response( array( 'id' => (int) $id, 'message' => 'کاربرگ به‌عنوان تکلیف برای کلاس ثبت شد.' ), 201 );
	}

	/* ------------------------------ بارگذاری پیوست ------------------------------ */

	public static function upload( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		if ( ! self::teacher_class_ids( $uid ) ) { return self::forbid(); }
		$lim = HS_API_Util::limited( 'upl_' . $uid, 60, 'تعداد بارگذاری‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.' );
		if ( $lim ) { return $lim; }
		$files = $r->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! $file || ! empty( $file['error'] ) ) { return self::err( 'فایلی دریافت نشد.' ); }
		if ( (int) $file['size'] > 15 * MB_IN_BYTES ) { return self::err( 'حجم فایل باید کمتر از ۱۵ مگابایت باشد.' ); }
		$mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
			'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'video/mp4',
		);
		$chk = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( ! $chk['ext'] || ! $chk['type'] ) { return self::err( 'این نوع فایل پذیرفته نیست (عکس، PDF، Word، PowerPoint، Excel، صدا یا ویدیو).' ); }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$up = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
		if ( isset( $up['error'] ) ) { return self::err( 'بارگذاری ناموفق بود: ' . $up['error'] ); }
		HS_API_Util::hit( 'upl_' . $uid, HOUR_IN_SECONDS );
		$type = 0 === strpos( $chk['type'], 'image/' ) ? 'image' : ( 'pdf' === $chk['ext'] ? 'pdf' : ( 0 === strpos( $chk['type'], 'audio/' ) ? 'audio' : ( 0 === strpos( $chk['type'], 'video/' ) ? 'video' : 'file' ) ) );
		return new WP_REST_Response( array( 'url' => esc_url_raw( $up['url'] ), 'name' => sanitize_file_name( $file['name'] ), 'type' => $type ), 201 );
	}

	/* ------------------------------ پیام ------------------------------ */

	/** برچسب طرف گفت‌وگو برای $viewer: «معلم کلاس سوم الف»، «والد سارا رضایی»، «مدیر مدرسه» */
	public static function partner_label( $viewer, $other ) {
		$viewer = (int) $viewer;
		$other  = (int) $other;
		// طرف مقابل معلم کلاس فرزند من (یا کلاس خودم اگر دانش‌آموزم)
		$mine = HS_API_Access::children_ids( $viewer );
		if ( HS_API_Access::is_student( $viewer ) ) { $mine[] = $viewer; }
		foreach ( $mine as $s ) {
			foreach ( HS_API_Access::student_classes( $s ) as $cid ) {
				if ( (int) get_post_meta( $cid, '_hs_teacher_id', true ) === $other ) { return 'معلم ' . get_the_title( $cid ); }
			}
		}
		// طرف مقابل والد دانش‌آموزی در کلاس من
		$kids = HS_API_Access::children_ids( $other );
		if ( $kids ) {
			$tc = self::teacher_class_ids( $viewer );
			foreach ( $kids as $s ) {
				foreach ( HS_API_Access::student_classes( $s ) as $cid ) {
					if ( in_array( (int) $cid, $tc, true ) ) {
						$u = get_userdata( $s );
						return 'والد ' . ( $u ? $u->display_name : '' );
					}
				}
			}
			$u = get_userdata( $kids[0] );
			return 'والد ' . ( $u ? $u->display_name : '' );
		}
		if ( HS_API_Access::is_student( $other ) ) {
			$c = HS_API_Access::student_classes( $other );
			return 'دانش‌آموز' . ( $c ? ' ' . get_the_title( $c[0] ) : '' );
		}
		$sid = HS_API_Access::school_id( $other );
		if ( $sid && class_exists( 'HS_SO_Store' ) && HS_SO_Store::can_supervise( $sid, $other ) ) { return 'مدیر مدرسه'; }
		if ( self::teacher_class_ids( $other ) ) { return 'معلم'; }
		return '';
	}

	/**
	 * حذف پیام: فرستنده، معلم کلاس، مدیر مدرسه، مدیر سایت، نماینده‌ی (دستیار) والدین کلاس
	 */
	public static function can_delete_message( $uid, $m ) {
		$uid = (int) $uid;
		if ( (int) $m['sender_id'] === $uid || user_can( $uid, 'manage_options' ) ) { return true; }
		$classes = array();
		if ( (int) $m['class_id'] ) { $classes[] = (int) $m['class_id']; }
		if ( (int) $m['student_id'] ) { $classes = array_merge( $classes, HS_API_Access::student_classes( (int) $m['student_id'] ) ); }
		foreach ( array( (int) $m['sender_id'], (int) $m['recipient_id'] ) as $p ) {
			foreach ( HS_API_Access::children_ids( $p ) as $s ) { $classes = array_merge( $classes, HS_API_Access::student_classes( $s ) ); }
		}
		foreach ( array_unique( array_map( 'intval', $classes ) ) as $cid ) {
			// فقط پیامی که یک طرفش به این کلاس ربط دارد
			$level = HS_API_Access::class_level( $cid, $uid );
			if ( in_array( $level, array( HS_API_Access::WRITE, HS_API_Access::SUPER ), true ) ) { return true; }
			if ( class_exists( 'HSPP_Group' ) && method_exists( 'HSPP_Group', 'is_assistant' ) && HSPP_Group::is_assistant( $cid, $uid ) ) { return true; }
		}
		return false;
	}

	public static function message_delete( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Comms_DB' ) || ! method_exists( 'HS_Comms_DB', 'delete_message' ) ) { return self::err( 'افزونه‌ی مدیریت کلاس ۱.۷.۴ یا بالاتر لازم است.', 503, 'hs_unavailable' ); }
		$m = HS_Comms_DB::get_message( (int) $r['id'] );
		if ( ! $m ) { return self::err( 'پیام پیدا نشد.', 404, 'hs_not_found' ); }
		if ( ! self::can_delete_message( get_current_user_id(), $m ) ) { return self::forbid(); }
		HS_Comms_DB::delete_message( (int) $m['id'] );
		return array( 'ok' => true );
	}

	/* ------------------------------ والدین ------------------------------ */

	private static function child_ok( $sid ) {
		return HS_API_Access::is_parent_of( get_current_user_id(), (int) $sid );
	}

	public static function child_progress( WP_REST_Request $r ) {
		$sid = (int) $r['id'];
		if ( ! self::child_ok( $sid ) ) { return self::forbid(); }
		if ( ! class_exists( 'HSPP_Progress' ) ) { return self::err( 'پنل والدین روی سایت فعال نیست.', 503, 'hs_unavailable' ); }
		$y   = (int) $r->get_param( 'y' );
		$m   = (int) $r->get_param( 'm' );
		if ( $y < 2000 || $m < 1 || $m > 12 ) {
			$y = (int) current_time( 'Y' );
			$m = (int) current_time( 'n' );
		}
		$pid = get_current_user_id();
		$out = array();
		foreach ( HS_API_Access::student_classes( $sid ) as $cid ) {
			$d     = HSPP_Progress::data( $pid, $sid, (int) $cid, $y, $m );
			$out[] = array(
				'class_id' => (int) $cid, 'class' => get_the_title( $cid ),
				'attendance' => $d['att'], 'points_month' => $d['points'], 'points_total' => $d['total'] ?? null,
				'assessments' => $d['assess'], 'homework' => $d['hw'] ? array( 'done' => (int) $d['hw'][0], 'total' => (int) $d['hw'][1] ) : null,
				'practice' => $d['practice'],
			);
		}
		return array( 'year' => $y, 'month' => $m, 'classes' => $out );
	}

	public static function child_calendar( WP_REST_Request $r ) {
		$sid = (int) $r['id'];
		if ( ! self::child_ok( $sid ) ) { return self::forbid(); }
		list( $from, $to ) = self::range( $r );
		return array( 'from' => $from, 'to' => $to, 'events' => self::child_events( get_current_user_id(), $sid, $from, $to ) );
	}

	/**
	 * تقویم فرزند: رویدادهای مدرسه/کلاس (با در نظر گرفتن مقطع) + موعد تکلیف، ارزیابی و جلسه با معلم از پنل والدین
	 */
	public static function child_events( $pid, $sid, $from, $to ) {
		$events = array();
		if ( HS_API_Access::parent_can( $pid, $sid, 'calendar' ) ) {
			foreach ( self::events_for( $pid, $from, $to, $sid ) as $e ) {
				$events[] = array( 'date' => $e['date'], 'icon' => $e['holiday'] ? '🔴' : ( 'academic' === $e['type'] ? '🎓' : '📌' ), 'title' => $e['title'], 'sub' => trim( $e['type_label'] . ( $e['class'] ? ' · ' . $e['class'] : '' ) ), 'kind' => 'event' );
			}
		}
		if ( class_exists( 'HSPP_Calendar' ) ) {
			foreach ( HSPP_Calendar::events( $pid, $sid, $from, $to ) as $day => $list ) {
				foreach ( $list as $e ) {
					if ( 'event' === $e[3] ) { continue; } // رویدادها را خودمان (با مقطع) آوردیم
					$events[] = array( 'date' => $day, 'icon' => $e[0], 'title' => $e[1], 'sub' => (string) $e[2], 'kind' => $e[3] );
				}
			}
		}
		usort( $events, function ( $x, $y ) { return strcmp( $x['date'], $y['date'] ); } );
		return $events;
	}
}
