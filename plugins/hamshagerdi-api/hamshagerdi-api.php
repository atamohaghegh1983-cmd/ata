<?php
/**
 * Plugin Name: API موبایل (JWT)
 * Description: REST API امن با توکن (JWT + Refresh Token) برای اپ بومی؛ ورود، کلاس‌ها، حضور و غیاب، تکالیف، اطلاعیه‌ها، پیام‌ها، والدین و مدرسه.
 * Version:     1.0.2
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HS_API_VERSION', '1.0.2' );
define( 'HS_API_NS', 'hamshagerdi/v1' );
define( 'HS_API_DIR', plugin_dir_path( __FILE__ ) );

require_once HS_API_DIR . 'includes/class-jwt.php';
require_once HS_API_DIR . 'includes/class-auth.php';
require_once HS_API_DIR . 'includes/class-access.php';
require_once HS_API_DIR . 'includes/class-routes.php';

register_activation_hook( __FILE__, function () { HS_API_Auth::ensure_secret(); } );

add_action( 'plugins_loaded', function () {
	HS_API_Auth::init();
	add_action( 'rest_api_init', array( 'HS_API_Routes', 'register' ) );
} );
