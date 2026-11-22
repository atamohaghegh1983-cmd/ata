<?php
/**
 * مجوزهای دانش‌آموز
 * - معلم برای کلاس خودش و هر دانش‌آموزش تنظیم می‌کند (دانش‌آموز ← کلاس).
 * - مدرسه و مدیر کل سقف می‌گذارند: اگر مدرسه (یا کل سایت) بخشی را ببندد، معلم نمی‌تواند بازش کند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_Perms {

	public static function features() {
		return array(
			'homework'   => array( 'تکالیف', '📚' ),
			'assessment' => array( 'نمره و ارزیابی در کارنامه', '📝' ),
			'attendance' => array( 'حضور و غیاب در کارنامه', '✅' ),
			'points'     => array( 'امتیاز و جایزه', '⭐' ),
			'games'      => array( 'بازی‌ها', '🎮' ),
			'library'    => array( 'کتابخانه‌ی کلاس', '📖' ),
			'messages'   => array( 'پیام‌ها و اطلاعیه‌ها', '💬' ),
			'calendar'   => array( 'برنامه و تقویم', '🗓️' ),
		);
	}

	public static function scopes() {
		return array( 'site' => 'کل سایت', 'school' => 'مدرسه', 'class' => 'کلاس', 'student' => 'یک دانش‌آموز' );
	}

	private static $all = null;

	private static function rules() {
		global $wpdb;
		if ( null === self::$all ) {
			self::$all = array();
			foreach ( (array) $wpdb->get_results( 'SELECT scope, scope_id, feature, allow FROM ' . HSSP_DB::t( 'perms' ), ARRAY_A ) as $r ) { // phpcs:ignore
				self::$all[ $r['scope'] . ':' . $r['scope_id'] . ':' . $r['feature'] ] = (int) $r['allow'];
			}
		}
		return self::$all;
	}

	public static function rule( $scope, $id, $feature ) {
		$r = self::rules();
		$k = $scope . ':' . (int) $id . ':' . $feature;
		return isset( $r[ $k ] ) ? $r[ $k ] : null;
	}

	public static function class_school( $cid ) {
		if ( class_exists( 'HSPP_Perms' ) ) {
			return HSPP_Perms::class_school( $cid );
		}
		$t = (int) get_post_meta( $cid, '_hs_teacher_id', true );
		if ( ! $t ) {
			return 0;
		}
		if ( class_exists( 'HSUM_Invites' ) ) {
			$s = HSUM_Invites::teacher_schools( $t );
			if ( $s ) {
				return (int) $s[0];
			}
		}
		if ( class_exists( 'HS_SO_Store' ) ) {
			$m = (int) HS_SO_Store::manager_school_id( $t );
			if ( $m ) {
				return $m;
			}
			$row = HS_SO_Store::staff_row_by_user( $t );
			return $row ? (int) $row['school_id'] : 0;
		}
		return 0;
	}

	/** سقف بالادستی (مدرسه، کل سایت) برای یک کلاس: false = بسته شده */
	public static function ceiling( $cid, $feature ) {
		$s = self::class_school( $cid );
		if ( $s && 0 === self::rule( 'school', $s, $feature ) ) {
			return false;
		}
		if ( 0 === self::rule( 'site', 0, $feature ) && ! ( $s && 1 === self::rule( 'school', $s, $feature ) ) ) {
			return false;
		}
		return true;
	}

	/** این دانش‌آموز این بخش را (در این کلاس یا یکی از کلاس‌هایش) می‌بیند؟ */
	public static function can( $sid, $feature, $cid = 0 ) {
		$classes = $cid ? array( (int) $cid ) : HSSP_Util::student_classes( $sid );
		if ( ! $classes ) {
			$v = self::rule( 'site', 0, $feature );
			return (bool) apply_filters( 'hssp_can', null === $v ? true : (bool) $v, $sid, $feature, $cid );
		}
		$ok = false;
		foreach ( $classes as $c ) {
			if ( ! self::ceiling( $c, $feature ) ) {
				continue;
			}
			$v = self::rule( 'student', $sid, $feature );
			if ( null === $v ) {
				$v = self::rule( 'class', $c, $feature );
			}
			if ( null === $v || $v ) {
				$ok = true;
				break;
			}
		}
		return (bool) apply_filters( 'hssp_can', $ok, $sid, $feature, $cid );
	}

	public static function can_manage( $scope, $id, $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		if ( user_can( $uid, 'manage_options' ) ) {
			return true;
		}
		$school = class_exists( 'HS_SO_Store' ) ? (int) HS_SO_Store::manager_school_id( $uid ) : 0;
		switch ( $scope ) {
			case 'school':
				return $school && (int) $id === $school;
			case 'class':
				return HSSP_Util::is_class_teacher( $uid, $id ) || ( $school && self::class_school( $id ) === $school );
			case 'student':
				foreach ( HSSP_Util::student_classes( $id ) as $c ) {
					if ( HSSP_Util::is_class_teacher( $uid, $c ) || ( $school && self::class_school( $c ) === $school ) ) {
						return true;
					}
				}
		}
		return false;
	}

	/** $allow: 1 | 0 | null (= پیروی از سطح بالاتر) */
	public static function set( $scope, $id, $feature, $allow ) {
		global $wpdb;
		if ( ! isset( self::features()[ $feature ], self::scopes()[ $scope ] ) ) {
			return new WP_Error( 'bad', 'نامعتبر.' );
		}
		if ( ! self::can_manage( $scope, $id ) ) {
			return new WP_Error( 'cap', 'اجازه‌ی تغییر این مورد را ندارید.' );
		}
		$t   = HSSP_DB::t( 'perms' );
		$old = self::rule( $scope, $id, $feature );
		$wpdb->delete( $t, array( 'scope' => $scope, 'scope_id' => (int) $id, 'feature' => $feature ) );
		if ( null !== $allow ) {
			$wpdb->insert( $t, array( 'scope' => $scope, 'scope_id' => (int) $id, 'feature' => $feature, 'allow' => $allow ? 1 : 0, 'set_by' => get_current_user_id(), 'updated_at' => HSSP_DB::now() ) );
		}
		self::$all = null;
		HSSP_DB::log( 'perm', $id, array( 'scope' => $scope, 'feature' => $feature, 'from' => $old, 'to' => $allow ) );
		return true;
	}
}
