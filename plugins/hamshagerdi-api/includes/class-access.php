<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** تعیین نقش و سطح دسترسی کاربر به کلاس‌ها — منبع حقیقت: افزونهٔ مدیریت کلاس + مدرسه */
class HS_API_Access {

	const WRITE   = 'write';   // معلم کلاس / مدیر سایت
	const SUPER   = 'super';   // مدیر/ناظر مدرسه (فقط‌خواندنی)
	const STUDENT = 'student';
	const PARENT  = 'parent';

	public static function ready() { return class_exists( 'HS_Roles' ) && class_exists( 'HS_Shell' ); }

	public static function view( $uid ) { return self::ready() ? HS_Shell::view_type( $uid ) : 'other'; }

	public static function profile( WP_User $u ) {
		$view = self::view( $u->ID );
		$p = array(
			'id'           => $u->ID,
			'name'         => $u->display_name,
			'username'     => $u->user_login,
			'view'         => $view,   // teacher | manager | parent | student | other
			'roles'        => array_values( (array) $u->roles ),
			'avatar'       => get_avatar_url( $u->ID, array( 'size' => 128 ) ),
			'can_teach'    => 'teacher' === $view,
			'can_supervise'=> false,
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

	public static function school_id( $uid ) {
		if ( ! class_exists( 'HS_SO_Store' ) ) { return 0; }
		$m = HS_SO_Store::manager_school_id( $uid );
		if ( $m ) { return (int) $m; }
		$row = HS_SO_Store::staff_row_by_user( $uid );
		return $row ? (int) $row['school_id'] : 0;
	}

	public static function student_classes( $uid ) {
		$c = get_user_meta( $uid, '_hs_classes', true );
		return is_array( $c ) ? array_map( 'absint', $c ) : array();
	}

	/** سطح دسترسی کاربر به یک کلاس یا false */
	public static function class_level( $class_id, $uid ) {
		$class_id = absint( $class_id );
		if ( ! self::ready() || ! $class_id || 'hs_class' !== get_post_type( $class_id ) ) { return false; }
		if ( HS_Roles::can_manage_class( $class_id, $uid ) ) { return self::WRITE; }
		if ( class_exists( 'HS_SO_Store' ) && class_exists( 'HS_SO_Monitor' ) ) {
			$sid = self::school_id( $uid );
			if ( $sid && HS_SO_Store::can_supervise( $sid, $uid ) && in_array( $class_id, HS_SO_Monitor::class_ids( $sid ), true ) ) {
				return self::SUPER;
			}
		}
		if ( in_array( $class_id, self::student_classes( $uid ), true ) && in_array( 'student', (array) get_userdata( $uid )->roles, true ) ) {
			return self::STUDENT;
		}
		if ( class_exists( 'HS_Parent_Link' ) ) {
			foreach ( HS_Parent_Link::get_children( $uid ) as $child ) {
				if ( in_array( $class_id, self::student_classes( $child ), true ) ) { return self::PARENT; }
			}
		}
		return false;
	}

	/** کلاس‌های قابل‌دسترس کاربر (شناسه‌ها) */
	public static function class_ids( $uid ) {
		$view = self::view( $uid );
		if ( 'teacher' === $view ) {
			$args = array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true );
			if ( ! user_can( $uid, 'hs_manage_all_classes' ) ) {
				$args['meta_query'] = array( array( 'key' => '_hs_teacher_id', 'value' => $uid ) );
			}
			return array_map( 'intval', get_posts( $args ) );
		}
		if ( 'manager' === $view || ( class_exists( 'HS_SO_Store' ) && self::school_id( $uid ) ) ) {
			$sid = self::school_id( $uid );
			if ( $sid && class_exists( 'HS_SO_Monitor' ) && HS_SO_Store::can_supervise( $sid, $uid ) ) {
				return HS_SO_Monitor::class_ids( $sid );
			}
		}
		if ( 'student' === $view ) { return self::student_classes( $uid ); }
		if ( 'parent' === $view && class_exists( 'HS_Parent_Link' ) ) {
			$ids = array();
			foreach ( HS_Parent_Link::get_children( $uid ) as $child ) { $ids = array_merge( $ids, self::student_classes( $child ) ); }
			return array_values( array_unique( $ids ) );
		}
		return array();
	}

	/** آیا a می‌تواند به b پیام خصوصی بدهد؟ (معلم ↔ والد/دانش‌آموز همان کلاس) */
	public static function can_message( $a, $b ) {
		if ( $a === $b || ! get_userdata( $b ) ) { return false; }
		foreach ( array( array( $a, $b ), array( $b, $a ) ) as $pair ) {
			list( $t, $o ) = $pair;
			if ( 'teacher' !== self::view( $t ) ) { continue; }
			$students = array( $o );
			if ( class_exists( 'HS_Parent_Link' ) ) { $students = array_merge( $students, HS_Parent_Link::get_children( $o ) ); }
			foreach ( $students as $s ) {
				foreach ( self::student_classes( $s ) as $cid ) {
					if ( HS_Roles::can_manage_class( $cid, $t ) ) { return true; }
				}
			}
		}
		return false;
	}
}
