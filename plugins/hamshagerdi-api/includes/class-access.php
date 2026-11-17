<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * منبع واحد تصمیم‌گیری دسترسی در API.
 *
 * قواعد:
 *  - معلم: فقط کلاس‌هایی که خودش معلم آن است (نوشتن).
 *  - مدیر/ناظر مدرسه: فقط‌خواندنی، فقط کلاس‌های مدرسه‌ی خودش و فقط اگر تأییدشده باشد.
 *  - والد: فقط فرزندان متصل خودش؛ هر بخش (حضور، امتیاز، تکلیف، اطلاعیه، پیام…) با HSPP_Perms::can.
 *  - دانش‌آموز: فقط خودش و کلاس‌های خودش.
 */
class HS_API_Access {

	const WRITE   = 'write';   // معلم کلاس
	const SUPER   = 'super';   // مدیر/ناظر مدرسه (فقط‌خواندنی)
	const STUDENT = 'student';
	const PARENT  = 'parent';

	public static function ready() { return class_exists( 'HS_Roles' ) && class_exists( 'HS_Shell' ); }

	public static function view( $uid ) { return self::ready() ? HS_Shell::view_type( $uid ) : 'other'; }

	public static function needs_onboarding( $uid ) {
		return class_exists( 'HSUM_Onboard' ) ? (bool) HSUM_Onboard::needs( (int) $uid ) : false;
	}

	public static function membership_status( $uid ) {
		return class_exists( 'HSUM_Membership' ) ? HSUM_Membership::status( (int) $uid ) : 'active';
	}

	public static function profile( WP_User $u ) {
		$view = self::view( $u->ID );
		$p = array(
			'id'               => $u->ID,
			'name'             => $u->display_name,
			'first_name'       => (string) $u->first_name,
			'last_name'        => (string) $u->last_name,
			'username'         => $u->user_login,
			'mobile'           => (string) get_user_meta( $u->ID, '_hs_mobile', true ),
			'view'             => $view,   // teacher | manager | parent | student | other
			'roles'            => array_values( (array) $u->roles ),
			'status'           => self::membership_status( $u->ID ), // active | pending | rejected | suspended
			'avatar'           => HS_API_Util::avatar( $u->ID ),
			'can_teach'        => 'teacher' === $view,
			'has_children'     => (bool) self::children_ids( $u->ID ),
			'can_supervise'    => false,
			'needs_onboarding' => self::needs_onboarding( $u->ID ),
		);
		if ( class_exists( 'HS_SO_Store' ) ) {
			$sid = self::school_id( $u->ID );
			if ( $sid ) {
				$p['school']        = array( 'id' => $sid, 'name' => get_the_title( $sid ) );
				$p['can_supervise'] = HS_SO_Store::can_supervise( $sid, $u->ID );
			}
		}
		return $p;
	}

	/** مدرسه‌ی کاربر: مدیرِ تأییدشده یا عضو کادر */
	public static function school_id( $uid ) {
		if ( ! class_exists( 'HS_SO_Store' ) ) { return 0; }
		$m = HS_SO_Store::manager_school_id( $uid ); // از ۲.۲.۱ فقط مدیر تأییدشده
		if ( $m ) { return (int) $m; }
		$row = HS_SO_Store::staff_row_by_user( $uid );
		return $row ? (int) $row['school_id'] : 0;
	}

	public static function student_classes( $uid ) {
		$c = get_user_meta( (int) $uid, '_hs_classes', true );
		return is_array( $c ) ? array_values( array_unique( array_filter( array_map( 'absint', $c ) ) ) ) : array();
	}

	public static function is_student( $uid ) {
		$u = get_userdata( (int) $uid );
		return $u && in_array( 'student', (array) $u->roles, true );
	}

	/* ---------- والد ↔ فرزند ---------- */

	/** فرزندان واقعیِ متصل (بدون استثنای معلم/مدیر) */
	public static function children_ids( $uid ) {
		if ( ! class_exists( 'HS_Parent_Link' ) ) { return array(); }
		return array_values( array_filter( HS_Parent_Link::get_children( (int) $uid ), function ( $sid ) use ( $uid ) {
			return $sid && (int) $sid !== (int) $uid && get_userdata( $sid );
		} ) );
	}

	public static function is_parent_of( $uid, $sid ) {
		return in_array( (int) $sid, self::children_ids( $uid ), true );
	}

	/**
	 * والد اجازه‌ی دیدن این بخش از این فرزند را دارد؟
	 * هم اتصال واقعی (نه دسترسی معلم/مدیر)، هم مجوز قابل‌تنظیم مدرسه (HSPP_Perms::can).
	 */
	public static function parent_can( $uid, $sid, $feature, $class_id = 0 ) {
		if ( ! self::is_parent_of( $uid, $sid ) ) {
			return false;
		}
		if ( $class_id && ! in_array( (int) $class_id, self::student_classes( $sid ), true ) ) {
			return false;
		}
		if ( class_exists( 'HSPP_Perms' ) ) {
			return (bool) HSPP_Perms::can( (int) $uid, (int) $sid, $feature, (int) $class_id );
		}
		return (bool) apply_filters( 'hs_api_parent_can_default', true, $uid, $sid, $feature, $class_id );
	}

	/** فرزندانِ این والد در این کلاس که این بخش برایشان باز است */
	public static function children_in_class( $uid, $class_id, $feature = '' ) {
		$out = array();
		foreach ( self::children_ids( $uid ) as $sid ) {
			if ( in_array( (int) $class_id, self::student_classes( $sid ), true ) && ( ! $feature || self::parent_can( $uid, $sid, $feature, $class_id ) ) ) {
				$out[] = (int) $sid;
			}
		}
		return $out;
	}

	public static function features() {
		if ( class_exists( 'HSPP_Perms' ) ) {
			return array_keys( HSPP_Perms::features() );
		}
		return array( 'attendance', 'homework', 'assessment', 'points', 'announce', 'messages', 'group', 'records', 'meetings', 'calendar', 'practice', 'digest' );
	}

	/** نقشه‌ی بخش‌های باز برای یک فرزند در یک کلاس */
	public static function feature_map( $uid, $sid, $class_id = 0 ) {
		$out = array();
		foreach ( self::features() as $f ) {
			$out[ $f ] = self::parent_can( $uid, $sid, $f, $class_id );
		}
		return $out;
	}

	/* ---------- کلاس ---------- */

	public static function supervised_class_ids( $uid ) {
		if ( ! class_exists( 'HS_SO_Store' ) || ! class_exists( 'HS_SO_Monitor' ) ) { return array(); }
		$sid = self::school_id( $uid );
		if ( ! $sid || ! HS_SO_Store::can_supervise( $sid, $uid ) ) { return array(); }
		return array_map( 'intval', HS_SO_Monitor::class_ids( $sid ) );
	}

	/** سطح دسترسی کاربر به یک کلاس یا false */
	public static function class_level( $class_id, $uid ) {
		$class_id = absint( $class_id );
		$uid      = (int) $uid;
		if ( ! self::ready() || ! $class_id || ! $uid || 'hs_class' !== get_post_type( $class_id ) || 'publish' !== get_post_status( $class_id ) ) { return false; }
		if ( HS_Roles::can_manage_class( $class_id, $uid ) ) { return self::WRITE; }
		if ( in_array( $class_id, self::supervised_class_ids( $uid ), true ) ) { return self::SUPER; }
		if ( self::is_student( $uid ) && in_array( $class_id, self::student_classes( $uid ), true ) ) { return self::STUDENT; }
		if ( self::children_in_class( $uid, $class_id ) ) { return self::PARENT; }
		return false;
	}

	/** کلاس‌های قابل‌دسترس کاربر (شناسه‌ها) */
	public static function class_ids( $uid ) {
		$uid = (int) $uid;
		$ids = array();
		if ( self::ready() && user_can( $uid, 'hs_manage_own_classes' ) ) {
			$ids = array_merge( $ids, array_map( 'intval', get_posts( array(
				'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true,
				'meta_query' => array( array( 'key' => '_hs_teacher_id', 'value' => $uid ) ), // phpcs:ignore
			) ) ) );
		}
		$ids = array_merge( $ids, self::supervised_class_ids( $uid ) );
		if ( self::is_student( $uid ) ) {
			$ids = array_merge( $ids, self::student_classes( $uid ) );
		}
		foreach ( self::children_ids( $uid ) as $c ) {
			$ids = array_merge( $ids, self::student_classes( $c ) );
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		return array_values( array_filter( $ids, function ( $cid ) use ( $uid ) { return false !== self::class_level( $cid, $uid ); } ) );
	}

	public static function class_members( $class_id ) {
		return array_values( array_unique( array_filter( array_map( 'intval', (array) get_post_meta( (int) $class_id, '_hs_students', true ) ) ) ) );
	}

	/* ---------- پیام ---------- */

	/** آیا $sender می‌تواند به $to پیام خصوصی بفرستد؟ */
	public static function can_message( $sender, $to ) {
		$sender = (int) $sender;
		$to     = (int) $to;
		if ( ! $sender || ! $to || $sender === $to || ! get_userdata( $to ) || ! self::ready() ) { return false; }
		// معلم ← دانش‌آموز کلاس خودش یا والدِ دانش‌آموز کلاس خودش
		$students = array_merge( array( $to ), self::children_ids( $to ) );
		foreach ( $students as $s ) {
			foreach ( self::student_classes( $s ) as $cid ) {
				if ( HS_Roles::can_manage_class( $cid, $sender ) && ( $s === $to ? self::is_student( $s ) : true ) ) { return true; }
			}
		}
		// دانش‌آموز ← معلم کلاس خودش
		if ( self::is_student( $sender ) ) {
			foreach ( self::student_classes( $sender ) as $cid ) {
				if ( HS_Roles::can_manage_class( $cid, $to ) ) { return true; }
			}
		}
		// والد ← معلم کلاس فرزند، فقط اگر بخش «پیام به معلم» برای آن فرزند باز باشد
		foreach ( self::children_ids( $sender ) as $s ) {
			foreach ( self::student_classes( $s ) as $cid ) {
				if ( HS_Roles::can_manage_class( $cid, $to ) && self::parent_can( $sender, $s, 'messages', $cid ) ) { return true; }
			}
		}
		return false;
	}

	/** دیدن گفت‌وگو: فقط اگر رابطه‌ی فعلی (در یکی از دو جهت) برقرار باشد */
	public static function can_read_thread( $uid, $other ) {
		return self::can_message( $uid, $other ) || self::can_message( $other, $uid );
	}
}
