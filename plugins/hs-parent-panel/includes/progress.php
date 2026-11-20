<?php
/**
 * گزارش پیشرفت ماهانه‌ی فرزند (قابل چاپ)
 * حضور، امتیاز، ارزیابی‌ها، تکالیف، تمرین در خانه — هر بخش با مجوز خودش.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Progress {

	/** ماه‌های اخیر: [ [y, m], ... ] (۰ = ماه جاری) */
	public static function months( $n = 6 ) {
		$out = array();
		$t   = strtotime( current_time( 'Y-m-01' ) );
		for ( $i = 0; $i < $n; $i++ ) {
			$ts    = strtotime( "-$i month", $t );
			$out[] = array( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ) );
		}
		return $out;
	}

	public static function month_label( $y, $m ) {
		$a = sprintf( '%04d-%02d-01', $y, $m );
		$b = gmdate( 'Y-m-t', strtotime( $a ) );
		return HSPP_Util::d( $a, false ) . ' تا ' . HSPP_Util::d( $b );
	}

	/** داده‌ی گزارش یک کلاس */
	public static function data( $pid, $sid, $cid, $y, $m ) {
		$can = function ( $f ) use ( $pid, $sid, $cid ) {
			return HSPP_Perms::can( $pid, $sid, $f, $cid );
		};
		$ym  = sprintf( '%04d-%02d', $y, $m );
		$d   = array( 'att' => null, 'points' => null, 'history' => array(), 'assess' => array(), 'hw' => null, 'practice' => null );

		if ( $can( 'attendance' ) && class_exists( 'HS_Attendance_DB' ) ) {
			$all = HS_Attendance_DB::get_monthly_summary( $cid, $y, $m );
			$a   = isset( $all[ $sid ] ) ? $all[ $sid ] : array();
			$a   = array_merge( array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0 ), array_intersect_key( (array) $a, array_flip( array( 'present', 'absent', 'late', 'excused' ) ) ) );
			$tot = array_sum( array_map( 'intval', $a ) );
			$a['rate'] = $tot ? (int) round( ( (int) $a['present'] + (int) $a['late'] + (int) $a['excused'] ) * 100 / $tot ) : null;
			$d['att']  = $a;
		}
		if ( $can( 'points' ) && class_exists( 'HS_Points_DB' ) ) {
			$d['points'] = 0;
			foreach ( (array) HS_Points_DB::get_student_history( $cid, $sid, 200 ) as $h ) {
				$when = (string) ( $h['created_at'] ?? '' );
				if ( 0 === strpos( $when, $ym ) ) {
					$d['points']   += (int) ( $h['points'] ?? 0 );
					$d['history'][] = $h;
				}
			}
			$d['total'] = (int) HS_Points_DB::get_student_total( $cid, $sid );
		}
		if ( $can( 'assessment' ) && class_exists( 'HS_Assessment_DB' ) ) {
			$levels = HS_Assessment_DB::descriptive_levels();
			foreach ( (array) HS_Assessment_DB::get_class_assessments( $cid, 100 ) as $a ) {
				$when = (string) ( $a['assess_date'] ? $a['assess_date'] : $a['created_at'] );
				if ( 0 !== strpos( $when, $ym ) ) {
					continue;
				}
				$res = HS_Assessment_DB::get_results( $a['id'] );
				$r   = isset( $res[ $sid ] ) ? $res[ $sid ] : null;
				$val = 'ثبت نشده';
				$col = '#94A3B8';
				if ( $r ) {
					if ( 'numeric' === $a['scale_type'] && null !== $r['score'] && '' !== $r['score'] ) {
						$val = 'نمره ' . rtrim( rtrim( (string) $r['score'], '0' ), '.' );
						$col = '#4F46E5';
					} elseif ( ! empty( $r['level_key'] ) && isset( $levels[ $r['level_key'] ] ) ) {
						$val = $levels[ $r['level_key'] ]['label'];
						$col = $levels[ $r['level_key'] ]['color'];
					}
				}
				$d['assess'][] = array( 'title' => $a['title'], 'subject' => $a['subject'], 'date' => $when, 'value' => $val, 'color' => $col, 'comment' => $r ? (string) $r['comment'] : '' );
			}
		}
		if ( $can( 'homework' ) && class_exists( 'HS_Homework_DB' ) ) {
			$tot  = 0;
			$done = 0;
			foreach ( (array) HS_Homework_DB::get_class_homework( $cid, 200 ) as $hw ) {
				$when = (string) ( ! empty( $hw['due_date'] ) ? $hw['due_date'] : $hw['created_at'] );
				if ( 0 !== strpos( $when, $ym ) ) {
					continue;
				}
				$tot++;
				$st = HS_Homework_DB::get_statuses( $hw['id'] );
				if ( ! empty( $st[ $sid ] ) && in_array( $st[ $sid ]['status'], array( 'done', 'late' ), true ) ) {
					$done++;
				}
			}
			$d['hw'] = array( $done, $tot );
		}
		if ( $can( 'practice' ) ) {
			$d['practice'] = HSPP_Practice::month_count( $cid, $sid, $ym );
		}
		return $d;
	}

	public static function render( $pid, $sid ) {
		$months = self::months();
		$i      = isset( $_GET['mo'] ) ? max( 0, min( count( $months ) - 1, (int) $_GET['mo'] ) ) : 0; // phpcs:ignore
		list( $y, $m ) = $months[ $i ];
		$u = get_userdata( $sid );

		$h  = '<section class="hs-pp-card hspp-report">';
		$h .= '<div class="hspp-rep-head"><h3>📊 گزارش پیشرفت ' . esc_html( $u ? $u->display_name : '' ) . '</h3>';
		$h .= '<form method="get" class="hspp-noprint"><input type="hidden" name="pp" value="progress"><input type="hidden" name="child" value="' . (int) $sid . '"><select name="mo" onchange="this.form.submit()">';
		foreach ( $months as $k => $mm ) {
			$h .= '<option value="' . (int) $k . '"' . selected( $k, $i, false ) . '>' . esc_html( self::month_label( $mm[0], $mm[1] ) ) . '</option>';
		}
		$h .= '</select> <button type="button" class="hs-pp-btn" onclick="window.print()">🖨 چاپ / PDF</button></form></div>';
		$h .= '<p class="hs-pp-muted">بازه: ' . esc_html( self::month_label( $y, $m ) ) . '</p></section>';

		$classes = HS_Parent_Link::student_classes( $sid );
		if ( ! $classes ) {
			return $h . '<section class="hs-pp-card"><p class="hs-pp-muted">فرزند شما هنوز در کلاسی نیست.</p></section>';
		}
		foreach ( $classes as $c ) {
			$d  = self::data( $pid, $sid, $c['id'], $y, $m );
			$h .= '<section class="hs-pp-card hspp-report"><h3>🏫 ' . esc_html( $c['title'] ) . ( $c['teacher'] ? ' <small>· معلم: ' . esc_html( $c['teacher'] ) . '</small>' : '' ) . '</h3><div class="hs-pp-stats">';
			if ( $d['att'] ) {
				$h .= '<div class="hs-pp-stat"><div class="num">' . ( null === $d['att']['rate'] ? '—' : (int) $d['att']['rate'] . '٪' ) . '</div><div class="lbl">حضور (' . (int) $d['att']['absent'] . ' غیبت، ' . (int) $d['att']['late'] . ' تأخیر)</div></div>';
			}
			if ( $d['hw'] ) {
				$h .= '<div class="hs-pp-stat"><div class="num">' . (int) $d['hw'][0] . '/' . (int) $d['hw'][1] . '</div><div class="lbl">تکلیف انجام‌شده</div></div>';
			}
			if ( null !== $d['points'] ) {
				$h .= '<div class="hs-pp-stat"><div class="num">' . (int) $d['points'] . '</div><div class="lbl">امتیاز این ماه (کل: ' . (int) $d['total'] . ')</div></div>';
			}
			if ( null !== $d['practice'] ) {
				$h .= '<div class="hs-pp-stat"><div class="num">' . (int) $d['practice'] . '</div><div class="lbl">تمرین در خانه</div></div>';
			}
			$h .= '</div>';
			if ( $d['assess'] ) {
				$h .= '<h4>ارزیابی‌ها</h4><table class="hspp-table"><thead><tr><th>عنوان</th><th>درس</th><th>تاریخ</th><th>نتیجه</th></tr></thead><tbody>';
				foreach ( $d['assess'] as $a ) {
					$h .= '<tr><td>' . esc_html( $a['title'] ) . '</td><td>' . esc_html( $a['subject'] ) . '</td><td>' . esc_html( HSPP_Util::d( $a['date'], false ) ) . '</td><td><span class="hspp-lv" style="--c:' . esc_attr( $a['color'] ) . '">' . esc_html( $a['value'] ) . '</span>'
						. ( $a['comment'] ? '<small class="hspp-cm">' . esc_html( $a['comment'] ) . '</small>' : '' ) . '</td></tr>';
				}
				$h .= '</tbody></table>';
			} elseif ( HSPP_Perms::can( $pid, $sid, 'assessment', $c['id'] ) ) {
				$h .= '<p class="hs-pp-muted">در این ماه ارزیابی ثبت نشده.</p>';
			}
			if ( $d['history'] ) {
				$h .= '<h4>تشویق‌ها و یادداشت‌های رفتاری</h4><ul class="hs-pp-list">';
				foreach ( array_slice( $d['history'], 0, 10 ) as $p ) {
					$pts = (int) ( $p['points'] ?? 0 );
					$h  .= '<li><strong>' . esc_html( ( $p['behavior_icon'] ?? '' ) . ' ' . ( $p['behavior_name'] ?? '' ) ) . '</strong><span>' . ( $pts > 0 ? '+' : '' ) . $pts . ( ! empty( $p['note'] ) ? ' — ' . esc_html( $p['note'] ) : '' ) . '</span></li>';
				}
				$h .= '</ul>';
			}
			$h .= '</section>';
		}
		return $h;
	}
}
