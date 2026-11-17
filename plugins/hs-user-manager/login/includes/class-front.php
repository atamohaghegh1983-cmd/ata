<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSMA_Front {

	public static function init() {
		add_shortcode( 'hs_auth', array( __CLASS__, 'render' ) );
		add_shortcode( 'hs_register', array( __CLASS__, 'render' ) );
		add_shortcode( 'hs_login', array( __CLASS__, 'render' ) );

		// همه پیوندهای «ورود» و «ثبت‌نام» سایت به صفحه ما بروند (wp-login.php برای مدیر همچنان کار می‌کند)
		add_filter( 'login_url', array( __CLASS__, 'login_url' ), 20, 2 );
		add_filter( 'register_url', array( __CLASS__, 'register_url' ), 20 );
	}

	public static function page_url() {
		$pid = (int) HSMA_Util::opt( 'page_id' );
		return ( $pid && 'publish' === get_post_status( $pid ) ) ? get_permalink( $pid ) : '';
	}

	public static function login_url( $url, $redirect ) {
		if ( ! HSMA_Util::opt( 'replace_login' ) || is_admin() ) {
			return $url;
		}
		$p = self::page_url();
		if ( ! $p ) {
			return $url;
		}
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $p ) : $p;
	}

	public static function register_url( $url ) {
		$p = HSMA_Util::opt( 'replace_login' ) ? self::page_url() : '';
		return $p ? $p : $url;
	}

	/** ساخت صفحه «ورود و ثبت‌نام» هنگام فعال‌سازی (اگر نبود) */
	public static function create_page() {
		$o = (array) get_option( 'hsma_settings', array() );
		if ( ! empty( $o['page_id'] ) && get_post( $o['page_id'] ) && 'trash' !== get_post_status( $o['page_id'] ) ) {
			return (int) $o['page_id'];
		}
		$existing = get_page_by_path( 'login' );
		if ( $existing && false !== strpos( $existing->post_content, '[hs_auth' ) ) {
			$pid = $existing->ID;
		} else {
			$pid = wp_insert_post( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'ورود و ثبت‌نام',
				'post_name'    => $existing ? 'mobile-login' : 'login',
				'post_content' => '[hs_auth]',
			) );
		}
		if ( $pid && ! is_wp_error( $pid ) ) {
			$o['page_id'] = (int) $pid;
			update_option( 'hsma_settings', $o );
		}
		return (int) $pid;
	}

	public static function enqueue() {
		wp_register_style( 'hsma', HSMA_URL . 'assets/auth.css', ( wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array() ), HSMA_VERSION );
		wp_enqueue_style( 'hsma' );
		wp_enqueue_script( 'hsma', HSMA_URL . 'assets/auth.js', array(), HSMA_VERSION, true );
	}

	/**
	 * شورت‌کد [hs_auth role="teacher" redirect="/panel/classes/"]
	 * فقط جعبه‌ی ورود/ثبت‌نام. افزونه‌های دیگر (مثلاً پنل معلم) آن را داخل صفحه‌ی خودشان قرار می‌دهند.
	 */
	public static function render( $atts = array(), $content = '', $tag = 'hs_auth' ) {
		$atts     = shortcode_atts( array( 'role' => '', 'redirect' => '' ), (array) $atts, $tag );
		$redirect = $atts['redirect'] ? esc_url_raw( $atts['redirect'] ) : ( isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '' );

		if ( is_user_logged_in() ) {
			$u = wp_get_current_user();
			self::enqueue();
			return '<div class="hsma-box hsma-done"><p>شما با حساب <strong>' . esc_html( $u->display_name ) . '</strong> وارد شده‌اید.</p>'
				. '<p><a class="hsma-btn" href="' . esc_url( HSMA_Util::redirect_url( $redirect ) ) . '">رفتن به پنل</a> '
				. '<a class="hsma-link" href="' . esc_url( wp_logout_url( get_permalink() ) ) . '">خروج</a></p></div>';
		}
		$role = sanitize_key( $atts['role'] );
		// نقش پیشنهادی از لینک صفحه‌ی اصلی (مثلاً ?role=teacher)؛ فقط نقش‌های مجاز
		if ( ! $role && isset( $_GET['role'] ) ) {
			$q = sanitize_key( wp_unslash( $_GET['role'] ) );
			$role = in_array( $q, array( 'parent', 'student', 'teacher', 'school_admin' ), true ) ? $q : '';
		}
		return self::form_html( $redirect, 'hs_login' === $tag ? 'pw' : 'otp', $role );
	}

	/** جعبه ورود/ثبت‌نام */
	public static function form_html( $redirect = '', $start = 'otp', $role = '' ) {
		self::enqueue();
		wp_localize_script( 'hsma', 'hsmaCfg', array(
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'hsma' ),
			'redirect' => $redirect,
			'start'    => $start,
			'role'     => $role,
		) );

		$roles = HSMA_Util::allowed_roles();
		ob_start();
		?>
		<div class="hsma-box" id="hsma" dir="rtl">
			<div class="hsma-tabs" role="tablist">
				<button type="button" class="hsma-tab is-on" data-tab="otp">ورود / ثبت‌نام با کد پیامکی</button>
				<button type="button" class="hsma-tab" data-tab="pw">ورود با رمز</button>
			</div>

			<ol class="hsma-steps" aria-label="مراحل ثبت‌نام">
				<li data-s="1">شماره موبایل</li><li data-s="2">نقش</li><li data-s="3">مشخصات</li>
			</ol>
			<div class="hsma-msg" role="alert" aria-live="polite"></div>

			<!-- مرحله ۱: موبایل -->
			<form class="hsma-step" data-step="mobile" novalidate>
				<label>شماره موبایل
					<input type="tel" name="mobile" inputmode="numeric" autocomplete="tel" placeholder="09121234567" maxlength="14" required>
				</label>
				<button type="submit" class="hsma-btn">دریافت کد</button>
				<p class="hsma-hint">اگر حساب دارید وارد می‌شوید؛ اگر ندارید، ثبت‌نام می‌کنید.</p>
			</form>

			<!-- مرحله ۲: کد -->
			<form class="hsma-step" data-step="code" hidden novalidate>
				<p class="hsma-hint">کد ۵ رقمی ارسال‌شده به <strong class="hsma-mobile"></strong> را وارد کنید.</p>
				<label>کد تأیید
					<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" class="hsma-code" required>
				</label>
				<button type="submit" class="hsma-btn">تأیید</button>
				<p class="hsma-row">
					<button type="button" class="hsma-link hsma-resend" disabled>ارسال دوباره <span class="hsma-timer"></span></button>
					<button type="button" class="hsma-link hsma-edit">تغییر شماره</button>
				</p>
			</form>

			<!-- مرحله ۳: ثبت‌نام -->
			<!-- مرحله ۳: نقش -->
			<form class="hsma-step" data-step="role" hidden novalidate>
				<p class="hsma-hint">شماره <strong class="hsma-mobile"></strong> تأیید شد. شما کدام هستید؟</p>
				<fieldset class="hsma-roles">
					<legend class="screen-reader-text">نقش</legend>
					<?php
					$hsma_role_info = array(
						'parent'       => array( '👨‍👩‍👧', 'پیگیری درس و ارتباط با معلم' ),
						'student'      => array( '🎒', 'تکالیف، امتیاز و کلاس من' ),
						'teacher'      => array( '👩‍🏫', 'مدیریت کلاس و دانش‌آموزان' ),
						'school_admin' => array( '🏛️', 'کادر، وظایف و نظارت' ),
					);
					foreach ( $roles as $key => $label ) :
						$ri = isset( $hsma_role_info[ $key ] ) ? $hsma_role_info[ $key ] : array( '👤', '' );
						?>
						<label class="hsma-role"><input type="radio" name="role" value="<?php echo esc_attr( $key ); ?>"><span><i aria-hidden="true"><?php echo esc_html( $ri[0] ); ?></i><b><?php echo esc_html( $label ); ?></b><small><?php echo esc_html( $ri[1] ); ?></small></span></label>
					<?php endforeach; ?>
				</fieldset>
				<button type="submit" class="hsma-btn">ادامه</button>
			</form>

			<!-- مرحله ۴: مشخصات -->
			<form class="hsma-step" data-step="register" hidden novalidate>
				<p class="hsma-hint">ثبت‌نام به‌عنوان <strong class="hsma-role-label"></strong> — <button type="button" class="hsma-link hsma-back-role">تغییر نقش</button></p>
				<div class="hsma-grid">
					<label>نام <input type="text" name="first_name" autocomplete="given-name" required></label>
					<label>نام خانوادگی <input type="text" name="last_name" autocomplete="family-name" required></label>
				</div>

				<div class="hsma-extra" data-for="student" hidden>
					<label>کد دعوت کلاس <small>(اختیاری؛ از معلم بگیرید)</small>
						<input type="text" name="invite_code" autocomplete="off" dir="ltr">
					</label>
				</div>
				<div class="hsma-extra" data-for="parent" hidden>
					<p class="hsma-hint">برای دیدن کارنامه و پیام‌های فرزندتان، اطلاعاتی که معلم داده را وارد کنید (اختیاری؛ بعداً هم می‌شود).</p>
					<div class="hsma-grid">
						<label>نام کاربری فرزند <input type="text" name="child_username" autocomplete="off" dir="ltr"></label>
						<label>کد والد <input type="text" name="child_code" autocomplete="off" dir="ltr"></label>
					</div>
				</div>
				<div class="hsma-extra" data-for="teacher school_admin" hidden>
					<label>نام مدرسه <small>(اختیاری)</small> <input type="text" name="school"></label>
				</div>

				<label>رمز عبور <small>(دست‌کم ۶ کاراکتر؛ برای ورود در اپ موبایل)</small>
					<input type="password" name="password" autocomplete="new-password" minlength="6" required dir="ltr">
				</label>
				<button type="submit" class="hsma-btn">ساخت حساب</button>
			</form>

			<!-- ورود با رمز -->
			<form class="hsma-step" data-step="pw" hidden novalidate>
				<label>شماره موبایل یا نام کاربری
					<input type="text" name="mobile" autocomplete="username" dir="ltr" required>
				</label>
				<label>رمز عبور
					<input type="password" name="password" autocomplete="current-password" dir="ltr" required>
				</label>
				<button type="submit" class="hsma-btn">ورود</button>
				<p class="hsma-hint">رمز را فراموش کرده‌اید؟ از «ورود با کد پیامکی» وارد شوید.</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
