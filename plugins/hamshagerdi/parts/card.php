<article <?php post_class( "hst-card hst-post hst-reveal" ); ?>>
	<a href="<?php the_permalink(); ?>" class="hst-post-img"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium_large' ); } else { echo '<span class="hst-dots"><i></i><i></i><i></i></span>'; } ?></a>
	<div class="hst-post-body">
		<?php $c = get_the_category(); if ( $c ) : ?><a class="hst-tag" href="<?php echo esc_url( get_category_link( $c[0] ) ); ?>"><?php echo esc_html( $c[0]->name ); ?></a><?php endif; ?>
		<?php echo hst_post_badges(); // phpcs:ignore ?>
		<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
		<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
	</div>
</article>
