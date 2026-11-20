<?php
/**
 * نظارت (فقط‌خواندنی): آمار و فعالیت کلاس‌های مدرسه از جدول‌های «مدیریت کلاس».
 * این کلاس هیچ نوشتنی روی داده‌های کلاس انجام نمی‌دهد.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_SO_Monitor {

	public static function available() {
		return post_type_exists( 'hs_class' ) && class_exists( 'HS_Attendance_DB' );
	}

	/** کلاس‌های مدرسه = کلاس‌های همهٔ اعضای کادر (آموزگاران و ...) */
	public static function class_ids( $school_id, $teacher_id = 0 ) {
		if ( ! self::available() ) {
			return array();
		}
		$ids = $teacher_id ? array( (int) $teacher_id ) : HS_SO_Store::staff_user_ids( $school_id );
		if ( ! $ids ) {
			return array();
		}
		$posts = get_posts( array(
			'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 300, 'no_found_rows' => true,
			'meta_query' => array( array( 'key' => '_hs_teacher_id', 'value' => $ids, 'compare' => 'IN' ) ),
		) );
		return array_map( 'intval', $posts );
	}

	private static function in( $ids ) {
		return implode( ',', array_map( 'intval', $ids ) );
	}

	/** آمار هر کلاس در ۳۰ روز اخیر */
	public static function class_rows( $class_ids ) {
		global $wpdb;
		if ( ! $class_ids ) {
			return array();
		}
		$in    = self::in( $class_ids );
		$from  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -30 days' ) );
		$fromt = $from . ' 00:00:00';
		$att   = HS_Attendance_DB::table_name();

		$att_rows = $wpdb->get_results( $wpdb->prepare( "SELECT class_id, MAX(att_date) AS last_d, SUM(status IN ('present','late')) AS ok, COUNT(*) AS total FROM {$att} WHERE class_id IN ({$in}) AND att_date >= %s GROUP BY class_id", $from ), OBJECT_K ); // phpcs:ignore
		$att_last = $wpdb->get_results( "SELECT class_id, MAX(att_date) AS last_d FROM {$att} WHERE class_id IN ({$in}) GROUP BY class_id", OBJECT_K ); // phpcs:ignore

		$count = function ( $table, $col = 'created_at' ) use ( $wpdb, $in, $fromt ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT class_id, COUNT(*) AS n, MAX({$col}) AS last_t FROM {$table} WHERE class_id IN ({$in}) AND {$col} >= %s GROUP BY class_id", $fromt ), OBJECT_K ); // phpcs:ignore
		};
		$hw   = class_exists( 'HS_Homework_DB' ) ? $count( HS_Homework_DB::hw_table() ) : array();
		$as   = class_exists( 'HS_Assessment_DB' ) ? $count( HS_Assessment_DB::assessments_table() ) : array();
		$pt   = class_exists( 'HS_Points_DB' ) ? $count( HS_Points_DB::points_table() ) : array();
		$ann  = class_exists( 'HS_Comms_DB' ) ? $count( HS_Comms_DB::announcements_table() ) : array();
		$feed = class_exists( 'HS_Comms_DB' ) ? $count( HS_Comms_DB::feed_table() ) : array();
		$pf   = class_exists( 'HS_Portfolio_DB' ) ? $count( HS_Portfolio_DB::table() ) : array();

		$out = array();
		foreach ( $class_ids as $cid ) {
			$tid     = (int) get_post_meta( $cid, '_hs_teacher_id', true );
			$t       = $tid ? get_userdata( $tid ) : null;
			$a       = isset( $att_rows[ $cid ] ) ? $att_rows[ $cid ] : null;
			$last_ts = 0;
			foreach ( array( $hw, $as, $pt, $ann, $feed, $pf ) as $set ) {
				if ( isset( $set[ $cid ] ) && $set[ $cid ]->last_t ) {
					$last_ts = max( $last_ts, strtotime( $set[ $cid ]->last_t ) );
				}
			}
			if ( isset( $att_last[ $cid ] ) && $att_last[ $cid ]->last_d ) {
				$last_ts = max( $last_ts, strtotime( $att_last[ $cid ]->last_d . ' 12:00:00' ) );
			}
			$out[] = array(
				'id'        => $cid,
				'title'     => get_the_title( $cid ),
				'grade'     => (string) get_post_meta( $cid, '_hs_grade', true ),
				'teacher'   => $t ? $t->display_name : '—',
				'teacher_id'=> $tid,
				'students'  => class_exists( 'HS_Class_Students' ) ? (int) HS_Class_Students::count( $cid ) : 0,
				'att_rate'  => ( $a && (int) $a->total > 0 ) ? (int) round( 100 * $a->ok / $a->total ) : null,
				'att_last'  => isset( $att_last[ $cid ] ) ? $att_last[ $cid ]->last_d : '',
				'homework'  => isset( $hw[ $cid ] ) ? (int) $hw[ $cid ]->n : 0,
				'assess'    => isset( $as[ $cid ] ) ? (int) $as[ $cid ]->n : 0,
				'points'    => isset( $pt[ $cid ] ) ? (int) $pt[ $cid ]->n : 0,
				'comms'     => ( isset( $ann[ $cid ] ) ? (int) $ann[ $cid ]->n : 0 ) + ( isset( $feed[ $cid ] ) ? (int) $feed[ $cid ]->n : 0 ),
				'portfolio' => isset( $pf[ $cid ] ) ? (int) $pf[ $cid ]->n : 0,
				'last_ts'   => $last_ts,
			);
		}
		return $out;
	}

	/** خلاصهٔ هر آموزگار از روی ردیف کلاس‌ها */
	public static function teacher_rows( $school_id, $rows ) {
		$by = array();
		foreach ( $rows as $r ) {
			$k = $r['teacher_id'];
			if ( ! isset( $by[ $k ] ) ) {
				$by[ $k ] = array( 'id' => $k, 'name' => $r['teacher'], 'classes' => 0, 'students' => 0, 'homework' => 0, 'last_ts' => 0, 'att' => array() );
			}
			$by[ $k ]['classes']++;
			$by[ $k ]['students'] += $r['students'];
			$by[ $k ]['homework'] += $r['homework'];
			$by[ $k ]['last_ts']   = max( $by[ $k ]['last_ts'], $r['last_ts'] );
			if ( null !== $r['att_rate'] ) {
				$by[ $k ]['att'][] = $r['att_rate'];
			}
		}
		foreach ( $by as &$t ) {
			$t['att_rate'] = $t['att'] ? (int) round( array_sum( $t['att'] ) / count( $t['att'] ) ) : null;
		}
		unset( $t );
		return array_values( $by );
	}

	/** فهرست زمانی فعالیت‌های اخیر (۱۴ روز) */
	public static function feed( $class_ids, $limit = 30 ) {
		global $wpdb;
		if ( ! $class_ids ) {
			return array();
		}
		$in   = self::in( $class_ids );
		$from = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . ' -14 days' ) );
		$ev   = array();
		$add  = function ( $rows, $icon, $label, $by_field, $title_field = '' ) use ( &$ev ) {
			foreach ( (array) $rows as $r ) {
				$u    = ! empty( $r[ $by_field ] ) ? get_userdata( $r[ $by_field ] ) : null;
				$ev[] = array(
					'ts'    => strtotime( $r['ts'] ),
					'icon'  => $icon,
					'text'  => $label . ( $title_field && ! empty( $r[ $title_field ] ) ? ': ' . wp_html_excerpt( $r[ $title_field ], 60, '…' ) : '' ),
					'class' => get_the_title( $r['class_id'] ),
					'by'    => $u ? $u->display_name : '',
				);
			}
		};
		if ( class_exists( 'HS_Homework_DB' ) ) {
			$add( $wpdb->get_results( $wpdb->prepare( 'SELECT class_id, title, created_by, created_at AS ts FROM ' . HS_Homework_DB::hw_table() . " WHERE class_id IN ({$in}) AND created_at >= %s ORDER BY created_at DESC LIMIT 30", $from ), ARRAY_A ), '📚', 'تکلیف جدید', 'created_by', 'title' ); // phpcs:ignore
		}
		if ( class_exists( 'HS_Assessment_DB' ) ) {
			$add( $wpdb->get_results( $wpdb->prepare( 'SELECT class_id, title, created_by, created_at AS ts FROM ' . HS_Assessment_DB::assessments_table() . " WHERE class_id IN ({$in}) AND created_at >= %s ORDER BY created_at DESC LIMIT 30", $from ), ARRAY_A ), '📝', 'ارزیابی جدید', 'created_by', 'title' ); // phpcs:ignore
		}
		if ( class_exists( 'HS_Comms_DB' ) ) {
			$add( $wpdb->get_results( $wpdb->prepare( 'SELECT class_id, title, author_id, created_at AS ts FROM ' . HS_Comms_DB::announcements_table() . " WHERE class_id IN ({$in}) AND created_at >= %s ORDER BY created_at DESC LIMIT 30", $from ), ARRAY_A ), '📢', 'اطلاعیه برای والدین', 'author_id', 'title' ); // phpcs:ignore
			$add( $wpdb->get_results( $wpdb->prepare( 'SELECT class_id, author_id, created_at AS ts FROM ' . HS_Comms_DB::feed_table() . " WHERE class_id IN ({$in}) AND created_at >= %s ORDER BY created_at DESC LIMIT 30", $from ), ARRAY_A ), '📖', 'پست جدید در فید کلاس', 'author_id' ); // phpcs:ignore
		}
		// حضور و غیاب: به‌ازای هر کلاس-روز یک رویداد
		$att = HS_Attendance_DB::table_name();
		$add( $wpdb->get_results( $wpdb->prepare( "SELECT class_id, att_date, MAX(recorded_by) AS rb, MAX(created_at) AS ts, COUNT(*) AS n FROM {$att} WHERE class_id IN ({$in}) AND created_at >= %s GROUP BY class_id, att_date ORDER BY ts DESC LIMIT 30", $from ), ARRAY_A ), '✅', 'ثبت حضور و غیاب', 'rb' ); // phpcs:ignore
		usort( $ev, function ( $a, $b ) {
			return $b['ts'] <=> $a['ts'];
		} );
		return array_slice( $ev, 0, $limit );
	}

	/** جزئیات یک کلاس (HTML فقط‌خواندنی) */
	public static function class_detail_html( $class_id ) {
		global $wpdb;
		$class_id = (int) $class_id;
		$fa       = array( 'HS_SO_Store', 'fa' );
		$out      = '';

		// حضور و غیاب ۷ روز ثبت‌شدهٔ اخیر
		$att  = HS_Attendance_DB::table_name();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT att_date, SUM(status='present') p, SUM(status='absent') a, SUM(status='late') l, SUM(status='excused') e FROM {$att} WHERE class_id = %d GROUP BY att_date ORDER BY att_date DESC LIMIT 7", $class_id ), ARRAY_A ); // phpcs:ignore
		$out .= '<h4>✅ حضور و غیاب (۷ روز ثبت‌شدهٔ اخیر)</h4>';
		if ( $rows ) {
			$out .= '<table class="hs-table"><thead><tr><th>تاریخ</th><th>حاضر</th><th>غایب</th><th>تأخیر</th><th>موجه</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$out .= '<tr><td>' . esc_html( class_exists( 'HS_Util' ) ? HS_Util::jdate( $r['att_date'] ) : $r['att_date'] ) . '</td><td>' . esc_html( call_user_func( $fa, $r['p'] ) ) . '</td><td>' . esc_html( call_user_func( $fa, $r['a'] ) ) . '</td><td>' . esc_html( call_user_func( $fa, $r['l'] ) ) . '</td><td>' . esc_html( call_user_func( $fa, $r['e'] ) ) . '</td></tr>';
			}
			$out .= '</tbody></table>';
		} else {
			$out .= '<p class="hs-muted">هنوز حضور و غیابی ثبت نشده است.</p>';
		}

		$list = function ( $title, $rows, $date_key, $name_key = 'title' ) use ( $fa ) {
			$h = '<h4>' . $title . '</h4>';
			if ( ! $rows ) {
				return $h . '<p class="hs-muted">موردی ثبت نشده است.</p>';
			}
			$h .= '<ul class="hs-so-mini">';
			foreach ( $rows as $r ) {
				$h .= '<li><strong>' . esc_html( $r[ $name_key ] ) . '</strong><small>' . esc_html( HS_SO_Monitor::ago( strtotime( $r[ $date_key ] ) ) ) . '</small></li>';
			}
			return $h . '</ul>';
		};
		if ( class_exists( 'HS_Homework_DB' ) ) {
			$out .= $list( '📚 تکالیف اخیر', $wpdb->get_results( $wpdb->prepare( 'SELECT title, created_at FROM ' . HS_Homework_DB::hw_table() . ' WHERE class_id = %d ORDER BY id DESC LIMIT 5', $class_id ), ARRAY_A ), 'created_at' ); // phpcs:ignore
		}
		if ( class_exists( 'HS_Assessment_DB' ) ) {
			$out .= $list( '📝 ارزیابی‌های اخیر', $wpdb->get_results( $wpdb->prepare( 'SELECT title, created_at FROM ' . HS_Assessment_DB::assessments_table() . ' WHERE class_id = %d ORDER BY id DESC LIMIT 5', $class_id ), ARRAY_A ), 'created_at' ); // phpcs:ignore
		}
		if ( class_exists( 'HS_Comms_DB' ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT title, body, created_at FROM ' . HS_Comms_DB::announcements_table() . ' WHERE class_id = %d ORDER BY id DESC LIMIT 5', $class_id ), ARRAY_A ); // phpcs:ignore
			foreach ( $rows as &$r ) {
				$r['title'] = '' !== trim( $r['title'] ) ? $r['title'] : wp_html_excerpt( $r['body'], 50, '…' );
			}
			unset( $r );
			$out .= $list( '📢 اطلاعیه‌های اخیر', $rows, 'created_at' );
		}
		$out .= '<p class="hs-so-ro-note">🔒 نمای ناظر؛ این صفحه فقط برای مشاهده است و پیام خصوصی والدین نمایش داده نمی‌شود.</p>';
		return $out;
	}

	/** «۲ روز پیش» */
	public static function ago( $ts ) {
		if ( ! $ts ) {
			return 'هنوز فعالیتی ثبت نشده';
		}
		$d = max( 0, time() - $ts );
		if ( $d < 3600 ) {
			return 'همین الان';
		}
		if ( $d < 86400 ) {
			return HS_SO_Store::fa( floor( $d / 3600 ) ) . ' ساعت پیش';
		}
		if ( $d < 86400 * 60 ) {
			return HS_SO_Store::fa( floor( $d / 86400 ) ) . ' روز پیش';
		}
		return HS_SO_Store::fa( floor( $d / ( 86400 * 30 ) ) ) . ' ماه پیش';
	}
}
