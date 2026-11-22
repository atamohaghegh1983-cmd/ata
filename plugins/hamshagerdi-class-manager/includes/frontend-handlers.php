<?php
/**
 * هندلرهای AJAX و فرم‌های فرانت‌اند
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Frontend_Handlers {

	public static function init() {
		// ساخت کلاس
		add_action( 'wp_ajax_hs_create_class', array( __CLASS__, 'create_class' ) );
		// ویرایش کلاس
		add_action( 'wp_ajax_hs_update_class', array( __CLASS__, 'update_class' ) );
		// حذف کلاس
		add_action( 'wp_ajax_hs_delete_class', array( __CLASS__, 'delete_class' ) );
		// افزودن دانش‌آموز
		add_action( 'wp_ajax_hs_add_student', array( __CLASS__, 'add_student' ) );
		add_action( 'wp_ajax_hs_remove_student', array( __CLASS__, 'remove_student' ) );
		add_action( 'wp_ajax_hs_delete_student_account', array( __CLASS__, 'delete_student_account' ) );
		add_action( 'wp_ajax_hs_update_student_profile', array( __CLASS__, 'update_student_profile' ) );
		add_action( 'wp_ajax_hs_get_student_profile', array( __CLASS__, 'get_student_profile' ) );
		add_action( 'wp_ajax_hs_transfer_student', array( __CLASS__, 'transfer_student' ) );
		add_action( 'wp_ajax_hs_export_class_students', array( __CLASS__, 'export_class_students' ) );
		add_action( 'wp_ajax_hs_regenerate_code', array( __CLASS__, 'regenerate_code' ) );
		add_action( 'wp_ajax_hs_regen_parent_code', array( __CLASS__, 'regen_parent_code' ) );
		add_action( 'wp_ajax_hs_join_class', array( __CLASS__, 'join_class' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'لطفاً وارد شوید.' ) );
		}
	}

	public static function create_class() {
		self::verify();

		if ( ! HS_Roles::can_create_class() ) {
			wp_send_json_error( array( 'message' => 'شما مجاز به ساخت کلاس نیستید.' ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$grade = isset( $_POST['grade'] ) ? sanitize_text_field( wp_unslash( $_POST['grade'] ) ) : '';
		$year  = isset( $_POST['year'] ) ? sanitize_text_field( wp_unslash( $_POST['year'] ) ) : HS_Util::academic_year();
		$desc  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$school   = isset( $_POST['school'] ) ? sanitize_text_field( wp_unslash( $_POST['school'] ) ) : '';
		$city     = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$province = isset( $_POST['province'] ) ? sanitize_text_field( wp_unslash( $_POST['province'] ) ) : '';
		if ( $province && ! in_array( $province, HS_Util::provinces(), true ) ) {
			$province = '';
		}

		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'نام کلاس الزامی است.' ) );
		}
		if ( '' === $school || '' === $city || '' === $province ) {
			wp_send_json_error( array( 'message' => 'نام مدرسه، استان و شهر را وارد کنید.' ) );
		}

		$post_id = wp_insert_post( array(
			'post_title'  => $title,
			'post_type'   => 'hs_class',
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		), true );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		update_post_meta( $post_id, '_hs_grade', $grade );
		update_post_meta( $post_id, '_hs_year', $year );
		update_post_meta( $post_id, '_hs_description', $desc );
		update_post_meta( $post_id, '_hs_school', $school );
		update_post_meta( $post_id, '_hs_city', $city );
		// نشانی مدرسه و مدیر (برای اتصال خودکار کلاس به مدرسه)
		foreach ( array( 'school_address' => '_hs_school_address', 'manager_name' => '_hs_manager_name' ) as $f => $mk ) {
			if ( isset( $_POST[ $f ] ) ) {
				update_post_meta( $post_id, $mk, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
			}
		}
		if ( ! empty( $_POST['manager_mobile'] ) ) {
			$mm = sanitize_text_field( wp_unslash( $_POST['manager_mobile'] ) );
			$mm = class_exists( 'HSUM_Phone' ) ? HSUM_Phone::norm( $mm ) : preg_replace( '/\D/', '', $mm );
			if ( $mm ) {
				update_post_meta( $post_id, '_hs_manager_mobile', $mm );
				do_action( 'hs_class_manager_mobile', $post_id, $mm );
			}
		}
		update_post_meta( $post_id, '_hs_province', $province );
		do_action( 'hs_class_location_saved', $post_id, $school, $city, $province );
		update_post_meta( $post_id, '_hs_teacher_id', get_current_user_id() );
		update_post_meta( $post_id, '_hs_students', array() );

		$code = HS_Class_Meta::generate_invite_code();
		update_post_meta( $post_id, '_hs_invite_code', $code );

		wp_send_json_success( array(
			'message'   => 'کلاس با موفقیت ساخته شد.',
			'class_id'  => $post_id,
			'invite'    => $code,
			'redirect'  => add_query_arg( array( 'class_id' => $post_id ), home_url( '/panel/classes/' ) ),
		) );
	}

	public static function update_class() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$grade = isset( $_POST['grade'] ) ? sanitize_text_field( wp_unslash( $_POST['grade'] ) ) : '';
		$year  = isset( $_POST['year'] ) ? sanitize_text_field( wp_unslash( $_POST['year'] ) ) : '';
		$desc  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$school   = isset( $_POST['school'] ) ? sanitize_text_field( wp_unslash( $_POST['school'] ) ) : '';
		$city     = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$province = isset( $_POST['province'] ) ? sanitize_text_field( wp_unslash( $_POST['province'] ) ) : '';
		if ( $province && ! in_array( $province, HS_Util::provinces(), true ) ) {
			$province = '';
		}

		if ( $title ) {
			wp_update_post( array( 'ID' => $class_id, 'post_title' => $title ) );
		}
		update_post_meta( $class_id, '_hs_grade', $grade );
		update_post_meta( $class_id, '_hs_year', $year );
		update_post_meta( $class_id, '_hs_description', $desc );
		update_post_meta( $class_id, '_hs_school', $school );
		update_post_meta( $class_id, '_hs_city', $city );
		update_post_meta( $class_id, '_hs_province', $province );
		do_action( 'hs_class_location_saved', $class_id, $school, $city, $province );

		wp_send_json_success( array( 'message' => 'تغییرات ذخیره شد.' ) );
	}

	public static function delete_class() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		// حذف رابطه دانش‌آموزان
		$students = get_post_meta( $class_id, '_hs_students', true );
		if ( is_array( $students ) ) {
			foreach ( $students as $uid ) {
				$user_classes = get_user_meta( $uid, '_hs_classes', true );
				if ( is_array( $user_classes ) ) {
					$user_classes = array_diff( $user_classes, array( $class_id ) );
					update_user_meta( $uid, '_hs_classes', array_values( $user_classes ) );
				}
			}
		}

		wp_trash_post( $class_id );
		wp_send_json_success( array( 'message' => 'کلاس حذف شد.' ) );
	}

	public static function add_student() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$data = array(
			'first_name'   => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
			'last_name'    => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
			'name'         => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'parent_name'  => isset( $_POST['parent_name'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_name'] ) ) : '',
			'parent_phone' => isset( $_POST['parent_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_phone'] ) ) : '',
			'parent2_name' => isset( $_POST['parent2_name'] ) ? sanitize_text_field( wp_unslash( $_POST['parent2_name'] ) ) : '',
			'parent2_phone' => isset( $_POST['parent2_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['parent2_phone'] ) ) : '',
			'class_number' => isset( $_POST['class_number'] ) ? sanitize_text_field( wp_unslash( $_POST['class_number'] ) ) : '',
			'birth_date'   => isset( $_POST['birth_date'] ) ? sanitize_text_field( wp_unslash( $_POST['birth_date'] ) ) : '',
			'photo_url'    => isset( $_POST['photo_url'] ) ? esc_url_raw( wp_unslash( $_POST['photo_url'] ) ) : '',
			'status'       => isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'active',
			'notes'        => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		$result = HS_Student_Profile::create_in_class( $class_id, $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$profile = isset( $result['profile'] ) ? $result['profile'] : HS_Student_Profile::get( $result['user_id'] );

		wp_send_json_success( array(
			'message'  => 'دانش‌آموز ثبت شد.',
			'student'  => array(
				'id'           => $result['user_id'],
				'name'         => $profile['display_name'],
				'first_name'   => $profile['first_name'],
				'last_name'    => $profile['last_name'],
				'parent_name'  => $profile['parent_name'],
				'parent_phone' => $profile['parent_phone'],
				'class_number' => $profile['class_number'],
				'status'       => $profile['status'],
				'photo_url'    => $profile['photo_url'],
				'username'     => $result['username'],
				'password'     => $result['password'],
				'parent_code'  => HS_Parent_Link::get_code( $result['user_id'] ),
			),
			'count'    => HS_Class_Students::count( $class_id ),
		) );
	}

	public static function get_student_profile() {
		self::verify();
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $class_id || ! $user_id || ! HS_Roles::can_manage_class( $class_id ) || ! HS_Class_Students::is_member( $class_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		$profile = HS_Student_Profile::get( $user_id );
		if ( ! $profile ) {
			wp_send_json_error( array( 'message' => 'دانش‌آموز یافت نشد.' ) );
		}
		$profile['parent_code'] = HS_Parent_Link::get_code( $user_id );
		wp_send_json_success( array( 'profile' => $profile ) );
	}

	public static function update_student_profile() {
		self::verify();
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $class_id || ! $user_id || ! HS_Roles::can_manage_class( $class_id ) || ! HS_Class_Students::is_member( $class_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		$data = array();
		foreach ( array( 'first_name', 'last_name', 'parent_name', 'parent_phone', 'parent2_name', 'parent2_phone', 'class_number', 'birth_date', 'photo_url', 'status', 'notes' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$data[ $k ] = wp_unslash( $_POST[ $k ] );
			}
		}
		$cur = HS_Student_Profile::get( $user_id );
		$dup = HS_Student_Profile::find_duplicate(
			$class_id,
			isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : $cur['first_name'],
			isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : $cur['last_name'],
			isset( $data['parent_phone'] ) ? sanitize_text_field( $data['parent_phone'] ) : $cur['parent_phone'],
			isset( $data['class_number'] ) ? sanitize_text_field( $data['class_number'] ) : $cur['class_number'],
			$user_id
		);
		if ( is_wp_error( $dup ) ) {
			wp_send_json_error( array( 'message' => $dup->get_error_message() ) );
		}
		HS_Student_Profile::save( $user_id, $data );
		wp_send_json_success( array(
			'message' => 'پرونده دانش‌آموز ذخیره شد.',
			'profile' => HS_Student_Profile::get( $user_id ),
		) );
	}

	public static function transfer_student() {
		self::verify();
		$user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$from_class = isset( $_POST['from_class'] ) ? absint( $_POST['from_class'] ) : 0;
		$to_class   = isset( $_POST['to_class'] ) ? absint( $_POST['to_class'] ) : 0;

		if ( ! $user_id || ! $from_class || ! $to_class ) {
			wp_send_json_error( array( 'message' => 'اطلاعات ناقص است.' ) );
		}
		if ( ! HS_Roles::can_manage_class( $from_class ) || ! HS_Roles::can_manage_class( $to_class ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		if ( ! HS_Class_Students::is_member( $from_class, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'این دانش‌آموز عضو کلاس مبدأ نیست.' ) );
		}

		$result = HS_Student_Profile::transfer( $user_id, $from_class, $to_class );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => 'دانش‌آموز به کلاس جدید منتقل شد.' ) );
	}

	public static function export_class_students() {
		self::verify();
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		$rows = HS_Student_Profile::list_for_class( $class_id );
		$statuses = HS_Student_Profile::statuses();
		$csv = array();
		$csv[] = array( 'شماره', 'نام', 'نام خانوادگی', 'نام والد', 'تلفن والد', 'تاریخ تولد', 'وضعیت', 'یادداشت', 'کد اتصال والد' );
		foreach ( $rows as $r ) {
			$csv[] = array(
				$r['class_number'],
				$r['first_name'],
				$r['last_name'],
				$r['parent_name'],
				$r['parent_phone'],
				$r['birth_date'],
				isset( $statuses[ $r['status'] ] ) ? $statuses[ $r['status'] ] : $r['status'],
				$r['notes'],
				isset( $r['user_id'] ) ? HS_Parent_Link::get_code( $r['user_id'] ) : '',
			);
		}
		$class = get_post( $class_id );
		wp_send_json_success( array(
			'filename' => 'students-' . ( $class ? $class->post_name : $class_id ) . '.csv',
			'rows'     => $csv,
		) );
	}

	public static function remove_student() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $class_id || ! $user_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		HS_Class_Students::remove_student( $class_id, $user_id );
		$orphan = self::is_orphan_student( $user_id );
		if ( $orphan ) {
			// اجازه‌ی حذف کامل حساب فقط برای همین کاربر و تا ۱۰ دقیقه
			set_transient( 'hs_rm_' . get_current_user_id() . '_' . $user_id, $class_id, 10 * MINUTE_IN_SECONDS );
		}
		wp_send_json_success( array(
			'message' => 'دانش‌آموز از کلاس حذف شد.',
			'count'   => HS_Class_Students::count( $class_id ),
			'orphan'  => $orphan,
		) );
	}

	/** دانش‌آموزی که در هیچ کلاسی نیست، فقط نقش دانش‌آموز دارد و والدی به او متصل نیست */
	private static function is_orphan_student( $user_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u || array_diff( (array) $u->roles, array( 'student' ) ) ) {
			return false;
		}
		$classes = get_user_meta( $user_id, '_hs_classes', true );
		if ( is_array( $classes ) && array_filter( $classes ) ) {
			return false;
		}
		return ! ( class_exists( 'HS_Parent_Link' ) && HS_Parent_Link::get_parents( $user_id ) );
	}

	/** حذف کامل حساب دانش‌آموز (مثلاً ثبت تکراری) — فقط بلافاصله پس از حذف از کلاس توسط همان معلم */
	public static function delete_student_account() {
		self::verify();
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$key     = 'hs_rm_' . get_current_user_id() . '_' . $user_id;
		$class   = (int) get_transient( $key );
		if ( ! $user_id || ! $class || ! HS_Roles::can_manage_class( $class ) || ! self::is_orphan_student( $user_id ) ) {
			wp_send_json_error( array( 'message' => 'حذف حساب مجاز نیست (دانش‌آموز در کلاس دیگری است یا والد متصل دارد).' ) );
		}
		delete_transient( $key );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
		wp_send_json_success( array( 'message' => 'حساب دانش‌آموز کاملاً حذف شد.' ) );
	}

	public static function regenerate_code() {
		self::verify();

		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$code = HS_Class_Meta::generate_invite_code();
		update_post_meta( $class_id, '_hs_invite_code', $code );

		wp_send_json_success( array(
			'message' => 'کد دعوت جدید تولید شد.',
			'code'    => $code,
		) );
	}

	public static function regen_parent_code() {
		self::verify();
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $class_id || ! $user_id || ! HS_Roles::can_manage_class( $class_id ) || ! HS_Class_Students::is_member( $class_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		wp_send_json_success( array( 'code' => HS_Parent_Link::regen_code( $user_id ) ) );
	}

	public static function join_class() {
		// برای کاربران وارد شده
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'ابتدا وارد شوید یا ثبت‌نام کنید.' ) );
		}

		check_ajax_referer( 'hs_class_nonce', 'nonce' );

		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$result = HS_Class_Students::join_by_invite_code( $code );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'با موفقیت به کلاس پیوستید.' ) );
	}
}

HS_Frontend_Handlers::init();
