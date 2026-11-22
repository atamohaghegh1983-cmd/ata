<?php
/**
 * پوسته نمایش کاربرگ — بدون گوتنبرگ
 * محتوا از متا و داشبورد مدیریتی پر می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
$card    = class_exists( 'HS_Worksheet_CPT' ) ? HS_Worksheet_CPT::get_card_data( $post_id ) : array();
$body    = get_post_meta( $post_id, '_hs_ws_body', true );
if ( ! $body ) {
	// سازگاری با نسخه قبلی
	$body = get_post_field( 'post_content', $post_id );
	$body = preg_replace( '/<figure[\s\S]*?<\/figure>/i', '', $body );
	$body = preg_replace( '/<p class="hs-ws-download"[\s\S]*?<\/p>/i', '', $body );
	$body = trim( $body );
}
$pdf   = get_post_meta( $post_id, '_hs_ws_pdf', true ) ?: ( $card['pdf'] ?? '' );
$video = get_post_meta( $post_id, '_hs_ws_video', true );
$file  = get_post_meta( $post_id, '_hs_ws_file', true );
$image = get_the_post_thumbnail_url( $post_id, 'large' );
if ( ! $image ) {
	$image = get_post_meta( $post_id, '_hs_ws_image_url', true );
}

$site = get_bloginfo( 'name' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( get_the_title() . ' — ' . $site ); ?></title>
	<?php wp_head(); ?>
	<style>
		:root {
			--ws-primary: #173B70;
			--ws-accent: #079E91;
			--ws-bg: #F5F7FB;
			--ws-card: #ffffff;
		}
		body.hs-ws-single-body {
			margin: 0;
			background: var(--ws-bg);
			font-family: Tahoma, Vazirmatn, sans-serif;
			color: #0F172A;
			line-height: 1.7;
		}
		.hs-ws-page { max-width: 820px; margin: 0 auto; padding: 24px 16px 48px; }
		.hs-ws-top {
			display: flex; justify-content: space-between; align-items: center;
			gap: 12px; margin-bottom: 18px; flex-wrap: wrap;
		}
		.hs-ws-top a {
			color: var(--ws-accent); text-decoration: none; font-weight: 700; font-size: 0.9rem;
		}
		.hs-ws-hero {
			background: linear-gradient(135deg, #173B70, #079E91);
			color: #fff; border-radius: 20px; padding: 28px 24px; margin-bottom: 18px;
			box-shadow: 0 10px 30px rgba(23, 59, 112, 0.25);
		}
		.hs-ws-hero h1 { margin: 0 0 10px; font-size: 1.6rem; line-height: 1.35; }
		.hs-ws-badges { display: flex; flex-wrap: wrap; gap: 8px; }
		.hs-ws-badge {
			background: rgba(255,255,255,0.18); border-radius: 999px;
			padding: 4px 12px; font-size: 0.8rem; font-weight: 700;
		}
		.hs-ws-panel {
			background: var(--ws-card); border-radius: 16px; padding: 20px;
			border: 1px solid #E2E8F0; margin-bottom: 16px;
			box-shadow: 0 2px 10px rgba(15,23,42,0.04);
		}
		.hs-ws-panel h2 {
			margin: 0 0 12px; font-size: 1.05rem; color: var(--ws-primary);
			border-bottom: 2px solid #E0F2F1; padding-bottom: 8px;
		}
		.hs-ws-body { font-size: 1.02rem; white-space: pre-wrap; }
		.hs-ws-body p { margin: 0 0 0.85em; }
		.hs-ws-media img, .hs-ws-media video {
			width: 100%; max-height: 480px; object-fit: contain;
			border-radius: 14px; background: #F1F5F9; display: block;
		}
		.hs-ws-actions { display: flex; flex-wrap: wrap; gap: 10px; }
		.hs-ws-btn {
			display: inline-flex; align-items: center; gap: 6px;
			background: var(--ws-accent); color: #fff !important; text-decoration: none !important;
			font-weight: 800; padding: 12px 18px; border-radius: 12px; font-size: 0.95rem;
		}
		.hs-ws-btn.secondary { background: #173B70; }
		.hs-ws-btn.ghost {
			background: #EEF2FF; color: #3730A3 !important;
		}
		.hs-ws-meta-line { color: #64748B; font-size: 0.88rem; margin-top: 8px; }
		.hs-ws-footer {
			text-align: center; color: #94A3B8; font-size: 0.82rem; margin-top: 24px;
		}
		@media print {
			.hs-ws-top, .hs-ws-footer { display: none; }
			.hs-ws-hero { box-shadow: none; }
		}
	</style>
</head>
<body <?php body_class( 'hs-ws-single-body' ); ?>>
<?php wp_body_open(); ?>

<div class="hs-ws-page">
	<div class="hs-ws-top">
		<a href="<?php echo esc_url( get_post_type_archive_link( 'hs_worksheet' ) ?: home_url( '/worksheets/' ) ); ?>">← کتابخانه کاربرگ</a>
		<?php if ( current_user_can( 'manage_options' ) || ( class_exists( 'HS_Roles' ) && HS_Roles::is_teacher_or_admin() ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=hs-worksheet-dashboard' ) ); ?>">ویرایش در داشبورد</a>
		<?php endif; ?>
	</div>

	<header class="hs-ws-hero">
		<h1><?php the_title(); ?></h1>
		<div class="hs-ws-badges">
			<?php if ( ! empty( $card['grade_label'] ) ) : ?>
				<span class="hs-ws-badge"><?php echo esc_html( $card['grade_label'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $card['subject_label'] ) ) : ?>
				<span class="hs-ws-badge"><?php echo esc_html( $card['subject_label'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $card['topic'] ) ) : ?>
				<span class="hs-ws-badge"><?php echo esc_html( $card['topic'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $card['type_label'] ) ) : ?>
				<span class="hs-ws-badge"><?php echo esc_html( $card['type_label'] ); ?></span>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $body ) : ?>
	<section class="hs-ws-panel">
		<h2>📝 متن و راهنما</h2>
		<div class="hs-ws-body"><?php echo wp_kses_post( wpautop( $body ) ); ?></div>
	</section>
	<?php endif; ?>

	<?php if ( $image ) : ?>
	<section class="hs-ws-panel hs-ws-media">
		<h2>🖼️ تصویر</h2>
		<img src="<?php echo esc_url( $image ); ?>" alt="<?php the_title_attribute(); ?>">
	</section>
	<?php endif; ?>

	<?php if ( $video ) : ?>
	<section class="hs-ws-panel hs-ws-media">
		<h2>🎬 فیلم آموزشی</h2>
		<video controls playsinline src="<?php echo esc_url( $video ); ?>"></video>
	</section>
	<?php endif; ?>

	<?php if ( $pdf || $file ) : ?>
	<section class="hs-ws-panel">
		<h2>📥 دانلود</h2>
		<div class="hs-ws-actions">
			<?php if ( $pdf ) : ?>
				<a class="hs-ws-btn" href="<?php echo esc_url( $pdf ); ?>" target="_blank" rel="noopener">📄 دانلود PDF کاربرگ</a>
			<?php endif; ?>
			<?php if ( $file ) : ?>
				<a class="hs-ws-btn secondary" href="<?php echo esc_url( $file ); ?>" target="_blank" rel="noopener">📎 فایل پیوست</a>
			<?php endif; ?>
			<button type="button" class="hs-ws-btn ghost" onclick="window.print()">🖨️ چاپ صفحه</button>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( ! $body && ! $image && ! $video && ! $pdf && ! $file ) : ?>
	<section class="hs-ws-panel">
		<p>محتوایی برای این کاربرگ ثبت نشده. از داشبورد مدیریت کاربرگ آن را کامل کنید.</p>
	</section>
	<?php endif; ?>

	<p class="hs-ws-meta-line">همشاگردی · <?php echo esc_html( $site ); ?></p>
	<footer class="hs-ws-footer">نمایش با پوسته اختصاصی کاربرگ — بدون نیاز به ویرایشگر گوتنبرگ</footer>
</div>

<?php wp_footer(); ?>
</body>
</html>
