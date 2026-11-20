<?php
/**
 * درخواست اتصال والدین
 * - والدی که هنوز به فرزندی متصل نیست: درخواست به معلم (با شماره موبایل معلم) یا مدیر مدرسه
 * - والدی که با کد کلاس آمد ولی شماره‌اش در فهرست کلاس نبود
 * معلم/مدیر: انتخاب دانش‌آموز ← اتصال، یا رد.
 * اگر معلمِ معرفی‌شده هنوز عضو سایت نیست، پیامک دعوت به سایت برایش فرستاده می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Requests {

	const MAX_OPEN = 5;

	public static function init() {
		add_action( 'hsum_mobile_known', array( __CLASS__, 'on_mobile_known' ), 10, 2 );
	}

	private static function t() {
		return HSUM_DB::t( 'requests' );
	}

	public static function status_labels() {
		return array( 'open' => 'در انتظار', 'approved' => 'متصل شد', 'rejected' => 'رد شد' );
	}

	public static function row( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	}

	/**
	 * @param array $a class_id | teacher_mobile | school_id, child_name, message
	 * @return int|WP_Error
	 */
	public static function create( $parent_id, $a ) {
		global $wpdb;
		$open = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . " WHERE parent_id = %d AND status = 'open'", $parent_id ) ); // phpcs:ignore
		if ( $open >= self::MAX_OPEN ) {
			return new WP_Error( 'max', 'شما ' . self::MAX_OPEN . ' درخواست باز دارید؛ منتظر پاسخ بمانید.' );
		}
		$child = sanitize_text_field( $a['child_name'] ?? '' );
		if ( mb_strlen( trim( $child ) ) < 3 ) {
			return new WP_Error( 'name', 'نام و نام خانوادگی فرزند را بنویسید.' );
		}
		$class_id = absint( $a['class_id'] ?? 0 );
		$school   = absint( $a['school_id'] ?? 0 );
		$tmobile  = HSUM_Phone::norm( $a['teacher_mobile'] ?? '' );
		$teacher  = $class_id ? HSUM_Invites::class_teacher( $class_id ) : 0;
		if ( ! $teacher && $tmobile ) {
			$tu      = HSUM_Phone::user_by_mobile( $tmobile );
			$teacher = $tu && array_intersect( (array) $tu->roles, array( 'teacher', 'administrator' ) ) ? $tu->ID : 0;
		}
		if ( ! $class_id && ! $teacher && ! $tmobile && ! $school ) {
			return new WP_Error( 'to', 'شماره موبایل معلم یا مدرسه را مشخص کنید.' );
		}
		$wpdb->insert( self::t(), array(
			'parent_id'      => $parent_id,
			'class_id'       => $class_id,
			'school_id'      => $school,
			'teacher_id'     => $teacher,
			'teacher_mobile' => $tmobile,
			'child_name'     => $child,
			'message'        => sanitize_textarea_field( $a['message'] ?? '' ),
			'status'         => 'open',
			'created_at'     => HSUM_DB::now(),
		) );
		$id = (int) $wpdb->insert_id;
		HSUM_Audit::log( 'request', $parent_id, array( 'request' => $id, 'class' => $class_id, 'teacher' => $teacher ) );

		// معلم هنوز عضو سایت نیست ← دعوت به سایت با پیامک
		if ( $tmobile && ! $teacher && function_exists( 'hsma_send_sms' ) && hsum_opt( 'sms_invites' ) && ! get_transient( 'hsum_tinv_' . $tmobile ) ) {
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			hsma_send_sms( $tmobile, sprintf( 'یکی از والدین کلاس شما در %s درخواست اتصال به کلاس را دارد. با همین شماره ثبت‌نام کنید: %s', $site, wp_login_url( HSUM_Front::page_url() ) ) );
			set_transient( 'hsum_tinv_' . $tmobile, 1, DAY_IN_SECONDS );
		}
		return $id;
	}

	/** معلمی که بعداً با همان شماره عضو شد، درخواست‌ها را ببیند */
	public static function on_mobile_known( $user_id, $mobile ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET teacher_id = %d WHERE teacher_mobile = %s AND teacher_id = 0 AND status = 'open'", $user_id, $mobile ) ); // phpcs:ignore
	}

	public static function for_parent( $parent_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE parent_id = %d ORDER BY id DESC LIMIT 20', $parent_id ), ARRAY_A ); // phpcs:ignore
	}

	/** درخواست‌های باز که این کاربر می‌تواند رسیدگی کند */
	public static function inbox( $uid = 0 ) {
		global $wpdb;
		$uid = $uid ? $uid : get_current_user_id();
		$t   = self::t();
		if ( HSUM_Invites::is_site_admin( $uid ) ) {
			return $wpdb->get_results( "SELECT * FROM $t WHERE status = 'open' ORDER BY id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore
		}
		$classes = wp_list_pluck( HSUM_Invites::manageable_classes( $uid ), 'ID' );
		$school  = HSUM_Invites::managed_school( $uid );
		$where   = array( $wpdb->prepare( 'teacher_id = %d', $uid ) );
		if ( $classes ) {
			$where[] = 'class_id IN (' . implode( ',', array_map( 'intval', $classes ) ) . ')';
		}
		if ( $school ) {
			$where[] = $wpdb->prepare( 'school_id = %d', $school );
			$tids    = HSUM_Invites::school_teacher_ids( $school );
			if ( $tids ) {
				$where[] = 'teacher_id IN (' . implode( ',', array_map( 'intval', $tids ) ) . ')';
			}
		}
		return $wpdb->get_results( "SELECT * FROM $t WHERE status = 'open' AND (" . implode( ' OR ', $where ) . ') ORDER BY id DESC LIMIT 200', ARRAY_A ); // phpcs:ignore
	}

	private static function can_handle( $r, $uid ) {
		foreach ( self::inbox( $uid ) as $x ) {
			if ( (int) $x['id'] === (int) $r['id'] ) {
				return true;
			}
		}
		return false;
	}

	/** تأیید: اتصال والد به دانش‌آموز انتخاب‌شده */
	public static function approve( $id, $student_id ) {
		global $wpdb;
		$r = self::row( $id );
		if ( ! $r || 'open' !== $r['status'] || ! self::can_handle( $r, get_current_user_id() ) ) {
			return new WP_Error( 'cap', 'دسترسی ندارید.' );
		}
		$class = HSUM_Links::student_class( $student_id );
		$ok    = false;
		foreach ( HSUM_Invites::manageable_classes() as $c ) {
			if ( in_array( (int) $student_id, array_map( 'intval', (array) get_post_meta( $c->ID, '_hs_students', true ) ), true ) ) {
				$ok    = true;
				$class = $c->ID;
				break;
			}
		}
		if ( ! $ok ) {
			return new WP_Error( 'cap', 'این دانش‌آموز در کلاس‌های شما نیست.' );
		}
		$res = HSUM_Links::link_direct( $student_id, (int) $r['parent_id'], $class, 'request' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$wpdb->update( self::t(), array( 'status' => 'approved', 'handled_by' => get_current_user_id(), 'handled_at' => HSUM_DB::now() ), array( 'id' => $id ) );
		self::notify( $r['parent_id'], 'درخواست شما تأیید شد و به حساب فرزندتان متصل شدید. ' . home_url( '/panel/parent/' ) );
		return true;
	}

	public static function reject( $id, $reason = '' ) {
		global $wpdb;
		$r = self::row( $id );
		if ( ! $r || 'open' !== $r['status'] || ! self::can_handle( $r, get_current_user_id() ) ) {
			return new WP_Error( 'cap', 'دسترسی ندارید.' );
		}
		$wpdb->update( self::t(), array( 'status' => 'rejected', 'handled_by' => get_current_user_id(), 'handled_at' => HSUM_DB::now(), 'message' => trim( $r['message'] . "\nپاسخ: " . sanitize_text_field( $reason ) ) ), array( 'id' => $id ) );
		return true;
	}

	private static function notify( $user_id, $text ) {
		if ( function_exists( 'hsma_send_sms' ) ) {
			$m = HSUM_Phone::user_mobile( $user_id );
			if ( $m ) {
				hsma_send_sms( $m, $text );
			}
		}
	}
}
