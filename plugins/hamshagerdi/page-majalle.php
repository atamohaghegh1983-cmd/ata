<?php
/* صفحه‌ی «مجله‌ی همشاگردی»: همه‌ی مقالات با فیلتر دسته و پایه */
get_header();
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$cat   = absint( $_GET['dasteh'] ?? 0 ); // phpcs:ignore
$paye  = sanitize_text_field( wp_unslash( $_GET['paye'] ?? '' ) ); // phpcs:ignore
$args  = array( 'post_type' => 'post', 'posts_per_page' => 12, 'paged' => $paged, 'ignore_sticky_posts' => true );
if ( $cat ) { $args['cat'] = $cat; }
if ( $paye && taxonomy_exists( 'hs_grade' ) ) { $args['tax_query'] = array( array( 'taxonomy' => 'hs_grade', 'field' => 'name', 'terms' => $paye ) ); } // phpcs:ignore
$q = new WP_Query( $args );
?>
<section class="hst-sec hst-wrap">
	<header class="hst-sec-head"><h1>مجله‌ی همشاگردی</h1><p>تقویم آموزشی، بودجه‌بندی، آموزش حروف، نکات تربیتی و روانشناسی</p></header>
	<nav class="hst-pills" aria-label="دسته‌ها">
		<a class="<?php echo $cat ? '' : 'is-on'; ?>" href="<?php echo esc_url( remove_query_arg( array( 'dasteh', 'paged' ) ) ); ?>">همه</a>
		<?php foreach ( get_categories( array( 'hide_empty' => true ) ) as $c ) { printf( '<a class="%s" href="%s">%s</a>', $cat === $c->term_id ? 'is-on' : '', esc_url( add_query_arg( 'dasteh', $c->term_id, remove_query_arg( 'paged' ) ) ), esc_html( $c->name ) ); } ?>
	</nav>
	<?php if ( taxonomy_exists( 'hs_grade' ) ) : $gs = get_terms( array( 'taxonomy' => 'hs_grade', 'hide_empty' => true ) ); if ( $gs && ! is_wp_error( $gs ) ) : ?>
	<nav class="hst-pills hst-pills-sm" aria-label="پایه‌ها"><span>پایه:</span>
		<a class="<?php echo $paye ? '' : 'is-on'; ?>" href="<?php echo esc_url( remove_query_arg( array( 'paye', 'paged' ) ) ); ?>">همه</a>
		<?php foreach ( $gs as $g ) { printf( '<a class="%s" href="%s">%s</a>', $paye === $g->name ? 'is-on' : '', esc_url( add_query_arg( 'paye', $g->name, remove_query_arg( 'paged' ) ) ), esc_html( $g->name ) ); } ?>
	</nav>
	<?php endif; endif; ?>
	<?php if ( $q->have_posts() ) : ?>
		<div class="hst-grid hst-g3"><?php while ( $q->have_posts() ) { $q->the_post(); get_template_part( 'parts/card' ); } wp_reset_postdata(); ?></div>
		<div class="hst-pager"><?php echo paginate_links( array( 'total' => $q->max_num_pages, 'current' => $paged, 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ) ); // phpcs:ignore ?></div>
	<?php else : ?><p class="hst-empty">مطلبی پیدا نشد.</p><?php endif; ?>
</section>
<?php get_footer(); ?>
