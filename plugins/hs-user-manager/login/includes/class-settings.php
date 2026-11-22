<?php
/**
 * منوی «لاگین»
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_Settings {

	const SLUG = 'hsma';

	public static function tabs() {
		return array(
			'sms'      => 'پیامک',
			'login'    => 'ورود و ثبت‌نام',
			'bale'     => 'پیام‌رسان بله',
			'security' => 'امنیت',
			'users'    => 'کاربران',
			'bulk'     => 'ارسال گروهی',
			'log'      => 'گزارش ارسال',
		);
	}

	public static function init() {
		add_action( 'admin_menu', function () {
			add_menu_page( 'لاگین', 'لاگین', 'manage_options', self::SLUG, array( __CLASS__, 'page' ), 'dashicons-smartphone', 71 );
		} );
		foreach ( array( 'save', 'test', 'page', 'import', 'block', 'unblock', 'bulk' ) as $a ) {
			add_action( 'admin_post_hsma_' . $a, array( __CLASS__, 'do_' . $a ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( HSMA_FILE ), function ( $l ) {
			array_unshift( $l, '<a href="' . esc_url( self::url() ) . '">تنظیمات</a>' );
			return $l;
		} );
		// نشانی قدیمی (تنظیمات ← ثبت‌نام با موبایل)
		add_action( 'admin_init', function () {
			if ( isset( $_GET['page'] ) && 'hsma' === $_GET['page'] && false !== strpos( $_SERVER['REQUEST_URI'] ?? '', 'options-general.php' ) ) {
				wp_safe_redirect( self::url() );
				exit;
			}
		} );
	}

	public static function url( $tab = '', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $tab ? array( 'tab' => $tab ) : array(), $args ), admin_url( 'admin.php' ) );
	}

	private static function guard( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.' );
		}
		check_admin_referer( $nonce );
	}

	private static function back( $msg, $tab = '' ) {
		$tab = $tab ? $tab : sanitize_key( wp_unslash( $_POST['tab'] ?? '' ) );
		wp_safe_redirect( self::url( $tab, array( 'hsma_msg' => rawurlencode( $msg ) ) ) );
		exit;
	}

	/* ---------- ذخیره ---------- */

	public static function do_save() {
		self::guard( 'hsma_save' );
		$in  = wp_unslash( $_POST );
		$tab = sanitize_key( $in['tab'] ?? '' );
		$o   = HSMA_Util::opt();
		$t   = function ( $k ) use ( $in ) {
			return sanitize_text_field( HSMA_Util::en_digits( $in[ $k ] ?? '' ) );
		};

		if ( 'sms' === $tab ) {
			// سامانه‌ی پیامک در پینوا تنظیم می‌شود.
			$mode            = sanitize_key( $in['otp_mode'] ?? 'auto' );
			$o['otp_mode']   = in_array( $mode, array( 'auto', 'meli_otp', 'pattern', 'text', 'pwsms' ), true ) ? $mode : 'auto';
			$o['pattern_code'] = $t( 'pattern_code' );
			$o['pattern_var']  = $t( 'pattern_var' ) ?: 'code';
			$o['msg_text']     = sanitize_text_field( $in['msg_text'] ?? '' );
			if ( false === strpos( $o['msg_text'], '{code}' ) ) {
				$o['msg_text'] = 'کد ورود شما به {site}: {code}';
			}
			$o['test_mode'] = empty( $in['test_mode'] ) ? 0 : 1;
		} elseif ( 'login' === $tab ) {
			$o['roles']          = array_values( array_intersect( array_keys( HSMA_Util::role_labels() ), (array) ( $in['roles'] ?? array() ) ) );
			$o['redirect']       = '/' . ltrim( $t( 'redirect' ) ?: '/panel/', '/' );
			$o['replace_login']  = empty( $in['replace_login'] ) ? 0 : 1;
			$o['woo_login']      = empty( $in['woo_login'] ) ? 0 : 1;
			$o['menu_locations'] = array_values( array_map( 'sanitize_key', (array) ( $in['menu_locations'] ?? array() ) ) );
			$o['menu_label']     = sanitize_text_field( $in['menu_label'] ?? '' ) ?: 'ثبت‌نام / ورود';
		} elseif ( 'bale' === $tab ) {
			$o['bale_on']    = empty( $in['bale_on'] ) ? 0 : 1;
			$o['bale_token'] = sanitize_text_field( $in['bale_token'] ?? '' ) ?: $o['bale_token'];
			$o['bale_bot']   = $t( 'bale_bot' );
		}
		foreach ( array( 'provider', 'api_key', 'username', 'password', 'template', 'sender', 'param', 'custom_url', 'custom_method', 'custom_headers', 'custom_body', 'custom_ok', 'guest_landing', 'logo_url', 'sms_mode', 'body_id' ) as $k ) {
			unset( $o[ $k ] ); // باقیمانده‌ی نسخه‌های قدیمی
		}
		update_option( 'hsma_settings', $o );
		self::back( 'ذخیره شد.', $tab );
	}

	public static function do_import() {
		self::guard( 'hsma_import' );
		self::back( 'سامانه‌ی پیامک اکنون در افزونه‌ی پینوا تنظیم می‌شود.', 'sms' );
	}

	/** ارسال آزمایشی کد (حتی در حالت آزمایشی، واقعاً ارسال می‌شود) */
	public static function do_test() {
		self::guard( 'hsma_test' );
		$m = HSMA_Util::mobile( sanitize_text_field( wp_unslash( $_POST['mobile'] ?? '' ) ) );
		if ( ! $m ) {
			self::back( 'شماره موبایل درست نیست.', 'sms' );
		}
		$r = HSMA_SMS::sms( $m, '12345' );
		if ( true !== $r ) {
			self::back( 'ارسال ناموفق — ' . $r, 'sms' );
		}
		self::back( 'کد آزمایشی 12345 به ' . $m . ' ارسال شد (روش: ' . self::mode_label( HSMA_SMS::mode() ) . '). اگر رسید، «حالت آزمایشی» را خاموش کنید.', 'sms' );
	}

	public static function do_page() {
		self::guard( 'hsma_page' );
		HSMA_Front::create_page();
		self::back( 'صفحه آماده است.', 'login' );
	}

	public static function do_block() {
		self::guard( 'hsma_block' );
		$raw = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$id  = HSMA_Util::mobile( $raw ) ?: ( filter_var( $raw, FILTER_VALIDATE_IP ) ? $raw : '' );
		if ( ! $id ) {
			self::back( 'شماره موبایل یا IP درست نیست.', 'security' );
		}
		HSMA_Util::block( $id, absint( $_POST['minutes'] ?? 0 ) );
		self::back( $id . ' مسدود شد.', 'security' );
	}

	public static function do_unblock() {
		self::guard( 'hsma_unblock' );
		HSMA_Util::unblock( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) );
		self::back( 'از فهرست مسدودی برداشته شد.', 'security' );
	}

	public static function do_bulk() {
		self::guard( 'hsma_bulk' );
		$role = sanitize_key( wp_unslash( $_POST['role'] ?? '' ) );
		$msg  = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$list = array_filter( array_map( array( 'HSMA_Util', 'mobile' ), preg_split( '/[\s,،]+/u', HSMA_Util::en_digits( wp_unslash( $_POST['numbers'] ?? '' ) ) ) ) );
		if ( $role ) {
			foreach ( get_users( array( 'role' => $role, 'fields' => 'ID', 'number' => 1000 ) ) as $uid ) {
				$m = HSMA_Users::mobile_of( $uid );
				if ( $m ) {
					$list[] = $m;
				}
			}
		}
		$list = array_values( array_unique( $list ) );
		if ( ! $list || '' === trim( $msg ) ) {
			self::back( 'گیرنده یا متن پیامک خالی است.', 'bulk' );
		}
		$r = HSMA_Gateways::send( $list, $msg );
		self::back( true === $r ? sprintf( 'پیامک برای %d شماره ارسال شد.', count( $list ) ) : 'ارسال ناموفق — ' . $r, 'bulk' );
	}

	/* ---------- نمایش ---------- */

	public static function mode_label( $m ) {
		return array(
			'auto'     => 'خودکار',
			'meli_otp' => 'سرویس کد تأیید ملی‌پیامک',
			'pattern'  => 'الگو (پترن)',
			'text'     => 'متن عادی',
			'pwsms'    => 'افزونه‌ی پیامک ووکامرس',
		)[ $m ] ?? $m;
	}

	private static function form_open( $tab, $action = 'hsma_save' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
	}

	public static function page() {
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'sms';
		$msg  = isset( $_GET['hsma_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['hsma_msg'] ) ) : '';
		echo '<div class="wrap" dir="rtl"><h1>ورود و پیامک</h1>';
		if ( $msg ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
		if ( HSMA_Util::opt( 'test_mode' ) ) {
			echo '<div class="notice notice-warning"><p><strong>حالت آزمایشی روشن است:</strong> کد ورود برای کاربران ارسال نمی‌شود و فقط در زبانه‌ی «پیامک» نمایش داده می‌شود.</p></div>';
		}
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $l ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $k === $tab ? 'nav-tab-active' : '', esc_url( self::url( $k ) ), esc_html( $l ) );
		}
		echo '</nav><div style="max-width:900px">';
		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		echo '</div></div>';
	}

	private static function tab_sms() {
		$o    = HSMA_Util::opt();
		$o    = HSMA_Util::opt();
		$err  = get_option( 'hsma_last_error' );
		self::form_open( 'sms' );
		?>
		<h2>سامانه‌ی پیامک: پینوا</h2>
		<table class="form-table"><tr><th>وضعیت</th><td>
			<?php if ( HSMA_Gateways::pinova() ) : ?>
				<strong style="color:#00795A">✓ پینوا فعال است.</strong>
				<p class="description">همه‌ی پیامک‌های سایت (کد ورود، خلاصه‌ی روزانه، دعوت‌ها، خبر تأیید عضویت) از طریق پینوا ارسال می‌شوند. سامانه، نام کاربری، شماره‌ی فرستنده و متن یا الگوی کد ورود را در <a href="<?php echo esc_url( admin_url( 'admin.php?page=pinova' ) ); ?>">تنظیمات پینوا</a> وارد کنید.</p>
				<p class="description">فرم ورود خود پینوا را در برگه‌ها قرار ندهید؛ ورود و انتخاب نقش با همین افزونه است.</p>
			<?php else : ?>
				<strong style="color:#b32d2e">✗ پینوا نصب یا فعال نیست؛ هیچ پیامکی ارسال نمی‌شود.</strong>
			<?php endif; ?>
		</td></tr></table>
		<h2>ارسال کد ورود</h2>
		<table class="form-table">
			<tr><th>حالت آزمایشی</th><td><label><input type="checkbox" name="test_mode" value="1" <?php checked( $o['test_mode'] ); ?>> کد برای کاربران ارسال نشود؛ پایین همین صفحه نمایش داده شود</label></td></tr>
		</table>
		<?php submit_button( 'ذخیره' ); ?>
		</form>

		<h2>ارسال آزمایشی</h2>
		<?php self::form_open( 'sms', 'hsma_test' ); ?>
			<input name="mobile" dir="ltr" placeholder="09121234567"> <button class="button">ارسال کد 12345</button>
		</form>
		<?php if ( $err ) : ?>
			<p><strong>آخرین خطا</strong> (<?php echo esc_html( wp_date( 'Y/m/d H:i', $err['t'] ) ); ?>): <code><?php echo esc_html( $err['msg'] ); ?></code></p>
		<?php endif; ?>
		<?php if ( $o['test_mode'] ) : ?>
			<h2>کدهای حالت آزمایشی</h2>
			<table class="widefat striped" style="max-width:480px"><thead><tr><th>زمان</th><th>موبایل</th><th>کد</th></tr></thead><tbody>
			<?php foreach ( (array) get_option( 'hsma_test_log', array() ) as $row ) : ?>
				<tr><td><?php echo esc_html( wp_date( 'H:i:s', $row['t'] ) ); ?></td><td dir="ltr"><?php echo esc_html( $row['m'] ); ?></td><td><strong><?php echo esc_html( $row['c'] ); ?></strong></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif;
	}

	private static function tab_login() {
		$o   = HSMA_Util::opt();
		$url = HSMA_Front::page_url();
		self::form_open( 'login' );
		?>
		<table class="form-table">
			<tr><th>صفحه‌ی ورود</th><td><?php echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>' : 'وجود ندارد'; ?>
				<p class="description">شورت‌کد <code>[hs_auth]</code> — اختیاری: <code>role="teacher"</code>، <code>redirect="/panel/"</code>. لینک ورود/پنل: <code>[hs_auth_link]</code></p></td></tr>
			<tr><th>نقش‌های قابل انتخاب</th><td>
				<?php foreach ( HSMA_Util::role_labels() as $k => $l ) : ?>
					<label style="margin-left:16px"><input type="checkbox" name="roles[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $o['roles'], true ) ); ?>> <?php echo esc_html( $l ); ?></label>
				<?php endforeach; ?></td></tr>
			<tr><th><label for="hsma-r">بعد از ورود برود به</label></th><td><input id="hsma-r" dir="ltr" name="redirect" value="<?php echo esc_attr( $o['redirect'] ); ?>"></td></tr>
			<tr><th>منوی سایت</th><td>
				<label>متن گزینه <input type="text" name="menu_label" value="<?php echo esc_attr( $o['menu_label'] ); ?>"></label><br>
				<label><input type="checkbox" name="menu_locations[]" value="__auto__" <?php checked( in_array( '__auto__', (array) $o['menu_locations'], true ) ); ?>> خودکار (منوی اصلی)</label><br>
				<?php foreach ( get_registered_nav_menus() as $loc => $desc ) : ?>
					<label><input type="checkbox" name="menu_locations[]" value="<?php echo esc_attr( $loc ); ?>" <?php checked( in_array( $loc, (array) $o['menu_locations'], true ) ); ?>> <?php echo esc_html( $desc ); ?></label><br>
				<?php endforeach; ?></td></tr>
			<tr><th>پیوندهای ورود</th><td>
				<label><input type="checkbox" name="replace_login" value="1" <?php checked( $o['replace_login'] ); ?>> پیوندهای «ورود» و «ثبت‌نام» سایت به صفحه‌ی ورود بروند (wp-login.php برای مدیر باقی است)</label><br>
				<label><input type="checkbox" name="woo_login" value="1" <?php checked( $o['woo_login'] ); ?>> فرم ورود «حساب کاربری من» ووکامرس ← ورود با موبایل</label></td></tr>
		</table>
		<?php submit_button( 'ذخیره' ); ?>
		</form>
		<?php if ( ! $url ) : self::form_open( 'login', 'hsma_page' ); ?><button class="button">ساخت صفحه‌ی ورود</button></form><?php endif;
	}

	private static function tab_bale() {
		$o = HSMA_Util::opt();
		self::form_open( 'bale' );
		?>
		<p>اگر فعال باشد، کد ورود اول در <strong>بله</strong> (به حسابی که با همان شماره ساخته شده) ارسال می‌شود؛ اگر کاربر بله نداشت یا ارسال نشد، پیامک می‌رود. ارسال در بله ارزان‌تر از پیامک است.</p>
		<table class="form-table">
			<tr><th>فعال</th><td><label><input type="checkbox" name="bale_on" value="1" <?php checked( $o['bale_on'] ); ?>> ارسال کد با بله</label></td></tr>
			<tr><th><label for="hsma-bt">کلید دسترسی (API access key)</label></th><td><input id="hsma-bt" type="password" class="regular-text" dir="ltr" name="bale_token" placeholder="<?php echo $o['bale_token'] ? 'ذخیره شده — برای تغییر وارد کنید' : ''; ?>"></td></tr>
			<tr><th><label for="hsma-bb">شناسه‌ی بات</label></th><td><input id="hsma-bb" dir="ltr" name="bale_bot" value="<?php echo esc_attr( $o['bale_bot'] ); ?>">
				<p class="description">از پنل «سفیر» بله (safir.bale.ai) بگیرید.</p></td></tr>
		</table>
		<?php submit_button( 'ذخیره' ); ?>
		</form>
		<?php
	}

	private static function tab_security() {
		?>
		<p>محدودیت‌های خودکار: هر کد ۲ دقیقه اعتبار و ۵ تلاش دارد؛ هر شماره حداکثر ۵ کد در ساعت و هر IP حداکثر ۱۵ کد در ساعت. شماره‌ای که ۳ بار پشت سر هم کد را غلط بزند، ۱ ساعت مسدود می‌شود. حساب‌های مدیر سایت فقط با رمز وارد می‌شوند.</p>
		<h2>مسدود کردن</h2>
		<?php self::form_open( 'security', 'hsma_block' ); ?>
			<input name="id" dir="ltr" placeholder="0912… یا IP" required>
			<select name="minutes"><option value="60">۱ ساعت</option><option value="1440">۱ روز</option><option value="10080">۱ هفته</option><option value="0">دائمی</option></select>
			<button class="button">مسدود کن</button>
		</form>
		<h2>فهرست مسدودی</h2>
		<table class="widefat striped" style="max-width:600px"><thead><tr><th>موبایل / IP</th><th>تا</th><th></th></tr></thead><tbody>
		<?php
		$b = HSMA_Util::blocks();
		if ( ! $b ) {
			echo '<tr><td colspan="3">خالی است.</td></tr>';
		}
		foreach ( $b as $id => $until ) {
			echo '<tr><td dir="ltr">' . esc_html( $id ) . '</td><td>' . ( $until ? esc_html( wp_date( 'Y/m/d H:i', $until ) ) : 'دائمی' ) . '</td><td>';
			self::form_open( 'security', 'hsma_unblock' );
			echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '"><button class="button-link">برداشتن</button></form></td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function tab_users() {
		$link = function ( $fmt, $role = '' ) {
			return wp_nonce_url( add_query_arg( array( 'action' => 'hsma_export', 'fmt' => $fmt, 'role' => $role ), admin_url( 'admin-post.php' ) ), 'hsma_export' );
		};
		echo '<p>ستون «موبایل» به فهرست کاربران و فیلد «شماره موبایل» به صفحه‌ی ویرایش کاربر اضافه شده است.</p>';
		echo '<h2>خروجی کاربرانِ دارای موبایل</h2><table class="widefat striped" style="max-width:600px"><tbody>';
		$rows = array( '' => 'همه' ) + HSMA_Util::role_labels();
		foreach ( $rows as $r => $l ) {
			printf( '<tr><td>%s</td><td><a class="button" href="%s">اکسل (CSV)</a> <a class="button" href="%s">مخاطبین گوشی (VCF)</a></td></tr>', esc_html( $l ), esc_url( $link( 'csv', $r ) ), esc_url( $link( 'vcf', $r ) ) );
		}
		echo '</tbody></table>';
	}

	private static function tab_bulk() {
		self::form_open( 'bulk', 'hsma_bulk' );
		?>
		<p>پیامک به همه‌ی کاربرانِ یک نقش (مثلاً همه‌ی والدین) یا به شماره‌های دلخواه.</p>
		<table class="form-table">
			<tr><th>گیرندگان</th><td><select name="role"><option value="">— فقط شماره‌های زیر —</option>
				<?php foreach ( HSMA_Util::role_labels() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>">همه‌ی <?php echo esc_html( $l ); ?>‌ها</option><?php endforeach; ?></select></td></tr>
			<tr><th>شماره‌های دیگر</th><td><textarea name="numbers" rows="3" class="large-text" dir="ltr" placeholder="0912…، 0935…"></textarea></td></tr>
			<tr><th>متن</th><td><textarea name="message" rows="4" class="large-text" required></textarea>
				<p class="description">برای ارسال با الگو: سطر اول <code>pattern:کدالگو</code> و سطرهای بعد <code>متغیر:مقدار</code>.</p></td></tr>
		</table>
		<?php submit_button( 'ارسال', 'primary', 'submit', true, array( 'onclick' => "return confirm('پیامک ارسال شود؟')" ) ); ?>
		</form>
		<?php
	}

	private static function tab_log() {
		echo '<p>۱۰۰ ارسال آخر (کدهای ورود پوشانده شده‌اند).</p><table class="widefat striped"><thead><tr><th>زمان</th><th>گیرنده</th><th>متن</th><th>نتیجه</th></tr></thead><tbody>';
		$log = (array) get_option( HSMA_Gateways::LOG, array() );
		if ( ! $log ) {
			echo '<tr><td colspan="4">هنوز چیزی ارسال نشده است.</td></tr>';
		}
		foreach ( $log as $l ) {
			printf( '<tr><td>%s</td><td dir="ltr">%s</td><td>%s</td><td>%s</td></tr>', esc_html( wp_date( 'Y/m/d H:i', $l['t'] ) ), esc_html( wp_html_excerpt( $l['to'], 60, '…' ) ), esc_html( $l['m'] ), $l['ok'] ? '✅' : '❌ ' . esc_html( $l['r'] ) );
		}
		echo '</tbody></table>';
	}
}
