<?php
/**
 * کادر مدرسه: مدیر، معاونان (اجرایی، آموزشی، پرورشی) و معلمان را ردیف‌به‌ردیف ثبت می‌کند.
 * برای هر نفر یک دعوت‌نامه (type=staff) با کد اختصاصی، لینک /join/CODE و کیوآر کد ساخته می‌شود.
 * وقتی آن شخص با همین شماره وارد شود یا لینک/کد را باز کند، به کادر مدرسه اضافه می‌شود؛
 * اگر مدیر برای معلم «کلاسش را هم بساز» زده باشد، کلاس به نام معلم منتقل می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Staff {

	const TYPE = 'staff';

	public static function init() {
		add_action( 'hsum_mobile_known', array( __CLASS__, 'on_mobile_known' ), 20, 2 );
		add_action( 'admin_post_hsum_staff_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'wp_ajax_hsum_staff_sms', array( __CLASS__, 'ajax_sms' ) );
		add_action( 'wp_ajax_hsum_staff_import', array( __CLASS__, 'ajax_import' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'template_redirect', array( __CLASS__, 'join_route' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'qr_upload_boot' ) );
	}

	public static function positions() {
		return array(
			'teacher'     => 'معلم',
			'deputy_exec' => 'معاون اجرایی',
			'deputy_edu'  => 'معاون آموزشی',
			'deputy_cult' => 'معاون پرورشی',
		);
	}

	public static function grades() {
		return class_exists( 'HS_Class_Meta' ) ? HS_Class_Meta::grades() : array( 'پیش‌دبستانی', 'اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم' );
	}

	private static function t() {
		return HSUM_DB::t( 'invites' );
	}

	public static function register() {
		wp_register_script( 'hsum-qrcode', HSUM_URL . 'assets/qrcode.js', array(), '1.4.4', true );
		wp_register_script( 'hsum-staff', HSUM_URL . 'assets/staff.js', array( 'hsum-qrcode' ), HSUM_VERSION, true );
		wp_localize_script( 'hsum-staff', 'hsumStaff', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'hsum_staff' ),
			'jsqr'  => HSUM_URL . 'assets/jsqr.min.js?v=1.4.0',
		) );
	}

	/* ------------------------------ داده ------------------------------ */

	private static function new_code() {
		global $wpdb;
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$c = 'T-';
			for ( $i = 0; $i < 6; $i++ ) {
				$c .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
			}
		} while ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t() . ' WHERE code = %s', $c ) ) ); // phpcs:ignore
		return $c;
	}

	private static function decode( $row ) {
		$d = json_decode( (string) ( $row['data'] ?? '' ), true );
		$row['d'] = is_array( $d ) ? $d : array();
		return $row;
	}

	/** ردیف‌های کادر یک مدرسه (فعال و پذیرفته‌شده) */
	public static function rows( $school_id ) {
		global $wpdb;
		if ( ! $school_id ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE type = %s AND school_id = %d AND status IN ('active','accepted') ORDER BY id ASC", self::TYPE, $school_id ), ARRAY_A ); // phpcs:ignore
		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	public static function get( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d AND type = %s', $id, self::TYPE ), ARRAY_A ); // phpcs:ignore
		return $r ? self::decode( $r ) : null;
	}

	/**
	 * ردیف‌های ارسالی فرم را با جدول هماهنگ می‌کند: افزودن، ویرایش، حذف (فقط ردیف‌هایی که هنوز پذیرفته نشده‌اند).
	 * @return array{added:int,errors:string[]}
	 */
	public static function sync( $school_id, $posted, $manager_id ) {
		global $wpdb;
		$out  = array( 'added' => 0, 'errors' => array() );
		$keep = array();
		$seen = array();
		$cur  = array();
		foreach ( self::rows( $school_id ) as $r ) {
			$cur[ (int) $r['id'] ] = $r;
		}
		foreach ( (array) $posted as $in ) {
			$in    = (array) $in;
			$pos   = sanitize_key( $in['position'] ?? 'teacher' );
			$pos   = isset( self::positions()[ $pos ] ) ? $pos : 'teacher';
			$first = sanitize_text_field( $in['first'] ?? '' );
			$last  = sanitize_text_field( $in['last'] ?? '' );
			$mob   = HSUM_Phone::norm( sanitize_text_field( $in['mobile'] ?? '' ) );
			if ( '' === trim( $first . $last ) && ! $mob ) {
				continue; // ردیف خالی
			}
			$name = trim( $first . ' ' . $last );
			if ( '' === $first || '' === $last ) {
				$out['errors'][] = 'نام و نام خانوادگی «' . ( $name ? $name : 'ردیف بدون نام' ) . '» کامل نیست.';
				continue;
			}
			if ( ! $mob ) {
				$out['errors'][] = 'شماره موبایل «' . $name . '» درست نیست.';
				continue;
			}
			if ( isset( $seen[ $mob ] ) ) {
				$out['errors'][] = 'شماره‌ی ' . $mob . ' تکراری است.';
				continue;
			}
			$seen[ $mob ] = 1;
			$id   = absint( $in['id'] ?? 0 );
			$old  = $id && isset( $cur[ $id ] ) ? $cur[ $id ] : null;
			$d    = $old ? $old['d'] : array();
			$tch  = 'teacher' === $pos;
			$d    = array_merge( $d, array(
				'position' => $pos,
				'first'    => $first,
				'last'     => $last,
				// ابتدایی: کلاس و پایه ملاک است؛ متوسطه: درس + کلاس + پایه
				'subject'  => $tch ? sanitize_text_field( $in['subject'] ?? '' ) : '',
				'class'    => $tch ? sanitize_text_field( $in['class'] ?? '' ) : '',
				'grade'    => $tch && in_array( $in['grade'] ?? '', self::grades(), true ) ? $in['grade'] : '',
			) );
			unset( $d['mode'], $d['title'] );
			$d['title'] = trim( ( $d['grade'] && false === mb_strpos( $d['class'], $d['grade'] ) ? $d['grade'] . ' ' : '' ) . $d['class'] );
			// ساخت کلاس از روی ردیف معلم (یک بار)
			if ( $tch && ! empty( $in['make_class'] ) && empty( $d['class_id'] ) && ( $d['class'] || $d['grade'] ) ) {
				$d['title'] = $d['title'] ? $d['title'] : $d['grade'];
				$cid = self::create_class( $school_id, $d, $manager_id );
				if ( $cid ) {
					$d['class_id'] = $cid;
				}
			}
			$row = array( 'mobile' => $mob, 'name' => $name, 'data' => wp_json_encode( $d, JSON_UNESCAPED_UNICODE ) );
			if ( $old ) {
				if ( 'accepted' === $old['status'] ) {
					unset( $row['mobile'] ); // شماره‌ی عضوِ پذیرفته‌شده عوض نمی‌شود
				}
				$wpdb->update( self::t(), $row, array( 'id' => $id ) );
				$keep[] = $id;
				if ( 'accepted' === $old['status'] && class_exists( 'HS_SO_Store' ) && $old['user_id'] ) {
					self::so_position( (int) $old['user_id'], $school_id, $pos );
				}
				continue;
			}
			$dup = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t() . " WHERE type = %s AND school_id = %d AND mobile = %s AND status IN ('active','accepted')", self::TYPE, $school_id, $mob ) ); // phpcs:ignore
			if ( $dup ) {
				$keep[] = (int) $dup;
				continue;
			}
			$u = HSUM_Phone::user_by_mobile( $mob );
			$wpdb->insert( self::t(), $row + array(
				'type'       => self::TYPE,
				'code'       => self::new_code(),
				'school_id'  => $school_id,
				'user_id'    => 0,
				'status'     => 'active',
				'created_by' => $manager_id,
				'created_at' => HSUM_DB::now(),
			) );
			$nid    = (int) $wpdb->insert_id;
			$keep[] = $nid;
			$out['added']++;
			HSUM_Audit::log( 'invite', $school_id, array( 'type' => 'staff', 'mobile' => $mob, 'invite' => $nid ) );
			// کسی که همین حالا با این شماره عضو سایت است، مستقیم به کادر اضافه می‌شود
			if ( $u ) {
				self::accept( self::get( $nid ), $u->ID );
			}
		}
		// ردیف‌های حذف‌شده از فرم: دعوت باطل؛ عضوِ پذیرفته‌شده از کادر خارج می‌شود
		foreach ( $cur as $id => $r ) {
			if ( in_array( $id, $keep, true ) ) {
				continue;
			}
			$wpdb->update( self::t(), array( 'status' => 'revoked' ), array( 'id' => $id ) );
			if ( 'accepted' === $r['status'] && $r['user_id'] && class_exists( 'HS_SO_Store' ) ) {
				$sr = HS_SO_Store::staff_row_by_user( (int) $r['user_id'] );
				if ( $sr && (int) $sr['school_id'] === (int) $school_id ) {
					HS_SO_Store::remove_staff( (int) $sr['id'] );
				}
			}
		}
		return $out;
	}

	private static function so_position( $uid, $school_id, $pos ) {
		global $wpdb;
		$sr = HS_SO_Store::staff_row_by_user( $uid );
		if ( $sr && (int) $sr['school_id'] === (int) $school_id && $sr['position'] !== $pos ) {
			$wpdb->update( HS_SO_Store::staff_table(), array( 'position' => $pos, 'can_view' => 0 === strpos( $pos, 'deputy_' ) ? 1 : (int) $sr['can_view'] ), array( 'id' => $sr['id'] ) );
		}
	}

	/** کلاس به نام مدیر ساخته می‌شود و پس از عضویت معلم به او منتقل می‌شود */
	private static function create_class( $school_id, $d, $manager_id ) {
		if ( ! post_type_exists( 'hs_class' ) ) {
			return 0;
		}
		$school = get_the_title( $school_id );
		$cid    = wp_insert_post( array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'post_title' => $d['title'], 'post_author' => $manager_id ), true );
		if ( is_wp_error( $cid ) ) {
			return 0;
		}
		update_post_meta( $cid, '_hs_grade', $d['grade'] );
		update_post_meta( $cid, '_hs_year', class_exists( 'HS_Util' ) && method_exists( 'HS_Util', 'academic_year' ) ? HS_Util::academic_year() : '' );
		update_post_meta( $cid, '_hs_school', $school );
		update_post_meta( $cid, '_hs_school_id', $school_id );
		update_post_meta( $cid, '_hs_city', (string) get_post_meta( $school_id, '_hs_city', true ) );
		update_post_meta( $cid, '_hs_province', (string) get_post_meta( $school_id, '_hs_province', true ) );
		update_post_meta( $cid, '_hs_school_address', (string) get_post_meta( $school_id, '_hs_address', true ) );
		$mgr = get_userdata( $manager_id );
		update_post_meta( $cid, '_hs_manager_name', $mgr ? $mgr->display_name : '' );
		update_post_meta( $cid, '_hs_manager_mobile', HSUM_Phone::user_mobile( $manager_id ) );
		update_post_meta( $cid, '_hs_teacher_id', $manager_id );
		update_post_meta( $cid, '_hs_students', array() );
		if ( class_exists( 'HS_Class_Meta' ) ) {
			update_post_meta( $cid, '_hs_invite_code', HS_Class_Meta::generate_invite_code() );
		}
		return (int) $cid;
	}

	/* ------------------------------ پذیرش ------------------------------ */

	public static function accept( $inv, $uid ) {
		global $wpdb;
		if ( ! $inv || 'active' !== $inv['status'] || ! $uid ) {
			return new WP_Error( 'bad', 'دعوت‌نامه معتبر نیست یا قبلاً استفاده شده است.' );
		}
		$u = get_userdata( $uid );
		if ( ! $u || array_intersect( (array) $u->roles, array( 'student' ) ) ) {
			return new WP_Error( 'role', 'این حساب نمی‌تواند عضو کادر مدرسه شود.' );
		}
		$sid = (int) $inv['school_id'];
		$d   = $inv['d'];
		$pos = $d['position'] ?? 'teacher';
		if ( (int) get_post_meta( $sid, '_hs_manager_id', true ) === (int) $uid ) {
			return new WP_Error( 'self', 'شما مدیر همین مدرسه هستید.' );
		}
		// نام کاربر اگر خالی است از اطلاعات مدیر پر می‌شود
		if ( '' === trim( $u->first_name . $u->last_name ) && ! empty( $d['first'] ) ) {
			wp_update_user( array( 'ID' => $uid, 'first_name' => $d['first'], 'last_name' => $d['last'], 'display_name' => trim( $d['first'] . ' ' . $d['last'] ) ) );
		}
		// معلم و معاون در پنل «معلم» کار می‌کنند؛ دعوت مدیر مدرسه همان تأیید است
		if ( ! array_intersect( (array) $u->roles, array( 'teacher', 'administrator', 'school_admin' ) ) ) {
			$u->add_role( 'teacher' );
			if ( class_exists( 'HSUM_Membership' ) && in_array( HSUM_Membership::PENDING_ROLE, (array) $u->roles, true ) && 'teacher' === ( HSUM_Membership::get( $uid )['role'] ?? '' ) ) {
				$u->remove_role( HSUM_Membership::PENDING_ROLE );
			}
		}
		if ( class_exists( 'HS_SO_Store' ) && ! HS_SO_Store::staff_row_by_user( $uid ) ) {
			HS_SO_Store::add_staff( $sid, $uid, $pos, '', 0 === strpos( $pos, 'deputy_' ) ? 1 : 0 );
		}
		if ( 'teacher' === $pos ) {
			$t = HSUM_DB::t( 'school_members' );
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE school_id = %d AND user_id = %d", $sid, $uid ) ) ) { // phpcs:ignore
				$wpdb->insert( $t, array( 'school_id' => $sid, 'user_id' => $uid, 'role' => 'teacher', 'status' => 'active', 'invite_id' => (int) $inv['id'], 'created_at' => HSUM_DB::now() ) );
			}
			if ( ! empty( $d['class_id'] ) && 'hs_class' === get_post_type( $d['class_id'] ) ) {
				update_post_meta( (int) $d['class_id'], '_hs_teacher_id', $uid );
				wp_update_post( array( 'ID' => (int) $d['class_id'], 'post_author' => $uid ) );
			}
			$subj = trim( ( $d['subject'] ?? '' ) . ' ' . ( $d['class'] ?? '' ) . ( ! empty( $d['grade'] ) ? ' — ' . $d['grade'] : '' ) );
			if ( $subj ) {
				update_user_meta( $uid, '_hs_subject', $subj );
			}
			HSUM_Invites::attach_teacher_classes( $uid, $sid );
		}
		$wpdb->update( self::t(), array( 'status' => 'accepted', 'user_id' => $uid, 'uses' => 1 ), array( 'id' => (int) $inv['id'] ) );
		update_user_meta( $uid, '_hs_school_name', get_the_title( $sid ) );
		update_user_meta( $uid, '_hsum_onboarded', time() ); // اطلاعات را مدیر داده است
		HSUM_Audit::log( 'school_member', $uid, array( 'school' => $sid, 'invite' => (int) $inv['id'], 'position' => $pos ) );
		return true;
	}

	/** ورود/ثبت‌نام با شماره‌ی تأییدشده */
	public static function on_mobile_known( $uid, $mobile ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE type = %s AND mobile = %s AND status = 'active'", self::TYPE, $mobile ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			self::accept( self::decode( $r ), $uid );
		}
	}

	/** /join/T-XXXXXX */
	public static function join_route() {
		$code = strtoupper( (string) get_query_var( 'hsum_join' ) );
		if ( 0 !== strpos( $code, 'T-' ) ) {
			return;
		}
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE type = %s AND code = %s', self::TYPE, $code ), ARRAY_A ); // phpcs:ignore
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( add_query_arg( 'role', 'teacher', wp_login_url( home_url( '/join/' . $code . '/' ) ) ) );
			exit;
		}
		$res = $r ? self::accept( self::decode( $r ), get_current_user_id() ) : new WP_Error( 'code', 'کد دعوت معتبر نیست.' );
		if ( is_wp_error( $res ) && $r && 'accepted' === $r['status'] && (int) $r['user_id'] === get_current_user_id() ) {
			$res = true;
		}
		$q = is_wp_error( $res ) ? array( 'hsum_err' => rawurlencode( $res->get_error_message() ) ) : array( 'hsum_msg' => rawurlencode( 'به کادر مدرسه «' . get_the_title( (int) $r['school_id'] ) . '» پیوستید.' ) );
		wp_safe_redirect( add_query_arg( $q, is_wp_error( $res ) ? HSUM_Front::page_url() : home_url( '/panel/' ) ) );
		exit;
	}

	/* ------------------------------ فرم ------------------------------ */

	/** سطح مدرسه: ابتدایی/پیش‌دبستانی ← فقط کلاس و پایه؛ متوسطه ← درس هم */
	public static function level( $school_id ) {
		return $school_id ? (string) get_post_meta( $school_id, '_hs_level', true ) : '';
	}

	private static function row_html( $i, $r = array() ) {
		$d    = $r['d'] ?? array();
		// داده‌ی نسخه‌ی قبل (mode/title)
		if ( ! isset( $d['class'] ) && ! empty( $d['title'] ) ) {
			if ( 'class' === ( $d['mode'] ?? '' ) ) {
				$d['class'] = $d['title'];
			} else {
				$d['subject'] = $d['title'];
			}
		}
		$pos  = $d['position'] ?? 'teacher';
		$f    = 'staff[' . $i . ']';
		$v    = function ( $k ) use ( $d ) { return esc_attr( $d[ $k ] ?? '' ); };
		$acc  = 'accepted' === ( $r['status'] ?? '' );
		$po   = '';
		foreach ( self::positions() as $k => $l ) {
			$po .= '<option value="' . esc_attr( $k ) . '"' . selected( $pos, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$go = '<option value="">پایه…</option>';
		foreach ( self::grades() as $g ) {
			$go .= '<option' . selected( $d['grade'] ?? '', $g, false ) . '>' . esc_html( $g ) . '</option>';
		}
		$h  = '<div class="hsum-st-row" data-pos="' . esc_attr( $pos ) . '">';
		$h .= '<input type="hidden" name="' . $f . '[id]" value="' . (int) ( $r['id'] ?? 0 ) . '">';
		$h .= '<div class="hsum-st-l1">';
		$h .= '<select name="' . $f . '[position]" class="hsum-st-pos" aria-label="سمت">' . $po . '</select>';
		$h .= '<input name="' . $f . '[mobile]" class="hsum-st-mob" dir="ltr" inputmode="tel" maxlength="13" value="' . esc_attr( $r['mobile'] ?? '' ) . '" placeholder="موبایل 09…" aria-label="موبایل"' . ( $acc ? ' readonly' : '' ) . '>';
		$h .= '</div><div class="hsum-st-l2">';
		$h .= '<input name="' . $f . '[last]" value="' . $v( 'last' ) . '" placeholder="نام خانوادگی" aria-label="نام خانوادگی">';
		$h .= '<input name="' . $f . '[first]" value="' . $v( 'first' ) . '" placeholder="نام" aria-label="نام">';
		$h .= '</div><div class="hsum-st-teach">';
		$h .= '<input name="' . $f . '[subject]" class="hsum-st-subj" value="' . $v( 'subject' ) . '" placeholder="درس، مثلاً ریاضی" aria-label="درس">';
		$h .= '<input name="' . $f . '[class]" value="' . $v( 'class' ) . '" placeholder="کلاس، مثلاً الف یا ۱۰۲" aria-label="کلاس">';
		$h .= '<select name="' . $f . '[grade]" aria-label="پایه">' . $go . '</select>';
		if ( empty( $d['class_id'] ) ) {
			$h .= '<label class="hsum-st-mk"><input type="checkbox" name="' . $f . '[make_class]" value="1"> کلاسش را هم بساز</label>';
		} else {
			$h .= '<span class="hsum-st-mk ok">🏫 کلاس ساخته شد</span>';
		}
		$h .= '</div><div class="hsum-st-act">';
		if ( ! empty( $r['code'] ) ) {
			$url = HSUM_Invites::link_url( $r['code'] );
			$h  .= $acc ? '<span class="hsum-st-state ok">✅ عضو شد</span>' : '<span class="hsum-st-state">⏳ دعوت شده</span>';
			$h  .= '<code dir="ltr">' . esc_html( $r['code'] ) . '</code>';
			if ( ! $acc ) {
				$h .= '<button type="button" class="hsum-st-qr" data-url="' . esc_attr( $url ) . '" data-code="' . esc_attr( $r['code'] ) . '" data-name="' . esc_attr( $r['name'] ) . '">▦ QR</button>';
				$h .= '<button type="button" class="hsum-st-sms" data-id="' . (int) $r['id'] . '">📱 ' . ( empty( $d['sms_at'] ) ? 'پیامک' : 'ارسال دوباره' ) . '</button>';
			}
		} else {
			$h .= '<span class="hsum-st-state new">پس از ذخیره، کد دعوت و QR ساخته می‌شود</span>';
		}
		$h .= '<button type="button" class="hsum-st-del" aria-label="حذف ردیف" title="حذف">×</button>';
		$h .= '</div></div>';
		return $h;
	}

	/** بخش کادر (درون فرم دیگری یا مستقل) */
	public static function section( $school_id ) {
		wp_enqueue_script( 'hsum-staff' );
		$rows = self::rows( $school_id );
		$lvl  = self::level( $school_id );
		$h    = '<div class="hsum-staff" data-next="' . ( count( $rows ) + 1 ) . '" data-level="' . esc_attr( $lvl ? $lvl : 'primary' ) . '">';
		$h   .= '<div class="hsum-st-list">';
		foreach ( $rows as $i => $r ) {
			$h .= self::row_html( $i, $r );
		}
		if ( ! $rows ) {
			$h .= self::row_html( 0 );
		}
		$h .= '</div>';
		$h .= '<template class="hsum-st-tpl">' . self::row_html( '__N__' ) . '</template>';
		$h .= '<div class="hsum-st-tools"><button type="button" class="hsum-st-add">＋ افزودن نفر جدید</button>';
		$h .= '<button type="button" class="hsum-st-sample">⬇️ دانلود نمونه فایل اکسل</button>';
		$h .= '<label class="hsum-st-upload">📤 بارگذاری فایل اکسل<input type="file" accept=".xlsx,.csv,.txt" hidden></label>';
		$h .= '</div>';
		$h .= '<p class="hsum-onb-hint hsum-st-lvl-hint">در <b>ابتدایی</b> معلم برای همه‌ی درس‌های یک کلاس ثابت است، پس فقط کلاس و پایه پرسیده می‌شود؛ در <b>متوسطه</b> درس هم پرسیده می‌شود. برای هر نفر کد دعوت و کیوآر کد اختصاصی ساخته می‌شود؛ وقتی با همین شماره وارد سایت شود یا لینک دعوت را باز کند، خودکار به کادر مدرسه اضافه می‌شود. معاونان کلاس‌ها را فقط می‌بینند.</p>';
		$h .= '</div>';
		return $h;
	}

	/** خواندن فایل اکسل/CSV کادر (فقط خواندن؛ چیزی ذخیره نمی‌شود) */
	public static function ajax_import() {
		check_ajax_referer( 'hsum_staff', 'nonce' );
		if ( ! is_user_logged_in() || empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'فایلی دریافت نشد.' ) );
		}
		$f   = $_FILES['file']; // phpcs:ignore
		$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
		if ( (int) $f['size'] > 2 * MB_IN_BYTES || ! in_array( $ext, array( 'xlsx', 'csv', 'txt' ), true ) ) {
			wp_send_json_error( array( 'message' => 'فقط فایل xlsx یا csv تا ۲ مگابایت.' ) );
		}
		if ( ! class_exists( 'HS_Student_Import' ) ) {
			wp_send_json_error( array( 'message' => 'افزونه‌ی مدیریت کلاس لازم است.' ) );
		}
		$rows = 'xlsx' === $ext ? HS_Student_Import::parse_xlsx( $f['tmp_name'] ) : HS_Student_Import::parse_csv( $f['tmp_name'] );
		if ( is_wp_error( $rows ) ) {
			wp_send_json_error( array( 'message' => $rows->get_error_message() ) );
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			$row = array_map( function ( $c ) { return sanitize_text_field( (string) $c ); }, array_slice( array_values( (array) $row ), 0, 7 ) );
			if ( implode( '', $row ) === '' || false !== mb_strpos( implode( ' ', $row ), 'نام خانوادگی' ) ) {
				continue; // سطر خالی یا سرستون
			}
			$out[] = $row;
			if ( count( $out ) >= 300 ) {
				break;
			}
		}
		wp_send_json_success( array( 'rows' => $out ) );
	}

	/** فرم مستقل در پنل مدیر (تب «معاونین و کادر») */
	public static function panel( $school_id ) {
		$msg = '';
		if ( isset( $_GET['hsum_staff'] ) ) { // phpcs:ignore
			$msg = '<div class="hsum-note ok">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hsum_staff'] ) ) ) . '</div>'; // phpcs:ignore
		}
		if ( isset( $_GET['hsum_err'] ) ) { // phpcs:ignore
			$msg .= '<div class="hsum-note err">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hsum_err'] ) ) ) . '</div>'; // phpcs:ignore
		}
		wp_enqueue_style( 'hsum', HSUM_URL . 'assets/front.css', array(), HSUM_VERSION );
		return '<section class="hs-card hsum" dir="rtl"><h3>👥 معاونان و معلمان</h3>' . $msg
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'hsum_staff_save', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="hsum_staff_save"><input type="hidden" name="back" value="' . esc_url( add_query_arg( array() ) ) . '">'
			. self::section( $school_id )
			. '<button class="hsum-btn hsum-onb-go">ذخیره‌ی کادر</button></form></section>';
	}

	public static function handle_save() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'hsum_staff_save' );
		$uid  = get_current_user_id();
		$sid  = class_exists( 'HS_SO_Store' ) ? HS_SO_Store::manager_school_id( $uid ) : 0;
		$back = esc_url_raw( wp_unslash( $_POST['back'] ?? home_url( '/panel/' ) ) );
		$back = remove_query_arg( array( 'hsum_staff', 'hsum_err' ), $back );
		if ( ! $sid ) {
			wp_safe_redirect( add_query_arg( 'hsum_err', rawurlencode( 'اول اطلاعات مدرسه را ثبت کنید.' ), $back ) );
			exit;
		}
		$res = self::sync( $sid, wp_unslash( $_POST['staff'] ?? array() ), $uid ); // phpcs:ignore
		$q   = array( 'hsum_staff' => rawurlencode( 'کادر ذخیره شد' . ( $res['added'] ? '؛ ' . $res['added'] . ' دعوت تازه ساخته شد.' : '.' ) ) );
		if ( $res['errors'] ) {
			$q['hsum_err'] = rawurlencode( implode( ' ', $res['errors'] ) );
		}
		wp_safe_redirect( add_query_arg( $q, $back ) );
		exit;
	}

	public static function ajax_sms() {
		check_ajax_referer( 'hsum_staff', 'nonce' );
		$inv = self::get( absint( $_POST['id'] ?? 0 ) );
		$uid = get_current_user_id();
		if ( ! $inv || ! class_exists( 'HS_SO_Store' ) || ! HS_SO_Store::is_manager( (int) $inv['school_id'], $uid ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) );
		}
		if ( 'active' !== $inv['status'] ) {
			wp_send_json_error( array( 'message' => 'این نفر قبلاً عضو شده است.' ) );
		}
		if ( ! function_exists( 'hsma_send_sms' ) ) {
			wp_send_json_error( array( 'message' => 'سامانه‌ی پیامک فعال نیست.' ) );
		}
		$last = (int) get_transient( 'hsum_stsms_' . $inv['id'] );
		if ( $last ) {
			wp_send_json_error( array( 'message' => 'پیامک چند لحظه پیش فرستاده شد؛ کمی بعد دوباره امتحان کنید.' ) );
		}
		$pos = self::positions()[ $inv['d']['position'] ?? 'teacher' ] ?? 'معلم';
		$msg = sprintf( '%s عزیز، شما به‌عنوان %s مدرسه‌ی «%s» در همشاگردی آنلاین معرفی شدید. ورود با همین شماره یا لینک: %s کد: %s', $inv['name'], $pos, get_the_title( (int) $inv['school_id'] ), HSUM_Invites::link_url( $inv['code'] ), $inv['code'] );
		$r   = hsma_send_sms( $inv['mobile'], $msg );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => 'ارسال نشد: ' . $r->get_error_message() ) );
		}
		set_transient( 'hsum_stsms_' . $inv['id'], 1, 2 * MINUTE_IN_SECONDS );
		global $wpdb;
		$inv['d']['sms_at'] = time();
		$wpdb->update( self::t(), array( 'data' => wp_json_encode( $inv['d'], JSON_UNESCAPED_UNICODE ) ), array( 'id' => (int) $inv['id'] ) );
		wp_send_json_success( array( 'message' => 'پیامک دعوت فرستاده شد ✅' ) );
	}

	/** دکمه‌ی «بارگذاری عکس QR» کنار هر فیلد کد دعوت (والدین) */
	public static function qr_upload_boot() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		wp_enqueue_script( 'hsum-staff' );
	}
}
