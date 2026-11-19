<?php
/**
 * مدیریت دانش‌آموزان کلاس
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Class_Students {

	/**
	 * دریافت لیست دانش‌آموزان یک کلاس
	 */
	public static function get_students( $class_id ) {
		$ids = get_post_meta( $class_id, '_hs_students', true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$users = array();
		foreach ( $ids as $uid ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				$users[] = $user;
			}
		}
		return self::sort( $class_id, $users );
	}

	/** آیا معلم پذیرش عضو جدید با کد یا QR کلاس را بسته است؟ */
	public static function join_closed( $class_id ) {
		return (bool) get_post_meta( (int) $class_id, '_hs_join_closed', true );
	}

	public static function set_join_closed( $class_id, $closed ) {
		if ( $closed ) {
			update_post_meta( (int) $class_id, '_hs_join_closed', 1 );
		} else {
			delete_post_meta( (int) $class_id, '_hs_join_closed' );
		}
	}

	public static function join_closed_message() {
		return 'این کلاس فعلاً عضو جدید نمی‌پذیرد. اگر فرزندتان در این کلاس است، با معلم تماس بگیرید.';
	}

	/** ترتیب نمایش هر کلاس: first (نام) | last (نام خانوادگی) | custom (دلخواه معلم) | added (ترتیب افزودن) */
	public static function sort_mode( $class_id ) {
		$m = (string) get_post_meta( $class_id, '_hs_sort', true );
		return in_array( $m, array( 'first', 'last', 'custom', 'added' ), true ) ? $m : 'first';
	}

	/** نام و نام خانوادگی از پرونده‌ی دانش‌آموز؛ اگر نبود، از نام نمایشی */
	public static function name_parts( $user ) {
		$p = class_exists( 'HS_Student_Profile' ) ? (array) HS_Student_Profile::get( $user->ID ) : array();
		$f = trim( (string) ( $p['first_name'] ?? '' ) );
		$l = trim( (string) ( $p['last_name'] ?? '' ) );
		// اگر فقط نام کامل ثبت شده (مثلاً «سارا محمدی» در فیلد نام)، کلمه‌ی اول نام و بقیه نام خانوادگی است
		if ( '' === $l ) {
			$full  = '' !== $f ? $f : trim( $user->display_name );
			$parts = preg_split( '/\s+/u', $full, 2 );
			$f     = $parts[0] ?? '';
			$l     = $parts[1] ?? '';
		}
		return array( $f, $l );
	}

	public static function sort( $class_id, $users ) {
		$mode = self::sort_mode( $class_id );
		if ( 'added' === $mode || count( $users ) < 2 ) {
			return $users;
		}
		if ( 'custom' === $mode ) {
			$order = array_flip( array_map( 'intval', (array) get_post_meta( $class_id, '_hs_order', true ) ) );
			usort( $users, function ( $a, $b ) use ( $order ) {
				$x = $order[ $a->ID ] ?? PHP_INT_MAX;
				$y = $order[ $b->ID ] ?? PHP_INT_MAX;
				return $x === $y ? $a->ID - $b->ID : $x - $y;
			} );
			return $users;
		}
		$coll = class_exists( 'Collator' ) ? new Collator( 'fa_IR' ) : null;
		$cmp  = function ( $x, $y ) use ( $coll ) {
			return $coll ? $coll->compare( $x, $y ) : strcmp( $x, $y );
		};
		$keys = array();
		foreach ( $users as $u ) {
			$keys[ $u->ID ] = self::name_parts( $u );
		}
		usort( $users, function ( $a, $b ) use ( $keys, $mode, $cmp ) {
			list( $af, $al ) = $keys[ $a->ID ];
			list( $bf, $bl ) = $keys[ $b->ID ];
			$r = 'last' === $mode ? ( $cmp( $al, $bl ) ?: $cmp( $af, $bf ) ) : ( $cmp( $af, $bf ) ?: $cmp( $al, $bl ) );
			return $r ?: $a->ID - $b->ID;
		} );
		return $users;
	}

	/**
	 * افزودن دانش‌آموز به کلاس
	 */
	public static function add_student( $class_id, $user_id ) {
		$class_id = absint( $class_id );
		$user_id  = absint( $user_id );

		if ( ! $class_id || ! $user_id ) {
			return new WP_Error( 'invalid', 'شناسه نامعتبر است.' );
		}

		$students = get_post_meta( $class_id, '_hs_students', true );
		if ( ! is_array( $students ) ) {
			$students = array();
		}

		if ( in_array( $user_id, $students, true ) ) {
			return new WP_Error( 'exists', 'این دانش‌آموز قبلاً در کلاس است.' );
		}

		$students[] = $user_id;
		update_post_meta( $class_id, '_hs_students', array_values( array_unique( $students ) ) );

		// ذخیره کلاس در متای کاربر (برای دسترسی سریع)
		$user_classes = get_user_meta( $user_id, '_hs_classes', true );
		if ( ! is_array( $user_classes ) ) {
			$user_classes = array();
		}
		$user_classes[] = $class_id;
		update_user_meta( $user_id, '_hs_classes', array_values( array_unique( $user_classes ) ) );

		return true;
	}

	/**
	 * حذف دانش‌آموز از کلاس
	 */
	public static function remove_student( $class_id, $user_id ) {
		$class_id = absint( $class_id );
		$user_id  = absint( $user_id );

		$students = get_post_meta( $class_id, '_hs_students', true );
		if ( ! is_array( $students ) ) {
			return true;
		}

		$students = array_diff( $students, array( $user_id ) );
		update_post_meta( $class_id, '_hs_students', array_values( $students ) );

		$user_classes = get_user_meta( $user_id, '_hs_classes', true );
		if ( is_array( $user_classes ) ) {
			$user_classes = array_diff( $user_classes, array( $class_id ) );
			update_user_meta( $user_id, '_hs_classes', array_values( $user_classes ) );
		}

		return true;
	}

	/**
	 * ایجاد کاربر دانش‌آموز جدید و افزودن به کلاس
	 */
	public static function create_and_add_student( $class_id, $display_name, $username = '', $email = '' ) {
		$class_id     = absint( $class_id );
		$display_name = sanitize_text_field( $display_name );

		if ( empty( $display_name ) ) {
			return new WP_Error( 'empty_name', 'نام دانش‌آموز الزامی است.' );
		}

		// تولید نام کاربری اگر خالی باشد
		if ( empty( $username ) ) {
			$base = sanitize_user( remove_accents( $display_name ), true );
			if ( empty( $base ) ) {
				$base = 'student';
			}
			$username = $base;
			$i = 1;
			while ( username_exists( $username ) ) {
				$username = $base . $i;
				$i++;
			}
		} else {
			$username = sanitize_user( $username, true );
			if ( username_exists( $username ) ) {
				return new WP_Error( 'username_exists', 'این نام کاربری قبلاً استفاده شده است.' );
			}
		}

		if ( empty( $email ) ) {
			$email = $username . '@hamshagerdi.local'; // ایمیل موقت
		} else {
			$email = sanitize_email( $email );
			if ( email_exists( $email ) ) {
				return new WP_Error( 'email_exists', 'این ایمیل قبلاً ثبت شده است.' );
			}
		}

		$password = wp_generate_password( 12, true );

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_pass'    => $password,
			'user_email'   => $email,
			'display_name' => $display_name,
			'role'         => 'student',
			'first_name'   => $display_name,
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, '_hs_temp_pass', 1 ); // رمز موقت؛ تا تغییر، هشدار نمایش داده می‌شود

		$result = self::add_student( $class_id, $user_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'user_id'  => $user_id,
			'username' => $username,
			'password' => $password, // فقط یک‌بار نمایش داده شود
		);
	}

	/**
	 * پیوستن با کد دعوت
	 */
	public static function is_member( $class_id, $user_id ) {
		$students = get_post_meta( absint( $class_id ), '_hs_students', true );
		return is_array( $students ) && in_array( absint( $user_id ), array_map( 'absint', $students ), true );
	}

	public static function join_by_invite_code( $code, $user_id = null ) {
		$code = strtoupper( sanitize_text_field( $code ) );
		if ( empty( $code ) ) {
			return new WP_Error( 'empty_code', 'کد دعوت را وارد کنید.' );
		}

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', 'برای پیوستن باید وارد شوید.' );
		}

		// فقط حساب دانش‌آموز (نه معلم/مدیر/والد) می‌تواند عضو کلاس شود
		$u     = get_userdata( $user_id );
		$roles = $u ? (array) $u->roles : array();
		if ( array_intersect( $roles, array( 'teacher', 'school_admin', 'administrator', 'parent' ) ) ) {
			return new WP_Error( 'role_denied', 'فقط حساب دانش‌آموز می‌تواند با کد دعوت به کلاس بپیوندد.' );
		}

		// محدودیت تلاش ناموفق: ۸ بار در ۱۵ دقیقه
		$rl = 'join_' . $user_id;
		if ( HS_Util::rl_blocked( $rl, 8 ) ) {
			return new WP_Error( 'rate_limited', 'تلاش‌های ناموفق زیاد بود. چند دقیقه بعد دوباره امتحان کنید.' );
		}

		$q = new WP_Query( array(
			'post_type'      => 'hs_class',
			'meta_key'       => '_hs_invite_code',
			'meta_value'     => $code,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		) );

		if ( ! $q->have_posts() ) {
			HS_Util::rl_hit( $rl, 15 * MINUTE_IN_SECONDS );
			return new WP_Error( 'invalid_code', 'کد دعوت نامعتبر است.' );
		}
		HS_Util::rl_reset( $rl );

		$class_id = $q->posts[0]->ID;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', 'برای پیوستن باید وارد شوید.' );
		}

		return self::add_student( $class_id, $user_id );
	}

	/**
	 * تعداد دانش‌آموزان
	 */
	public static function count( $class_id ) {
		$students = get_post_meta( $class_id, '_hs_students', true );
		return is_array( $students ) ? count( $students ) : 0;
	}
}
