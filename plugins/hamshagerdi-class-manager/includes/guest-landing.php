<?php
/**
 * صفحه‌ی ورود هر بخش پنل برای کاربرِ واردنشده:
 * معرفی امکانات همان بخش (معلم / دانش‌آموز / والدین) + جعبه‌ی ورود و ثبت‌نام.
 *
 * - جعبه‌ی ورود از افزونه‌ی «ثبت‌نام و ورود با موبایل» می‌آید (شورت‌کد [hs_auth])؛ اگر نصب نبود، فرم ورود وردپرس.
 * - افزونه‌های دیگر نقش خودشان را با فیلتر hs_guest_landing_roles اضافه می‌کنند (مثلاً ساختار مدرسه ← مدیر مدرسه).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Guest_Landing {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 100 );
		// پیوند «ورود» به صفحه‌ای از پنل ← همان صفحه (که خودش ورود دارد)
		add_filter( 'login_url', array( __CLASS__, 'login_url' ), 30, 2 );
	}

	public static function roles() {
		$roles = array(
			'teacher' => array(
				'title' => 'پنل معلم',
				'short' => 'معلم',
				'lead'  => 'همه‌ی کارهای کلاس در یک جا؛ از حضور و غیاب تا گزارش برای والدین.',
				'link'  => 'classes',
				'items' => array(
					array( '🏫', 'کلاس‌ها و دانش‌آموزان', 'ساخت کلاس، ثبت دانش‌آموز یا ورود یک‌جا از اکسل، کد دعوت' ),
					array( '✅', 'حضور و غیاب', 'ثبت سریع روزانه و آمار هر دانش‌آموز' ),
					array( '⭐', 'تشویق و امتیاز', 'امتیاز رفتاری، نشان‌ها و جدول کلاس' ),
					array( '📝', 'تکالیف', 'تکلیف عادی و آنلاین، دفتر تکلیف و پیگیری انجام' ),
					array( '📊', 'ارزیابی و گزارش', 'نمره، ارزیابی توصیفی و گزارش پیشرفت' ),
					array( '💬', 'ارتباط با والدین', 'پیام خصوصی، گفتگوی کلاس و اطلاعیه با عکس' ),
					array( '🗓️', 'برنامه هفتگی و بودجه‌بندی', 'هماهنگ با کتاب‌های درسی و تقویم مناسبت‌ها' ),
					array( '🎲', 'ابزارهای کلاس', 'تاس، سکه، گروه‌ساز، سروصداسنج، کاربرگ و استودیو محتوا' ),
				),
			),
			'student' => array(
				'title' => 'دنیای دانش‌آموز',
				'short' => 'دانش‌آموز',
				'lead'  => 'تکلیف‌ها، امتیازها و کارهای خودت را ببین.',
				'link'  => 'student',
				'slugs' => array( 'student', 'online-hw', 'notebook' ),
				'items' => array(
					array( '📝', 'تکالیف من', 'دیدن تکلیف‌ها و پاسخ دادن به تکلیف آنلاین' ),
					array( '📒', 'دفتر تکالیف', 'همه‌ی کارهایی که باید انجام بدهی، مرتب' ),
					array( '⭐', 'امتیاز و نشان', 'امتیازهایی که گرفته‌ای و جایگاهت در کلاس' ),
					array( '📢', 'اطلاعیه‌های کلاس', 'خبرهای معلم و کلاس' ),
					array( '🗂️', 'پورتفولیو', 'کارهای خوبت در یک پرونده' ),
					array( '🔑', 'پیوستن به کلاس', 'با کد دعوتی که معلم داده' ),
				),
			),
			// «والدین» را افزونه‌ی «پنل والدین» با فیلتر hs_guest_landing_roles اضافه می‌کند
		);
		/**
		 * افزودن/تغییر نقش‌ها. کلیدهای هر نقش:
		 * title, short, lead, link (نامک صفحه پنل), slugs (نامک‌هایی که این صفحه را نشان می‌دهند),
		 * items [[آیکون, عنوان, توضیح]], extra (HTML بالای امکانات), class (کلاس CSS اضافه)
		 */
		return apply_filters( 'hs_guest_landing_roles', $roles );
	}

	/** نامک صفحه ← نقش ('' = صفحه‌ی اصلی پنل، همه‌ی نقش‌ها) */
	public static function role_for_slug( $slug ) {
		if ( 'panel' === $slug || '' === (string) $slug ) {
			return '';
		}
		foreach ( self::roles() as $k => $r ) {
			if ( ! empty( $r['slugs'] ) && in_array( $slug, (array) $r['slugs'], true ) ) {
				return $k;
			}
		}
		return 'teacher'; // بقیه‌ی صفحه‌های پنل ابزار معلم‌اند
	}

	public static function login_url( $url, $redirect ) {
		if ( ! $redirect || is_admin() || ! HS_Settings::shell_enabled() ) {
			return $url;
		}
		$path  = trim( (string) wp_parse_url( $redirect, PHP_URL_PATH ), '/' );
		$panel = trim( (string) wp_parse_url( home_url( '/panel/' ), PHP_URL_PATH ), '/' );
		if ( $path === $panel || 0 === strpos( $path . '/', $panel . '/' ) ) {
			return $redirect;
		}
		return $url;
	}

	public static function enqueue() {
		if ( is_user_logged_in() || ! HS_Shell::is_panel_request() || ! HS_Settings::shell_enabled() ) {
			return;
		}
		wp_enqueue_style( 'hs-guest', HS_CLASS_URL . 'assets/css/guest-landing.css', array( 'hs-theme' ), HS_CLASS_VERSION );
		if ( is_callable( array( 'HSMA_Front', 'enqueue' ) ) ) {
			HSMA_Front::enqueue(); // استایل جعبه‌ی ورود در head بارگذاری شود
		}
	}

	private static function logo_html() {
		$name = get_bloginfo( 'name' );
		$id   = (int) get_theme_mod( 'custom_logo' );
		$src  = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		if ( ! $src ) {
			$src = get_site_icon_url( 128 );
		}
		if ( $src ) {
			return '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $name ) . '">';
		}
		return '<span aria-hidden="true">🎒</span> ' . esc_html( $name );
	}

	private static function login_box( $role, $redirect ) {
		if ( shortcode_exists( 'hs_auth' ) ) {
			return do_shortcode( sprintf( '[hs_auth role="%s" redirect="%s"]', esc_attr( $role ), esc_url( $redirect ) ) );
		}
		return '<div class="hs-guest-wplogin">' . wp_login_form( array(
			'echo'           => false,
			'redirect'       => $redirect,
			'label_username' => 'نام کاربری',
			'label_password' => 'رمز عبور',
			'label_log_in'   => 'ورود به پنل',
		) ) . '</div>';
	}

	/** کل صفحه‌ی مهمان (داخل قالب پنل) */
	public static function render( $slug ) {
		$roles    = self::roles();
		$role     = self::role_for_slug( $slug );
		$r        = ( $role && isset( $roles[ $role ] ) ) ? $roles[ $role ] : null;
		$redirect = home_url( add_query_arg( null, null ) );
		ob_start();
		?>
		<div class="hs-guest hs-guest-<?php echo esc_attr( $role ? $role : 'all' ); ?> <?php echo esc_attr( $r['class'] ?? '' ); ?>" dir="rtl">
			<?php echo $role ? HS_Guest_Bg::html( $role ) : ''; // phpcs:ignore — تصویر از «تنظیمات ← تصویر صفحه‌ی ورود» ?>
			<header class="hs-guest-top">
				<a class="hs-guest-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo self::logo_html(); // phpcs:ignore ?></a>
				<nav class="hs-guest-switch" aria-label="بخش‌ها">
					<?php foreach ( $roles as $k => $rr ) : ?>
						<a href="<?php echo esc_url( HS_Shell::url( $rr['link'] ) ); ?>" class="<?php echo $k === $role ? 'is-on' : ''; ?>"><?php echo esc_html( $rr['short'] ); ?></a>
					<?php endforeach; ?>
				</nav>
			</header>

			<main class="hs-guest-grid">
				<header class="hs-guest-hero">
					<?php if ( $r ) : ?>
						<h1><?php echo esc_html( $r['title'] ); ?></h1>
						<p><?php echo esc_html( $r['lead'] ); ?></p>
					<?php else : ?>
						<h1>یک پنل برای همه‌ی مدرسه</h1>
						<p>معلم، دانش‌آموز، والد یا مدیر مدرسه هستید؟ وارد شوید یا ثبت‌نام کنید؛ هر کس بخش خودش را می‌بیند.</p>
					<?php endif; ?>
				</header>

				<aside class="hs-guest-side"><?php echo self::login_box( $role, $redirect ); // phpcs:ignore ?></aside>

				<section class="hs-guest-feat" aria-label="امکانات">
					<?php if ( $r ) : ?>
						<?php echo ! empty( $r['extra'] ) ? $r['extra'] : ''; // phpcs:ignore ?>
						<h2>در این پنل چه امکاناتی دارید؟</h2>
						<ul class="hs-guest-items">
							<?php foreach ( $r['items'] as $it ) : ?>
								<li><span class="hs-guest-ic" aria-hidden="true"><?php echo esc_html( $it[0] ); ?></span><div><strong><?php echo esc_html( $it[1] ); ?></strong><small><?php echo esc_html( $it[2] ); ?></small></div></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<div class="hs-guest-cards">
							<?php foreach ( $roles as $rr ) : ?>
								<a class="hs-guest-card" href="<?php echo esc_url( HS_Shell::url( $rr['link'] ) ); ?>">
									<h2><?php echo esc_html( $rr['title'] ); ?></h2>
									<ul><?php foreach ( array_slice( $rr['items'], 0, 4 ) as $it ) : ?><li><?php echo esc_html( $it[1] ); ?></li><?php endforeach; ?></ul>
									<span>امکانات کامل ←</span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			</main>
			<footer class="hs-guest-foot">© <?php echo esc_html( get_bloginfo( 'name' ) ); ?></footer>
		</div>
		<?php
		return ob_get_clean();
	}
}

HS_Guest_Landing::init();
