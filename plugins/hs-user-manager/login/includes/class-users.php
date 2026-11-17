<?php
/**
 * کاربران: ستون و فیلد موبایل، خروجی CSV/VCF، جایگزینی فرم ورود حساب کاربری ووکامرس
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_Users {

	public static function init() {
		add_filter( 'manage_users_columns', array( __CLASS__, 'col' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'col_val' ), 10, 3 );
		add_action( 'show_user_profile', array( __CLASS__, 'field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_field' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_field' ) );
		add_action( 'user_profile_update_errors', array( __CLASS__, 'check_field' ), 10, 3 );
		add_action( 'admin_post_hsma_export', array( __CLASS__, 'export' ) );
		add_filter( 'wc_get_template', array( __CLASS__, 'woo_login' ), 20, 2 );
	}

	public static function mobile_of( $user_id ) {
		$m = (string) get_user_meta( $user_id, '_hs_mobile', true );
		if ( ! $m ) {
			$u = get_userdata( $user_id );
			$m = $u ? HSMA_Util::mobile( $u->user_login ) : '';
		}
		return $m;
	}

	public static function col( $cols ) {
		$cols['hsma_mobile'] = 'موبایل';
		return $cols;
	}

	public static function col_val( $val, $col, $user_id ) {
		return 'hsma_mobile' === $col ? '<span dir="ltr">' . esc_html( self::mobile_of( $user_id ) ) . '</span>' : $val;
	}

	public static function field( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		?>
		<h2>موبایل</h2>
		<table class="form-table"><tr><th><label for="hsma_mobile">شماره موبایل</label></th><td>
			<input id="hsma_mobile" name="hsma_mobile" dir="ltr" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, '_hs_mobile', true ) ); ?>" <?php disabled( ! current_user_can( 'edit_users' ) ); ?>>
			<p class="description">برای ورود با کد پیامکی. فقط مدیر سایت می‌تواند تغییر دهد.</p>
		</td></tr></table>
		<?php
	}

	public static function check_field( $errors, $update, $user ) {
		if ( ! isset( $_POST['hsma_mobile'] ) || ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$raw = sanitize_text_field( wp_unslash( $_POST['hsma_mobile'] ) );
		if ( '' === $raw ) {
			return;
		}
		$m = HSMA_Util::mobile( $raw );
		if ( ! $m ) {
			$errors->add( 'hsma_mobile', 'شماره موبایل درست نیست.' );
			return;
		}
		$other = HSMA_Util::user_by_mobile( $m );
		if ( $other && ( empty( $user->ID ) || (int) $other->ID !== (int) $user->ID ) ) {
			$errors->add( 'hsma_mobile', 'این شماره موبایل برای کاربر دیگری («' . esc_html( $other->display_name ) . '») ثبت شده است.' );
		}
	}

	public static function save_field( $user_id ) {
		if ( ! isset( $_POST['hsma_mobile'] ) || ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$raw = sanitize_text_field( wp_unslash( $_POST['hsma_mobile'] ) );
		if ( '' === $raw ) {
			delete_user_meta( $user_id, '_hs_mobile' );
			return;
		}
		$m     = HSMA_Util::mobile( $raw );
		$other = $m ? HSMA_Util::user_by_mobile( $m ) : false;
		if ( $m && ( ! $other || (int) $other->ID === (int) $user_id ) ) {
			update_user_meta( $user_id, '_hs_mobile', $m );
		}
	}

	/** خروجی کاربرانِ دارای موبایل: CSV (اکسل) یا VCF (مخاطبین گوشی) */
	public static function export() {
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( 'دسترسی ندارید.' );
		}
		check_admin_referer( 'hsma_export' );
		$fmt   = isset( $_GET['fmt'] ) && 'vcf' === $_GET['fmt'] ? 'vcf' : 'csv';
		$role  = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '';
		$args  = array( 'fields' => array( 'ID', 'display_name', 'user_login' ), 'number' => -1 );
		if ( $role ) {
			$args['role'] = $role;
		}
		$labels = HSMA_Util::role_labels();
		$rows   = array();
		foreach ( get_users( $args ) as $u ) {
			$m = self::mobile_of( $u->ID );
			if ( ! $m ) {
				continue;
			}
			$ud  = get_userdata( $u->ID );
			$rl  = array();
			foreach ( (array) $ud->roles as $r ) {
				$rl[] = $labels[ $r ] ?? $r;
			}
			$rows[] = array( $u->display_name, $m, implode( '، ', $rl ), $u->user_login );
		}
		$name = 'users-' . ( $role ? $role . '-' : '' ) . gmdate( 'Y-m-d' );
		nocache_headers();
		if ( 'vcf' === $fmt ) {
			header( 'Content-Type: text/vcard; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $name . '.vcf' );
			foreach ( $rows as $r ) {
				$fn = str_replace( array( "\r", "\n", ';', ',' ), ' ', $r[0] );
				echo "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:{$fn}\r\nN:{$fn};;;;\r\nTEL;TYPE=CELL:+98" . substr( $r[1], 1 ) . "\r\nNOTE:" . str_replace( array( "\r", "\n" ), ' ', $r[2] ) . "\r\nEND:VCARD\r\n"; // phpcs:ignore
			}
			exit;
		}
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $name . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // برای نمایش درست فارسی در اکسل
		fputcsv( $out, array( 'نام', 'موبایل', 'نقش', 'نام کاربری' ) );
		foreach ( $rows as $r ) {
			$r[1] = "\t" . $r[1]; // صفر اول موبایل در اکسل حذف نشود
			fputcsv( $out, $r );
		}
		fclose( $out );
		exit;
	}

	/** فرم ورود «حساب کاربری من» ووکامرس ← فرم ورود با موبایل */
	public static function woo_login( $template, $name ) {
		if ( 'myaccount/form-login.php' === $name && HSMA_Util::opt( 'woo_login' ) && ! is_user_logged_in() ) {
			return HSMA_PATH . 'includes/woo-form-login.php';
		}
		return $template;
	}
}
