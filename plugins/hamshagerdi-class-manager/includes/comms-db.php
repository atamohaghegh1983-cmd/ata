<?php
/**
 * لایه دیتابیس ارتباط: اطلاعیه و فید کلاسی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Comms_DB {

	const TABLE_ANNOUNCEMENTS = 'hs_announcements';
	const TABLE_FEED          = 'hs_feed_posts';
	const TABLE_MESSAGES      = 'hs_messages';

	public static function announcements_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_ANNOUNCEMENTS;
	}

	public static function feed_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_FEED;
	}

	public static function messages_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_MESSAGES;
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$ann = self::announcements_table();
		$feed = self::feed_table();
		$msg = self::messages_table();

		$sql1 = "CREATE TABLE {$ann} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			body text NOT NULL,
			author_id bigint(20) unsigned DEFAULT NULL,
			is_pinned tinyint(1) NOT NULL DEFAULT 0,
			show_from datetime DEFAULT NULL,
			show_until datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql2 = "CREATE TABLE {$feed} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			author_id bigint(20) unsigned DEFAULT NULL,
			body text NOT NULL,
			media_url varchar(500) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql3 = "CREATE TABLE {$msg} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sender_id bigint(20) unsigned NOT NULL,
			recipient_id bigint(20) unsigned NOT NULL,
			student_id bigint(20) unsigned DEFAULT NULL,
			body text NOT NULL,
			media_url varchar(500) DEFAULT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY sender (sender_id),
			KEY recipient (recipient_id),
			KEY class_id (class_id),
			KEY is_read (is_read)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	/* ---------- اطلاعیه‌ها ---------- */

	/**
	 * $show_from / $show_until: بازه‌ی نمایش (Y-m-d H:i:s یا Y-m-d)؛ خالی یعنی بدون محدودیت.
	 * بعد از show_until اطلاعیه برای والدین و دانش‌آموزان پنهان و برای معلم «بایگانی» می‌شود.
	 */
	public static function add_announcement( $class_id, $title, $body, $author_id = null, $pinned = false, $show_from = null, $show_until = null ) {
		global $wpdb;
		$wpdb->insert(
			self::announcements_table(),
			array(
				'class_id'   => absint( $class_id ),
				'title'      => sanitize_text_field( $title ),
				'body'       => wp_kses_post( $body ),
				'author_id'  => $author_id ? absint( $author_id ) : get_current_user_id(),
				'is_pinned'  => $pinned ? 1 : 0,
				'show_from'  => self::dt( $show_from ),
				'show_until' => self::dt( $show_until, true ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		if ( $id ) {
			do_action( 'hs_announcement_created', $id, absint( $class_id ), $author_id ? absint( $author_id ) : get_current_user_id() );
		}
		return $id;
	}

	/** تاریخ ورودی ← datetime معتبر یا null؛ تاریخ بدون ساعت برای پایان، آخر همان روز است */
	public static function dt( $v, $end = false ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return null;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			$v .= $end ? ' 23:59:59' : ' 00:00:00';
		}
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v ) ? $v : null;
	}

	/**
	 * اطلاعیه‌های کلاس. پیش‌فرض فقط آن‌هایی که الان در بازه‌ی نمایش‌اند؛ $all=true همه (برای معلم: بایگانی هم)
	 */
	public static function get_announcements( $class_id, $limit = 30, $all = false ) {
		global $wpdb;
		$now   = current_time( 'mysql' );
		$where = $all ? '' : $wpdb->prepare( ' AND (show_from IS NULL OR show_from <= %s) AND (show_until IS NULL OR show_until >= %s)', $now, $now );
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::announcements_table() .
			' WHERE class_id = %d' . $where . ' ORDER BY is_pinned DESC, created_at DESC LIMIT %d', // phpcs:ignore
			absint( $class_id ),
			absint( $limit )
		), ARRAY_A );
	}

	/** وضعیت نمایش: active | scheduled | archived */
	public static function ann_state( $a ) {
		$now = current_time( 'mysql' );
		if ( ! empty( $a['show_from'] ) && $a['show_from'] > $now ) {
			return 'scheduled';
		}
		if ( ! empty( $a['show_until'] ) && $a['show_until'] < $now ) {
			return 'archived';
		}
		return 'active';
	}

	public static function update_announcement( $id, $data ) {
		global $wpdb;
		$f = array();
		foreach ( array( 'title', 'body' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$f[ $k ] = 'body' === $k ? wp_kses_post( $data[ $k ] ) : sanitize_text_field( $data[ $k ] );
			}
		}
		if ( isset( $data['pinned'] ) ) {
			$f['is_pinned'] = $data['pinned'] ? 1 : 0;
		}
		if ( array_key_exists( 'show_from', $data ) ) {
			$f['show_from'] = self::dt( $data['show_from'] );
		}
		if ( array_key_exists( 'show_until', $data ) ) {
			$f['show_until'] = self::dt( $data['show_until'], true );
		}
		return $f ? $wpdb->update( self::announcements_table(), $f, array( 'id' => absint( $id ) ) ) : 0;
	}

	public static function delete_announcement( $id ) {
		global $wpdb;
		return $wpdb->delete( self::announcements_table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/* ---------- فید کلاسی ---------- */

	public static function add_feed_post( $class_id, $body, $author_id = null, $media_url = '' ) {
		global $wpdb;
		$wpdb->insert(
			self::feed_table(),
			array(
				'class_id'   => absint( $class_id ),
				'author_id'  => $author_id ? absint( $author_id ) : get_current_user_id(),
				'body'       => wp_kses_post( $body ),
				'media_url'  => esc_url_raw( $media_url ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_feed( $class_id, $limit = 40 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::feed_table() .
			' WHERE class_id = %d ORDER BY created_at DESC LIMIT %d',
			absint( $class_id ),
			absint( $limit )
		), ARRAY_A );
	}

	public static function delete_feed_post( $id ) {
		global $wpdb;
		return $wpdb->delete( self::feed_table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/* ---------- پیام‌ها ---------- */

	public static function send_message( $sender_id, $recipient_id, $body, $class_id = 0, $student_id = 0, $media_url = '' ) {
		global $wpdb;
		$wpdb->insert(
			self::messages_table(),
			array(
				'class_id'     => absint( $class_id ),
				'sender_id'    => absint( $sender_id ),
				'recipient_id' => absint( $recipient_id ),
				'student_id'   => $student_id ? absint( $student_id ) : null,
				'body'         => sanitize_textarea_field( $body ),
				'media_url'    => $media_url ? esc_url_raw( $media_url ) : null,
				'is_read'      => 0,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d' )
		);
		$id = (int) $wpdb->insert_id;
		if ( $media_url ) {
			$body = trim( $body . "\n🖼️ " . esc_url_raw( $media_url ) ); // برای اعلان بله
		}
		/**
		 * پس از ارسال پیام سایت (برای ربات بله و اعلان‌ها)
		 */
		do_action( 'hs_message_sent', absint( $sender_id ), absint( $recipient_id ), $body, absint( $class_id ), absint( $student_id ), $id );
		return $id;
	}

	public static function get_message( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::messages_table() . ' WHERE id = %d', absint( $id ) ), ARRAY_A );
	}

	/** حذف یک پیام (بررسی اجازه با فراخواننده است) */
	public static function delete_message( $id ) {
		global $wpdb;
		$ok = $wpdb->delete( self::messages_table(), array( 'id' => absint( $id ) ), array( '%d' ) );
		if ( $ok ) {
			do_action( 'hs_message_deleted', absint( $id ) );
		}
		return $ok;
	}

	/**
	 * گفتگو بین دو کاربر (مرتبط با کلاس اختیاری)
	 */
	public static function get_conversation( $user_a, $user_b, $class_id = 0, $limit = 50 ) {
		global $wpdb;
		$table = self::messages_table();
		$user_a = absint( $user_a );
		$user_b = absint( $user_b );

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE ((sender_id = %d AND recipient_id = %d) OR (sender_id = %d AND recipient_id = %d))",
			$user_a, $user_b, $user_b, $user_a
		);
		if ( $class_id ) {
			$sql .= $wpdb->prepare( ' AND class_id = %d', absint( $class_id ) );
		}
		$sql .= $wpdb->prepare( ' ORDER BY created_at ASC LIMIT %d', absint( $limit ) );

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * صندوق ورودی معلم: آخرین پیام از هر فرستنده
	 */
	public static function get_inbox( $user_id, $limit = 40 ) {
		global $wpdb;
		$table = self::messages_table();
		$user_id = absint( $user_id );

		// آخرین پیام در هر گفتگو (با طرف مقابل)
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT m.* FROM {$table} m
			 INNER JOIN (
			 	SELECT
			 		CASE WHEN sender_id = %d THEN recipient_id ELSE sender_id END AS other_id,
			 		MAX(id) AS max_id
			 	FROM {$table}
			 	WHERE sender_id = %d OR recipient_id = %d
			 	GROUP BY other_id
			 ) t ON m.id = t.max_id
			 ORDER BY m.created_at DESC
			 LIMIT %d",
			$user_id, $user_id, $user_id, absint( $limit )
		), ARRAY_A );

		return $rows ? $rows : array();
	}

	/** پیش‌نمایش کوتاه یک پیام (با پشتیبانی از عکس) */
	public static function preview( $m, $len = 50 ) {
		$body = isset( $m['body'] ) ? trim( (string) $m['body'] ) : '';
		if ( ! empty( $m['media_url'] ) ) {
			return '📷 ' . ( '' !== $body ? mb_substr( $body, 0, $len ) : 'تصویر' );
		}
		return mb_substr( $body, 0, $len );
	}

	public static function count_unread( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::messages_table() .
			' WHERE recipient_id = %d AND is_read = 0',
			absint( $user_id )
		) );
	}

	public static function mark_read( $user_id, $other_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::messages_table() .
			' SET is_read = 1 WHERE recipient_id = %d AND sender_id = %d AND is_read = 0',
			absint( $user_id ),
			absint( $other_id )
		) );
	}
}
