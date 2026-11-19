<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * نسخه‌ی ۲.۴: کارهای سایت در اپ
 * اطلاعیه (ویرایش/حذف)، درخواست‌های اتصال والد، اتصال با کد کلاس، اکسل، پیام گروهی، عکس،
 * پورتفولیو و دیوار کلاس، پروفایل و کادر مدرسه، اتصال معلم به مدرسه، اعلان‌ها، حذف حساب.
 */
class HS_API_Routes_More {

	public static function register() {
		$ns = HS_API_NS;
		$a  = array( 'HS_API_Routes', 'authed' );
		$r  = function ( $route, $method, $cb, $perm = null ) use ( $ns, $a ) {
			register_rest_route( $ns, $route, array( 'methods' => $method, 'callback' => array( __CLASS__, $cb ), 'permission_callback' => $perm ? $perm : $a ) );
		};
		// اطلاعیه
		$r( '/announcements/(?P<id>\d+)', 'POST', 'ann_update' );
		$r( '/announcements/(?P<id>\d+)', 'DELETE', 'ann_delete' );
		// درخواست‌های اتصال (معلم)
		$r( '/requests', 'GET', 'requests' );
		$r( '/requests/(?P<id>\d+)', 'POST', 'request_answer' );
		// والد: کد کلاس ← انتخاب فرزند
		$r( '/join/students', 'POST', 'join_students' );
		$r( '/children/join-class', 'POST', 'join_class' );
		// معلم
		$r( '/classes/(?P<id>\d+)/students/import', 'POST', 'import' );
		$r( '/classes/(?P<id>\d+)/broadcast', 'POST', 'broadcast' );
		$r( '/classes/(?P<id>\d+)/students/(?P<sid>\d+)/avatar', 'POST', 'avatar_by_teacher' );
		$r( '/classes/(?P<id>\d+)/wall', 'GET', 'wall' );
		$r( '/classes/(?P<id>\d+)/portfolio', 'GET', 'class_portfolio' );
		// عکس و پورتفولیو
		$r( '/children/(?P<id>\d+)/avatar', 'POST', 'avatar_by_parent' );
		$r( '/me/avatar', 'POST', 'avatar_self' );
		$r( '/portfolio/student/(?P<id>\d+)', 'GET', 'portfolio' );
		$r( '/portfolio/student/(?P<id>\d+)', 'POST', 'portfolio_add' );
		$r( '/portfolio/student/(?P<id>\d+)/about', 'POST', 'portfolio_about' );
		$r( '/portfolio/(?P<id>\d+)/wall', 'POST', 'portfolio_wall' );
		$r( '/portfolio/(?P<id>\d+)/cheer', 'POST', 'portfolio_cheer' );
		$r( '/portfolio/(?P<id>\d+)', 'DELETE', 'portfolio_delete' );
		// مدرسه
		$r( '/school/profile', 'GET', 'school_profile' );
		$r( '/school/profile', 'POST', 'school_profile_save' );
		$r( '/school/staff', 'POST', 'staff_add' );
		$r( '/school/claims', 'GET', 'claims' );
		$r( '/school/claims/(?P<id>\d+)', 'POST', 'claim' );
		$r( '/me/school', 'POST', 'teacher_school' );
		// اعلان‌ها و حساب
		$r( '/notifications', 'GET', 'notifications' );
		$r( '/notifications/read', 'POST', 'notifications_read' );
		$r( '/me/delete', 'POST', 'delete_account' );
	}

	private static function p( WP_REST_Request $r, $k, $d = '' ) { return HS_API_Util::p( $r, $k, $d ); }
	private static function err( $m, $s = 400, $c = 'hs_error' ) { return HS_API_Util::err( $m, $s, $c ); }
	private static function forbid() { return self::err( 'به این بخش دسترسی ندارید.', 403, 'hs_forbidden' ); }
	private static function level( $cid ) { return HS_API_Access::class_level( (int) $cid, get_current_user_id() ); }

	/** مدیرِ تأییدشده‌ی مدرسه‌ای که این کلاس زیر نظرش است */
	private static function school_manager_of( $cid, $uid ) {
		if ( ! class_exists( 'HS_SO_Store' ) ) { return false; }
		$sid = (int) HS_SO_Store::manager_school_id( $uid );
		return $sid && HS_SO_Store::is_manager( $sid, $uid ) && in_array( (int) $cid, HS_API_Access::supervised_class_ids( $uid ), true );
	}

	/* ------------------------------ اطلاعیه ------------------------------ */

	private static function ann( $id ) {
		global $wpdb;
		return class_exists( 'HS_Comms_DB' ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HS_Comms_DB::announcements_table() . ' WHERE id = %d', $id ), ARRAY_A ) : null; // phpcs:ignore
	}

	/** نویسنده، معلم همان کلاس یا مدیر مدرسه‌ی آن */
	public static function can_edit_ann( $a, $uid ) {
		return $a && ( (int) $a['author_id'] === $uid || HS_Roles::can_manage_class( (int) $a['class_id'], $uid ) || self::school_manager_of( (int) $a['class_id'], $uid ) );
	}

	public static function ann_update( WP_REST_Request $r ) {
		global $wpdb;
		$a = self::ann( (int) $r['id'] );
		if ( ! self::can_edit_ann( $a, get_current_user_id() ) ) { return self::forbid(); }
		$title = HS_API_Util::text( self::p( $r, 'title', $a['title'] ), 190 );
		$body  = HS_API_Util::textarea( self::p( $r, 'body', wp_strip_all_tags( $a['body'] ) ) );
		if ( '' === $title || '' === trim( $body ) ) { return self::err( 'عنوان و متن لازم است.' ); }
		$f = array( 'title' => $title, 'body' => wp_kses_post( $body ), 'is_pinned' => rest_sanitize_boolean( self::p( $r, 'pinned', (bool) $a['is_pinned'] ) ) ? 1 : 0 );
		if ( method_exists( 'HS_Comms_DB', 'ann_state' ) ) {
			$p = $r->get_json_params() ? $r->get_json_params() : $r->get_body_params();
			foreach ( array( 'show_from', 'show_until' ) as $k ) {
				if ( is_array( $p ) && array_key_exists( $k, $p ) ) { $f[ $k ] = HS_Comms_DB::dt( (string) $p[ $k ], 'show_until' === $k ); }
			}
			if ( ! empty( $f['show_from'] ) && ! empty( $f['show_until'] ) && $f['show_until'] < $f['show_from'] ) { return self::err( 'تاریخ پایان نمایش قبل از شروع است.' ); }
		}
		$wpdb->update( HS_Comms_DB::announcements_table(), $f, array( 'id' => (int) $a['id'] ) );
		return array( 'ok' => true, 'message' => 'اطلاعیه ویرایش شد.' );
	}

	public static function ann_delete( WP_REST_Request $r ) {
		$a = self::ann( (int) $r['id'] );
		if ( ! self::can_edit_ann( $a, get_current_user_id() ) ) { return self::forbid(); }
		HS_Comms_DB::delete_announcement( (int) $a['id'] );
		return array( 'ok' => true, 'message' => 'اطلاعیه حذف شد.' );
	}

	/* ------------------------------ درخواست‌های اتصال ------------------------------ */

	public static function requests() {
		if ( ! class_exists( 'HSUM_Requests' ) ) { return array(); }
		$out = array();
		foreach ( (array) HSUM_Requests::inbox( get_current_user_id() ) as $q ) {
			$p    = get_userdata( (int) $q['parent_id'] );
			$cid  = (int) $q['class_id'];
			$cand = array();
			$cids = $cid ? array( $cid ) : array_map( function ( $c ) { return (int) $c->ID; }, HSUM_Invites::manageable_classes() );
			foreach ( $cids as $c ) {
				if ( HS_API_Access::WRITE !== self::level( $c ) ) { continue; }
				foreach ( HS_Class_Students::get_students( $c ) as $u ) {
					$cand[] = array( 'id' => $u->ID, 'name' => $u->display_name, 'class' => get_the_title( $c ) );
				}
			}
			$out[] = array(
				'id' => (int) $q['id'], 'parent' => $p ? $p->display_name : '', 'parent_mobile' => $p ? HSUM_Phone::user_mobile( $p->ID ) : '',
				'child_name' => (string) $q['child_name'], 'class' => $cid ? get_the_title( $cid ) : '', 'message' => (string) $q['message'],
				'created_at' => $q['created_at'], 'student_id' => (int) ( $q['student_id'] ?? 0 ), 'candidates' => $cand,
			);
		}
		return $out;
	}

	public static function request_answer( WP_REST_Request $r ) {
		if ( ! class_exists( 'HSUM_Requests' ) ) { return self::err( 'این بخش فعال نیست.', 503 ); }
		$id  = (int) $r['id'];
		$res = rest_sanitize_boolean( self::p( $r, 'approve', false ) )
			? HSUM_Requests::approve( $id, (int) self::p( $r, 'student_id', 0 ) )
			: HSUM_Requests::reject( $id, HS_API_Util::text( self::p( $r, 'reason' ), 190 ) );
		return is_wp_error( $res ) ? self::err( $res->get_error_message(), 400 ) : array( 'ok' => true );
	}

	/* ------------------------------ کد کلاس ← انتخاب فرزند ------------------------------ */

	/** معلم پذیرش عضو جدید با کد کلاس را بسته؟ (مدیریت کلاس ۱.۷.۷+) */
	public static function join_closed( $cid ) {
		return class_exists( 'HS_Class_Students' ) && method_exists( 'HS_Class_Students', 'join_closed' ) && HS_Class_Students::join_closed( (int) $cid );
	}

	private static function invite_class( $raw ) {
		if ( ! class_exists( 'HSUM_Invites' ) ) { return 0; }
		$inv = HSUM_Invites::by_code( HS_API_Util::extract_code( (string) $raw ) );
		return $inv ? (int) $inv['class_id'] : 0;
	}

	private static function short_name( WP_User $u ) {
		$f = trim( (string) $u->first_name );
		$l = trim( (string) $u->last_name );
		if ( $f ) { return $f . ( $l ? ' ' . mb_substr( $l, 0, 1 ) . '.' : '' ); }
		$p = preg_split( '/\s+/u', trim( $u->display_name ) );
		return $p[0] . ( isset( $p[1] ) ? ' ' . mb_substr( $p[1], 0, 1 ) . '.' : '' );
	}

	/** کد کلاس ← فهرست نام‌ها (نام کوچک + حرف اول فامیل) برای انتخاب فرزند */
	public static function join_students( WP_REST_Request $r ) {
		$k   = 'joins_' . get_current_user_id();
		$lim = HS_API_Util::limited( $k, 10, 'تلاش زیاد بود. ۱۵ دقیقه بعد امتحان کنید.' );
		if ( $lim ) { return $lim; }
		$cid = self::invite_class( self::p( $r, 'code' ) );
		if ( $cid && self::join_closed( $cid ) ) { return self::err( HS_Class_Students::join_closed_message(), 403, 'hs_join_closed' ); }
		if ( ! $cid ) {
			HS_API_Util::hit( $k, 15 * MINUTE_IN_SECONDS );
			return self::err( 'کد کلاس معتبر نیست. دوباره از معلم بگیرید.', 404, 'hs_bad_code' );
		}
		$list = array();
		foreach ( HS_Class_Students::get_students( $cid ) as $u ) {
			$list[] = array( 'id' => $u->ID, 'name' => self::short_name( $u ), 'avatar' => HS_API_Util::avatar( $u->ID, 96 ), 'mine' => HS_API_Access::is_parent_of( get_current_user_id(), $u->ID ) );
		}
		$t = get_userdata( (int) get_post_meta( $cid, '_hs_teacher_id', true ) );
		return array( 'class' => array( 'id' => $cid, 'title' => get_the_title( $cid ), 'teacher' => $t ? $t->display_name : '' ), 'students' => $list );
	}

	/** شماره‌ی والدِ ثبت‌شده در پرونده‌ی دانش‌آموز (یا پیشنهاد اتصال) با شماره‌ی این حساب یکی است؟ */
	public static function phone_matches( $uid, $sid ) {
		$m = class_exists( 'HSUM_Phone' ) ? HSUM_Phone::user_mobile( $uid ) : (string) get_user_meta( $uid, '_hs_mobile', true );
		if ( ! $m ) { return false; }
		$norm = function ( $s ) { return class_exists( 'HSUM_Phone' ) ? HSUM_Phone::norm( $s ) : preg_replace( '/\D/', '', (string) $s ); };
		foreach ( array( '_hs_parent_phone', '_hs_parent2_phone' ) as $k ) {
			if ( $norm( get_user_meta( $sid, $k, true ) ) === $m ) { return true; }
		}
		if ( class_exists( 'HSUM_Links' ) ) {
			foreach ( HSUM_Links::for_student( $sid ) as $l ) {
				if ( $l['parent_mobile'] === $m ) { return true; }
			}
		}
		return false;
	}

	public static function join_class( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		$cid = self::invite_class( self::p( $r, 'code' ) );
		if ( $cid && self::join_closed( $cid ) ) { return self::err( HS_Class_Students::join_closed_message(), 403, 'hs_join_closed' ); }
		$sid = (int) self::p( $r, 'student_id' );
		if ( ! $cid || ! in_array( $sid, HS_API_Access::class_members( $cid ), true ) ) {
			return self::err( 'کد کلاس یا دانش‌آموز معتبر نیست.', 404, 'hs_bad_code' );
		}
		if ( HS_API_Access::is_parent_of( $uid, $sid ) ) {
			return array( 'ok' => true, 'status' => 'linked', 'message' => 'این فرزند قبلاً به حساب شما وصل است.' );
		}
		$u = get_userdata( $sid );
		// شماره‌ی شما را معلم برای همین دانش‌آموز ثبت کرده ← اتصال فوری
		if ( self::phone_matches( $uid, $sid ) ) {
			$res = class_exists( 'HSUM_Links' ) ? HSUM_Links::link_direct( $sid, $uid, $cid, 'class_code' ) : HS_Parent_Link::link( $uid, $sid );
			if ( is_wp_error( $res ) ) { return self::err( $res->get_error_message() ); }
			return array( 'ok' => true, 'status' => 'linked', 'message' => 'به حساب ' . $u->display_name . ' وصل شدید.' );
		}
		if ( ! class_exists( 'HSUM_Requests' ) ) { return self::err( 'این بخش فعال نیست.', 503 ); }
		$res = HSUM_Requests::create( $uid, array( 'class_id' => $cid, 'child_name' => $u->display_name, 'message' => 'از اپ با کد کلاس' ) );
		if ( is_wp_error( $res ) ) { return self::err( $res->get_error_message() ); }
		HS_API_Notify::add( array( (int) get_post_meta( $cid, '_hs_teacher_id', true ) ), 'request', 'درخواست اتصال والد', wp_get_current_user()->display_name . ' می‌خواهد به ' . $u->display_name . ' وصل شود.', array( 'class' => $cid ) );
		return array( 'ok' => true, 'status' => 'request', 'message' => 'درخواست شما برای معلم فرستاده شد. بعد از تأیید معلم، اتصال برقرار می‌شود.' );
	}

	/** پس از ورود با شماره: پیشنهادهایی که معلم با همین شماره ثبت کرده، بدون تأیید فعال می‌شوند */
	public static function auto_link( $uid ) {
		if ( ! class_exists( 'HSUM_Links' ) ) { return 0; }
		$n = 0;
		foreach ( (array) HSUM_Links::for_parent( $uid ) as $l ) {
			if ( 'awaiting' === $l['status'] && ! is_wp_error( HSUM_Links::confirm( (int) $l['id'], $uid ) ) ) { $n++; }
		}
		if ( $n && class_exists( 'HSUM_Onboard' ) && ! get_user_meta( $uid, HSUM_Onboard::DONE, true ) ) {
			// والدِ تعریف‌شده توسط معلم نیاز به فرم ثبت‌نام ندارد
			$u = get_userdata( $uid );
			if ( $u && ! array_intersect( (array) $u->roles, array( 'teacher', 'school_admin', 'student' ) ) ) {
				$kids = HS_API_Access::children_ids( $uid );
				$name = $kids ? trim( (string) get_user_meta( $kids[0], '_hs_parent_name', true ) ) : '';
				if ( $name && preg_match( '/^0?9\d{9}$/', $u->display_name ) ) {
					wp_update_user( array( 'ID' => $uid, 'display_name' => $name, 'nickname' => $name ) );
				}
				if ( ! in_array( 'parent', (array) $u->roles, true ) ) { $u->add_role( 'parent' ); }
				update_user_meta( $uid, HSUM_Onboard::DONE, time() );
			}
		}
		return $n;
	}

	/* ------------------------------ اکسل ------------------------------ */

	public static function import( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		if ( HS_API_Access::WRITE !== self::level( $cid ) ) { return self::forbid(); }
		$rows = self::p( $r, 'rows', array() );
		if ( ! is_array( $rows ) || ! $rows ) { return self::err( 'ردیفی برای ثبت نیست.' ); }
		if ( count( $rows ) > 150 ) { return self::err( 'حداکثر ۱۵۰ دانش‌آموز در هر بار.' ); }
		$out = array( 'added' => 0, 'skipped' => 0, 'errors' => array() );
		foreach ( array_values( $rows ) as $i => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$d = array();
			foreach ( array( 'first_name', 'last_name', 'parent_name', 'parent_phone', 'class_number' ) as $k ) {
				$d[ $k ] = HS_API_Util::text( HS_API_Util::digits( (string) ( $row[ $k ] ?? '' ) ), 100 );
			}
			if ( '' === $d['first_name'] || '' === $d['last_name'] ) {
				$out['skipped']++;
				continue;
			}
			$res = HS_Student_Profile::create_in_class( $cid, $d );
			if ( is_wp_error( $res ) ) {
				$out['errors'][] = array( 'row' => $i + 1, 'name' => $d['first_name'] . ' ' . $d['last_name'], 'message' => $res->get_error_message() );
			} else {
				$out['added']++;
			}
		}
		return $out;
	}

	/* ------------------------------ پیام گروهی ------------------------------ */

	public static function broadcast( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		$uid = get_current_user_id();
		if ( HS_API_Access::WRITE !== self::level( $cid ) ) { return self::forbid(); }
		$body = HS_API_Util::textarea( self::p( $r, 'body' ), 2000 );
		if ( '' === trim( $body ) ) { return self::err( 'متن پیام خالی است.' ); }
		$lim = HS_API_Util::limited( 'bc_' . $cid, 10, 'امروز پیام گروهی زیادی فرستاده‌اید.' );
		if ( $lim ) { return $lim; }
		HS_API_Util::hit( 'bc_' . $cid, DAY_IN_SECONDS );
		$sent = array();
		foreach ( HS_API_Access::class_members( $cid ) as $sid ) {
			foreach ( HS_Parent_Link::get_parents( $sid ) as $pid ) {
				if ( isset( $sent[ $pid ] ) ) { continue; }
				$sent[ $pid ] = HS_Comms_DB::send_message( $uid, (int) $pid, $body, $cid );
				do_action( 'hs_api_message_sent', (int) $sent[ $pid ], $uid, (int) $pid );
			}
		}
		return array( 'ok' => true, 'sent' => count( $sent ) );
	}

	/* ------------------------------ عکس ------------------------------ */

	/** ذخیره‌ی عکس بارگذاری‌شده (JPG/PNG/WebP، حداکثر ۵ مگابایت) با تغییر اندازه */
	public static function save_image( WP_REST_Request $r, $field, $subdir, $max ) {
		$files = $r->get_file_params();
		$file  = $files[ $field ] ?? null;
		if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) { return self::err( 'عکسی انتخاب نشده است.' ); }
		if ( (int) $file['size'] > 5 * MB_IN_BYTES ) { return self::err( 'حجم عکس باید کمتر از ۵ مگابایت باشد.' ); }
		$mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $check['ext'] ) ) { return self::err( 'فقط عکس JPG، PNG یا WebP پذیرفته می‌شود.' ); }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$dirf = function ( $d ) use ( $subdir ) {
			$d['subdir'] = '/' . $subdir;
			$d['path']   = $d['basedir'] . '/' . $subdir;
			$d['url']    = $d['baseurl'] . '/' . $subdir;
			return $d;
		};
		$ext = $check['ext'];
		add_filter( 'upload_dir', $dirf );
		$moved = wp_handle_upload( $file, array(
			'test_form' => false, 'test_type' => true, 'mimes' => $mimes,
			'unique_filename_callback' => function () use ( $ext ) { return strtolower( wp_generate_password( 16, false ) ) . '.' . $ext; },
		) );
		remove_filter( 'upload_dir', $dirf );
		if ( ! is_array( $moved ) || ! empty( $moved['error'] ) ) { return self::err( 'ذخیره‌ی عکس ممکن نشد.' ); }
		$ed = wp_get_image_editor( $moved['file'] );
		if ( is_wp_error( $ed ) ) {
			wp_delete_file( $moved['file'] );
			return self::err( 'این فایل عکس معتبر نیست.' );
		}
		if ( method_exists( $ed, 'maybe_exif_rotate' ) ) { $ed->maybe_exif_rotate(); }
		$ed->resize( $max, $max, 256 === $max );
		$ed->save( $moved['file'] );
		return $moved;
	}

	private static function set_avatar( WP_REST_Request $r, $uid ) {
		$m = self::save_image( $r, 'photo', 'hs-avatars', 256 );
		if ( is_wp_error( $m ) ) { return $m; }
		$old = (string) get_user_meta( $uid, '_hs_avatar_file', true );
		if ( $old && file_exists( $old ) ) { wp_delete_file( $old ); }
		update_user_meta( $uid, '_hs_avatar_url', esc_url_raw( $m['url'] ) );
		update_user_meta( $uid, '_hs_avatar_file', $m['file'] );
		return array( 'ok' => true, 'url' => esc_url_raw( $m['url'] ) );
	}

	public static function avatar_by_teacher( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		$sid = (int) $r['sid'];
		if ( HS_API_Access::WRITE !== self::level( $cid ) || ! in_array( $sid, HS_API_Access::class_members( $cid ), true ) ) { return self::forbid(); }
		return self::set_avatar( $r, $sid );
	}

	public static function avatar_by_parent( WP_REST_Request $r ) {
		if ( ! HS_API_Access::is_parent_of( get_current_user_id(), (int) $r['id'] ) ) { return self::forbid(); }
		return self::set_avatar( $r, (int) $r['id'] );
	}

	public static function avatar_self( WP_REST_Request $r ) {
		return self::set_avatar( $r, get_current_user_id() );
	}

	/* ------------------------------ پورتفولیو ------------------------------ */

	/** خودِ دانش‌آموز یا والدِ متصل (حالت کودک روی گوشی والد) */
	private static function can_post_for( $sid, $uid ) {
		return ( $sid === $uid && HS_API_Access::is_student( $uid ) ) || HS_API_Access::is_parent_of( $uid, $sid );
	}

	private static function can_view_portfolio( $sid, $uid ) {
		if ( self::can_post_for( $sid, $uid ) ) { return true; }
		foreach ( HS_API_Access::student_classes( $sid ) as $c ) {
			if ( in_array( self::level( $c ), array( HS_API_Access::WRITE, HS_API_Access::SUPER ), true ) ) { return true; }
		}
		return false;
	}

	private static function cheers_t() {
		global $wpdb;
		return $wpdb->prefix . 'hs_api_cheers';
	}

	public static function install_cheers() {
		global $wpdb;
		if ( '1' === get_option( 'hs_api_cheers_db' ) ) { return; }
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::cheers_t() . ' (
			item_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (item_id, user_id)
		) ' . $wpdb->get_charset_collate() . ';' );
		update_option( 'hs_api_cheers_db', '1', false );
	}

	/** سنجاق‌شده‌ها اول (مدیریت کلاس ۱.۷.۵+) */
	private static function wall_order() {
		return method_exists( 'HS_Portfolio_DB', 'set_pinned' ) ? 'is_pinned DESC, pinned_at DESC, id DESC' : 'id DESC';
	}

	private static function post_row( $p, $uid ) {
		global $wpdb;
		$n    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::cheers_t() . ' WHERE item_id = %d', $p['id'] ) ); // phpcs:ignore
		$mine = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::cheers_t() . ' WHERE item_id = %d AND user_id = %d', $p['id'], $uid ) ); // phpcs:ignore
		$s    = get_userdata( (int) $p['student_id'] );
		return array(
			'id' => (int) $p['id'], 'student_id' => (int) $p['student_id'], 'student' => $s ? self::short_name( $s ) : '', 'avatar' => HS_API_Util::avatar( (int) $p['student_id'], 96 ),
			'class_id' => (int) $p['class_id'], 'title' => (string) $p['title'], 'text' => (string) $p['description'], 'type' => (string) $p['item_type'],
			'image' => (string) $p['media_url'], 'on_wall' => 'approved' === $p['status'], 'cheers' => $n, 'cheered' => $mine, 'at' => $p['created_at'],
			'pinned' => ! empty( $p['is_pinned'] ),
		);
	}

	public static function portfolio( WP_REST_Request $r ) {
		global $wpdb;
		$uid = get_current_user_id();
		$sid = (int) $r['id'];
		if ( ! self::can_view_portfolio( $sid, $uid ) || ! class_exists( 'HS_Portfolio_DB' ) ) { return self::forbid(); }
		$u     = get_userdata( $sid );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HS_Portfolio_DB::table() . " WHERE student_id = %d AND status <> 'rejected' ORDER BY id DESC LIMIT 60", $sid ), ARRAY_A ); // phpcs:ignore
		return array(
			'student'  => array( 'id' => $sid, 'name' => $u->display_name, 'avatar' => HS_API_Util::avatar( $sid ), 'about' => (string) get_user_meta( $sid, '_hs_about', true ) ),
			'can_post' => self::can_post_for( $sid, $uid ),
			'today'    => self::posts_today( $sid ),
			'limit'    => 2,
			'posts'    => array_map( function ( $p ) use ( $uid ) { return self::post_row( $p, $uid ); }, (array) $rows ),
		);
	}

	private static function posts_today( $sid ) {
		global $wpdb;
		list( $from, $to ) = HS_API_Routes_Dashboard::db_day_range( HS_API_Util::today() );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HS_Portfolio_DB::table() . ' WHERE student_id = %d AND created_at >= %s AND created_at < %s', $sid, $from, $to ) ); // phpcs:ignore
	}

	public static function portfolio_add( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		$sid = (int) $r['id'];
		if ( ! self::can_post_for( $sid, $uid ) || ! class_exists( 'HS_Portfolio_DB' ) ) { return self::forbid(); }
		$cls = HS_API_Access::student_classes( $sid );
		if ( ! $cls ) { return self::err( 'دانش‌آموز هنوز در کلاسی نیست.' ); }
		if ( self::posts_today( $sid ) >= 2 ) { return self::err( 'امروز ۲ پست گذاشته‌ای؛ فردا دوباره 🌟', 429, 'hs_post_limit' ); }
		$title = HS_API_Util::text( self::p( $r, 'title' ), 120 );
		$text  = HS_API_Util::textarea( self::p( $r, 'text' ), 1000 );
		$img   = '';
		if ( $r->get_file_params() ) {
			$m = self::save_image( $r, 'photo', 'hs-portfolio', 1280 );
			if ( is_wp_error( $m ) ) { return $m; }
			$img = $m['url'];
		}
		if ( '' === $title && '' === $text && '' === $img ) { return self::err( 'عکس یا چند کلمه درباره‌ی کارت بنویس.' ); }
		$type = sanitize_key( (string) self::p( $r, 'type', 'photo' ) );
		$id   = HS_Portfolio_DB::add( array( 'class_id' => $cls[0], 'student_id' => $sid, 'title' => $title ? $title : 'کار من', 'description' => $text, 'item_type' => isset( HS_Portfolio_DB::types()[ $type ] ) ? $type : 'other', 'media_url' => $img, 'status' => 'pending' ) );
		$u    = get_userdata( $sid );
		HS_API_Notify::add( array( (int) get_post_meta( $cls[0], '_hs_teacher_id', true ) ), 'portfolio', 'پست تازه در پورتفولیو', ( $u ? $u->display_name : '' ) . ': ' . ( $title ? $title : 'کار من' ), array( 'child' => $sid, 'class' => $cls[0] ) );
		return new WP_REST_Response( array( 'ok' => true, 'id' => $id ), 201 );
	}

	public static function portfolio_about( WP_REST_Request $r ) {
		$sid = (int) $r['id'];
		if ( ! self::can_post_for( $sid, get_current_user_id() ) ) { return self::forbid(); }
		update_user_meta( $sid, '_hs_about', HS_API_Util::textarea( self::p( $r, 'about' ), 400 ) );
		return array( 'ok' => true );
	}

	private static function item( $id ) {
		return class_exists( 'HS_Portfolio_DB' ) ? HS_Portfolio_DB::get( (int) $id ) : null;
	}

	/** معلم کلاس: گذاشتن/برداشتن روی دیوار کلاس */
	public static function portfolio_wall( WP_REST_Request $r ) {
		$p = self::item( $r['id'] );
		if ( ! $p || HS_API_Access::WRITE !== self::level( (int) $p['class_id'] ) ) { return self::forbid(); }
		$on = rest_sanitize_boolean( self::p( $r, 'on', true ) );
		HS_Portfolio_DB::update_status( (int) $p['id'], $on ? 'approved' : 'pending' );
		return array( 'ok' => true, 'on_wall' => $on );
	}

	public static function portfolio_cheer( WP_REST_Request $r ) {
		global $wpdb;
		$uid = get_current_user_id();
		$p   = self::item( $r['id'] );
		if ( ! $p ) { return self::err( 'پست پیدا نشد.', 404 ); }
		$visible = self::can_view_portfolio( (int) $p['student_id'], $uid ) || ( 'approved' === $p['status'] && self::level( (int) $p['class_id'] ) );
		if ( ! $visible ) { return self::forbid(); }
		$has = $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::cheers_t() . ' WHERE item_id = %d AND user_id = %d', $p['id'], $uid ) ); // phpcs:ignore
		if ( $has ) {
			$wpdb->delete( self::cheers_t(), array( 'item_id' => (int) $p['id'], 'user_id' => $uid ) );
		} else {
			$wpdb->insert( self::cheers_t(), array( 'item_id' => (int) $p['id'], 'user_id' => $uid, 'created_at' => current_time( 'mysql' ) ) );
		}
		return array( 'ok' => true, 'cheered' => ! $has );
	}

	/** معلم کلاس، یا صاحب پست (دانش‌آموز/والد) */
	public static function portfolio_delete( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		$p   = self::item( $r['id'] );
		if ( ! $p ) { return self::err( 'پست پیدا نشد.', 404 ); }
		if ( HS_API_Access::WRITE !== self::level( (int) $p['class_id'] ) && ! self::can_post_for( (int) $p['student_id'], $uid ) ) { return self::forbid(); }
		HS_Portfolio_DB::delete( (int) $p['id'] );
		return array( 'ok' => true );
	}

	/** دیوار کلاس: پست‌هایی که معلم تأیید کرده؛ برای همه‌ی اعضای کلاس */
	public static function wall( WP_REST_Request $r ) {
		global $wpdb;
		$cid = (int) $r['id'];
		if ( ! self::level( $cid ) || ! class_exists( 'HS_Portfolio_DB' ) ) { return self::forbid(); }
		$uid  = get_current_user_id();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HS_Portfolio_DB::table() .   " WHERE class_id = %d AND status = 'approved' ORDER BY " . self::wall_order() . ' LIMIT 60', $cid ), ARRAY_A ); // phpcs:ignore
		return array_map( function ( $p ) use ( $uid ) { return self::post_row( $p, $uid ); }, (array) $rows );
	}

	/** معلم/ناظر: همه‌ی پست‌های کلاس (در انتظار و روی دیوار) */
	public static function class_portfolio( WP_REST_Request $r ) {
		global $wpdb;
		$cid = (int) $r['id'];
		if ( ! in_array( self::level( $cid ), array( HS_API_Access::WRITE, HS_API_Access::SUPER ), true ) || ! class_exists( 'HS_Portfolio_DB' ) ) { return self::forbid(); }
		$uid  = get_current_user_id();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HS_Portfolio_DB::table() .   " WHERE class_id = %d AND status <> 'rejected' ORDER BY " . self::wall_order() . ' LIMIT 100', $cid ), ARRAY_A ); // phpcs:ignore
		return array_map( function ( $p ) use ( $uid ) { return self::post_row( $p, $uid ); }, (array) $rows );
	}

	/* ------------------------------ مدرسه ------------------------------ */

	/** کسی که نقش مدیر مدرسه دارد یا درخواستش در انتظار است */
	private static function is_manager_applicant( $uid ) {
		$u = get_userdata( $uid );
		if ( ! $u ) { return false; }
		if ( in_array( 'school_admin', (array) $u->roles, true ) ) { return true; }
		return class_exists( 'HSUM_Membership' ) && 'school_admin' === ( HSUM_Membership::get( $uid )['role'] ?? '' );
	}

	public static function school_profile() {
		$uid = get_current_user_id();
		if ( ! class_exists( 'HS_SO_Store' ) ) { return self::err( 'بخش مدرسه فعال نیست.', 503 ); }
		$sid = HS_SO_Store::owned_school_id( $uid );
		return array(
			'can_edit'  => self::is_manager_applicant( $uid ),
			'approved'  => HS_SO_Store::manager_approved( $uid ),
			'school'    => $sid ? HS_SO_Store::get_school( $sid ) : null,
			'levels'    => HS_SO_Store::levels(),
			'provinces' => class_exists( 'HS_Util' ) ? array_values( HS_Util::provinces() ) : array(),
			'positions' => class_exists( 'HSUM_Staff' ) ? HSUM_Staff::positions() : array(),
		);
	}

	public static function school_profile_save( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		if ( ! class_exists( 'HS_SO_Store' ) ) { return self::err( 'بخش مدرسه فعال نیست.', 503 ); }
		if ( ! self::is_manager_applicant( $uid ) ) { return self::err( 'فقط مدیر مدرسه می‌تواند اطلاعات مدرسه را ثبت کند.', 403, 'hs_forbidden' ); }
		$code = preg_replace( '/\D/', '', HS_API_Util::digits( (string) self::p( $r, 'code' ) ) );
		if ( ! preg_match( '/^\d{7}$/', $code ) ) { return self::err( 'کد مدرسه معتبر نیست.' ); }
		$own   = HS_SO_Store::owned_school_id( $uid );
		$taken = get_posts( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 1, 'meta_key' => '_hs_school_code', 'meta_value' => $code, 'exclude' => $own ? array( $own ) : array() ) ); // phpcs:ignore
		if ( $taken ) { return self::err( 'این کد مدرسه قبلاً ثبت شده است. اگر مدیر همین مدرسه هستید با پشتیبانی تماس بگیرید.', 409 ); }
		$d   = array();
		foreach ( array( 'name', 'province', 'city', 'address', 'postal', 'phone', 'level' ) as $k ) {
			$d[ $k ] = HS_API_Util::text( HS_API_Util::digits( (string) self::p( $r, $k ) ), 'address' === $k ? 300 : 120 );
		}
		$d['code'] = $code;
		$res       = HS_SO_Store::save_school( $own, $d, $uid );
		if ( is_wp_error( $res ) ) { return self::err( $res->get_error_message() ); }
		update_user_meta( $uid, '_hs_school_name', $d['name'] );
		return array( 'ok' => true, 'school' => HS_SO_Store::get_school( (int) $res ), 'approved' => HS_SO_Store::manager_approved( $uid ) );
	}

	/** مدیر تأییدشده: افزودن معاون/معلم (دعوت با پیامک؛ اگر حساب داشته باشد مستقیم عضو کادر می‌شود) */
	public static function staff_add( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		if ( ! class_exists( 'HSUM_Staff' ) || ! class_exists( 'HS_SO_Store' ) ) { return self::err( 'این بخش فعال نیست.', 503 ); }
		$sid = (int) HS_SO_Store::manager_school_id( $uid );
		if ( ! $sid ) { return self::err( 'فقط مدیرِ تأییدشده‌ی مدرسه می‌تواند کادر اضافه کند.', 403, 'hs_forbidden' ); }
		$new = array(
			'position' => sanitize_key( (string) self::p( $r, 'position', 'teacher' ) ),
			'first'    => HS_API_Util::text( self::p( $r, 'first' ), 60 ),
			'last'     => HS_API_Util::text( self::p( $r, 'last' ), 60 ),
			'mobile'   => HS_API_Util::digits( (string) self::p( $r, 'mobile' ) ),
		);
		$posted = array();
		foreach ( HSUM_Staff::rows( $sid ) as $row ) {
			$posted[] = array_merge( array( 'id' => (int) $row['id'], 'mobile' => (string) $row['mobile'] ), (array) $row['d'] );
		}
		$posted[] = $new;
		$res      = HSUM_Staff::sync( $sid, $posted, $uid );
		if ( ! empty( $res['errors'] ) ) { return self::err( implode( ' ', $res['errors'] ) ); }
		return array( 'ok' => true, 'added' => (int) $res['added'], 'message' => $res['added'] ? 'دعوت‌نامه ساخته شد.' : 'این شماره از قبل در کادر است.' );
	}

	public static function claims() {
		if ( ! class_exists( 'HSUM_Invites' ) ) { return array(); }
		return array_map( function ( $cid ) {
			$t = get_userdata( (int) get_post_meta( $cid, '_hs_teacher_id', true ) );
			return array( 'id' => (int) $cid, 'title' => get_the_title( $cid ), 'teacher' => $t ? $t->display_name : '', 'students' => count( HS_API_Access::class_members( $cid ) ) );
		}, (array) HSUM_Invites::claimable_classes( get_current_user_id() ) );
	}

	public static function claim( WP_REST_Request $r ) {
		if ( ! class_exists( 'HSUM_Invites' ) ) { return self::err( 'این بخش فعال نیست.', 503 ); }
		if ( rest_sanitize_boolean( self::p( $r, 'reject', false ) ) ) {
			if ( ! in_array( (int) $r['id'], array_map( 'intval', (array) HSUM_Invites::claimable_classes( get_current_user_id() ) ), true ) ) { return self::forbid(); }
			delete_post_meta( (int) $r['id'], '_hs_manager_mobile' );
			return array( 'ok' => true );
		}
		$res = HSUM_Invites::claim_class( (int) $r['id'], get_current_user_id() );
		return is_wp_error( $res ) ? self::err( $res->get_error_message() ) : array( 'ok' => true );
	}

	/** معلم: اتصال کلاس‌هایش به مدرسه با کد مدرسه یا موبایل مدیر (پس از تأیید مدیر) */
	public static function teacher_school( WP_REST_Request $r ) {
		$uid  = get_current_user_id();
		$code = preg_replace( '/\D/', '', HS_API_Util::digits( (string) self::p( $r, 'school_code' ) ) );
		$mm   = class_exists( 'HSUM_Phone' ) ? HSUM_Phone::norm( (string) self::p( $r, 'manager_mobile' ) ) : '';
		$lim  = HS_API_Util::limited( 'tsch_' . $uid, 10, 'تلاش زیاد بود؛ کمی بعد امتحان کنید.' );
		if ( $lim ) { return $lim; }
		HS_API_Util::hit( 'tsch_' . $uid, HOUR_IN_SECONDS );
		$manager = 0;
		if ( $code && class_exists( 'HS_SO_Store' ) ) {
			$s = get_posts( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 1, 'meta_key' => '_hs_school_code', 'meta_value' => $code ) ); // phpcs:ignore
			if ( ! $s ) { return self::err( 'مدرسه‌ای با این کد پیدا نشد.', 404 ); }
			$manager = (int) get_post_meta( $s[0], '_hs_manager_id', true );
			$mm      = $manager ? HSUM_Phone::user_mobile( $manager ) : '';
		} elseif ( $mm ) {
			$mu      = HSUM_Phone::user_by_mobile( $mm );
			$manager = $mu ? (int) $mu->ID : 0;
		}
		if ( ! $mm ) { return self::err( 'کد مدرسه یا موبایل مدیر را وارد کنید.' ); }
		update_user_meta( $uid, '_hs_manager_mobile', $mm );
		$n = 0;
		foreach ( get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_hs_teacher_id', 'meta_value' => $uid ) ) as $cid ) { // phpcs:ignore
			if ( ! (int) get_post_meta( $cid, '_hs_school_id', true ) ) {
				update_post_meta( $cid, '_hs_manager_mobile', $mm );
				$n++;
			}
		}
		if ( $manager ) {
			HS_API_Notify::add( array( $manager ), 'claim', 'درخواست اتصال کلاس', wp_get_current_user()->display_name . ' می‌خواهد کلاس‌هایش به مدرسه وصل شود.', array() );
		}
		return array( 'ok' => true, 'classes' => $n, 'message' => $manager ? 'درخواست برای مدیر مدرسه فرستاده شد؛ بعد از تأیید او کلاس‌ها به مدرسه وصل می‌شوند.' : 'ثبت شد؛ وقتی مدیر با این شماره عضو شود، درخواست را می‌بیند.' );
	}

	/* ------------------------------ اعلان‌ها و حساب ------------------------------ */

	public static function notifications() {
		$uid = get_current_user_id();
		return array( 'unread' => HS_API_Notify::unread( $uid ), 'items' => HS_API_Notify::list_for( $uid ) );
	}

	public static function notifications_read( WP_REST_Request $r ) {
		$ids = self::p( $r, 'ids', array() );
		HS_API_Notify::mark_read( get_current_user_id(), is_array( $ids ) ? array_map( 'intval', $ids ) : array() );
		return array( 'ok' => true );
	}

	/** درخواست حذف حساب: اتصال‌ها و نشست‌ها فوراً قطع؛ حذف نهایی توسط مدیر سایت */
	public static function delete_account( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		foreach ( HS_API_Access::children_ids( $uid ) as $sid ) {
			HS_Parent_Link::unlink( $uid, $sid );
		}
		update_user_meta( $uid, '_hs_delete_requested', time() );
		update_user_meta( $uid, '_hs_delete_reason', HS_API_Util::textarea( self::p( $r, 'reason' ), 500 ) );
		if ( class_exists( 'HSUM_Audit' ) ) { HSUM_Audit::log( 'delete_request', $uid, array( 'via' => 'app' ) ); }
		wp_mail( get_option( 'admin_email' ), 'درخواست حذف حساب', 'کاربر شماره‌ی ' . $uid . ' از اپ درخواست حذف حساب داده است. ' . admin_url( 'user-edit.php?user_id=' . $uid ) );
		HS_API_Auth::revoke_all( $uid );
		return array( 'ok' => true, 'message' => 'درخواست حذف ثبت شد؛ از حساب خارج شدید.' );
	}
}
