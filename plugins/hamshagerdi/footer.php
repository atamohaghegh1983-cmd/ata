</main>
<footer class="hst-footer">
	<div class="hst-wrap hst-footer-grid">
		<div>
			<a class="hst-footer-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo hst_logo( true ); ?></a>
			<p><?php bloginfo( 'description' ); ?></p>
		</div>
		<div><h4>بخش‌ها</h4><?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 1, 'fallback_cb' => function () {
			echo '<ul><li><a href="' . hst_login_url( 'teacher' ) . '">ورود معلم</a></li><li><a href="' . hst_login_url( 'parent' ) . '">ورود والدین</a></li><li><a href="' . hst_login_url( 'school_admin' ) . '">ورود مدیر مدرسه</a></li><li><a href="' . esc_url( home_url( '/#features' ) ) . '">امکانات</a></li><li><a href="' . hst_page( 'privacy' ) . '">حریم خصوصی</a></li><li><a href="' . esc_url( home_url( '/#faq' ) ) . '">سؤالات پرتکرار</a></li></ul>';
		} ) ); ?></div>
		<div><h4>تماس</h4><ul>
			<?php if ( hst_mod( 'hst_phone' ) ) : ?><li><?php echo hst_icon( 'phone' ); ?> <a href="tel:<?php echo esc_attr( hst_mod( 'hst_phone' ) ); ?>"><?php echo esc_html( hst_mod( 'hst_phone' ) ); ?></a></li><?php endif; ?>
			<?php if ( hst_mod( 'hst_email' ) ) : ?><li><a href="mailto:<?php echo esc_attr( hst_mod( 'hst_email' ) ); ?>"><?php echo esc_html( hst_mod( 'hst_email' ) ); ?></a></li><?php endif; ?>
		</ul></div>
	</div>
	<div class="hst-wrap hst-footer-bottom">
		<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
		<?php hst_credit(); ?>
	</div>
</footer>
<?php echo hst_logged_notice(); // phpcs:ignore ?>
<?php wp_footer(); ?>
</body>
</html>
