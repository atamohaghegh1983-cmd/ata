<?php
/**
 * صفحه‌ی معلم: «پنل دانش‌آموزان»
 * زبانه‌ها: تنظیمات کلاس (ظاهر، بازی‌ها، ورود با کارت) · دسترسی‌ها · کارت ورود · کتابخانه
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_Teacher {

	public static function init() {
		add_shortcode( 'hs_student_settings', array( __CLASS__, 'page' ) );
		add_action( 'admin_post_hssp_cls', array( __CLASS__, 'handle' ) );
	}

	private static function tabs() {
		return array( 'general' => '⚙️ تنظیمات', 'perms' => '🔐 دسترسی‌ها', 'cards' => '🎫 کارت ورود', 'library' => '📖 کتابخانه', 'preview' => '👀 نمای دانش‌آموز' );
	}

	public static function page() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid     = get_current_user_id();
		$classes = HSSP_Util::teacher_classes( $uid );
		if ( ! $classes ) {
			return '<div class="hs-card"><p>ابتدا یک کلاس بسازید.</p></div>';
		}
		$cid = isset( $_GET['cls'] ) && in_array( (int) $_GET['cls'], $classes, true ) ? (int) $_GET['cls'] : $classes[0]; // phpcs:ignore
		$tab = isset( $_GET['tab'], self::tabs()[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore
		$h   = '<div class="hssp-t" dir="rtl">' . HSSP_Util::notice();
		if ( count( $classes ) > 1 ) {
			$h .= '<nav class="hssp-pills">';
			foreach ( $classes as $c ) {
				$h .= '<a class="' . ( $c === $cid ? 'is-on' : '' ) . '" href="' . esc_url( add_query_arg( array( 'cls' => $c, 'tab' => $tab ) ) ) . '">🏫 ' . esc_html( get_the_title( $c ) ) . '</a>';
			}
			$h .= '</nav>';
		}
		$h .= '<nav class="hssp-tabs">';
		foreach ( self::tabs() as $k => $v ) {
			$h .= '<a class="' . ( $k === $tab ? 'is-on' : '' ) . '" href="' . esc_url( add_query_arg( array( 'cls' => $cid, 'tab' => $k ) ) ) . '">' . esc_html( $v ) . '</a>';
		}
		$h .= '</nav>';
		switch ( $tab ) {
			case 'perms':
				$h .= self::perms( $cid );
				break;
			case 'cards':
				$h .= self::cards( $cid );
				break;
			case 'library':
				$h .= HSSP_Library::teacher_view( $cid );
				break;
			case 'preview':
				$h .= self::preview( $cid );
				break;
			default:
				$h .= self::general( $cid );
		}
		return $h . '</div>';
	}

	private static function form( $op, $cid, $inner, $attrs = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $attrs . '>' . wp_nonce_field( 'hssp_cls', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="hssp_cls"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><input type="hidden" name="class_id" value="' . (int) $cid . '">' . $inner . '</form>';
	}

	private static function general( $cid ) {
		$s     = HSSP_Settings::get( $cid );
		$grade = HSSP_Util::grade_num( $cid );
		$auto  = ( $grade < 0 || $grade <= 3 ) ? 'کودکانه' : 'ساده';
		$h     = '<section class="hs-card"><h3>🎨 ظاهر پنل دانش‌آموزان</h3><p class="hs-muted">پایه‌ی کلاس: ' . esc_html( (string) get_post_meta( $cid, '_hs_grade', true ) ?: 'تعیین نشده' ) . '</p>';
		$opts  = array( 'auto' => 'خودکار بر اساس پایه (الان: ' . $auto . ')', 'kid' => '🦊 کودکانه — رنگی و بزرگ (پیش‌دبستانی تا سوم)', 'teen' => '📘 ساده — آرام و جدی‌تر (چهارم به بالا)' );
		$in    = '';
		foreach ( $opts as $k => $v ) {
			$in .= '<label class="hssp-radio"><input type="radio" name="theme" value="' . esc_attr( $k ) . '"' . checked( $s['theme'], $k, false ) . '> ' . esc_html( $v ) . '</label>';
		}
		$h .= self::form( 'theme', $cid, $in . '<button class="hs-btn hs-btn-primary">ذخیره</button>' ) . '</section>';

		$cur  = HSSP_Settings::class_games( $cid );
		$in   = '<div class="hssp-games-pick">';
		foreach ( HSSP_Settings::games() as $id => $g ) {
			$fit = HSSP_Settings::suitable( $id, $grade );
			$in .= '<label class="' . ( $fit ? 'fit' : '' ) . '"><input type="checkbox" name="games[]" value="' . esc_attr( $id ) . '"' . checked( in_array( $id, $cur, true ), true, false ) . '> <span>' . esc_html( $g[0] . ' ' . $g[1] ) . '</span><small>پایه ' . ( $g[3] ? (int) $g[3] : 'پیش' ) . ' تا ' . (int) $g[4] . ( $fit ? ' · مناسب کلاس' : '' ) . '</small></label>';
		}
		$in .= '</div><label class="hssp-radio"><input type="checkbox" name="auto_games" value="1"' . checked( null === $s['games'], true, false ) . '> خودکار: همیشه بازی‌های مناسب پایه (با تغییر پایه عوض می‌شود)</label>';
		$h  .= '<section class="hs-card"><h3>🎮 بازی‌های کلاس</h3><p class="hs-muted">سطح سؤال‌ها هم خودکار با پایه تنظیم می‌شود (مثلاً جمع تا ۱۰ برای اول، تا ۱۰۰ برای سوم). هر دانش‌آموز روزی حداکثر ۵ امتیاز از بازی می‌گیرد، آن هم اگر دست‌کم نصف جواب‌ها درست باشد.</p>'
			. self::form( 'games', $cid, $in . '<button class="hs-btn hs-btn-primary">ذخیره</button>' ) . '</section>';

		$h .= '<section class="hs-card"><h3>🎫 ورود با کارت کلاس</h3><p class="hs-muted">برای بچه‌هایی که موبایل ندارند: هر دانش‌آموز یک کارت با QR و رمز ۴ رقمی می‌گیرد. فقط حساب‌های دانش‌آموزی (نه معلم و والد) این‌طور وارد می‌شوند و بعد از ۵ رمز اشتباه، ۱۵ دقیقه قفل می‌شود.</p>'
			. self::form( 'login', $cid, '<input type="hidden" name="on" value="' . ( $s['login'] ? 0 : 1 ) . '"><button class="hs-btn ' . ( $s['login'] ? '' : 'hs-btn-primary' ) . '">' . ( $s['login'] ? 'خاموش کردن ورود با کارت' : 'روشن کردن ورود با کارت' ) . '</button>' );
		if ( $s['login'] ) {
			$h .= '<p>کد کلاس: <code class="hssp-code" dir="ltr">' . esc_html( HSSP_Login::class_code( $cid ) ) . '</code> · <a href="' . esc_url( add_query_arg( array( 'cls' => $cid, 'tab' => 'cards' ) ) ) . '">چاپ کارت‌ها ←</a></p>';
		}
		return $h . '</section>';
	}

	private static function perms( $cid ) {
		$h = '<section class="hs-card"><h3>🔐 دانش‌آموزان این کلاس چه چیزهایی ببینند؟</h3><p class="hs-muted">«پیش‌فرض» یعنی پیروی از تنظیم مدرسه. اگر مدرسه بخشی را بسته باشد، اینجا قفل است 🔒.</p>';
		$in = '<table class="hssp-perm"><tbody>';
		foreach ( HSSP_Perms::features() as $f => $v ) {
			$cur  = HSSP_Perms::rule( 'class', $cid, $f );
			$lock = ! HSSP_Perms::ceiling( $cid, $f );
			$in  .= '<tr><td>' . esc_html( $v[1] . ' ' . $v[0] ) . '</td><td>' . ( $lock ? '🔒 بسته از طرف مدرسه' : self::tri( 'p[' . $f . ']', $cur ) ) . '</td></tr>';
		}
		$h .= self::form( 'perms', $cid, $in . '</tbody></table><button class="hs-btn hs-btn-primary">ذخیره</button>' ) . '</section>';

		$students = array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) );
		$sid      = isset( $_GET['stu'] ) && in_array( (int) $_GET['stu'], $students, true ) ? (int) $_GET['stu'] : 0; // phpcs:ignore
		$h       .= '<section class="hs-card"><h3>👤 تنظیم برای یک دانش‌آموز</h3><form method="get" class="hssp-inline"><input type="hidden" name="cls" value="' . (int) $cid . '"><input type="hidden" name="tab" value="perms"><select name="stu" onchange="this.form.submit()"><option value="0">— انتخاب دانش‌آموز —</option>';
		foreach ( $students as $s ) {
			$u  = get_userdata( $s );
			$h .= $u ? '<option value="' . (int) $s . '"' . selected( $s, $sid, false ) . '>' . esc_html( $u->display_name ) . '</option>' : '';
		}
		$h .= '</select></form>';
		if ( $sid ) {
			$in = '<input type="hidden" name="stu" value="' . (int) $sid . '"><table class="hssp-perm"><tbody>';
			foreach ( HSSP_Perms::features() as $f => $v ) {
				$lock = ! HSSP_Perms::ceiling( $cid, $f );
				$in  .= '<tr><td>' . esc_html( $v[1] . ' ' . $v[0] ) . '</td><td>' . ( $lock ? '🔒' : self::tri( 'p[' . $f . ']', HSSP_Perms::rule( 'student', $sid, $f ), 'مثل کلاس' ) ) . '</td><td class="hssp-eff">' . ( HSSP_Perms::can( $sid, $f, $cid ) ? '✅ می‌بیند' : '⛔ نمی‌بیند' ) . '</td></tr>';
			}
			$h .= self::form( 'perms_student', $cid, $in . '</tbody></table><button class="hs-btn hs-btn-primary">ذخیره</button>' );
		}
		return $h . '</section>';
	}

	public static function tri( $name, $cur, $inherit = 'پیش‌فرض' ) {
		$o = array( '' => $inherit, '1' => '✅ باز', '0' => '⛔ بسته' );
		$h = '<select name="' . esc_attr( $name ) . '">';
		foreach ( $o as $k => $v ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . ( ( null === $cur && '' === $k ) || ( null !== $cur && (string) $cur === (string) $k ) ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>';
		}
		return $h . '</select>';
	}

	private static function cards( $cid ) {
		$s = HSSP_Settings::get( $cid );
		if ( ! $s['login'] ) {
			return '<section class="hs-card"><p>ورود با کارت برای این کلاس خاموش است.</p>' . self::form( 'login', $cid, '<input type="hidden" name="on" value="1"><button class="hs-btn hs-btn-primary">روشن کردن</button>' ) . '</section>';
		}
		$code     = HSSP_Login::class_code( $cid );
		$students = array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) );
		$h        = '<section class="hs-card hssp-noprint"><h3>🎫 کارت‌های ورود — کد کلاس <code dir="ltr">' . esc_html( $code ) . '</code></h3>'
			. '<p class="hs-muted">کارت‌ها را چاپ کنید و به هر دانش‌آموز بدهید (یا برای والدینش بفرستید). با اسکن QR مستقیم به صفحه‌ی رمز می‌رود. کارت را مثل رمز نگه دارند.</p>'
			. '<p><button type="button" class="hs-btn hs-btn-primary" onclick="window.print()">🖨 چاپ کارت‌ها</button> '
			. self::form( 'reset_code', $cid, '<button class="hs-btn">کد کلاس جدید</button>', 'class="hssp-inline-f" onsubmit="return confirm(\'همه‌ی کارت‌های قبلی باطل می‌شوند. ادامه؟\')"' ) . ' '
			. self::form( 'reset_pins', $cid, '<button class="hs-btn">رمز جدید برای همه</button>', 'class="hssp-inline-f" onsubmit="return confirm(\'رمز همه‌ی دانش‌آموزان عوض می‌شود. ادامه؟\')"' ) . '</p></section><div class="hssp-cards">';
		$n = 0;
		foreach ( $students as $sid ) {
			$u = get_userdata( $sid );
			if ( ! $u ) {
				continue;
			}
			if ( ! HSSP_Login::eligible( $sid ) ) {
				$h .= '<div class="hssp-card off"><strong>' . esc_html( $u->display_name ) . '</strong><small>این حساب نقش غیر دانش‌آموزی دارد و با کارت وارد نمی‌شود.</small></div>';
				continue;
			}
			$pin = HSSP_Login::ensure_pin( $sid );
			$url = HSSP_Login::url( $code, $sid );
			$n++;
			$h .= '<div class="hssp-card"><div class="hssp-card-top">🎒 ' . esc_html( get_bloginfo( 'name' ) ) . '</div><div class="hssp-card-body"><div class="hssp-qr" data-qr="' . esc_attr( $url ) . '"></div><div>'
				. '<strong>' . esc_html( $u->display_name ) . '</strong><small>' . esc_html( get_the_title( $cid ) ) . '</small>'
				. '<div class="hssp-card-kv">کد کلاس: <b dir="ltr">' . esc_html( $code ) . '</b></div><div class="hssp-card-kv">رمز من: <b dir="ltr">' . esc_html( $pin ) . '</b></div></div></div>'
				. '<div class="hssp-card-url" dir="ltr">' . esc_html( preg_replace( '#^https?://#', '', HSSP_Login::url() ) ) . '</div>'
				. self::form( 'reset_pin', $cid, '<input type="hidden" name="stu" value="' . (int) $sid . '"><button class="hssp-link hssp-noprint">رمز جدید</button>' ) . '</div>';
		}
		if ( ! $n ) {
			$h .= '<p>دانش‌آموزی برای کارت ندارید.</p>';
		}
		return $h . '</div>';
	}

	private static function preview( $cid ) {
		$students = array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) );
		$h        = '<section class="hs-card"><h3>👀 دیدن پنل از چشم دانش‌آموز</h3><p class="hs-muted">فقط مشاهده؛ چیزی تغییر نمی‌کند.</p><ul class="hssp-list">';
		foreach ( $students as $s ) {
			$u  = get_userdata( $s );
			$h .= $u ? '<li><span>' . esc_html( $u->display_name ) . '</span><a class="hs-btn" href="' . esc_url( add_query_arg( array( 'student_id' => $s, 'class_id' => $cid ), HSSP_Util::page_url( 'student' ) ) ) . '">مشاهده</a></li>' : '';
		}
		return $h . '</ul></section>';
	}

	private static function tri_val( $v ) {
		return '' === $v || null === $v ? null : ( '1' === (string) $v ? 1 : 0 );
	}

	public static function handle() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'hssp_cls' ) ) {
			wp_die( 'نامعتبر' );
		}
		$uid = get_current_user_id();
		$cid = (int) ( $_POST['class_id'] ?? 0 );
		$op  = sanitize_key( $_POST['op'] ?? '' );
		if ( ! HSSP_Util::is_class_teacher( $uid, $cid ) ) {
			HSSP_Util::back( 'اجازه‌ی این کار را ندارید.' );
		}
		$p = isset( $_POST['p'] ) && is_array( $_POST['p'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['p'] ) ) : array(); // phpcs:ignore
		switch ( $op ) {
			case 'theme':
				HSSP_Settings::save( $cid, array( 'theme' => sanitize_key( $_POST['theme'] ?? 'auto' ) ) );
				HSSP_Util::back( 'ظاهر ذخیره شد.' );
				break;
			case 'games':
				$g = ! empty( $_POST['auto_games'] ) ? null : array_map( 'sanitize_key', (array) ( $_POST['games'] ?? array() ) ); // phpcs:ignore
				HSSP_Settings::save( $cid, array( 'games' => $g ) );
				HSSP_Util::back( 'بازی‌ها ذخیره شد.' );
				break;
			case 'login':
				HSSP_Settings::save( $cid, array( 'login' => ! empty( $_POST['on'] ) ) );
				if ( ! empty( $_POST['on'] ) ) {
					HSSP_Login::class_code( $cid );
				}
				HSSP_DB::log( 'login_toggle', $cid, array( 'on' => ! empty( $_POST['on'] ) ) );
				HSSP_Util::back( ! empty( $_POST['on'] ) ? 'ورود با کارت روشن شد.' : 'ورود با کارت خاموش شد.' );
				break;
			case 'reset_code':
				HSSP_Login::reset_code( $cid );
				HSSP_DB::log( 'code_reset', $cid );
				HSSP_Util::back( 'کد کلاس عوض شد؛ کارت‌های جدید را چاپ کنید.' );
				break;
			case 'reset_pins':
			case 'reset_pin':
				$list = 'reset_pin' === $op ? array( (int) ( $_POST['stu'] ?? 0 ) ) : array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) );
				foreach ( $list as $s ) {
					if ( HSSP_Util::in_class( $s, $cid ) && HSSP_Login::eligible( $s ) ) {
						HSSP_Login::ensure_pin( $s, true );
					}
				}
				HSSP_DB::log( 'pin_reset', $cid, array( 'n' => count( $list ) ) );
				HSSP_Util::back( 'رمز جدید ساخته شد.' );
				break;
			case 'perms':
				foreach ( HSSP_Perms::features() as $f => $v ) {
					if ( array_key_exists( $f, $p ) ) {
						HSSP_Perms::set( 'class', $cid, $f, self::tri_val( $p[ $f ] ) );
					}
				}
				HSSP_Util::back( 'دسترسی‌های کلاس ذخیره شد.' );
				break;
			case 'perms_student':
				$sid = (int) ( $_POST['stu'] ?? 0 );
				if ( ! HSSP_Util::in_class( $sid, $cid ) ) {
					HSSP_Util::back( 'این دانش‌آموز در کلاس نیست.' );
				}
				foreach ( HSSP_Perms::features() as $f => $v ) {
					if ( array_key_exists( $f, $p ) ) {
						HSSP_Perms::set( 'student', $sid, $f, self::tri_val( $p[ $f ] ) );
					}
				}
				HSSP_Util::back( 'ذخیره شد.' );
		}
		HSSP_Util::back( 'نامعتبر.' );
	}
}

HSSP_Teacher::init();
