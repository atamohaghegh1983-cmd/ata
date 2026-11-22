<?php get_header(); ?>
<section class="hst-sec hst-wrap hst-article">
	<header class="hst-center"><span class="hst-safe-ico" style="margin:auto"><?php echo hst_icon( 'shield' ); ?></span><h1>مرکز حریم خصوصی</h1><p class="hst-lead">اطلاعات بچه‌ها امانت است.</p></header>
	<div class="hst-feats" style="grid-template-columns:repeat(2,1fr);margin:30px 0">
		<?php foreach ( array(
			array( '👪', 'هر کس فقط سهم خودش', 'والد فقط فرزند خودش را می‌بیند، معلم فقط کلاس خودش، و دانش‌آموز فقط کارهای خودش.', '#1565C0' ),
			array( '🚫', 'بدون تبلیغ', 'اطلاعات دانش‌آموزان به هیچ تبلیغ‌کننده‌ای داده نمی‌شود.', '#E5487A' ),
			array( '🎚️', 'تنظیم سلسله‌مراتبی', 'سایت، مدرسه، کلاس و حتی هر خانواده تعیین می‌کند چه چیزی دیده شود.', '#00A884' ),
			array( '🔐', 'ورود امن', 'ورود با کد پیامکی؛ کارت کلاس بعد از ۵ رمز اشتباه قفل می‌شود.', '#F57C00' ),
		) as $x ) : ?>
			<div class="hst-feat2" style="--fc:<?php echo esc_attr( $x[3] ); ?>"><span class="hst-feat2-ico"><?php echo esc_html( $x[0] ); ?></span><b><?php echo esc_html( $x[1] ); ?></b><small><?php echo esc_html( $x[2] ); ?></small></div>
		<?php endforeach; ?>
	</div>
	<?php while ( have_posts() ) { the_post(); echo '<div class="hst-prose">'; the_content(); echo '</div>'; } ?>
</section>
<?php get_footer(); ?>
