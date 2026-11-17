<?php
/**
 * مدیریت وردپرس: فهرست مدارس (فقط برای مدیر سایت)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_SO_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function menu() {
		add_menu_page( 'مدارس', 'مدارس', 'manage_options', 'hs-school-org', array( __CLASS__, 'page' ), 'dashicons-building', 26 );
		add_submenu_page( 'hs-school-org', 'مدارس', 'مدارس', 'manage_options', 'hs-school-org', array( __CLASS__, 'page' ) );
		if ( class_exists( 'HS_Guest_Bg' ) ) {
			add_submenu_page( 'hs-school-org', 'تصویر صفحه‌ی ورود مدیر', 'تصویر صفحه‌ی ورود', 'manage_options', 'hs-school-bg', function () {
				echo '<div class="wrap" dir="rtl"><h1>تصویر صفحه‌ی ورود مدیر مدرسه</h1>';
				HS_Guest_Bg::admin_box( 'school_admin' );
				echo '</div>';
			} );
		}
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.' );
		}
		$schools = get_posts( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<div class="wrap" dir="rtl"><h1>مدارس</h1>';
		echo '<p>مدیر مدرسه (کاربر با نقش «مدیر مدرسه») پس از ورود به پنل، مدرسهٔ خود را معرفی می‌کند: <a href="' . esc_url( HS_SO_Frontend::url() ) . '">/panel/school/</a>. معاونین، وظایف و نظارت در همان صفحه است.</p>';
		echo '<table class="widefat striped"><thead><tr><th>مدرسه</th><th>شمارهٔ مدرسه</th><th>استان / شهر</th><th>مدیر</th><th>کادر</th><th></th></tr></thead><tbody>';
		if ( ! $schools ) {
			echo '<tr><td colspan="6">هنوز مدرسه‌ای معرفی نشده است. یک کاربر با نقش «مدیر مدرسه» بسازید تا مدرسه را معرفی کند.</td></tr>';
		}
		foreach ( $schools as $p ) {
			$s = HS_SO_Store::get_school( $p->ID );
			$m = get_userdata( $s['manager_id'] );
			echo '<tr><td><strong>' . esc_html( $s['name'] ) . '</strong></td><td>' . esc_html( $s['code'] ) . '</td><td>' . esc_html( $s['province'] . ' / ' . $s['city'] ) . '</td><td>' . esc_html( $m ? $m->display_name : '—' ) . '</td><td>' . (int) count( HS_SO_Store::staff_list( $p->ID ) ) . '</td><td><a class="button" href="' . esc_url( add_query_arg( 'school_id', $p->ID, HS_SO_Frontend::url() ) ) . '">مشاهده در پنل</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
