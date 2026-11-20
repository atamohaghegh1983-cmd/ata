<?php
/**
 * تحلیل نمرات: داشبورد نموداری ارزیابی‌های یک کلاس و هر دانش‌آموز (فقط معلم/مدیر).
 *
 * داده‌ها از جدول‌های موجود خوانده می‌شوند (ارزیابی، حضور، تکالیف) و چیزی ذخیره نمی‌شود.
 * نمره‌ی عددی (۰ تا ۲۰) و توصیفی (خیلی خوب … نیاز به تلاش) هر دو به «درصد» تبدیل می‌شوند
 * تا روی یک نمودار قابل مقایسه باشند. نمودارها SVG سمت سرورند (بدون کتابخانه‌ی خارجی).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Grades_Insights {

	const SLUG = 'insights';

	/** معادل درصدی سطوح توصیفی */
	const LEVEL_PCT = array(
		'excellent'  => 100,
		'good'       => 75,
		'acceptable' => 50,
		'needs_work' => 25,
	);

	/** مرز سطوح روی درصد (معادل ۱۷، ۱۴ و ۱۰ از ۲۰) */
	const T_EXCELLENT = 85;
	const T_GOOD      = 70;
	const T_PASS      = 50;

	/** نمودار روند حداکثر چند ارزیابی آخر را نشان دهد */
	const MAX_POINTS = 12;

	public static function init() {
		add_shortcode( 'hamshagerdi_grades_insights', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 100 );
	}

	public static function enqueue() {
		global $post;
		if ( ! is_user_logged_in() ) {
			return;
		}
		$on_page = is_page( self::SLUG ) || ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'hamshagerdi_grades_insights' ) );
		if ( ! $on_page ) {
			return;
		}
		wp_enqueue_style( 'hs-grades-insights', HS_CLASS_URL . 'assets/css/grades-insights.css', array( 'hs-core' ), HS_CLASS_VERSION );
	}

	/* =============== ورودی =============== */

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای استفاده باید وارد شوید.</div>';
		}
		if ( ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان در دسترس است.</div>';
		}

		$classes = self::classes();
		// phpcs:disable WordPress.Security.NonceVerification -- فقط خواندنی (GET)
		$cid = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;
		$sid = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
		// phpcs:enable
		$ids = wp_list_pluck( $classes, 'id' );
		if ( ! in_array( $cid, $ids, true ) ) {
			$cid = $ids ? $ids[0] : 0;
		}

		$html  = '<div class="hs-gi">';
		$html .= '<div class="hs-gi-head"><div><h2>📊 تحلیل نمرات</h2><p class="hs-muted">روند کلاس، نقاط قوت و ضعف درس‌ها و دانش‌آموزانی که به توجه نیاز دارند</p></div>';
		if ( count( $classes ) > 1 ) {
			$html .= self::class_picker( $classes, $cid );
		}
		$html .= '</div>';

		if ( ! $cid ) {
			$html .= self::empty_state( '🏫', 'هنوز کلاسی ندارید', 'classes', '+ ساخت کلاس' );
			return $html . '</div>';
		}
		if ( ! HS_Roles::can_manage_class( $cid ) ) {
			return $html . '<div class="hs-alert hs-alert-warning">به این کلاس دسترسی ندارید.</div></div>';
		}

		$data = self::collect( $cid );
		if ( ! $data['assess'] ) {
			$html .= self::empty_state( '📝', 'هنوز ارزیابی‌ای برای این کلاس ثبت نشده است', 'assessment', '+ ساخت ارزیابی', 'بعد از ثبت اولین ارزیابی، نمودارها این‌جا ساخته می‌شوند.' );
			return $html . '</div>';
		}

		if ( $sid && isset( $data['students'][ $sid ] ) ) {
			$html .= self::student_view( $cid, $sid, $data );
		} else {
			$html .= self::class_view( $cid, $data );
		}
		return $html . '</div>';
	}

	/* =============== داده‌ها =============== */

	/** کلاس‌هایی که کاربر مدیریت می‌کند: [[id, title], ...] */
	private static function classes() {
		$args = array(
			'post_type'      => 'hs_class',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( ! current_user_can( 'hs_manage_all_classes' ) ) {
			$args['meta_query'] = array( array( 'key' => '_hs_teacher_id', 'value' => get_current_user_id() ) ); // phpcs:ignore
		}
		$out = array();
		foreach ( get_posts( $args ) as $p ) {
			$out[] = array( 'id' => (int) $p->ID, 'title' => get_the_title( $p ) );
		}
		return $out;
	}

	/** نمره‌ی یک نتیجه به درصد (۰ تا ۱۰۰) یا null برای غایب/ثبت‌نشده */
	public static function to_pct( $scale, $level, $score ) {
		if ( 'numeric' === $scale ) {
			if ( null === $score || '' === $score ) {
				return null;
			}
			return max( 0.0, min( 100.0, (float) $score * 5 ) );
		}
		return isset( self::LEVEL_PCT[ (string) $level ] ) ? (float) self::LEVEL_PCT[ (string) $level ] : null;
	}

	/** سطح یک درصد: excellent | good | acceptable | needs_work */
	public static function level_of( $pct ) {
		if ( $pct >= self::T_EXCELLENT ) {
			return 'excellent';
		}
		if ( $pct >= self::T_GOOD ) {
			return 'good';
		}
		if ( $pct >= self::T_PASS ) {
			return 'acceptable';
		}
		return 'needs_work';
	}

	private static function avg( $vals ) {
		$vals = array_filter( (array) $vals, function ( $v ) {
			return null !== $v;
		} );
		return $vals ? array_sum( $vals ) / count( $vals ) : null;
	}

	/**
	 * همه‌ی داده‌ی تحلیل یک کلاس با چند پرس‌وجوی گروهی.
	 *
	 * @return array {
	 *   students: [sid => name], assess: [aid => row + avg], pct: [sid => [aid => pct]],
	 *   raw: [sid => [aid => result row]], att: [sid => rate|null], hw: [list], hw_status: [sid => [hid => status]],
	 *   missing: [sid => n], stats: [sid => [...]], all_numeric: bool (most assessments numeric)
	 * }
	 */
	private static function collect( $cid ) {
		global $wpdb;
		$a = HS_Assessment_DB::assessments_table();
		$r = HS_Assessment_DB::results_table();

		$students = array();
		foreach ( HS_Class_Students::get_students( $cid ) as $u ) {
			$students[ (int) $u->ID ] = $u->display_name;
		}

		$assess = array();
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, subject, scale_type, assess_date FROM {$a} WHERE class_id = %d ORDER BY assess_date ASC, id ASC", $cid ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $row ) {
			$row['subject']           = trim( (string) $row['subject'] );
			$assess[ (int) $row['id'] ] = $row;
		}

		$pct = array();
		$raw = array();
		if ( $assess ) {
			$res = $wpdb->get_results( $wpdb->prepare( "SELECT r.assessment_id, r.student_id, r.level_key, r.score, r.comment FROM {$r} r INNER JOIN {$a} a ON a.id = r.assessment_id WHERE a.class_id = %d", $cid ), ARRAY_A ); // phpcs:ignore
			foreach ( (array) $res as $row ) {
				$aid = (int) $row['assessment_id'];
				$sid = (int) $row['student_id'];
				if ( ! isset( $students[ $sid ], $assess[ $aid ] ) ) {
					continue; // دانش‌آموز حذف‌شده از کلاس
				}
				$raw[ $sid ][ $aid ] = $row;
				$p                   = self::to_pct( $assess[ $aid ]['scale_type'], $row['level_key'], $row['score'] );
				if ( null !== $p ) {
					$pct[ $sid ][ $aid ] = $p;
				}
			}
		}

		$numeric_n = 0;
		foreach ( $assess as $aid => $row ) {
			$vals                   = array();
			foreach ( $pct as $per ) {
				if ( isset( $per[ $aid ] ) ) {
					$vals[] = $per[ $aid ];
				}
			}
			$assess[ $aid ]['avg'] = self::avg( $vals );
			$assess[ $aid ]['n']   = count( $vals );
			if ( 'numeric' === $row['scale_type'] ) {
				$numeric_n++;
			}
		}
		// بیشتر ارزیابی‌ها نمره‌ای باشند ← میانگین‌ها «از ۲۰» نمایش داده می‌شوند، وگرنه با برچسب سطح
		$all_numeric = $assess && $numeric_n * 2 >= count( $assess );

		// حضور ۶۰ روز اخیر
		$att   = array();
		$today = current_time( 'Y-m-d' );
		$from  = gmdate( 'Y-m-d', strtotime( $today . ' 12:00:00 UTC' ) - 60 * DAY_IN_SECONDS );
		$t     = HS_Attendance_DB::table_name();
		$cnt   = array();
		$res   = $wpdb->get_results( $wpdb->prepare( "SELECT student_id, status, COUNT(*) c FROM {$t} WHERE class_id = %d AND att_date BETWEEN %s AND %s GROUP BY student_id, status", $cid, $from, $today ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $res as $row ) {
			$s = (int) $row['student_id'];
			if ( ! isset( $cnt[ $s ] ) ) {
				$cnt[ $s ] = array( 'ok' => 0, 'all' => 0 );
			}
			$cnt[ $s ]['all'] += (int) $row['c'];
			if ( in_array( $row['status'], array( 'present', 'late' ), true ) ) {
				$cnt[ $s ]['ok'] += (int) $row['c'];
			}
		}
		foreach ( $students as $s => $_ ) {
			$att[ $s ] = ! empty( $cnt[ $s ]['all'] ) ? (int) round( $cnt[ $s ]['ok'] / $cnt[ $s ]['all'] * 100 ) : null;
		}

		// ۸ تکلیف آخر و وضعیت هر دانش‌آموز
		$hw_t = HS_Homework_DB::hw_table();
		$st_t = HS_Homework_DB::status_table();
		$hw   = array_reverse( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, title, due_date, created_at FROM {$hw_t} WHERE class_id = %d ORDER BY COALESCE(due_date, created_at) DESC, id DESC LIMIT 8", $cid ), ARRAY_A ) ); // phpcs:ignore
		$hw_status = array();
		$missing   = array_fill_keys( array_keys( $students ), 0 );
		if ( $hw ) {
			$in  = implode( ',', array_map( 'absint', wp_list_pluck( $hw, 'id' ) ) );
			$res = $wpdb->get_results( "SELECT homework_id, student_id, status FROM {$st_t} WHERE homework_id IN ({$in})", ARRAY_A ); // phpcs:ignore
			foreach ( (array) $res as $row ) {
				$s = (int) $row['student_id'];
				$hw_status[ $s ][ (int) $row['homework_id'] ] = $row['status'];
				if ( 'missing' === $row['status'] && isset( $missing[ $s ] ) ) {
					$missing[ $s ]++;
				}
			}
		}

		$data = compact( 'students', 'assess', 'pct', 'raw', 'att', 'hw', 'hw_status', 'missing', 'all_numeric' );
		$data['stats'] = array();
		foreach ( $students as $s => $_ ) {
			$data['stats'][ $s ] = self::student_stats( $s, $data );
		}
		return $data;
	}

	/** میانگین، روند اخیر و هشدارهای یک دانش‌آموز */
	private static function student_stats( $sid, $data ) {
		$series = array();
		foreach ( $data['assess'] as $aid => $_ ) {
			if ( isset( $data['pct'][ $sid ][ $aid ] ) ) {
				$series[] = $data['pct'][ $sid ][ $aid ];
			}
		}
		$avg   = self::avg( $series );
		$delta = null;
		if ( count( $series ) >= 3 ) {
			$recent = array_slice( $series, -2 );
			$before = array_slice( $series, 0, -2 );
			$delta  = self::avg( $recent ) - self::avg( $before );
		}
		$flags = array();
		if ( null !== $avg && $avg < self::T_PASS ) {
			$flags[] = array( 'low', 'میانگین پایین' );
		}
		if ( null !== $delta && $delta <= -15 ) {
			$flags[] = array( 'drop', 'افت اخیر' );
		}
		if ( null !== $data['att'][ $sid ] && $data['att'][ $sid ] < 80 ) {
			$flags[] = array( 'att', 'غیبت زیاد' );
		}
		if ( $data['missing'][ $sid ] >= 2 ) {
			$flags[] = array( 'hw', 'تکلیف انجام‌نشده' );
		}
		return array(
			'avg'    => $avg,
			'delta'  => $delta,
			'series' => $series,
			'flags'  => $flags,
		);
	}

	/** میانگین هر درس: [درس => درصد] (فقط ارزیابی‌های دارای درس) */
	private static function subject_avgs( $data, $sid = 0 ) {
		$bucket = array();
		foreach ( $data['assess'] as $aid => $row ) {
			if ( '' === $row['subject'] ) {
				continue;
			}
			if ( $sid ) {
				if ( isset( $data['pct'][ $sid ][ $aid ] ) ) {
					$bucket[ $row['subject'] ][] = $data['pct'][ $sid ][ $aid ];
				}
			} else {
				foreach ( $data['pct'] as $per ) {
					if ( isset( $per[ $aid ] ) ) {
						$bucket[ $row['subject'] ][] = $per[ $aid ];
					}
				}
			}
		}
		$out = array();
		foreach ( $bucket as $sub => $vals ) {
			$out[ $sub ] = self::avg( $vals );
		}
		arsort( $out );
		return $out;
	}

	/* =============== نماها =============== */

	private static function class_view( $cid, $data ) {
		$class_avg = self::avg( array_column( $data['assess'], 'avg' ) );
		$at_risk   = 0;
		foreach ( $data['stats'] as $st ) {
			if ( $st['flags'] ) {
				$at_risk++;
			}
		}
		$att_vals = array_filter( $data['att'], function ( $v ) {
			return null !== $v;
		} );
		$att_avg  = $att_vals ? (int) round( array_sum( $att_vals ) / count( $att_vals ) ) : null;

		// سهم سطوح در همه‌ی نتایج
		$levels = HS_Assessment_DB::descriptive_levels();
		$dist   = array( 'excellent' => 0, 'good' => 0, 'acceptable' => 0, 'needs_work' => 0 );
		foreach ( $data['pct'] as $per ) {
			foreach ( $per as $p ) {
				$dist[ self::level_of( $p ) ]++;
			}
		}

		$html  = '<div class="hs-stats">';
		$html .= self::kpi( '🎯', 'میانگین کلاس', self::grade_text( $class_avg, $data['all_numeric'] ), 'violet', null === $class_avg ? '' : self::level_label( $class_avg ) . ' · ' . self::fa_pct( $class_avg ) );
		$html .= self::kpi( '📝', 'ارزیابی‌های ثبت‌شده', HS_Util::fa_digits( count( $data['assess'] ) ), 'blue', HS_Util::fa_digits( count( $data['students'] ) ) . ' دانش‌آموز' );
		$html .= self::kpi( '✅', 'حضور ۶۰ روز اخیر', null === $att_avg ? '—' : HS_Util::fa_digits( $att_avg ) . '٪', 'green', 'حاضر + با تأخیر' );
		$html .= self::kpi( '⚠️', 'نیاز به توجه', HS_Util::fa_digits( $at_risk ), $at_risk ? 'pink' : 'teal', $at_risk ? 'دانش‌آموز · جدول پایین' : 'همه خوب‌اند 🎉' );
		$html .= '</div>';

		// روند کلاس
		$pts    = array_slice( $data['assess'], -self::MAX_POINTS, null, true );
		$labels = array();
		$vals   = array();
		foreach ( $pts as $row ) {
			$labels[] = array( HS_Util::jdate( $row['assess_date'], false ), $row['title'] . ( $row['subject'] ? ' · ' . $row['subject'] : '' ) );
			$vals[]   = $row['avg'];
		}
		$html .= '<div class="hs-grid hs-gi-row">';
		$html .= self::card( '📈 روند میانگین کلاس', 'هر نقطه یک ارزیابی · خط‌چین: مرز «خوب» (۱۴ از ۲۰)', self::line_chart( $labels, array( array( 'میانگین کلاس', 'var(--hs-primary)', $vals, false ) ) ) );
		$html .= self::card( '🥧 پراکندگی سطح‌ها', 'همه‌ی نتایج ثبت‌شده', self::donut( array(
			array( $levels['excellent']['label'] . ' (۱۷+)', $dist['excellent'], $levels['excellent']['color'] ),
			array( $levels['good']['label'] . ' (۱۴–۱۷)', $dist['good'], $levels['good']['color'] ),
			array( $levels['acceptable']['label'] . ' (۱۰–۱۴)', $dist['acceptable'], $levels['acceptable']['color'] ),
			array( $levels['needs_work']['label'], $dist['needs_work'], $levels['needs_work']['color'] ),
		), 'نتیجه' ) );
		$html .= '</div>';

		$subs  = self::subject_avgs( $data );
		$html .= '<div class="hs-grid hs-gi-row2">';
		$html .= self::card( '📚 میانگین هر درس', 'مرتب‌شده · خط‌چین: مرز «خوب»', $subs ? self::hbars( $subs ) : self::note( 'برای دیدن این نمودار، هنگام ساخت ارزیابی «درس» را هم بنویسید.' ) );
		$html .= self::card( '🗓️ تحویل تکالیف', 'تکالیف اخیر · هر خانه وضعیت یک دانش‌آموز', self::hw_grid( $data ) );
		$html .= '</div>';

		$html .= self::card( '🧒 دانش‌آموزان', 'روی نام هر دانش‌آموز بزنید تا جزئیاتش را ببینید', self::students_table( $cid, $data ) );
		return $html;
	}

	private static function student_view( $cid, $sid, $data ) {
		$st     = $data['stats'][ $sid ];
		$name   = $data['students'][ $sid ];
		$back   = add_query_arg( 'class_id', $cid, HS_Shell::url( self::SLUG ) );
		$html   = '<div class="hs-gi-student"><a class="hs-back-link" href="' . esc_url( $back ) . '">→ بازگشت به کل کلاس</a>';
		$html  .= '<div class="hs-gi-student-name"><span class="hs-av hs-av-lg hs-av-' . ( $sid % 5 ) . '">' . esc_html( self::initial( $name ) ) . '</span><div><h3>' . esc_html( $name ) . '</h3>';
		foreach ( $st['flags'] as $f ) {
			$html .= '<span class="hs-gi-flag hs-gi-flag-' . esc_attr( $f[0] ) . '">' . esc_html( $f[1] ) . '</span> ';
		}
		$html .= '</div></div></div>';

		$delta_txt = null === $st['delta'] ? 'برای روند حداقل ۳ ارزیابی لازم است' : ( $st['delta'] >= 0 ? '▲ ' : '▼ ' ) . self::fa_num( abs( $st['delta'] ) / 5 ) . ' نمره در دو ارزیابی اخیر';
		$html     .= '<div class="hs-stats">';
		$html     .= self::kpi( '🎯', 'میانگین', self::grade_text( $st['avg'], $data['all_numeric'] ), 'violet', null === $st['avg'] ? '' : self::level_label( $st['avg'] ) . ' · ' . self::fa_pct( $st['avg'] ) );
		$html     .= self::kpi( null !== $st['delta'] && $st['delta'] < 0 ? '📉' : '📈', 'روند', null === $st['delta'] ? '—' : ( $st['delta'] >= 0 ? 'رو به بالا' : 'رو به پایین' ), null !== $st['delta'] && $st['delta'] < 0 ? 'pink' : 'green', $delta_txt );
		$html     .= self::kpi( '✅', 'حضور ۶۰ روز اخیر', null === $data['att'][ $sid ] ? '—' : HS_Util::fa_digits( $data['att'][ $sid ] ) . '٪', 'blue', 'حاضر + با تأخیر' );
		$html     .= self::kpi( '📚', 'تکلیف انجام‌نشده', HS_Util::fa_digits( $data['missing'][ $sid ] ), $data['missing'][ $sid ] ? 'orange' : 'teal', 'از ' . HS_Util::fa_digits( count( $data['hw'] ) ) . ' تکلیف اخیر' );
		$html     .= '</div>';

		$pts    = array_slice( $data['assess'], -self::MAX_POINTS, null, true );
		$labels = array();
		$mine   = array();
		$cls    = array();
		foreach ( $pts as $aid => $row ) {
			$labels[] = array( HS_Util::jdate( $row['assess_date'], false ), $row['title'] . ( $row['subject'] ? ' · ' . $row['subject'] : '' ) );
			$mine[]   = isset( $data['pct'][ $sid ][ $aid ] ) ? $data['pct'][ $sid ][ $aid ] : null;
			$cls[]    = $row['avg'];
		}
		$html .= '<div class="hs-grid hs-gi-row">';
		$html .= self::card( '📈 روند نمره', 'در مقایسه با میانگین کلاس · خط‌چین نارنجی: مرز «خوب»', self::line_chart( $labels, array(
			array( 'میانگین کلاس', '#94A3B8', $cls, true ),
			array( $name, 'var(--hs-primary)', $mine, false ),
		) ) );

		$mine_s = self::subject_avgs( $data, $sid );
		$cls_s  = self::subject_avgs( $data );
		if ( count( $mine_s ) >= 3 ) {
			$axes = array_slice( array_keys( $mine_s ), 0, 8 );
			$body = self::radar( $axes, $mine_s, $cls_s, $name );
		} elseif ( $mine_s ) {
			$body = self::hbars( $mine_s );
		} else {
			$body = self::note( 'برای این نمودار، هنگام ساخت ارزیابی «درس» را هم بنویسید.' );
		}
		$html .= self::card( '🧭 نقاط قوت و ضعف', 'میانگین هر درس در برابر کلاس', $body );
		$html .= '</div>';

		// فهرست ارزیابی‌ها
		$levels = HS_Assessment_DB::descriptive_levels();
		$rows   = '';
		foreach ( array_reverse( $data['assess'], true ) as $aid => $row ) {
			$res = isset( $data['raw'][ $sid ][ $aid ] ) ? $data['raw'][ $sid ][ $aid ] : null;
			if ( ! $res ) {
				$val = '<span class="hs-muted">ثبت نشده</span>';
			} elseif ( 'numeric' === $row['scale_type'] ) {
				$val = null === $res['score'] || '' === $res['score'] ? '<span class="hs-muted">—</span>' : self::pill( self::fa_num( (float) $res['score'] ), self::level_of( (float) $res['score'] * 5 ) );
			} else {
				$k   = (string) $res['level_key'];
				$val = isset( $levels[ $k ] ) ? '<span class="hs-gi-pill" style="--lv:' . esc_attr( $levels[ $k ]['color'] ) . '">' . esc_html( $levels[ $k ]['label'] ) . '</span>' : '<span class="hs-muted">—</span>';
			}
			$rows .= '<tr><td>' . esc_html( HS_Util::jdate( $row['assess_date'] ) ) . '</td><td><strong>' . esc_html( $row['title'] ) . '</strong></td><td>' . esc_html( $row['subject'] ?: '—' ) . '</td><td>' . $val . '</td>'
				. '<td>' . esc_html( null === $row['avg'] ? '—' : self::grade_text( $row['avg'], 'numeric' === $row['scale_type'] ) ) . '</td><td class="hs-gi-comment">' . esc_html( $res ? (string) $res['comment'] : '' ) . '</td></tr>';
		}
		$table = '<div class="hs-table-wrap"><table class="hs-table"><thead><tr><th>تاریخ</th><th>ارزیابی</th><th>درس</th><th>نتیجه</th><th>میانگین کلاس</th><th>یادداشت معلم</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
		$html .= self::card( '📝 همه‌ی ارزیابی‌ها', 'جدیدترین بالا', $table );
		return $html;
	}

	/* =============== اجزای رابط =============== */

	private static function class_picker( $classes, $cid ) {
		$html = '<form class="hs-gi-picker" method="get" action="' . esc_url( HS_Shell::url( self::SLUG ) ) . '"><label for="hs-gi-class">کلاس</label><select id="hs-gi-class" name="class_id" onchange="this.form.submit()">';
		foreach ( $classes as $c ) {
			$html .= '<option value="' . esc_attr( $c['id'] ) . '"' . selected( $cid, $c['id'], false ) . '>' . esc_html( $c['title'] ) . '</option>';
		}
		return $html . '</select><noscript><button class="hs-btn hs-btn-sm hs-btn-primary">نمایش</button></noscript></form>';
	}

	private static function card( $title, $hint, $body ) {
		return '<section class="hs-card hs-dash-card hs-gi-card"><div class="hs-card-head"><div><h3>' . esc_html( $title ) . '</h3><p class="hs-gi-hint">' . esc_html( $hint ) . '</p></div></div>' . $body . '</section>';
	}

	private static function kpi( $icon, $label, $value, $color, $sub = '' ) {
		return '<div class="hs-stat hs-c-' . esc_attr( $color ) . '"><span class="hs-stat-ico" aria-hidden="true">' . esc_html( $icon ) . '</span>'
			. '<div class="hs-stat-body"><div class="hs-stat-label">' . esc_html( $label ) . '</div><div class="hs-stat-val">' . esc_html( $value ) . '</div>'
			. ( $sub ? '<div class="hs-stat-sub">' . esc_html( $sub ) . '</div>' : '' ) . '</div></div>';
	}

	private static function empty_state( $icon, $title, $slug, $btn, $text = '' ) {
		return '<div class="hs-card hs-empty-state"><div class="hs-empty-icon">' . esc_html( $icon ) . '</div><h3>' . esc_html( $title ) . '</h3>'
			. ( $text ? '<p class="hs-muted">' . esc_html( $text ) . '</p>' : '' )
			. '<a class="hs-btn hs-btn-primary" href="' . esc_url( HS_Shell::url( $slug ) ) . '">' . esc_html( $btn ) . '</a></div>';
	}

	private static function note( $text ) {
		return '<p class="hs-gi-note">' . esc_html( $text ) . '</p>';
	}

	private static function initial( $name ) {
		return function_exists( 'mb_substr' ) ? mb_substr( trim( $name ), 0, 1 ) : substr( trim( $name ), 0, 1 );
	}

	/** عدد با ممیز فارسی: 16.5 ← «۱۶٫۵» */
	private static function fa_num( $n ) {
		$s = rtrim( rtrim( number_format( round( (float) $n, 1 ), 1, '.', '' ), '0' ), '.' );
		return str_replace( '.', '٫', HS_Util::fa_digits( $s ) );
	}

	private static function fa_pct( $p ) {
		return HS_Util::fa_digits( (int) round( $p ) ) . '٪';
	}

	/** نمایش میانگین: «۱۶٫۵ از ۲۰» برای کلاس‌های نمره‌ای، وگرنه برچسب سطح */
	private static function grade_text( $pct, $numeric ) {
		if ( null === $pct ) {
			return '—';
		}
		return $numeric ? self::fa_num( $pct / 5 ) . ' از ۲۰' : self::level_label( $pct );
	}

	private static function level_label( $pct ) {
		$levels = HS_Assessment_DB::descriptive_levels();
		return $levels[ self::level_of( $pct ) ]['label'];
	}

	private static function level_color( $level ) {
		$levels = HS_Assessment_DB::descriptive_levels();
		return $levels[ $level ]['color'];
	}

	private static function pill( $text, $level ) {
		return '<span class="hs-gi-pill" style="--lv:' . esc_attr( self::level_color( $level ) ) . '">' . esc_html( $text ) . '</span>';
	}

	/* =============== نمودارها (SVG، راست‌به‌چپ) =============== */

	/**
	 * نمودار خطی؛ اولین برچسب سمت راست.
	 *
	 * @param array $labels [[برچسب کوتاه، عنوان کامل], ...]
	 * @param array $series [[نام، رنگ، مقادیر درصدی|null، خط‌چین؟], ...]
	 */
	private static function line_chart( $labels, $series ) {
		$n = count( $labels );
		$w = 640;
		$h = 250;
		$l = 16;
		$r = 44;
		$t = 14;
		$b = 34;
		$x = function ( $i ) use ( $n, $w, $l, $r ) {
			return $n > 1 ? $w - $r - $i * ( ( $w - $r - $l ) / ( $n - 1 ) ) : ( $w - $r + $l ) / 2;
		};
		$y = function ( $v ) use ( $h, $t, $b ) {
			return $t + ( 100 - $v ) / 100 * ( $h - $t - $b );
		};

		$aria = 'نمودار روند: ' . implode( '، ', wp_list_pluck( $series, 0 ) );
		$svg  = '<svg class="hs-gi-svg" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr( $aria ) . '">';
		foreach ( array( 0, 5, 10, 15, 20 ) as $g ) {
			$gy   = $y( $g * 5 );
			$svg .= '<line x1="' . $l . '" x2="' . ( $w - $r ) . '" y1="' . $gy . '" y2="' . $gy . '" class="hs-gi-grid"/>';
			$svg .= '<text x="' . ( $w - $r + 10 ) . '" y="' . ( $gy + 4 ) . '" class="hs-gi-axis">' . esc_html( HS_Util::fa_digits( $g ) ) . '</text>';
		}
		$ty   = $y( self::T_GOOD );
		$svg .= '<line x1="' . $l . '" x2="' . ( $w - $r ) . '" y1="' . $ty . '" y2="' . $ty . '" class="hs-gi-target"/>';

		$step = max( 1, (int) ceil( $n / 8 ) ); // برچسب‌ها روی هم نیفتند
		foreach ( $labels as $i => $lb ) {
			if ( 0 === ( $n - 1 - $i ) % $step ) { // آخرین ارزیابی همیشه برچسب دارد
				$svg .= '<text x="' . $x( $i ) . '" y="' . ( $h - 10 ) . '" text-anchor="middle" class="hs-gi-axis">' . esc_html( $lb[0] ) . '</text>';
			}
		}

		foreach ( $series as $si => $s ) {
			list( $name, $color, $vals, $dashed ) = $s;
			$d   = '';
			$pen = false;
			foreach ( $vals as $i => $v ) {
				if ( null === $v ) {
					$pen = false;
					continue;
				}
				$d  .= ( $pen ? 'L' : 'M' ) . round( $x( $i ), 1 ) . ' ' . round( $y( $v ), 1 ) . ' ';
				$pen = true;
			}
			$last = count( $series ) - 1 === $si;
			if ( $last && ! $dashed && $d ) {
				$svg .= '<path d="' . esc_attr( $d ) . '" class="hs-gi-line" style="stroke:' . esc_attr( $color ) . '"/>';
			} elseif ( $d ) {
				$svg .= '<path d="' . esc_attr( $d ) . '" class="hs-gi-line hs-gi-line-soft" style="stroke:' . esc_attr( $color ) . '"' . ( $dashed ? ' stroke-dasharray="6 4"' : '' ) . '/>';
			}
			if ( ! $last ) {
				continue;
			}
			foreach ( $vals as $i => $v ) {
				if ( null === $v ) {
					continue;
				}
				$low  = $v < self::T_PASS;
				$tip  = $labels[ $i ][1] . ' (' . $labels[ $i ][0] . '): ' . self::fa_num( $v / 5 ) . ' از ۲۰ · ' . self::level_label( $v );
				$svg .= '<circle cx="' . round( $x( $i ), 1 ) . '" cy="' . round( $y( $v ), 1 ) . '" r="' . ( $low ? 6 : 4.5 ) . '" class="hs-gi-dot' . ( $low ? ' is-low' : '' ) . '" style="stroke:' . esc_attr( $color ) . '"><title>' . esc_html( $tip ) . '</title></circle>';
			}
		}
		$svg .= '</svg>';

		$legend = '<div class="hs-gi-legend">';
		foreach ( $series as $s ) {
			$legend .= '<span><i style="background:' . esc_attr( $s[1] ) . '"></i>' . esc_html( $s[0] ) . '</span>';
		}
		$legend .= '<span><i class="hs-gi-legend-target"></i>مرز «خوب»</span><span><i class="hs-gi-legend-low"></i>زیر ۱۰</span></div>';
		return ( count( $series ) > 1 ? $legend : '' ) . '<div class="hs-gi-scroll">' . $svg . '</div>';
	}

	/** دونات: [[برچسب، تعداد، رنگ], ...] */
	private static function donut( $segments, $unit ) {
		$total = array_sum( array_map( function ( $s ) {
			return max( 0, (int) $s[1] );
		}, $segments ) );
		$r     = 52;
		$circ  = 2 * M_PI * $r;
		$svg   = '<svg class="hs-gi-donut" viewBox="0 0 140 140" role="img" aria-label="' . esc_attr( 'پراکندگی سطح‌ها از ' . $total . ' ' . $unit ) . '"><circle cx="70" cy="70" r="' . $r . '" fill="none" stroke="#EEF0F7" stroke-width="18"/>';
		$off   = 0;
		foreach ( $segments as $s ) {
			$len = $total ? max( 0, (int) $s[1] ) / $total * $circ : 0;
			if ( $len > 0 ) {
				$vis  = max( 0.5, $len - 2 );
				$svg .= sprintf( '<circle cx="70" cy="70" r="%d" fill="none" stroke="%s" stroke-width="18" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 70 70)"/>', $r, esc_attr( $s[2] ), $vis, $circ - $vis, -$off );
				$off += $len;
			}
		}
		$svg .= '<text x="70" y="70" text-anchor="middle" class="hs-gi-donut-num">' . esc_html( HS_Util::fa_digits( $total ) ) . '</text><text x="70" y="90" text-anchor="middle" class="hs-gi-donut-lbl">' . esc_html( $unit ) . '</text></svg>';

		$legend = '<ul class="hs-gi-dlegend">';
		foreach ( $segments as $s ) {
			$pct     = $total ? (int) round( $s[1] / $total * 100 ) : 0;
			$legend .= '<li><i style="background:' . esc_attr( $s[2] ) . '"></i><span>' . esc_html( $s[0] ) . '</span><b>' . esc_html( HS_Util::fa_digits( (int) $s[1] ) ) . '</b><small>' . esc_html( HS_Util::fa_digits( $pct ) ) . '٪</small></li>';
		}
		return '<div class="hs-gi-donut-wrap">' . $svg . $legend . '</ul></div>';
	}

	/** میله‌های افقی (HTML)؛ [برچسب => درصد] */
	private static function hbars( $items ) {
		$html = '<div class="hs-gi-hbars" style="--target:' . self::T_GOOD . '%">';
		foreach ( $items as $label => $p ) {
			$lv    = self::level_of( $p );
			$html .= '<div class="hs-gi-hbar" title="' . esc_attr( $label . ': ' . self::fa_num( $p / 5 ) . ' از ۲۰' ) . '"><span class="hs-gi-hbar-lbl">' . esc_html( $label ) . '</span>'
				. '<span class="hs-gi-hbar-track"><span class="hs-gi-hbar-fill" style="width:' . max( 3, (int) round( $p ) ) . '%;background:' . esc_attr( self::level_color( $lv ) ) . '"></span></span>'
				. '<span class="hs-gi-hbar-val">' . esc_html( self::fa_num( $p / 5 ) ) . '</span></div>';
		}
		return $html . '</div>';
	}

	/** نمودار رادار: دانش‌آموز در برابر کلاس */
	private static function radar( $axes, $mine, $cls, $name ) {
		$cx = 170;
		$cy = 135;
		$rr = 80;
		$n  = count( $axes );
		$pt = function ( $i, $v ) use ( $cx, $cy, $rr, $n ) {
			$a = -M_PI / 2 - $i * 2 * M_PI / $n; // پادساعت‌گرد: از بالا به سمت راست‌به‌چپ
			$d = max( 0, $v ) / 100 * $rr; // داده‌ها پیش‌تر به ۱۰۰ محدودند؛ برچسب‌ها بیرون از حلقه می‌نشینند
			return round( $cx + $d * cos( $a ), 1 ) . ',' . round( $cy + $d * sin( $a ), 1 );
		};
		$svg = '<svg class="hs-gi-svg hs-gi-radar" viewBox="-40 0 420 270" role="img" aria-label="' . esc_attr( 'نقاط قوت و ضعف ' . $name ) . '">';
		foreach ( array( 25, 50, 75, 100 ) as $ring ) {
			$poly = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$poly[] = $pt( $i, $ring );
			}
			$svg .= '<polygon points="' . implode( ' ', $poly ) . '" class="hs-gi-grid" fill="none"/>';
		}
		foreach ( $axes as $i => $ax ) {
			list( $ex, $ey ) = explode( ',', $pt( $i, 100 ) );
			list( $lx, $ly ) = explode( ',', $pt( $i, 115 ) );
			$svg            .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $ex . '" y2="' . $ey . '" class="hs-gi-grid"/>';
			$anchor          = abs( (float) $lx - $cx ) < 8 ? 'middle' : ( (float) $lx > $cx ? 'start' : 'end' );
			$svg            .= '<text x="' . $lx . '" y="' . ( (float) $ly + 4 ) . '" text-anchor="' . $anchor . '" class="hs-gi-axis">' . esc_html( $ax ) . '</text>';
		}
		$poly = function ( $src ) use ( $axes, $pt ) {
			$out = array();
			foreach ( $axes as $i => $ax ) {
				$out[] = $pt( $i, isset( $src[ $ax ] ) ? $src[ $ax ] : 0 );
			}
			return implode( ' ', $out );
		};
		$svg .= '<polygon points="' . $poly( $cls ) . '" class="hs-gi-radar-cls"/>';
		$svg .= '<polygon points="' . $poly( $mine ) . '" class="hs-gi-radar-me"/>';
		foreach ( $axes as $i => $ax ) {
			list( $px, $py ) = explode( ',', $pt( $i, $mine[ $ax ] ) );
			$svg            .= '<circle cx="' . $px . '" cy="' . $py . '" r="3.5" class="hs-gi-radar-dot"><title>' . esc_html( $ax . ': ' . self::fa_num( $mine[ $ax ] / 5 ) . ' · کلاس: ' . ( isset( $cls[ $ax ] ) ? self::fa_num( $cls[ $ax ] / 5 ) : '—' ) ) . '</title></circle>';
		}
		$svg .= '</svg>';
		return '<div class="hs-gi-legend"><span><i style="background:var(--hs-primary)"></i>' . esc_html( $name ) . '</span><span><i style="background:#94A3B8"></i>میانگین کلاس</span></div>' . $svg;
	}

	/** جدول رنگی تحویل تکالیف: سطر = دانش‌آموز، ستون = تکلیف (اولین تکلیف سمت راست) */
	private static function hw_grid( $data ) {
		if ( ! $data['hw'] ) {
			return self::note( 'هنوز تکلیفی برای این کلاس ثبت نشده است.' );
		}
		$names = array( 'done' => 'انجام‌شده', 'partial' => 'ناقص', 'missing' => 'انجام‌نشده', 'pending' => 'در انتظار' );
		$html  = '<div class="hs-table-wrap"><table class="hs-gi-hw"><thead><tr><th></th>';
		foreach ( $data['hw'] as $i => $h ) {
			$html .= '<th title="' . esc_attr( $h['title'] ) . '">' . esc_html( HS_Util::fa_digits( $i + 1 ) ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		$rows  = array_slice( $data['students'], 0, 12, true );
		foreach ( $rows as $sid => $name ) {
			$html .= '<tr><th>' . esc_html( $name ) . '</th>';
			foreach ( $data['hw'] as $h ) {
				$s     = isset( $data['hw_status'][ $sid ][ (int) $h['id'] ] ) ? $data['hw_status'][ $sid ][ (int) $h['id'] ] : 'pending';
				$s     = isset( $names[ $s ] ) ? $s : 'pending';
				$html .= '<td><span class="hs-gi-hw-cell is-' . esc_attr( $s ) . '" title="' . esc_attr( $h['title'] . ': ' . $names[ $s ] ) . '">' . ( 'missing' === $s ? '✕' : '' ) . '<span class="hs-gi-sr">' . esc_html( $names[ $s ] ) . '</span></span></td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table></div><div class="hs-gi-hw-legend">';
		foreach ( $names as $k => $lbl ) {
			$html .= '<span><i class="hs-gi-hw-cell is-' . esc_attr( $k ) . '">' . ( 'missing' === $k ? '✕' : '' ) . '</i>' . esc_html( $lbl ) . '</span>';
		}
		if ( count( $data['students'] ) > 12 ) {
			$html .= '<span class="hs-muted">۱۲ دانش‌آموز اول نمایش داده شده‌اند</span>';
		}
		return $html . '</div>';
	}

	/** جدول دانش‌آموزان: نیازمندان توجه بالا */
	private static function students_table( $cid, $data ) {
		$order = array_keys( $data['students'] );
		usort( $order, function ( $a, $b ) use ( $data ) {
			$fa = count( $data['stats'][ $a ]['flags'] );
			$fb = count( $data['stats'][ $b ]['flags'] );
			if ( $fa !== $fb ) {
				return $fb - $fa;
			}
			$va = null === $data['stats'][ $a ]['avg'] ? 999 : $data['stats'][ $a ]['avg'];
			$vb = null === $data['stats'][ $b ]['avg'] ? 999 : $data['stats'][ $b ]['avg'];
			return $va <=> $vb;
		} );

		$html = '<div class="hs-table-wrap"><table class="hs-table hs-gi-table"><thead><tr><th>دانش‌آموز</th><th>میانگین</th><th>روند</th><th>حضور</th><th>تکلیف انجام‌نشده</th><th>وضعیت</th></tr></thead><tbody>';
		foreach ( $order as $i => $sid ) {
			$st   = $data['stats'][ $sid ];
			$name = $data['students'][ $sid ];
			$url  = add_query_arg( array( 'class_id' => $cid, 'student_id' => $sid ), HS_Shell::url( self::SLUG ) );
			$avg  = null === $st['avg'] ? '<span class="hs-muted">—</span>' : self::pill( self::grade_text( $st['avg'], $data['all_numeric'] ), self::level_of( $st['avg'] ) );
			$flags = '';
			foreach ( $st['flags'] as $f ) {
				$flags .= '<span class="hs-gi-flag hs-gi-flag-' . esc_attr( $f[0] ) . '">' . esc_html( $f[1] ) . '</span> ';
			}
			$html .= '<tr><td><a class="hs-gi-who" href="' . esc_url( $url ) . '"><span class="hs-av hs-av-' . ( $sid % 5 ) . '">' . esc_html( self::initial( $name ) ) . '</span><strong>' . esc_html( $name ) . '</strong></a></td>'
				. '<td>' . $avg . '</td><td>' . self::sparkline( array_slice( $st['series'], -6 ), $st['delta'] ) . '</td>'
				. '<td>' . esc_html( null === $data['att'][ $sid ] ? '—' : HS_Util::fa_digits( $data['att'][ $sid ] ) . '٪' ) . '</td>'
				. '<td>' . esc_html( HS_Util::fa_digits( $data['missing'][ $sid ] ) ) . '</td>'
				. '<td>' . ( $flags ? $flags : '<span class="hs-gi-ok">✓ خوب</span>' ) . '</td></tr>';
		}
		return $html . '</tbody></table></div>';
	}

	/** نمودار کوچک روند (اولین مقدار سمت راست) */
	private static function sparkline( $vals, $delta ) {
		$n = count( $vals );
		if ( $n < 2 ) {
			return '<span class="hs-muted">—</span>';
		}
		$pts = array();
		foreach ( array_values( $vals ) as $i => $v ) {
			$v     = max( 40, min( 100, $v ) ); // بازه‌ی ۸ تا ۲۰ تا تغییرها دیده شوند
			$pts[] = round( 76 - $i * ( 72 / ( $n - 1 ) ), 1 ) . ',' . round( 24 - ( $v - 40 ) / 60 * 20, 1 );
		}
		$down  = null !== $delta && $delta <= -15;
		$label = null === $delta ? 'روند' : ( $delta >= 0 ? 'رو به بالا' : 'رو به پایین' );
		return '<svg class="hs-gi-spark' . ( $down ? ' is-down' : '' ) . '" viewBox="0 0 80 28" role="img" aria-label="' . esc_attr( $label ) . '"><polyline points="' . implode( ' ', $pts ) . '"/></svg>';
	}
}

HS_Grades_Insights::init();
