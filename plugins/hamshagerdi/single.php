<?php get_header(); while ( have_posts() ) : the_post(); ?>
<article class="hst-sec hst-wrap hst-article">
	<header>
		<?php $c = get_the_category(); if ( $c ) : ?><a class="hst-tag" href="<?php echo esc_url( get_category_link( $c[0] ) ); ?>"><?php echo esc_html( $c[0]->name ); ?></a><?php endif; ?>
		<h1><?php the_title(); ?></h1>
		<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
	</header>
	<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'class' => 'hst-cover' ) ); } ?>
	<div class="hst-prose"><?php the_content(); ?></div>
</article>
<?php endwhile; get_footer(); ?>
