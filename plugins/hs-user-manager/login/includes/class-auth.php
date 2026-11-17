<?php
/**
 * درخواست‌های AJAX: ارسال کد، تأیید کد (ورود یا رفتن به ثبت‌نام)، ثبت‌نام، ورود با رمز
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_Auth {

	public static function init() {
		foreach ( array( 'send', 'verify', 'register', 'login_pw' ) as $a ) {
			add_action( 'wp_ajax_nopriv_hsma_' . $a, array( __CLASS__, $a ) );
			add_action( 'wp_ajax_hsma_' . $a, array( __CLASS__, $a ) );
		}
	}

	private static function guard() {
		if ( ! check_ajax_referer( 'hsma', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'صفحه منقضی شده؛ یک‌بار صفحه را تازه کنید.' ) );
		}
		if ( is_user_logged_in() ) {
			wp_send_json_success( array( 'redirect' => HSMA_Util::redirect_url( self::post( 'redirect_to' ) ) ) );
		}
		if ( HSMA_Util::is_blocked( HSMA_Util::ip() ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی شما موقتاً مسدود است. با مدیر سایت تماس بگیرید.' ) );
		}
	}

	private static function post( $k ) {
		return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
	}

	private static function mobile_or_die() {
		$m = HSMA_Util::mobile( self::post( 'mobile' ) );
		if ( ! $m ) {
			wp_send_json_error( array( 'message' => 'شماره موبایل درست نیست. مثال: 09121234567' ) );
		}
		return $m;
	}

	/** کاربرانی که فقط با رمز وارد می‌شوند (مدیران سایت) */
	private static function password_only( $user ) {
		return user_can( $user, 'manage_options' ) || user_can( $user, 'edit_others_posts' );
	}

	private static function do_login( $user, $remember = true ) {
		// افزونه‌های دیگر (مثلاً تعلیق حساب در مدیریت کاربران) می‌توانند ورود را ببندند
		$ok = apply_filters( 'hsma_can_login', true, $user );
		if ( is_wp_error( $ok ) ) {
			wp_send_json_error( array( 'message' => $ok->get_error_message() ) );
		}
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
	}

	/* ---------- ۱) ارسال کد ---------- */

	public static function send() {
		self::guard();
		$m   = self::mobile_or_die();
		$res = HSMA_OTP::send( $m );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message(), 'code' => $res->get_error_code() ) );
		}
		wp_send_json_success( array( 'message' => 'کد به ' . $m . ' پیامک شد.', 'wait' => $res['wait'] ) );
	}

	/* ---------- ۲) تأیید کد ---------- */

	public static function verify() {
		self::guard();
		$m  = self::mobile_or_die();
		$ok = HSMA_OTP::verify( $m, self::post( 'code' ) );
		if ( is_wp_error( $ok ) ) {
			wp_send_json_error( array( 'message' => $ok->get_error_message(), 'code' => $ok->get_error_code() ) );
		}

		$user = HSMA_Util::user_by_mobile( $m );
		if ( $user ) {
			if ( self::password_only( $user ) ) {
				wp_send_json_error( array( 'message' => 'این حساب مدیریتی است؛ از «ورود با رمز» استفاده کنید.' ) );
			}
			self::do_login( $user );
			wp_send_json_success( array(
				'message'  => 'خوش آمدید، ' . $user->display_name,
				'redirect' => HSMA_Util::redirect_url( self::post( 'redirect_to' ) ),
			) );
		}

		$roles = HSMA_Util::allowed_roles();
		if ( ! $roles ) {
			wp_send_json_error( array( 'message' => 'ثبت‌نام در حال حاضر بسته است.' ) );
		}
		wp_send_json_success( array(
			'register' => true,
			'ticket'   => HSMA_OTP::issue_ticket( $m ),
			'roles'    => $roles,
		) );
	}

	/* ---------- ۳) ثبت‌نام ---------- */

	public static function register() {
		self::guard();
		$ticket = self::post( 'ticket' );
		$m      = HSMA_OTP::ticket_mobile( $ticket );
		if ( ! $m ) {
			wp_send_json_error( array( 'message' => 'زمان ثبت‌نام تمام شد؛ دوباره شماره را تأیید کنید.', 'code' => 'ticket' ) );
		}
		if ( HSMA_Util::user_by_mobile( $m ) ) {
			HSMA_OTP::burn_ticket( $ticket );
			wp_send_json_error( array( 'message' => 'با این شماره قبلاً حساب ساخته شده است؛ وارد شوید.', 'code' => 'ticket' ) );
		}

		$first = self::post( 'first_name' );
		$last  = self::post( 'last_name' );
		$role  = sanitize_key( self::post( 'role' ) );
		$pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		$roles = HSMA_Util::allowed_roles();
		if ( '' === $first || '' === $last ) {
			wp_send_json_error( array( 'message' => 'نام و نام خانوادگی را وارد کنید.' ) );
		}
		if ( ! isset( $roles[ $role ] ) ) {
			wp_send_json_error( array( 'message' => 'نقش را انتخاب کنید.' ) );
		}
		if ( strlen( $pass ) < 6 ) {
			wp_send_json_error( array( 'message' => 'رمز عبور باید دست‌کم ۶ کاراکتر باشد.' ) );
		}

		$uid = wp_insert_user( array(
			'user_login'   => $m,
			'user_pass'    => $pass,
			'user_email'   => $m . '@hamshagerdi.local',
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => trim( $first . ' ' . $last ),
			'nickname'     => trim( $first . ' ' . $last ),
			'role'         => $role,
		) );
		if ( is_wp_error( $uid ) ) {
			wp_send_json_error( array( 'message' => 'ساخت حساب انجام نشد: ' . $uid->get_error_message() ) );
		}
		HSMA_OTP::burn_ticket( $ticket );
		update_user_meta( $uid, '_hs_mobile', $m );
		update_user_meta( $uid, '_hs_registered_via', 'mobile' );

		$notes = array();

		if ( 'student' === $role ) {
			if ( class_exists( 'HS_Student_Profile' ) ) {
				HS_Student_Profile::save( $uid, array( 'first_name' => $first, 'last_name' => $last ) );
			}
			$inv = self::post( 'invite_code' );
			if ( $inv && class_exists( 'HS_Class_Students' ) ) {
				$j = HS_Class_Students::join_by_invite_code( $inv, $uid );
				$notes[] = is_wp_error( $j ) ? 'عضویت در کلاس انجام نشد: ' . $j->get_error_message() : 'به کلاس اضافه شدید.';
			}
		}

		if ( 'parent' === $role ) {
			$cu = self::post( 'child_username' );
			$cc = self::post( 'child_code' );
			if ( $cu && $cc && class_exists( 'HS_Parent_Link' ) ) {
				$l = HS_Parent_Link::link_by_student_login( $uid, HSMA_Util::en_digits( $cu ), HSMA_Util::en_digits( $cc ) );
				$notes[] = is_wp_error( $l ) ? 'اتصال به فرزند انجام نشد: ' . $l->get_error_message() . ' (بعداً از پنل والد هم می‌شود)' : 'به حساب فرزندتان وصل شدید.';
			}
		}

		if ( in_array( $role, array( 'teacher', 'school_admin' ), true ) ) {
			$school = self::post( 'school' );
			if ( $school ) {
				update_user_meta( $uid, '_hs_school_name', $school );
			}
		}

		do_action( 'hsma_registered', $uid, $role, $m );
		$notes = (array) apply_filters( 'hsma_register_notes', $notes, $uid, $role );

		$user = get_userdata( $uid );
		self::do_login( $user );
		wp_send_json_success( array(
			'message'  => 'ثبت‌نام انجام شد. نام کاربری شما همین شماره موبایل است.',
			'notes'    => $notes,
			'redirect' => HSMA_Util::redirect_url( self::post( 'redirect_to' ) ),
		) );
	}

	/* ---------- ورود با رمز ---------- */

	public static function login_pw() {
		self::guard();
		$raw  = self::post( 'mobile' );
		$m    = HSMA_Util::mobile( $raw );
		$pass = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$ip   = HSMA_Util::ip();
		$id   = $m ? $m : sanitize_user( $raw );

		if ( '' === $id || '' === $pass ) {
			wp_send_json_error( array( 'message' => 'شماره موبایل و رمز را وارد کنید.' ) );
		}
		if ( HSMA_Util::hits( 'pw_' . $id ) >= 5 || HSMA_Util::hits( 'pw_ip_' . $ip ) >= 20 ) {
			wp_send_json_error( array( 'message' => 'تلاش ناموفق زیاد بود. ۱۵ دقیقه بعد امتحان کنید، یا با کد پیامکی وارد شوید.' ) );
		}

		// موبایل، یا نام کاربری قدیمی (مثلاً دانش‌آموزانی که معلم ساخته)
		$user = $m ? HSMA_Util::user_by_mobile( $m ) : false;
		if ( ! $user ) {
			$user = get_user_by( 'login', $id );
		}
		$ok = $user && wp_check_password( $pass, $user->user_pass, $user->ID );
		if ( ! $ok ) {
			HSMA_Util::hit( 'pw_' . $id, 15 * MINUTE_IN_SECONDS );
			HSMA_Util::hit( 'pw_ip_' . $ip, 15 * MINUTE_IN_SECONDS );
			wp_send_json_error( array( 'message' => 'شماره/نام کاربری یا رمز درست نیست.' ) );
		}
		HSMA_Util::reset( 'pw_' . $id );

		$signed = wp_signon( array(
			'user_login'    => $user->user_login,
			'user_password' => $pass,
			'remember'      => true,
		), is_ssl() );
		if ( is_wp_error( $signed ) ) {
			wp_send_json_error( array( 'message' => wp_strip_all_tags( $signed->get_error_message() ) ) );
		}
		wp_send_json_success( array(
			'message'  => 'خوش آمدید، ' . $signed->display_name,
			'redirect' => HSMA_Util::redirect_url( self::post( 'redirect_to' ) ),
		) );
	}
}
