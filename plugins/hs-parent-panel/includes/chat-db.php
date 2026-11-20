<?php
/**
 * چت گروهی والدین
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Parent_Chat_DB {

	const TABLE = 'hs_parent_chat';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function create_table() {
		global $wpdb;
		$t = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			room varchar(40) NOT NULL DEFAULT 'class',
			sender_id bigint(20) unsigned NOT NULL,
			parent_id bigint(20) unsigned DEFAULT NULL,
			body longtext DEFAULT NULL,
			msg_type varchar(20) NOT NULL DEFAULT 'text',
			media_url text DEFAULT NULL,
			media_id bigint(20) unsigned DEFAULT NULL,
			reply_to bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY room (room),
			KEY sender_id (sender_id),
			KEY reply_to (reply_to),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * ثبت پیام
	 */
	public static function insert( $data ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'class_id'   => absint( $data['class_id'] ?? 0 ),
				'room'       => sanitize_key( $data['room'] ?? 'class' ),
				'sender_id'  => absint( $data['sender_id'] ),
				'parent_id'  => isset( $data['parent_id'] ) ? absint( $data['parent_id'] ) : null,
				'body'       => isset( $data['body'] ) ? wp_kses_post( $data['body'] ) : '',
				'msg_type'   => sanitize_key( $data['msg_type'] ?? 'text' ),
				'media_url'  => isset( $data['media_url'] ) ? esc_url_raw( $data['media_url'] ) : '',
				'media_id'   => isset( $data['media_id'] ) ? absint( $data['media_id'] ) : 0,
				'reply_to'   => isset( $data['reply_to'] ) ? absint( $data['reply_to'] ) : 0,
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			absint( $id )
		), ARRAY_A );
	}

	/**
	 * پیام‌های یک اتاق (کلاس یا global)
	 */
	public static function get_messages( $class_id, $room = 'class', $after_id = 0, $limit = 50 ) {
		global $wpdb;
		$class_id = absint( $class_id );
		$room = sanitize_key( $room );
		$after_id = absint( $after_id );
		$limit = min( 100, max( 1, absint( $limit ) ) );

		if ( $after_id > 0 ) {
			$sql = $wpdb->prepare(
				'SELECT * FROM ' . self::table() . '
				 WHERE class_id = %d AND room = %s AND id > %d AND deleted = 0
				 ORDER BY id ASC LIMIT %d',
				$class_id, $room, $after_id, $limit
			);
		} else {
			// آخرین N پیام، بعد برعکس برای نمایش از قدیم به جدید
			$sql = $wpdb->prepare(
				'SELECT * FROM (
					SELECT * FROM ' . self::table() . '
					WHERE class_id = %d AND room = %s AND deleted = 0
					ORDER BY id DESC LIMIT %d
				) t ORDER BY id ASC',
				$class_id, $room, $limit
			);
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return $rows ? $rows : array();
	}

	/** شناسه‌ی پیام‌های حذف‌شده‌ی اخیر (تا از صفحه‌ی بقیه هم برداشته شود) */
	public static function recent_deleted( $class_id ) {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE class_id = %d AND deleted = 1 ORDER BY id DESC LIMIT 100', absint( $class_id ) ) ) );
	}

	public static function count_unread_approx( $class_id, $since ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::table() . '
			 WHERE class_id = %d AND created_at > %s',
			absint( $class_id ),
			$since
		) );
	}
}
