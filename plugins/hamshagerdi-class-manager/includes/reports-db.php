<?php
/**
 * تجمیع داده‌های گزارش پیشرفت از حضور، امتیاز و ارزیابی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Reports {

	/**
	 * آمار کلی یک کلاس
	 */
	public static function class_overview( $class_id, $year = null, $month = null ) {
		$class_id = absint( $class_id );
		if ( ! $year ) {
			$year = (int) date( 'Y' );
		}
		if ( ! $month ) {
			$month = (int) date( 'n' );
		}

		$students = HS_Class_Students::get_students( $class_id );
		$student_count = count( $students );

		// حضور ماه جاری
		$att_summary = array();
		$recorded_days = 0;
		if ( class_exists( 'HS_Attendance_DB' ) ) {
			$att_summary   = HS_Attendance_DB::get_monthly_summary( $class_id, $year, $month );
			$recorded_days = HS_Attendance_DB::count_recorded_days( $class_id, $year, $month );
		}

		// امتیازات
		$scores = array();
		if ( class_exists( 'HS_Points_DB' ) ) {
			$scores = HS_Points_DB::get_class_leaderboard( $class_id );
		}

		// ارزیابی‌ها
		$assessments = array();
		if ( class_exists( 'HS_Assessment_DB' ) ) {
			$assessments = HS_Assessment_DB::get_class_assessments( $class_id, 10 );
		}

		// محاسبه نرخ حضور کلاس
		$total_present = 0;
		$total_slots   = 0;
		foreach ( $att_summary as $sid => $s ) {
			$p = (int) $s['present'] + (int) $s['late'] + (int) $s['excused'];
			$a = (int) $s['absent'];
			$total_present += $p;
			$total_slots   += $p + $a;
		}
		$attendance_rate = $total_slots > 0 ? round( ( $total_present / $total_slots ) * 100 ) : null;

		$points_total = $scores ? array_sum( $scores ) : 0;
		$points_avg   = $student_count > 0 && $scores ? round( $points_total / $student_count, 1 ) : 0;

		return array(
			'student_count'   => $student_count,
			'recorded_days'   => $recorded_days,
			'attendance_rate' => $attendance_rate,
			'points_total'    => $points_total,
			'points_avg'      => $points_avg,
			'assessment_count'=> count( $assessments ),
			'year'            => $year,
			'month'           => $month,
		);
	}

	/**
	 * گزارش تفصیلی هر دانش‌آموز در کلاس
	 */
	public static function students_progress( $class_id, $year = null, $month = null ) {
		$class_id = absint( $class_id );
		if ( ! $year ) {
			$year = (int) date( 'Y' );
		}
		if ( ! $month ) {
			$month = (int) date( 'n' );
		}

		$students = HS_Class_Students::get_students( $class_id );
		$att_summary = class_exists( 'HS_Attendance_DB' )
			? HS_Attendance_DB::get_monthly_summary( $class_id, $year, $month )
			: array();
		$scores = class_exists( 'HS_Points_DB' )
			? HS_Points_DB::get_class_leaderboard( $class_id )
			: array();

		// آخرین ارزیابی‌ها و نتایج
		$recent_assessments = array();
		$assessment_results = array(); // assessment_id => [ student_id => result ]
		if ( class_exists( 'HS_Assessment_DB' ) ) {
			$recent_assessments = HS_Assessment_DB::get_class_assessments( $class_id, 5 );
			foreach ( $recent_assessments as $a ) {
				$assessment_results[ (int) $a['id'] ] = HS_Assessment_DB::get_results( $a['id'] );
			}
		}

		$levels = class_exists( 'HS_Assessment_DB' )
			? HS_Assessment_DB::descriptive_levels()
			: array();

		$rows = array();
		foreach ( $students as $user ) {
			$sid = $user->ID;
			$att = isset( $att_summary[ $sid ] ) ? $att_summary[ $sid ] : array(
				'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0,
			);
			$att_total = $att['present'] + $att['absent'] + $att['late'] + $att['excused'];
			$att_good  = $att['present'] + $att['late'] + $att['excused'];
			$att_rate  = $att_total > 0 ? round( ( $att_good / $att_total ) * 100 ) : null;

			$points = isset( $scores[ $sid ] ) ? (int) $scores[ $sid ] : 0;

			$assess_items = array();
			foreach ( $recent_assessments as $a ) {
				$aid = (int) $a['id'];
				$r = isset( $assessment_results[ $aid ][ $sid ] ) ? $assessment_results[ $aid ][ $sid ] : null;
				$display = '—';
				if ( $r ) {
					if ( $a['scale_type'] === 'numeric' && $r['score'] !== null ) {
						$display = (string) $r['score'];
					} elseif ( ! empty( $r['level_key'] ) && isset( $levels[ $r['level_key'] ] ) ) {
						$display = $levels[ $r['level_key'] ]['label'];
					}
				}
				$assess_items[] = array(
					'title'   => $a['title'],
					'value'   => $display,
					'id'      => $aid,
				);
			}

			$rows[] = array(
				'id'           => $sid,
				'name'         => $user->display_name,
				'points'       => $points,
				'att_present'  => (int) $att['present'],
				'att_absent'   => (int) $att['absent'],
				'att_late'     => (int) $att['late'],
				'att_excused'  => (int) $att['excused'],
				'att_rate'     => $att_rate,
				'assessments'  => $assess_items,
			);
		}

		// مرتب‌سازی بر اساس امتیاز نزولی
		usort( $rows, function( $a, $b ) {
			return $b['points'] - $a['points'];
		} );

		return array(
			'students'     => $rows,
			'assessments'  => $recent_assessments,
			'year'         => $year,
			'month'        => $month,
		);
	}

	/**
	 * گزارش یک دانش‌آموز (برای ارسال به والد)
	 */
	public static function student_report( $class_id, $student_id, $year = null, $month = null ) {
		$class_id   = absint( $class_id );
		$student_id = absint( $student_id );
		if ( ! $year ) {
			$year = (int) date( 'Y' );
		}
		if ( ! $month ) {
			$month = (int) date( 'n' );
		}

		$user = get_userdata( $student_id );
		if ( ! $user ) {
			return null;
		}

		$class = get_post( $class_id );
		$att = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0 );
		if ( class_exists( 'HS_Attendance_DB' ) ) {
			$all = HS_Attendance_DB::get_monthly_summary( $class_id, $year, $month );
			if ( isset( $all[ $student_id ] ) ) {
				$att = $all[ $student_id ];
			}
		}

		$points = class_exists( 'HS_Points_DB' )
			? HS_Points_DB::get_student_total( $class_id, $student_id )
			: 0;
		$history = class_exists( 'HS_Points_DB' )
			? HS_Points_DB::get_student_history( $class_id, $student_id, 15 )
			: array();

		$assess_rows = array();
		if ( class_exists( 'HS_Assessment_DB' ) ) {
			$levels = HS_Assessment_DB::descriptive_levels();
			$list = HS_Assessment_DB::get_class_assessments( $class_id, 20 );
			foreach ( $list as $a ) {
				$results = HS_Assessment_DB::get_results( $a['id'] );
				$r = isset( $results[ $student_id ] ) ? $results[ $student_id ] : null;
				$value = '—';
				if ( $r ) {
					if ( $a['scale_type'] === 'numeric' && $r['score'] !== null ) {
						$value = (string) $r['score'];
					} elseif ( ! empty( $r['level_key'] ) && isset( $levels[ $r['level_key'] ] ) ) {
						$value = $levels[ $r['level_key'] ]['label'];
					}
					if ( ! empty( $r['comment'] ) ) {
						$value .= ' — ' . $r['comment'];
					}
				}
				$assess_rows[] = array(
					'title'   => $a['title'],
					'subject' => $a['subject'],
					'date'    => $a['assess_date'],
					'value'   => $value,
				);
			}
		}

		$att_total = $att['present'] + $att['absent'] + $att['late'] + $att['excused'];
		$att_good  = $att['present'] + $att['late'] + $att['excused'];
		$att_rate  = $att_total > 0 ? round( ( $att_good / $att_total ) * 100 ) : null;

		return array(
			'student_name'  => $user->display_name,
			'class_name'    => $class ? $class->post_title : '',
			'grade'         => get_post_meta( $class_id, '_hs_grade', true ),
			'year'          => $year,
			'month'         => $month,
			'points'        => $points,
			'points_history'=> $history,
			'attendance'    => $att,
			'att_rate'      => $att_rate,
			'assessments'   => $assess_rows,
		);
	}

	/**
	 * نام ماه شمسی تقریبی (بر اساس ماه میلادی فعلی برای نمایش)
	 * برای سادگی برچسب ماه میلادی + سال؛ در UI فارسی می‌نویسیم.
	 */
	public static function month_label( $month ) {
		$months = array(
			1 => 'ژانویه', 2 => 'فوریه', 3 => 'مارس', 4 => 'آوریل',
			5 => 'مه', 6 => 'ژوئن', 7 => 'ژوئیه', 8 => 'اوت',
			9 => 'سپتامبر', 10 => 'اکتبر', 11 => 'نوامبر', 12 => 'دسامبر',
		);
		return isset( $months[ $month ] ) ? $months[ $month ] : (string) $month;
	}
}
