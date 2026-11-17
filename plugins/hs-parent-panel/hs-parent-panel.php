<?php
/**
 * Plugin Name: پنل والدین
 * Description: داشبورد والدین با کارت هر فرزند، حضور و غیاب، تکالیف، پیشرفت، اطلاعیه‌ها (با «دیده شد»)، پیام به معلم، گروه والدین هر کلاس با ناظر و دستیار، نظرسنجی، و مجوزهای قابل تنظیم برای هر بخش. والدین اطلاعات خود و فرزندشان را می‌بینند و ویرایش می‌کنند.
 * Version:     1.1.3
 * Text Domain: hs-parent-panel
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HSPP_VERSION', '1.1.3' );
define( 'HSPP_DB_VERSION', '2' );
define( 'HSPP_FILE', __FILE__ );
define( 'HSPP_PATH', plugin_dir_path( __FILE__ ) );
define( 'HSPP_URL', plugin_dir_url( __FILE__ ) );

/** این افزونه به داده‌های «مدیریت کلاس» (کلاس، حضور، تکلیف، ارزیابی، اطلاعیه) نیاز دارد */
function hspp_core_ready() {
	return class_exists( 'HS_Parent_Link' ) && class_exists( 'HS_Roles' );
}

add_action( 'plugins_loaded', function () {
	if ( ! hspp_core_ready() ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>پنل والدین:</strong> افزونه‌ی «مدیریت کلاس» (نسخه ۱.۴ یا بالاتر) باید فعال باشد.</p></div>';
		} );
		return;
	}
	if ( class_exists( 'HS_Parent_Panel_Frontend' ) || class_exists( 'HS_Parent_Chat_DB' ) ) {
		// نسخه‌ی قدیمی «مدیریت کلاس» هنوز بخش والدین را دارد
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p><strong>پنل والدین:</strong> ابتدا «مدیریت کلاس» را به نسخه ۱.۴.۰ به‌روز کنید؛ تا آن موقع بخش والدین قدیمی کار می‌کند.</p></div>';
		} );
		return;
	}
	foreach ( array( 'db', 'perms', 'names', 'chat-db', 'chat-handlers', 'chat-frontend', 'group', 'profile', 'reads', 'util', 'progress', 'meetings', 'calendar', 'practice', 'digest', 'panel', 'access', 'integration' ) as $f ) {
		require_once HSPP_PATH . 'includes/' . $f . '.php';
	}
	HSPP_DB::maybe_upgrade();
}, 20 );

register_activation_hook( __FILE__, function () {
	require_once HSPP_PATH . 'includes/db.php';
	if ( ! class_exists( 'HS_Parent_Chat_DB' ) ) {
		require_once HSPP_PATH . 'includes/chat-db.php';
	}
	HSPP_DB::install();
	if ( ! class_exists( 'HSPP_Integration' ) ) {
		require_once HSPP_PATH . 'includes/integration.php';
	}
	HSPP_Integration::create_pages();
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'hspp_digest_cron' );
} );
