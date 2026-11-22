<?php
/** صفحه‌ی معرفی یک نقش. $args: role, color, title, mark, lead, cta, items[ [emoji,title,text,color,mock] ], faq[] */
$a = wp_parse_args( $args, array( 'items' => array(), 'faq' => array() ) );
$rc = $a['color'];
?>
<section class="hst-rhero" style="--rc:<?php echo esc_attr( $rc ); ?>">
	<div class="hst-blob hst-blob-a" style="background:<?php echo esc_attr( $rc ); ?>" aria-hidden="true"></div>
	<div class="hst-wrap hst-rhero-in">
		<div>
			<span class="hst-chip hst-in" style="--d:0"><?php echo esc_html( $a['chip'] ); ?></span>
			<h1 class="hst-in" style="--d:1"><?php echo esc_html( $a['title'] ); ?> <span class="hst-grad"><?php echo esc_html( $a['mark'] ); ?></span></h1>
			<p class="hst-lead hst-in" style="--d:2"><?php echo esc_html( $a['lead'] ); ?></p>
			<div class="hst-row hst-in" style="--d:3">
				<a class="hst-btn hst-btn-lg" style="background:<?php echo esc_attr( $rc ); ?>" href="<?php echo hst_login_url( $a['role'] ); ?>"><?php echo esc_html( $a['cta'] ); ?> <?php echo hst_icon( 'arrow' ); ?></a>
				<a class="hst-btn hst-btn-ghost hst-btn-lg" href="#more">بیشتر بدانید</a>
			</div>
		</div>
		<div class="hst-rhero-art" aria-hidden="true">
			<div class="hst-rhero-disc"></div>
			<span class="hst-rhero-badge"><?php echo hst_role_icon( $a['role'] ); ?></span>
			<span class="hst-rhero-emoji e1"><?php echo esc_html( $a['items'][0][0] ?? '⭐' ); ?></span>
			<span class="hst-rhero-emoji e2"><?php echo esc_html( $a['items'][1][0] ?? '💬' ); ?></span>
			<span class="hst-rhero-emoji e3"><?php echo esc_html( $a['items'][2][0] ?? '📅' ); ?></span>
		</div>
	</div>
</section>

<section id="more" class="hst-sec hst-wrap hst-zig">
	<svg class="hst-zig-path" viewBox="0 0 100 1000" preserveAspectRatio="none" aria-hidden="true"><path d="M50 0 C90 120 10 230 50 340 S90 560 50 680 S10 880 50 1000" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="6 8" vector-effect="non-scaling-stroke"/></svg>
	<?php foreach ( $a['items'] as $i => $it ) : ?>
		<article class="hst-zrow hst-reveal" style="--fc:<?php echo esc_attr( $it[3] ); ?>">
			<div class="hst-ztext">
				<span class="hst-zico"><?php echo esc_html( $it[0] ); ?></span>
				<h2><?php echo esc_html( $it[1] ); ?></h2>
				<p><?php echo esc_html( $it[2] ); ?></p>
			</div>
			<div class="hst-zmock" data-hst-tilt><?php echo wp_kses_post( $it[4] ); ?></div>
		</article>
	<?php endforeach; ?>
</section>

<?php if ( $a['faq'] ) : ?>
<section class="hst-sec hst-wrap hst-faq-wrap">
	<header class="hst-sec-head hst-reveal"><span class="hst-eyebrow">سؤالات</span><h2>پرسش‌های <?php echo esc_html( $a['label'] ); ?></h2></header>
	<div class="hst-faq"><?php foreach ( $a['faq'] as $q ) : ?><details class="hst-card"><summary><?php echo esc_html( $q[0] ); ?><span aria-hidden="true">+</span></summary><p><?php echo esc_html( $q[1] ); ?></p></details><?php endforeach; ?></div>
</section>
<?php endif; ?>

<section class="hst-sec hst-wrap">
	<div class="hst-cta2 hst-reveal" style="background:linear-gradient(135deg,<?php echo esc_attr( $rc ); ?>,<?php echo esc_attr( $rc ); ?>cc)">
		
		<h2><?php echo esc_html( $a['cta_title'] ); ?></h2>
		<a class="hst-btn hst-btn-white hst-btn-lg" href="<?php echo hst_login_url( $a['role'] ); ?>"><?php echo esc_html( $a['cta'] ); ?> <?php echo hst_icon( 'arrow' ); ?></a>
	</div>
</section>
