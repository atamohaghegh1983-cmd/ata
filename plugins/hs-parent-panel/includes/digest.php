<?php
/**
 * خلاصه‌ی روزانه برای والدین
 * - کارت «خلاصه‌ی امروز» در خانه‌ی پنل (همیشه)
 * - پیامک عصرگاهی برای والدینی که خودشان روشن کرده‌اند (نیازمند «ورود و پیامک»)
 * ساعت ارسال: فیلتر hspp_digest_hour (پیش‌فرض ۱۹)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Digest {

	const META = '_hspp_digest'; // off | sms

	public static function init() {
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'hspp_digest_cron', array( __CLASS__, 'run' ) );
		add_action( 'admin_post_hspp_digest', array( __CLASS__, 'save' ) );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( 'hspp_digest_cron' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'hspp_digest_cron' );
		}
	}

	public static function sms_ready() {
		return class_exists( 'HSMA_Gateways' ) && '' !== (string) HSMA_Gateways::current_class();
	}

	/** خطوط خلاصه‌ی یک روز برای یک فرزند */
	public static function lines( $pid, $sid, $day ) {
		$l    = array();
		$next = gmdate( 'Y-m-d', strtotime( $day . ' +1 day' ) );
		$att  = array( 'absent' => '❌ غایب بود', 'late' => '⏰ با تأخیر آمد', 'excused' => '📝 غیبت موجه', 'present' => '✅ حاضر بود' );
		foreach ( HSPP_Perms::student_classes( $sid ) as $cid ) {
			$can = function ( $f ) use ( $pid, $sid, $cid ) {
				return HSPP_Perms::can( $pid, $sid, $f, $cid );
			};
			if ( $can( 'attendance' ) && class_exists( 'HS_Attendance_DB' ) ) {
				$r = HS_Attendance_DB::get_by_class_date( $cid, $day );
				if ( isset( $r[ $sid ], $att[ $r[ $sid ]['status'] ] ) ) {
					$l['att'] = $att[ $r[ $sid ]['status'] ];
				}
			}
			if ( $can( 'homework' ) && class_exists( 'HS_Homework_DB' ) ) {
				foreach ( (array) HS_Homework_DB::get_class_homework( $cid, 30 ) as $hw ) {
					if ( 0 === strpos( (string) $hw['created_at'], $day ) ) {
						$l[] = '📚 تکلیف جدید: ' . $hw['title'] . ( ! empty( $hw['due_date'] ) ? ' (تا ' . HSPP_Util::d( $hw['due_date'], false ) . ')' : '' );
					} elseif ( $next === (string) $hw['due_date'] ) {
						$st = HS_Homework_DB::get_statuses( $hw['id'] );
						if ( empty( $st[ $sid ] ) || ! in_array( $st[ $sid ]['status'], array( 'done', 'late' ), true ) ) {
							$l[] = '⏳ فردا موعد تکلیف: ' . $hw['title'];
						}
					}
				}
			}
			if ( $can( 'points' ) && class_exists( 'HS_Points_DB' ) ) {
				$sum = 0;
				foreach ( (array) HS_Points_DB::get_student_history( $cid, $sid, 30 ) as $p ) {
					if ( 0 === strpos( (string) $p['created_at'], $day ) ) {
						$sum += (int) $p['points'];
					}
				}
				if ( $sum ) {
					$l[] = ( $sum > 0 ? '⭐ امروز ' . $sum . ' امتیاز تشویقی گرفت' : '⚠️ امروز یادداشت رفتاری داشت' );
				}
			}
			if ( $can( 'announce' ) && class_exists( 'HS_Comms_DB' ) ) {
				foreach ( (array) HS_Comms_DB::get_announcements( $cid, 10 ) as $a ) {
					if ( 0 === strpos( (string) $a['created_at'], $day ) ) {
						$l[] = '📢 اطلاعیه: ' . ( $a['title'] ? $a['title'] : mb_substr( wp_strip_all_tags( $a['body'] ), 0, 40 ) );
					}
				}
			}
			if ( $can( 'assessment' ) && class_exists( 'HS_Assessment_DB' ) ) {
				foreach ( (array) HS_Assessment_DB::get_class_assessments( $cid, 10 ) as $a ) {
					if ( $next === (string) $a['assess_date'] ) {
						$l[] = '📝 فردا ارزیابی: ' . $a['title'];
					}
				}
			}
			if ( $can( 'practice' ) ) {
				foreach ( HSPP_Practice::new_since( $cid, $day . ' 00:00:00' ) as $p ) {
					if ( 0 === strpos( (string) $p['created_at'], $day ) ) {
						$l[] = '🏡 تمرین جدید برای خانه: ' . $p['title'];
					}
				}
			}
		}
		foreach ( HSPP_Meetings::upcoming_for_parent( $pid, $next, $next ) as $m ) {
			if ( (int) $m['student_id'] === (int) $sid ) {
				$l[] = '🤝 فردا ساعت ' . substr( $m['slot'], 11, 5 ) . ' جلسه با معلم';
			}
		}
		if ( HSPP_Perms::can( $pid, $sid, 'calendar' ) ) {
			foreach ( HSPP_Calendar::events( $pid, $sid, $next, $next ) as $items ) {
				foreach ( $items as $it ) {
					if ( 'event' === $it[3] ) {
						$l[] = '📌 فردا: ' . $it[1];
					}
				}
			}
		}
		return array_values( array_unique( $l ) );
	}

	/** کارت خانه‌ی پنل */
	public static function card( $pid, $sid ) {
		if ( ! HSPP_Perms::can( $pid, $sid, 'digest' ) ) {
			return '';
		}
		$lines = self::lines( $pid, $sid, current_time( 'Y-m-d' ) );
		$h     = '<section class="hs-pp-card hspp-digest"><h3>📰 خلاصه‌ی امروز <small>' . esc_html( HSPP_Util::weekday( current_time( 'Y-m-d' ) ) . ' ' . HSPP_Util::d( current_time( 'Y-m-d' ), false ) ) . '</small></h3>';
		if ( $lines ) {
			$h .= '<ul>';
			foreach ( $lines as $x ) {
				$h .= '<li>' . esc_html( $x ) . '</li>';
			}
			$h .= '</ul>';
		} else {
			$h .= '<p class="hs-pp-muted">امروز خبر تازه‌ای ثبت نشده است.</p>';
		}
		if ( self::sms_ready() ) {
			$on = 'sms' === get_user_meta( $pid, self::META, true );
			$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hspp-digest-opt">' . wp_nonce_field( 'hspp_digest', '_wpnonce', true, false )
				. '<input type="hidden" name="action" value="hspp_digest"><input type="hidden" name="v" value="' . ( $on ? 'off' : 'sms' ) . '">'
				. '<span>' . ( $on ? '📲 خلاصه هر عصر برایتان پیامک می‌شود.' : 'می‌خواهید این خلاصه هر عصر پیامک شود؟' ) . '</span> <button class="hspp-link">' . ( $on ? 'خاموش کردن' : 'روشن کردن' ) . '</button></form>';
		}
		return $h . '</section>';
	}

	public static function save() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'hspp_digest' ) ) {
			wp_die( 'نامعتبر' );
		}
		$v = 'sms' === ( $_POST['v'] ?? '' ) ? 'sms' : 'off';
		if ( 'sms' === $v && ! get_user_meta( get_current_user_id(), '_hs_mobile', true ) ) {
			HSPP_Util::back( 'برای پیامک، شماره موبایل حساب شما ثبت نشده است.' );
		}
		update_user_meta( get_current_user_id(), self::META, $v );
		HSPP_Util::back( 'sms' === $v ? 'خلاصه‌ی روزانه از امشب پیامک می‌شود.' : 'پیامک خلاصه خاموش شد.' );
	}

	/** کرون ساعتی؛ فقط یک‌بار در روز و در ساعت تعیین‌شده می‌فرستد */
	public static function run() {
		$hour = (int) apply_filters( 'hspp_digest_hour', 19 );
		$day  = current_time( 'Y-m-d' );
		if ( (int) current_time( 'G' ) < $hour || get_option( 'hspp_digest_day' ) === $day || ! self::sms_ready() ) {
			return;
		}
		update_option( 'hspp_digest_day', $day, false );
		$users = get_users( array( 'meta_key' => self::META, 'meta_value' => 'sms', 'fields' => 'ID', 'number' => 2000 ) ); // phpcs:ignore
		$sent  = 0;
		foreach ( $users as $pid ) {
			$parts = array();
			foreach ( HS_Parent_Link::get_children( $pid ) as $sid ) {
				if ( ! HSPP_Perms::can( $pid, $sid, 'digest' ) ) {
					continue;
				}
				$lines = self::lines( $pid, $sid, $day );
				if ( $lines ) {
					$u       = get_userdata( $sid );
					$parts[] = ( $u ? ( $u->first_name ? $u->first_name : $u->display_name ) : '' ) . ":\n" . implode( "\n", array_slice( $lines, 0, 6 ) );
				}
			}
			if ( $parts && HSPP_Util::sms( $pid, "خلاصه امروز\n" . implode( "\n\n", $parts ) . "\n" . wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
				$sent++;
			}
		}
		HSPP_DB::log( 'digest', 0, array( 'day' => $day, 'sent' => $sent ) );
	}
}

HSPP_Digest::init();
