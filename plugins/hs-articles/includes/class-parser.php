<?php
/**
 * تبدیل ورودی (متن، TXT، HTML، ZIP) به مقاله: عنوان، محتوا و اطلاعات سئو.
 *
 * فرمت اختیاری سرصفحه برای متن/TXT (و HTML):
 * ---
 * title: عنوان مقاله
 * seo_title: عنوان سئو
 * description: توضیح متا
 * keyword: کلمه کلیدی کانونی
 * slug: نامک
 * category: آموزشی، پرورشی
 * tags: برچسب ۱، برچسب ۲
 * image: https://.../cover.jpg
 * ---
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSAR_Parser {

	const META_KEYS = array( 'title', 'seo_title', 'description', 'keyword', 'slug', 'category', 'tags', 'image', 'excerpt', 'grade', 'audience' );

	/** متن خام یا HTML را تشخیص می‌دهد و تبدیل می‌کند */
	public static function from_string( $raw, $hint = '' ) {
		$raw = self::clean_bom( (string) $raw );
		list( $meta, $raw ) = self::front_matter( $raw );
		$is_html = 'html' === $hint || ( 'txt' !== $hint && preg_match( '/<(p|h[1-6]|div|ul|ol|img|br|article|body|html)\b/i', $raw ) );
		$a = $is_html ? self::html( $raw ) : self::text( $raw );
		foreach ( $meta as $k => $v ) {
			if ( '' !== $v ) {
				$a[ $k ] = $v;
			}
		}
		return self::finish( $a );
	}

	/** @return array{0:array,1:string} */
	public static function front_matter( $raw ) {
		$meta = array();
		if ( preg_match( '/^\s*---\s*\R(.*?)\R---\s*\R/su', $raw, $m ) ) {
			foreach ( preg_split( '/\R/u', $m[1] ) as $line ) {
				if ( preg_match( '/^\s*([a-z_]+)\s*:\s*(.*)$/iu', $line, $kv ) ) {
					$k = strtolower( $kv[1] );
					$aliases = array( 'عنوان' => 'title', 'meta' => 'description', 'focus_keyword' => 'keyword', 'focus' => 'keyword', 'categories' => 'category', 'tag' => 'tags', 'cover' => 'image', 'thumbnail' => 'image', 'grades' => 'grade', 'paye' => 'grade', 'mokhatab' => 'audience' );
					$k = $aliases[ $k ] ?? $k;
					if ( in_array( $k, self::META_KEYS, true ) ) {
						$meta[ $k ] = trim( $kv[2], " \t\"'" );
					}
				}
			}
			$raw = substr( $raw, strlen( $m[0] ) );
		}
		return array( $meta, $raw );
	}

	/** متن ساده / مارک‌داون ساده → HTML */
	public static function text( $raw ) {
		$title  = '';
		$blocks = preg_split( '/\R\s*\R/u', trim( str_replace( "\r", '', $raw ) ) );
		$html   = array();
		foreach ( $blocks as $b ) {
			$b = trim( $b );
			if ( '' === $b ) {
				continue;
			}
			if ( preg_match( '/^(#{1,4})\s+(.+)$/u', $b, $m ) && false === strpos( $b, "\n" ) ) {
				$lvl = strlen( $m[1] );
				if ( 1 === $lvl && '' === $title ) {
					$title = trim( $m[2] );
					continue;
				}
				$lvl    = max( 2, $lvl );
				$html[] = "<h{$lvl}>" . self::inline( $m[2] ) . "</h{$lvl}>";
				continue;
			}
			$lines = explode( "\n", $b );
			if ( count( array_filter( $lines, function ( $l ) { return (bool) preg_match( '/^\s*([-*•]|\d+[.)])\s+/u', $l ); } ) ) === count( $lines ) ) {
				$ordered = (bool) preg_match( '/^\s*\d/', $lines[0] );
				$items   = array_map( function ( $l ) { return '<li>' . self::inline( preg_replace( '/^\s*([-*•]|\d+[.)])\s+/u', '', $l ) ) . '</li>'; }, $lines );
				$html[]  = ( $ordered ? '<ol>' : '<ul>' ) . implode( '', $items ) . ( $ordered ? '</ol>' : '</ul>' );
				continue;
			}
			if ( preg_match( '/^!\[([^\]]*)\]\(([^)\s]+)\)$/', $b, $m ) ) {
				$html[] = '<figure><img src="' . esc_url( $m[2] ) . '" alt="' . esc_attr( $m[1] ) . '"></figure>';
				continue;
			}
			if ( 0 === strpos( $b, '>' ) ) {
				$html[] = '<blockquote><p>' . self::inline( preg_replace( '/^>\s?/m', '', $b ) ) . '</p></blockquote>';
				continue;
			}
			// خط اول کوتاه و بدون نقطه در ابتدای فایل → عنوان
			if ( '' === $title && ! $html && count( $lines ) === 1 && mb_strlen( $b ) < 120 && ! preg_match( '/[.!؟?:]$/u', $b ) ) {
				$title = $b;
				continue;
			}
			$html[] = '<p>' . implode( '<br>', array_map( array( __CLASS__, 'inline' ), $lines ) ) . '</p>';
		}
		return array( 'title' => $title, 'content' => implode( "\n", $html ) );
	}

	/** پررنگ، مورب و لینک ساده */
	private static function inline( $s ) {
		$s = esc_html( trim( $s ) );
		$s = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s );
		$s = preg_replace( '/(?<!\*)\*(?!\*)(.+?)\*/u', '<em>$1</em>', $s );
		$s = preg_replace_callback( '/\[([^\]]+)\]\((https?:[^)\s]+)\)/u', function ( $m ) { return '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '">' . $m[1] . '</a>'; }, $s );
		return $s;
	}

	/** HTML کامل یا تکه‌ای → محتوا + عنوان/توضیح از تگ‌های head */
	public static function html( $raw ) {
		$a = array( 'title' => '', 'content' => '' );
		if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $raw, $m )
			|| preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/i', $raw, $m ) ) {
			$a['description'] = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		if ( preg_match( '/<meta[^>]+name=["\']keywords["\'][^>]*content=["\']([^"\',]*)/i', $raw, $m ) ) {
			$a['keyword'] = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
		}
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $raw, $m ) ) {
			$a['seo_title'] = trim( wp_strip_all_tags( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) ) );
		}
		$body = preg_match( '/<body[^>]*>(.*)<\/body>/is', $raw, $m ) ? $m[1] : $raw;
		$body = preg_replace( '#<(script|style|noscript|iframe|form|nav|header|footer)\b.*?</\1>#is', '', $body );
		$body = preg_replace( '#<!--.*?-->#s', '', $body );
		if ( preg_match( '#<h1[^>]*>(.*?)</h1>#is', $body, $m ) ) {
			$a['title'] = trim( wp_strip_all_tags( $m[1] ) );
			$body       = preg_replace( '#<h1[^>]*>.*?</h1>#is', '', $body, 1 );
		}
		if ( '' === $a['title'] && ! empty( $a['seo_title'] ) ) {
			$a['title'] = $a['seo_title'];
		}
		$a['content'] = trim( $body );
		return $a;
	}

	private static function finish( $a ) {
		$a = array_merge( array_fill_keys( self::META_KEYS, '' ), array( 'content' => '' ), $a );
		$a['content'] = wp_kses_post( $a['content'] );
		if ( '' === $a['title'] ) {
			$a['title'] = wp_trim_words( wp_strip_all_tags( $a['content'] ), 8, '' );
		}
		return $a;
	}

	private static function clean_bom( $s ) {
		return 0 === strpos( $s, "\xEF\xBB\xBF" ) ? substr( $s, 3 ) : $s;
	}

	/**
	 * فایل ZIP: هر فایل html/htm/txt یک مقاله؛ تصاویر داخل ZIP برای جایگزینی آدرس‌های نسبی.
	 * فایل‌ها روی دیسک باز نمی‌شوند (جلوگیری از Zip Slip).
	 * @return array{articles:array,images:array,errors:array}
	 */
	public static function zip( $path, $max_mb = 50 ) {
		$out = array( 'articles' => array(), 'images' => array(), 'errors' => array() );
		if ( ! class_exists( 'ZipArchive' ) ) {
			$out['errors'][] = 'افزونه‌ی ZIP روی سرور فعال نیست.';
			return $out;
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $path ) ) {
			$out['errors'][] = 'فایل ZIP باز نشد.';
			return $out;
		}
		$total = 0;
		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			$st   = $z->statIndex( $i );
			$name = str_replace( '\\', '/', (string) $st['name'] );
			if ( '/' === substr( $name, -1 ) || false !== strpos( $name, '__MACOSX' ) || false !== strpos( $name, '..' ) ) {
				continue;
			}
			$total += (int) $st['size'];
			if ( $total > $max_mb * MB_IN_BYTES ) {
				$out['errors'][] = 'حجم فایل‌های داخل ZIP بیش از حد مجاز است.';
				break;
			}
			$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( in_array( $ext, array( 'html', 'htm', 'txt', 'md' ), true ) ) {
				$a          = self::from_string( (string) $z->getFromIndex( $i ), 'txt' === $ext || 'md' === $ext ? 'txt' : 'html' );
				$a['_file'] = $name;
				$out['articles'][] = $a;
			} elseif ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
				$out['images'][ $name ] = $i;
			}
		}
		$out['_zip'] = $z;
		return $out;
	}
}
