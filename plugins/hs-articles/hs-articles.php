<?php
/**
 * Plugin Name: مقالات همشاگردی
 * Description: دسترسی مطالب بر اساس مخاطب (عمومی/والدین/معلمان) و پایه. انتشار مقاله از متن، TXT، HTML یا ZIP با تنظیمات سئو در همان فرم (Rank Math، Yoast یا داخلی)، دانلود و ذخیره‌ی تصاویر از آدرس و تبدیل به WebP، بررسی کیفیت پیش از انتشار، و فهرست مطالب خودکار برای هر مقاله.
 * Version:     1.1.0
 * Text Domain: hs-articles
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'HSAR_VERSION', '1.1.0' );
define( 'HSAR_PATH', plugin_dir_path( __FILE__ ) );
define( 'HSAR_URL', plugin_dir_url( __FILE__ ) );

// اگر افزونه‌ی قدیمی «فهرست مطالب مقاله» هنوز فعال است، فهرست دوبار ساخته نشود.
if ( ! class_exists( 'HS_Article_TOC' ) ) {
	require_once HSAR_PATH . 'includes/class-toc.php';
} else {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-warning"><p><b>مقالات همشاگردی:</b> فهرست مطالب داخل همین افزونه است؛ افزونه‌ی قدیمی «فهرست مطالب مقاله» را غیرفعال و حذف کنید.</p></div>';
	} );
}
require_once HSAR_PATH . 'includes/class-parser.php';
require_once HSAR_PATH . 'includes/class-images.php';
require_once HSAR_PATH . 'includes/class-seo.php';
HSAR_SEO::init();
require_once HSAR_PATH . 'includes/class-access.php';
HSAR_Access::init();
if ( is_admin() ) {
	require_once HSAR_PATH . 'includes/class-admin.php';
	HSAR_Admin::init();
}
