<?php
/**
 * راه‌انداز تست‌ها: وردپرس آزمایشی + همه‌ی افزونه‌های همشاگردی از پوشه‌ی plugins/ همین مخزن.
 *   WP_CORE_DIR=/path/to/wordpress  vendor/bin/phpunit
 */
$root = dirname( __DIR__ );
define( 'HS_TEST_PLUGINS', $root . '/plugins' );

$cfg = __DIR__ . '/wp-tests-config.php';
if ( ! file_exists( $cfg ) ) {
	$cfg = __DIR__ . '/wp-tests-config-sample.php';
}
putenv( 'WP_TESTS_CONFIG_FILE_PATH=' . $cfg );
define( 'WP_TESTS_CONFIG_FILE_PATH', $cfg );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/vendor/yoast/phpunit-polyfills' );

require_once __DIR__ . '/vendor/autoload.php';
$wp_tests = __DIR__ . '/vendor/wp-phpunit/wp-phpunit';
require_once $wp_tests . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	foreach ( array(
		'hamshagerdi-class-manager/hamshagerdi-class-manager.php',
		'hs-user-manager/hs-user-manager.php',
		'hamshagerdi-school-org/hamshagerdi-school-org.php',
		'hs-parent-panel/hs-parent-panel.php',
		'hs-student-panel/hs-student-panel.php',
		'hs-articles/hs-articles.php',
		'hamshagerdi-api/hamshagerdi-api.php',
	) as $p ) {
		require HS_TEST_PLUGINS . '/' . $p;
	}
} );

// ساخت برگه‌ها روی plugins_loaded (پیش از آماده‌شدن $wp_rewrite) در محیط تست اجرا نشود؛ جدول‌ها پایین‌تر ساخته می‌شوند
tests_add_filter( 'pre_option_hspp_db_version', function () { return defined( 'HSPP_DB_VERSION' ) ? HSPP_DB_VERSION : false; } );
tests_add_filter( 'pre_option_hssp_db_version', function () { return defined( 'HSSP_DB_VERSION' ) ? HSSP_DB_VERSION : false; } );

// جدول‌ها (معادل فعال‌سازی افزونه‌ها)
tests_add_filter( 'init', function () {
	if ( function_exists( 'hs_class_activate' ) ) { hs_class_activate(); }
	if ( function_exists( 'hs_so_activate' ) ) { hs_so_activate(); }
	foreach ( array( 'HSUM_DB', 'HSPP_DB', 'HSSP_DB' ) as $c ) {
		if ( class_exists( $c ) && method_exists( $c, 'install' ) ) { $c::install(); }
	}
	if ( class_exists( 'HS_Points_DB' ) && method_exists( 'HS_Points_DB', 'seed_default_behaviors' ) ) { HS_Points_DB::seed_default_behaviors(); }
}, 1 );

require $wp_tests . '/includes/bootstrap.php';
require_once __DIR__ . '/tests/class-hs-test-case.php';
