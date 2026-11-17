<?php
/**
 * Plugin Name: مدیریت کلاس
 * Description: مدیریت کلاس و پرونده دانش‌آموز، حضور، امتیاز، ارتباط، ارزیابی، گزارش، تکالیف، پورتفولیو و ابزارها
 * Version:     1.7.2
 * Text Domain: hamshagerdi-class
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HS_CLASS_VERSION', '1.7.2' );
define( 'HS_CLASS_FILE', __FILE__ );
define( 'HS_CLASS_PATH', plugin_dir_path( __FILE__ ) );
define( 'HS_CLASS_URL', plugin_dir_url( __FILE__ ) );

$hs_includes = array(
	'helpers.php', 'settings.php',
	'roles.php', 'class-post-type.php', 'class-meta.php', 'class-students.php',
	'student-profile.php', 'student-sheet.php', 'uploads.php', 'student-import.php',
	'frontend-handlers.php', 'frontend-panel.php',
	'attendance-db.php', 'attendance-handlers.php', 'attendance-frontend.php',
	'points-db.php', 'points-handlers.php', 'points-frontend.php',
	'comms-db.php', 'comms-handlers.php', 'comms-frontend.php',
	'assessment-db.php', 'assessment-handlers.php', 'assessment-frontend.php',
	'reports-db.php', 'reports-handlers.php', 'reports-frontend.php',
	'homework-db.php', 'homework-handlers.php', 'homework-frontend.php',
	'portfolio-db.php', 'portfolio-handlers.php', 'portfolio-frontend.php',
	'tools-frontend.php',
	'worksheet-cpt.php',
	'worksheet-frontend.php',
	'worksheet-dashboard.php',
	'online-hw-db.php',
	'online-hw-handlers.php',
	'online-hw-frontend.php',
	'content-studio-db.php',
	'content-extract.php',
	'content-studio-handlers.php',
	'content-studio-frontend.php',
	'parent-link.php',
	'bale-api.php',
	'bale-bridge.php',
	'calendar-db.php',
	'calendar-reminders.php',
	'calendar-frontend.php',
	'budget.php', 'avatar.php',
	'hubs.php', 'shell.php', 'guest-bg.php', 'guest-landing.php', 'dashboard.php',
	'setup-pages.php',
);
foreach ( $hs_includes as $file ) {
	require_once HS_CLASS_PATH . 'includes/' . $file;
}

/**
 * ساخت/به‌روزرسانی همه جدول‌ها (dbDelta امن و تکرارپذیر است)
 */
function hs_class_install_tables() {
	HS_Attendance_DB::create_table();
	HS_Points_DB::create_tables();
	HS_Comms_DB::create_tables();
	HS_Assessment_DB::create_tables();
	HS_Homework_DB::create_tables();
	HS_Portfolio_DB::create_table();
	if ( class_exists( 'HS_Online_HW_DB' ) ) {
		HS_Online_HW_DB::create_tables();
	}
	if ( class_exists( 'HS_Content_Studio_DB' ) ) {
		HS_Content_Studio_DB::create_table();
	}
	if ( class_exists( 'HS_Parent_Chat_DB' ) ) {
		HS_Parent_Chat_DB::create_table();
	}
	if ( class_exists( 'HS_Calendar_DB' ) ) {
		HS_Calendar_DB::create_table();
	}
}

function hs_class_activate() {
	HS_Roles::register_roles();
	HS_Roles::migrate_caps();
	HS_Class_Post_Type::register();
	hs_class_install_tables();
	if ( class_exists( 'HS_Worksheet_CPT' ) ) {
		HS_Worksheet_CPT::register();
	}
	if ( class_exists( 'HS_Setup_Pages' ) ) {
		HS_Setup_Pages::create_all();
	}
	if ( class_exists( 'HS_Calendar_Reminders' ) ) {
		HS_Calendar_Reminders::schedule();
	}
	flush_rewrite_rules();
	update_option( 'hs_class_db_version', HS_CLASS_VERSION );
}
register_activation_hook( __FILE__, 'hs_class_activate' );

/**
 * ارتقای خودکار: بعد از آپدیت افزونه (بدون نیاز به غیرفعال/فعال‌سازی) جدول‌ها و دسترسی‌ها به‌روز می‌شوند.
 */
function hs_class_maybe_upgrade() {
	if ( wp_doing_cron() || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
		return;
	}
	$db_ver = get_option( 'hs_class_db_version', '0' );
	if ( HS_CLASS_VERSION === $db_ver ) {
		return;
	}
	hs_class_install_tables();
	HS_Roles::migrate_caps();
	if ( class_exists( 'HS_Setup_Pages' ) ) {
		HS_Setup_Pages::create_all(); // صفحه‌های تازه (مثل بودجه‌بندی)
	}
	update_option( 'hs_class_db_version', HS_CLASS_VERSION );
}
// مهم: روی init اجرا شود (نه plugins_loaded)؛ تا وردپرس، نوع‌نوشته‌ها و پیوندهای یکتا آماده باشند.
add_action( 'init', 'hs_class_maybe_upgrade', 99 );

function hs_class_load_textdomain() {
	load_plugin_textdomain( 'hamshagerdi-class', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'hs_class_load_textdomain' );

function hs_class_deactivate() {
	if ( class_exists( 'HS_Calendar_Reminders' ) ) {
		HS_Calendar_Reminders::unschedule();
	}
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'hs_class_deactivate' );

function hs_class_enqueue_assets() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	global $post;
	$codes = array(
		'hamshagerdi_classes', 'hamshagerdi_class_detail', 'hamshagerdi_attendance',
		'hamshagerdi_points', 'hamshagerdi_comms', 'hamshagerdi_announcements',
		'hamshagerdi_messages', 'hamshagerdi_assessment', 'hamshagerdi_reports',
		'hamshagerdi_homework', 'hamshagerdi_portfolio', 'hamshagerdi_tools', 'hamshagerdi_worksheets', 'hamshagerdi_online_hw', 'hamshagerdi_online_builder', 'hamshagerdi_notebook', 'hamshagerdi_content_studio', 'hamshagerdi_student_world', 'hamshagerdi_parent_panel', 'hamshagerdi_parent_chat', 'hamshagerdi_calendar', 'hamshagerdi_bale_link', 'hamshagerdi_join_class',
	);
	$has_shortcode = false;
	if ( is_a( $post, 'WP_Post' ) ) {
		foreach ( $codes as $code ) {
			if ( has_shortcode( $post->post_content, $code ) ) {
				$has_shortcode = true;
				break;
			}
		}
	}
	$pages = array( 'panel', 'attendance', 'classes', 'points', 'messages', 'assessment', 'reports', 'homework', 'portfolio', 'tools', 'worksheets', 'online-builder', 'online-hw', 'notebook', 'content-studio', 'student', 'parent', 'parent-chat', 'calendar' );
	$is_panel = is_page( $pages ) || $has_shortcode;
	if ( ! $is_panel && is_page() && $post ) {
		$parent = $post->post_parent ? get_post( $post->post_parent ) : null;
		if ( $parent && $parent->post_name === 'panel' ) {
			$is_panel = true;
		}
	}
	if ( ! $is_panel && ! is_singular( 'hs_class' ) ) {
		return;
	}
	wp_enqueue_style( 'hs-class-panel', HS_CLASS_URL . 'assets/css/panel-classes.css', array( 'hs-core' ), HS_CLASS_VERSION );
	wp_enqueue_script( 'hs-qrcode', HS_CLASS_URL . 'assets/js/qrcode.js', array(), '1.4.4', true );
	wp_enqueue_script( 'hs-class-panel', HS_CLASS_URL . 'assets/js/panel-classes.js', array( 'jquery', 'hs-qrcode' ), HS_CLASS_VERSION, true );
	wp_enqueue_script( 'hs-imgpick', HS_CLASS_URL . 'assets/js/hs-imgpick.js', array( 'jquery' ), HS_CLASS_VERSION, true );
	wp_localize_script( 'hs-imgpick', 'hsImg', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'hs_class_nonce' ),
	) );
	wp_localize_script( 'hs-class-panel', 'hsClass', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'hs_class_nonce' ),
		'i18n'    => array(
			'confirmDelete' => 'آیا از حذف این کلاس مطمئن هستید؟ این عمل غیرقابل بازگشت است.',
			'confirmRemove' => 'این دانش‌آموز از کلاس حذف شود؟',
			'saving'        => 'در حال ذخیره...',
			'saved'         => 'ذخیره شد',
			'error'         => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.',
			'copySuccess'   => 'کد دعوت کپی شد',
			'enterName'     => 'لطفاً نام دانش‌آموز را وارد کنید',
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'hs_class_enqueue_assets' );

function hs_class_admin_assets( $hook ) {
	global $post_type;
	if ( 'hs_class' === $post_type || 'post.php' === $hook || 'post-new.php' === $hook ) {
		wp_enqueue_style( 'hs-class-admin', HS_CLASS_URL . 'assets/css/panel-classes.css', array( 'hs-core' ), HS_CLASS_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'hs_class_admin_assets' );

/**
 * هسته‌ی طراحی مشترک همشاگردی (فونت لاله‌زار + وزیرمتن و توکن‌های رنگ).
 * زودتر از همه ثبت می‌شود تا افزونه‌های دیگر فقط به 'hs-core' وابسته شوند
 * و فونت‌ها فقط یک بار دانلود شوند.
 */
function hs_core_register_style() {
	if ( ! wp_style_is( 'hs-core', 'registered' ) ) {
		wp_register_style( 'hs-core', HS_CLASS_URL . 'assets/css/hs-core.css', array(), HS_CLASS_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'hs_core_register_style', 1 );
add_action( 'admin_enqueue_scripts', 'hs_core_register_style', 1 );
add_action( 'login_enqueue_scripts', 'hs_core_register_style', 1 );
