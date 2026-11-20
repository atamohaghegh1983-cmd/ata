<?php
/**
 * دسترسی دانش‌آموزان در سطح مدرسه و کل سایت (مدیر مدرسه / مدیر کل)
 * بستن در این سطح = سقف؛ معلم نمی‌تواند بازش کند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_Access {

	public static function init() {
		add_shortcode( 'hs_student_access', array( __CLASS__, 'page' ) );
		add_action( 'admin_post_hssp_access', array( __CLASS__, 'handle' ) );
		add_action( 'admin_menu', function () {
			add_menu_page( 'پنل دانش‌آموز', 'پنل دانش‌آموز', 'manage_options', 'hssp', function () {
				echo '<div class="wrap">' . HSSP_Access::page() . '</div>'; // phpcs:ignore
			}, 'dashicons-welcome-learn-more', 58 );
		} );
	}

	public static function page() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid   = get_current_user_id();
		$admin = user_can( $uid, 'manage_options' );
		$mine  = class_exists( 'HS_SO_Store' ) ? (int) HS_SO_Store::manager_school_id( $uid ) : 0;
		if ( ! $admin && ! $mine ) {
			return '<div class="hs-card"><p>این صفحه برای مدیر مدرسه است.</p></div>';
		}
		$h = '<div class="hssp-t" dir="rtl">' . HSSP_Util::notice();
		if ( $admin ) {
			$h .= self::box( 'site', 0, '🌐 کل سایت', 'پیش‌فرض همه‌ی مدرسه‌ها. «بسته» = همه جا بسته است مگر مدرسه‌ای صراحتاً باز کند.' );
			$schools = class_exists( 'HS_SO_Store' ) ? get_posts( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'posts_per_page' => 300 ) ) : array();
			$sch     = isset( $_GET['sch'] ) ? absint( $_GET['sch'] ) : ( $mine ? $mine : ( $schools ? $schools[0]->ID : 0 ) ); // phpcs:ignore
			if ( $schools ) {
				$h .= '<section class="hs-card"><form method="get" class="hssp-inline">' . ( is_admin() ? '<input type="hidden" name="page" value="hssp">' : '' ) . '<label>مدرسه <select name="sch" onchange="this.form.submit()">';
				foreach ( $schools as $s ) {
					$h .= '<option value="' . (int) $s->ID . '"' . selected( $s->ID, $sch, false ) . '>' . esc_html( $s->post_title ) . '</option>';
				}
				$h .= '</select></label></form></section>';
			}
			if ( $sch ) {
				$h .= self::box( 'school', $sch, '🏫 ' . get_the_title( $sch ), '' );
			}
		} else {
			$h .= self::box( 'school', $mine, '🏫 ' . get_the_title( $mine ), 'آنچه اینجا ببندید برای همه‌ی کلاس‌های مدرسه بسته می‌شود و معلم‌ها نمی‌توانند بازش کنند.' );
		}
		return $h . '</div>';
	}

	private static function box( $scope, $id, $title, $help ) {
		$h = '<section class="hs-card"><h3>' . esc_html( $title ) . '</h3>' . ( $help ? '<p class="hs-muted">' . esc_html( $help ) . '</p>' : '' )
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'hssp_access', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="hssp_access"><input type="hidden" name="scope" value="' . esc_attr( $scope ) . '"><input type="hidden" name="id" value="' . (int) $id . '"><table class="hssp-perm"><tbody>';
		foreach ( HSSP_Perms::features() as $f => $v ) {
			$h .= '<tr><td>' . esc_html( $v[1] . ' ' . $v[0] ) . '</td><td>' . HSSP_Teacher::tri( 'p[' . $f . ']', HSSP_Perms::rule( $scope, $id, $f ), 'site' === $scope ? 'پیش‌فرض (باز)' : 'مثل کل سایت' ) . '</td></tr>';
		}
		return $h . '</tbody></table><button class="hs-btn hs-btn-primary button button-primary">ذخیره</button></form></section>';
	}

	public static function handle() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'hssp_access' ) ) {
			wp_die( 'نامعتبر' );
		}
		$scope = sanitize_key( $_POST['scope'] ?? '' );
		$id    = (int) ( $_POST['id'] ?? 0 );
		$p     = isset( $_POST['p'] ) && is_array( $_POST['p'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['p'] ) ) : array(); // phpcs:ignore
		if ( ! in_array( $scope, array( 'site', 'school' ), true ) || ! HSSP_Perms::can_manage( $scope, $id ) ) {
			HSSP_Util::back( 'اجازه‌ی این کار را ندارید.' );
		}
		foreach ( HSSP_Perms::features() as $f => $v ) {
			if ( array_key_exists( $f, $p ) ) {
				HSSP_Perms::set( $scope, $id, $f, '' === $p[ $f ] ? null : (int) ( '1' === $p[ $f ] ) );
			}
		}
		HSSP_Util::back( 'ذخیره شد.' );
	}
}

HSSP_Access::init();
