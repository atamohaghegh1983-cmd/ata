<?php
/** فهرست مطالب خودکار (از افزونه‌ی «فهرست مطالب مقاله» ۱.۱.۰) */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Article_TOC {

	const MIN_HEADINGS = 2;
	private static $built = false;

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'build' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	private static function enabled() {
		return is_singular( 'post' ) && '1' !== get_post_meta( get_the_ID(), '_hsar_no_toc', true ) && apply_filters( 'hs_toc_enabled', true, get_the_ID() );
	}

	public static function build( $content ) {
		if ( self::$built || ! self::enabled() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$toc  = '';
		$i    = 0;
		$used = array();
		$content = preg_replace_callback(
			'/<h([23])([^>]*)>(.*?)<\/h\1>/is',
			function ( $m ) use ( &$toc, &$i, &$used ) {
				$text = trim( wp_strip_all_tags( $m[3] ) );
				if ( '' === $text ) {
					return $m[0];
				}
				$i++;
				$attrs = $m[2];
				if ( preg_match( '/\bid=["\']([^"\']+)["\']/', $attrs, $idm ) ) {
					$id = $idm[1];
				} else {
					// شناسه‌ی خوانا از روی متن تیتر (فارسی هم پشتیبانی می‌شود)
					$base = trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', mb_strtolower( $text ) ), '-' );
					$base = $base ? mb_substr( $base, 0, 60 ) : 'toc-' . $i;
					$id   = $base;
					$n    = 2;
					while ( isset( $used[ $id ] ) ) {
						$id = $base . '-' . $n++;
					}
					$attrs = ' id="' . esc_attr( $id ) . '"' . $attrs;
				}
				$used[ $id ] = true;
				$toc .= sprintf( '<li class="toc-h%1$s"><a href="#%2$s" data-toc-target="%2$s">%3$s</a></li>', $m[1], esc_attr( $id ), esc_html( $text ) );
				return '<h' . $m[1] . $attrs . '>' . $m[3] . '</h' . $m[1] . '>';
			},
			$content
		);
		if ( $i < (int) apply_filters( 'hs_toc_min_headings', self::MIN_HEADINGS ) ) {
			return $content;
		}
		self::$built = true;
		return '<div class="hs-article-wrap">'
			. '<div class="hs-toc-rail"><aside class="hs-toc" id="hs-toc" aria-label="فهرست مطالب"><details open><summary class="hs-toc-header">فهرست مطالب</summary>'
			. '<nav><ul class="hs-toc-list">' . $toc . '</ul></nav></details></aside></div>'
			. '<div class="hs-article-body">' . $content . '</div></div>';
	}

	public static function assets() {
		if ( ! self::enabled() ) {
			return;
		}
		wp_enqueue_style( 'hs-toc', HSAR_URL . 'assets/toc.css', wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array(), HSAR_VERSION );
		wp_enqueue_script( 'hs-toc', HSAR_URL . 'assets/toc.js', array(), HSAR_VERSION, true );
	}
}

HS_Article_TOC::init();
