<?php
/**
 * استخراج متن از PDF و Word
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Content_Extract {

	/**
	 * استخراج متن از فایل آپلودشده در media library یا مسیر مطلق
	 */
	public static function from_file( $file_path, $mime = '' ) {
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'no_file', 'فایل یافت نشد.' );
		}

		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! $mime ) {
			$mime = mime_content_type( $file_path );
		}

		if ( $ext === 'pdf' || strpos( $mime, 'pdf' ) !== false ) {
			return self::from_pdf( $file_path );
		}
		if ( in_array( $ext, array( 'docx' ), true ) || strpos( $mime, 'wordprocessingml' ) !== false ) {
			return self::from_docx( $file_path );
		}
		if ( in_array( $ext, array( 'doc' ), true ) ) {
			return new WP_Error( 'doc_old', 'فرمت DOC قدیمی پشتیبانی نمی‌شود. لطفاً به DOCX تبدیل کنید.' );
		}
		if ( in_array( $ext, array( 'txt', 'md' ), true ) || strpos( $mime, 'text/' ) === 0 ) {
			$text = file_get_contents( $file_path );
			return is_string( $text ) ? self::normalize( $text ) : new WP_Error( 'read', 'خواندن فایل ممکن نشد.' );
		}

		return new WP_Error( 'unsupported', 'فرمت پشتیبانی‌نشده. PDF، DOCX یا TXT بفرستید.' );
	}

	public static function from_pdf( $path ) {
		$bin = self::find_pdftotext();
		if ( ! $bin ) {
			return new WP_Error( 'no_pdftotext', 'ابزار pdftotext روی سرور نصب نیست.' );
		}

		$tmp = tempnam( sys_get_temp_dir(), 'hs_pdf_' );
		$cmd = escapeshellarg( $bin ) . ' -layout -enc UTF-8 ' . escapeshellarg( $path ) . ' ' . escapeshellarg( $tmp ) . ' 2>&1';
		exec( $cmd, $out, $code );

		if ( ! file_exists( $tmp ) ) {
			return new WP_Error( 'pdf_fail', 'استخراج PDF ناموفق بود: ' . implode( ' ', $out ) );
		}

		$text = file_get_contents( $tmp );
		@unlink( $tmp );

		if ( $text === false || trim( $text ) === '' ) {
			return new WP_Error( 'pdf_empty', 'متنی از PDF استخراج نشد (ممکن است اسکن تصویری باشد).' );
		}

		return self::normalize( $text );
	}

	public static function from_docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'no_zip', 'افزونه ZipArchive روی PHP فعال نیست.' );
		}

		$zip = new ZipArchive();
		if ( $zip->open( $path ) !== true ) {
			return new WP_Error( 'docx_open', 'باز کردن فایل Word ممکن نشد.' );
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( ! $xml ) {
			return new WP_Error( 'docx_empty', 'محتوای document.xml یافت نشد.' );
		}

		// تبدیل تگ‌های پاراگراف به خط جدید
		$xml = preg_replace( '/<\/w:p>/', "\n", $xml );
		$xml = preg_replace( '/<w:tab[^\/]*\/>/', "\t", $xml );
		$text = strip_tags( $xml );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );

		return self::normalize( $text );
	}

	public static function normalize( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		$text = trim( $text );
		return $text;
	}

	private static function find_pdftotext() {
		$candidates = array( '/usr/bin/pdftotext', '/usr/local/bin/pdftotext', 'pdftotext' );
		foreach ( $candidates as $c ) {
			if ( $c === 'pdftotext' ) {
				$which = trim( (string) shell_exec( 'which pdftotext 2>/dev/null' ) );
				if ( $which && is_executable( $which ) ) {
					return $which;
				}
			} elseif ( is_executable( $c ) ) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * تبدیل متن خام به HTML ساده پاراگراف‌ها
	 */
	public static function text_to_html( $text ) {
		$parts = preg_split( "/\n{2,}/", trim( $text ) );
		$html = '';
		foreach ( $parts as $p ) {
			$p = trim( $p );
			if ( $p === '' ) {
				continue;
			}
			// عنوان‌گونه اگر کوتاه و بدون نقطه باشد
			if ( mb_strlen( $p ) < 80 && ! preg_match( '/[.؟!]$/u', $p ) && substr_count( $p, "\n" ) === 0 ) {
				$html .= '<h2>' . esc_html( $p ) . '</h2>' . "\n";
			} else {
				$lines = array_map( 'esc_html', explode( "\n", $p ) );
				$html .= '<p>' . implode( '<br>', $lines ) . '</p>' . "\n";
			}
		}
		return $html;
	}
}
