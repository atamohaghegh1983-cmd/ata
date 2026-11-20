<?php
/**
 * اتصال والد ↔ دانش‌آموز
 *
 * چرخه: proposed (شماره ثبت شده، والد هنوز عضو نیست) → awaiting (والد با همان شماره عضو/وارد شد و باید تأیید کند)
 *       → active (اتصال برقرار) | rejected | removed
 *
 * قاعده‌ها:
 * - شماره‌ای که معلم/مدیر ثبت می‌کند به‌تنهایی دسترسی نمی‌دهد؛ والد باید با همان شماره (تأییدشده با کد پیامکی) وارد شود و بپذیرد.
 * - هر دانش‌آموز ابتدا یک والد؛ والد دوم با دعوت والد اول (حداکثر ۲ والد).
 * - اتصال فعال در ساختار افزونه‌ی مدیریت کلاس (HS_Parent_Link) هم ثبت می‌شود تا پنل والدین فعلی کار کند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Links {

	const MAX_PARENTS     = 2;

	/** در ورود گروهی، پیشنهاد را خود ورود گروهی (با نسبت پدر/مادر) می‌سازد */
	public static $quiet = false;
	const PARENT_PHONE_KEY = '_hs_parent_phone'; // فیلد «شماره والد» در پرونده‌ی دانش‌آموزِ مدیریت کلاس

	public static function relations() {
		return array( 'father' => 'پدر', 'mother' => 'مادر', 'parent' => 'والد', 'guardian' => 'سرپرست' );
	}

	public static function status_labels() {
		return array(
			'proposed' => 'منتظر عضویت والد',
			'awaiting' => 'منتظر تأیید والد',
			'active'   => 'متصل',
			'rejected' => 'رد شده',
			'removed'  => 'حذف شده',
		);
	}

	public static function init() {
		add_action( 'hsum_mobile_known', array( __CLASS__, 'on_mobile_known' ), 10, 2 );
		add_action( 'added_user_meta', array( __CLASS__, 'on_parent_phone' ), 10, 4 );
		add_action( 'updated_user_meta', array( __CLASS__, 'on_parent_phone' ), 10, 4 );
	}

	private static function t() {
		return HSUM_DB::t( 'links' );
	}

	public static function row( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function for_student( $student_id, $live = true ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::t() . ' WHERE student_id = %d' . ( $live ? " AND status IN ('proposed','awaiting','active')" : '' ) . ' ORDER BY id';
		return $wpdb->get_results( $wpdb->prepare( $sql, $student_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function for_parent( $parent_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE parent_id = %d AND status IN ('awaiting','active') ORDER BY status, id", $parent_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function student_class( $student_id ) {
		$c = get_user_meta( $student_id, '_hs_classes', true );
		return is_array( $c ) && $c ? (int) reset( $c ) : 0;
	}

	/**
	 * پیشنهاد اتصال یک شماره به دانش‌آموز
	 * @return int|WP_Error شناسه ردیف
	 */
	public static function propose( $student_id, $mobile, $relation = 'parent', $class_id = 0, $source = 'manual' ) {
		global $wpdb;
		$m = HSUM_Phone::norm( $mobile );
		if ( ! $m || ! get_userdata( $student_id ) ) {
			return new WP_Error( 'bad', 'شماره موبایل یا دانش‌آموز نامعتبر است.' );
		}
		$live = self::for_student( $student_id );
		foreach ( $live as $l ) {
			if ( $l['parent_mobile'] === $m ) {
				return (int) $l['id']; // تکراری نساز
			}
		}
		if ( count( $live ) >= self::MAX_PARENTS ) {
			return new WP_Error( 'max', 'برای این دانش‌آموز حداکثر ' . self::MAX_PARENTS . ' والد ثبت می‌شود.' );
		}
		$parent = HSUM_Phone::user_by_mobile( $m );
		$wpdb->insert( self::t(), array(
			'student_id'    => $student_id,
			'parent_id'     => $parent ? $parent->ID : 0,
			'parent_mobile' => $m,
			'relation'      => isset( self::relations()[ $relation ] ) ? $relation : 'parent',
			'class_id'      => $class_id ? $class_id : self::student_class( $student_id ),
			'status'        => $parent ? 'awaiting' : 'proposed',
			'source'        => sanitize_key( $source ),
			'created_by'    => get_current_user_id(),
			'created_at'    => HSUM_DB::now(),
		) );
		$id = (int) $wpdb->insert_id;
		HSUM_Audit::log( 'link_proposed', $student_id, array( 'link' => $id, 'mobile' => $m, 'source' => $source ) );
		return $id;
	}

	/** والد با شماره‌ی تأییدشده وارد/عضو شد ← پیشنهادها به او برسد */
	public static function on_mobile_known( $user_id, $mobile ) {
		global $wpdb;
		$u = get_userdata( $user_id );
		if ( ! $u || array_intersect( (array) $u->roles, array( 'student', 'administrator' ) ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET parent_id = %d, status = 'awaiting' WHERE parent_mobile = %s AND status = 'proposed'", $user_id, $mobile ) ); // phpcs:ignore

		// دانش‌آموزانی که معلم در پرونده‌شان همین شماره را به‌عنوان شماره والد نوشته (و هنوز پیشنهادی ندارند)
		$tail = substr( $mobile, -9 );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s LIMIT 50", self::PARENT_PHONE_KEY, '%' . $wpdb->esc_like( $tail ) ) ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			if ( HSUM_Phone::norm( $r->meta_value ) === $mobile ) {
				self::propose( (int) $r->user_id, $mobile, 'parent', 0, 'teacher' );
			}
		}
	}

	/** معلم شماره والد را در پرونده‌ی دانش‌آموز ثبت/عوض کرد */
	public static function on_parent_phone( $meta_id, $user_id, $key, $value ) {
		if ( self::$quiet || ! in_array( $key, array( self::PARENT_PHONE_KEY, '_hs_parent2_phone' ), true ) ) {
			return;
		}
		$m = HSUM_Phone::norm( $value );
		if ( ! $m ) {
			return;
		}
		// اگر همین شماره قبلاً برای این دانش‌آموز پیشنهاد/وصل شده، تکرار نشود
		foreach ( (array) self::for_student( $user_id, false ) as $l ) {
			if ( ( $l['parent_mobile'] ?? '' ) === $m ) {
				return;
			}
		}
		self::propose( $user_id, $m, self::PARENT_PHONE_KEY === $key ? 'father' : 'mother', 0, 'teacher' );
	}

	/** والد اتصال را می‌پذیرد */
	public static function confirm( $id, $parent_id ) {
		$l = self::row( $id );
		if ( ! $l || (int) $l['parent_id'] !== (int) $parent_id || 'awaiting' !== $l['status'] ) {
			return new WP_Error( 'bad', 'این درخواست اتصال معتبر نیست.' );
		}
		if ( HSUM_Phone::user_mobile( $parent_id ) !== $l['parent_mobile'] ) {
			return new WP_Error( 'mobile', 'شماره موبایل حساب شما با شماره‌ی ثبت‌شده یکی نیست.' );
		}
		return self::activate( $id, $l );
	}

	/** فعال‌سازی (والد تأیید کرد، یا معلم/مدیر درخواست والد را تأیید کرد) */
	public static function activate( $id, $l = null ) {
		global $wpdb;
		$l = $l ? $l : self::row( $id );
		if ( ! $l || ! $l['parent_id'] ) {
			return new WP_Error( 'bad', 'اتصال نامعتبر است.' );
		}
		$wpdb->update( self::t(), array( 'status' => 'active', 'confirmed_at' => HSUM_DB::now() ), array( 'id' => $id ) );
		$p = get_userdata( $l['parent_id'] );
		if ( $p && ! array_intersect( (array) $p->roles, array( 'parent', 'teacher', 'school_admin', 'administrator' ) ) ) {
			$p->add_role( 'parent' );
		}
		if ( class_exists( 'HS_Parent_Link' ) ) {
			HS_Parent_Link::link( (int) $l['parent_id'], (int) $l['student_id'] );
		}
		HSUM_Audit::log( 'link_active', $l['student_id'], array( 'link' => $id, 'parent' => (int) $l['parent_id'] ) );
		do_action( 'hsum_link_active', (int) $l['parent_id'], (int) $l['student_id'], $id );
		return true;
	}

	/** اتصال مستقیم پس از تأیید معلم (برای درخواست والدی که شماره‌اش در پرونده نبود) */
	public static function link_direct( $student_id, $parent_id, $class_id, $source ) {
		global $wpdb;
		foreach ( self::for_student( $student_id ) as $l ) {
			if ( (int) $l['parent_id'] === (int) $parent_id ) {
				return 'active' === $l['status'] ? true : self::activate( (int) $l['id'] );
			}
		}
		if ( count( self::for_student( $student_id ) ) >= self::MAX_PARENTS ) {
			return new WP_Error( 'max', 'این دانش‌آموز ' . self::MAX_PARENTS . ' والد دارد.' );
		}
		$wpdb->insert( self::t(), array(
			'student_id'    => $student_id,
			'parent_id'     => $parent_id,
			'parent_mobile' => HSUM_Phone::user_mobile( $parent_id ),
			'relation'      => 'parent',
			'class_id'      => $class_id,
			'status'        => 'awaiting',
			'source'        => $source,
			'created_by'    => get_current_user_id(),
			'created_at'    => HSUM_DB::now(),
		) );
		return self::activate( (int) $wpdb->insert_id );
	}

	public static function reject( $id, $parent_id ) {
		global $wpdb;
		$l = self::row( $id );
		if ( ! $l || (int) $l['parent_id'] !== (int) $parent_id || 'awaiting' !== $l['status'] ) {
			return new WP_Error( 'bad', 'این درخواست اتصال معتبر نیست.' );
		}
		$wpdb->update( self::t(), array( 'status' => 'rejected' ), array( 'id' => $id ) );
		HSUM_Audit::log( 'link_rejected', $l['student_id'], array( 'link' => $id ) );
		return true;
	}

	/** حذف اتصال (معلمِ کلاس، مدیر مدرسه‌ی همان مدرسه یا مدیر کل) */
	public static function remove( $id ) {
		global $wpdb;
		$l = self::row( $id );
		if ( ! $l || ! HSUM_Invites::can_manage_class( $l['class_id'] ? $l['class_id'] : self::student_class( $l['student_id'] ) ) ) {
			return new WP_Error( 'cap', 'دسترسی ندارید.' );
		}
		$wpdb->update( self::t(), array( 'status' => 'removed' ), array( 'id' => $id ) );
		if ( $l['parent_id'] && class_exists( 'HS_Parent_Link' ) ) {
			HS_Parent_Link::unlink( (int) $l['parent_id'], (int) $l['student_id'] );
		}
		HSUM_Audit::log( 'link_removed', $l['student_id'], array( 'link' => $id ) );
		return true;
	}

	/** والد اول، والد دوم را دعوت می‌کند */
	public static function invite_second( $student_id, $mobile, $relation, $by_parent ) {
		$mine = false;
		foreach ( self::for_student( $student_id ) as $l ) {
			if ( (int) $l['parent_id'] === (int) $by_parent && 'active' === $l['status'] ) {
				$mine = true;
			}
		}
		if ( ! $mine ) {
			return new WP_Error( 'cap', 'فقط والدِ متصل می‌تواند والد دوم را دعوت کند.' );
		}
		$m = HSUM_Phone::norm( $mobile );
		if ( ! $m ) {
			return new WP_Error( 'mobile', 'شماره موبایل درست نیست.' );
		}
		if ( HSUM_Phone::user_mobile( $by_parent ) === $m ) {
			return new WP_Error( 'self', 'این شماره‌ی خود شماست.' );
		}
		$id = self::propose( $student_id, $m, $relation, 0, 'parent_invite' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( function_exists( 'hsma_send_sms' ) && hsum_opt( 'sms_invites' ) ) {
			$s    = get_userdata( $student_id );
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			hsma_send_sms( $m, sprintf( 'برای دیدن وضعیت تحصیلی %s در %s با همین شماره وارد شوید: %s', $s ? $s->display_name : '', $site, wp_login_url( home_url( '/panel/' ) ) ) );
		}
		return $id;
	}

	/** وضعیت والدین دانش‌آموزان یک کلاس (برای معلم) */
	public static function class_overview( $class_id ) {
		$out = array();
		$ids = get_post_meta( $class_id, '_hs_students', true );
		foreach ( (array) $ids as $sid ) {
			$u = get_userdata( (int) $sid );
			if ( ! $u ) {
				continue;
			}
			$out[] = array(
				'student' => $u,
				'phone'   => HSUM_Phone::norm( get_user_meta( $u->ID, self::PARENT_PHONE_KEY, true ) ),
				'links'   => self::for_student( $u->ID ),
			);
		}
		return $out;
	}
}
