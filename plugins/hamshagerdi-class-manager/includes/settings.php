<?php
/**
 * تنظیمات ظاهر و داده‌ها: تنظیمات ← همشاگردی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Settings {

	const OPT_SHELL   = 'hs_shell_enabled';
	const OPT_PALETTE = 'hs_theme_palette';
	const OPT_STRIP   = 'hs_shell_strip_theme';
	const OPT_DELETE  = 'hs_delete_on_uninstall';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function palettes() {
		return array(
			'chap'   => array( 'label' => 'رنگ‌های لوگوی همشاگردی (پیش‌فرض)', 'p' => '#C24E00', 's' => '#F57C00', 'ink' => '#0F2F6B', 'rgb' => '194,78,0' ),
			'violet' => array( 'label' => 'بنفش شاد',   'p' => '#7C5CFC', 's' => '#B197FC', 'ink' => '#1F1B4D', 'rgb' => '124,92,252' ),
			'ocean'  => array( 'label' => 'آبی اقیانوسی', 'p' => '#2F80ED', 's' => '#56CCF2', 'ink' => '#14305E', 'rgb' => '47,128,237' ),
			'mint'   => array( 'label' => 'سبز نعناعی', 'p' => '#10B981', 's' => '#6EE7B7', 'ink' => '#064E3B', 'rgb' => '16,185,129' ),
			'sunset' => array( 'label' => 'نارنجی غروب', 'p' => '#F97316', 's' => '#FBBF24', 'ink' => '#7C2D12', 'rgb' => '249,115,22' ),
			'candy'  => array( 'label' => 'صورتی آبنباتی', 'p' => '#EC4899', 's' => '#F9A8D4', 'ink' => '#500724', 'rgb' => '236,72,153' ),
		);
	}

	public static function palette() {
		$all = self::palettes();
		$key = get_option( self::OPT_PALETTE, 'chap' );
		return isset( $all[ $key ] ) ? $all[ $key ] : $all['chap'];
	}

	public static function shell_enabled() {
		return 'no' !== get_option( self::OPT_SHELL, 'yes' );
	}

	public static function menu() {
		add_options_page( 'مدیریت کلاس', 'مدیریت کلاس', 'manage_options', 'hs-class-settings', array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting( 'hs_class_settings', self::OPT_SHELL, array( 'type' => 'string', 'sanitize_callback' => function ( $v ) { return 'no' === $v ? 'no' : 'yes'; } ) );
		register_setting( 'hs_class_settings', self::OPT_STRIP, array( 'type' => 'string', 'sanitize_callback' => function ( $v ) { return 'no' === $v ? 'no' : 'yes'; } ) );
		register_setting( 'hs_class_settings', self::OPT_PALETTE, array( 'type' => 'string', 'sanitize_callback' => function ( $v ) { return isset( self::palettes()[ $v ] ) ? $v : 'chap'; } ) );
		register_setting( 'hs_class_settings', self::OPT_DELETE, array( 'type' => 'string', 'sanitize_callback' => function ( $v ) { return 'yes' === $v ? 'yes' : 'no'; } ) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$cur = get_option( self::OPT_PALETTE, 'chap' );
		?>
		<div class="wrap" dir="rtl">
			<h1>مدیریت کلاس — تنظیمات</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'hs_class_settings' ); ?>
				<table class="form-table">
					<tr><th>چیدمان داشبوردی پنل</th><td>
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_SHELL ); ?>" value="yes" <?php checked( self::shell_enabled() ); ?>> فعال (سایدبار + داشبورد، بدون هدر و فوتر قالب)</label><br>
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_SHELL ); ?>" value="no" <?php checked( ! self::shell_enabled() ); ?>> غیرفعال (نمایش داخل قالب سایت)</label>
					</td></tr>
					<tr><th>حذف استایل‌های قالب در پنل</th><td>
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_STRIP ); ?>" value="yes" <?php checked( 'no' !== get_option( self::OPT_STRIP, 'yes' ) ); ?>> بله (پیشنهادی؛ جلوگیری از تداخل ظاهری)</label><br>
						<label><input type="radio" name="<?php echo esc_attr( self::OPT_STRIP ); ?>" value="no" <?php checked( 'no' === get_option( self::OPT_STRIP, 'yes' ) ); ?>> خیر</label>
					</td></tr>
					<tr><th>پالت رنگی</th><td>
						<?php foreach ( self::palettes() as $k => $p ) : ?>
							<label style="display:inline-flex;align-items:center;gap:6px;margin:0 0 8px 14px;">
								<input type="radio" name="<?php echo esc_attr( self::OPT_PALETTE ); ?>" value="<?php echo esc_attr( $k ); ?>" <?php checked( $cur, $k ); ?>>
								<span style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,<?php echo esc_attr( $p['p'] ); ?>,<?php echo esc_attr( $p['s'] ); ?>);display:inline-block"></span>
								<?php echo esc_html( $p['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</td></tr>
					<tr><th>حذف داده‌ها هنگام پاک‌کردن افزونه</th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( self::OPT_DELETE ); ?>" value="yes" <?php checked( 'yes', get_option( self::OPT_DELETE, 'no' ) ); ?>> همه جدول‌ها، کلاس‌ها و اطلاعات دانش‌آموزان با حذف افزونه پاک شود</label>
						<p class="description">پیش‌فرض خاموش است تا اطلاعات به‌طور ناخواسته از بین نرود.</p>
					</td></tr>
				</table>
				<?php submit_button( 'ذخیره' ); ?>
			</form>
			<p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=hs-bale-settings' ) ); ?>">تنظیمات ربات بله ←</a></p>
		</div>
		<?php
	}
}

HS_Settings::init();
