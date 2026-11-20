<?php
/**
 * سئو: ذخیره در Rank Math و Yoast (هرکدام نصب باشد)؛ اگر هیچ‌کدام نبود، خود افزونه
 * عنوان و توضیح متا را در head چاپ می‌کند. به‌اضافه‌ی بررسی کیفیت قبل از انتشار.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSAR_SEO {

	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
	}

	public static function engine() {
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			return 'rankmath';
		}
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		return 'own';
	}

	public static function save( $post_id, $a ) {
		$map = array(
			'seo_title'   => array( 'rank_math_title', '_yoast_wpseo_title' ),
			'description' => array( 'rank_math_description', '_yoast_wpseo_metadesc' ),
			'keyword'     => array( 'rank_math_focus_keyword', '_yoast_wpseo_focuskw' ),
		);
		foreach ( $map as $k => $keys ) {
			$v = trim( (string) ( $a[ $k ] ?? '' ) );
			if ( '' === $v ) {
				continue;
			}
			update_post_meta( $post_id, '_hsar_' . $k, $v );
			update_post_meta( $post_id, $keys[0], $v );
			update_post_meta( $post_id, $keys[1], $v );
		}
		if ( ! empty( $a['noindex'] ) ) {
			update_post_meta( $post_id, 'rank_math_robots', array( 'noindex' ) );
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
			update_post_meta( $post_id, '_hsar_noindex', '1' );
		}
		if ( ! empty( $a['canonical'] ) ) {
			update_post_meta( $post_id, 'rank_math_canonical_url', esc_url_raw( $a['canonical'] ) );
			update_post_meta( $post_id, '_yoast_wpseo_canonical', esc_url_raw( $a['canonical'] ) );
			update_post_meta( $post_id, '_hsar_canonical', esc_url_raw( $a['canonical'] ) );
		}
	}

	/* ---------- خروجی خودی (فقط وقتی Rank Math و Yoast نیستند) ---------- */

	public static function title( $t ) {
		if ( 'own' !== self::engine() || ! is_singular() ) {
			return $t;
		}
		$v = get_post_meta( get_queried_object_id(), '_hsar_seo_title', true );
		return $v ? $v : $t;
	}

	public static function head() {
		if ( 'own' !== self::engine() || ! is_singular() ) {
			return;
		}
		$id = get_queried_object_id();
		$d  = get_post_meta( $id, '_hsar_description', true );
		if ( $d ) {
			printf( "<meta name=\"description\" content=\"%s\">\n<meta property=\"og:description\" content=\"%s\">\n", esc_attr( $d ), esc_attr( $d ) );
		}
		printf( "<meta property=\"og:title\" content=\"%s\">\n<meta property=\"og:type\" content=\"article\">\n<meta property=\"og:url\" content=\"%s\">\n", esc_attr( get_post_meta( $id, '_hsar_seo_title', true ) ?: get_the_title( $id ) ), esc_url( get_permalink( $id ) ) );
		if ( has_post_thumbnail( $id ) ) {
			printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( get_the_post_thumbnail_url( $id, 'large' ) ) );
		}
		if ( get_post_meta( $id, '_hsar_noindex', true ) ) {
			echo "<meta name=\"robots\" content=\"noindex,follow\">\n";
		}
		$c = get_post_meta( $id, '_hsar_canonical', true );
		if ( $c ) {
			remove_action( 'wp_head', 'rel_canonical' );
			printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $c ) );
		}
	}

	/* ---------- بررسی کیفیت ---------- */

	/**
	 * @return array{score:int,items:array<int,array{0:string,1:string}>,blocking:bool}  هر آیتم: [ok|warn|bad, متن]
	 */
	public static function check( $a, $opt ) {
		$items = array();
		$html  = (string) $a['content'];
		$text  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
		$words = $text ? count( preg_split( '/\s+/u', $text ) ) : 0;
		$kw    = trim( (string) $a['keyword'] );
		$desc  = trim( (string) $a['description'] );
		$stitle = trim( (string) ( $a['seo_title'] ?: $a['title'] ) );
		$min   = (int) ( $opt['min_words'] ?? 300 );

		$items[] = $words >= $min ? array( 'ok', "طول متن: {$words} کلمه" ) : array( 'bad', "متن کوتاه است: {$words} کلمه (حداقل {$min})" );
		$h2 = preg_match_all( '/<h2\b/i', $html );
		$items[] = $h2 >= 2 ? array( 'ok', "{$h2} تیتر H2 (برای فهرست مطالب)" ) : array( 'warn', 'کمتر از ۲ تیتر H2؛ فهرست مطالب ساخته نمی‌شود' );
		$items[] = preg_match( '/<h1\b/i', $html ) ? array( 'warn', 'داخل متن تیتر H1 هست؛ عنوان مقاله خودش H1 است' ) : array( 'ok', 'H1 تکراری در متن نیست' );
		$dl = mb_strlen( $desc );
		$items[] = ! $desc ? array( 'bad', 'توضیح متا خالی است' ) : ( $dl < 70 || $dl > 160 ? array( 'warn', "طول توضیح متا {$dl} نویسه (بهتر: ۷۰ تا ۱۶۰)" ) : array( 'ok', "توضیح متا {$dl} نویسه" ) );
		$tl = mb_strlen( $stitle );
		$items[] = $tl > 65 ? array( 'warn', "عنوان سئو بلند است ({$tl} نویسه؛ بهتر: کمتر از ۶۵)" ) : array( 'ok', "عنوان سئو {$tl} نویسه" );
		if ( $kw ) {
			$in_title = false !== mb_stripos( $stitle, $kw );
			$first    = mb_substr( $text, 0, 400 );
			$in_first = false !== mb_stripos( $first, $kw );
			$in_desc  = false !== mb_stripos( $desc, $kw );
			$count    = $text ? mb_substr_count( mb_strtolower( $text ), mb_strtolower( $kw ) ) : 0;
			$dens     = $words ? round( 100 * $count * max( 1, count( preg_split( '/\s+/u', $kw ) ) ) / $words, 1 ) : 0;
			$items[] = $in_title ? array( 'ok', 'کلمه کلیدی در عنوان هست' ) : array( 'warn', 'کلمه کلیدی در عنوان نیست' );
			$items[] = $in_first ? array( 'ok', 'کلمه کلیدی در ابتدای متن هست' ) : array( 'warn', 'کلمه کلیدی در پاراگراف اول نیست' );
			$items[] = $in_desc ? array( 'ok', 'کلمه کلیدی در توضیح متا هست' ) : array( 'warn', 'کلمه کلیدی در توضیح متا نیست' );
			$items[] = $dens > 3 ? array( 'warn', "تکرار کلمه کلیدی زیاد است ({$dens}٪)" ) : ( $count ? array( 'ok', "تکرار کلمه کلیدی {$count} بار ({$dens}٪)" ) : array( 'warn', 'کلمه کلیدی در متن تکرار نشده' ) );
		} else {
			$items[] = array( 'warn', 'کلمه کلیدی کانونی تعیین نشده' );
		}
		$imgs   = preg_match_all( '/<img\b/i', $html );
		$noalt  = preg_match_all( '/<img\b(?![^>]*\balt=["\'][^"\']+)[^>]*>/i', $html );
		$items[] = $imgs ? ( $noalt ? array( 'warn', "{$noalt} تصویر بدون متن جایگزین (خودکار پر می‌شود)" ) : array( 'ok', "{$imgs} تصویر، همه با متن جایگزین" ) ) : array( 'warn', 'مقاله تصویر ندارد' );
		$links  = preg_match_all( '/<a\s[^>]*href=/i', $html );
		$items[] = $links ? array( 'ok', "{$links} لینک در متن" ) : array( 'warn', 'لینکی در متن نیست (داخلی یا منبع)' );
		$items[] = $a['image'] || $imgs ? array( 'ok', 'تصویر شاخص مشخص است' ) : array( 'warn', 'تصویر شاخص ندارد' );

		$bad   = count( array_filter( $items, function ( $i ) { return 'bad' === $i[0]; } ) );
		$ok    = count( array_filter( $items, function ( $i ) { return 'ok' === $i[0]; } ) );
		$score = (int) round( 100 * $ok / max( 1, count( $items ) ) );
		return array( 'score' => $score, 'items' => $items, 'blocking' => $bad > 0 );
	}
}
