<?php
// حذف افزونه اطلاعات کاربران و ارتباط‌ها را پاک نمی‌کند (فقط تنظیمات).
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'hsum_settings' );
