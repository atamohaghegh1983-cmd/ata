<?php
/** اتصال به پنل: صفحه‌ها، منو، فایل‌ها، داشبورد، صفحه‌ی ورود مهمان */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_Integration {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_filter( 'hs_shell_menu', array( __CLASS__, 'menu' ), 16, 2 );
		add_filter( 'hs_guest_landing_roles', array( __CLASS__, 'guest_role' ), 20 );
		add_filter( 'hs_student_dashboard_html', array( __CLASS__, 'dashboard' ), 10, 2 );
	}

	/** slug => [عنوان, محتوا, زیر پنل؟] */
	public static function pages() {
		return array(
			'student'          => array( 'دنیای من', '[hamshagerdi_student_world]', true ),
			'student-settings' => array( 'پنل دانش‌آموزان', '[hs_student_settings]', true ),
			'student-access'   => array( 'دسترسی دانش‌آموزان', '[hs_student_access]', true ),
			'class-login'      => array( 'ورود دانش‌آموز', '[hs_kid_login]', false ),
		);
	}

	public static function page_url( $slug ) {
		$p = get_page_by_path( 'panel/' . $slug );
		if ( ! $p ) {
			$p = get_page_by_path( $slug );
		}
		if ( $p ) {
			return get_permalink( $p );
		}
		$def = self::pages()[ $slug ] ?? array( '', '', true );
		return home_url( ( $def[2] ? '/panel/' : '/' ) . $slug . '/' );
	}

	public static function create_pages() {
		$panel = get_page_by_path( 'panel' );
		foreach ( self::pages() as $slug => $p ) {
			if ( get_page_by_path( 'panel/' . $slug ) || get_page_by_path( $slug ) ) {
				continue;
			}
			wp_insert_post( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $p[0],
				'post_name'    => $slug,
				'post_parent'  => ( $p[2] && $panel ) ? $panel->ID : 0,
				'post_content' => $p[1],
			) );
		}
	}

	public static function assets() {
		$post = get_post();
		$sc   = function ( $tag ) use ( $post ) {
			return $post && has_shortcode( (string) $post->post_content, $tag );
		};
		$panel = class_exists( 'HS_Shell' ) && HS_Shell::is_panel_request();
		if ( ! $panel && ! $sc( 'hamshagerdi_student_world' ) && ! $sc( 'hs_kid_login' ) && ! $sc( 'hs_student_settings' ) && ! $sc( 'hs_student_access' ) ) {
			return;
		}
		wp_enqueue_style( 'hssp', HSSP_URL . 'assets/student.css', ( wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array() ), HSSP_VERSION );
		$deps = array( 'jquery' );
		if ( $sc( 'hs_student_settings' ) ) {
			wp_enqueue_script( 'hssp-qr', HSSP_URL . 'assets/vendor/qrcode.js', array(), '1.4.4', true );
			$deps[] = 'hssp-qr';
		}
		wp_enqueue_script( 'hssp', HSSP_URL . 'assets/student.js', $deps, HSSP_VERSION, true );
		wp_localize_script( 'hssp', 'hsStudent', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => is_user_logged_in() ? wp_create_nonce( 'hs_class_nonce' ) : '',
		) );
	}

	public static function menu( $menu, $type ) {
		if ( ! is_array( $menu ) ) {
			return $menu;
		}
		$add = array();
		if ( 'teacher' === $type ) {
			$add = array( 'مدیریت کلاس', array( 'student-settings', 'پنل دانش‌آموزان', '🎒', 'violet' ) );
		} elseif ( 'manager' === $type ) {
			$add = array( 'مدیریت مدرسه', array( 'student-access', 'دسترسی دانش‌آموزان', '🎒', 'teal' ) );
		}
		if ( $add ) {
			foreach ( $menu as $i => $g ) {
				if ( $add[0] === $g[0] ) {
					$menu[ $i ][1][] = $add[1];
					return $menu;
				}
			}
			$menu[] = array( $add[0], array( $add[1] ) );
		}
		return $menu;
	}

	public static function guest_role( $roles ) {
		if ( ! isset( $roles['student'] ) ) {
			return $roles;
		}
		$roles['student']['lead']  = 'تکلیف‌ها، برنامه، کارنامه، کتابخانه و بازی‌های کلاست را ببین.';
		$roles['student']['extra'] = '<a class="hssp-guest-card" href="' . esc_url( self::page_url( 'class-login' ) ) . '">🎫 <strong>کارت کلاس داری؟</strong> با کد کلاس و رمز ۴ رقمی وارد شو ←</a>';
		$roles['student']['items'] = array(
			array( '🗓️', 'برنامه‌ی من', 'تکلیف‌ها و برنامه‌های دو هفته‌ی آینده در یک نگاه' ),
			array( '📚', 'تکالیف', 'دیدن تکلیف، ارسال عکس دفتر و تکلیف آنلاین' ),
			array( '📊', 'کارنامه‌ی من', 'حضور، تکلیف‌ها و ارزیابی‌های هر ماه — بدون مقایسه با دیگران' ),
			array( '📖', 'کتابخانه‌ی کلاس', 'داستان و متن‌هایی که معلم گذاشته' ),
			array( '🎮', 'بازی‌های آموزشی', 'متناسب با پایه‌ی تو، از حروف الفبا تا جدول ضرب و کسرها' ),
			array( '⭐', 'امتیاز و نشان', 'امتیازهایی که گرفته‌ای و نشان‌های بعدی' ),
		);
		return $roles;
	}

	/** کارت «کارهای من» در داشبورد اصلی دانش‌آموز */
	public static function dashboard( $html, $uid ) {
		$open = array();
		foreach ( HSSP_Util::student_classes( $uid ) as $c ) {
			$open = array_merge( $open, HSSP_World::open_homework( $uid, $c ) );
		}
		if ( ! $open ) {
			return $html;
		}
		$card = '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>📋 کارهای من</h3></div><ul class="hs-list">';
		foreach ( array_slice( $open, 0, 4 ) as $hw ) {
			$card .= '<li><span class="hs-av">📘</span><div><strong>' . esc_html( $hw['title'] ) . '</strong><small>' . esc_html( trim( $hw['subject'] . ( $hw['due_date'] ? ' · تا ' . HSSP_Util::d( $hw['due_date'], false ) : '' ), ' ·' ) ) . '</small></div></li>';
		}
		$card .= '</ul><a class="hs-btn hs-btn-primary" href="' . esc_url( add_query_arg( 'sw', 'plan', self::page_url( 'student' ) ) ) . '">🗓️ برنامه‌ی من</a></section>';
		return $card . $html;
	}
}

HSSP_Integration::init();
