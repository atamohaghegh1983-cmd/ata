<?php
/**
 * لایه دیتابیس پورتفولیو دانش‌آموز
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Portfolio_DB {

	const TABLE = 'hs_portfolio';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function create_table() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			description text DEFAULT NULL,
			item_type varchar(40) DEFAULT 'work',
			media_url varchar(500) DEFAULT NULL,
			media_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'approved',
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY student_id (student_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function types() {
		return array(
			'work'       => array( 'label' => 'کار کلاسی',     'icon' => '📝' ),
			'art'        => array( 'label' => 'هنری',          'icon' => '🎨' ),
			'project'    => array( 'label' => 'پروژه',         'icon' => '🔬' ),
			'writing'    => array( 'label' => 'نوشتاری',       'icon' => '✍️' ),
			'photo'      => array( 'label' => 'عکس فعالیت',    'icon' => '📷' ),
			'certificate'=> array( 'label' => 'گواهی / تقدیر', 'icon' => '🏆' ),
			'other'      => array( 'label' => 'سایر',          'icon' => '📎' ),
		);
	}

	public static function statuses() {
		return array(
			'pending'  => array( 'label' => 'در انتظار تأیید', 'color' => '#F59E0B' ),
			'approved' => array( 'label' => 'تأییدشده',       'color' => '#10B981' ),
			'rejected' => array( 'label' => 'ردشده',          'color' => '#EF4444' ),
		);
	}

	public static function add( $data ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'class_id'     => absint( $data['class_id'] ),
				'student_id'   => absint( $data['student_id'] ),
				'title'        => sanitize_text_field( $data['title'] ),
				'description'  => sanitize_textarea_field( isset( $data['description'] ) ? $data['description'] : '' ),
				'item_type'    => sanitize_key( isset( $data['item_type'] ) ? $data['item_type'] : 'work' ),
				'media_url'    => isset( $data['media_url'] ) ? esc_url_raw( $data['media_url'] ) : '',
				'media_id'     => isset( $data['media_id'] ) ? absint( $data['media_id'] ) : null,
				'status'       => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'approved',
				'created_by'   => get_current_user_id(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function update_status( $id, $status ) {
		global $wpdb;
		$allowed = array_keys( self::statuses() );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		return $wpdb->update(
			self::table(),
			array( 'status' => $status ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ),
			ARRAY_A
		);
	}

	/**
	 * آیتم‌های یک کلاس
	 */
	public static function get_by_class( $class_id, $args = array() ) {
		global $wpdb;
		$table = self::table();
		$class_id = absint( $class_id );

		$where = $wpdb->prepare( 'class_id = %d', $class_id );
		if ( ! empty( $args['student_id'] ) ) {
			$where .= $wpdb->prepare( ' AND student_id = %d', absint( $args['student_id'] ) );
		}
		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', sanitize_key( $args['status'] ) );
		}
		if ( ! empty( $args['item_type'] ) ) {
			$where .= $wpdb->prepare( ' AND item_type = %s', sanitize_key( $args['item_type'] ) );
		}

		$limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 60;

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT {$limit}",
			ARRAY_A
		);
	}

	public static function count_by_class( $class_id, $status = null ) {
		global $wpdb;
		$table = self::table();
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE class_id = %d", absint( $class_id ) );
		if ( $status ) {
			$sql .= $wpdb->prepare( ' AND status = %s', sanitize_key( $status ) );
		}
		return (int) $wpdb->get_var( $sql );
	}

	public static function count_by_student( $class_id, $student_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::table() . ' WHERE class_id = %d AND student_id = %d AND status = %s',
			absint( $class_id ),
			absint( $student_id ),
			'approved'
		) );
	}
}
