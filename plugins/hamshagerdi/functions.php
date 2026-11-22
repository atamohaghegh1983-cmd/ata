<?php
defined( 'ABSPATH' ) || exit;
define( 'HST_VER', '2.5.0' );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 64, 'width' => 64, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	register_nav_menus( array( 'main' => 'منوی اصلی', 'footer' => 'منوی فوتر' ) );
} );

add_action( 'wp_enqueue_scripts', function () {
	// فونت‌ها و توکن‌ها از افزونه‌ی «مدیریت کلاس» (hs-core) می‌آیند؛ اگر نبود، از فونت سیستم استفاده می‌شود.
	$deps = wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array();
	wp_enqueue_style( 'hst', get_template_directory_uri() . '/assets/site.css', $deps, HST_VER );
	wp_enqueue_script( 'hst', get_template_directory_uri() . '/assets/site.js', array(), HST_VER, true );
}, 20 );

/** جلوگیری از چشمک حالت تیره: قبل از رندر صفحه اعمال می‌شود */
add_action( 'wp_head', function () {
	echo "<script>document.documentElement.className+=' js';try{var t=localStorage.getItem('hst-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>\n";
}, 1 );

/** نشانی پنل و ورود (از افزونه‌ها؛ قابل تغییر در سفارشی‌سازی) */
function hst_url( $key ) {
	$map = array( 'panel' => '/panel/', 'login' => '/panel/' );
	return esc_url( home_url( get_theme_mod( 'hst_url_' . $key, $map[ $key ] ) ) );
}

add_action( 'customize_register', function ( $c ) {
	$c->add_section( 'hst', array( 'title' => 'همشاگردی', 'priority' => 30 ) );
	$fields = array(
		'hst_hero_title' => array( 'تیتر اصلی', 'هر روزِ کلاس،' ),
		'hst_hero_mark'  => array( 'بخش رنگی تیتر', 'یک قصه‌ی تازه' ),
		'hst_hero_text'  => array( 'متن زیر تیتر', 'همشاگردی معلم، خانواده و بچه‌ها را کنار هم می‌آورد: حضور و غیاب، تکلیف، امتیاز، پیام و پورتفولیو؛ همه در یک خانه‌ی شاد.' ),
		'hst_url_panel'  => array( 'نشانی پنل', '/panel/' ),
		'hst_url_login'  => array( 'نشانی ورود', '/panel/' ),
		'hst_phone'      => array( 'تلفن پشتیبانی', '' ),
		'hst_email'      => array( 'ایمیل پشتیبانی', '' ),
		'hst_cta_title'  => array( 'تیتر دعوت پایین صفحه', 'بیا با هم شروع کنیم' ),
		'hst_cta_text'   => array( 'متن دعوت پایین صفحه', 'وارد شو، نقشت را انتخاب کن و پنل مخصوص خودت را ببین.' ),
	);
	// سه کارت اختیاری صفحه‌ی اصلی (زیر هم)
	$c->add_section( 'hst_extra', array( 'title' => 'همشاگردی: کارت‌های اختیاری صفحه‌ی اصلی', 'priority' => 31, 'description' => 'هر کارت را با تیک «نمایش» فعال کنید. کارت‌ها زیر بخش «نقش‌ها» نمایش داده می‌شوند.' ) );
	for ( $n = 1; $n <= 3; $n++ ) {
		$c->add_setting( "hst_x{$n}_on", array( 'default' => false, 'sanitize_callback' => 'wp_validate_boolean' ) );
		$c->add_control( "hst_x{$n}_on", array( 'label' => "کارت {$n}: نمایش", 'section' => 'hst_extra', 'type' => 'checkbox' ) );
		foreach ( array( 'title' => 'عنوان', 'text' => 'متن', 'btn' => 'متن دکمه (اختیاری)', 'url' => 'لینک دکمه (اختیاری)' ) as $k => $l ) {
			$c->add_setting( "hst_x{$n}_{$k}", array( 'default' => '', 'sanitize_callback' => 'url' === $k ? 'esc_url_raw' : ( 'text' === $k ? 'sanitize_textarea_field' : 'sanitize_text_field' ) ) );
			$c->add_control( "hst_x{$n}_{$k}", array( 'label' => "کارت {$n}: {$l}", 'section' => 'hst_extra', 'type' => 'text' === $k ? 'textarea' : ( 'url' === $k ? 'url' : 'text' ) ) );
		}
		$c->add_setting( "hst_x{$n}_img", array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$c->add_control( new WP_Customize_Media_Control( $c, "hst_x{$n}_img", array( 'label' => "کارت {$n}: تصویر", 'section' => 'hst_extra', 'mime_type' => 'image' ) ) );
	}
	foreach ( $fields as $id => $f ) {
		$c->add_setting( $id, array( 'default' => $f[1], 'sanitize_callback' => 'sanitize_text_field' ) );
		$c->add_control( $id, array( 'label' => $f[0], 'section' => 'hst', 'type' => 'text' ) );
	}
} );

function hst_mod( $k, $d = '' ) { return get_theme_mod( $k, $d ); }

/** آیکن‌های خطی ساده (SVG داخلی؛ بدون کتابخانه‌ی بیرونی) */
function hst_icon( $n ) {
	$p = array(
		'check'   => '<path d="M5 12l4 4L19 6"/>',
		'book'    => '<path d="M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2zM4 5v16"/>',
		'cal'     => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'chat'    => '<path d="M4 5h16v11H8l-4 4z"/>',
		'star'    => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
		'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
		'file'    => '<path d="M6 2h9l5 5v15H6zM14 2v6h6"/>',
		'shield'  => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
		'school'  => '<path d="M3 21h18M5 21V9l7-5 7 5v12M10 21v-5h4v5"/>',
		'teacher' => '<circle cx="12" cy="7" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
		'parent'  => '<circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20c0-3 3-5 6-5s6 2 6 5M15 20c0-2 1-4 4-4"/>',
		'kid'     => '<circle cx="12" cy="8" r="4"/><path d="M8 7c1-2 7-2 8 0M6 21c0-3 3-5 6-5s6 2 6 5"/>',
		'arrow'   => '<path d="M14 6l-6 6 6 6"/>',
		'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/>',
		'moon'    => '<path d="M20 14A8 8 0 1110 4a6 6 0 0010 10z"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'phone'   => '<path d="M5 3h4l2 5-3 2a12 12 0 006 6l2-3 5 2v4a2 2 0 01-2 2A17 17 0 013 5a2 2 0 012-2z"/>',
	);
	return '<svg class="hst-i" viewBox="0 0 24 24" aria-hidden="true">' . ( $p[ $n ] ?? '' ) . '</svg>';
}

/** فیتوکد: اعتبار سازنده (شفاف، در فوتر) */
function hst_credit() {
	echo '<p class="hst-credit">طراحی و ساخت با اسکیل <a href="https://fitocode.ir/?utm_source=fitocode-skill&amp;utm_medium=footer" target="_blank" rel="noopener nofollow">فیتوکد</a></p>';
}

/** نشانی صفحه‌ی ورود (از افزونه‌ی «ورود و پیامک»)، با نقش پیشنهادی */
function hst_login_url( $role = '' ) {
	// دانش‌آموز با موبایل وارد نمی‌شود: صفحه‌ی ورود با کد کلاس و رمز ۴ رقمی (از والدین یا کارت کلاس)
	if ( 'student' === $role && class_exists( 'HSSP_Login' ) && ! is_user_logged_in() ) {
		return esc_url( HSSP_Login::url() );
	}
	$custom = get_theme_mod( 'hst_url_login', '' );
	$o      = get_option( 'hsma_settings' );
	if ( $custom && '/panel/' !== $custom ) {
		$url = home_url( $custom );
	} elseif ( is_array( $o ) && ! empty( $o['page_id'] ) && get_permalink( $o['page_id'] ) ) {
		$url = get_permalink( $o['page_id'] );
	} else {
		$url = home_url( '/panel/' );
	}
	$url = add_query_arg( 'redirect_to', rawurlencode( home_url( '/panel/' ) ), $url );
	return esc_url( $role ? add_query_arg( 'role', $role, $url ) : $url );
}

/** نقش کاربر واردشده (برای پیام «شما با نقش … وارد شده‌اید») */
function hst_current_role_label() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$r = (array) wp_get_current_user()->roles;
	$map = array( 'administrator' => 'مدیر سایت', 'school_admin' => 'مدیر مدرسه', 'teacher' => 'معلم', 'parent' => 'والدین', 'student' => 'دانش‌آموز', 'hsum_pending' => 'در انتظار تأیید' );
	foreach ( $map as $k => $l ) {
		if ( in_array( $k, $r, true ) ) {
			return $l;
		}
	}
	return 'کاربر';
}

/** کاربر واردشده: به‌جای صفحه‌ی ورود، به پنل خودش برود و پیام روشن ببیند */
function hst_logged_notice() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$u = wp_get_current_user();
	return '<aside class="hst-whoami" role="status"><button type="button" class="hst-whoami-x" aria-label="بستن" onclick="this.parentNode.hidden=true">×</button>'
		. '<span class="hst-whoami-av">' . esc_html( mb_substr( $u->display_name, 0, 1 ) ) . '</span>'
		. '<div><b>' . esc_html( $u->display_name ) . '</b><small>' . esc_html( hst_current_role_label() ) . '</small>'
		. '<p><a href="' . esc_url( home_url( '/panel/' ) ) . '">پنل من</a> · <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">خروج</a></p></div></aside>';
}

/** میانبرهای کاربر واردشده (فقط منوی نقش خودش) */
function hst_my_shortcuts() {
	if ( ! class_exists( 'HS_Shell' ) ) {
		return '<a class="hst-mine-item" href="' . esc_url( home_url( '/panel/' ) ) . '"><span>🏠</span>پنل من</a>';
	}
	$type = HS_Shell::view_type();
	$h    = '';
	$n    = 0;
	foreach ( HS_Shell::menu( $type ) as $grp ) {
		foreach ( (array) $grp[1] as $it ) {
			if ( $n++ >= 8 ) {
				break 2;
			}
			$h .= '<a class="hst-mine-item" href="' . esc_url( HS_Shell::url( $it[0] ) ) . '"><span>' . esc_html( $it[2] ) . '</span>' . esc_html( $it[1] ) . '</a>';
		}
	}
	return $h;
}

/** لوگو: لوگوی سفارشی وردپرس، وگرنه لوگوی همراه قالب */
function hst_logo( $full = false ) {
	if ( ! $full && has_custom_logo() ) {
		return wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'medium', false, array( 'class' => 'hst-logo', 'alt' => get_bloginfo( 'name' ) ) );
	}
	$f = $full ? 'logo.png' : 'logo-mark.png';
	return '<img class="' . ( $full ? 'hst-logo-full' : 'hst-logo' ) . '" src="' . esc_url( get_template_directory_uri() . '/assets/' . $f ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" width="' . ( $full ? 320 : 64 ) . '" height="' . ( $full ? 269 : 36 ) . '">';
}

/** شخصیت «همشاگردی» (برگرفته از دو آدمک لوگو) */
function hst_kid( $color = '#F57C00', $extra = '', $cls = '' ) {
	return '<svg class="hst-kid ' . esc_attr( $cls ) . '" viewBox="0 0 120 140" style="color:' . esc_attr( $color ) . '" aria-hidden="true"><use href="#hst-kid"/>' . $extra . '</svg>';
}
add_action( 'wp_footer', function () {
	// «مدادی»: شخصیت اختصاصی همشاگردی — مداد کوچولوی شاد
	echo '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><symbol id="hst-kid" viewBox="0 0 120 140">'
		. '<g class="k-body">'
		. '<path d="M30 20h60a8 8 0 0 1 8 8v10H22V28a8 8 0 0 1 8-8z" fill="#FF8FA3"/>'
		. '<rect x="22" y="36" width="76" height="10" fill="#B8C2D9"/><rect x="22" y="40" width="76" height="2" fill="#94A0BC"/>'
		. '<rect x="22" y="46" width="76" height="56" rx="4" fill="currentColor"/>'
		. '<rect x="44" y="46" width="12" height="56" fill="#fff" opacity=".18"/><rect x="74" y="46" width="8" height="56" fill="#000" opacity=".08"/>'
		. '<path d="M22 102h76l-30 30a11 11 0 0 1-16 0z" fill="#F6D2A8"/><path d="M52 124l8 8 8-8a11 11 0 0 1-16 0z" fill="#2B2F45"/>'
		. '<path d="M22 70q-14 4-16 16" stroke="currentColor" stroke-width="7" fill="none" stroke-linecap="round"/><circle cx="6" cy="87" r="5.5" fill="#F6D2A8"/>'
		. '<g class="k-wave"><path d="M98 70q14-6 16-20" stroke="currentColor" stroke-width="7" fill="none" stroke-linecap="round"/><circle cx="114" cy="48" r="5.5" fill="#F6D2A8"/></g>'
		. '</g>'
		. '<g class="k-head"><g class="k-eyes"><ellipse cx="47" cy="66" rx="8" ry="9.5" fill="#fff"/><ellipse cx="73" cy="66" rx="8" ry="9.5" fill="#fff"/><circle cx="48.5" cy="68" r="4.6" fill="#1B1F33"/><circle cx="74.5" cy="68" r="4.6" fill="#1B1F33"/><circle cx="50" cy="66" r="1.6" fill="#fff"/><circle cx="76" cy="66" r="1.6" fill="#fff"/></g>'
		. '<path d="M51 83q9 9 18 0" stroke="#1B1F33" stroke-width="3.4" fill="#fff" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<ellipse cx="36" cy="80" rx="5" ry="3.2" fill="#FF6B81" opacity=".45"/><ellipse cx="84" cy="80" rx="5" ry="3.2" fill="#FF6B81" opacity=".45"/></g>'
		. '</symbol>'
		// آیکن‌های نقش‌ها (طراحی اختصاصی)
		. '<symbol id="hst-r-teacher" viewBox="0 0 64 64"><rect x="8" y="12" width="48" height="32" rx="4" fill="#2E7D5B"/><rect x="8" y="12" width="48" height="32" rx="4" fill="none" stroke="#A86B32" stroke-width="4"/><path d="M16 24h18M16 31h12" stroke="#fff" stroke-width="3" stroke-linecap="round"/><path d="M44 52c-6 0-9-5-9-10 0-4 3-7 7-6 1 0 2 1 2 1s1-1 2-1c4-1 7 2 7 6 0 5-3 10-9 10z" fill="#E53935"/><path d="M44 36c0-3 2-5 4-6" stroke="#5D4037" stroke-width="2.5" fill="none" stroke-linecap="round"/></symbol>'
		. '<symbol id="hst-r-parent" viewBox="0 0 64 64"><path d="M10 30L32 12l22 18v22a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4z" fill="#FFF3E0"/><path d="M6 32L32 10l26 22" stroke="#C24E00" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M32 49c-7-5-11-9-11-13 0-3 2-5 5-5 2 0 4 1 6 4 2-3 4-4 6-4 3 0 5 2 5 5 0 4-4 8-11 13z" fill="#E5487A"/></symbol>'
		. '<symbol id="hst-r-student" viewBox="0 0 64 64"><path d="M22 18a10 10 0 0 1 20 0" stroke="#0F2F6B" stroke-width="4" fill="none"/><rect x="12" y="18" width="40" height="38" rx="10" fill="#FFB300"/><rect x="18" y="34" width="28" height="16" rx="5" fill="#F57C00"/><path d="M18 40h28" stroke="#C24E00" stroke-width="2"/><circle cx="32" cy="26" r="3" fill="#0F2F6B"/></symbol>'
		. '<symbol id="hst-r-school" viewBox="0 0 64 64"><path d="M32 8v10" stroke="#5D4037" stroke-width="2.5"/><path d="M33 8h10l-3 3 3 3H33z" fill="#E53935"/><path d="M8 30L32 18l24 12z" fill="#C24E00"/><rect x="12" y="30" width="40" height="24" fill="#FFF3E0"/><rect x="27" y="40" width="10" height="14" rx="5" fill="#1565C0"/><rect x="16" y="35" width="7" height="7" rx="1.5" fill="#64B5F6"/><rect x="41" y="35" width="7" height="7" rx="1.5" fill="#64B5F6"/><rect x="6" y="54" width="52" height="4" rx="2" fill="#6D7A99"/></symbol>'
		. '</defs></svg>';
}, 5 );

/** با فعال‌سازی قالب، برگه‌های نقش‌ها و حریم خصوصی ساخته می‌شوند (اگر نباشند). */
function hst_make_pages() {
	$pages = array( 'teachers' => 'برای معلم‌ها', 'parents' => 'برای خانواده‌ها', 'students' => 'برای دانش‌آموزان', 'schools' => 'برای مدیران مدرسه', 'privacy' => 'حریم خصوصی', 'majalle' => 'مجله‌ی همشاگردی' );
	foreach ( $pages as $slug => $title ) {
		if ( ! get_page_by_path( $slug ) ) {
			wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => '' ) );
		}
	}
	update_option( 'hst_pages_ver', HST_VER );
}
add_action( 'after_switch_theme', 'hst_make_pages' );
add_action( 'admin_init', function () { if ( current_user_can( 'manage_options' ) && get_option( 'hst_pages_ver' ) !== HST_VER ) { hst_make_pages(); } } );
function hst_page( $slug ) { $p = get_page_by_path( $slug ); return esc_url( $p ? get_permalink( $p ) : home_url( '/' . $slug . '/' ) ); }

/** آیکن نقش (معلم، والدین، دانش‌آموز، مدرسه) */
function hst_role_icon( $role, $cls = '' ) {
	$map = array( 'teacher' => 'teacher', 'parent' => 'parent', 'student' => 'student', 'school_admin' => 'school', 'school' => 'school' );
	return '<svg class="hst-ricon ' . esc_attr( $cls ) . '" viewBox="0 0 64 64" aria-hidden="true"><use href="#hst-r-' . esc_attr( $map[ $role ] ?? 'student' ) . '"/></svg>';
}

/** آواتار کودکانه (طراحی اختصاصی؛ ۴ مدل مو، رنگ پوست/مو/لباس متفاوت) */
function hst_avatar( $i ) {
	$skins  = array( '#F6D2A8', '#E9B98D', '#FBE0C4', '#D9A27A' );
	$hairs  = array( '#3B2416', '#6B3E1F', '#1E1A1A', '#A0522D', '#2D1B10' );
	$shirts = array( '#1565C0', '#F57C00', '#00A884', '#E5487A', '#7C5CFC', '#FFB300', '#0B8BE0' );
	$sk = $skins[ $i % 4 ];
	$hc = $hairs[ $i % 5 ];
	$sh = $shirts[ $i % 7 ];
	$style = $i % 4;
	$hair = array(
		'<path d="M22 36c0-14 9-22 20-22s20 8 20 22c-4-6-10-9-20-9s-16 3-20 9z" fill="' . $hc . '"/>',
		'<circle cx="42" cy="12" r="7" fill="' . $hc . '"/><path d="M21 38c0-15 9-23 21-23s21 8 21 23c-3-7-11-11-21-11s-18 4-21 11z" fill="' . $hc . '"/>',
		'<path d="M21 36c0-14 9-22 21-22s21 8 21 22c-5-5-12-8-21-8s-16 3-21 8z" fill="' . $hc . '"/><circle cx="18" cy="40" r="7" fill="' . $hc . '"/><circle cx="66" cy="40" r="7" fill="' . $hc . '"/>',
		'<g fill="' . $hc . '"><circle cx="26" cy="26" r="8"/><circle cx="36" cy="19" r="8"/><circle cx="48" cy="19" r="8"/><circle cx="58" cy="26" r="8"/><circle cx="22" cy="36" r="6"/><circle cx="62" cy="36" r="6"/></g>',
	);
	return '<svg class="hst-av" viewBox="0 0 84 84" aria-hidden="true">'
		. '<circle cx="42" cy="42" r="42" fill="' . $sh . '" opacity=".14"/>'
		. '<path d="M16 84c0-16 12-24 26-24s26 8 26 24z" fill="' . $sh . '"/>'
		. '<rect x="37" y="52" width="10" height="10" rx="4" fill="' . $sk . '"/>'
		. '<circle cx="42" cy="40" r="19" fill="' . $sk . '"/>'
		. $hair[ $style ]
		. '<circle cx="35" cy="41" r="2.6" fill="#1B1F33"/><circle cx="49" cy="41" r="2.6" fill="#1B1F33"/>'
		. '<path d="M36 49q6 5 12 0" stroke="#1B1F33" stroke-width="2.4" fill="none" stroke-linecap="round"/>'
		. '<circle cx="30" cy="47" r="3" fill="#FF8A80" opacity=".5"/><circle cx="54" cy="47" r="3" fill="#FF8A80" opacity=".5"/>'
		. '</svg>';
}

/** نشانی صفحه‌ی همه‌ی مقالات: صفحه‌ی نوشته‌ها اگر تعیین شده، وگرنه «مجله» */
function hst_blog_url() {
	$pf = (int) get_option( 'page_for_posts' );
	return $pf ? get_permalink( $pf ) : hst_page( 'majalle' );
}
/** برچسب پایه/مخاطب/قفل روی کارت مقاله (افزونه‌ی مقالات) */
function hst_post_badges() {
	return class_exists( 'HSAR_Access' ) ? HSAR_Access::badges( get_the_ID() ) : '';
}
/** دسته‌های مجله با تعداد و رنگ */
function hst_mag_cats( $limit = 8 ) {
	$cats = get_categories( array( 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => $limit + 1 ) );
	$cats = array_filter( is_array( $cats ) ? $cats : array(), function ( $c ) { return ! in_array( $c->slug, array( 'uncategorized', 'بدون-دسته-بندی', 'دسته‌بندی-نشده' ), true ) && 'دسته‌بندی نشده' !== $c->name; } );
	return array_slice( array_values( $cats ), 0, $limit );
}
