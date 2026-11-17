<?php
/**
 * قالب داشبوردی پنل (سایدبار + نوار بالا) برای همه صفحات /panel/*
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Shell {

	const PANEL_SLUGS = array(
		'panel', 'attendance', 'classes', 'points', 'messages', 'assessment', 'reports', 'homework', 'portfolio',
		'tools', 'worksheets', 'online-builder', 'online-hw', 'notebook', 'content-studio', 'student', 'parent',
		'parent-chat', 'calendar', 'budget', 'weekly',
	);

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'strip_theme_styles' ), 1000 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_ajax_hs_change_password', array( __CLASS__, 'ajax_change_password' ) );
		add_action( 'template_redirect', array( __CLASS__, 'guard' ), 5 );
	}

	/** نامک‌های منوی یک نقش (بعد از افزوده‌های افزونه‌های دیگر) */
	private static function slugs_of( $menu ) {
		$out = array();
		foreach ( (array) $menu as $grp ) {
			foreach ( (array) ( $grp[1] ?? array() ) as $it ) {
				if ( ! empty( $it[0] ) ) {
					$out[] = (string) $it[0];
				}
			}
		}
		return array_unique( $out );
	}

	/**
	 * هر نقش فقط صفحه‌های خودش: صفحه‌ای که مخصوص نقش دیگری است (مثلاً «حضور و غیاب» برای والدین
	 * یا «ساختار مدرسه» برای معلم) به داشبورد خود کاربر برمی‌گردد.
	 */
	public static function guard() {
		if ( ! is_user_logged_in() || ! self::is_panel_request() || current_user_can( 'manage_options' ) ) {
			return;
		}
		$post = get_queried_object();
		$slug = $post instanceof WP_Post ? $post->post_name : '';
		if ( ! $slug || 'panel' === $slug ) {
			return;
		}
		$type = self::view_type();
		$mine = self::slugs_of( self::menu( $type ) );
		if ( in_array( $slug, $mine, true ) ) {
			return;
		}
		$others = array();
		foreach ( array( 'teacher', 'manager', 'parent', 'other' ) as $t ) {
			if ( $t !== $type ) {
				$others = array_merge( $others, self::slugs_of( apply_filters( 'hs_shell_menu', self::menu_base( $t ), $t ) ) );
			}
		}
		if ( in_array( $slug, $others, true ) ) {
			wp_safe_redirect( add_query_arg( 'hs_denied', 1, home_url( '/panel/' ) ) );
			exit;
		}
	}

	/** آیا صفحه فعلی بخشی از پنل است؟ */
	public static function is_panel_request() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = false;
		if ( ! is_page() ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		if ( in_array( $post->post_name, self::PANEL_SLUGS, true ) && ( ! $post->post_parent || 'panel' === get_post_field( 'post_name', $post->post_parent ) ) ) {
			$cache = true;
		} elseif ( $post->post_parent && 'panel' === get_post_field( 'post_name', $post->post_parent ) ) {
			$cache = true;
		}
		return $cache;
	}

	private static function active() {
		return HS_Settings::shell_enabled() && self::is_panel_request();
	}

	public static function template( $tpl ) {
		if ( self::active() ) {
			return HS_CLASS_PATH . 'templates/shell.php';
		}
		return $tpl;
	}

	public static function body_class( $classes ) {
		if ( self::active() ) {
			$classes[] = 'hs-shell-body';
		}
		return $classes;
	}

	public static function enqueue() {
		$p = HS_Settings::palette();
		// پالت رنگی روی همهٔ صفحات پنل (حتی با چیدمان قالب) اعمال می‌شود
		if ( ! self::is_panel_request() && ! is_singular( 'hs_class' ) ) {
			return;
		}
		wp_enqueue_style( 'hs-theme', HS_CLASS_URL . 'assets/css/hs-theme.css', array( 'hs-core' ), HS_CLASS_VERSION );
		wp_add_inline_style( 'hs-theme', sprintf(
			':root{--hs-primary:%1$s;--hs-primary-2:%2$s;--hs-ink:%3$s;--hs-primary-rgb:%4$s;}',
			$p['p'], $p['s'], $p['ink'], $p['rgb']
		) );
		if ( self::active() ) {
			wp_enqueue_script( 'hs-shell', HS_CLASS_URL . 'assets/js/hs-shell.js', array( 'jquery' ), HS_CLASS_VERSION, true );
			wp_localize_script( 'hs-shell', 'hsShell', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hs_shell' ),
			) );
		}
	}

	/** حذف CSS قالب (فقط روی صفحات پنل) برای جلوگیری از تداخل */
	public static function strip_theme_styles() {
		if ( ! self::active() || 'no' === get_option( HS_Settings::OPT_STRIP, 'yes' ) ) {
			return;
		}
		$styles = wp_styles();
		$roots  = array_filter( array( get_stylesheet_directory_uri(), get_template_directory_uri() ) );
		foreach ( (array) $styles->queue as $handle ) {
			$src = isset( $styles->registered[ $handle ] ) ? (string) $styles->registered[ $handle ]->src : '';
			foreach ( $roots as $root ) {
				if ( $src && 0 === strpos( $src, $root ) ) {
					wp_dequeue_style( $handle );
					break;
				}
			}
		}
	}

	public static function role_label( $user ) {
		$roles = (array) $user->roles;
		if ( in_array( 'administrator', $roles, true ) ) {
			return 'مدیر سایت';
		}
		if ( in_array( 'school_admin', $roles, true ) ) {
			return 'مدیر مدرسه';
		}
		if ( in_array( 'teacher', $roles, true ) ) {
			return 'معلم';
		}
		if ( in_array( 'parent', $roles, true ) ) {
			return 'والد';
		}
		if ( in_array( 'student', $roles, true ) ) {
			return 'دانش‌آموز';
		}
		return 'کاربر';
	}

	/** نوع نمای کاربر: teacher | manager | parent | student | other */
	public static function view_type( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( HS_Roles::is_teacher_or_admin( $user_id ) ) {
			return 'teacher';
		}
		if ( HS_Roles::is_school_manager( $user_id ) ) {
			return 'manager';
		}
		$u     = get_userdata( $user_id );
		$roles = $u ? (array) $u->roles : array();
		if ( in_array( 'parent', $roles, true ) || ( class_exists( 'HS_Parent_Link' ) && HS_Parent_Link::get_children( $user_id ) ) ) {
			return 'parent';
		}
		if ( in_array( 'student', $roles, true ) ) {
			return 'student';
		}
		return 'other';
	}

	/** ساختار منو بر اساس نقش */
	public static function menu( $type ) {
		$menu = self::menu_base( $type );
		if ( 'teacher' === $type && shortcode_exists( 'hamshagerdi_weekly_plan' ) ) {
			foreach ( $menu as $i => $grp ) {
				if ( 'مدیریت کلاس' === $grp[0] ) {
					$menu[ $i ][1][] = array( 'weekly', 'برنامه هفتگی', '🗓️', 'teal' );
				}
			}
		}
		if ( 'teacher' === $type ) {
			foreach ( $menu as $i => $grp ) {
				if ( 'مدیریت کلاس' === $grp[0] ) {
					$menu[ $i ][1][] = array( 'calendar', 'تقویم مناسبتی', '📅', 'orange' );
				}
			}
		}
		if ( 'teacher' === $type ) {
			$menu = HS_Hubs::apply_to_menu( $menu );
		}
		/**
		 * افزونه‌ها (مثل «ساختار مدیریت مدرسه») می‌توانند منو را گسترش دهند.
		 * هر گروه: array( عنوان گروه, array( array( slug, برچسب, آیکن, رنگ[, 'unread'] ) ) )
		 */
		return apply_filters( 'hs_shell_menu', $menu, $type );
	}

	private static function menu_base( $type ) {
		$root = array( array( 'panel', 'داشبورد', '🏠', 'violet' ) );
		switch ( $type ) {
			case 'teacher':
				return array(
					array( '', $root ),
					array( 'مدیریت کلاس', array(
						array( 'classes', 'کلاس‌ها', '🏫', 'blue' ),
						array( 'attendance', 'حضور و غیاب', '✅', 'green' ),
						array( 'points', 'تشویق و امتیاز', '⭐', 'orange' ),
						array( 'assessment', 'ارزیابی و نمرات', '📝', 'pink' ),
						array( 'reports', 'گزارش پیشرفت', '📈', 'teal' ),
						array( 'budget', 'بودجه‌بندی کلاس', '📐', 'violet' ),
					) ),
					array( 'تکلیف و محتوا', array(
						array( 'homework', 'تکالیف', '📚', 'blue' ),
						array( 'online-builder', 'تکلیف‌ساز آنلاین', '🧪', 'violet' ),
						array( 'notebook', 'دفتر تکالیف', '📓', 'orange' ),
						array( 'worksheets', 'کتابخانه کاربرگ', '🗂️', 'green' ),
						array( 'content-studio', 'استودیو محتوا', '🎬', 'pink' ),
						array( 'worksheet-dashboard', 'داشبورد کاربرگ', '📚', 'violet' ),
						array( 'portfolio', 'پورتفولیو', '🎨', 'teal' ),
					) ),
					array( 'ارتباط', array(
						array( 'messages', 'ارتباط با والدین', '💬', 'pink', 'unread' ),
						array( 'calendar', 'تقویم مناسبتی', '📅', 'orange' ),
					) ),
					array( 'ابزارها', array(
						array( 'tools', 'ابزارهای کلاس', '🧰', 'green' ),
					) ),
				);
			case 'manager':
				return array(
					array( '', $root ),
					array( 'مدیریت مدرسه', array(
						array( 'classes', 'کلاس‌های مدرسه', '🏫', 'blue' ),
						array( 'calendar', 'تقویم مناسبتی', '📅', 'orange' ),
					) ),
				);
			case 'parent':
				return array(
					array( '', $root ),
					// «پنل والدین» و «گروه والدین» را افزونه‌ی «پنل والدین» با فیلتر hs_shell_menu اضافه می‌کند
					array( 'فرزندان من', array(
						array( 'calendar', 'تقویم مناسبتی', '📅', 'orange' ),
					) ),
				);
			default:
				return array(
					array( '', $root ),
					array( 'یادگیری', array(
						array( 'student', 'دنیای من', '🎮', 'violet' ),
						array( 'online-hw', 'تکلیف آنلاین', '📝', 'pink' ),
						array( 'calendar', 'تقویم مناسبتی', '📅', 'orange' ),
					) ),
				);
		}
	}

	public static function url( $slug ) {
		return 'panel' === $slug ? home_url( '/panel/' ) : home_url( '/panel/' . $slug . '/' );
	}

	public static function unread_count( $user_id ) {
		return class_exists( 'HS_Comms_DB' ) ? (int) HS_Comms_DB::count_unread( $user_id ) : 0;
	}

	public static function ajax_change_password() {
		if ( ! check_ajax_referer( 'hs_shell', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست نامعتبر. صفحه را رفرش کنید.' ) );
		}
		$pass    = isset( $_POST['pass'] ) ? (string) wp_unslash( $_POST['pass'] ) : '';
		$confirm = isset( $_POST['confirm'] ) ? (string) wp_unslash( $_POST['confirm'] ) : '';
		if ( strlen( $pass ) < 8 ) {
			wp_send_json_error( array( 'message' => 'رمز باید حداقل ۸ کاراکتر باشد.' ) );
		}
		if ( $pass !== $confirm ) {
			wp_send_json_error( array( 'message' => 'تکرار رمز با رمز جدید یکسان نیست.' ) );
		}
		$uid = get_current_user_id();
		wp_set_password( $pass, $uid );
		delete_user_meta( $uid, '_hs_temp_pass' );
		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, true );
		wp_send_json_success( array( 'message' => 'رمز عبور تغییر کرد.' ) );
	}
}

HS_Shell::init();
