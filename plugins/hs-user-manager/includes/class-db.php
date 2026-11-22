<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * جداول افزونه. حساب کاربران همان کاربران وردپرس است؛ کلاس‌ها (hs_class) و مدارس (hs_school) از افزونه‌های مدیریت کلاس و ساختار مدرسه.
 */
class HSUM_DB {

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'hsum_' . $name;
	}

	public static function maybe_upgrade() {
		if ( get_option( 'hsum_db_version' ) !== HSUM_DB_VERSION ) {
			self::install();
		}
		// ۲.۱: معلم دیگر به تأیید مدیر سایت نیاز ندارد (یک بار؛ بعد از آن تنظیمات مدیر محترم است)
		if ( ! get_option( 'hsum_teacher_free' ) ) {
			$o = (array) get_option( 'hsum_settings', array() );
			if ( isset( $o['approval_roles'] ) ) {
				$o['approval_roles'] = array_values( array_diff( (array) $o['approval_roles'], array( 'teacher' ) ) );
				update_option( 'hsum_settings', $o );
			}
			update_option( 'hsum_teacher_free', 1, false );
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		// وضعیت عضویت (مستقل از نقش)
		dbDelta( 'CREATE TABLE ' . self::t( 'membership' ) . " (
			user_id bigint(20) unsigned NOT NULL,
			role varchar(40) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			reason text NULL,
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewed_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (user_id),
			KEY status (status),
			KEY role (role)
		) $c;" );

		// ارتباط والد ↔ دانش‌آموز (چندبه‌چند؛ پیشنهاد با شماره تا زمان ثبت‌نام والد نگه داشته می‌شود)
		dbDelta( 'CREATE TABLE ' . self::t( 'links' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			student_id bigint(20) unsigned NOT NULL,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_mobile varchar(15) NOT NULL DEFAULT '',
			relation varchar(20) NOT NULL DEFAULT 'parent',
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'proposed',
			source varchar(20) NOT NULL DEFAULT 'manual',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			confirmed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY student_id (student_id),
			KEY parent_id (parent_id),
			KEY parent_mobile (parent_mobile),
			KEY status (status)
		) $c;" );

		// دعوت‌نامه‌ها: class (کد مشترک والدین کلاس) | teacher (معرفی معلم با موبایل توسط مدیر مدرسه)
		dbDelta( 'CREATE TABLE ' . self::t( 'invites' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL,
			code varchar(32) NOT NULL DEFAULT '',
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			school_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mobile varchar(15) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			data text NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			uses int unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			expires_at datetime NULL,
			PRIMARY KEY  (id),
			KEY code (code),
			KEY class_id (class_id),
			KEY mobile (mobile),
			KEY type_status (type,status)
		) $c;" );

		// عضویت معلمان در مدارس (یک معلم می‌تواند در چند مدرسه باشد)
		dbDelta( 'CREATE TABLE ' . self::t( 'school_members' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			school_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL DEFAULT 'teacher',
			status varchar(20) NOT NULL DEFAULT 'active',
			invite_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY school_user (school_id,user_id),
			KEY user_id (user_id)
		) $c;" );

		// درخواست‌های اتصال والدین بدون کلاس / با کد کلاس بدون تطبیق شماره
		dbDelta( 'CREATE TABLE ' . self::t( 'requests' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			parent_id bigint(20) unsigned NOT NULL,
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			school_id bigint(20) unsigned NOT NULL DEFAULT 0,
			teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
			teacher_mobile varchar(15) NOT NULL DEFAULT '',
			child_name varchar(190) NOT NULL DEFAULT '',
			message text NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			handled_by bigint(20) unsigned NOT NULL DEFAULT 0,
			handled_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY parent_id (parent_id),
			KEY class_id (class_id),
			KEY teacher_id (teacher_id),
			KEY status (status)
		) $c;" );

		// سوابق: تغییر نقش، تأیید/رد، اتصال‌ها، ورود گروهی
		dbDelta( 'CREATE TABLE ' . self::t( 'audit' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL,
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action (action),
			KEY target_id (target_id)
		) $c;" );

		update_option( 'hsum_db_version', HSUM_DB_VERSION );
	}

	public static function now() {
		return current_time( 'mysql' );
	}
}

/** تنظیمات */
function hsum_opt( $key = null ) {
	$d = array(
		'phone_keys'      => array( '_hs_mobile', 'billing_phone', 'digits_phone_no', 'digits_phone', 'mobile', 'phone', 'user_phone' ),
		'approval_roles'  => array( 'school_admin' ), // معلم محور سایت است و بدون تأیید شروع می‌کند
		'invite_days'     => 0, // ۰ = بدون انقضا
		'sms_invites'     => 1,
	);
	$o = wp_parse_args( (array) get_option( 'hsum_settings', array() ), $d );
	return null === $key ? $o : ( $o[ $key ] ?? null );
}
