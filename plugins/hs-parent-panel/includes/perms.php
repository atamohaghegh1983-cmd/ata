<?php
/**
 * مجوزهای قابل تنظیم والدین
 *
 * اولویت (جزئی‌تر برنده است):
 *   والدِ خاص برای فرزندِ خاص (link) ← دانش‌آموز (student) ← کلاس (class) ← مدرسه (school) ← کل سایت (site) ← پیش‌فرض
 * همه در سرور بررسی می‌شود؛ پنهان کردن گزینه فقط برای راحتی است.
 * دسترسی فقط با ارتباط تأییدشده‌ی والد ↔ فرزند (HS_Parent_Link::can_view).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Perms {

	public static function features() {
		return array(
			'attendance'  => array( 'حضور و غیاب', 1 ),
			'homework'    => array( 'تکالیف', 1 ),
			'assessment'  => array( 'ارزیابی، نمره و پیشرفت', 1 ),
			'points'      => array( 'امتیاز و رفتار', 1 ),
			'announce'    => array( 'اطلاعیه‌ها', 1 ),
			'messages'    => array( 'پیام به معلم', 1 ),
			'group'       => array( 'گروه والدین کلاس', 1 ),
			'records'     => array( 'پرونده‌ی آموزشی و کتاب‌ها', 1 ),
			'meetings'    => array( 'درخواست جلسه با معلم', 1 ),
			'calendar'    => array( 'تقویم', 1 ),
			'practice'    => array( 'تمرین در خانه', 1 ),
			'digest'      => array( 'خلاصه‌ی روزانه', 1 ),
		);
	}

	public static function scopes() {
		return array( 'site' => 'کل سایت', 'school' => 'مدرسه', 'class' => 'کلاس', 'student' => 'دانش‌آموز', 'link' => 'یک والد برای یک فرزند' );
	}

	/** مدرسه‌ی یک کلاس (از روی معلم کلاس) */
	public static function class_school( $class_id ) {
		$teacher = (int) get_post_meta( $class_id, '_hs_teacher_id', true );
		if ( ! $teacher ) {
			return 0;
		}
		if ( class_exists( 'HSUM_Invites' ) ) {
			$s = HSUM_Invites::teacher_schools( $teacher );
			if ( $s ) {
				return (int) $s[0];
			}
		}
		if ( class_exists( 'HS_SO_Store' ) ) {
			$m = HS_SO_Store::manager_school_id( $teacher );
			if ( $m ) {
				return $m;
			}
			$row = HS_SO_Store::staff_row_by_user( $teacher );
			return $row ? (int) $row['school_id'] : 0;
		}
		return 0;
	}

	public static function student_classes( $student_id ) {
		$c = get_user_meta( $student_id, '_hs_classes', true );
		return is_array( $c ) ? array_values( array_filter( array_map( 'intval', $c ) ) ) : array();
	}

	/** حافظه‌ی همین درخواست؛ با هر تغییر پاک می‌شود */
	private static $all = null;

	private static function rules() {
		global $wpdb;
		if ( null === self::$all ) {
			$all  = array();
			$rows = $wpdb->get_results( 'SELECT scope, scope_id, parent_id, feature, allow FROM ' . HSPP_DB::t( 'perms' ), ARRAY_A ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				$all[ $r['scope'] . ':' . $r['scope_id'] . ':' . $r['parent_id'] . ':' . $r['feature'] ] = (int) $r['allow'];
			}
			self::$all = $all;
		}
		return self::$all;
	}

	/** مقدار یک قانون یا null */
	public static function rule( $scope, $scope_id, $feature, $parent_id = 0 ) {
		$k = $scope . ':' . (int) $scope_id . ':' . (int) $parent_id . ':' . $feature;
		$r = self::rules();
		return isset( $r[ $k ] ) ? $r[ $k ] : null;
	}

	/**
	 * والد اجازه‌ی دیدن این بخشِ این فرزند را دارد؟
	 * $class_id اختیاری: برای اطلاعات یک کلاس خاص (گروه، اطلاعیه)
	 */
	public static function can( $parent_id, $student_id, $feature, $class_id = 0 ) {
		if ( user_can( $parent_id, 'manage_options' ) ) {
			return true;
		}
		if ( ! HS_Parent_Link::can_view( $parent_id, $student_id ) ) {
			return false;
		}
		$v = self::rule( 'link', $student_id, $feature, $parent_id );
		if ( null === $v ) {
			$v = self::rule( 'student', $student_id, $feature );
		}
		$classes = $class_id ? array( (int) $class_id ) : self::student_classes( $student_id );
		if ( null === $v ) {
			foreach ( $classes as $c ) { // اگر در یکی از کلاس‌ها بسته باشد، بسته است
				$cv = self::rule( 'class', $c, $feature );
				if ( null !== $cv ) {
					$v = $v === null ? $cv : min( $v, $cv );
				}
			}
		}
		if ( null === $v ) {
			foreach ( $classes as $c ) {
				$s = self::class_school( $c );
				$sv = $s ? self::rule( 'school', $s, $feature ) : null;
				if ( null !== $sv ) {
					$v = $v === null ? $sv : min( $v, $sv );
				}
			}
		}
		if ( null === $v ) {
			$v = self::rule( 'site', 0, $feature );
		}
		if ( null === $v ) {
			$f = self::features();
			$v = isset( $f[ $feature ] ) ? $f[ $feature ][1] : 0;
		}
		return (bool) apply_filters( 'hspp_can', (bool) $v, $parent_id, $student_id, $feature, $class_id );
	}

	/** کاربر اجازه‌ی تنظیم مجوز در این دامنه را دارد؟ */
	public static function can_manage( $scope, $scope_id, $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		if ( user_can( $uid, 'manage_options' ) ) {
			return true;
		}
		$school = class_exists( 'HS_SO_Store' ) ? (int) HS_SO_Store::manager_school_id( $uid ) : 0;
		if ( ! $school ) {
			return false;
		}
		switch ( $scope ) {
			case 'school':
				return (int) $scope_id === $school;
			case 'class':
				return self::class_school( $scope_id ) === $school;
			case 'student':
			case 'link':
				foreach ( self::student_classes( $scope_id ) as $c ) {
					if ( self::class_school( $c ) === $school ) {
						return true;
					}
				}
				return false;
		}
		return false;
	}

	/** $allow: 1 | 0 | null (حذف قانون = ارث از سطح بالاتر) */
	public static function set( $scope, $scope_id, $feature, $allow, $parent_id = 0 ) {
		global $wpdb;
		if ( ! isset( self::features()[ $feature ] ) || ! isset( self::scopes()[ $scope ] ) ) {
			return new WP_Error( 'bad', 'نامعتبر.' );
		}
		if ( ! self::can_manage( $scope, $scope_id ) ) {
			return new WP_Error( 'cap', 'اجازه‌ی تغییر این مجوز را ندارید.' );
		}
		$t   = HSPP_DB::t( 'perms' );
		$old = self::rule( $scope, $scope_id, $feature, $parent_id );
		$wpdb->delete( $t, array( 'scope' => $scope, 'scope_id' => (int) $scope_id, 'parent_id' => (int) $parent_id, 'feature' => $feature ) );
		if ( null !== $allow ) {
			$wpdb->insert( $t, array(
				'scope'      => $scope,
				'scope_id'   => (int) $scope_id,
				'parent_id'  => (int) $parent_id,
				'feature'    => $feature,
				'allow'      => $allow ? 1 : 0,
				'set_by'     => get_current_user_id(),
				'updated_at' => HSPP_DB::now(),
			) );
		}
		self::$all = null;
		HSPP_DB::log( 'perm', (int) $scope_id, array( 'scope' => $scope, 'parent' => (int) $parent_id, 'feature' => $feature, 'from' => $old, 'to' => $allow ) );
		return true;
	}
}
