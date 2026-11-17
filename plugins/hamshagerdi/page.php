<?php get_header(); while ( have_posts() ) : the_post(); ?>
<article class="hst-sec hst-wrap hst-article">
	<header>
		
		<h1><?php the_title(); ?></h1>
	</header>
	<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'class' => 'hst-cover' ) ); } ?>
	<div class="hst-prose"><?php the_content(); ?></div>
</article>
<?php endwhile; get_footer(); ?>
