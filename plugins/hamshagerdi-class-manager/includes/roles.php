<?php
/**
 * مدیریت نقش‌های کاربری همشاگردی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Roles {

	/**
	 * ثبت نقش‌ها
	 */
	public static function register_roles() {
		// معلم
		add_role(
			'teacher',
			'معلم',
			array(
				'read'                   => true,
				'upload_files'           => true,
				'hs_manage_own_classes'  => true,
				'hs_view_class'          => true,
				'hs_add_students'        => true,
			)
		);

		// مدیر مدرسه
		add_role(
			'school_admin',
			'مدیر مدرسه',
			array(
				'read'                   => true,
				'upload_files'           => true,
				'hs_view_class'          => true,
				'hs_supervise'           => true, // ناظر بر کلاس‌های معلمان
				'hs_manage_own_classes'  => true, // تعریف کلاس‌های مدرسه
				'hs_add_students'        => true,
				'list_users'             => true,
			)
		);

		// دانش‌آموز
		add_role(
			'student',
			'دانش‌آموز',
			array(
				'read'          => true,
				'hs_view_class' => true,
			)
		);

		// والد
		add_role(
			'parent',
			'والد',
			array(
				'read'          => true,
				'hs_view_class' => true,
			)
		);

		// قابلیت‌های اضافی برای مدیر کل
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'hs_manage_all_classes' );
			$admin->add_cap( 'hs_manage_own_classes' );
			$admin->add_cap( 'hs_view_class' );
			$admin->add_cap( 'hs_add_students' );
		}
	}

	/** حذف دسترسی اضافه (edit_posts) از نقش‌های قدیمی */
	public static function migrate_caps() {
		foreach ( array( 'teacher', 'school_admin' ) as $r ) {
			$role = get_role( $r );
			if ( $role && $role->has_cap( 'edit_posts' ) ) {
				$role->remove_cap( 'edit_posts' );
			}
		}
		// مدیر مدرسه بر کلاس‌های دیگران ناظر است، ولی کلاس‌های خودِ مدرسه را تعریف و مدیریت می‌کند (از ۱.۷)
		$mgr = get_role( 'school_admin' );
		if ( $mgr ) {
			$mgr->add_cap( 'hs_manage_own_classes' );
			$mgr->add_cap( 'hs_add_students' );
			foreach ( array( 'hs_manage_all_classes' ) as $cap ) {
				if ( $mgr->has_cap( $cap ) ) {
					$mgr->remove_cap( $cap );
				}
			}
			$mgr->add_cap( 'hs_supervise' );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'hs_supervise' );
		}
	}

	/** نقش‌های همشاگردی بدون هیچ نقش دیگر وردپرسی؟ */
	public static function is_panel_only_user( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		$roles = (array) $user->roles;
		return $roles && ! array_diff( $roles, array( 'teacher', 'school_admin', 'student', 'parent' ) );
	}

	/** مخفی‌کردن نوار مدیریت و بستن wp-admin برای معلم/دانش‌آموز/والد */
	public static function init_guards() {
		add_filter( 'show_admin_bar', function ( $show ) {
			return self::is_panel_only_user() ? false : $show;
		} );
		add_action( 'admin_init', function () {
			if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
				return;
			}
			$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
			if ( 'admin-post.php' === $script ) {
				return;
			}
			if ( self::is_panel_only_user() && apply_filters( 'hs_block_wp_admin', true ) ) {
				wp_safe_redirect( home_url( '/panel/' ) );
				exit;
			}
		} );
	}

	/**
	 * بررسی اینکه کاربر معلم یا مدیر است
	 */
	public static function is_teacher_or_admin( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		$roles = (array) $user->roles;
		// مدیر مدرسه (school_admin) ناظر است و جزو معلمان نیست؛ اگر خودش هم تدریس می‌کند نقش «معلم» هم بگیرد
		return array_intersect( $roles, array( 'teacher', 'administrator' ) ) ? true : false;
	}

	/** ساخت و مدیریت کلاس: معلم، مدیر سایت، یا مدیر مدرسه (برای تعریف کلاس‌های مدرسه) */
	public static function can_create_class( $user_id = null ) {
		return self::is_teacher_or_admin( $user_id ) || self::is_school_manager( $user_id );
	}

	/** مدیر مدرسه‌ای که فقط ناظر است (نه معلم، نه مدیر سایت) */
	public static function is_school_manager( $user_id = null ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		$roles = (array) $user->roles;
		return in_array( 'school_admin', $roles, true ) && ! array_intersect( $roles, array( 'teacher', 'administrator' ) );
	}

	/**
	 * بررسی مالکیت کلاس
	 */
	public static function can_manage_class( $class_id, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( user_can( $user_id, 'hs_manage_all_classes' ) ) {
			return true;
		}

		$teacher_id = (int) get_post_meta( $class_id, '_hs_teacher_id', true );
		return $teacher_id === (int) $user_id && user_can( $user_id, 'hs_manage_own_classes' );
	}
}

HS_Roles::init_guards();
