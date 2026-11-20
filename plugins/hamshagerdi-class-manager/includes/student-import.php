<?php
/**
 * ورود گروهی دانش‌آموزان از فایل اکسل (xlsx) یا CSV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Student_Import {

	const MAX_ROWS = 200;

	public static function init() {
		add_action( 'wp_ajax_hs_import_students', array( __CLASS__, 'ajax_import' ) );
	}

	public static function fa_to_en( $s ) {
		return strtr( (string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) );
	}

	/** نام ستون → کلید */
	private static function header_key( $h ) {
		$h = trim( self::fa_to_en( $h ) );
		$h = str_replace( array( 'ي', 'ك', "\xE2\x80\x8C" ), array( 'ی', 'ک', ' ' ), $h );
		$h = mb_strtolower( preg_replace( '/\s+/u', ' ', $h ) );
		$map = array(
			'شماره' => 'class_number', 'ردیف' => 'class_number', 'شماره کلاس' => 'class_number', 'شماره در کلاس' => 'class_number', 'number' => 'class_number', 'no' => 'class_number',
			'نام' => 'first_name', 'first name' => 'first_name', 'first_name' => 'first_name',
			'نام خانوادگی' => 'last_name', 'فامیل' => 'last_name', 'last name' => 'last_name', 'last_name' => 'last_name',
			'نام و نام خانوادگی' => 'name', 'نام کامل' => 'name', 'name' => 'name',
			'نام والد' => 'parent_name', 'نام پدر' => 'parent_name', 'نام پدر/مادر' => 'parent_name', 'نام پدر / مادر' => 'parent_name', 'والد' => 'parent_name',
			'تلفن والد' => 'parent_phone', 'تلفن' => 'parent_phone', 'موبایل' => 'parent_phone', 'شماره تماس' => 'parent_phone', 'شماره تماس والد' => 'parent_phone', 'شماره موبایل والد' => 'parent_phone', 'phone' => 'parent_phone',
			'تاریخ تولد' => 'birth_date', 'تولد' => 'birth_date', 'birth' => 'birth_date',
			'وضعیت' => 'status', 'status' => 'status',
			'یادداشت' => 'notes', 'توضیحات' => 'notes', 'notes' => 'notes',
		);
		return isset( $map[ $h ] ) ? $map[ $h ] : '';
	}

	public static function parse_csv( $path ) {
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $raw ) {
			return new WP_Error( 'read', 'فایل خوانده نشد.' );
		}
		// حذف BOM و تبدیل UTF-16 (خروجی «Unicode Text» اکسل)
		if ( 0 === strpos( $raw, "\xFF\xFE" ) ) {
			$raw = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16LE' );
		} elseif ( 0 === strpos( $raw, "\xFE\xFF" ) ) {
			$raw = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16BE' );
		} else {
			$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
			if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
				$raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1256' );
			}
		}
		$first = strtok( $raw, "\n" );
		$delim = ',';
		if ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) {
			$delim = ';';
		} elseif ( substr_count( $first, "\t" ) > substr_count( $first, ',' ) ) {
			$delim = "\t";
		}
		$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $fh, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		rewind( $fh );
		$rows = array();
		while ( ( $data = fgetcsv( $fh, 0, $delim ) ) !== false ) {
			$rows[] = array_map( 'strval', $data );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $rows;
	}

	public static function parse_xlsx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip', 'روی این سرور خواندن xlsx فعال نیست؛ فایل را به‌صورت CSV ذخیره و بارگذاری کنید.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'open', 'فایل اکسل باز نشد.' );
		}
		$shared = array();
		$ss     = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( $ss ) {
			$sx = @simplexml_load_string( $ss );
			if ( $sx ) {
				foreach ( $sx->si as $si ) {
					$text = '';
					if ( isset( $si->t ) ) {
						$text = (string) $si->t;
					} elseif ( isset( $si->r ) ) {
						foreach ( $si->r as $r ) {
							$text .= (string) $r->t;
						}
					}
					$shared[] = $text;
				}
			}
		}
		$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		if ( ! $sheet ) {
			// نام برگه ممکن است متفاوت باشد
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$n = $zip->getNameIndex( $i );
				if ( 0 === strpos( $n, 'xl/worksheets/' ) && '.xml' === substr( $n, -4 ) ) {
					$sheet = $zip->getFromIndex( $i );
					break;
				}
			}
		}
		$zip->close();
		if ( ! $sheet ) {
			return new WP_Error( 'sheet', 'برگهٔ اول خوانده نشد.' );
		}
		$xml = @simplexml_load_string( $sheet );
		if ( ! $xml || ! isset( $xml->sheetData ) ) {
			return new WP_Error( 'xml', 'ساختار فایل اکسل نامعتبر است.' );
		}
		$grid = array();
		$ri   = 0;
		foreach ( $xml->sheetData->row as $row ) {
			$ri = isset( $row['r'] ) ? (int) $row['r'] : $ri + 1;
			foreach ( $row->c as $c ) {
				if ( ! preg_match( '/^([A-Z]+)\d+$/', (string) $c['r'], $m ) ) {
					continue;
				}
				$col = 0;
				for ( $i = 0, $n = strlen( $m[1] ); $i < $n; $i++ ) {
					$col = $col * 26 + ( ord( $m[1][ $i ] ) - 64 );
				}
				$col--;
				$t = isset( $c['t'] ) ? (string) $c['t'] : '';
				if ( 's' === $t ) {
					$val = isset( $shared[ (int) $c->v ] ) ? $shared[ (int) $c->v ] : '';
				} elseif ( 'inlineStr' === $t ) {
					$val = isset( $c->is->t ) ? (string) $c->is->t : '';
				} else {
					$val = isset( $c->v ) ? (string) $c->v : '';
				}
				$grid[ $ri ][ $col ] = $val;
			}
		}
		ksort( $grid );
		$rows = array();
		foreach ( $grid as $r ) {
			$max = $r ? max( array_keys( $r ) ) : -1;
			$line = array();
			for ( $i = 0; $i <= $max; $i++ ) {
				$line[] = isset( $r[ $i ] ) ? (string) $r[ $i ] : '';
			}
			$rows[] = $line;
		}
		return $rows;
	}

	/** جلالی → میلادی (برای تاریخ تولد نوشته‌شده به‌صورت 1395/04/12) */
	private static function j2g( $jy, $jm, $jd ) {
		$jy  += 1595;
		$days = -355668 + ( 365 * $jy ) + ( (int) ( $jy / 33 ) * 8 ) + (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * (int) ( $days / 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			$gy  += 100 * (int) ( --$days / 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}
		$gy  += 4 * (int) ( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy  += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd = $days + 1;
		$sal_a = array( 0, 31, ( ( $gy % 4 == 0 && $gy % 100 != 0 ) || ( $gy % 400 == 0 ) ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 0; $gm < 13 && $gd > $sal_a[ $gm ]; $gm++ ) {
			$gd -= $sal_a[ $gm ];
		}
		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	private static function norm_date( $v ) {
		$v = trim( self::fa_to_en( $v ) );
		if ( '' === $v ) {
			return '';
		}
		if ( preg_match( '/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $v, $m ) ) {
			$y = (int) $m[1];
			if ( $y >= 1300 && $y <= 1500 ) {
				return self::j2g( $y, (int) $m[2], (int) $m[3] );
			}
			return sprintf( '%04d-%02d-%02d', $y, (int) $m[2], (int) $m[3] );
		}
		if ( preg_match( '/^\d{5}(\.\d+)?$/', $v ) ) { // سریال تاریخ اکسل
			return gmdate( 'Y-m-d', (int) ( ( (float) $v - 25569 ) * 86400 ) );
		}
		return '';
	}

	private static function norm_phone( $v ) {
		$v = preg_replace( '/[^0-9]/', '', self::fa_to_en( $v ) );
		if ( '' !== $v && 10 === strlen( $v ) && '9' === $v[0] ) {
			$v = '0' . $v;
		}
		return $v;
	}

	/** ردیف‌های خام → دانش‌آموزان */
	public static function rows_to_students( $rows ) {
		$rows = array_values( array_filter( $rows, function ( $r ) {
			return count( array_filter( $r, function ( $c ) {
				return '' !== trim( (string) $c );
			} ) ) > 0;
		} ) );
		if ( count( $rows ) < 2 ) {
			return new WP_Error( 'empty', 'فایل خالی است یا فقط ردیف عنوان دارد.' );
		}
		$map = array();
		foreach ( $rows[0] as $i => $h ) {
			$k = self::header_key( $h );
			if ( $k && ! in_array( $k, $map, true ) ) {
				$map[ $i ] = $k;
			}
		}
		$has_name = in_array( 'name', $map, true ) || in_array( 'first_name', $map, true ) || in_array( 'last_name', $map, true );
		if ( ! $has_name ) {
			return new WP_Error( 'header', 'ستون «نام» و «نام خانوادگی» در ردیف اول فایل پیدا نشد. از «دانلود فایل نمونه» استفاده کنید.' );
		}
		$statuses = array_flip( HS_Student_Profile::statuses() );
		$out      = array();
		foreach ( array_slice( $rows, 1 ) as $r ) {
			$d = array();
			foreach ( $map as $i => $k ) {
				$d[ $k ] = isset( $r[ $i ] ) ? trim( (string) $r[ $i ] ) : '';
			}
			$d = wp_parse_args( $d, array( 'class_number' => '', 'first_name' => '', 'last_name' => '', 'name' => '', 'parent_name' => '', 'parent_phone' => '', 'birth_date' => '', 'status' => '', 'notes' => '' ) );
			if ( '' === $d['first_name'] && '' === $d['last_name'] && '' === $d['name'] ) {
				continue;
			}
			$d['class_number'] = self::fa_to_en( $d['class_number'] );
			$d['parent_phone'] = self::norm_phone( $d['parent_phone'] );
			$d['birth_date']   = self::norm_date( $d['birth_date'] );
			$d['status']       = isset( $statuses[ $d['status'] ] ) ? $statuses[ $d['status'] ] : ( array_key_exists( $d['status'], HS_Student_Profile::statuses() ) ? $d['status'] : 'active' );
			$out[]             = $d;
		}
		if ( ! $out ) {
			return new WP_Error( 'none', 'هیچ دانش‌آموزی در فایل پیدا نشد.' );
		}
		return $out;
	}

	public static function ajax_import() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست نامعتبر. صفحه را رفرش کنید.' ) );
		}
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		if ( empty( $_FILES['file'] ) || ! empty( $_FILES['file']['error'] ) || empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'فایلی انتخاب نشده است.' ) );
		}
		$f = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( (int) $f['size'] > 2 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => 'حجم فایل باید کمتر از ۲ مگابایت باشد.' ) );
		}
		$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'xlsx', 'csv', 'txt' ), true ) ) {
			wp_send_json_error( array( 'message' => 'فقط فایل xlsx یا csv پذیرفته می‌شود.' ) );
		}
		$rows = 'xlsx' === $ext ? self::parse_xlsx( $f['tmp_name'] ) : self::parse_csv( $f['tmp_name'] );
		if ( is_wp_error( $rows ) ) {
			wp_send_json_error( array( 'message' => $rows->get_error_message() ) );
		}
		$students = self::rows_to_students( $rows );
		if ( is_wp_error( $students ) ) {
			wp_send_json_error( array( 'message' => $students->get_error_message() ) );
		}
		if ( count( $students ) > self::MAX_ROWS ) {
			wp_send_json_error( array( 'message' => 'در هر بار حداکثر ' . self::MAX_ROWS . ' دانش‌آموز وارد می‌شود.' ) );
		}

		// بررسی تکراری (نام+نام خانوادگی+شماره والد، و شماره در کلاس) در create_in_class انجام می‌شود
		$created = array();
		$skipped = array();
		$failed  = array();
		foreach ( $students as $i => $d ) {
			$label = trim( $d['first_name'] . ' ' . $d['last_name'] ) ?: $d['name'];
			$res = HS_Student_Profile::create_in_class( $class_id, $d );
			if ( is_wp_error( $res ) && in_array( $res->get_error_code(), array( 'duplicate', 'duplicate_number' ), true ) ) {
				$skipped[] = $label . ' (' . $res->get_error_message() . ')';
				continue;
			}
			if ( is_wp_error( $res ) ) {
				$failed[] = $label . ': ' . $res->get_error_message();
				continue;
			}
			$created[] = array(
				'name'     => $label,
				'username' => $res['username'],
				'password' => $res['password'],
				'code'     => class_exists( 'HS_Parent_Link' ) ? HS_Parent_Link::get_code( $res['user_id'] ) : '',
			);
		}

		wp_send_json_success( array(
			'message' => sprintf( '%d دانش‌آموز اضافه شد%s%s.', count( $created ), $skipped ? '، ' . count( $skipped ) . ' تکراری رد شد' : '', $failed ? '، ' . count( $failed ) . ' خطا' : '' ),
			'created' => $created,
			'skipped' => $skipped,
			'failed'  => $failed,
			'count'   => HS_Class_Students::count( $class_id ),
		) );
	}
}

HS_Student_Import::init();
