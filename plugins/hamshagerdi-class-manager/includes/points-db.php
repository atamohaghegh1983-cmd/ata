<?php
/**
 * لایه دیتابیس سیستم امتیاز و رفتارها
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Points_DB {

	const TABLE_BEHAVIORS = 'hs_behaviors';
	const TABLE_POINTS    = 'hs_points';

	public static function behaviors_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_BEHAVIORS;
	}

	public static function points_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_POINTS;
	}

	/**
	 * ایجاد جداول
	 */
	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$beh     = self::behaviors_table();
		$pts     = self::points_table();

		$sql1 = "CREATE TABLE {$beh} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(120) NOT NULL,
			icon varchar(20) DEFAULT '⭐',
			points int(11) NOT NULL DEFAULT 1,
			is_positive tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY is_active (is_active)
		) {$charset};";

		$sql2 = "CREATE TABLE {$pts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned NOT NULL,
			behavior_id bigint(20) unsigned DEFAULT NULL,
			points int(11) NOT NULL DEFAULT 1,
			note text DEFAULT NULL,
			awarded_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY class_student (class_id, student_id),
			KEY class_date (class_id, created_at),
			KEY student_id (student_id),
			KEY behavior_id (behavior_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );

		// رفتارهای پیش‌فرض سراسری (class_id = 0)
		self::seed_default_behaviors();
	}

	/**
	 * رفتارهای پیش‌فرض مثبت و منفی
	 */
	public static function seed_default_behaviors() {
		global $wpdb;
		$table = self::behaviors_table();

		$exists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE class_id = 0" );
		if ( $exists > 0 ) {
			return;
		}

		$defaults = array(
			array( 'تلاش و پشتکار',     '💪',  1, 1, 10 ),
			array( 'کار تیمی',           '🤝',  1, 1, 20 ),
			array( 'کمک به دیگران',      '🤗',  1, 1, 30 ),
			array( 'مسئولیت‌پذیری',      '✅',  1, 1, 40 ),
			array( 'احترام و ادب',       '🙏',  1, 1, 50 ),
			array( 'خلاقیت',             '💡',  1, 1, 60 ),
			array( 'دقت و تمرکز',        '🎯',  1, 1, 70 ),
			array( 'مشارکت در کلاس',     '🗣️',  1, 1, 80 ),
			array( 'نیاز به تذکر',       '⚠️', -1, 0, 90 ),
			array( 'اختلال در کلاس',     '🚫', -1, 0, 100 ),
		);

		foreach ( $defaults as $d ) {
			$wpdb->insert(
				$table,
				array(
					'class_id'    => 0,
					'name'        => $d[0],
					'icon'        => $d[1],
					'points'      => $d[2],
					'is_positive' => $d[3],
					'sort_order'  => $d[4],
					'is_active'   => 1,
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * لیست رفتارهای فعال (سراسری + اختصاصی کلاس)
	 */
	public static function get_behaviors( $class_id = 0, $only_active = true ) {
		global $wpdb;
		$table = self::behaviors_table();

		$where = $wpdb->prepare(
			'(class_id = 0 OR class_id = %d)',
			absint( $class_id )
		);
		if ( $only_active ) {
			$where .= ' AND is_active = 1';
		}

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY is_positive DESC, sort_order ASC, id ASC",
			ARRAY_A
		);
	}

	/**
	 * یک رفتار
	 */
	public static function get_behavior( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::behaviors_table() . ' WHERE id = %d', absint( $id ) ),
			ARRAY_A
		);
	}

	/**
	 * افزودن رفتار سفارشی برای کلاس
	 */
	public static function add_behavior( $class_id, $name, $icon = '⭐', $points = 1, $is_positive = 1 ) {
		global $wpdb;
		$wpdb->insert(
			self::behaviors_table(),
			array(
				'class_id'    => absint( $class_id ),
				'name'        => sanitize_text_field( $name ),
				'icon'        => sanitize_text_field( $icon ),
				'points'      => (int) $points,
				'is_positive' => $is_positive ? 1 : 0,
				'sort_order'  => 200,
				'is_active'   => 1,
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * ثبت امتیاز برای یک دانش‌آموز
	 */
	public static function award( $class_id, $student_id, $behavior_id, $points = null, $note = '', $awarded_by = null ) {
		global $wpdb;

		$class_id    = absint( $class_id );
		$student_id  = absint( $student_id );
		$behavior_id = absint( $behavior_id );
		$awarded_by  = $awarded_by ? absint( $awarded_by ) : get_current_user_id();
		$note        = sanitize_textarea_field( $note );

		if ( $points === null && $behavior_id ) {
			$beh = self::get_behavior( $behavior_id );
			$points = $beh ? (int) $beh['points'] : 1;
		}
		$points = (int) $points;

		$wpdb->insert(
			self::points_table(),
			array(
				'class_id'    => $class_id,
				'student_id'  => $student_id,
				'behavior_id' => $behavior_id ?: null,
				'points'      => $points,
				'note'        => $note,
				'awarded_by'  => $awarded_by,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * ثبت امتیاز برای چند دانش‌آموز (یک رفتار)
	 */
	public static function award_batch( $class_id, $student_ids, $behavior_id, $note = '' ) {
		$count = 0;
		$beh   = self::get_behavior( $behavior_id );
		$pts   = $beh ? (int) $beh['points'] : 1;
		foreach ( (array) $student_ids as $sid ) {
			self::award( $class_id, $sid, $behavior_id, $pts, $note );
			$count++;
		}
		return $count;
	}

	/**
	 * مجموع امتیاز یک دانش‌آموز در کلاس
	 */
	public static function get_student_total( $class_id, $student_id ) {
		global $wpdb;
		$total = $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(points), 0) FROM ' . self::points_table() .
			' WHERE class_id = %d AND student_id = %d',
			absint( $class_id ),
			absint( $student_id )
		) );
		return (int) $total;
	}

	/**
	 * رتبه‌بندی کلاس: [ student_id => total, ... ] مرتب نزولی
	 */
	public static function get_class_leaderboard( $class_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT student_id, SUM(points) as total
			 FROM ' . self::points_table() . '
			 WHERE class_id = %d
			 GROUP BY student_id
			 ORDER BY total DESC',
			absint( $class_id )
		), ARRAY_A );

		$result = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$result[ (int) $row['student_id'] ] = (int) $row['total'];
			}
		}
		return $result;
	}

	/**
	 * تاریخچه اخیر امتیازات یک کلاس
	 */
	public static function get_recent( $class_id, $limit = 20 ) {
		global $wpdb;
		$pts = self::points_table();
		$beh = self::behaviors_table();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, b.name as behavior_name, b.icon as behavior_icon
			 FROM {$pts} p
			 LEFT JOIN {$beh} b ON p.behavior_id = b.id
			 WHERE p.class_id = %d
			 ORDER BY p.created_at DESC
			 LIMIT %d",
			absint( $class_id ),
			absint( $limit )
		), ARRAY_A );
	}

	/**
	 * تاریخچه یک دانش‌آموز
	 */
	public static function get_student_history( $class_id, $student_id, $limit = 30 ) {
		global $wpdb;
		$pts = self::points_table();
		$beh = self::behaviors_table();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, b.name as behavior_name, b.icon as behavior_icon
			 FROM {$pts} p
			 LEFT JOIN {$beh} b ON p.behavior_id = b.id
			 WHERE p.class_id = %d AND p.student_id = %d
			 ORDER BY p.created_at DESC
			 LIMIT %d",
			absint( $class_id ),
			absint( $student_id ),
			absint( $limit )
		), ARRAY_A );
	}

	/**
	 * حذف یک رکورد امتیاز (فقط توسط معلم)
	 */
	public static function delete_point( $point_id ) {
		global $wpdb;
		return $wpdb->delete( self::points_table(), array( 'id' => absint( $point_id ) ), array( '%d' ) );
	}
}
