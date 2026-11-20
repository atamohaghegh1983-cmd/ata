<?php
/**
 * پروفایل کامل دانش‌آموز (متای کاربر)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Student_Profile {

	/** کلیدهای متا */
	const META = array(
		'first_name'     => '_hs_first_name',
		'last_name'      => '_hs_last_name',
		'parent_name'    => '_hs_parent_name',
		'parent_phone'   => '_hs_parent_phone',
		'parent2_name'   => '_hs_parent2_name',
		'parent2_phone'  => '_hs_parent2_phone',
		'class_number'   => '_hs_class_number',
		'birth_date'     => '_hs_birth_date',
		'photo_url'      => '_hs_photo_url',
		'status'         => '_hs_reg_status',
		'notes'          => '_hs_student_notes',
	);

	public static function statuses() {
		return array(
			'active'    => 'فعال',
			'pending'   => 'در انتظار',
			'inactive'  => 'غیرفعال',
			'transferred' => 'منتقل‌شده',
		);
	}

	/**
	 * خواندن پروفایل کامل
	 */
	public static function get( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		$first = get_user_meta( $user_id, self::META['first_name'], true );
		$last  = get_user_meta( $user_id, self::META['last_name'], true );

		if ( ! $first && ! $last ) {
			// fallback از display_name
			$parts = preg_split( '/\s+/', trim( $user->display_name ), 2 );
			$first = $parts[0] ?? '';
			$last  = $parts[1] ?? '';
		}

		return array(
			'user_id'      => (int) $user_id,
			'display_name' => $user->display_name,
			'username'     => $user->user_login,
			'first_name'   => $first,
			'last_name'    => $last,
			'parent_name'  => get_user_meta( $user_id, self::META['parent_name'], true ),
			'parent_phone' => get_user_meta( $user_id, self::META['parent_phone'], true ),
			'parent2_name' => get_user_meta( $user_id, self::META['parent2_name'], true ),
			'parent2_phone' => get_user_meta( $user_id, self::META['parent2_phone'], true ),
			'class_number' => get_user_meta( $user_id, self::META['class_number'], true ),
			'birth_date'   => get_user_meta( $user_id, self::META['birth_date'], true ),
			'photo_url'    => get_user_meta( $user_id, self::META['photo_url'], true ),
			'status'       => get_user_meta( $user_id, self::META['status'], true ) ?: 'active',
			'notes'        => get_user_meta( $user_id, self::META['notes'], true ),
		);
	}

	/**
	 * ذخیره / به‌روزرسانی پروفایل
	 */
	public static function save( $user_id, $data ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return false;
		}

		$map = array(
			'first_name'   => 'sanitize_text_field',
			'last_name'    => 'sanitize_text_field',
			'parent_name'  => 'sanitize_text_field',
			'parent_phone' => 'sanitize_text_field',
			'parent2_name' => 'sanitize_text_field',
			'parent2_phone' => 'sanitize_text_field',
			'class_number' => 'sanitize_text_field',
			'birth_date'   => 'sanitize_text_field',
			'photo_url'    => 'esc_url_raw',
			'status'       => 'sanitize_key',
			'notes'        => 'sanitize_textarea_field',
		);

		foreach ( $map as $key => $sanitizer ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$val = call_user_func( $sanitizer, $data[ $key ] );
			if ( $key === 'status' ) {
				$allowed = array_keys( self::statuses() );
				if ( ! in_array( $val, $allowed, true ) ) {
					$val = 'active';
				}
			}
			update_user_meta( $user_id, self::META[ $key ], $val );
		}

		// به‌روزرسانی display_name
		$first = get_user_meta( $user_id, self::META['first_name'], true );
		$last  = get_user_meta( $user_id, self::META['last_name'], true );
		$full  = trim( $first . ' ' . $last );
		if ( $full ) {
			wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => $full,
				'first_name'   => $first,
				'last_name'    => $last,
			) );
		}

		return true;
	}

	/**
	 * ساخت کاربر + پروفایل + افزودن به کلاس
	 */
	public static function create_in_class( $class_id, $data ) {
		$class_id = absint( $class_id );
		$first = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
		$last  = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
		$full  = trim( $first . ' ' . $last );

		if ( empty( $full ) && ! empty( $data['name'] ) ) {
			$full = sanitize_text_field( $data['name'] );
			$parts = preg_split( '/\s+/', $full, 2 );
			$first = $parts[0] ?? $full;
			$last  = $parts[1] ?? '';
		}

		if ( empty( $full ) ) {
			return new WP_Error( 'empty_name', 'نام دانش‌آموز الزامی است.' );
		}

		// قفل کلاس: دو درخواست هم‌زمان (دوبار کلیک، اینترنت کند) نباید هر دو از بررسی تکراری رد شوند
		if ( ! self::lock_class( $class_id ) ) {
			return new WP_Error( 'busy', 'ثبت قبلی هنوز در حال انجام است. چند لحظه بعد دوباره امتحان کنید.' );
		}
		try {
			$dup = self::find_duplicate( $class_id, $first, $last, isset( $data['parent_phone'] ) ? $data['parent_phone'] : '', isset( $data['class_number'] ) ? $data['class_number'] : '' );
			if ( is_wp_error( $dup ) ) {
				return $dup;
			}

			$result = HS_Class_Students::create_and_add_student( $class_id, $full );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

		$user_id = $result['user_id'];
		$profile_data = array(
			'first_name'   => $first,
			'last_name'    => $last,
			'parent_name'  => isset( $data['parent_name'] ) ? $data['parent_name'] : '',
			'parent_phone' => isset( $data['parent_phone'] ) ? $data['parent_phone'] : '',
			'class_number' => isset( $data['class_number'] ) ? $data['class_number'] : '',
			'birth_date'   => isset( $data['birth_date'] ) ? $data['birth_date'] : '',
			'photo_url'    => isset( $data['photo_url'] ) ? $data['photo_url'] : '',
			'status'       => isset( $data['status'] ) ? $data['status'] : 'active',
			'notes'        => isset( $data['notes'] ) ? $data['notes'] : '',
		);
			self::save( $user_id, $profile_data );
		} finally {
			self::unlock_class( $class_id );
		}

		$result['profile'] = self::get( $user_id );
		return $result;
	}

	/* ---------- جلوگیری از ثبت تکراری ---------- */

	/** یکسان‌سازی نام: ی/ک عربی، نیم‌فاصله، فاصله‌های اضافه، حروف کوچک */
	public static function norm_name( $s ) {
		$s = str_replace( array( 'ي', 'ى', 'ك', "\u{200C}", "\u{200F}", "\u{200E}" ), array( 'ی', 'ی', 'ک', ' ', '', '' ), (string) $s );
		$s = preg_replace( '/\s+/u', ' ', trim( $s ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
	}

	/** یکسان‌سازی عدد (تلفن/شماره کلاس): ارقام فارسی/عربی → لاتین، فقط رقم */
	public static function norm_digits( $s ) {
		$s = strtr( (string) $s, array(
			'۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
			'٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
		) );
		return preg_replace( '/\D+/', '', $s );
	}

	public static function norm_phone( $s ) {
		$d = self::norm_digits( $s );
		if ( 0 === strpos( $d, '0098' ) ) {
			$d = '0' . substr( $d, 4 );
		} elseif ( 0 === strpos( $d, '98' ) && strlen( $d ) === 12 ) {
			$d = '0' . substr( $d, 2 );
		} elseif ( strlen( $d ) === 10 && '9' === $d[0] ) {
			$d = '0' . $d;
		}
		return $d;
	}

	public static function norm_number( $s ) {
		$d = self::norm_digits( $s );
		return '' === $d ? '' : (string) (int) $d; // «03» و «3» یکی است
	}

	/**
	 * بررسی تکراری در یک کلاس. خروجی: false یا WP_Error.
	 * - نام + نام خانوادگی + شماره والد یکسان (اگر هر دو بی‌شماره باشند، نام یکسان کافی است)
	 * - شماره در کلاس یکسان
	 * $exclude_user برای ویرایش پرونده (خودش را تکراری حساب نکند).
	 */
	public static function find_duplicate( $class_id, $first, $last, $phone, $number, $exclude_user = 0 ) {
		$name  = self::norm_name( trim( $first . ' ' . $last ) );
		$phone = self::norm_phone( $phone );
		$num   = self::norm_number( $number );

		foreach ( self::list_for_class( $class_id ) as $p ) {
			if ( $exclude_user && (int) $p['user_id'] === (int) $exclude_user ) {
				continue;
			}
			$pname  = self::norm_name( trim( $p['first_name'] . ' ' . $p['last_name'] ) );
			if ( '' === trim( $pname ) ) {
				$pname = self::norm_name( $p['display_name'] );
			}
			$pphone = self::norm_phone( $p['parent_phone'] );
			if ( '' !== $name && $name === $pname && $phone === $pphone ) {
				return new WP_Error( 'duplicate', 'این دانش‌آموز قبلاً ثبت شده است.' );
			}
			if ( '' !== $num && $num === self::norm_number( $p['class_number'] ) ) {
				return new WP_Error( 'duplicate_number', sprintf( 'شماره %s در این کلاس قبلاً به «%s» داده شده است.', $num, $p['display_name'] ) );
			}
		}
		return false;
	}

	/** قفل اتمی با add_option (درج تکراری در دیتابیس شکست می‌خورد) */
	private static function lock_class( $class_id ) {
		$key = 'hs_addst_lock_' . absint( $class_id );
		for ( $i = 0; $i < 20; $i++ ) {
			if ( add_option( $key, time(), '', 'no' ) ) {
				return true;
			}
			$t = (int) get_option( $key );
			if ( $t && time() - $t > 30 ) { // قفل جامانده از خطای قبلی
				delete_option( $key );
				continue;
			}
			usleep( 250000 ); // تا ۵ ثانیه صبر؛ درخواست دوم بعد از اولی بررسی می‌شود و تکراری تشخیص داده می‌شود
		}
		return false;
	}

	private static function unlock_class( $class_id ) {
		delete_option( 'hs_addst_lock_' . absint( $class_id ) );
	}

	/**
	 * انتقال به کلاس دیگر
	 */
	public static function transfer( $user_id, $from_class, $to_class ) {
		$user_id    = absint( $user_id );
		$from_class = absint( $from_class );
		$to_class   = absint( $to_class );

		if ( ! $user_id || ! $from_class || ! $to_class ) {
			return new WP_Error( 'invalid', 'شناسه‌ها نامعتبر است.' );
		}
		if ( $from_class === $to_class ) {
			return new WP_Error( 'same', 'کلاس مبدأ و مقصد یکسان است.' );
		}

		$remove = HS_Class_Students::remove_student( $from_class, $user_id );
		if ( is_wp_error( $remove ) ) {
			return $remove;
		}

		$add = HS_Class_Students::add_student( $to_class, $user_id );
		if ( is_wp_error( $add ) ) {
			// تلاش برای بازگرداندن
			HS_Class_Students::add_student( $from_class, $user_id );
			return $add;
		}

		return true;
	}

	/**
	 * داده‌های جدول برای یک کلاس (با پروفایل)
	 */
	public static function list_for_class( $class_id ) {
		$users = HS_Class_Students::get_students( $class_id );
		$rows  = array();
		foreach ( $users as $user ) {
			$p = self::get( $user->ID );
			if ( $p ) {
				$rows[] = $p;
			}
		}
		// مرتب‌سازی بر اساس شماره کلاس سپس نام
		usort( $rows, function( $a, $b ) {
			$na = $a['class_number'] !== '' ? (int) $a['class_number'] : 9999;
			$nb = $b['class_number'] !== '' ? (int) $b['class_number'] : 9999;
			if ( $na !== $nb ) {
				return $na - $nb;
			}
			return strcmp( $a['display_name'], $b['display_name'] );
		} );
		return $rows;
	}
}
