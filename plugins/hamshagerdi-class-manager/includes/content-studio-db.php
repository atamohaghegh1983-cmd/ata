<?php
/**
 * استودیو محتوا — پروژه‌های بارگذاری
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Content_Studio_DB {

	const TABLE = 'hs_content_projects';

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
			title varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'draft',
			source_type varchar(40) DEFAULT '',
			extracted_text longtext DEFAULT NULL,
			html_content longtext DEFAULT NULL,
			meta_json longtext DEFAULT NULL,
			media_json longtext DEFAULT NULL,
			published_post_id bigint(20) unsigned DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_by (created_by)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function create( $data ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'title'          => sanitize_text_field( $data['title'] ?? 'بدون عنوان' ),
				'status'         => 'draft',
				'source_type'    => sanitize_key( $data['source_type'] ?? '' ),
				'extracted_text' => $data['extracted_text'] ?? '',
				'html_content'   => $data['html_content'] ?? '',
				'meta_json'      => isset( $data['meta'] ) ? wp_json_encode( $data['meta'], JSON_UNESCAPED_UNICODE ) : '{}',
				'media_json'     => isset( $data['media'] ) ? wp_json_encode( $data['media'], JSON_UNESCAPED_UNICODE ) : '[]',
				'created_by'     => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$fields = array();
		$formats = array();

		$map = array(
			'title'             => '%s',
			'status'            => '%s',
			'source_type'       => '%s',
			'extracted_text'    => '%s',
			'html_content'      => '%s',
			'published_post_id' => '%d',
		);

		foreach ( $map as $key => $fmt ) {
			if ( array_key_exists( $key, $data ) ) {
				$fields[ $key ] = $data[ $key ];
				$formats[] = $fmt;
			}
		}
		if ( isset( $data['meta'] ) ) {
			$fields['meta_json'] = wp_json_encode( $data['meta'], JSON_UNESCAPED_UNICODE );
			$formats[] = '%s';
		}
		if ( isset( $data['media'] ) ) {
			$fields['media_json'] = wp_json_encode( $data['media'], JSON_UNESCAPED_UNICODE );
			$formats[] = '%s';
		}
		if ( empty( $fields ) ) {
			return false;
		}

		return $wpdb->update( self::table(), $fields, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			absint( $id )
		), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$row['meta']  = $row['meta_json'] ? json_decode( $row['meta_json'], true ) : array();
		$row['media'] = $row['media_json'] ? json_decode( $row['media_json'], true ) : array();
		if ( ! is_array( $row['meta'] ) ) {
			$row['meta'] = array();
		}
		if ( ! is_array( $row['media'] ) ) {
			$row['media'] = array();
		}
		return $row;
	}

	public static function list_projects( $args = array() ) {
		global $wpdb;
		$limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 30;
		$user  = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;

		$sql = 'SELECT * FROM ' . self::table();
		if ( $user && ! current_user_can( 'manage_options' ) ) {
			$sql .= $wpdb->prepare( ' WHERE created_by = %d', $user );
		}
		$sql .= ' ORDER BY updated_at DESC LIMIT ' . (int) $limit;

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['meta']  = $r['meta_json'] ? json_decode( $r['meta_json'], true ) : array();
			$r['media'] = $r['media_json'] ? json_decode( $r['media_json'], true ) : array();
		}
		return $rows;
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}
}
