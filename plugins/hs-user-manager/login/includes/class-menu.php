<?php
/**
 * گزینه‌ی «ثبت‌نام / ورود» در منوی سایت (برای واردشده‌ها: «پنل من»)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_Menu {

	public static function init() {
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'items' ), 20, 2 );
		add_shortcode( 'hs_auth_link', array( __CLASS__, 'shortcode' ) );
	}

	/** جایگاه‌هایی که گزینه به آن‌ها اضافه می‌شود */
	public static function locations() {
		$sel = (array) HSMA_Util::opt( 'menu_locations' );
		if ( in_array( '__auto__', $sel, true ) ) {
			// خودکار: اولین جایگاهی که منو دارد (معمولاً منوی اصلی بالای صفحه)
			$assigned = array_filter( (array) get_nav_menu_locations() );
			$order    = array_keys( get_registered_nav_menus() );
			foreach ( array( 'primary', 'main', 'main-menu', 'header', 'menu-1', 'top' ) as $pref ) {
				if ( ! empty( $assigned[ $pref ] ) ) {
					return array( $pref );
				}
			}
			foreach ( $order as $loc ) {
				if ( ! empty( $assigned[ $loc ] ) ) {
					return array( $loc );
				}
			}
			return array();
		}
		return $sel;
	}

	private static function link() {
		if ( is_user_logged_in() ) {
			return array( 'پنل من', HSMA_Util::redirect_url() );
		}
		$p = HSMA_Front::page_url();
		return array( HSMA_Util::opt( 'menu_label' ) ?: 'ثبت‌نام / ورود', $p ? $p : wp_login_url() );
	}

	public static function items( $items, $args ) {
		$loc = isset( $args->theme_location ) ? $args->theme_location : '';
		if ( ! $loc || ! in_array( $loc, self::locations(), true ) ) {
			return $items;
		}
		list( $label, $url ) = self::link();
		return $items . '<li class="menu-item hsma-menu-item"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}

	/** [hs_auth_link] برای سربرگ‌هایی که با صفحه‌ساز ساخته شده‌اند */
	public static function shortcode() {
		list( $label, $url ) = self::link();
		return '<a class="hsma-menu-link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
}
