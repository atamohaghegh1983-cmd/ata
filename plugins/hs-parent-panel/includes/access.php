<?php
/**
 * تنظیم مجوزهای والدین
 * - مدیر سایت: پیشخوان ← پنل والدین (همه‌ی سطح‌ها)
 * - مدیر مدرسه: پنل ← «دسترسی والدین» [hs_parent_access] (مدرسه‌ی خودش، کلاس‌ها، دانش‌آموزان و والدین آن)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Access {

	public static function init() {
		add_shortcode( 'hs_parent_access', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_hspp_perm_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_menu', function () {
			add_menu_page( 'پنل والدین', 'پنل والدین', 'manage_options', 'hspp', array( __CLASS__, 'admin_page' ), 'dashicons-groups', 72 );
		} );
	}

	public static function save() {
		check_admin_referer( 'hspp_perm' );
		$scope  = sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) );
		$sid    = absint( $_POST['scope_id'] ?? 0 );
		$parent = absint( $_POST['parent_id'] ?? 0 );
		$errors = array();
		foreach ( HSPP_Perms::features() as $f => $l ) {
			$v = isset( $_POST['f'][ $f ] ) ? sanitize_key( wp_unslash( $_POST['f'][ $f ] ) ) : 'inherit';
			$r = HSPP_Perms::set( $scope, $sid, $f, 'inherit' === $v ? null : ( 'allow' === $v ? 1 : 0 ), 'link' === $scope ? $parent : 0 );
			if ( is_wp_error( $r ) ) {
				$errors[ $r->get_error_message() ] = true;
			}
		}
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=hspp' );
		wp_safe_redirect( add_query_arg( 'hspp_msg', rawurlencode( $errors ? implode( ' ', array_keys( $errors ) ) : 'مجوزها ذخیره شد.' ), remove_query_arg( 'hspp_msg', $back ) ) );
		exit;
	}

	public static function admin_page() {
		echo '<div class="wrap" dir="rtl"><h1>پنل والدین — دسترسی والدین</h1>';
		echo self::ui( true ); // phpcs:ignore
		echo '<h2>سوابق اخیر</h2><table class="widefat striped"><thead><tr><th>زمان</th><th>توسط</th><th>عملیات</th><th>جزئیات</th></tr></thead><tbody>';
		global $wpdb;
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . HSPP_DB::t( 'log' ) . ' ORDER BY id DESC LIMIT 100', ARRAY_A ) as $l ) { // phpcs:ignore
			$a = $l['actor_id'] ? get_userdata( $l['actor_id'] ) : null;
			echo '<tr><td>' . esc_html( mysql2date( 'Y/m/d H:i', $l['created_at'] ) ) . '</td><td>' . esc_html( $a ? $a->display_name : '—' ) . '</td><td>' . esc_html( $l['action'] ) . '</td><td dir="ltr" style="text-align:left"><code>' . esc_html( $l['details'] ) . '</code></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>ابتدا وارد شوید.</p>';
		}
		$uid    = get_current_user_id();
		$school = class_exists( 'HS_SO_Store' ) ? (int) HS_SO_Store::manager_school_id( $uid ) : 0;
		if ( ! $school && ! current_user_can( 'manage_options' ) ) {
			return '<div class="hs-card"><p>این بخش برای مدیر مدرسه است.</p></div>';
		}
		return '<div class="hs-pp hspp-access">' . self::ui( false ) . '</div>';
	}

	/** کلاس‌هایی که کاربر می‌تواند مجوزشان را تنظیم کند */
	private static function classes() {
		$all = get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC' ) );
		return array_values( array_filter( $all, function ( $c ) {
			return HSPP_Perms::can_manage( 'class', $c->ID );
		} ) );
	}

	private static function ui( $admin ) {
		$uid    = get_current_user_id();
		$school = class_exists( 'HS_SO_Store' ) ? (int) HS_SO_Store::manager_school_id( $uid ) : 0;
		$level  = isset( $_GET['lvl'] ) ? sanitize_key( $_GET['lvl'] ) : ( $admin ? 'site' : 'school' );
		$cls    = isset( $_GET['cls'] ) ? absint( $_GET['cls'] ) : 0;
		$stu    = isset( $_GET['stu'] ) ? absint( $_GET['stu'] ) : 0;
		$par    = isset( $_GET['par'] ) ? absint( $_GET['par'] ) : 0;
		$h      = '';
		if ( isset( $_GET['hspp_msg'] ) ) {
			$h .= '<div class="notice notice-info hs-pp-note ok"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['hspp_msg'] ) ) ) . '</p></div>';
		}
		$h .= '<p>هر بخش از پنل والدین را می‌توانید در سطح‌های مختلف باز یا بسته کنید. تنظیمِ جزئی‌تر بر کلی‌تر مقدم است: <b>والد خاص ← دانش‌آموز ← کلاس ← مدرسه ← کل سایت</b>. «پیش‌فرض» یعنی از سطح بالاتر پیروی کند.</p>';

		$levels = array( 'school' => 'مدرسه', 'class' => 'کلاس', 'student' => 'دانش‌آموز', 'link' => 'یک والد خاص' );
		if ( $admin ) {
			$levels = array( 'site' => 'کل سایت' ) + $levels;
		}
		$h .= '<nav class="hspp-levels">';
		foreach ( $levels as $k => $l ) {
			$h .= '<a class="' . ( $k === $level ? 'is-on' : '' ) . '" href="' . esc_url( add_query_arg( array( 'lvl' => $k ), remove_query_arg( array( 'hspp_msg' ) ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		$h .= '</nav>';

		// انتخاب هدف
		$scope = $level;
		$sid   = 0;
		$pid   = 0;
		$pick  = '<form method="get" class="hspp-pick">';
		foreach ( $_GET as $k => $v ) { // phpcs:ignore
			if ( in_array( $k, array( 'page', 'lvl' ), true ) ) {
				$pick .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $v ) ) ) . '">';
			}
		}
		if ( 'school' === $level ) {
			if ( $admin && class_exists( 'HS_SO_Store' ) ) {
				$schools = get_posts( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'posts_per_page' => 300 ) );
				$sel     = isset( $_GET['sch'] ) ? absint( $_GET['sch'] ) : ( $schools ? $schools[0]->ID : 0 );
				$pick   .= '<select name="sch" onchange="this.form.submit()">';
				foreach ( $schools as $s ) {
					$pick .= '<option value="' . (int) $s->ID . '" ' . selected( $sel, $s->ID, false ) . '>' . esc_html( $s->post_title ) . '</option>';
				}
				$pick .= '</select>';
				$sid   = $sel;
			} else {
				$sid = $school;
			}
		}
		if ( in_array( $level, array( 'class', 'student', 'link' ), true ) ) {
			$classes = self::classes();
			if ( ! $cls && $classes ) {
				$cls = $classes[0]->ID;
			}
			$pick .= '<select name="cls" onchange="this.form.submit()">';
			foreach ( $classes as $c ) {
				$pick .= '<option value="' . (int) $c->ID . '" ' . selected( $cls, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
			}
			$pick .= '</select>';
			$sid   = $cls;
		}
		if ( in_array( $level, array( 'student', 'link' ), true ) && $cls ) {
			$sts = array_filter( array_map( 'get_userdata', array_map( 'intval', (array) get_post_meta( $cls, '_hs_students', true ) ) ) );
			if ( ! $stu && $sts ) {
				$stu = reset( $sts )->ID;
			}
			$pick .= '<select name="stu" onchange="this.form.submit()">';
			foreach ( $sts as $s ) {
				$pick .= '<option value="' . (int) $s->ID . '" ' . selected( $stu, $s->ID, false ) . '>' . esc_html( $s->display_name ) . '</option>';
			}
			$pick .= '</select>';
			$sid   = $stu;
		}
		if ( 'link' === $level && $stu ) {
			$ps = HS_Parent_Link::get_parents( $stu );
			if ( ! $par && $ps ) {
				$par = (int) $ps[0];
			}
			$pick .= '<select name="par" onchange="this.form.submit()">';
			foreach ( $ps as $p ) {
				$pick .= '<option value="' . (int) $p . '" ' . selected( $par, $p, false ) . '>' . esc_html( HSPP_Names::relation_label( $p, $stu ) . ' — ' . get_userdata( $p )->display_name ) . '</option>';
			}
			$pick .= '</select>';
			$pid   = $par;
			if ( ! $ps ) {
				$pick .= ' <span class="hs-pp-muted">این دانش‌آموز هنوز والد متصل ندارد.</span>';
			}
		}
		$pick .= '</form>';
		$h    .= $pick;

		if ( ( 'site' !== $scope && ! $sid ) || ( 'link' === $scope && ! $pid ) ) {
			return $h . '<p class="hs-pp-muted">موردی برای تنظیم انتخاب نشده است.</p>';
		}
		if ( ! HSPP_Perms::can_manage( $scope, $sid ) ) {
			return $h . '<p>اجازه‌ی تنظیم این مورد را ندارید.</p>';
		}

		$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'hspp_perm', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="hspp_perm_save"><input type="hidden" name="scope" value="' . esc_attr( $scope ) . '"><input type="hidden" name="scope_id" value="' . (int) $sid . '"><input type="hidden" name="parent_id" value="' . (int) $pid . '">'
			. '<table class="widefat striped hs-table"><thead><tr><th>بخش</th><th>تنظیم این سطح</th>' . ( in_array( $scope, array( 'student', 'link' ), true ) ? '<th>نتیجه‌ی فعلی</th>' : '' ) . '</tr></thead><tbody>';
		foreach ( HSPP_Perms::features() as $f => $l ) {
			$cur = HSPP_Perms::rule( $scope, $sid, $f, 'link' === $scope ? $pid : 0 );
			$sel = null === $cur ? 'inherit' : ( $cur ? 'allow' : 'deny' );
			$h  .= '<tr><td>' . esc_html( $l[0] ) . '</td><td><select name="f[' . esc_attr( $f ) . ']">'
				. '<option value="inherit" ' . selected( $sel, 'inherit', false ) . '>پیش‌فرض (سطح بالاتر)</option>'
				. '<option value="allow" ' . selected( $sel, 'allow', false ) . '>✅ مجاز</option>'
				. '<option value="deny" ' . selected( $sel, 'deny', false ) . '>🚫 بسته</option></select></td>';
			if ( 'student' === $scope || 'link' === $scope ) {
				$parents = 'link' === $scope ? array( $pid ) : HS_Parent_Link::get_parents( $sid );
				$res     = array();
				foreach ( $parents as $p ) {
					$res[] = ( HSPP_Perms::can( (int) $p, 'link' === $scope ? $stu : $sid, $f ) ? '✅ ' : '🚫 ' ) . get_userdata( $p )->display_name;
				}
				$h .= '<td>' . esc_html( implode( '، ', $res ) ?: '—' ) . '</td>';
			}
			$h .= '</tr>';
		}
		$h .= '</tbody></table><p><button class="button button-primary hs-btn hs-btn-primary">ذخیره</button></p></form>';
		return $h;
	}
}

HSPP_Access::init();
