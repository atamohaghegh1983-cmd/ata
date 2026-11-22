<?php
/**
 * پاک‌سازی هنگام حذف افزونه — فقط اگر مدیر در «تنظیمات ← همشاگردی» فعال کرده باشد.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'hs_calendar_daily_reminders' );

if ( 'yes' !== get_option( 'hs_delete_on_uninstall', 'no' ) ) {
	return; // به‌صورت پیش‌فرض داده‌ها حفظ می‌شود
}

global $wpdb;

// جدول‌ها
$tables = array(
	'hs_assessments', 'hs_assessment_results', 'hs_attendance', 'hs_calendar_events', 'hs_announcements',
	'hs_feed_posts', 'hs_messages', 'hs_content_projects', 'hs_homework', 'hs_homework_status',
	'hs_hw_questions', 'hs_hw_submissions', 'hs_parent_chat', 'hs_behaviors', 'hs_points', 'hs_portfolio',
);
foreach ( $tables as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . $t ) . '`' ); // phpcs:ignore
}

// کلاس‌ها و کاربرگ‌ها
foreach ( array( 'hs_class', 'hs_worksheet' ) as $pt ) {
	$ids = get_posts( array( 'post_type' => $pt, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}

// گزینه‌ها و ترنزینت‌ها
foreach ( array(
	'hs_class_db_version', 'hs_class_page_ids', 'hs_class_pages_setup', 'hs_bale_bot_token', 'hs_bale_enabled',
	'hs_bale_webhook_secret', 'hs_shell_enabled', 'hs_theme_palette', 'hs_shell_strip_theme', 'hs_delete_on_uninstall',
) as $o ) {
	delete_option( $o );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_hs\\_%' OR option_name LIKE '\\_transient\\_timeout\\_hs\\_%'" ); // phpcs:ignore

// متای کاربران
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_hs\\_%'" ); // phpcs:ignore

// نقش‌ها
foreach ( array( 'teacher', 'school_admin', 'student', 'parent' ) as $r ) {
	remove_role( $r );
}
$admin = get_role( 'administrator' );
if ( $admin ) {
	foreach ( array( 'hs_manage_all_classes', 'hs_manage_own_classes', 'hs_view_class', 'hs_add_students' ) as $cap ) {
		$admin->remove_cap( $cap );
	}
}
