<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_DB {

	public static function t( $n ) {
		global $wpdb;
		return $wpdb->prefix . 'hssp_' . $n;
	}

	public static function now() {
		return current_time( 'mysql' );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'hssp_db_version' ) !== HSSP_DB_VERSION ) {
			self::install();
			if ( class_exists( 'HSSP_Integration' ) ) {
				HSSP_Integration::create_pages();
			}
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		dbDelta( 'CREATE TABLE ' . self::t( 'perms' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope varchar(20) NOT NULL,
			scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
			feature varchar(30) NOT NULL,
			allow tinyint(1) NOT NULL DEFAULT 1,
			set_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY rule (scope,scope_id,feature)
		) $c;" );
		dbDelta( 'CREATE TABLE ' . self::t( 'library' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL DEFAULT 'story',
			title varchar(190) NOT NULL DEFAULT '',
			body longtext,
			url varchar(255) NOT NULL DEFAULT '',
			icon varchar(16) NOT NULL DEFAULT '📖',
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY class_id (class_id)
		) $c;" );
		dbDelta( 'CREATE TABLE ' . self::t( 'reads' ) . " (
			item_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned NOT NULL,
			stars tinyint(1) NOT NULL DEFAULT 0,
			read_at datetime NOT NULL,
			PRIMARY KEY  (item_id,student_id)
		) $c;" );
		dbDelta( 'CREATE TABLE ' . self::t( 'log' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL,
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details text,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action (action)
		) $c;" );
		update_option( 'hssp_db_version', HSSP_DB_VERSION );
	}

	public static function log( $action, $target = 0, $details = array() ) {
		global $wpdb;
		$wpdb->insert( self::t( 'log' ), array(
			'actor_id'   => get_current_user_id(),
			'action'     => $action,
			'target_id'  => (int) $target,
			'details'    => wp_json_encode( $details ),
			'created_at' => self::now(),
		) );
	}
}
