<?php
/**
 * اتصال والد ↔ دانش‌آموز
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Parent_Link {

	const META_CHILDREN = '_hs_children';
	const META_PARENTS  = '_hs_parents';

	/**
	 * فرزندان یک والد
	 * @return int[] user ids
	 */
	public static function get_children( $parent_id ) {
		$ids = get_user_meta( absint( $parent_id ), self::META_CHILDREN, true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * والدین یک دانش‌آموز
	 */
	public static function get_parents( $student_id ) {
		$ids = get_user_meta( absint( $student_id ), self::META_PARENTS, true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * آیا این والد به این دانش‌آموز دسترسی دارد؟
	 */
	public static function can_view( $parent_id, $student_id ) {
		$parent_id  = absint( $parent_id );
		$student_id = absint( $student_id );
		if ( ! $parent_id || ! $student_id ) {
			return false;
		}
		// دسترسی خودِ $parent_id بررسی می‌شود، نه کاربر فعلی (رفع باگ ۱.۷.۳)
		if ( user_can( $parent_id, 'manage_options' ) || user_can( $parent_id, 'hs_manage_all_classes' ) ) {
			return true;
		}
		// معلم کلاس دانش‌آموز
		$classes = get_user_meta( $student_id, '_hs_classes', true );
		if ( is_array( $classes ) ) {
			foreach ( $classes as $cid ) {
				if ( HS_Roles::can_manage_class( $cid, $parent_id ) ) {
					return true;
				}
			}
		}
		return in_array( $student_id, self::get_children( $parent_id ), true );
	}

	/**
	 * لینک دو طرفه
	 */
	public static function link( $parent_id, $student_id ) {
		$parent_id  = absint( $parent_id );
		$student_id = absint( $student_id );
		if ( ! $parent_id || ! $student_id || $parent_id === $student_id ) {
			return new WP_Error( 'invalid', 'شناسه نامعتبر است.' );
		}
		if ( ! get_userdata( $parent_id ) || ! get_userdata( $student_id ) ) {
			return new WP_Error( 'not_found', 'کاربر یافت نشد.' );
		}

		$children = self::get_children( $parent_id );
		if ( ! in_array( $student_id, $children, true ) ) {
			$children[] = $student_id;
			update_user_meta( $parent_id, self::META_CHILDREN, $children );
		}

		$parents = self::get_parents( $student_id );
		if ( ! in_array( $parent_id, $parents, true ) ) {
			$parents[] = $parent_id;
			update_user_meta( $student_id, self::META_PARENTS, $parents );
		}

		// نقش parent اگر نداشت
		$user = new WP_User( $parent_id );
		if ( ! in_array( 'parent', (array) $user->roles, true ) && ! user_can( $parent_id, 'manage_options' ) ) {
			$user->add_role( 'parent' );
		}

		return true;
	}

	public static function unlink( $parent_id, $student_id ) {
		$parent_id  = absint( $parent_id );
		$student_id = absint( $student_id );

		$children = array_diff( self::get_children( $parent_id ), array( $student_id ) );
		update_user_meta( $parent_id, self::META_CHILDREN, array_values( $children ) );

		$parents = array_diff( self::get_parents( $student_id ), array( $parent_id ) );
		update_user_meta( $student_id, self::META_PARENTS, array_values( $parents ) );

		return true;
	}

	/* ---------- کد اتصال والد (ساخته‌شده توسط معلم) ---------- */

	public static function get_code( $student_id ) {
		$student_id = absint( $student_id );
		$code = get_user_meta( $student_id, '_hs_parent_code', true );
		if ( ! $code ) {
			$code = self::regen_code( $student_id );
		}
		return $code;
	}

	public static function regen_code( $student_id ) {
		$code = HS_Util::random_code( 8 );
		update_user_meta( absint( $student_id ), '_hs_parent_code', $code );
		return $code;
	}

	/**
	 * اتصال والد به فرزند: نام کاربری دانش‌آموز + کد والد (که معلم می‌دهد)
	 */
	public static function link_by_student_login( $parent_id, $student_login, $code = '' ) {
		$parent_id = absint( $parent_id );
		$rl_user   = 'plink_u_' . $parent_id;
		$rl_ip     = 'plink_ip_' . HS_Util::client_ip();
		if ( HS_Util::rl_blocked( $rl_user, 5 ) || HS_Util::rl_blocked( $rl_ip, 20 ) ) {
			return new WP_Error( 'rate_limited', 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه بعد دوباره امتحان کنید.' );
		}

		$fail = new WP_Error( 'bad_credentials', 'نام کاربری یا کد والد نادرست است. کد را از معلم فرزندتان بگیرید.' );

		$student_login = sanitize_user( $student_login );
		$code          = strtoupper( trim( sanitize_text_field( $code ) ) );
		$user          = $student_login ? get_user_by( 'login', $student_login ) : false;

		$ok = false;
		if ( $user && $code && in_array( 'student', (array) $user->roles, true ) ) {
			$stored = (string) get_user_meta( $user->ID, '_hs_parent_code', true );
			$ok     = $stored && hash_equals( $stored, $code );
		}

		if ( ! $ok ) {
			HS_Util::rl_hit( $rl_user, 15 * MINUTE_IN_SECONDS );
			HS_Util::rl_hit( $rl_ip, 15 * MINUTE_IN_SECONDS );
			return $fail;
		}

		HS_Util::rl_reset( $rl_user );
		return self::link( $parent_id, $user->ID );
	}

	/**
	 * کلاس‌های یک دانش‌آموز
	 */
	public static function student_classes( $student_id ) {
		$ids = get_user_meta( absint( $student_id ), '_hs_classes', true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $cid ) {
			$post = get_post( $cid );
			if ( $post && $post->post_status === 'publish' ) {
				$tid = get_post_meta( $cid, '_hs_teacher_id', true );
				$teacher = $tid ? get_userdata( $tid ) : null;
				$out[] = array(
					'id'      => (int) $cid,
					'title'   => $post->post_title,
					'grade'   => get_post_meta( $cid, '_hs_grade', true ),
					'teacher' => $teacher ? $teacher->display_name : '',
					'teacher_id' => $tid ? (int) $tid : 0,
				);
			}
		}
		return $out;
	}
}
