<?php
/**
 * بخش ورود و ثبت‌نام (قبلاً افزونه‌ی جدای «لاگین» / «ورود و پیامک»).
 * پیامک‌ها از طریق افزونه‌ی پینوا ارسال می‌شوند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HSMA_VERSION', '4.0.0' );
define( 'HSMA_FILE', defined( 'HSUM_FILE' ) ? HSUM_FILE : __FILE__ );
define( 'HSMA_PATH', plugin_dir_path( __FILE__ ) );
define( 'HSMA_URL', plugin_dir_url( __FILE__ ) );

require_once HSMA_PATH . 'includes/class-util.php';
require_once HSMA_PATH . 'includes/class-gateways.php';
require_once HSMA_PATH . 'includes/class-sms.php';
require_once HSMA_PATH . 'includes/class-otp.php';
require_once HSMA_PATH . 'includes/class-auth.php';
require_once HSMA_PATH . 'includes/class-front.php';
require_once HSMA_PATH . 'includes/class-settings.php';
require_once HSMA_PATH . 'includes/class-menu.php';
require_once HSMA_PATH . 'includes/class-users.php';

add_action( 'plugins_loaded', function () {
	HSMA_Auth::init();
	HSMA_Front::init();
	HSMA_Settings::init();
	HSMA_Menu::init();
	HSMA_Users::init();
} );



/** کاربرانی که با شماره به‌عنوان نام کاربری ساخته شده‌اند (مثل پینوا): شماره در _hs_mobile هم ذخیره شود */
add_action( 'wp_login', function ( $login, $user ) {
	if ( ! get_user_meta( $user->ID, '_hs_mobile', true ) ) {
		$m = HSMA_Util::mobile( $user->user_login );
		if ( $m ) {
			update_user_meta( $user->ID, '_hs_mobile', $m );
		}
	}
}, 5, 2 );
