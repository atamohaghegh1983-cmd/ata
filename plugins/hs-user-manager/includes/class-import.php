<?php
/**
 * ورود گروهی دانش‌آموزان و شماره والدین (Excel یا CSV)
 * مجاز: مدیر کل، مدیر مدرسه (کلاس‌های معلمان مدرسه‌اش)، معلم (کلاس‌های خودش)
 * مراحل: فایل ← پیش‌نمایش با خطاها و تکراری‌ها ← تأیید ← ساخت دانش‌آموز (بدون تکرار) و پیشنهاد اتصال والد
 * نسخه اول: فقط یک والد اولیه؛ والد دوم با دعوت والد اول.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Import {

	public static function init() {
		add_action( 'admin_post_hsum_import_upload', array( __CLASS__, 'upload' ) );
		add_action( 'admin_post_hsum_import_apply', array( __CLASS__, 'apply' ) );
		add_action( 'admin_post_hsum_import_sample', array( __CLASS__, 'sample' ) );
	}

	public static function columns() {
		return array(
			'first'    => array( 'نام', array( 'نام', 'نام دانش آموز', 'نام دانش‌آموز', 'first name', 'first' ) ),
			'last'     => array( 'نام خانوادگی', array( 'نام خانوادگی', 'نام خانوادگی دانش آموز', 'نام خانوادگی دانش‌آموز', 'last name', 'last' ) ),
			'mobile'   => array( 'موبایل والد', array( 'موبایل والد', 'شماره والد', 'تلفن والد', 'موبایل پدر', 'موبایل مادر', 'parent mobile', 'mobile' ) ),
			'relation' => array( 'نسبت والد', array( 'نسبت والد', 'نوع والد', 'نسبت', 'relation' ) ),
			'number'   => array( 'شماره در کلاس', array( 'شماره در کلاس', 'شماره', 'ردیف کلاس', 'number' ) ),
		);
	}

	public static function available() {
		return class_exists( 'HS_Student_Profile' ) && method_exists( 'HS_Student_Profile', 'create_in_class' );
	}

	private static function back( $args ) {
		$url = wp_get_referer() ? wp_get_referer() : HSUM_Front::page_url();
		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'hsum_imp', 'hsum_msg', 'hsum_done' ), $url ) ) );
		exit;
	}

	public static function sample() {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=class-list-sample.csv' );
		$o = fopen( 'php://output', 'w' );
		fwrite( $o, "\xEF\xBB\xBF" );
		fputcsv( $o, array( 'نام', 'نام خانوادگی', 'موبایل والد', 'نسبت والد', 'شماره در کلاس' ) );
		fputcsv( $o, array( 'علی', 'رضایی', "\t09120000001", 'پدر', '1' ) );
		fputcsv( $o, array( 'سارا', 'احمدی', "\t09130000002", 'مادر', '2' ) );
		fclose( $o );
		exit;
	}

	private static function norm_header( $h ) {
		$h = trim( str_replace( array( 'ي', 'ك', "\u{200C}", "\xEF\xBB\xBF" ), array( 'ی', 'ک', ' ', '' ), mb_strtolower( (string) $h ) ) );
		return preg_replace( '/\s+/u', ' ', $h );
	}

	/** @return array{rows:array}|WP_Error */
	public static function read( $path, $name ) {
		if ( ! class_exists( 'HS_Student_Import' ) ) {
			return new WP_Error( 'cm', 'افزونه‌ی مدیریت کلاس فعال نیست.' );
		}
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$rows = 'xlsx' === $ext ? HS_Student_Import::parse_xlsx( $path ) : HS_Student_Import::parse_csv( $path );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$rows = array_values( array_filter( (array) $rows, function ( $r ) {
			return is_array( $r ) && implode( '', array_map( 'trim', array_map( 'strval', $r ) ) ) !== '';
		} ) );
		if ( count( $rows ) < 2 ) {
			return new WP_Error( 'empty', 'فایل خالی است یا سطر عنوان ندارد.' );
		}
		$map = array();
		foreach ( array_values( $rows[0] ) as $i => $h ) {
			$h = self::norm_header( $h );
			foreach ( self::columns() as $k => $c ) {
				if ( ! isset( $map[ $k ] ) && in_array( $h, array_map( array( __CLASS__, 'norm_header' ), $c[1] ), true ) ) {
					$map[ $k ] = $i;
				}
			}
		}
		if ( ! isset( $map['first'] ) || ! isset( $map['last'] ) ) {
			return new WP_Error( 'head', 'ستون‌های «نام» و «نام خانوادگی» در سطر اول پیدا نشد. فایل نمونه را دریافت کنید.' );
		}
		$out = array();
		foreach ( array_slice( $rows, 1, 500 ) as $n => $r ) {
			$r   = array_values( $r );
			$get = function ( $k ) use ( $r, $map ) {
				return isset( $map[ $k ] ) && isset( $r[ $map[ $k ] ] ) ? trim( (string) $r[ $map[ $k ] ] ) : '';
			};
			$rel = $get( 'relation' );
			$out[] = array(
				'line'     => $n + 2,
				'first'    => sanitize_text_field( $get( 'first' ) ),
				'last'     => sanitize_text_field( $get( 'last' ) ),
				'mobile_raw' => $get( 'mobile' ),
				'mobile'   => HSUM_Phone::norm( $get( 'mobile' ) ),
				'relation' => false !== mb_strpos( $rel, 'پدر' ) ? 'father' : ( false !== mb_strpos( $rel, 'مادر' ) ? 'mother' : 'parent' ),
				'number'   => sanitize_text_field( $get( 'number' ) ),
			);
		}
		return array( 'rows' => $out );
	}

	/** بررسی هر سطر نسبت به کلاس: ok | exists | error */
	public static function check( $class_id, $rows ) {
		$seen = array();
		foreach ( $rows as &$r ) {
			$r['state'] = 'ok';
			$r['note']  = '';
			$key = $r['first'] . '|' . $r['last'];
			if ( '' === $r['first'] || '' === $r['last'] ) {
				$r['state'] = 'error';
				$r['note']  = 'نام یا نام خانوادگی خالی است';
			} elseif ( '' !== $r['mobile_raw'] && ! $r['mobile'] ) {
				$r['state'] = 'error';
				$r['note']  = 'شماره موبایل نامعتبر: ' . $r['mobile_raw'];
			} elseif ( isset( $seen[ $key ] ) ) {
				$r['state'] = 'error';
				$r['note']  = 'در همین فایل تکرار شده (سطر ' . $seen[ $key ] . ')';
			} elseif ( self::existing_student( $class_id, $r ) ) {
				$r['state'] = 'exists';
				$r['note']  = 'در کلاس هست؛ فقط شماره والد به‌روز می‌شود';
			}
			$seen[ $key ] = $r['line'];
		}
		return $rows;
	}

	private static function existing_student( $class_id, $r ) {
		if ( ! class_exists( 'HS_Student_Profile' ) ) {
			return 0;
		}
		$want = HS_Student_Profile::norm_name( $r['first'] . ' ' . $r['last'] );
		foreach ( HS_Student_Profile::list_for_class( $class_id ) as $p ) {
			$n = HS_Student_Profile::norm_name( trim( $p['first_name'] . ' ' . $p['last_name'] ) ?: $p['display_name'] );
			if ( $n === $want ) {
				return (int) $p['user_id'];
			}
		}
		return 0;
	}

	public static function upload() {
		check_admin_referer( 'hsum_import' );
		$class_id = absint( $_POST['class_id'] ?? 0 );
		if ( ! self::available() || ! HSUM_Invites::can_manage_class( $class_id ) ) {
			self::back( array( 'hsum_msg' => rawurlencode( 'دسترسی به این کلاس ندارید.' ) ) );
		}
		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
			self::back( array( 'hsum_msg' => rawurlencode( 'فایلی انتخاب نشده است.' ) ) );
		}
		$name = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) );
		if ( ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'csv', 'xlsx' ), true ) || $_FILES['file']['size'] > 2 * MB_IN_BYTES ) {
			self::back( array( 'hsum_msg' => rawurlencode( 'فقط فایل xlsx یا csv تا ۲ مگابایت.' ) ) );
		}
		$res = self::read( $_FILES['file']['tmp_name'], $name ); // فایل فقط خوانده می‌شود و ذخیره نمی‌شود
		if ( is_wp_error( $res ) ) {
			self::back( array( 'hsum_msg' => rawurlencode( $res->get_error_message() ) ) );
		}
		$token = wp_generate_password( 16, false );
		set_transient( 'hsum_imp_' . $token, array(
			'user'  => get_current_user_id(),
			'class' => $class_id,
			'rows'  => self::check( $class_id, $res['rows'] ),
		), HOUR_IN_SECONDS );
		self::back( array( 'hsum_imp' => $token ) );
	}

	public static function pending( $token ) {
		$d = get_transient( 'hsum_imp_' . preg_replace( '/[^A-Za-z0-9]/', '', (string) $token ) );
		return ( is_array( $d ) && (int) $d['user'] === get_current_user_id() ) ? $d : null;
	}

	public static function apply() {
		check_admin_referer( 'hsum_import_apply' );
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$d     = self::pending( $token );
		if ( ! $d || ! HSUM_Invites::can_manage_class( $d['class'] ) ) {
			self::back( array( 'hsum_msg' => rawurlencode( 'زمان پیش‌نمایش تمام شده؛ فایل را دوباره بارگذاری کنید.' ) ) );
		}
		delete_transient( 'hsum_imp_' . $token );
		$class = (int) $d['class'];
		$n     = array( 'new' => 0, 'upd' => 0, 'link' => 0, 'err' => 0 );
		HSUM_Links::$quiet = true;
		foreach ( self::check( $class, $d['rows'] ) as $r ) { // دوباره بررسی (ممکن است کلاس در این فاصله تغییر کرده باشد)
			if ( 'error' === $r['state'] ) {
				$n['err']++;
				continue;
			}
			$sid = self::existing_student( $class, $r );
			if ( ! $sid ) {
				$res = HS_Student_Profile::create_in_class( $class, array(
					'first_name'   => $r['first'],
					'last_name'    => $r['last'],
					'parent_phone' => $r['mobile'],
					'class_number' => $r['number'],
				) );
				if ( is_wp_error( $res ) ) {
					$n['err']++;
					continue;
				}
				$sid = (int) $res['user_id'];
				$n['new']++;
			} else {
				if ( $r['mobile'] && ! HSUM_Phone::norm( get_user_meta( $sid, HSUM_Links::PARENT_PHONE_KEY, true ) ) ) {
					update_user_meta( $sid, HSUM_Links::PARENT_PHONE_KEY, $r['mobile'] );
				}
				$n['upd']++;
			}
			if ( $r['mobile'] ) {
				$id = HSUM_Links::propose( $sid, $r['mobile'], $r['relation'], $class, 'import' );
				if ( ! is_wp_error( $id ) ) {
					$n['link']++;
				}
			}
		}
		HSUM_Links::$quiet = false;
		HSUM_Audit::log( 'import', $class, $n );
		self::back( array( 'hsum_done' => rawurlencode( sprintf( 'دانش‌آموز جدید: %d، موجود: %d، شماره والد ثبت‌شده: %d، سطر رد شده: %d', $n['new'], $n['upd'], $n['link'], $n['err'] ) ) ) );
	}
}
