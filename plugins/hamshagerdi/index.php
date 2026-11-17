<?php get_header(); ?>
<section class="hst-sec hst-wrap">
	<header class="hst-sec-head"><h1><?php
		if ( is_category() ) { single_cat_title(); } elseif ( is_search() ) { echo 'جستجو: ' . esc_html( get_search_query() ); } elseif ( is_archive() ) { the_archive_title(); } else { echo 'مقالات'; }
	?></h1><?php if ( is_home() || is_category() ) : ?><p>علمی، آموزشی و پرورشی</p><?php endif; ?></header>
	<?php $cats = get_categories( array( 'hide_empty' => true ) ); if ( $cats && ( is_home() || is_category() || is_tax() ) ) : ?>
		<nav class="hst-pills" aria-label="دسته‌ها"><?php foreach ( $cats as $c ) { printf( '<a class="%s" href="%s">%s</a>', is_category( $c->term_id ) ? 'is-on' : '', esc_url( get_category_link( $c ) ), esc_html( $c->name ) ); } ?></nav>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<div class="hst-grid hst-g3"><?php while ( have_posts() ) { the_post(); get_template_part( 'parts/card' ); } ?></div>
		<div class="hst-pager"><?php the_posts_pagination( array( 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ) ); ?></div>
	<?php else : ?><p class="hst-empty">هنوز مطلبی منتشر نشده است.</p><?php endif; ?>
</section>
<?php get_footer(); ?>
