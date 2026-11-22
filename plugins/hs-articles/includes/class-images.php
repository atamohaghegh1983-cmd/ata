<?php
/**
 * تصاویر مقاله: دانلود از آدرس داده‌شده (یا برداشتن از ZIP)، ذخیره در کتابخانه‌ی رسانه،
 * تبدیل اختیاری به WebP، جایگزینی آدرس در متن و پر کردن متن جایگزین (alt).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSAR_Images {

	const MAX = 40; // حداکثر تصویر در هر مقاله

	public static function load_deps() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	/**
	 * @param string          $html
	 * @param int             $post_id
	 * @param array           $opt   webp(bool) quality(int) alt(string)
	 * @param ZipArchive|null $zip
	 * @param array           $zip_images نام فایل در ZIP => اندیس
	 * @param string          $base_dir پوشه‌ی فایل مقاله در ZIP (برای آدرس نسبی)
	 * @return array{html:string,ids:int[],errors:string[]}
	 */
	public static function process( $html, $post_id, $opt, $zip = null, $zip_images = array(), $base_dir = '' ) {
		self::load_deps();
		$ids = array();
		$errors = array();
		$done = array();
		$n = 0;
		$html = preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) use ( $post_id, $opt, $zip, $zip_images, $base_dir, &$ids, &$errors, &$done, &$n ) {
			$tag = $m[0];
			if ( ! preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $s ) ) {
				return $tag;
			}
			$src = html_entity_decode( $s[1] );
			if ( 0 === strpos( $src, 'data:' ) || self::is_local( $src ) ) {
				return self::ensure_alt( $tag, $opt['alt'] );
			}
			if ( ++$n > self::MAX ) {
				return $tag;
			}
			$alt = preg_match( '/\balt=["\']([^"\']*)["\']/i', $tag, $a ) && '' !== trim( $a[1] ) ? $a[1] : $opt['alt'];
			if ( ! isset( $done[ $src ] ) ) {
				$r = preg_match( '#^https?://#i', $src )
					? self::from_url( $src, $post_id, $alt, $opt )
					: self::from_zip( $src, $base_dir, $zip, $zip_images, $post_id, $alt, $opt );
				if ( is_wp_error( $r ) ) {
					$errors[] = $src . ' → ' . $r->get_error_message();
					return $tag;
				}
				$done[ $src ] = $r;
				$ids[]        = $r;
			}
			$id  = $done[ $src ];
			$url = wp_get_attachment_url( $id );
			$tag = preg_replace( '/\bsrc=["\'][^"\']+["\']/i', 'src="' . esc_url( $url ) . '"', $tag );
			$tag = preg_replace( '/\s(srcset|sizes)=["\'][^"\']*["\']/i', '', $tag );
			$tag = preg_replace( '/\sclass=["\']([^"\']*)["\']/i', ' class="$1 wp-image-' . $id . '"', $tag, 1, $had );
			if ( ! $had ) {
				$tag = str_replace( '<img', '<img class="wp-image-' . $id . '"', $tag );
			}
			if ( false === stripos( $tag, 'loading=' ) ) {
				$tag = str_replace( '<img', '<img loading="lazy"', $tag );
			}
			return self::ensure_alt( $tag, $alt );
		}, $html );
		return array( 'html' => $html, 'ids' => $ids, 'errors' => $errors );
	}

	private static function is_local( $src ) {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = wp_parse_url( $src, PHP_URL_HOST );
		return $host && $home && strtolower( $host ) === strtolower( $home ) && false !== strpos( $src, '/wp-content/uploads/' );
	}

	private static function ensure_alt( $tag, $alt ) {
		if ( preg_match( '/\balt=["\']\s*["\']/i', $tag ) ) {
			return preg_replace( '/\balt=["\']\s*["\']/i', 'alt="' . esc_attr( $alt ) . '"', $tag );
		}
		return false === stripos( $tag, ' alt=' ) ? str_replace( '<img', '<img alt="' . esc_attr( $alt ) . '"', $tag ) : $tag;
	}

	/** دانلود امن (wp_safe_remote_get از آدرس‌های داخلی شبکه جلوگیری می‌کند) */
	public static function from_url( $url, $post_id, $alt, $opt ) {
		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( ! $url ) {
			return new WP_Error( 'url', 'آدرس نامعتبر' );
		}
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'dl', 'دانلود نشد: ' . $tmp->get_error_message() );
		}
		$name = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) ?: 'image';
		return self::store( $tmp, $name, $post_id, $alt, $opt );
	}

	public static function from_zip( $src, $base_dir, $zip, $zip_images, $post_id, $alt, $opt ) {
		if ( ! $zip ) {
			return new WP_Error( 'rel', 'آدرس نسبی است؛ تصویر باید داخل ZIP باشد یا آدرس کامل (https://...) داشته باشد.' );
		}
		$path = self::norm( ltrim( $base_dir . '/' . preg_replace( '#[?#].*$#', '', $src ), '/' ) );
		$key  = isset( $zip_images[ $path ] ) ? $path : null;
		if ( null === $key ) { // جستجو با نام فایل
			foreach ( $zip_images as $k => $i ) {
				if ( strtolower( wp_basename( $k ) ) === strtolower( wp_basename( $src ) ) ) {
					$key = $k;
					break;
				}
			}
		}
		if ( null === $key ) {
			return new WP_Error( 'nf', 'داخل ZIP پیدا نشد' );
		}
		$tmp = wp_tempnam( wp_basename( $key ) );
		file_put_contents( $tmp, $zip->getFromIndex( $zip_images[ $key ] ) ); // phpcs:ignore
		return self::store( $tmp, sanitize_file_name( wp_basename( $key ) ), $post_id, $alt, $opt );
	}

	private static function norm( $p ) {
		$out = array();
		foreach ( explode( '/', $p ) as $seg ) {
			if ( '..' === $seg ) {
				array_pop( $out );
			} elseif ( '' !== $seg && '.' !== $seg ) {
				$out[] = $seg;
			}
		}
		return implode( '/', $out );
	}

	private static function store( $tmp, $name, $post_id, $alt, $opt ) {
		$type = wp_check_filetype_and_ext( $tmp, $name );
		if ( empty( $type['type'] ) || 0 !== strpos( $type['type'], 'image/' ) ) {
			$info = @getimagesize( $tmp ); // phpcs:ignore
			if ( ! $info ) {
				@unlink( $tmp ); // phpcs:ignore
				return new WP_Error( 'type', 'فایل تصویر معتبر نیست' );
			}
			$name = preg_replace( '/\.[^.]*$/', '', $name ) . image_type_to_extension( $info[2] );
		}
		if ( ! empty( $opt['webp'] ) ) {
			$c = self::to_webp( $tmp, (int) ( $opt['quality'] ?? 82 ), (int) ( $opt['max_w'] ?? 1600 ) );
			if ( $c ) {
				@unlink( $tmp ); // phpcs:ignore
				$tmp  = $c;
				$name = preg_replace( '/\.[^.]*$/', '', $name ) . '.webp';
			}
		}
		$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), $post_id, $alt );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		return (int) $id;
	}

	/** @return string|false مسیر فایل WebP موقت */
	private static function to_webp( $file, $q, $max_w ) {
		$ed = wp_get_image_editor( $file );
		if ( is_wp_error( $ed ) || ! $ed->supports_mime_type( 'image/webp' ) ) {
			return false;
		}
		$size = $ed->get_size();
		if ( $max_w && $size['width'] > $max_w ) {
			$ed->resize( $max_w, null );
		}
		$ed->set_quality( max( 40, min( 100, $q ) ) );
		$dest = wp_tempnam( 'hsar' ) . '.webp';
		$r    = $ed->save( $dest, 'image/webp' );
		return is_wp_error( $r ) ? false : $r['path'];
	}
}
