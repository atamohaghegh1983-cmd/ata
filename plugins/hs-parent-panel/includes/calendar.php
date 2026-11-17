<?php
/**
 * تقویم والدین: مناسبت‌ها و رویدادهای مدرسه/کلاس + موعد تکالیف + ارزیابی‌ها + جلسه‌های تأییدشده
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Calendar {

	const DAYS = 45;

	/** رویدادهای بازه برای یک فرزند: [ 'Y-m-d' => [ [icon, title, sub, kind], ... ] ] */
	public static function events( $pid, $sid, $from, $to ) {
		$ev      = array();
		$add     = function ( $date, $icon, $title, $sub, $kind ) use ( &$ev ) {
			$ev[ substr( $date, 0, 10 ) ][] = array( $icon, $title, $sub, $kind );
		};
		$classes = HSPP_Perms::student_classes( $sid );
		$schools = array_filter( array_map( array( 'HSPP_Perms', 'class_school' ), $classes ) );

		if ( HSPP_Perms::can( $pid, $sid, 'calendar' ) && class_exists( 'HS_Calendar_DB' ) ) {
			$types = HS_Calendar_DB::types();
			foreach ( (array) HS_Calendar_DB::in_range( $from, $to ) as $e ) {
				$ok = false;
				if ( in_array( $e['audience'], array( 'parents', 'all' ), true ) ) {
					$ok = empty( $e['school_id'] ) || in_array( (int) $e['school_id'], $schools, true );
					if ( ! empty( $e['class_id'] ) ) {
						$ok = in_array( (int) $e['class_id'], $classes, true );
					}
				} elseif ( 'class' === $e['audience'] ) {
					$ok = in_array( (int) $e['class_id'], $classes, true );
				}
				if ( $ok ) {
					$add( $e['event_date'], '📌', $e['title'], $types[ $e['event_type'] ] ?? '', 'event' );
				}
			}
		}
		foreach ( $classes as $cid ) {
			if ( HSPP_Perms::can( $pid, $sid, 'homework', $cid ) && class_exists( 'HS_Homework_DB' ) ) {
				foreach ( (array) HS_Homework_DB::get_class_homework( $cid, 100 ) as $hw ) {
					if ( ! empty( $hw['due_date'] ) && $hw['due_date'] >= $from && $hw['due_date'] <= $to ) {
						$st   = HS_Homework_DB::get_statuses( $hw['id'] );
						$done = ! empty( $st[ $sid ] ) && in_array( $st[ $sid ]['status'], array( 'done', 'late' ), true );
						$add( $hw['due_date'], $done ? '✅' : '📚', 'موعد تکلیف: ' . $hw['title'], $hw['subject'], 'hw' );
					}
				}
			}
			if ( HSPP_Perms::can( $pid, $sid, 'assessment', $cid ) && class_exists( 'HS_Assessment_DB' ) ) {
				foreach ( (array) HS_Assessment_DB::get_class_assessments( $cid, 100 ) as $a ) {
					if ( ! empty( $a['assess_date'] ) && $a['assess_date'] >= $from && $a['assess_date'] <= $to ) {
						$add( $a['assess_date'], '📝', 'ارزیابی: ' . $a['title'], $a['subject'], 'assess' );
					}
				}
			}
		}
		foreach ( HSPP_Meetings::upcoming_for_parent( $pid, $from, $to ) as $m ) {
			if ( (int) $m['student_id'] === (int) $sid ) {
				$add( $m['slot'], '🤝', 'جلسه با معلم ساعت ' . substr( $m['slot'], 11, 5 ), $m['place'], 'meet' );
			}
		}
		ksort( $ev );
		return $ev;
	}

	public static function render( $pid, $sid ) {
		$from = current_time( 'Y-m-d' );
		$to   = gmdate( 'Y-m-d', strtotime( $from . ' +' . self::DAYS . ' days' ) );
		$ev   = self::events( $pid, $sid, $from, $to );
		$h    = '<section class="hs-pp-card"><h3>🗓 تقویم ' . self::DAYS . ' روز آینده</h3>'
			. '<p class="hs-pp-muted">مناسبت‌ها و برنامه‌های مدرسه، موعد تکالیف، ارزیابی‌ها و جلسه‌های شما با معلم.</p>';
		if ( ! $ev ) {
			return $h . '<p class="hs-pp-muted">در این بازه برنامه‌ای ثبت نشده است.</p></section>';
		}
		$h .= '<ol class="hspp-cal">';
		foreach ( $ev as $date => $items ) {
			$days = (int) round( ( strtotime( $date ) - strtotime( $from ) ) / DAY_IN_SECONDS );
			$rel  = 0 === $days ? 'امروز' : ( 1 === $days ? 'فردا' : $days . ' روز دیگر' );
			$h   .= '<li' . ( $days <= 1 ? ' class="is-soon"' : '' ) . '><div class="hspp-cal-d"><b>' . esc_html( HSPP_Util::weekday( $date ) ) . '</b>' . esc_html( HSPP_Util::d( $date, false ) ) . '<small>' . esc_html( $rel ) . '</small></div><ul>';
			foreach ( $items as $it ) {
				$h .= '<li class="k-' . esc_attr( $it[3] ) . '"><span aria-hidden="true">' . esc_html( $it[0] ) . '</span> ' . esc_html( $it[1] ) . ( $it[2] ? ' <small>' . esc_html( $it[2] ) . '</small>' : '' ) . '</li>';
			}
			$h .= '</ul></li>';
		}
		return $h . '</ol></section>';
	}
}
