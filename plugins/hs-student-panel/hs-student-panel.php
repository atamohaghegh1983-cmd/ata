<?php
/**
 * Plugin Name: پنل دانش‌آموز
 * Description: دنیای دانش‌آموز: برنامه‌ی من، کارنامه، تکالیف، کتابخانه‌ی کلاس، بازی‌های متناسب با پایه، جایزه‌ها، ورود ساده با کارت کلاس و مجوزهای قابل تنظیم. نیازمند «مدیریت کلاس» ۱.۵ به بالا.
 * Version:     1.1.0
 * Requires PHP: 7.4
 * Text Domain: hs-student-panel
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HSSP_VERSION', '1.1.0' );
define( 'HSSP_DB_VERSION', '1' );
define( 'HSSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'HSSP_URL', plugin_dir_url( __FILE__ ) );

function hssp_core_ready() {
	return class_exists( 'HS_Homework_DB' ) && class_exists( 'HS_Shell' ) && class_exists( 'HS_Parent_Link' );
}

add_action( 'plugins_loaded', function () {
	if ( ! hssp_core_ready() ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>«پنل دانش‌آموز» به افزونه‌ی «مدیریت کلاس» (نسخه‌ی ۱.۵.۰ یا بالاتر) نیاز دارد.</p></div>';
		} );
		return;
	}
	if ( class_exists( 'HS_Student_World_Frontend' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p>برای فعال شدن «پنل دانش‌آموز»، ابتدا «مدیریت کلاس» را به نسخه‌ی ۱.۵.۰ به‌روز کنید.</p></div>';
		} );
		return;
	}
	foreach ( array( 'db', 'util', 'perms', 'settings', 'login', 'library', 'world', 'teacher', 'access', 'integration' ) as $f ) {
		require_once HSSP_PATH . 'includes/' . $f . '.php';
	}
	HSSP_DB::maybe_upgrade();
}, 20 );

register_activation_hook( __FILE__, function () {
	require_once HSSP_PATH . 'includes/db.php';
	HSSP_DB::install();
	if ( ! class_exists( 'HSSP_Integration' ) ) {
		require_once HSSP_PATH . 'includes/integration.php';
	}
	HSSP_Integration::create_pages();
} );
