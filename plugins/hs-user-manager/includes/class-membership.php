<?php
/**
 * وضعیت عضویت: pending | active | rejected | suspended
 *
 * - معلم و مدیر مدرسه‌ای که خودشان ثبت‌نام می‌کنند، تا تأیید مدیر کل نقش «در انتظار تأیید» (hs_pending) دارند؛
 *   نقش درخواستی در جدول عضویت نگه داشته می‌شود و با تأیید روی حساب اعمال می‌شود.
 * - حساب تعلیق‌شده در هیچ مسیری (رمز، کد پیامکی، کوکی قبلی) وارد نمی‌شود.
 * - تغییر نقش فقط توسط مدیر کل و با ثبت سابقه.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Membership {

	const PENDING_ROLE = 'hs_pending';

	public static function statuses() {
		return array(
			'pending'   => 'در انتظار تأیید',
			'active'    => 'فعال',
			'rejected'  => 'رد شده',
			'suspended' => 'تعلیق',
		);
	}

	public static function roles() {
		return array(
			'teacher'      => 'معلم',
			'school_admin' => 'مدیر مدرسه',
			'student'      => 'دانش‌آموز',
			'parent'       => 'والدین',
		);
	}

	public static function init() {
		if ( ! get_role( self::PENDING_ROLE ) ) {
			add_role( self::PENDING_ROLE, 'در انتظار تأیید', array( 'read' => true ) );
		}
		add_action( 'user_register', array( __CLASS__, 'on_register' ), 5 );
		add_filter( 'determine_current_user', array( __CLASS__, 'drop_suspended' ), 99 );
		add_filter( 'authenticate', array( __CLASS__, 'block_login' ), 99 );
		add_filter( 'hsma_can_login', array( __CLASS__, 'can_login' ), 10, 2 );
		add_filter( 'hsma_register_notes', array( __CLASS__, 'register_notes' ), 10, 3 );
		add_action( 'wp_footer', array( __CLASS__, 'pending_banner' ) );
		add_action( 'set_user_role', array( __CLASS__, 'on_role_set' ), 10, 3 );
	}

	/** حافظه‌ی موقت همین درخواست (با هر تغییر پاک می‌شود) */
	private static $cache = array();

	public static function get( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( ! array_key_exists( $user_id, self::$cache ) ) {
			self::$cache[ $user_id ] = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HSUM_DB::t( 'membership' ) . ' WHERE user_id = %d', $user_id ), ARRAY_A ); // phpcs:ignore
		}
		return self::$cache[ $user_id ] ? self::$cache[ $user_id ] : array( 'user_id' => $user_id, 'role' => '', 'status' => 'active', 'reason' => '' );
	}

	public static function status( $user_id ) {
		return self::get( $user_id )['status'];
	}

	private static function upsert( $user_id, $data ) {
		global $wpdb;
		unset( self::$cache[ (int) $user_id ] );
		$t   = HSUM_DB::t( 'membership' );
		$has = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $t WHERE user_id = %d", $user_id ) ); // phpcs:ignore
		if ( $has ) {
			$wpdb->update( $t, $data, array( 'user_id' => $user_id ) );
		} else {
			$wpdb->insert( $t, array_merge( array( 'user_id' => $user_id, 'created_at' => HSUM_DB::now() ), $data ) );
		}
	}

	/** ثبت‌نام: نقش‌های نیازمند تأیید ← در انتظار */
	public static function on_register( $user_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return;
		}
		$roles = array_intersect( (array) $u->roles, array_keys( self::roles() ) );
		$role  = $roles ? reset( $roles ) : '';
		$needs = $role && in_array( $role, (array) hsum_opt( 'approval_roles' ), true );
		// مدیر سایت یا مدیر مدرسه که حساب می‌سازد ← فعال
		$by_staff = current_user_can( 'create_users' ) || current_user_can( 'hs_supervise' );
		if ( $needs && ! $by_staff ) {
			self::upsert( $user_id, array( 'role' => $role, 'status' => 'pending' ) );
			$u->set_role( self::PENDING_ROLE );
			HSUM_Audit::log( 'status', $user_id, array( 'to' => 'pending', 'role' => $role ) );
			do_action( 'hsum_pending', $user_id, $role );
		} else {
			self::upsert( $user_id, array( 'role' => $role, 'status' => 'active' ) );
		}
	}

	/** نقش وردپرس توسط مدیر از جای دیگری عوض شد ← جدول همگام شود */
	public static function on_role_set( $user_id, $role, $old ) {
		if ( self::PENDING_ROLE === $role || ! isset( self::roles()[ $role ] ) ) {
			return;
		}
		$m = self::get( $user_id );
		if ( $m['role'] !== $role && ! empty( $m['created_at'] ) ) {
			self::upsert( $user_id, array( 'role' => $role ) );
		}
	}

	/**
	 * تغییر وضعیت (فقط مدیر کل)
	 * @return true|WP_Error
	 */
	public static function set_status( $user_id, $status, $reason = '' ) {
		if ( ! current_user_can( 'promote_users' ) ) {
			return new WP_Error( 'cap', 'فقط مدیر کل سایت می‌تواند وضعیت عضویت را تغییر دهد.' );
		}
		if ( ! isset( self::statuses()[ $status ] ) ) {
			return new WP_Error( 'status', 'وضعیت نامعتبر.' );
		}
		$u = get_userdata( $user_id );
		if ( ! $u || user_can( $u, 'manage_options' ) ) {
			return new WP_Error( 'user', 'این کاربر قابل تغییر نیست.' );
		}
		$m    = self::get( $user_id );
		$role = $m['role'] ? $m['role'] : self::wp_role( $u );
		self::upsert( $user_id, array(
			'role'        => $role,
			'status'      => $status,
			'reason'      => sanitize_textarea_field( $reason ),
			'reviewed_by' => get_current_user_id(),
			'reviewed_at' => HSUM_DB::now(),
		) );
		if ( 'active' === $status && $role ) {
			// نقش «در انتظار» برداشته و نقش درخواستی افزوده می‌شود (نقش‌های دیگر کاربر، مثلاً والد، می‌ماند)
			$u->remove_role( self::PENDING_ROLE );
			$u->add_role( $role );
		} elseif ( in_array( $status, array( 'pending', 'rejected' ), true ) && $role ) {
			$u->remove_role( $role );
			$u->add_role( self::PENDING_ROLE );
		}
		if ( 'suspended' === $status && class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}
		HSUM_Audit::log( 'status', $user_id, array( 'from' => $m['status'], 'to' => $status, 'role' => $role, 'reason' => $reason ) );
		self::notify( $user_id, $status, $role, $reason );
		do_action( 'hsum_status_changed', $user_id, $status, $m['status'] );
		return true;
	}

	/** درخواست نقش جدید برای کاربر موجود (مثلاً والدی که معلم هم هست) ← در انتظار تأیید مدیر کل */
	public static function request_role( $user_id, $role ) {
		$u = get_userdata( $user_id );
		if ( ! $u || ! isset( self::roles()[ $role ] ) ) {
			return;
		}
		self::upsert( $user_id, array( 'role' => $role, 'status' => 'pending' ) );
		$u->add_role( self::PENDING_ROLE );
		HSUM_Audit::log( 'status', $user_id, array( 'to' => 'pending', 'role' => $role ) );
		do_action( 'hsum_pending', $user_id, $role );
	}

	/** تغییر نقش (فقط مدیر کل، با سابقه) */
	public static function change_role( $user_id, $role ) {
		if ( ! current_user_can( 'promote_users' ) ) {
			return new WP_Error( 'cap', 'فقط مدیر کل سایت می‌تواند نقش را تغییر دهد.' );
		}
		if ( ! isset( self::roles()[ $role ] ) ) {
			return new WP_Error( 'role', 'نقش نامعتبر.' );
		}
		$u = get_userdata( $user_id );
		if ( ! $u || user_can( $u, 'manage_options' ) ) {
			return new WP_Error( 'user', 'این کاربر قابل تغییر نیست.' );
		}
		$m    = self::get( $user_id );
		$from = $m['role'] ? $m['role'] : self::wp_role( $u );
		self::upsert( $user_id, array( 'role' => $role ) );
		if ( 'active' === $m['status'] ) {
			$u->set_role( $role );
		}
		HSUM_Audit::log( 'role', $user_id, array( 'from' => $from, 'to' => $role ) );
		return true;
	}

	public static function wp_role( $u ) {
		$r = array_intersect( (array) $u->roles, array_keys( self::roles() ) );
		return $r ? reset( $r ) : '';
	}

	/* ---------- تعلیق: هیچ راه ورودی باز نباشد ---------- */

	public static function drop_suspended( $user_id ) {
		if ( $user_id && 'suspended' === self::status( $user_id ) ) {
			return 0;
		}
		return $user_id;
	}

	public static function block_login( $user ) {
		if ( $user instanceof WP_User && 'suspended' === self::status( $user->ID ) ) {
			return new WP_Error( 'hsum_suspended', 'حساب شما تعلیق شده است. با مدیر سایت تماس بگیرید.' );
		}
		return $user;
	}

	public static function can_login( $ok, $user ) {
		if ( $user instanceof WP_User && 'suspended' === self::status( $user->ID ) ) {
			return new WP_Error( 'hsum_suspended', 'حساب شما تعلیق شده است. با مدیر سایت تماس بگیرید.' );
		}
		return $ok;
	}

	/** پیام پایان ثبت‌نام متناسب با نقش */
	public static function register_notes( $notes, $user_id, $role ) {
		if ( 'pending' === self::status( $user_id ) ) {
			$notes[] = 'حساب ' . ( self::roles()[ $role ] ?? '' ) . ' شما پس از بررسی و تأیید مدیر سامانه فعال می‌شود؛ نتیجه با پیامک اطلاع داده می‌شود.';
		}
		return $notes;
	}

	public static function pending_banner() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$m = self::get( get_current_user_id() );
		if ( ! in_array( $m['status'], array( 'pending', 'rejected' ), true ) ) {
			return;
		}
		$role = self::roles()[ $m['role'] ] ?? '';
		$txt  = 'pending' === $m['status']
			? 'درخواست عضویت شما به‌عنوان «' . $role . '» در انتظار تأیید مدیر سامانه است. پس از تأیید، امکانات این بخش فعال می‌شود.'
			: 'درخواست عضویت شما به‌عنوان «' . $role . '» تأیید نشد.' . ( $m['reason'] ? ' دلیل: ' . $m['reason'] : '' );
		echo '<div dir="rtl" style="position:fixed;bottom:16px;right:16px;left:16px;max-width:560px;margin:0 auto;z-index:99999;background:#fff8e1;border:1px solid #f0c36d;color:#5c4400;padding:12px 16px;border-radius:12px;font:14px/1.9 Vazirmatn,Tahoma,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.12)">⏳ ' . esc_html( $txt ) . '</div>';
	}

	private static function notify( $user_id, $status, $role, $reason ) {
		if ( ! function_exists( 'hsma_send_sms' ) || ! in_array( $status, array( 'active', 'rejected', 'suspended' ), true ) ) {
			return;
		}
		$m = HSUM_Phone::user_mobile( $user_id );
		if ( ! $m ) {
			return;
		}
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$r    = self::roles()[ $role ] ?? '';
		$txt  = array(
			'active'    => "حساب {$r} شما در {$site} تأیید و فعال شد. " . home_url( '/panel/' ),
			'rejected'  => "درخواست عضویت {$r} شما در {$site} تأیید نشد." . ( $reason ? " دلیل: {$reason}" : '' ),
			'suspended' => "حساب شما در {$site} تعلیق شد. برای پیگیری با مدیر سایت تماس بگیرید.",
		)[ $status ];
		hsma_send_sms( $m, $txt );
	}

	/** فهرست برای پنل مدیریت */
	public static function query( $args = array() ) {
		global $wpdb;
		$t      = HSUM_DB::t( 'membership' );
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'm.status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['role'] ) ) {
			$where[]  = 'm.role = %s';
			$params[] = $args['role'];
		}
		if ( ! empty( $args['q'] ) ) {
			$q        = '%' . $wpdb->esc_like( $args['q'] ) . '%';
			$where[]  = '(u.display_name LIKE %s OR u.user_login LIKE %s OR EXISTS (SELECT 1 FROM ' . $wpdb->usermeta . " um WHERE um.user_id = u.ID AND um.meta_key = '_hs_mobile' AND um.meta_value LIKE %s))";
			$params[] = $q;
			$params[] = $q;
			$params[] = '%' . $wpdb->esc_like( HSUM_Phone::norm( $args['q'] ) ?: $args['q'] ) . '%';
		}
		$sql = "SELECT m.*, u.display_name, u.user_registered FROM $t m JOIN {$wpdb->users} u ON u.ID = m.user_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY m.created_at DESC LIMIT 300';
		return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
	}

	public static function count_pending() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . HSUM_DB::t( 'membership' ) . " WHERE status = 'pending'" ); // phpcs:ignore
	}

	/** کاربران قدیمی (پیش از نصب) را با وضعیت فعال وارد جدول می‌کند */
	public static function backfill() {
		global $wpdb;
		$t   = HSUM_DB::t( 'membership' );
		$ids = get_users( array( 'role__in' => array_keys( self::roles() ), 'fields' => 'ID', 'number' => 5000 ) );
		$n   = 0;
		foreach ( $ids as $id ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $t WHERE user_id = %d", $id ) ) ) { // phpcs:ignore
				$u = get_userdata( $id );
				$wpdb->insert( $t, array( 'user_id' => $id, 'role' => self::wp_role( $u ), 'status' => 'active', 'created_at' => $u->user_registered ) );
				$n++;
			}
		}
		return $n;
	}
}
