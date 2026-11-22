<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_DB {

	public static function t( $n ) {
		global $wpdb;
		return $wpdb->prefix . 'hspp_' . $n;
	}

	public static function maybe_upgrade() {
		if ( get_option( 'hspp_db_version' ) !== HSPP_DB_VERSION ) {
			self::install();
			if ( class_exists( 'HSPP_Integration' ) ) {
				HSPP_Integration::create_pages();
			}
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		// جدول گفتگو همان جدول قبلی «مدیریت کلاس» است (پیام‌ها حفظ می‌شود) + ستون حذف
		if ( class_exists( 'HS_Parent_Chat_DB' ) ) {
			HS_Parent_Chat_DB::create_table();
			$ct = HS_Parent_Chat_DB::table();
			if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $ct LIKE %s", 'deleted' ) ) ) { // phpcs:ignore
				$wpdb->query( "ALTER TABLE $ct ADD deleted tinyint(1) NOT NULL DEFAULT 0, ADD deleted_by bigint(20) unsigned NOT NULL DEFAULT 0" ); // phpcs:ignore
			}
		}

		// مجوزها: scope = site | school | class | student | link ؛ allow = 1/0
		dbDelta( 'CREATE TABLE ' . self::t( 'perms' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope varchar(10) NOT NULL,
			scope_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			feature varchar(30) NOT NULL,
			allow tinyint(1) NOT NULL DEFAULT 1,
			set_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY rule (scope,scope_id,parent_id,feature)
		) $c;" );

		// تنظیمات گروه والدین هر کلاس
		dbDelta( 'CREATE TABLE ' . self::t( 'groups' ) . " (
			class_id bigint(20) unsigned NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			read_only tinyint(1) NOT NULL DEFAULT 0,
			allow_images tinyint(1) NOT NULL DEFAULT 1,
			quiet_from tinyint(2) NOT NULL DEFAULT 22,
			quiet_to tinyint(2) NOT NULL DEFAULT 7,
			quiet_on tinyint(1) NOT NULL DEFAULT 1,
			pinned_id bigint(20) unsigned NOT NULL DEFAULT 0,
			rules text NULL,
			PRIMARY KEY  (class_id)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'assistants' ) . " (
			class_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			added_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (class_id,user_id)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'mutes' ) . " (
			class_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			until datetime NOT NULL,
			by_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (class_id,user_id)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'reports' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			msg_id bigint(20) unsigned NOT NULL,
			class_id bigint(20) unsigned NOT NULL,
			reporter_id bigint(20) unsigned NOT NULL,
			reason varchar(255) NOT NULL DEFAULT '',
			status varchar(10) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one (msg_id,reporter_id),
			KEY class_status (class_id,status)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'polls' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			question varchar(255) NOT NULL,
			options text NOT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			closed tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY class_id (class_id)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'votes' ) . " (
			poll_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			choice tinyint(3) unsigned NOT NULL,
			PRIMARY KEY  (poll_id,user_id)
		) $c;" );

		// «دیده شد» اطلاعیه‌ها
		dbDelta( 'CREATE TABLE ' . self::t( 'reads' ) . " (
			ann_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			read_at datetime NOT NULL,
			PRIMARY KEY  (ann_id,user_id)
		) $c;" );

		// سوابق: تغییر مجوز، ویرایش اطلاعات توسط والد، اقدامات ناظر گروه
		dbDelta( 'CREATE TABLE ' . self::t( 'log' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(30) NOT NULL,
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action (action),
			KEY target_id (target_id)
		) $c;" );

		dbDelta( "CREATE TABLE " . self::t( 'meetings' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			student_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
			topic varchar(40) NOT NULL DEFAULT '',
			details text,
			mode varchar(20) NOT NULL DEFAULT 'inperson',
			prefs varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			slot datetime DEFAULT NULL,
			place varchar(190) NOT NULL DEFAULT '',
			reply text,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY teacher_id (teacher_id),
			KEY parent_id (parent_id)
		) $c;" );
		dbDelta( "CREATE TABLE " . self::t( 'practice' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			subject varchar(80) NOT NULL DEFAULT '',
			body text,
			url varchar(255) NOT NULL DEFAULT '',
			minutes int(11) NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY class_id (class_id)
		) $c;" );
		dbDelta( "CREATE TABLE " . self::t( 'practice_done' ) . " (
			practice_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned NOT NULL,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note varchar(255) NOT NULL DEFAULT '',
			done_at datetime NOT NULL,
			PRIMARY KEY  (practice_id,student_id)
		) $c;" );

		update_option( 'hspp_db_version', HSPP_DB_VERSION );
	}

	public static function now() {
		return current_time( 'mysql' );
	}

	public static function log( $action, $target, $details = array() ) {
		global $wpdb;
		$wpdb->insert( self::t( 'log' ), array(
			'actor_id'   => get_current_user_id(),
			'action'     => $action,
			'target_id'  => (int) $target,
			'details'    => wp_json_encode( $details, JSON_UNESCAPED_UNICODE ),
			'created_at' => self::now(),
		) );
	}
}
