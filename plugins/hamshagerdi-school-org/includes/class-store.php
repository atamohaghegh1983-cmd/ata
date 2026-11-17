<?php
/**
 * ذخیره‌سازی: مدرسه (CPT hs_school)، کادر و وظایف (جدول‌های اختصاصی)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_SO_Store {

	const CPT = 'hs_school';

	public static function register_cpt() {
		register_post_type( self::CPT, array(
			'labels'          => array( 'name' => 'مدارس', 'singular_name' => 'مدرسه' ),
			'public'          => false,
			'show_ui'         => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
		) );
	}

	public static function staff_table() {
		global $wpdb;
		return $wpdb->prefix . 'hs_so_staff';
	}

	public static function tasks_table() {
		global $wpdb;
		return $wpdb->prefix . 'hs_so_tasks';
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$s       = self::staff_table();
		$t       = self::tasks_table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$s} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			school_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			position varchar(30) NOT NULL DEFAULT 'other',
			title varchar(120) DEFAULT '',
			job_desc text DEFAULT NULL,
			can_view tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY school_id (school_id)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			school_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			domain varchar(30) DEFAULT '',
			assignee_id bigint(20) unsigned NOT NULL,
			assigned_by bigint(20) unsigned NOT NULL,
			due_date date DEFAULT NULL,
			priority varchar(10) NOT NULL DEFAULT 'normal',
			status varchar(10) NOT NULL DEFAULT 'todo',
			report text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT NULL,
			done_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY school_id (school_id),
			KEY assignee_id (assignee_id),
			KEY status (status)
		) {$charset};" );
	}

	/* ---------------- ارقام ---------------- */

	public static function en( $s ) {
		return strtr( (string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) );
	}

	public static function fa( $s ) {
		return strtr( (string) $s, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/* ---------------- مدرسه ---------------- */

	public static function is_admin_user( $uid = 0 ) {
		return user_can( $uid ? $uid : get_current_user_id(), 'manage_options' );
	}

	/** مدرسهٔ مدیر (یا مدرسهٔ انتخاب‌شده توسط مدیر سایت) */
	public static function manager_school_id( $uid ) {
		$ids = get_posts( array(
			'post_type' => self::CPT, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1,
			'meta_key' => '_hs_manager_id', 'meta_value' => $uid, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true,
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	/** مدرسهٔ کاربر: مدیر، عضو کادر، یا (برای مدیر سایت) مدرسهٔ انتخابی */
	public static function school_id_for_user( $uid ) {
		if ( self::is_admin_user( $uid ) ) {
			$sel = isset( $_GET['school_id'] ) ? absint( $_GET['school_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			if ( $sel && self::CPT === get_post_type( $sel ) ) {
				return $sel;
			}
			$mine = self::manager_school_id( $uid );
			if ( $mine ) {
				return $mine;
			}
			$all = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC' ) );
			return $all ? (int) $all[0] : 0;
		}
		$m = self::manager_school_id( $uid );
		if ( $m ) {
			return $m;
		}
		$row = self::staff_row_by_user( $uid );
		return $row ? (int) $row['school_id'] : 0;
	}

	public static function get_school( $school_id ) {
		$p = get_post( $school_id );
		if ( ! $p || self::CPT !== $p->post_type ) {
			return null;
		}
		return array(
			'id'          => (int) $p->ID,
			'name'        => $p->post_title,
			'manager_id'  => (int) get_post_meta( $p->ID, '_hs_manager_id', true ),
			'code'        => (string) get_post_meta( $p->ID, '_hs_school_code', true ),
			'province'    => (string) get_post_meta( $p->ID, '_hs_province', true ),
			'city'        => (string) get_post_meta( $p->ID, '_hs_city', true ),
			'address'     => (string) get_post_meta( $p->ID, '_hs_address', true ),
			'postal'      => (string) get_post_meta( $p->ID, '_hs_postal', true ),
			'phone'       => (string) get_post_meta( $p->ID, '_hs_phone', true ),
			'level'       => (string) get_post_meta( $p->ID, '_hs_level', true ),
		);
	}

	public static function levels() {
		return array( '' => '— انتخاب —', 'preschool' => 'پیش‌دبستانی', 'primary' => 'ابتدایی', 'middle' => 'متوسطه اول', 'high' => 'متوسطه دوم' );
	}

	/** اعتبارسنجی و ذخیرهٔ مدرسه؛ WP_Error یا شناسه */
	public static function save_school( $school_id, $d, $manager_id ) {
		$name     = sanitize_text_field( $d['name'] ?? '' );
		$province = sanitize_text_field( $d['province'] ?? '' );
		$city     = sanitize_text_field( $d['city'] ?? '' );
		$address  = sanitize_textarea_field( $d['address'] ?? '' );
		$postal   = preg_replace( '/\D/', '', self::en( $d['postal'] ?? '' ) );
		$code     = preg_replace( '/\D/', '', self::en( $d['code'] ?? '' ) );
		$phone    = preg_replace( '/[^0-9+]/', '', self::en( $d['phone'] ?? '' ) );
		$level    = sanitize_key( $d['level'] ?? '' );

		if ( '' === $name ) {
			return new WP_Error( 'name', 'نام مدرسه را وارد کنید.' );
		}
		$provinces = class_exists( 'HS_Util' ) && method_exists( 'HS_Util', 'provinces' ) ? HS_Util::provinces() : array();
		if ( '' === $province || ( $provinces && ! in_array( $province, $provinces, true ) ) ) {
			return new WP_Error( 'province', 'استان را انتخاب کنید.' );
		}
		if ( '' === $city ) {
			return new WP_Error( 'city', 'شهر را وارد کنید.' );
		}
		if ( strlen( $address ) < 8 ) {
			return new WP_Error( 'address', 'آدرس کامل مدرسه را وارد کنید.' );
		}
		if ( 10 !== strlen( $postal ) ) {
			return new WP_Error( 'postal', 'کد پستی باید ۱۰ رقم باشد.' );
		}
		if ( '' === $code ) {
			return new WP_Error( 'code', 'شماره (کد) مدرسه را وارد کنید.' );
		}
		if ( ! isset( self::levels()[ $level ] ) ) {
			$level = '';
		}

		if ( $school_id ) {
			wp_update_post( array( 'ID' => $school_id, 'post_title' => $name ) );
		} else {
			$school_id = wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => $name, 'post_author' => $manager_id ), true );
			if ( is_wp_error( $school_id ) ) {
				return $school_id;
			}
			update_post_meta( $school_id, '_hs_manager_id', $manager_id );
		}
		update_post_meta( $school_id, '_hs_province', $province );
		update_post_meta( $school_id, '_hs_city', $city );
		update_post_meta( $school_id, '_hs_address', $address );
		update_post_meta( $school_id, '_hs_postal', $postal );
		update_post_meta( $school_id, '_hs_school_code', $code );
		update_post_meta( $school_id, '_hs_phone', $phone );
		update_post_meta( $school_id, '_hs_level', $level );
		do_action( 'hs_so_school_saved', $school_id );
		return (int) $school_id;
	}

	/* ---------------- دسترسی ---------------- */

	public static function is_manager( $school_id, $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		if ( ! $uid || ! $school_id ) {
			return false;
		}
		return self::is_admin_user( $uid ) || (int) get_post_meta( $school_id, '_hs_manager_id', true ) === (int) $uid;
	}

	/** ناظر: مدیر یا عضو کادری که مدیر اجازهٔ مشاهده داده است */
	public static function can_supervise( $school_id, $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		if ( self::is_manager( $school_id, $uid ) ) {
			return true;
		}
		$row = self::staff_row_by_user( $uid );
		return $row && (int) $row['school_id'] === (int) $school_id && (int) $row['can_view'] === 1;
	}

	/* ---------------- کادر ---------------- */

	public static function staff_row_by_user( $uid ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::staff_table() . ' WHERE user_id = %d', $uid ), ARRAY_A ); // phpcs:ignore
	}

	public static function staff_row( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::staff_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function staff_list( $school_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::staff_table() . ' WHERE school_id = %d ORDER BY id ASC', $school_id ), ARRAY_A ); // phpcs:ignore
		$order = array_flip( array_keys( HS_SO_Structure::positions() ) );
		foreach ( $rows as &$r ) {
			$u              = get_userdata( $r['user_id'] );
			$r['name']      = $u ? $u->display_name : 'کاربر حذف‌شده';
			$r['login']     = $u ? $u->user_login : '';
			$r['phone']     = $u ? (string) get_user_meta( $u->ID, '_hs_phone', true ) : '';
			$r['label']     = '' !== trim( (string) $r['title'] ) ? $r['title'] : HS_SO_Structure::position_title( $r['position'] );
			$r['job_final'] = '' !== trim( (string) $r['job_desc'] ) ? $r['job_desc'] : ( HS_SO_Structure::positions()[ $r['position'] ]['duties'] ?? '' );
		}
		unset( $r );
		usort( $rows, function ( $a, $b ) use ( $order ) {
			return ( $order[ $a['position'] ] ?? 99 ) <=> ( $order[ $b['position'] ] ?? 99 ) ?: $a['id'] <=> $b['id'];
		} );
		return $rows;
	}

	public static function staff_user_ids( $school_id, $position = '' ) {
		$ids = array();
		foreach ( self::staff_list( $school_id ) as $r ) {
			if ( '' === $position || $position === $r['position'] ) {
				$ids[] = (int) $r['user_id'];
			}
		}
		// معلمانی که از راه دعوت (افزونه‌ی مدیریت کاربران) به مدرسه پیوسته‌اند
		return array_values( array_unique( array_map( 'intval', (array) apply_filters( 'hs_so_staff_user_ids', $ids, $school_id, $position ) ) ) );
	}

	/** افزودن کاربر به کادر؛ WP_Error یا شناسهٔ ردیف */
	public static function add_staff( $school_id, $user_id, $position, $title = '', $can_view = 0 ) {
		global $wpdb;
		if ( ! isset( HS_SO_Structure::positions()[ $position ] ) ) {
			$position = 'other';
		}
		if ( self::staff_row_by_user( $user_id ) ) {
			return new WP_Error( 'dup', 'این کاربر قبلاً در یک مدرسه ثبت شده است.' );
		}
		if ( (int) get_post_meta( $school_id, '_hs_manager_id', true ) === (int) $user_id ) {
			return new WP_Error( 'self', 'مدیر مدرسه را نمی‌توان به‌عنوان کادر افزود.' );
		}
		$wpdb->insert( self::staff_table(), array(
			'school_id' => $school_id,
			'user_id'   => $user_id,
			'position'  => $position,
			'title'     => sanitize_text_field( $title ),
			'can_view'  => $can_view ? 1 : 0,
		) );
		return (int) $wpdb->insert_id;
	}

	public static function remove_staff( $id ) {
		global $wpdb;
		$row = self::staff_row( $id );
		if ( $row ) {
			$wpdb->delete( self::tasks_table(), array( 'assignee_id' => $row['user_id'], 'school_id' => $row['school_id'] ) );
			$wpdb->delete( self::staff_table(), array( 'id' => $id ) );
		}
	}

	/* ---------------- وظایف ---------------- */

	public static function task( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::tasks_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function tasks( $school_id, $args = array() ) {
		global $wpdb;
		$where = array( $wpdb->prepare( 'school_id = %d', $school_id ) );
		if ( ! empty( $args['assignee'] ) ) {
			$where[] = $wpdb->prepare( 'assignee_id = %d', $args['assignee'] );
		}
		if ( ! empty( $args['status'] ) && isset( HS_SO_Structure::statuses()[ $args['status'] ] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::tasks_table() . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY (status='done') ASC, (due_date IS NULL) ASC, due_date ASC, id DESC LIMIT 300", ARRAY_A ); // phpcs:ignore
		$today = current_time( 'Y-m-d' );
		foreach ( $rows as &$r ) {
			$u                 = get_userdata( $r['assignee_id'] );
			$r['assignee']     = $u ? $u->display_name : '—';
			$r['overdue']      = 'done' !== $r['status'] && $r['due_date'] && $r['due_date'] < $today;
		}
		unset( $r );
		return $rows;
	}

	public static function task_counts( $school_id ) {
		global $wpdb;
		$t     = self::tasks_table();
		$today = current_time( 'Y-m-d' );
		return array(
			'open'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE school_id = %d AND status <> 'done'", $school_id ) ), // phpcs:ignore
			'done'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE school_id = %d AND status = 'done'", $school_id ) ), // phpcs:ignore
			'overdue' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE school_id = %d AND status <> 'done' AND due_date IS NOT NULL AND due_date < %s", $school_id, $today ) ), // phpcs:ignore
		);
	}

	/* ---------------- سازگاری با افزونه‌های برنامه هفتگی و تقویم مناسبتی ---------------- */

	/** حوزه → سمت‌های مسئول (پیش‌فرض) */
	public static function domain_positions( $domain ) {
		$map = array(
			'weekly_plan'  => array( 'deputy_edu', 'teacher' ),
			'occasions'    => array( 'deputy_cult' ),
			'associations' => array( 'deputy_cult' ),
			'attendance'   => array( 'deputy_exec', 'teacher' ),
			'health'       => array( 'health', 'deputy_cult' ),
			'sports'       => array( 'pe', 'deputy_cult' ),
			'assessment'   => array( 'deputy_edu', 'teacher' ),
			'homework'     => array( 'teacher', 'deputy_edu' ),
			'parent_comms' => array( 'teacher', 'deputy_edu', 'deputy_exec' ),
			'reports'      => array( 'deputy_edu', 'deputy_exec' ),
		);
		return isset( $map[ $domain ] ) ? $map[ $domain ] : array();
	}

	/**
	 * true: مجاز · false: عضو کادر ولی سمتش مسئول این حوزه نیست · null: نظری ندارد
	 * (کاربرانی که در کادر ثبت نشده‌اند مسدود نمی‌شوند)
	 */
	public static function user_can_domain( $domain, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		$roles = self::domain_positions( $domain );
		if ( ! $roles ) {
			return null;
		}
		$row = self::staff_row_by_user( $user_id );
		if ( ! $row ) {
			return null;
		}
		return in_array( $row['position'], $roles, true );
	}

	public static function domain_role_labels( $domain ) {
		$labels = array();
		foreach ( self::domain_positions( $domain ) as $p ) {
			$labels[] = HS_SO_Structure::position_title( $p );
		}
		return $labels;
	}

	public static function domain_people_names( $domain ) {
		$sid   = self::school_id_for_user( get_current_user_id() );
		$names = array();
		if ( ! $sid ) {
			return $names;
		}
		$pos = self::domain_positions( $domain );
		foreach ( self::staff_list( $sid ) as $r ) {
			if ( in_array( $r['position'], $pos, true ) ) {
				$names[] = $r['name'];
			}
		}
		return $names;
	}
}
