<?php
/**
 * پیشخوان ← «کاربران و عضویت» (فقط مدیر کل)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Admin {

	const SLUG = 'hsum';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		foreach ( array( 'status', 'role', 'settings', 'backfill' ) as $a ) {
			add_action( 'admin_post_hsum_admin_' . $a, array( __CLASS__, 'do_' . $a ) );
		}
		add_filter( 'manage_users_columns', function ( $c ) {
			$c['hsum_status'] = 'عضویت';
			return $c;
		} );
		add_filter( 'manage_users_custom_column', function ( $v, $col, $uid ) {
			if ( 'hsum_status' !== $col ) {
				return $v;
			}
			$m = HSUM_Membership::get( $uid );
			return esc_html( HSUM_Membership::statuses()[ $m['status'] ] ?? '' ) . ( 'active' !== $m['status'] && $m['role'] ? ' (' . esc_html( HSUM_Membership::roles()[ $m['role'] ] ?? '' ) . ')' : '' );
		}, 10, 3 );
	}

	public static function menu() {
		$n = HSUM_Membership::count_pending();
		add_menu_page( 'کاربران و عضویت', 'کاربران و عضویت' . ( $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '' ), 'promote_users', self::SLUG, array( __CLASS__, 'page' ), 'dashicons-groups', 70 );
	}

	public static function url( $tab = '', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $tab ? array( 'tab' => $tab ) : array(), $args ), admin_url( 'admin.php' ) );
	}

	private static function guard( $nonce ) {
		if ( ! current_user_can( 'promote_users' ) ) {
			wp_die( 'دسترسی ندارید.' );
		}
		check_admin_referer( $nonce );
	}

	private static function back( $msg ) {
		$u = wp_get_referer() ? wp_get_referer() : self::url();
		wp_safe_redirect( add_query_arg( 'hsum_msg', rawurlencode( $msg ), remove_query_arg( 'hsum_msg', $u ) ) );
		exit;
	}

	public static function do_status() {
		self::guard( 'hsum_admin' );
		$r = HSUM_Membership::set_status( absint( $_POST['user'] ?? 0 ), sanitize_key( $_POST['status'] ?? '' ), sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ) );
		self::back( is_wp_error( $r ) ? $r->get_error_message() : 'وضعیت به‌روز شد.' );
	}

	public static function do_role() {
		self::guard( 'hsum_admin' );
		$r = HSUM_Membership::change_role( absint( $_POST['user'] ?? 0 ), sanitize_key( $_POST['role'] ?? '' ) );
		self::back( is_wp_error( $r ) ? $r->get_error_message() : 'نقش تغییر کرد و در سوابق ثبت شد.' );
	}

	public static function do_backfill() {
		self::guard( 'hsum_admin' );
		self::back( sprintf( '%d کاربر قبلی با وضعیت «فعال» ثبت شد.', HSUM_Membership::backfill() ) );
	}

	public static function do_settings() {
		self::guard( 'hsum_admin' );
		$in   = wp_unslash( $_POST );
		$keys = array_values( array_filter( array_map( 'sanitize_key', preg_split( '/[\s,،]+/u', (string) ( $in['phone_keys'] ?? '' ) ) ) ) );
		if ( ! in_array( '_hs_mobile', $keys, true ) ) {
			array_unshift( $keys, '_hs_mobile' );
		}
		update_option( 'hsum_settings', array(
			'phone_keys'     => $keys,
			'approval_roles' => array_values( array_intersect( array( 'teacher', 'school_admin', 'student', 'parent' ), (array) ( $in['approval_roles'] ?? array() ) ) ),
			'invite_days'    => absint( $in['invite_days'] ?? 0 ),
			'sms_invites'    => empty( $in['sms_invites'] ) ? 0 : 1,
		) );
		self::back( 'ذخیره شد.' );
	}

	/* ---------- نمایش ---------- */

	private static function post_form( $action, $fields, $button, $class = 'button' ) {
		$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-flex;gap:4px;align-items:center;margin:0">' . wp_nonce_field( 'hsum_admin', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="hsum_admin_' . esc_attr( $action ) . '">';
		foreach ( $fields as $k => $v ) {
			$h .= is_int( $k ) ? $v : '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		return $h . '<button class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button></form>';
	}

	public static function page() {
		$tabs = array( 'pending' => 'درخواست‌های عضویت', 'members' => 'اعضا', 'links' => 'ارتباط والدین', 'requests' => 'درخواست‌های اتصال', 'audit' => 'سوابق', 'settings' => 'تنظیمات' );
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'pending';
		echo '<div class="wrap" dir="rtl"><h1>کاربران و عضویت</h1>';
		if ( isset( $_GET['hsum_msg'] ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['hsum_msg'] ) ) ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $l ) {
			$badge = 'pending' === $k && HSUM_Membership::count_pending() ? ' (' . HSUM_Membership::count_pending() . ')' : '';
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $k === $tab ? 'nav-tab-active' : '', esc_url( self::url( $k ) ), esc_html( $l . $badge ) );
		}
		echo '</nav>';
		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		echo '</div>';
	}

	private static function user_cell( $uid ) {
		$u = get_userdata( $uid );
		if ( ! $u ) {
			return '—';
		}
		return '<a href="' . esc_url( get_edit_user_link( $uid ) ) . '"><b>' . esc_html( $u->display_name ) . '</b></a><br><span dir="ltr">' . esc_html( HSUM_Phone::user_mobile( $uid ) ) . '</span>';
	}

	private static function tab_pending() {
		$rows = HSUM_Membership::query( array( 'status' => 'pending' ) );
		echo '<p>معلم و مدیر مدرسه‌ای که خودشان ثبت‌نام کرده‌اند، تا تأیید شما نقش «در انتظار تأیید» دارند. نتیجه با پیامک به کاربر اطلاع داده می‌شود.</p>';
		echo '<table class="widefat striped"><thead><tr><th>کاربر</th><th>نقش درخواستی</th><th>تاریخ ثبت‌نام</th><th>مدرسه (اظهارشده)</th><th>اقدام</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">درخواستی در انتظار نیست.</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr><td>' . self::user_cell( $r['user_id'] ) . '</td><td>' . esc_html( HSUM_Membership::roles()[ $r['role'] ] ?? $r['role'] ) . '</td><td>' . esc_html( wp_date( 'Y/m/d H:i', strtotime( $r['user_registered'] . ' UTC' ) ) ) . '</td><td>' . esc_html( get_user_meta( $r['user_id'], '_hs_school_name', true ) ) . '</td><td>'
				. self::post_form( 'status', array( 'user' => $r['user_id'], 'status' => 'active' ), 'تأیید', 'button button-primary' ) . ' '
				. self::post_form( 'status', array( 'user' => $r['user_id'], 'status' => 'rejected', '<input name="reason" placeholder="دلیل رد">' ), 'رد' ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
	}

	private static function tab_members() {
		$q  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$st = isset( $_GET['st'] ) ? sanitize_key( $_GET['st'] ) : '';
		$rl = isset( $_GET['rl'] ) ? sanitize_key( $_GET['rl'] ) : '';
		echo '<form method="get" style="margin:12px 0"><input type="hidden" name="page" value="hsum"><input type="hidden" name="tab" value="members"><input name="q" value="' . esc_attr( $q ) . '" placeholder="نام یا موبایل"> <select name="st"><option value="">همه‌ی وضعیت‌ها</option>';
		foreach ( HSUM_Membership::statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $st, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <select name="rl"><option value="">همه‌ی نقش‌ها</option>';
		foreach ( HSUM_Membership::roles() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $rl, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <button class="button">جستجو</button> ' . self::post_form( 'backfill', array(), 'ثبت کاربران قبلی' ) . '</form>'; // phpcs:ignore
		echo '<table class="widefat striped"><thead><tr><th>کاربر</th><th>نقش</th><th>وضعیت</th><th>تغییر نقش</th><th>وضعیت</th></tr></thead><tbody>';
		foreach ( HSUM_Membership::query( array( 'q' => $q, 'status' => $st, 'role' => $rl ) ) as $r ) {
			$sel = '<select name="role">';
			foreach ( HSUM_Membership::roles() as $k => $l ) {
				$sel .= '<option value="' . esc_attr( $k ) . '" ' . selected( $r['role'], $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			$sel .= '</select>';
			$act  = 'suspended' === $r['status'] || 'rejected' === $r['status']
				? self::post_form( 'status', array( 'user' => $r['user_id'], 'status' => 'active' ), 'فعال‌سازی' )
				: ( 'active' === $r['status'] ? self::post_form( 'status', array( 'user' => $r['user_id'], 'status' => 'suspended', '<input name="reason" placeholder="دلیل">' ), 'تعلیق' ) : self::post_form( 'status', array( 'user' => $r['user_id'], 'status' => 'active' ), 'تأیید', 'button button-primary' ) );
			echo '<tr><td>' . self::user_cell( $r['user_id'] ) . '</td><td>' . esc_html( HSUM_Membership::roles()[ $r['role'] ] ?? '—' ) . '</td><td>' . esc_html( HSUM_Membership::statuses()[ $r['status'] ] ?? $r['status'] ) . ( $r['reason'] ? '<br><small>' . esc_html( $r['reason'] ) . '</small>' : '' ) . '</td><td>'
				. self::post_form( 'role', array( 'user' => $r['user_id'], $sel ), 'اعمال' ) . '</td><td>' . $act . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table><p class="description">۳۰۰ نتیجه‌ی آخر. کاربرانی که پیش از نصب این افزونه عضو بوده‌اند با «ثبت کاربران قبلی» به فهرست اضافه می‌شوند.</p>';
	}

	private static function tab_links() {
		global $wpdb;
		$rows   = $wpdb->get_results( 'SELECT * FROM ' . HSUM_DB::t( 'links' ) . ' ORDER BY id DESC LIMIT 300', ARRAY_A ); // phpcs:ignore
		$labels = HSUM_Links::status_labels();
		echo '<table class="widefat striped"><thead><tr><th>دانش‌آموز</th><th>کلاس</th><th>والد</th><th>نسبت</th><th>وضعیت</th><th>منبع</th><th>تاریخ</th></tr></thead><tbody>';
		$src = array( 'teacher' => 'معلم', 'import' => 'ورود گروهی', 'class_code' => 'کد کلاس', 'request' => 'درخواست والد', 'parent_invite' => 'دعوت والد اول', 'manual' => 'دستی' );
		foreach ( $rows as $l ) {
			$s = get_userdata( $l['student_id'] );
			echo '<tr><td>' . esc_html( $s ? $s->display_name : '—' ) . '</td><td>' . esc_html( $l['class_id'] ? get_the_title( $l['class_id'] ) : '' ) . '</td><td>' . ( $l['parent_id'] ? self::user_cell( $l['parent_id'] ) : '<span dir="ltr">' . esc_html( $l['parent_mobile'] ) . '</span>' ) . '</td><td>' . esc_html( HSUM_Links::relations()[ $l['relation'] ] ?? '' ) . '</td><td>' . esc_html( $labels[ $l['status'] ] ?? $l['status'] ) . '</td><td>' . esc_html( $src[ $l['source'] ] ?? $l['source'] ) . '</td><td>' . esc_html( wp_date( 'Y/m/d', strtotime( $l['created_at'] ) ) ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
	}

	private static function tab_requests() {
		$rows = HSUM_Requests::inbox();
		echo '<p>درخواست‌های باز والدین. معلم کلاس و مدیر مدرسه هم از صفحه‌ی «خانواده و دعوت» در پنل رسیدگی می‌کنند.</p><table class="widefat striped"><thead><tr><th>والد</th><th>فرزند</th><th>به</th><th>توضیح</th><th>تاریخ</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">درخواست بازی نیست.</td></tr>';
		}
		foreach ( $rows as $r ) {
			$to = $r['class_id'] ? get_the_title( $r['class_id'] ) : ( $r['teacher_id'] ? get_userdata( $r['teacher_id'] )->display_name : ( $r['teacher_mobile'] ? $r['teacher_mobile'] . ' (هنوز عضو نیست)' : ( $r['school_id'] ? get_the_title( $r['school_id'] ) : '—' ) ) );
			echo '<tr><td>' . self::user_cell( $r['parent_id'] ) . '</td><td>' . esc_html( $r['child_name'] ) . '</td><td>' . esc_html( $to ) . '</td><td>' . esc_html( $r['message'] ) . '</td><td>' . esc_html( wp_date( 'Y/m/d', strtotime( $r['created_at'] ) ) ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table><p><a class="button" href="' . esc_url( HSUM_Front::page_url() ) . '">رسیدگی در پنل</a></p>';
	}

	private static function tab_audit() {
		$labels = HSUM_Audit::labels();
		echo '<table class="widefat striped"><thead><tr><th>زمان</th><th>انجام‌دهنده</th><th>عملیات</th><th>کاربر/مورد</th><th>جزئیات</th></tr></thead><tbody>';
		foreach ( HSUM_Audit::recent( 300 ) as $a ) {
			$actor  = $a['actor_id'] ? get_userdata( $a['actor_id'] ) : null;
			$target = get_userdata( $a['target_id'] );
			$tname  = $target ? $target->display_name : ( get_the_title( $a['target_id'] ) ?: '#' . $a['target_id'] );
			echo '<tr><td>' . esc_html( wp_date( 'Y/m/d H:i', strtotime( $a['created_at'] ) ) ) . '</td><td>' . esc_html( $actor ? $actor->display_name : 'سیستم/کاربر مهمان' ) . '</td><td>' . esc_html( $labels[ $a['action'] ] ?? $a['action'] ) . '</td><td>' . esc_html( $tname ) . '</td><td dir="ltr" style="text-align:left"><code>' . esc_html( $a['details'] ) . '</code></td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function tab_settings() {
		$o = hsum_opt();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'hsum_admin' ); ?><input type="hidden" name="action" value="hsum_admin_settings">
			<table class="form-table">
				<tr><th>نقش‌های نیازمند تأیید</th><td>
					<?php foreach ( HSUM_Membership::roles() as $k => $l ) : ?>
						<label style="margin-left:14px"><input type="checkbox" name="approval_roles[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $o['approval_roles'], true ) ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
					<p class="description">حساب‌هایی که مدیر سایت یا مدیر مدرسه می‌سازد همیشه فعال است.</p></td></tr>
				<tr><th><label for="hsum-keys">فیلدهای شماره موبایل</label></th><td>
					<textarea id="hsum-keys" name="phone_keys" rows="4" class="large-text code" dir="ltr"><?php echo esc_textarea( implode( "\n", (array) $o['phone_keys'] ) ); ?></textarea>
					<p class="description">کلید user_meta که افزونه‌های ثبت‌نام دیگر شماره را در آن ذخیره می‌کنند (هر خط یکی). مثال: <code>billing_phone</code> (ووکامرس)، <code>digits_phone</code> (دیجیتس)، <code>mobile</code>، <code>phone</code>. با این کار، کاربرانی که با افزونه‌ی دیگری ثبت‌نام کرده‌اند هم شناخته می‌شوند.</p></td></tr>
				<tr><th><label for="hsum-days">انقضای کد دعوت کلاس</label></th><td><input id="hsum-days" type="number" min="0" name="invite_days" value="<?php echo (int) $o['invite_days']; ?>" style="width:80px"> روز <span class="description">(۰ = بدون انقضا)</span></td></tr>
				<tr><th>پیامک‌های دعوت</th><td><label><input type="checkbox" name="sms_invites" value="1" <?php checked( $o['sms_invites'] ); ?>> دعوت معلم، والد دوم و معلمِ غیرعضو با پیامک (از طریق افزونه‌ی «ورود و پیامک»)</label>
					<?php if ( ! function_exists( 'hsma_send_sms' ) ) : ?><p class="description" style="color:#b32d2e">افزونه‌ی «ورود و پیامک» فعال نیست؛ پیامکی ارسال نمی‌شود.</p><?php endif; ?></td></tr>
			</table>
			<?php submit_button( 'ذخیره' ); ?>
		</form>
		<p>صفحه‌ی پنل: <a href="<?php echo esc_url( HSUM_Front::page_url() ); ?>" target="_blank"><?php echo esc_html( HSUM_Front::page_url() ); ?></a> — شورت‌کد <code>[hs_family]</code> — لینک دعوت کلاس: <code><?php echo esc_html( home_url( '/join/K-XXXXXX/' ) ); ?></code></p>
		<?php
	}
}
