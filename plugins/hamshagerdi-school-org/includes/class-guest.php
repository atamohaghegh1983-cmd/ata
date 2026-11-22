<?php
/**
 * صفحه‌ی ورود «مدیر مدرسه» برای کاربرِ واردنشده.
 * محتوا را به صفحه‌ی مهمانِ پنل (افزونه مدیریت کلاس ≥ 1.1.0) با فیلتر hs_guest_landing_roles می‌دهد.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_SO_Guest {

	public static function init() {
		add_filter( 'hs_guest_landing_roles', array( __CLASS__, 'role' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 101 );
	}

	public static function role( $roles ) {
		$roles['school_admin'] = array(
			'title' => 'پنل مدیر مدرسه',
			'short' => 'مدیر مدرسه',
			'lead'  => 'کادر و وظایف را سامان دهید و بی‌آنکه در کار معلم دخالت کنید، بر روند کلاس‌ها نظارت داشته باشید.',
			'link'  => 'school',
			'slugs' => array( 'school' ),
			'class' => 'hs-guest-school',
			'extra' => self::preview(),
			'items' => array(
				array( '🏫', 'معرفی مدرسه', 'مشخصات، نشانی و مقطع مدرسه' ),
				array( '👥', 'معاونین و کادر', 'ساخت حساب، سمت و شرح وظایف' ),
				array( '📌', 'وظیفه‌دهی', 'تعیین کار با مهلت و اولویت، و پیگیری گزارش' ),
				array( '📈', 'نظارت بر کلاس‌ها', 'آمار ۳۰ روز حضور، تکلیف، ارزیابی و امتیاز' ),
				array( '🗺️', 'چارت سازمانی', 'نمای کامل ساختار مدرسه' ),
				array( '🗓️', 'تقویم مدرسه', 'مناسبت‌ها، تعطیلات و یادآوری‌ها' ),
			),
		);
		return $roles;
	}

	private static function is_school_guest() {
		if ( is_user_logged_in() || ! is_page() ) {
			return false;
		}
		$p = get_queried_object();
		return $p instanceof WP_Post && 'school' === $p->post_name;
	}

	public static function enqueue() {
		if ( self::is_school_guest() ) {
			wp_enqueue_style( 'hs-so-guest', HS_SO_URL . 'assets/css/guest.css', ( wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array() ), HS_SO_VERSION );
		}
	}

	/** نمای نمونه‌ی پنل نظارت (اعداد نمایشی‌اند و برچسب «نمونه» دارند) */
	private static function preview() {
		$bars = array( array( 'پایه اول', 98 ), array( 'پایه دوم', 94 ), array( 'پایه سوم', 91 ), array( 'پایه چهارم', 97 ) );
		$h  = '<div class="hs-so-pv" aria-label="نمونه‌ی نمای نظارت">';
		$h .= '<div class="hs-so-pv-head"><span>نمای نظارت — ۳۰ روز اخیر</span><em>نمونه</em></div>';
		$h .= '<div class="hs-so-pv-stats"><div><b>۹۶٪</b><small>میانگین حضور</small></div><div><b>۱۲۸</b><small>تکلیف ثبت‌شده</small></div><div><b>۴۲</b><small>ارزیابی</small></div><div><b>۵</b><small>وظیفه‌ی باز</small></div></div>';
		$h .= '<div class="hs-so-pv-bars" aria-hidden="true">';
		foreach ( $bars as $b ) {
			$h .= '<div class="hs-so-pv-bar"><span>' . esc_html( $b[0] ) . '</span><i style="--w:' . (int) $b[1] . '%"></i></div>';
		}
		$h .= '</div><div class="hs-so-org" aria-hidden="true"><div class="hs-so-org-top">مدیر مدرسه</div>';
		$h .= '<div class="hs-so-org-row"><span>معاون آموزشی</span><span>معاون پرورشی</span><span>معاون اجرایی</span></div></div></div>';
		return $h;
	}
}

HS_SO_Guest::init();
