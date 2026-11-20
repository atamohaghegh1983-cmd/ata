<?php
/**
 * دعوت‌نامه‌ها
 *
 * ۱) کد مشترک والدین کلاس (type=class): یک کد/لینک برای همه‌ی والدین کلاس، مثل K-7F3Q9A و /join/K-7F3Q9A
 *    کد فقط کلاس را مشخص می‌کند و به‌تنهایی به اطلاعات هیچ دانش‌آموزی دسترسی نمی‌دهد:
 *    والد نام فرزند را می‌نویسد؛ اگر شماره‌ی تأییدشده‌ی والد با شماره‌ی ثبت‌شده در پرونده‌ی همان دانش‌آموز یکی بود ← اتصال،
 *    وگرنه ← درخواست برای معلم.
 * ۲) معرفی معلم توسط مدیر مدرسه (type=teacher): با شماره موبایل معلم؛ وقتی معلم با همان شماره عضو/وارد شد، دعوت را می‌پذیرد.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Invites {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', function ( $v ) {
			$v[] = 'hsum_join';
			return $v;
		} );
		add_action( 'template_redirect', array( __CLASS__, 'join_route' ) );
		add_action( 'hsum_mobile_known', array( __CLASS__, 'on_mobile_known' ), 10, 2 );
		add_filter( 'hs_so_staff_user_ids', array( __CLASS__, 'school_teachers' ), 10, 3 );
	}

	public static function rewrite() {
		add_rewrite_rule( '^join/([A-Za-z0-9-]{4,32})/?$', 'index.php?hsum_join=$matches[1]', 'top' );
	}

	private static function t() {
		return HSUM_DB::t( 'invites' );
	}

	/** /join/CODE ← صفحه‌ی «خانواده و دعوت» (اگر وارد نشده، اول ورود/ثبت‌نام) */
	public static function join_route() {
		$code = get_query_var( 'hsum_join' );
		if ( ! $code ) {
			return;
		}
		$target = add_query_arg( 'join', rawurlencode( strtoupper( $code ) ), HSUM_Front::page_url() );
		// والد با لینک کلاس می‌آید: صفحه‌ی ورود با نقش «والدین» از قبل انتخاب‌شده، و بعد از ورود مستقیم اتصال به کلاس
		wp_safe_redirect( is_user_logged_in() ? $target : add_query_arg( 'role', 'parent', wp_login_url( $target ) ) );
		exit;
	}

	/* ---------- دسترسی ---------- */

	public static function is_site_admin( $uid = 0 ) {
		return user_can( $uid ? $uid : get_current_user_id(), 'manage_options' );
	}

	/** مدرسه‌ای که کاربر مدیر آن است */
	public static function managed_school( $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		return class_exists( 'HS_SO_Store' ) ? (int) HS_SO_Store::manager_school_id( $uid ) : 0;
	}

	public static function class_teacher( $class_id ) {
		return (int) get_post_meta( $class_id, '_hs_teacher_id', true );
	}

	/** مدرسه‌های یک معلم (عضویت این افزونه + کادر ساختار مدرسه) */
	public static function teacher_schools( $uid ) {
		global $wpdb;
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT school_id FROM ' . HSUM_DB::t( 'school_members' ) . " WHERE user_id = %d AND status = 'active'", $uid ) ) ); // phpcs:ignore
		if ( class_exists( 'HS_SO_Store' ) ) {
			$row = HS_SO_Store::staff_row_by_user( $uid );
			if ( $row ) {
				$ids[] = (int) $row['school_id'];
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/** کاربر اجازه‌ی مدیریت دعوت/والدین این کلاس را دارد؟ (معلم کلاس، مدیر مدرسه‌ی معلم، مدیر کل) */
	public static function can_manage_class( $class_id, $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		if ( ! $class_id || 'hs_class' !== get_post_type( $class_id ) ) {
			return false;
		}
		if ( self::is_site_admin( $uid ) ) {
			return true;
		}
		$teacher = self::class_teacher( $class_id );
		if ( $teacher === (int) $uid ) {
			return true;
		}
		$school = self::managed_school( $uid );
		return $school && $teacher && in_array( $school, self::teacher_schools( $teacher ), true );
	}

	/** کلاس‌هایی که کاربر مدیریت می‌کند */
	public static function manageable_classes( $uid = 0 ) {
		$uid  = $uid ? $uid : get_current_user_id();
		$args = array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true );
		if ( ! self::is_site_admin( $uid ) ) {
			$teachers = array( (int) $uid );
			$school   = self::managed_school( $uid );
			if ( $school ) {
				$teachers = array_merge( $teachers, self::school_teacher_ids( $school ) );
			}
			$args['meta_query'] = array( array( 'key' => '_hs_teacher_id', 'value' => array_unique( $teachers ), 'compare' => 'IN' ) );
		}
		return get_posts( $args );
	}

	public static function school_teacher_ids( $school_id ) {
		global $wpdb;
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . HSUM_DB::t( 'school_members' ) . " WHERE school_id = %d AND status = 'active'", $school_id ) ) ); // phpcs:ignore
		if ( class_exists( 'HS_SO_Store' ) ) {
			remove_filter( 'hs_so_staff_user_ids', array( __CLASS__, 'school_teachers' ), 10 );
			$ids = array_merge( $ids, array_map( 'intval', (array) HS_SO_Store::staff_user_ids( $school_id ) ) );
			add_filter( 'hs_so_staff_user_ids', array( __CLASS__, 'school_teachers' ), 10, 3 );
		}
		return array_values( array_unique( $ids ) );
	}

	/** نظارت ساختار مدرسه، معلمانِ دعوت‌شده را هم ببیند */
	public static function school_teachers( $ids, $school_id, $position = '' ) {
		global $wpdb;
		if ( $position && 'teacher' !== $position ) {
			return $ids;
		}
		$more = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . HSUM_DB::t( 'school_members' ) . " WHERE school_id = %d AND status = 'active'", $school_id ) ) ); // phpcs:ignore
		return array_values( array_unique( array_merge( (array) $ids, $more ) ) );
	}

	/* ---------- کد مشترک کلاس ---------- */

	public static function class_invite( $class_id, $create = true ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE type = 'class' AND class_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1", $class_id ), ARRAY_A ); // phpcs:ignore
		if ( $row && $row['expires_at'] && strtotime( $row['expires_at'] ) < current_time( 'timestamp' ) ) {
			$wpdb->update( self::t(), array( 'status' => 'expired' ), array( 'id' => $row['id'] ) );
			$row = null;
		}
		if ( ! $row && $create ) {
			$row = self::new_class_code( $class_id );
		}
		return $row;
	}

	public static function new_class_code( $class_id ) {
		global $wpdb;
		$wpdb->update( self::t(), array( 'status' => 'revoked' ), array( 'type' => 'class', 'class_id' => $class_id, 'status' => 'active' ) );
		do {
			$code = 'K-' . strtoupper( substr( str_replace( array( '0', 'O', '1', 'I', 'L' ), '', wp_generate_password( 20, false ) ), 0, 6 ) );
		} while ( strlen( $code ) < 8 || $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t() . ' WHERE code = %s', $code ) ) ); // phpcs:ignore
		$days = (int) hsum_opt( 'invite_days' );
		$wpdb->insert( self::t(), array(
			'type'       => 'class',
			'code'       => $code,
			'class_id'   => $class_id,
			'status'     => 'active',
			'created_by' => get_current_user_id(),
			'created_at' => HSUM_DB::now(),
			'expires_at' => $days ? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS ) : null,
		) );
		HSUM_Audit::log( 'invite', $class_id, array( 'type' => 'class', 'code' => $code ) );
		return self::by_code( $code );
	}

	public static function by_code( $code ) {
		global $wpdb;
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', strtr( (string) $code, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) ) ) );
		if ( preg_match( '/^K[A-Z0-9]{6}$/', $code ) ) {
			$code = 'K-' . substr( $code, 1 );
		}
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE code = %s AND status = 'active'", $code ), ARRAY_A ); // phpcs:ignore
		if ( $row && $row['expires_at'] && strtotime( $row['expires_at'] ) < current_time( 'timestamp' ) ) {
			return null;
		}
		// کدی که روی کارت کلاس (افزونه‌ی مدیریت کلاس) نمایش داده می‌شود هم معتبر است
		if ( ! $row && preg_match( '/^[A-Z0-9]{5,8}$/', $code ) ) {
			$cls = get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'any', 'meta_key' => '_hs_invite_code', 'meta_value' => $code, 'fields' => 'ids', 'posts_per_page' => 1 ) ); // phpcs:ignore
			if ( $cls ) {
				$old = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t() . ' WHERE code = %s', $code ) ); // phpcs:ignore
				if ( $old ) {
					return null; // این کد قبلاً باطل شده
				}
				$wpdb->insert( self::t(), array(
					'type'       => 'class',
					'code'       => $code,
					'class_id'   => (int) $cls[0],
					'status'     => 'active',
					'created_by' => (int) get_post_meta( $cls[0], '_hs_teacher_id', true ),
					'created_at' => HSUM_DB::now(),
					'expires_at' => null,
				) );
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE code = %s AND status = 'active'", $code ), ARRAY_A ); // phpcs:ignore
			}
		}
		return $row;
	}

	public static function link_url( $code ) {
		return home_url( '/join/' . $code . '/' );
	}

	/**
	 * والد با کد کلاس، نام فرزند را می‌دهد.
	 * @return array{status:string,message:string}|WP_Error
	 */
	public static function parent_join( $code, $parent_id, $child_name ) {
		global $wpdb;
		$inv = self::by_code( $code );
		if ( ! $inv ) {
			return new WP_Error( 'code', 'کد دعوت معتبر نیست یا منقضی شده است.' );
		}
		if ( class_exists( 'HS_Class_Students' ) && method_exists( 'HS_Class_Students', 'join_closed' ) && HS_Class_Students::join_closed( (int) $inv['class_id'] ) ) {
			return new WP_Error( 'closed', HS_Class_Students::join_closed_message() );
		}
		$child_name = trim( sanitize_text_field( $child_name ) );
		if ( mb_strlen( $child_name ) < 3 ) {
			return new WP_Error( 'name', 'نام و نام خانوادگی فرزند را کامل بنویسید.' );
		}
		$mobile = HSUM_Phone::user_mobile( $parent_id );
		if ( ! $mobile ) {
			return new WP_Error( 'mobile', 'ابتدا شماره موبایل حساب شما باید ثبت و تأیید شود.' );
		}
		$class_id = (int) $inv['class_id'];
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . ' SET uses = uses + 1 WHERE id = %d', $inv['id'] ) ); // phpcs:ignore

		// دانش‌آموزان کلاس که شماره‌ی والدِ ثبت‌شده‌شان همین شماره است
		$norm  = function ( $s ) {
			$s = str_replace( array( 'ي', 'ك', "\u{200C}" ), array( 'ی', 'ک', ' ' ), $s );
			return preg_replace( '/\s+/u', '', $s );
		};
		$match = array();
		foreach ( (array) get_post_meta( $class_id, '_hs_students', true ) as $sid ) {
			$sid = (int) $sid;
			$ph  = HSUM_Phone::norm( get_user_meta( $sid, HSUM_Links::PARENT_PHONE_KEY, true ) );
			$own = false;
			foreach ( HSUM_Links::for_student( $sid ) as $l ) {
				if ( $l['parent_mobile'] === $mobile ) {
					$own = true;
				}
			}
			if ( $ph === $mobile || $own ) {
				$u = get_userdata( $sid );
				if ( $u ) {
					$match[ $sid ] = $norm( $u->display_name );
				}
			}
		}
		$want = $norm( $child_name );
		$hit  = array_keys( array_filter( $match, function ( $n ) use ( $want ) {
			return $n === $want || false !== mb_strpos( $n, $want ) || false !== mb_strpos( $want, $n );
		} ) );
		if ( 1 === count( $hit ) || ( 1 === count( $match ) && ! $hit ) ) {
			$sid = $hit ? $hit[0] : array_keys( $match )[0];
			$r   = HSUM_Links::link_direct( $sid, $parent_id, $class_id, 'class_code' );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$u = get_userdata( $sid );
			return array( 'status' => 'linked', 'message' => 'به حساب ' . $u->display_name . ' در کلاس «' . get_the_title( $class_id ) . '» متصل شدید.' );
		}
		// تطبیق نشد ← درخواست برای معلم
		$rid = HSUM_Requests::create( $parent_id, array( 'class_id' => $class_id, 'child_name' => $child_name, 'message' => 'از کد دعوت کلاس' ) );
		if ( is_wp_error( $rid ) ) {
			return $rid;
		}
		return array( 'status' => 'request', 'message' => 'شماره‌ی شما در فهرست این کلاس پیدا نشد؛ درخواست شما برای معلم کلاس «' . get_the_title( $class_id ) . '» فرستاده شد. بعد از تأیید معلم، اتصال برقرار می‌شود.' );
	}

	/* ---------- معرفی معلم توسط مدیر مدرسه ---------- */

	public static function invite_teacher( $school_id, $mobile, $name = '' ) {
		global $wpdb;
		if ( ! self::is_site_admin() && self::managed_school() !== (int) $school_id ) {
			return new WP_Error( 'cap', 'فقط مدیر همین مدرسه می‌تواند معلم معرفی کند.' );
		}
		$m = HSUM_Phone::norm( $mobile );
		if ( ! $m ) {
			return new WP_Error( 'mobile', 'شماره موبایل معلم درست نیست.' );
		}
		$dup = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t() . " WHERE type = 'teacher' AND school_id = %d AND mobile = %s AND status = 'active'", $school_id, $m ) ); // phpcs:ignore
		if ( $dup ) {
			return new WP_Error( 'dup', 'این شماره قبلاً دعوت شده است.' );
		}
		$u = HSUM_Phone::user_by_mobile( $m );
		$wpdb->insert( self::t(), array(
			'type'       => 'teacher',
			'school_id'  => $school_id,
			'mobile'     => $m,
			'name'       => sanitize_text_field( $name ),
			'user_id'    => $u ? $u->ID : 0,
			'status'     => 'active',
			'created_by' => get_current_user_id(),
			'created_at' => HSUM_DB::now(),
		) );
		$id = (int) $wpdb->insert_id;
		HSUM_Audit::log( 'invite', $school_id, array( 'type' => 'teacher', 'mobile' => $m, 'invite' => $id ) );
		if ( function_exists( 'hsma_send_sms' ) && hsum_opt( 'sms_invites' ) ) {
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			hsma_send_sms( $m, sprintf( '%sشما به‌عنوان معلم «%s» در %s معرفی شدید. با همین شماره وارد شوید یا ثبت‌نام کنید: %s', $name ? $name . ' عزیز، ' : '', get_the_title( $school_id ), $site, wp_login_url( HSUM_Front::page_url() ) ) );
		}
		return $id;
	}

	/** کاربر با شماره‌ی تأییدشده وارد/عضو شد ← دعوت‌های معلمی به او برسد */
	public static function on_mobile_known( $user_id, $mobile ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET user_id = %d WHERE type = 'teacher' AND mobile = %s AND status = 'active' AND user_id = 0", $user_id, $mobile ) ); // phpcs:ignore
	}

	public static function teacher_invites_for( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE type = 'teacher' AND user_id = %d AND status = 'active'", $user_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function school_invites( $school_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE type = 'teacher' AND school_id = %d ORDER BY id DESC LIMIT 200", $school_id ), ARRAY_A ); // phpcs:ignore
	}

	/** معلم دعوت مدرسه را می‌پذیرد/رد می‌کند */
	public static function answer_teacher_invite( $invite_id, $user_id, $accept ) {
		global $wpdb;
		$inv = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE id = %d AND type = 'teacher' AND status = 'active'", $invite_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $inv || (int) $inv['user_id'] !== (int) $user_id || HSUM_Phone::user_mobile( $user_id ) !== $inv['mobile'] ) {
			return new WP_Error( 'bad', 'دعوت‌نامه معتبر نیست.' );
		}
		$wpdb->update( self::t(), array( 'status' => $accept ? 'accepted' : 'declined' ), array( 'id' => $invite_id ) );
		if ( ! $accept ) {
			return true;
		}
		$t = HSUM_DB::t( 'school_members' );
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE school_id = %d AND user_id = %d", $inv['school_id'], $user_id ) ) ) { // phpcs:ignore
			$wpdb->insert( $t, array( 'school_id' => $inv['school_id'], 'user_id' => $user_id, 'role' => 'teacher', 'status' => 'active', 'invite_id' => $invite_id, 'created_at' => HSUM_DB::now() ) );
		}
		// کاربری که هنوز نقشی ندارد ← درخواست معلمی (نیازمند تأیید مدیر کل)
		$u = get_userdata( $user_id );
		if ( $u && ! array_intersect( (array) $u->roles, array( 'teacher', 'administrator', HSUM_Membership::PENDING_ROLE ) ) ) {
			HSUM_Membership::request_role( $user_id, 'teacher' );
		}
		// کلاس‌هایی که معلم قبلاً به‌تنهایی ساخته و هنوز مدرسه ندارند ← به همین مدرسه وصل شوند
		$attached = self::attach_teacher_classes( $user_id, (int) $inv['school_id'] );
		HSUM_Audit::log( 'school_member', $user_id, array( 'school' => (int) $inv['school_id'], 'invite' => (int) $invite_id, 'classes' => $attached ) );
		return true;
	}

	/** @return int[] شناسه‌ی کلاس‌هایی که وصل شدند */
	public static function attach_teacher_classes( $user_id, $school_id ) {
		$ids = get_posts( array(
			'post_type'      => 'hs_class',
			'post_status'    => 'any',
			'meta_key'       => '_hs_teacher_id', // phpcs:ignore
			'meta_value'     => (int) $user_id, // phpcs:ignore
			'fields'         => 'ids',
			'posts_per_page' => -1,
		) );
		$done = array();
		foreach ( $ids as $cid ) {
			// _hs_school نام مدرسه (متن) است و دست نمی‌خورد؛ پیوند با شناسه در _hs_school_id
			if ( ! (int) get_post_meta( $cid, '_hs_school_id', true ) ) {
				update_post_meta( $cid, '_hs_school_id', (int) $school_id );
				if ( '' === trim( (string) get_post_meta( $cid, '_hs_school', true ) ) ) {
					update_post_meta( $cid, '_hs_school', get_the_title( $school_id ) );
				}
				$done[] = (int) $cid;
			}
		}
		return $done;
	}

	public static function revoke( $invite_id ) {
		global $wpdb;
		$inv = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $invite_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $inv ) {
			return new WP_Error( 'bad', 'یافت نشد.' );
		}
		$ok = 'class' === $inv['type'] ? self::can_manage_class( $inv['class_id'] ) : ( self::is_site_admin() || self::managed_school() === (int) $inv['school_id'] );
		if ( ! $ok ) {
			return new WP_Error( 'cap', 'دسترسی ندارید.' );
		}
		$wpdb->update( self::t(), array( 'status' => 'revoked' ), array( 'id' => $invite_id ) );
		return true;
	}

	/* ---------- کلاس‌هایی که معلم، موبایل مدیر را برایشان ثبت کرده ---------- */

	/** کلاس‌های منتظر اتصال به مدرسه‌ی این مدیر (موبایل مدیر در فرم کلاس) */
	public static function claimable_classes( $manager_uid ) {
		$m = HSUM_Phone::user_mobile( $manager_uid );
		if ( ! $m || ! self::managed_school( $manager_uid ) ) {
			return array();
		}
		$ids = get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 100, 'meta_key' => '_hs_manager_mobile', 'meta_value' => $m ) ); // phpcs:ignore
		return array_values( array_filter( $ids, function ( $cid ) {
			return ! (int) get_post_meta( $cid, '_hs_school_id', true );
		} ) );
	}

	/** مدیر تأیید می‌کند ← کلاس به مدرسه وصل و معلم عضو مدرسه می‌شود */
	public static function claim_class( $class_id, $manager_uid ) {
		global $wpdb;
		$school = self::managed_school( $manager_uid );
		if ( ! $school || ! in_array( (int) $class_id, self::claimable_classes( $manager_uid ), true ) ) {
			return new WP_Error( 'bad', 'این کلاس برای اتصال به مدرسه‌ی شما معرفی نشده است.' );
		}
		update_post_meta( $class_id, '_hs_school_id', $school );
		$teacher = self::class_teacher( $class_id );
		$t       = HSUM_DB::t( 'school_members' );
		if ( $teacher && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE school_id = %d AND user_id = %d", $school, $teacher ) ) ) { // phpcs:ignore
			$wpdb->insert( $t, array( 'school_id' => $school, 'user_id' => $teacher, 'role' => 'teacher', 'status' => 'active', 'invite_id' => 0, 'created_at' => HSUM_DB::now() ) );
		}
		HSUM_Audit::log( 'school_class', $class_id, array( 'school' => $school, 'by' => $manager_uid ) );
		return true;
	}
}
