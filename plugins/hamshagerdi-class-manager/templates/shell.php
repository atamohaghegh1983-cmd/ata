<?php
/**
 * قالب کامل پنل همشاگردی (جایگزین هدر/فوتر قالب سایت)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logged   = is_user_logged_in();
$user     = wp_get_current_user();
$type     = $logged ? HS_Shell::view_type() : 'other';
$menu     = HS_Shell::menu( $type );
$hs_page     = get_queried_object();
$cur_slug = ( $hs_page instanceof WP_Post ) ? $hs_page->post_name : '';
$unread   = $logged ? HS_Shell::unread_count( $user->ID ) : 0;
$title    = ( $hs_page instanceof WP_Post ) ? get_the_title( $hs_page ) : 'پنل';
$temp     = $logged && get_user_meta( $user->ID, '_hs_temp_pass', true );
$logo     = get_bloginfo( 'name' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $title . ' — ' . $logo ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// مهمان: معرفی امکانات همان بخش + ورود/ثبت‌نام (includes/guest-landing.php)
$guest_html = $logged ? '' : (string) apply_filters( 'hs_guest_panel_html', HS_Guest_Landing::render( $cur_slug ), $cur_slug, $hs_page );
?>
<?php if ( ! $logged && '' !== $guest_html ) : ?>
	<?php echo $guest_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php elseif ( ! $logged ) : ?>
	<main class="hs-login-wrap">
		<div class="hs-login-card">
			<div class="hs-login-logo">🎒</div>
			<h1><?php echo esc_html( $logo ); ?></h1>
			<p class="hs-muted">برای ورود به پنل همشاگردی، مشخصات حساب خود را وارد کنید.</p>
			<?php
			wp_login_form( array(
				'redirect'       => esc_url_raw( home_url( add_query_arg( null, null ) ) ),
				'label_username' => 'نام کاربری',
				'label_password' => 'رمز عبور',
				'label_log_in'   => 'ورود به پنل',
				'remember'       => true,
				'label_remember' => 'مرا به خاطر بسپار',
			) );
			?>
		</div>
	</main>
<?php else : ?>

<div class="hs-shell" id="hs-shell">
	<div class="hs-overlay" id="hs-overlay"></div>

	<aside class="hs-sidebar" id="hs-sidebar" aria-label="منوی اصلی">
		<a class="hs-brand" href="<?php echo esc_url( HS_Shell::url( 'panel' ) ); ?>">
			<span class="hs-brand-mark">🎒</span>
			<span class="hs-brand-text"><?php echo esc_html( $logo ); ?></span>
		</a>
		<nav class="hs-nav" id="hs-nav">
			<?php foreach ( $menu as $section ) : ?>
				<?php if ( $section[0] ) : ?>
					<div class="hs-nav-title"><?php echo esc_html( $section[0] ); ?></div>
				<?php endif; ?>
				<?php foreach ( $section[1] as $it ) :
					$is_active = ( $it[0] === $cur_slug ) || ( 'teacher' === $type && $it[0] === HS_Hubs::primary( $cur_slug ) );
					?>
					<a class="hs-nav-item hs-c-<?php echo esc_attr( $it[3] ); ?><?php echo $is_active ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( HS_Shell::url( $it[0] ) ); ?>" data-label="<?php echo esc_attr( $it[1] ); ?>">
						<span class="hs-nav-ico"><?php echo esc_html( $it[2] ); ?></span>
						<span class="hs-nav-label"><?php echo esc_html( $it[1] ); ?></span>
						<?php if ( ! empty( $it[4] ) && $unread > 0 ) : ?>
							<span class="hs-nav-badge"><?php echo esc_html( HS_Util::fa_digits( $unread ) ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</nav>
		<div class="hs-side-foot">
			<a class="hs-nav-item" href="<?php echo esc_url( wp_logout_url( home_url( '/panel/' ) ) ); ?>">
				<span class="hs-nav-ico">🚪</span><span class="hs-nav-label">خروج</span>
			</a>
		</div>
	</aside>

	<div class="hs-main">
		<header class="hs-topbar">
			<button type="button" class="hs-burger" id="hs-burger" aria-label="منو">☰</button>
			<div class="hs-search">
				<span>🔍</span>
				<input type="search" id="hs-menu-search" placeholder="جستجو در بخش‌ها…" autocomplete="off">
			</div>
			<div class="hs-top-date">📅 <?php echo esc_html( HS_Util::today_jalali() ); ?></div>
			<div class="hs-top-actions">
				<?php if ( 'teacher' === $type || 'parent' === $type ) : ?>
					<a class="hs-bell" href="<?php echo esc_url( HS_Shell::url( 'teacher' === $type ? 'messages' : 'parent-chat' ) ); ?>" title="پیام‌ها">
						🔔<?php if ( $unread > 0 ) : ?><span class="hs-bell-dot"><?php echo esc_html( HS_Util::fa_digits( $unread ) ); ?></span><?php endif; ?>
					</a>
				<?php endif; ?>
				<div class="hs-user" id="hs-user">
					<button type="button" class="hs-user-btn" id="hs-user-btn">
						<?php echo get_avatar( $user->ID, 38, '', '', array( 'class' => 'hs-avatar' ) ); ?>
						<?php if ( apply_filters( 'hs_parent_linked', false, $user->ID ) ) : ?><span class="hs-linked-badge" title="به فرزند متصل هستید" aria-label="متصل به فرزند">🔗</span><?php endif; ?>
						<span class="hs-user-meta"><strong><?php echo esc_html( $user->display_name ); ?></strong><small><?php echo esc_html( HS_Shell::role_label( $user ) ); ?></small></span>
						<span class="hs-caret">▾</span>
					</button>
					<div class="hs-user-menu" id="hs-user-menu">
						<button type="button" class="hs-um-item" id="hs-open-avatar">🖼️ عکس پروفایل</button>
						<button type="button" class="hs-um-item" id="hs-open-pass">🔑 تغییر رمز عبور</button>
						<a class="hs-um-item" href="<?php echo esc_url( wp_logout_url( home_url( '/panel/' ) ) ); ?>">🚪 خروج</a>
					</div>
				</div>
			</div>
		</header>

		<?php if ( $temp ) : ?>
			<div class="hs-banner">
				<span>⚠️ شما هنوز از رمز موقت استفاده می‌کنید. برای امنیت حساب، رمز خود را تغییر دهید.</span>
				<button type="button" class="hs-btn hs-btn-sm hs-btn-primary" id="hs-open-pass-2">تغییر رمز</button>
			</div>
		<?php endif; ?>

		<main class="hs-content" id="hs-content">
			<?php
			$is_root = ( 'panel' === $cur_slug && $hs_page instanceof WP_Post && ! $hs_page->post_parent );
			if ( $is_root ) {
				echo HS_Dashboard::render(); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			while ( have_posts() ) :
				the_post();
				$body = get_the_content();
				// اگر صفحه خودش شورت‌کد داشبورد دارد، دوباره رندر نشود (علت دوبار دیده شدن داشبورد)
				if ( $is_root ) {
					$body = trim( wp_strip_all_tags( preg_replace( '/\[hamshagerdi_dashboard[^\]]*\]/', '', $body ) ) );
					// خود داشبورد بالا رندر شده؛ شورت‌کدش داخل محتوا چیزی چاپ نکند
					add_shortcode( 'hamshagerdi_dashboard', '__return_empty_string' );
				}
				if ( ! $is_root || '' !== $body ) {
					the_content();
				}
			endwhile;
			?>
		</main>
		<footer class="hs-footer">ساخته‌شده با 💜 توسط همشاگردی</footer>
	</div>
</div>

<div class="hs-modal" id="hs-pass-modal" hidden>
	<div class="hs-modal-box">
		<h3>🔑 تغییر رمز عبور</h3>
		<div class="hs-form-row"><label>رمز جدید (حداقل ۸ کاراکتر)</label><input type="password" id="hs-pass-new" autocomplete="new-password"></div>
		<div class="hs-form-row"><label>تکرار رمز جدید</label><input type="password" id="hs-pass-confirm" autocomplete="new-password"></div>
		<div class="hs-form-message" id="hs-pass-msg"></div>
		<div class="hs-form-actions">
			<button type="button" class="hs-btn hs-btn-primary" id="hs-pass-save">ذخیره</button>
			<button type="button" class="hs-btn hs-btn-ghost" id="hs-pass-cancel">انصراف</button>
		</div>
	</div>
</div>
<div class="hs-modal" id="hs-avatar-modal" hidden>
	<div class="hs-modal-box">
		<h3>🖼️ عکس پروفایل</h3>
		<div class="hs-avatar-preview"><?php echo get_avatar( $user->ID, 112, '', '', array( 'id' => 'hs-avatar-img' ) ); ?></div>
		<div class="hs-form-row"><input type="file" id="hs-avatar-file" accept="image/jpeg,image/png,image/webp"></div>
		<p class="hs-hint">JPG، PNG یا WebP تا ۳ مگابایت. عکس به‌صورت مربع برش داده می‌شود.</p>
		<div class="hs-form-message" id="hs-avatar-msg"></div>
		<div class="hs-form-actions">
			<button type="button" class="hs-btn hs-btn-primary" id="hs-avatar-save">آپلود عکس</button>
			<button type="button" class="hs-btn hs-btn-ghost" id="hs-avatar-remove">حذف عکس</button>
			<button type="button" class="hs-btn hs-btn-ghost" id="hs-avatar-cancel">بستن</button>
		</div>
	</div>
</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
