<?php
/**
 * یادآوری مناسبت‌ها — یک هفته قبل (یا طبق remind_days)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Calendar_Reminders {

	const CRON_HOOK = 'hs_calendar_daily_reminders';

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	public static function run() {
		// یادآوری‌ها را افزونه‌ی «تقویم مناسبتی» انجام می‌دهد؛ جلوگیری از ارسال دوباره
		if ( function_exists( 'hs_oc_events' ) ) {
			return;
		}
		if ( ! class_exists( 'HS_Calendar_DB' ) ) {
			return;
		}
		$events = HS_Calendar_DB::due_for_reminder();
		if ( empty( $events ) ) {
			return;
		}

		$types = HS_Calendar_DB::types();

		foreach ( $events as $ev ) {
			self::notify_event( $ev, $types );
			HS_Calendar_DB::mark_reminded( $ev['id'] );
		}
	}

	private static function notify_event( $ev, $types ) {
		$type_label = $types[ $ev['event_type'] ] ?? $ev['event_type'];
		$title = $ev['title'];
		$date = $ev['event_date'];
		$days = (int) $ev['remind_days'];
		$desc = $ev['description'] ? "\n" . $ev['description'] : '';

		$text = "📅 یادآوری مناسبت همشاگردی\n";
		$text .= "«{$title}» ({$type_label})\n";
		$text .= "تاریخ: {$date}\n";
		$text .= "{$days} روز تا رویداد باقی مانده — برای برنامه‌ریزی اقدام کنید.{$desc}";

		$user_ids = self::recipients( $ev );

		foreach ( $user_ids as $uid ) {
			// اعلان داخل سایت (پیام سیستم به کاربر)
			if ( class_exists( 'HS_Comms_DB' ) ) {
				HS_Comms_DB::send_message( 0, $uid, $text, (int) $ev['class_id'], 0 );
			}
			// بله
			if ( class_exists( 'HS_Bale_API' ) && HS_Bale_API::is_enabled() ) {
				HS_Bale_API::notify_user( $uid, $text );
			}
		}
	}

	/**
	 * مخاطبان یادآوری
	 */
	private static function recipients( $ev ) {
		$ids = array();
		$audience = $ev['audience'];

		if ( $audience === 'class' && ! empty( $ev['class_id'] ) ) {
			$tid = get_post_meta( $ev['class_id'], '_hs_teacher_id', true );
			if ( $tid ) {
				$ids[] = (int) $tid;
			}
			// والدین کلاس
			$students = get_post_meta( $ev['class_id'], '_hs_students', true );
			if ( is_array( $students ) && class_exists( 'HS_Parent_Link' ) ) {
				foreach ( $students as $sid ) {
					foreach ( HS_Parent_Link::get_parents( $sid ) as $pid ) {
						$ids[] = (int) $pid;
					}
				}
			}
		} elseif ( $audience === 'parents' ) {
			$parents = get_users( array( 'role' => 'parent', 'fields' => 'ID', 'number' => 500 ) );
			$ids = array_map( 'intval', $parents );
		} elseif ( $audience === 'all' ) {
			$teachers = get_users( array( 'role' => 'teacher', 'fields' => 'ID', 'number' => 200 ) );
			$parents = get_users( array( 'role' => 'parent', 'fields' => 'ID', 'number' => 500 ) );
			$admins = get_users( array( 'role' => 'school_admin', 'fields' => 'ID', 'number' => 50 ) );
			$ids = array_merge(
				array_map( 'intval', $teachers ),
				array_map( 'intval', $parents ),
				array_map( 'intval', $admins )
			);
		} else {
			// teachers (پیش‌فرض): معلمان + school_admin
			$teachers = get_users( array( 'role' => 'teacher', 'fields' => 'ID', 'number' => 200 ) );
			$admins = get_users( array( 'role' => 'school_admin', 'fields' => 'ID', 'number' => 50 ) );
			$ids = array_merge( array_map( 'intval', $teachers ), array_map( 'intval', $admins ) );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}
}

HS_Calendar_Reminders::init();
