<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#F57C00">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'hst' ); ?>>
<?php wp_body_open(); ?>
<a class="hst-skip" href="#main">رفتن به محتوا</a>
<header class="hst-header">
	<div class="hst-wrap hst-header-in">
		<a class="hst-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo hst_logo(); ?>
			<span><?php bloginfo( 'name' ); ?></span>
		</a>
		<nav class="hst-nav" aria-label="منوی اصلی">
			<?php wp_nav_menu( array( 'theme_location' => 'main', 'container' => false, 'fallback_cb' => function () {
				echo '<ul><li><a href="' . hst_page( 'teachers' ) . '">معلم‌ها</a></li><li><a href="' . hst_page( 'parents' ) . '">خانواده‌ها</a></li><li><a href="' . hst_page( 'students' ) . '">دانش‌آموزان</a></li><li><a href="' . hst_page( 'schools' ) . '">مدیران مدرسه</a></li><li><a href="' . esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/?post_type=post' ) ) . '">مقالات</a></li></ul>';
			} ) ); ?>
		</nav>
		<div class="hst-actions">
			<button class="hst-iconbtn" data-hst-theme aria-label="تغییر حالت روشن و تیره"><?php echo hst_icon( 'moon' ) . hst_icon( 'sun' ); ?></button>
			<?php if ( is_user_logged_in() ) : ?>
				<a class="hst-btn" href="<?php echo hst_url( 'panel' ); ?>">پنل من</a>
			<?php else : ?>
				<a class="hst-btn hst-btn-ghost hst-hide-sm" href="<?php echo hst_login_url(); ?>">ورود</a>
				<a class="hst-btn" href="<?php echo hst_login_url(); ?>">ثبت‌نام</a>
			<?php endif; ?>
			<button class="hst-iconbtn hst-menu-btn" data-hst-menu aria-label="منو" aria-expanded="false"><?php echo hst_icon( 'menu' ); ?></button>
		</div>
	</div>
</header>
<main id="main">
