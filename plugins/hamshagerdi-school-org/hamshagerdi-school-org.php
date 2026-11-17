<?php
/**
 * Plugin Name: ساختار مدیریت مدرسه
 * Description: معرفی مدرسه توسط مدیر، معاونین و شرح وظایف، واگذاری وظایف و نظارت (فقط‌خواندنی) بر کلاس‌ها؛ یکپارچه با پنل «مدیریت کلاس».
 * Version:     2.2.0
 * Text Domain: hamshagerdi-school-org
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HS_SO_VERSION', '2.2.0' );
define( 'HS_SO_FILE', __FILE__ );
define( 'HS_SO_PATH', plugin_dir_path( __FILE__ ) );
define( 'HS_SO_URL', plugin_dir_url( __FILE__ ) );

require_once HS_SO_PATH . 'includes/class-structure.php';
require_once HS_SO_PATH . 'includes/class-store.php';
require_once HS_SO_PATH . 'includes/class-monitor.php';
require_once HS_SO_PATH . 'includes/class-ajax.php';
require_once HS_SO_PATH . 'includes/class-frontend.php';
require_once HS_SO_PATH . 'includes/class-admin.php';
require_once HS_SO_PATH . 'includes/class-guest.php';

function hs_so_activate() {
	HS_SO_Store::register_cpt();
	HS_SO_Store::create_tables();
	update_option( 'hs_so_db_version', HS_SO_VERSION );
}
register_activation_hook( __FILE__, 'hs_so_activate' );

/** ارتقای خودکار + ساخت صفحهٔ /panel/school/ (روی init تا وردپرس آماده باشد) */
function hs_so_maybe_upgrade() {
	if ( wp_doing_cron() || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
		return;
	}
	if ( HS_SO_VERSION === get_option( 'hs_so_db_version' ) && get_option( 'hs_so_page_id' ) ) {
		return;
	}
	HS_SO_Store::create_tables();
	HS_SO_Frontend::ensure_page();
	update_option( 'hs_so_db_version', HS_SO_VERSION );
}

add_action( 'init', array( 'HS_SO_Store', 'register_cpt' ) );
add_action( 'init', 'hs_so_maybe_upgrade', 99 );
add_action( 'plugins_loaded', function () {
	HS_SO_Ajax::init();
	HS_SO_Frontend::init();
	HS_SO_Admin::init();
} );
