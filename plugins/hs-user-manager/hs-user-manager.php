<?php
/**
 * Plugin Name: کاربران و عضویت
 * Description: ورود و ثبت‌نام با موبایل و کد یک‌بار مصرف (پیامک از طریق پینوا)، انتخاب نقش، تأیید عضویت معلم و مدیر مدرسه، تغییر نقش با سابقه، تعلیق، اتصال والدین به دانش‌آموز با شماره موبایل، کد دعوت کلاس، معرفی معلم توسط مدیر مدرسه، درخواست اتصال والدین بدون کلاس، ورود گروهی دانش‌آموز و والدین از اکسل، نگاشت فیلد موبایل افزونه‌های دیگر.
 * Version:     2.6.2
 * Text Domain: hs-user-manager
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HSUM_VERSION', '2.6.2' );
define( 'HSUM_DB_VERSION', '2' );
define( 'HSUM_FILE', __FILE__ );
define( 'HSUM_PATH', plugin_dir_path( __FILE__ ) );
define( 'HSUM_URL', plugin_dir_url( __FILE__ ) );

// بخش ورود (لاگین). اگر افزونه‌ی قدیمی «لاگین» هنوز فعال است، آن بارگذاری شده و این بخش کنار می‌ماند.
if ( defined( 'HSMA_VERSION' ) ) {
	add_action( 'admin_notices', function () {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><b>کاربران و عضویت:</b> بخش ورود اکنون داخل همین افزونه است. لطفاً افزونه‌ی قدیمی «لاگین» (یا «ورود و پیامک») را <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">غیرفعال و حذف کنید</a>. اطلاعات کاربران و تنظیمات حفظ می‌شود.</p></div>';
		}
	} );
} else {
	require_once HSUM_PATH . 'login/login.php';
}

foreach ( array( 'db', 'audit', 'phone', 'membership', 'links', 'invites', 'requests', 'import', 'front', 'admin', 'onboard', 'staff' ) as $hsum_f ) {
	require_once HSUM_PATH . 'includes/class-' . $hsum_f . '.php';
}

register_activation_hook( __FILE__, function () {
	HSUM_DB::install();
	HSUM_Invites::rewrite();
	flush_rewrite_rules();
	HSUM_Front::create_page();
	if ( class_exists( 'HSMA_Front' ) ) {
		HSMA_Front::create_page();
	}
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', function () {
	HSUM_DB::maybe_upgrade();
	HSUM_Phone::init();
	HSUM_Membership::init();
	HSUM_Links::init();
	HSUM_Invites::init();
	HSUM_Requests::init();
	HSUM_Onboard::init();
	HSUM_Staff::init();
	HSUM_Import::init();
	HSUM_Front::init();
	HSUM_Admin::init();
} );
