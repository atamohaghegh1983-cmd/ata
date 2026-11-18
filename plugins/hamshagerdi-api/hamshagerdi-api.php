<?php
/**
 * Plugin Name: API موبایل (JWT)
 * Description: REST API امن با توکن (JWT + Refresh Token) برای اپ موبایل؛ ورود با پیامک، تکمیل ثبت‌نام، کلاس‌ها، حضور و غیاب، امتیاز، تکالیف، اطلاعیه‌ها، پیام‌ها، والدین، مجله و مدرسه.
 * Version:     2.5.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HS_API_VERSION', '2.5.0' );
define( 'HS_API_NS', 'hamshagerdi/v1' );
define( 'HS_API_DIR', plugin_dir_path( __FILE__ ) );

require_once HS_API_DIR . 'includes/class-util.php';
require_once HS_API_DIR . 'includes/class-jwt.php';
require_once HS_API_DIR . 'includes/class-auth.php';
require_once HS_API_DIR . 'includes/class-otp.php';
require_once HS_API_DIR . 'includes/class-devices.php';
require_once HS_API_DIR . 'includes/class-access.php';
require_once HS_API_DIR . 'includes/class-routes.php';
require_once HS_API_DIR . 'includes/class-routes-parent.php';
require_once HS_API_DIR . 'includes/class-routes-articles.php';
require_once HS_API_DIR . 'includes/class-routes-dashboard.php';
require_once HS_API_DIR . 'includes/class-routes-more.php';
require_once HS_API_DIR . 'includes/class-routes-teach.php';
require_once HS_API_DIR . 'includes/class-notify.php';

register_activation_hook( __FILE__, function () { HS_API_Auth::ensure_secret(); } );

add_action( 'plugins_loaded', function () {
	HS_API_Auth::init();
	HS_API_Notify::init();
	add_action( 'rest_api_init', array( 'HS_API_Routes', 'register' ) );
} );

/** هشدار در پیشخوان اگر افزونه‌های لازم فعال نباشند */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'activate_plugins' ) ) { return; }
	$need = array();
	if ( ! class_exists( 'HS_Roles' ) ) { $need[] = 'مدیریت کلاس (۱.۷.۳ یا بالاتر)'; }
	if ( ! class_exists( 'HSMA_OTP' ) ) { $need[] = 'کاربران و عضویت (۲.۶.۳ یا بالاتر)'; }
	if ( $need ) {
		echo '<div class="notice notice-warning"><p>API موبایل: این افزونه‌ها باید فعال باشند: ' . esc_html( implode( '، ', $need ) ) . '</p></div>';
	}
} );
