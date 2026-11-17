<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * مسیرهای اصلی: احراز هویت، حساب، کلاس‌ها (معلم/ناظر/دانش‌آموز/والد)، پیام، مدرسه.
 * مسیرهای والدین در class-routes-parent.php و مجله در class-routes-articles.php.
 */
class HS_API_Routes {

	/* ---------- ثبت مسیرها ---------- */

	public static function register() {
		$ns    = HS_API_NS;
		$open  = '__return_true';
		$authd = array( __CLASS__, 'authed' );

		// احراز هویت
		register_rest_route( $ns, '/auth/otp/request', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'otp_request' ), 'permission_callback' => $open ) );
		register_rest_route( $ns, '/auth/otp/verify', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'otp_verify' ), 'permission_callback' => $open ) );
		register_rest_route( $ns, '/auth/login', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'login' ), 'permission_callback' => $open ) );
		register_rest_route( $ns, '/auth/refresh', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'refresh' ), 'permission_callback' => $open ) );
		register_rest_route( $ns, '/auth/logout', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'logout' ), 'permission_callback' => $authd ) );

		// حساب
		register_rest_route( $ns, '/me', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'me' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/onboarding', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'onboarding_get' ), 'permission_callback' => $authd ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'onboarding_save' ), 'permission_callback' => $authd ),
		) );
		register_rest_route( $ns, '/devices', array(
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'device_add' ), 'permission_callback' => $authd ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'device_remove' ), 'permission_callback' => $authd ),
		) );

		// کلاس‌ها
		register_rest_route( $ns, '/classes', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'classes' ), 'permission_callback' => $authd ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'class_create' ), 'permission_callback' => array( __CLASS__, 'can_create_class' ) ),
		) );
		register_rest_route( $ns, '/classes/options', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'class_options' ), 'permission_callback' => $authd ) );
		$staff = array( HS_API_Access::WRITE, HS_API_Access::SUPER );
		$all   = array( HS_API_Access::WRITE, HS_API_Access::SUPER, HS_API_Access::STUDENT, HS_API_Access::PARENT );
		$w     = array( HS_API_Access::WRITE );
		$cid   = '/classes/(?P<id>\d+)';
		register_rest_route( $ns, $cid, array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'class_one' ), 'permission_callback' => self::class_perm( $all ) ) );
		register_rest_route( $ns, $cid . '/students', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'students' ), 'permission_callback' => self::class_perm( $staff ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'student_create' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/students/(?P<sid>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'student_one' ), 'permission_callback' => self::class_perm( $staff ) ) );
		register_rest_route( $ns, $cid . '/attendance', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'attendance_get' ), 'permission_callback' => self::class_perm( $staff ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'attendance_save' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/attendance/week', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'attendance_week' ), 'permission_callback' => self::class_perm( $staff ) ) );
		register_rest_route( $ns, $cid . '/homework', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'homework_list' ), 'permission_callback' => self::class_perm( $all ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'homework_create' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/announcements', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'ann_list' ), 'permission_callback' => self::class_perm( $all ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'ann_create' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/points', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'points_get' ), 'permission_callback' => self::class_perm( $staff ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'points_award' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, $cid . '/invite', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'invite_get' ), 'permission_callback' => self::class_perm( $w ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'invite_reset' ), 'permission_callback' => self::class_perm( $w ) ),
		) );
		register_rest_route( $ns, '/homework/(?P<id>\d+)/statuses', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'hw_statuses' ), 'permission_callback' => array( __CLASS__, 'hw_perm_read' ) ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'hw_statuses_save' ), 'permission_callback' => array( __CLASS__, 'hw_perm_write' ) ),
		) );

		// پیام‌ها
		register_rest_route( $ns, '/messages', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'inbox' ), 'permission_callback' => $authd ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'send' ), 'permission_callback' => $authd ),
		) );
		register_rest_route( $ns, '/messages/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'thread' ), 'permission_callback' => $authd ) );

		// مدرسه
		register_rest_route( $ns, '/school', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'school' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/school/tasks', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'tasks' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/school/tasks/(?P<id>\d+)/status', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'task_status' ), 'permission_callback' => $authd ) );

		HS_API_Routes_Parent::register();
		HS_API_Routes_Articles::register();
	}

	/* ---------- دسترسی ---------- */

	public static function authed() {
		return is_user_logged_in() ? true : HS_API_Util::err( 'ابتدا وارد شوید.', 401, 'hs_unauthorized' );
	}

	private static function class_perm( array $levels ) {
		return function ( WP_REST_Request $r ) use ( $levels ) {
			if ( ! is_user_logged_in() ) { return HS_API_Util::err( 'ابتدا وارد شوید.', 401, 'hs_unauthorized' ); }
			$lv = HS_API_Access::class_level( (int) $r['id'], get_current_user_id() );
			if ( ! $lv || ! in_array( $lv, $levels, true ) ) { return HS_API_Util::err( 'به این بخش دسترسی ندارید.', 403, 'hs_forbidden' ); }
			$r->set_param( '_level', $lv );
			return true;
		};
	}

	private static function hw_class( $hid ) {
		if ( ! class_exists( 'HS_Homework_DB' ) ) { return 0; }
		$h = HS_Homework_DB::get( (int) $hid );
		return $h ? (int) $h['class_id'] : 0;
	}

	public static function hw_perm_read( WP_REST_Request $r ) {
		if ( ! is_user_logged_in() ) { return HS_API_Util::err( 'ابتدا وارد شوید.', 401, 'hs_unauthorized' ); }
		$lv = HS_API_Access::class_level( self::hw_class( (int) $r['id'] ), get_current_user_id() );
		return in_array( $lv, array( HS_API_Access::WRITE, HS_API_Access::SUPER ), true ) ? true : HS_API_Util::err( 'دسترسی ندارید.', 403, 'hs_forbidden' );
	}

	public static function hw_perm_write( WP_REST_Request $r ) {
		if ( ! is_user_logged_in() ) { return HS_API_Util::err( 'ابتدا وارد شوید.', 401, 'hs_unauthorized' ); }
		return HS_API_Access::WRITE === HS_API_Access::class_level( self::hw_class( (int) $r['id'] ), get_current_user_id() ) ? true : HS_API_Util::err( 'دسترسی ندارید.', 403, 'hs_forbidden' );
	}

	private static function p( WP_REST_Request $r, $k, $d = '' ) { return HS_API_Util::p( $r, $k, $d ); }

	private static function json( $data, $status = 200 ) { return new WP_REST_Response( $data, $status ); }

	/* ---------- احراز هویت ---------- */

	public static function otp_request( WP_REST_Request $r ) {
		return rest_ensure_response( HS_API_OTP::request( (string) self::p( $r, 'mobile' ) ) );
	}

	public static function otp_verify( WP_REST_Request $r ) {
		return rest_ensure_response( HS_API_OTP::verify( (string) self::p( $r, 'mobile' ), (string) self::p( $r, 'code' ), (string) self::p( $r, 'device' ) ) );
	}

	public static function login( WP_REST_Request $r ) {
		return rest_ensure_response( HS_API_Auth::login( (string) self::p( $r, 'username' ), (string) self::p( $r, 'password' ), (string) self::p( $r, 'device' ) ) );
	}

	public static function refresh( WP_REST_Request $r ) {
		return rest_ensure_response( HS_API_Auth::refresh( (string) self::p( $r, 'refresh_token' ), (string) self::p( $r, 'device' ) ) );
	}

	public static function logout( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		$dev = (string) self::p( $r, 'device_token' );
		if ( $dev ) { HS_API_Devices::remove( $uid, $dev ); }
		HS_API_Auth::logout( $uid, (string) self::p( $r, 'refresh_token' ), (bool) rest_sanitize_boolean( self::p( $r, 'all', false ) ) );
		return array( 'ok' => true );
	}

	/* ---------- حساب ---------- */

	public static function me() {
		$u   = wp_get_current_user();
		$out = HS_API_Access::profile( $u );
		$out['unread_messages'] = class_exists( 'HS_Comms_DB' ) ? (int) HS_Comms_DB::count_unread( $u->ID ) : 0;
		return $out;
	}

	public static function onboarding_get() {
		$uid = get_current_user_id();
		if ( ! class_exists( 'HSUM_Onboard' ) ) {
			return HS_API_Util::err( 'تکمیل ثبت‌نام روی سایت فعال نیست.', 503, 'hs_unavailable' );
		}
		$saved = get_user_meta( $uid, HSUM_Onboard::DATA, true );
		$out   = array(
			'needs'     => HS_API_Access::needs_onboarding( $uid ),
			'status'    => HS_API_Access::membership_status( $uid ),
			'view'      => HS_API_Access::view( $uid ),
			'roles'     => array(
				array( 'id' => 'parent', 'title' => 'والدین', 'hint' => 'فرزندم در کلاس است' ),
				array( 'id' => 'teacher', 'title' => 'معلم', 'hint' => 'کلاس دارم یا می‌سازم' ),
				array( 'id' => 'school_admin', 'title' => 'مدیر مدرسه', 'hint' => 'مدرسه را مدیریت می‌کنم (نیاز به تأیید)' ),
			),
			'relations' => array( 'father' => 'پدر', 'mother' => 'مادر', 'parent' => 'سرپرست' ),
			'provinces' => class_exists( 'HS_Util' ) && method_exists( 'HS_Util', 'provinces' ) ? array_values( HS_Util::provinces() ) : array(),
			'school_types' => class_exists( 'HS_SO_Store' ) ? HS_SO_Store::levels() : array(),
			'staff_positions' => class_exists( 'HSUM_Staff' ) && method_exists( 'HSUM_Staff', 'positions' ) ? HSUM_Staff::positions() : array(),
			'data'      => is_array( $saved ) ? $saved : new stdClass(),
		);
		return $out;
	}

	public static function onboarding_save( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		if ( ! class_exists( 'HSUM_Onboard' ) || ! method_exists( 'HSUM_Onboard', 'process' ) ) {
			return HS_API_Util::err( 'افزونه‌ی «کاربران و عضویت» نسخه‌ی ۲.۶.۳ یا بالاتر لازم است.', 503, 'hs_unavailable' );
		}
		$lim = HS_API_Util::limited( 'onb_' . $uid, 20 );
		if ( $lim ) { return $lim; }
		HS_API_Util::hit( 'onb_' . $uid, HOUR_IN_SECONDS );
		$in = $r->get_json_params();
		if ( ! is_array( $in ) || ! $in ) { $in = $r->get_body_params(); }
		$in = is_array( $in ) ? $in : array();
		// فقط فیلدهای شناخته‌شده‌ی فرم
		$allowed = array( 'role', 'first_name', 'last_name', 'relation', 'father_first', 'father_last', 'mother_first', 'mother_last', 'father_mobile', 'mother_mobile',
			'child_first', 'child_last', 'child_city', 'city', 'province', 'school_address', 'home_address', 'home_phone', 'postal_code', 'school_name', 'manager_name',
			'teacher_name', 'teacher_mobile', 'code', 'kids', 'school_name_t', 'subject', 'manager_name_t', 'manager_mobile_t', 'school_address_t', 'school_name_m', 'school_type',
			'school_address_m', 'province_m', 'city_m', 'postal_code_m', 'school_phone', 'school_code', 'personnel_code', 'staff' );
		$in = array_intersect_key( $in, array_flip( $allowed ) );
		foreach ( $in as $k => $v ) {
			if ( in_array( $k, array( 'kids', 'staff' ), true ) ) {
				$in[ $k ] = is_array( $v ) ? array_slice( array_values( array_filter( $v, 'is_array' ) ), 0, 60 ) : array();
			} elseif ( ! is_scalar( $v ) ) {
				unset( $in[ $k ] );
			} else {
				$in[ $k ] = HS_API_Util::digits( (string) $v );
			}
		}
		if ( ! empty( $in['code'] ) ) {
			$in['code'] = HS_API_Util::extract_code( $in['code'] );
		}
		$res = HSUM_Onboard::process( $uid, $in );
		if ( is_wp_error( $res ) ) {
			return HS_API_Util::err( $res->get_error_message(), 400, 'hs_onboarding' );
		}
		$u = get_userdata( $uid );
		return array(
			'ok'      => true,
			'message' => $res['message'],
			'role'    => $res['role'],
			'user'    => HS_API_Access::profile( $u ),
		);
	}

	public static function device_add( WP_REST_Request $r ) {
		$res = HS_API_Devices::register( get_current_user_id(), (string) self::p( $r, 'token' ), sanitize_key( (string) self::p( $r, 'platform', 'android' ) ), sanitize_key( (string) self::p( $r, 'provider', 'fcm' ) ), (string) self::p( $r, 'app_version' ) );
		return is_wp_error( $res ) ? $res : array( 'ok' => true );
	}

	public static function device_remove( WP_REST_Request $r ) {
		HS_API_Devices::remove( get_current_user_id(), (string) self::p( $r, 'token' ) );
		return array( 'ok' => true );
	}

	/* ---------- کلاس‌ها ---------- */

	private static function class_row( $cid, $uid ) {
		$t = get_userdata( (int) get_post_meta( $cid, '_hs_teacher_id', true ) );
		return array(
			'id'       => (int) $cid,
			'title'    => get_the_title( $cid ),
			'grade'    => (string) get_post_meta( $cid, '_hs_grade', true ),
			'teacher'  => $t ? $t->display_name : '',
			'teacher_id' => $t ? (int) $t->ID : 0,
			'students' => count( HS_API_Access::class_members( $cid ) ),
			'access'   => HS_API_Access::class_level( $cid, $uid ),
			'sort'     => class_exists( 'HS_Class_Students' ) && method_exists( 'HS_Class_Students', 'sort_mode' ) ? HS_Class_Students::sort_mode( $cid ) : 'first',
		);
	}

	public static function classes() {
		$uid = get_current_user_id();
		return array_map( function ( $cid ) use ( $uid ) { return self::class_row( $cid, $uid ); }, HS_API_Access::class_ids( $uid ) );
	}

	/** فقط معلم (یا مدیر مدرسه‌ی تأییدشده برای کلاس‌های مدرسه) — همان قاعده‌ی سایت */
	public static function can_create_class() {
		if ( ! is_user_logged_in() ) { return HS_API_Util::err( 'ابتدا وارد شوید.', 401, 'hs_unauthorized' ); }
		if ( ! HS_API_Access::ready() || ! HS_Roles::can_create_class( get_current_user_id() ) ) {
			return HS_API_Util::err( 'فقط معلم می‌تواند کلاس بسازد. اگر معلم هستید، ثبت‌نام خود را با نقش «معلم» کامل کنید.', 403, 'hs_forbidden' );
		}
		return true;
	}

	/** فهرست پایه‌ها و استان‌ها + پیش‌فرض‌ها برای فرم ساخت کلاس */
	public static function class_options() {
		$uid  = get_current_user_id();
		$prof = class_exists( 'HSUM_Onboard' ) ? get_user_meta( $uid, HSUM_Onboard::DATA, true ) : array();
		$prof = is_array( $prof ) ? $prof : array();
		return array(
			'can_create' => HS_API_Access::ready() && HS_Roles::can_create_class( $uid ),
			'grades'     => class_exists( 'HS_Class_Meta' ) && method_exists( 'HS_Class_Meta', 'grades' ) ? array_values( (array) HS_Class_Meta::grades() ) : array(),
			'provinces'  => class_exists( 'HS_Util' ) && method_exists( 'HS_Util', 'provinces' ) ? array_values( HS_Util::provinces() ) : array(),
			'year'       => class_exists( 'HS_Util' ) && method_exists( 'HS_Util', 'academic_year' ) ? HS_Util::academic_year() : '',
			'defaults'   => array(
				'school'   => (string) get_user_meta( $uid, '_hs_school_name', true ),
				'city'     => (string) ( $prof['city'] ?? '' ),
				'province' => (string) ( $prof['province'] ?? '' ),
			),
		);
	}

	/** ساخت کلاس (همان منطق HS_Frontend_Handlers::create_class در سایت) */
	public static function class_create( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		$lim = HS_API_Util::limited( 'mkclass_' . $uid, 20, 'امروز کلاس زیادی ساخته‌اید؛ فردا دوباره امتحان کنید.' );
		if ( $lim ) { return $lim; }
		$title    = HS_API_Util::text( self::p( $r, 'title' ), 120 );
		$grade    = HS_API_Util::text( self::p( $r, 'grade' ), 40 );
		$school   = HS_API_Util::text( self::p( $r, 'school' ), 150 );
		$city     = HS_API_Util::text( self::p( $r, 'city' ), 80 );
		$province = HS_API_Util::text( self::p( $r, 'province' ), 40 );
		$desc     = HS_API_Util::textarea( self::p( $r, 'description' ), 1000 );
		$year     = HS_API_Util::text( self::p( $r, 'year' ), 20 );
		$year     = $year ? $year : ( class_exists( 'HS_Util' ) && method_exists( 'HS_Util', 'academic_year' ) ? HS_Util::academic_year() : '' );
		$grades   = class_exists( 'HS_Class_Meta' ) && method_exists( 'HS_Class_Meta', 'grades' ) ? array_values( (array) HS_Class_Meta::grades() ) : array();
		$provs    = class_exists( 'HS_Util' ) && method_exists( 'HS_Util', 'provinces' ) ? HS_Util::provinces() : array();
		if ( '' === $title ) { return HS_API_Util::err( 'نام کلاس را بنویسید.' ); }
		if ( $grade && $grades && ! in_array( $grade, $grades, true ) ) { return HS_API_Util::err( 'پایه را از فهرست انتخاب کنید.' ); }
		if ( $province && $provs && ! in_array( $province, $provs, true ) ) { $province = ''; }
		if ( '' === $school || '' === $city || '' === $province ) { return HS_API_Util::err( 'نام مدرسه، استان و شهر را وارد کنید.' ); }
		$post_id = wp_insert_post( array( 'post_title' => $title, 'post_type' => 'hs_class', 'post_status' => 'publish', 'post_author' => $uid ), true );
		if ( is_wp_error( $post_id ) ) { return HS_API_Util::err( 'ساخت کلاس انجام نشد. دوباره امتحان کنید.', 500 ); }
		HS_API_Util::hit( 'mkclass_' . $uid, DAY_IN_SECONDS );
		update_post_meta( $post_id, '_hs_grade', $grade );
		update_post_meta( $post_id, '_hs_year', $year );
		update_post_meta( $post_id, '_hs_description', $desc );
		update_post_meta( $post_id, '_hs_school', $school );
		update_post_meta( $post_id, '_hs_city', $city );
		update_post_meta( $post_id, '_hs_province', $province );
		$mm = (string) get_user_meta( $uid, '_hs_manager_mobile', true );
		if ( $mm ) {
			update_post_meta( $post_id, '_hs_manager_mobile', $mm );
			do_action( 'hs_class_manager_mobile', $post_id, $mm );
		}
		do_action( 'hs_class_location_saved', $post_id, $school, $city, $province );
		update_post_meta( $post_id, '_hs_teacher_id', $uid );
		update_post_meta( $post_id, '_hs_students', array() );
		if ( class_exists( 'HS_Class_Meta' ) && method_exists( 'HS_Class_Meta', 'generate_invite_code' ) ) {
			update_post_meta( $post_id, '_hs_invite_code', HS_Class_Meta::generate_invite_code() );
		}
		if ( ! get_user_meta( $uid, '_hs_school_name', true ) ) {
			update_user_meta( $uid, '_hs_school_name', $school );
		}
		do_action( 'hs_api_class_created', (int) $post_id, $uid );
		return self::json( array( 'message' => 'کلاس ساخته شد.', 'class' => self::class_row( (int) $post_id, $uid ) ), 201 );
	}

	public static function class_one( WP_REST_Request $r ) {
		return self::class_row( (int) $r['id'], get_current_user_id() );
	}

	private static function student_row( WP_User $u ) {
		return array(
			'id'         => $u->ID,
			'name'       => $u->display_name,
			'first_name' => (string) $u->first_name,
			'last_name'  => (string) $u->last_name,
			'avatar'     => HS_API_Util::avatar( $u->ID, 96 ),
			'has_photo'  => (bool) get_user_meta( $u->ID, '_hs_avatar_url', true ),
		);
	}

	private static function class_students( $cid ) {
		return class_exists( 'HS_Class_Students' ) ? HS_Class_Students::get_students( (int) $cid ) : array();
	}

	public static function students( WP_REST_Request $r ) {
		return array_map( array( __CLASS__, 'student_row' ), self::class_students( (int) $r['id'] ) );
	}

	/** پروفایل سریع دانش‌آموز برای معلم/ناظر */
	public static function student_one( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		$sid = (int) $r['sid'];
		if ( ! in_array( $sid, HS_API_Access::class_members( $cid ), true ) ) {
			return HS_API_Util::err( 'این دانش‌آموز در این کلاس نیست.', 404, 'hs_not_found' );
		}
		$u   = get_userdata( $sid );
		$out = self::student_row( $u );
		$out['points_total'] = class_exists( 'HS_Points_DB' ) ? (int) HS_Points_DB::get_student_total( $cid, $sid ) : 0;
		$out['points_recent'] = array();
		if ( class_exists( 'HS_Points_DB' ) ) {
			foreach ( HS_Points_DB::get_student_history( $cid, $sid, 20 ) as $p ) {
				$out['points_recent'][] = array( 'id' => (int) $p['id'], 'points' => (int) $p['points'], 'behavior' => (string) $p['behavior_name'], 'icon' => (string) $p['behavior_icon'], 'note' => (string) $p['note'], 'at' => $p['created_at'] );
			}
		}
		$out['attendance_month'] = new stdClass();
		if ( class_exists( 'HS_Attendance_DB' ) ) {
			$sum = HS_Attendance_DB::get_monthly_summary( $cid, (int) current_time( 'Y' ), (int) current_time( 'n' ) );
			if ( isset( $sum[ $sid ] ) ) { $out['attendance_month'] = $sum[ $sid ]; }
		}
		$out['parents'] = array();
		if ( class_exists( 'HS_Parent_Link' ) ) {
			foreach ( HS_Parent_Link::get_parents( $sid ) as $pid ) {
				$p = get_userdata( $pid );
				if ( $p ) { $out['parents'][] = array( 'id' => $p->ID, 'name' => $p->display_name ); }
			}
		}
		return $out;
	}

	/* ---------- حضور و غیاب ---------- */

	const ATT_STATUSES = array( 'present', 'absent', 'late', 'excused' );

	public static function attendance_get( WP_REST_Request $r ) {
		$cid  = (int) $r['id'];
		$date = HS_API_Util::date( self::p( $r, 'date' ) );
		$date = $date ? $date : HS_API_Util::today();
		$rec  = class_exists( 'HS_Attendance_DB' ) ? (array) HS_Attendance_DB::get_by_class_date( $cid, $date ) : array();
		$out  = array();
		foreach ( self::class_students( $cid ) as $u ) {
			$row   = self::student_row( $u );
			$row['student_id'] = $u->ID;
			$row['status'] = isset( $rec[ $u->ID ] ) ? $rec[ $u->ID ]['status'] : null;
			$row['note']   = isset( $rec[ $u->ID ] ) ? (string) $rec[ $u->ID ]['note'] : '';
			$out[] = $row;
		}
		return array( 'date' => $date, 'recorded' => (bool) $rec, 'records' => $out );
	}

	/** کدام روزهای یک هفته ثبت شده‌اند (نوار روزهای هفته در اپ) */
	public static function attendance_week( WP_REST_Request $r ) {
		$cid  = (int) $r['id'];
		$from = HS_API_Util::date( self::p( $r, 'from' ) );
		$from = $from ? $from : HS_API_Util::today();
		$days = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$d      = gmdate( 'Y-m-d', strtotime( $from . ' +' . $i . ' days' ) );
			$days[] = array( 'date' => $d, 'recorded' => class_exists( 'HS_Attendance_DB' ) ? (bool) HS_Attendance_DB::is_recorded( $cid, $d ) : false );
		}
		return array( 'from' => $from, 'days' => $days );
	}

	public static function attendance_save( WP_REST_Request $r ) {
		$cid  = (int) $r['id'];
		$date = HS_API_Util::date( self::p( $r, 'date' ) );
		$recs = self::p( $r, 'records', array() );
		if ( ! $date || ! is_array( $recs ) || ! $recs ) { return HS_API_Util::err( 'تاریخ (YYYY-MM-DD) و فهرست records لازم است.' ); }
		if ( ! HS_API_Util::date_in_range( $date ) ) { return HS_API_Util::err( 'این تاریخ برای ثبت حضور مجاز نیست.' ); }
		if ( count( $recs ) > 200 ) { return HS_API_Util::err( 'تعداد رکوردها زیاد است.' ); }
		$members = HS_API_Access::class_members( $cid );
		$batch   = array();
		$skipped = 0;
		foreach ( $recs as $x ) {
			$sid = is_array( $x ) && isset( $x['student_id'] ) ? (int) $x['student_id'] : 0;
			if ( ! in_array( $sid, $members, true ) ) { $skipped++; continue; } // فقط دانش‌آموزان همین کلاس
			$st = isset( $x['status'] ) ? sanitize_key( (string) $x['status'] ) : 'present';
			$batch[ $sid ] = array( 'status' => in_array( $st, self::ATT_STATUSES, true ) ? $st : 'present', 'note' => HS_API_Util::text( $x['note'] ?? '', 255 ) );
		}
		if ( ! $batch ) { return HS_API_Util::err( 'هیچ دانش‌آموز معتبری در فهرست نبود.' ); }
		$saved = HS_Attendance_DB::save_batch( $cid, $date, $batch );
		do_action( 'hs_api_attendance_saved', $cid, $date, $batch, get_current_user_id() );
		return array( 'saved' => $saved, 'skipped' => $skipped, 'date' => $date );
	}

	/* ---------- تکالیف ---------- */

	private static function hw_row( $h ) {
		return array( 'id' => (int) $h['id'], 'title' => (string) $h['title'], 'description' => (string) $h['description'], 'subject' => (string) $h['subject'], 'due_date' => $h['due_date'], 'created_at' => $h['created_at'] ?? null );
	}

	public static function homework_list( WP_REST_Request $r ) {
		$cid   = (int) $r['id'];
		$uid   = get_current_user_id();
		$level = $r['_level'];
		$staff = in_array( $level, array( HS_API_Access::WRITE, HS_API_Access::SUPER ), true );
		$mine  = array();
		if ( HS_API_Access::PARENT === $level ) {
			$mine = HS_API_Access::children_in_class( $uid, $cid, 'homework' );
			if ( ! $mine ) {
				return HS_API_Util::err( 'مدرسه دیدن تکالیف این کلاس را برای شما بسته است.', 403, 'hs_feature_locked', array( 'feature' => 'homework' ) );
			}
		} elseif ( HS_API_Access::STUDENT === $level ) {
			$mine = array( $uid );
		}
		$list = HS_Homework_DB::get_class_homework( $cid, 50 );
		return array_map( function ( $h ) use ( $staff, $mine ) {
			$o = self::hw_row( $h );
			if ( $staff ) {
				$o['summary'] = HS_Homework_DB::get_summary( $h['id'] );
			} else {
				$st = HS_Homework_DB::get_statuses( $h['id'] );
				$o['my_status'] = array();
				foreach ( $mine as $sid ) {
					$o['my_status'][ $sid ] = isset( $st[ $sid ] ) ? array( 'status' => $st[ $sid ]['status'], 'feedback' => $st[ $sid ]['feedback'] ) : array( 'status' => 'pending', 'feedback' => '' );
				}
				$o['my_status'] = (object) $o['my_status'];
			}
			return $o;
		}, (array) $list );
	}

	public static function homework_create( WP_REST_Request $r ) {
		$title = HS_API_Util::text( self::p( $r, 'title' ), 190 );
		if ( '' === $title ) { return HS_API_Util::err( 'عنوان تکلیف لازم است.' ); }
		$due = HS_API_Util::date( self::p( $r, 'due_date' ) );
		$id  = HS_Homework_DB::create( (int) $r['id'], $title, HS_API_Util::textarea( self::p( $r, 'description' ) ), HS_API_Util::text( self::p( $r, 'subject' ), 100 ), $due ? $due : null );
		do_action( 'hs_api_homework_created', (int) $id, (int) $r['id'], get_current_user_id() );
		return self::json( array( 'id' => (int) $id ), 201 );
	}

	public static function hw_statuses( WP_REST_Request $r ) {
		$hid = (int) $r['id'];
		$st  = HS_Homework_DB::get_statuses( $hid );
		$out = array();
		foreach ( self::class_students( self::hw_class( $hid ) ) as $u ) {
			$out[] = array( 'student_id' => $u->ID, 'name' => $u->display_name, 'avatar' => HS_API_Util::avatar( $u->ID, 96 ), 'status' => isset( $st[ $u->ID ] ) ? $st[ $u->ID ]['status'] : 'pending', 'feedback' => isset( $st[ $u->ID ] ) ? $st[ $u->ID ]['feedback'] : '' );
		}
		return $out;
	}

	public static function hw_statuses_save( WP_REST_Request $r ) {
		$hid     = (int) $r['id'];
		$members = HS_API_Access::class_members( self::hw_class( $hid ) );
		$batch   = array();
		$valid   = array_keys( HS_Homework_DB::statuses() );
		foreach ( (array) self::p( $r, 'records', array() ) as $x ) {
			$sid = is_array( $x ) && isset( $x['student_id'] ) ? (int) $x['student_id'] : 0;
			$st  = is_array( $x ) && isset( $x['status'] ) ? sanitize_key( (string) $x['status'] ) : '';
			if ( in_array( $sid, $members, true ) && in_array( $st, $valid, true ) ) {
				$batch[ $sid ] = array( 'status' => $st, 'feedback' => HS_API_Util::textarea( $x['feedback'] ?? '', 1000 ) );
			}
		}
		return array( 'saved' => HS_Homework_DB::set_status_batch( $hid, $batch ) );
	}

	/* ---------- اطلاعیه‌ها ---------- */

	public static function ann_list( WP_REST_Request $r ) {
		if ( HS_API_Access::PARENT === $r['_level'] && ! HS_API_Access::children_in_class( get_current_user_id(), (int) $r['id'], 'announce' ) ) {
			return HS_API_Util::err( 'مدرسه دیدن اطلاعیه‌های این کلاس را برای شما بسته است.', 403, 'hs_feature_locked', array( 'feature' => 'announce' ) );
		}
		return array_map( function ( $a ) {
			$au = get_userdata( (int) $a['author_id'] );
			return array( 'id' => (int) $a['id'], 'title' => $a['title'], 'body' => wp_strip_all_tags( $a['body'] ), 'pinned' => (bool) $a['is_pinned'], 'author' => $au ? $au->display_name : '', 'created_at' => $a['created_at'] );
		}, (array) HS_Comms_DB::get_announcements( (int) $r['id'], 30 ) );
	}

	public static function ann_create( WP_REST_Request $r ) {
		$title = HS_API_Util::text( self::p( $r, 'title' ), 190 );
		$body  = HS_API_Util::textarea( self::p( $r, 'body' ) );
		if ( '' === $title || '' === trim( $body ) ) { return HS_API_Util::err( 'عنوان و متن لازم است.' ); }
		$id = HS_Comms_DB::add_announcement( (int) $r['id'], $title, $body, get_current_user_id(), (bool) rest_sanitize_boolean( self::p( $r, 'pinned', false ) ) );
		do_action( 'hs_api_announcement_created', (int) $id, (int) $r['id'], get_current_user_id() );
		return self::json( array( 'id' => (int) $id ), 201 );
	}

	/* ---------- ثبت دانش‌آموز ---------- */

	public static function student_create( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_Student_Profile' ) || ! method_exists( 'HS_Student_Profile', 'create_in_class' ) ) {
			return HS_API_Util::err( 'افزونه‌ی مدیریت کلاس نسخه‌ی ۱.۰.۸ یا بالاتر لازم است.', 500 );
		}
		$d = array();
		foreach ( array( 'first_name', 'last_name', 'name', 'parent_name', 'parent_phone', 'class_number', 'birth_date' ) as $k ) {
			$d[ $k ] = HS_API_Util::text( HS_API_Util::digits( (string) self::p( $r, $k, '' ) ), 100 );
		}
		$res = HS_Student_Profile::create_in_class( (int) $r['id'], $d );
		if ( is_wp_error( $res ) ) {
			$code = $res->get_error_code();
			$st   = in_array( $code, array( 'duplicate', 'duplicate_number' ), true ) ? 409 : ( 'busy' === $code ? 429 : 400 );
			return HS_API_Util::err( $res->get_error_message(), $st, 'hs_' . $code );
		}
		return self::json( array(
			'id'          => (int) $res['user_id'],
			'name'        => $res['profile']['display_name'],
			'username'    => $res['username'],
			'password'    => $res['password'],
			'parent_code' => class_exists( 'HS_Parent_Link' ) ? HS_Parent_Link::get_code( $res['user_id'] ) : '',
		), 201 );
	}

	/* ---------- امتیاز ---------- */

	public static function points_get( WP_REST_Request $r ) {
		$cid  = (int) $r['id'];
		$rows = array();
		foreach ( self::class_students( $cid ) as $u ) {
			$row          = self::student_row( $u );
			$row['student_id'] = $u->ID;
			$row['total'] = (int) HS_Points_DB::get_student_total( $cid, $u->ID );
			$rows[]       = $row;
		}
		$beh = array_map( function ( $b ) {
			return array( 'id' => (int) $b['id'], 'name' => $b['name'], 'icon' => $b['icon'], 'points' => (int) $b['points'], 'positive' => (int) $b['points'] >= 0 && ( ! isset( $b['is_positive'] ) || (int) $b['is_positive'] ) );
		}, (array) HS_Points_DB::get_behaviors( $cid ) );
		$board = $rows;
		usort( $board, function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
		return array( 'students' => $rows, 'leaderboard' => $board, 'behaviors' => $beh );
	}

	public static function points_award( WP_REST_Request $r ) {
		$cid = (int) $r['id'];
		$bid = (int) self::p( $r, 'behavior_id' );
		$beh = $bid ? HS_Points_DB::get_behavior( $bid ) : null;
		if ( ! $beh || ( (int) $beh['class_id'] && (int) $beh['class_id'] !== $cid ) ) { return HS_API_Util::err( 'رفتار نامعتبر است.' ); }
		$ids = self::p( $r, 'student_ids', array() );
		if ( ! is_array( $ids ) ) { $ids = array( $ids ); }
		if ( count( $ids ) > 200 ) { return HS_API_Util::err( 'تعداد دانش‌آموزان زیاد است.' ); }
		$members = HS_API_Access::class_members( $cid );
		$note    = HS_API_Util::text( self::p( $r, 'note' ), 255 );
		$n       = 0;
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $sid ) {
			if ( in_array( $sid, $members, true ) ) {
				HS_Points_DB::award( $cid, $sid, $bid, null, $note, get_current_user_id() );
				$n++;
			}
		}
		if ( $n ) { do_action( 'hs_api_points_awarded', $cid, $bid, get_current_user_id() ); }
		return array( 'awarded' => $n, 'points' => (int) $beh['points'] );
	}

	/* ---------- دعوت کلاس (QR) ---------- */

	private static function invite_payload( $cid ) {
		$out = array( 'class_id' => $cid, 'parent' => null, 'student_card' => null );
		if ( class_exists( 'HSUM_Invites' ) ) {
			$inv = HSUM_Invites::class_invite( $cid, true );
			if ( $inv ) {
				$out['parent'] = array( 'code' => $inv['code'], 'url' => HSUM_Invites::link_url( $inv['code'] ), 'expires_at' => $inv['expires_at'], 'qr' => HSUM_Invites::link_url( $inv['code'] ) );
			}
		} else {
			$code = (string) get_post_meta( $cid, '_hs_invite_code', true );
			if ( $code ) { $out['parent'] = array( 'code' => $code, 'url' => '', 'expires_at' => null, 'qr' => $code ); }
		}
		if ( class_exists( 'HSSP_Login' ) ) {
			$c = HSSP_Login::class_code( $cid, true );
			$out['student_card'] = array(
				'code'          => $c,
				'url'           => HSSP_Login::url( $c ),
				'qr'            => HSSP_Login::url( $c ),
				'login_enabled' => class_exists( 'HSSP_Settings' ) ? (bool) HSSP_Settings::get( $cid )['login'] : false,
			);
		}
		return $out;
	}

	public static function invite_get( WP_REST_Request $r ) {
		return self::invite_payload( (int) $r['id'] );
	}

	/** ساخت کد تازه (کد قبلی باطل می‌شود): which = parent | student_card */
	public static function invite_reset( WP_REST_Request $r ) {
		$cid   = (int) $r['id'];
		$which = sanitize_key( (string) self::p( $r, 'which', 'parent' ) );
		$lim   = HS_API_Util::limited( 'invreset_' . get_current_user_id(), 10 );
		if ( $lim ) { return $lim; }
		HS_API_Util::hit( 'invreset_' . get_current_user_id(), HOUR_IN_SECONDS );
		if ( 'student_card' === $which && class_exists( 'HSSP_Login' ) ) {
			HSSP_Login::reset_code( $cid );
		} elseif ( 'parent' === $which && class_exists( 'HSUM_Invites' ) ) {
			HSUM_Invites::new_class_code( $cid );
		} else {
			return HS_API_Util::err( 'نوع کد نامعتبر است.' );
		}
		return self::invite_payload( $cid );
	}

	/* ---------- پیام‌ها ---------- */

	public static function inbox() {
		$uid = get_current_user_id();
		$out = array();
		foreach ( (array) HS_Comms_DB::get_inbox( $uid, 60 ) as $m ) {
			$o = (int) ( (int) $m['sender_id'] === $uid ? $m['recipient_id'] : $m['sender_id'] );
			if ( ! HS_API_Access::can_read_thread( $uid, $o ) ) { continue; } // رابطه‌ی قطع‌شده دیده نمی‌شود
			$u     = get_userdata( $o );
			$out[] = array( 'user_id' => $o, 'name' => $u ? $u->display_name : '', 'avatar' => HS_API_Util::avatar( $o, 96 ), 'preview' => HS_Comms_DB::preview( $m ), 'at' => $m['created_at'], 'unread' => (int) $m['recipient_id'] === $uid && ! (int) $m['is_read'], 'can_reply' => HS_API_Access::can_message( $uid, $o ) );
			if ( count( $out ) >= 40 ) { break; }
		}
		return $out;
	}

	public static function thread( WP_REST_Request $r ) {
		$uid = get_current_user_id();
		$o   = (int) $r['id'];
		if ( ! HS_API_Access::can_read_thread( $uid, $o ) ) { return HS_API_Util::err( 'دسترسی ندارید.', 403, 'hs_forbidden' ); }
		HS_Comms_DB::mark_read( $uid, $o );
		return array(
			'can_reply' => HS_API_Access::can_message( $uid, $o ),
			'messages'  => array_map( function ( $m ) use ( $uid ) {
				return array( 'id' => (int) $m['id'], 'mine' => (int) $m['sender_id'] === $uid, 'body' => (string) $m['body'], 'image' => $m['media_url'] ? esc_url_raw( $m['media_url'] ) : '', 'at' => $m['created_at'] );
			}, (array) HS_Comms_DB::get_conversation( $uid, $o, 0, 100 ) ),
		);
	}

	public static function send( WP_REST_Request $r ) {
		$uid  = get_current_user_id();
		$to   = (int) self::p( $r, 'recipient_id' );
		$body = HS_API_Util::textarea( self::p( $r, 'body' ), 2000 );
		if ( '' === trim( $body ) ) { return HS_API_Util::err( 'متن پیام خالی است.' ); }
		if ( ! HS_API_Access::can_message( $uid, $to ) ) { return HS_API_Util::err( 'امکان ارسال پیام به این کاربر وجود ندارد.', 403, 'hs_forbidden' ); }
		$lim = HS_API_Util::limited( 'msg_' . $uid, 30, 'پیام‌ها خیلی پشت سر هم فرستاده شد؛ کمی صبر کنید.' );
		if ( $lim ) { return $lim; }
		HS_API_Util::hit( 'msg_' . $uid, MINUTE_IN_SECONDS * 5 );
		$cid = (int) self::p( $r, 'class_id', 0 );
		if ( $cid && ! HS_API_Access::class_level( $cid, $uid ) ) { $cid = 0; }
		$id = HS_Comms_DB::send_message( $uid, $to, $body, $cid );
		do_action( 'hs_api_message_sent', (int) $id, $uid, $to );
		return self::json( array( 'id' => (int) $id ), 201 );
	}

	/* ---------- مدرسه ---------- */

	private static function sid() { return (int) HS_API_Access::school_id( get_current_user_id() ); }

	public static function school() {
		if ( ! class_exists( 'HS_SO_Store' ) ) { return HS_API_Util::err( 'بخش مدرسه فعال نیست.', 503, 'hs_unavailable' ); }
		$sid = self::sid();
		if ( ! $sid ) { return HS_API_Util::err( 'مدرسه‌ای برای شما ثبت نشده است.', 404, 'hs_not_found' ); }
		$uid = get_current_user_id();
		$sup = HS_SO_Store::can_supervise( $sid, $uid );
		if ( ! $sup ) {
			// عضو کادر بدون اجازه‌ی نظارت: فقط نام مدرسه
			return array( 'id' => $sid, 'name' => get_the_title( $sid ), 'can_supervise' => false, 'is_manager' => false );
		}
		$s = HS_SO_Store::get_school( $sid );
		$s['can_supervise'] = true;
		$s['is_manager']    = HS_SO_Store::is_manager( $sid, $uid );
		$s['task_counts']   = HS_SO_Store::task_counts( $sid );
		if ( class_exists( 'HS_SO_Monitor' ) ) {
			$s['classes'] = array_map( function ( $cid ) use ( $uid ) { return self::class_row( $cid, $uid ); }, HS_SO_Monitor::class_ids( $sid ) );
		}
		if ( $s['is_manager'] ) {
			$s['staff'] = array_map( function ( $row ) {
				$u = get_userdata( (int) $row['user_id'] );
				return array( 'id' => (int) $row['id'], 'user_id' => (int) $row['user_id'], 'name' => $u ? $u->display_name : '', 'position' => $row['position'] ?? '', 'label' => (string) ( $row['label'] ?? '' ), 'can_view' => (bool) ( $row['can_view'] ?? 0 ) );
			}, (array) HS_SO_Store::staff_list( $sid ) );
			$s['invites'] = array();
			if ( class_exists( 'HSUM_Staff' ) ) {
				foreach ( (array) HSUM_Staff::rows( $sid ) as $inv ) {
					$s['invites'][] = array( 'id' => (int) $inv['id'], 'type' => 'staff', 'name' => (string) ( $inv['name'] ?? '' ), 'position' => (string) ( $inv['d']['position'] ?? '' ), 'status' => (string) $inv['status'], 'created_at' => $inv['created_at'] ?? null );
				}
			}
			if ( class_exists( 'HSUM_Invites' ) ) {
				foreach ( (array) HSUM_Invites::school_invites( $sid ) as $inv ) {
					$s['invites'][] = array( 'id' => (int) $inv['id'], 'type' => 'teacher', 'name' => (string) ( $inv['name'] ?? '' ), 'position' => 'teacher', 'status' => (string) $inv['status'], 'created_at' => $inv['created_at'] ?? null );
				}
			}
		}
		return $s;
	}

	public static function tasks() {
		if ( ! class_exists( 'HS_SO_Store' ) ) { return array(); }
		$sid = self::sid();
		$uid = get_current_user_id();
		if ( ! $sid ) { return array(); }
		$args = HS_SO_Store::is_manager( $sid, $uid ) ? array() : array( 'assignee' => $uid );
		return HS_SO_Store::tasks( $sid, $args );
	}

	public static function task_status( WP_REST_Request $r ) {
		if ( ! class_exists( 'HS_SO_Store' ) ) { return HS_API_Util::err( 'بخش مدرسه فعال نیست.', 503, 'hs_unavailable' ); }
		$t   = HS_SO_Store::task( (int) $r['id'] );
		$uid = get_current_user_id();
		$sid = self::sid();
		if ( ! $t || ! $sid || (int) $t['school_id'] !== $sid ) { return HS_API_Util::err( 'وظیفه پیدا نشد.', 404, 'hs_not_found' ); }
		if ( (int) $t['assignee_id'] !== $uid ) { return HS_API_Util::err( 'فقط مسئول انجام وظیفه می‌تواند وضعیت را تغییر دهد.', 403, 'hs_forbidden' ); }
		$st = sanitize_key( (string) self::p( $r, 'status' ) );
		if ( ! isset( HS_SO_Structure::statuses()[ $st ] ) ) { return HS_API_Util::err( 'وضعیت نامعتبر.' ); }
		global $wpdb;
		$wpdb->update( HS_SO_Store::tasks_table(), array( 'status' => $st, 'report' => HS_API_Util::textarea( self::p( $r, 'report' ), 2000 ), 'updated_at' => current_time( 'mysql' ), 'done_at' => 'done' === $st ? current_time( 'mysql' ) : null ), array( 'id' => (int) $t['id'] ) );
		return array( 'ok' => true );
	}
}
