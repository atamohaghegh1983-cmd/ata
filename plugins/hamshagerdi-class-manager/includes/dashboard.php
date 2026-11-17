<?php
/**
 * داشبورد نقش‌محور: معلم/مدیر، والد، دانش‌آموز
 * نمودارها با SVG/CSS خالص ساخته می‌شوند (بدون کتابخانه خارجی).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Dashboard {

	public static function init() {
		add_shortcode( 'hamshagerdi_dashboard', array( __CLASS__, 'render' ) );
	}

	/* =============== ورودی اصلی =============== */

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای مشاهده داشبورد باید وارد شوید.</div>';
		}
		$uid  = get_current_user_id();
		$type = HS_Shell::view_type( $uid );
		$user = wp_get_current_user();

		ob_start();
		echo '<div class="hs-dash">';
		if ( isset( $_GET['hs_denied'] ) ) { // phpcs:ignore
			echo '<div class="hs-alert hs-alert-warning">آن صفحه مخصوص نقش دیگری است؛ این‌جا داشبورد شماست.</div>';
		}
		echo self::hero( $user, $type );
		if ( 'teacher' === $type ) {
			echo self::teacher_view( $uid );
		} elseif ( 'manager' === $type ) {
			echo apply_filters( 'hs_manager_dashboard_html', '<div class="hs-card"><p>افزونهٔ «ساختار مدیریت مدرسه» را فعال کنید تا مدرسه‌تان را معرفی کنید.</p></div>', $uid ); // phpcs:ignore
		} elseif ( 'parent' === $type ) {
			echo self::parent_view( $uid );
		} elseif ( 'student' === $type ) {
			echo self::student_view( $uid );
		} else {
			echo '<div class="hs-card"><p>حساب شما هنوز به نقش معلم، والد یا دانش‌آموز اختصاص داده نشده است. با مدیر سایت تماس بگیرید.</p></div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/* =============== اجزای رابط =============== */

	/** سلام بر اساس ساعت */
	private static function greeting() {
		$h = (int) current_time( 'G' );
		if ( $h < 5 ) {
			return 'شب بخیر';
		}
		if ( $h < 12 ) {
			return 'صبح بخیر';
		}
		if ( $h < 17 ) {
			return 'ظهر بخیر';
		}
		return 'عصر بخیر';
	}

	/** تصویر تزئینی شاد پس‌زمینه کارت خوش‌آمد (SVG خالص) */
	private static function hero_art() {
		return '<svg class="hs-hero-art" viewBox="0 0 1000 220" preserveAspectRatio="xMinYMid slice" aria-hidden="true" focusable="false">'
			. '<circle cx="130" cy="30" r="110" fill="#fff" opacity=".12"/>'
			. '<circle cx="250" cy="215" r="70" fill="#fff" opacity=".10"/>'
			. '<circle cx="470" cy="215" r="90" fill="#FFC83D" opacity=".25"/>'
			. '<g fill="#FFC83D"><path d="M520 40l6 14 15 1-11 10 3 15-13-8-13 8 3-15-11-10 15-1z"/><path d="M300 150l4 9 10 1-7 7 2 10-9-5-9 5 2-10-7-7 10-1z"/><path d="M400 170l4 9 10 1-7 7 2 10-9-5-9 5 2-10-7-7 10-1z" opacity=".8"/></g>'
			. '<g fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" opacity=".45"><path d="M330 40q14-16 28 0t28 0"/><path d="M600 160q10-12 20 0t20 0"/></g>'
			. '<g transform="translate(440 36) rotate(28)"><rect width="22" height="110" rx="6" fill="#FF6B81"/><path d="M0 110h22l-11 24z" fill="#FFE2B8"/><path d="M7 124h8l-4 10z" fill="#2E3650"/><rect y="0" width="22" height="18" rx="6" fill="#fff" opacity=".7"/></g>'
			. '</svg>';
	}

	private static function hero( $user, $type ) {
		$hello = array(
			'teacher' => 'امروز هم برای ساختن یک کلاس شاد آماده‌اید؟',
			'parent'  => 'وضعیت فرزندانتان را یک‌جا ببینید.',
			'student' => 'امروز چه چیز جدیدی یاد می‌گیری؟',
			'manager' => 'نمای ناظر: فعالیت‌های مدرسه را ببینید و وظایف را واگذار کنید.',
			'other'   => '',
		);
		$html  = '<section class="hs-hero">' . self::hero_art() . '<div class="hs-hero-id">' . get_avatar( $user->ID, 76, '', '', array( 'class' => 'hs-hero-avatar' ) ) . '<div><span class="hs-hero-kicker">📅 ' . esc_html( HS_Util::today_jalali() ) . '</span>';
		$html .= '<h1>' . esc_html( self::greeting() . '، ' . $user->display_name ) . ' 👋</h1>';
		$html .= '<p>' . esc_html( $hello[ $type ] ) . '</p></div></div>';
		$html .= '<div class="hs-hero-actions">';
		if ( 'teacher' === $type ) {
			$html .= '<a class="hs-btn hs-btn-white" href="' . esc_url( HS_Shell::url( 'attendance' ) ) . '">✅ ثبت حضور</a>';
			$html .= '<a class="hs-btn hs-btn-white" href="' . esc_url( HS_Shell::url( 'homework' ) ) . '">📚 تکلیف جدید</a>';
		} elseif ( 'student' === $type ) {
			$html .= '<a class="hs-btn hs-btn-white" href="' . esc_url( HS_Shell::url( 'student' ) ) . '">🎮 دنیای من</a>';
		} elseif ( 'parent' === $type ) {
			$html .= '<a class="hs-btn hs-btn-white" href="' . esc_url( HS_Shell::url( 'parent' ) ) . '">👨‍👩‍👧 پنل والدین</a>';
		}
		$html .= '</div></section>';
		return $html;
	}

	/** نشانی فرم «ثبت دانش‌آموز» یک کلاس */
	private static function add_student_url( $cid ) {
		return add_query_arg( 'class_id', $cid, HS_Shell::url( 'classes' ) ) . '#hs-form-add-student';
	}

	/** میان‌بُرهای رنگی: [slug یا نشانی کامل، عنوان، آیکن، رنگ] */
	private static function quick( $items ) {
		$html = '<nav class="hs-quick" aria-label="دسترسی سریع">';
		foreach ( $items as $it ) {
			$href  = 0 === strpos( $it[0], 'http' ) ? $it[0] : HS_Shell::url( $it[0] );
			$html .= '<a class="hs-c-' . esc_attr( $it[3] ) . '" href="' . esc_url( $href ) . '"><span>' . esc_html( $it[2] ) . '</span>' . esc_html( $it[1] ) . '</a>';
		}
		return $html . '</nav>';
	}

	private static function stat( $icon, $label, $value, $color, $sub = '' ) {
		return '<div class="hs-stat hs-c-' . esc_attr( $color ) . '"><span class="hs-stat-ico">' . esc_html( $icon ) . '</span>'
			. '<div class="hs-stat-body"><div class="hs-stat-label">' . esc_html( $label ) . '</div>'
			. '<div class="hs-stat-val">' . esc_html( HS_Util::fa_digits( $value ) ) . '</div>'
			. ( $sub ? '<div class="hs-stat-sub">' . esc_html( $sub ) . '</div>' : '' ) . '</div></div>';
	}

	/** نمودار دونات SVG: $segments = [[label, value, color], ...] */
	private static function donut( $segments, $center_value, $center_label ) {
		$total = 0;
		foreach ( $segments as $s ) {
			$total += max( 0, (float) $s[1] );
		}
		$r    = 52;
		$circ = 2 * M_PI * $r;
		$svg  = '<svg class="hs-donut" viewBox="0 0 140 140" role="img" aria-label="' . esc_attr( $center_label ) . '">';
		$svg .= '<circle cx="70" cy="70" r="' . $r . '" fill="none" stroke="#EEF0F7" stroke-width="18"/>';
		if ( $total > 0 ) {
			$offset = 0;
			foreach ( $segments as $s ) {
				$len = ( max( 0, (float) $s[1] ) / $total ) * $circ;
				if ( $len <= 0 ) {
					continue;
				}
				$svg .= sprintf(
					'<circle cx="70" cy="70" r="%d" fill="none" stroke="%s" stroke-width="18" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 70 70)"/>',
					$r, esc_attr( $s[2] ), max( 0, $len - 2 ), $circ - max( 0, $len - 2 ), -$offset
				);
				$offset += $len;
			}
		}
		$svg .= '<text x="70" y="68" text-anchor="middle" class="hs-donut-num">' . esc_html( HS_Util::fa_digits( $center_value ) ) . '</text>';
		$svg .= '<text x="70" y="88" text-anchor="middle" class="hs-donut-lbl">' . esc_html( $center_label ) . '</text></svg>';

		$legend = '<ul class="hs-legend">';
		foreach ( $segments as $s ) {
			$legend .= '<li><i style="background:' . esc_attr( $s[2] ) . '"></i>' . esc_html( $s[0] ) . '<b>' . esc_html( HS_Util::fa_digits( (int) $s[1] ) ) . '</b></li>';
		}
		$legend .= '</ul>';
		return '<div class="hs-donut-wrap">' . $svg . $legend . '</div>';
	}

	/** نمودار ستونی: $items = [[label, percent 0..100 | null, title], ...] */
	private static function bars( $items ) {
		$dense = count( $items ) > 8 ? ' hs-bars-dense' : '';
		$html  = '<div class="hs-bars' . $dense . '">';
		foreach ( $items as $i => $it ) {
			$has   = null !== $it[1];
			$h     = $has ? max( 4, min( 100, (int) $it[1] ) ) : 3;
			$val   = $has ? HS_Util::fa_digits( (int) $it[1] ) . '٪' : '—';
			$html .= '<div class="hs-bar-col" title="' . esc_attr( $it[2] ) . '"><span class="hs-bar-val">' . esc_html( $val ) . '</span>'
				. '<div class="hs-bar-track"><div class="hs-bar hs-bar-' . ( $i % 4 ) . '" style="height:' . $h . '%"></div></div>'
				. '<span class="hs-bar-lbl">' . self::bar_label( $it[0] ) . '</span></div>';
		}
		return $html . '</div>';
	}

	/** برچسب میله: «نام روز|تاریخ» به دو خط شکسته می‌شود */
	private static function bar_label( $label ) {
		$parts = explode( '|', (string) $label, 2 );
		if ( 2 === count( $parts ) ) {
			return '<b>' . esc_html( $parts[0] ) . '</b><br>' . esc_html( $parts[1] );
		}
		return esc_html( $label );
	}

	/** روز آموزشی ایران: شنبه تا چهارشنبه (پنجشنبه و جمعه تعطیل) */
	private static function is_school_day( $ymd ) {
		$w = (int) gmdate( 'w', strtotime( $ymd . ' 12:00:00 UTC' ) );
		return ! in_array( $w, array( 4, 5 ), true );
	}

	/** شنبه‌ی هفته‌ای که این تاریخ در آن است */
	private static function week_start( $ymd ) {
		$ts  = strtotime( $ymd . ' 12:00:00 UTC' );
		$off = ( (int) gmdate( 'w', $ts ) + 1 ) % 7;
		return gmdate( 'Y-m-d', $ts - $off * DAY_IN_SECONDS );
	}

	/** حضور روزانه: [تاریخ => [present, absent, late, excused]] */
	private static function att_by_day( $class_ids, $from, $to ) {
		global $wpdb;
		$out = array();
		if ( ! $class_ids ) {
			return $out;
		}
		$t    = HS_Attendance_DB::table_name();
		$in   = self::in( $class_ids );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT att_date, status, COUNT(*) c FROM {$t} WHERE class_id IN ({$in}) AND att_date BETWEEN %s AND %s GROUP BY att_date, status", $from, $to ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			$d = substr( $r['att_date'], 0, 10 );
			if ( ! isset( $out[ $d ] ) ) {
				$out[ $d ] = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0 );
			}
			if ( isset( $out[ $d ][ $r['status'] ] ) ) {
				$out[ $d ][ $r['status'] ] = (int) $r['c'];
			}
		}
		return $out;
	}

	/** نرخ حضور مجموعه‌ای از روزها یا null اگر ثبتی نبود */
	private static function rate_of( $days, $by_day ) {
		$c = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0 );
		foreach ( $days as $d ) {
			if ( isset( $by_day[ $d ] ) ) {
				foreach ( $c as $k => $_ ) {
					$c[ $k ] += $by_day[ $d ][ $k ];
				}
			}
		}
		return array_sum( $c ) ? self::att_rate( $c ) : null;
	}

	/** سه تب: یک هفته اخیر، ماه اخیر، سه ماه اخیر (فقط شنبه تا چهارشنبه) */
	private static function attendance_tabs( $cids, $today ) {
		$by_day = self::att_by_day( $cids, gmdate( 'Y-m-d', strtotime( $today . ' 12:00:00 UTC' ) - 100 * DAY_IN_SECONDS ), $today );

		// هفته: هر پنج روز آموزشی (شنبه تا چهارشنبه) از شنبه‌ی هفته‌ی جاری؛ روزهای آینده خالی نشان داده می‌شوند
		$names = array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه' );
		$ws    = self::week_start( $today );
		$week  = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$d      = gmdate( 'Y-m-d', strtotime( $ws . ' 12:00:00 UTC' ) + $i * DAY_IN_SECONDS );
			$r      = $d <= $today ? self::rate_of( array( $d ), $by_day ) : null;
			$status = $d > $today ? ': هنوز نرسیده' : ( null === $r ? ': ثبت نشده' : ': ' . $r . '٪' );
			$week[] = array( $names[ $i ] . '|' . HS_Util::jdate( $d, false ), $r, HS_Util::jdate( $d ) . $status );
		}

		// ماه و سه ماه: میانگین هر هفته (شنبه تا چهارشنبه)
		$weekly = function ( $n ) use ( $today, $by_day ) {
			$out   = array();
			$start = self::week_start( $today );
			for ( $k = $n - 1; $k >= 0; $k-- ) {
				$ws   = gmdate( 'Y-m-d', strtotime( $start . ' 12:00:00 UTC' ) - $k * 7 * DAY_IN_SECONDS );
				$days = array();
				for ( $i = 0; $i < 5; $i++ ) { // شنبه..چهارشنبه
					$d = gmdate( 'Y-m-d', strtotime( $ws . ' 12:00:00 UTC' ) + $i * DAY_IN_SECONDS );
					if ( $d <= $today ) {
						$days[] = $d;
					}
				}
				$r     = self::rate_of( $days, $by_day );
				$out[] = array( HS_Util::jdate( $ws, false ), $r, 'هفته‌ی ' . HS_Util::jdate( $ws ) . ( null === $r ? ': ثبت نشده' : ': ' . $r . '٪' ) );
			}
			return $out;
		};

		$tabs = array(
			'w' => array( 'یک هفته اخیر', $week, 'هفته‌ی آموزشی جاری: شنبه تا چهارشنبه' ),
			'm' => array( 'ماه اخیر', $weekly( 5 ), 'میانگین هر هفته' ),
			'q' => array( 'سه ماه اخیر', $weekly( 13 ), 'میانگین هر هفته' ),
		);
		$html = '<div class="hs-tabs" data-hs-tabs><div class="hs-tab-list" role="tablist">';
		$i    = 0;
		foreach ( $tabs as $k => $t ) {
			$html .= '<button type="button" role="tab" class="' . ( 0 === $i ? 'is-active' : '' ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" data-tab="' . esc_attr( $k ) . '">' . esc_html( $t[0] ) . '</button>';
			$i++;
		}
		$html .= '</div>';
		$i     = 0;
		foreach ( $tabs as $k => $t ) {
			$html .= '<div class="hs-tab-panel' . ( 0 === $i ? ' is-active' : '' ) . '" role="tabpanel" data-panel="' . esc_attr( $k ) . '">' . self::bars( $t[1] ) . '<p class="hs-bar-note">' . esc_html( $t[2] ) . '</p></div>';
			$i++;
		}
		return $html . '</div>';
	}

	private static function empty_note( $text ) {
		return '<div class="hs-empty-state">' . esc_html( $text ) . '</div>';
	}

	private static function initial( $name ) {
		return function_exists( 'mb_substr' ) ? mb_substr( trim( $name ), 0, 1 ) : substr( trim( $name ), 0, 1 );
	}

	/* =============== داده‌ها ===============*/

	private static function class_ids( $uid ) {
		$args = array(
			'post_type'      => 'hs_class',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		if ( ! user_can( $uid, 'hs_manage_all_classes' ) ) {
			$args['meta_query'] = array( array( 'key' => '_hs_teacher_id', 'value' => $uid ) );
		}
		return array_map( 'absint', get_posts( $args ) );
	}

	private static function in( $ids ) {
		return implode( ',', array_map( 'absint', $ids ) ) ?: '0';
	}

	private static function students_of( $class_ids ) {
		$all = array();
		foreach ( $class_ids as $cid ) {
			$s = get_post_meta( $cid, '_hs_students', true );
			if ( is_array( $s ) ) {
				$all = array_merge( $all, array_map( 'absint', $s ) );
			}
		}
		return array_values( array_unique( $all ) );
	}

	/** شمارش وضعیت‌های حضور در بازه */
	private static function att_counts( $class_ids, $from, $to, $student_id = 0 ) {
		global $wpdb;
		$out = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0 );
		if ( ! $class_ids ) {
			return $out;
		}
		$t   = HS_Attendance_DB::table_name();
		$in  = self::in( $class_ids );
		$sql = "SELECT status, COUNT(*) c FROM {$t} WHERE class_id IN ({$in}) AND att_date BETWEEN %s AND %s";
		$args = array( $from, $to );
		if ( $student_id ) {
			$sql   .= ' AND student_id = %d';
			$args[] = $student_id;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' GROUP BY status', $args ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			if ( isset( $out[ $r['status'] ] ) ) {
				$out[ $r['status'] ] = (int) $r['c'];
			}
		}
		return $out;
	}

	private static function att_rate( $c ) {
		$total = array_sum( $c );
		return $total ? (int) round( ( ( $c['present'] + $c['late'] ) / $total ) * 100 ) : 0;
	}

	private static function open_homework_count( $class_ids, $student_id = 0 ) {
		global $wpdb;
		if ( ! $class_ids ) {
			return 0;
		}
		$hw = HS_Homework_DB::hw_table();
		$st = HS_Homework_DB::status_table();
		$in = self::in( $class_ids );
		$today = current_time( 'Y-m-d' );
		if ( $student_id ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$hw} h LEFT JOIN {$st} s ON s.homework_id = h.id AND s.student_id = %d
				 WHERE h.class_id IN ({$in}) AND (h.due_date IS NULL OR h.due_date >= %s) AND (s.id IS NULL OR s.status = 'pending')",
				$student_id, $today
			) ); // phpcs:ignore
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$hw} WHERE class_id IN ({$in}) AND (due_date IS NULL OR due_date >= %s)",
			$today
		) ); // phpcs:ignore
	}

	private static function points_total( $class_ids, $student_id ) {
		global $wpdb;
		if ( ! $class_ids ) {
			return 0;
		}
		$t  = HS_Points_DB::points_table();
		$in = self::in( $class_ids );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points),0) FROM {$t} WHERE class_id IN ({$in}) AND student_id = %d", $student_id ) ); // phpcs:ignore
	}

	private static function events_card( $limit = 5 ) {
		$html = '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>📅 مناسبت‌های پیش‌رو</h3><a href="' . esc_url( HS_Shell::url( 'calendar' ) ) . '">همه ←</a></div>';
		// موتور اصلی: افزونه‌ی «تقویم مناسبتی»
		if ( function_exists( 'hs_oc_upcoming' ) ) {
			$events = hs_oc_upcoming( $limit );
			if ( ! $events ) {
				return $html . self::empty_note( 'مناسبتی ثبت نشده است.' ) . '</section>';
			}
			$today = current_time( 'Y-m-d' );
			$html .= '<ul class="hs-list">';
			foreach ( $events as $e ) {
				$left  = max( 0, (int) round( ( strtotime( $e['date'] . ' 12:00:00 UTC' ) - strtotime( $today . ' 12:00:00 UTC' ) ) / DAY_IN_SECONDS ) );
				$when  = 0 === $left ? 'امروز' : ( 1 === $left ? 'فردا' : HS_Util::fa_digits( $left ) . ' روز دیگر' );
				$html .= '<li><span class="hs-dot" style="background:' . esc_attr( $e['color'] ) . '"></span><div><strong>' . esc_html( $e['title'] ) . '</strong>'
					. '<small>' . esc_html( HS_Util::jdate( $e['date'], false ) ) . ( $e['is_holiday'] ? ' · 🔴 تعطیل' : '' ) . ( 'school' !== $e['scope'] ? ' · ' . ( 'class' === $e['scope'] ? 'کلاسی' : 'شخصی' ) : '' ) . '</small></div>'
					. '<span class="hs-pill">' . esc_html( $when ) . '</span></li>';
			}
			return $html . '</ul></section>';
		}
		$events = class_exists( 'HS_Calendar_DB' ) ? HS_Calendar_DB::upcoming( $limit ) : array();
		$types  = class_exists( 'HS_Calendar_DB' ) ? HS_Calendar_DB::types() : array();
		if ( ! $events ) {
			return $html . self::empty_note( 'مناسبتی ثبت نشده است.' ) . '</section>';
		}
		$html .= '<ul class="hs-list">';
		$cols  = array( 'orange', 'blue', 'green', 'pink', 'violet' );
		foreach ( $events as $i => $e ) {
			$label = isset( $types[ $e['event_type'] ] ) ? $types[ $e['event_type'] ] : '';
			$html .= '<li><span class="hs-dot hs-bg-' . $cols[ $i % 5 ] . '"></span><div><strong>' . esc_html( $e['title'] ) . '</strong>'
				. '<small>' . esc_html( $label ) . '</small></div><span class="hs-pill">' . esc_html( HS_Util::jdate( $e['event_date'], false ) ) . '</span></li>';
		}
		return $html . '</ul></section>';
	}

	/* =============== نمای معلم/مدیر =============== */

	/** بدنه‌ی داشبورد معلم برای مجموعه‌ای از کلاس‌ها (همه یا یک کلاس) */
	private static function teacher_panel( $uid, $cids ) {
		global $wpdb;
		$students = self::students_of( $cids );
		$today    = current_time( 'Y-m-d' );
		$month_a  = current_time( 'Y-m' ) . '-01';
		$month    = self::att_counts( $cids, $month_a, $today );
		$unread   = HS_Shell::unread_count( $uid );
		$open_hw  = self::open_homework_count( $cids );

		$html  = '<div class="hs-stats">';
		$html .= self::stat( '🏫', 'کلاس‌ها', count( $cids ), 'violet', 'فعال' );
		$html .= self::stat( '🧒', 'دانش‌آموزان', count( $students ), 'green', 'در این کلاس' );
		$html .= self::stat( '✅', 'حضور این ماه', self::att_rate( $month ) . '٪', 'blue', 'میانگین حاضر + با تأخیر' );
		$html .= self::stat( '💬', 'پیام خوانده‌نشده', $unread, 'pink', HS_Util::fa_digits( $open_hw ) . ' تکلیف باز' );
		$html .= '</div>';

		$html .= '<div class="hs-grid hs-grid-chart">';
		$html .= '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>🥧 وضعیت حضور این ماه</h3></div>';
		$html .= self::donut( array(
			array( 'حاضر', $month['present'], '#10B981' ),
			array( 'غایب', $month['absent'], '#F43F5E' ),
			array( 'تأخیر', $month['late'], '#F59E0B' ),
			array( 'موجه', $month['excused'], '#3B82F6' ),
		), array_sum( $month ), 'ثبت حضور' );
		$html .= '</section>';
		$html .= '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>📊 درصد حضور و غیاب</h3></div>' . self::attendance_tabs( $cids, $today ) . '</section>';
		$html .= '</div>';

		// برترین‌ها + تکالیف + مناسبت‌ها
		$html .= '<div class="hs-grid hs-grid-3">';

		// برترین دانش‌آموزان
		$html .= '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>🏆 برترین‌ها (امتیاز)</h3><a href="' . esc_url( HS_Shell::url( 'points' ) ) . '">امتیازها ←</a></div>';
		$top = array();
		if ( $cids ) {
			$pt  = HS_Points_DB::points_table();
			$in  = self::in( $cids );
			$top = $wpdb->get_results( $wpdb->prepare( "SELECT student_id, SUM(points) total FROM {$pt} WHERE class_id IN ({$in}) GROUP BY student_id HAVING total > %d ORDER BY total DESC LIMIT %d", 0, 5 ), ARRAY_A ); // phpcs:ignore
		}
		if ( $top ) {
			$max   = max( 1, (int) $top[0]['total'] );
			$html .= '<ul class="hs-list hs-list-top">';
			foreach ( $top as $i => $r ) {
				$u    = get_userdata( (int) $r['student_id'] );
				$name = $u ? $u->display_name : '—';
				$pct  = (int) round( ( (int) $r['total'] / $max ) * 100 );
				$html .= '<li><span class="hs-av hs-av-' . ( $i % 5 ) . '">' . esc_html( self::initial( $name ) ) . '</span><div class="hs-grow"><strong>' . esc_html( $name ) . '</strong>'
					. '<div class="hs-meter"><i style="width:' . $pct . '%"></i></div></div><span class="hs-pill hs-pill-gold">' . esc_html( HS_Util::fa_digits( (int) $r['total'] ) ) . ' ⭐</span></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= self::empty_note( 'هنوز امتیازی ثبت نشده است.' );
		}
		$html .= '</section>';

		// تکالیف پیش‌رو
		$html .= '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>📚 تکالیف پیش‌رو</h3><a href="' . esc_url( HS_Shell::url( 'homework' ) ) . '">همه ←</a></div>';
		$hws = array();
		if ( $cids ) {
			$hw  = HS_Homework_DB::hw_table();
			$in  = self::in( $cids );
			$hws = $wpdb->get_results( $wpdb->prepare( "SELECT id, class_id, title, subject, due_date FROM {$hw} WHERE class_id IN ({$in}) AND (due_date IS NULL OR due_date >= %s) ORDER BY due_date IS NULL, due_date ASC LIMIT 5", $today ), ARRAY_A ); // phpcs:ignore
		}
		if ( $hws ) {
			$html .= '<ul class="hs-list">';
			$cols  = array( 'blue', 'green', 'pink', 'orange', 'violet' );
			foreach ( $hws as $i => $h ) {
				$html .= '<li><span class="hs-dot hs-bg-' . $cols[ $i % 5 ] . '"></span><div><strong>' . esc_html( $h['title'] ) . '</strong><small>' . esc_html( get_the_title( (int) $h['class_id'] ) . ( $h['subject'] ? ' · ' . $h['subject'] : '' ) ) . '</small></div>'
					. '<span class="hs-pill">' . esc_html( $h['due_date'] ? HS_Util::jdate( $h['due_date'], false ) : 'بدون مهلت' ) . '</span></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= self::empty_note( 'تکلیف بازی وجود ندارد.' );
		}
		$html .= '</section>';

		$html .= class_exists( 'HS_Budget' ) ? HS_Budget::dashboard_card( $cids ) : self::events_card();
		$html .= '</div>';
		if ( function_exists( 'hs_oc_upcoming' ) && class_exists( 'HS_Budget' ) ) {
			$html .= '<div class="hs-grid">' . self::events_card( 6 ) . '</div>';
		}
		return $html;
	}

	private static function teacher_view( $uid ) {
		$cids = self::class_ids( $uid );
		$html = self::quick( array(
			array( 'classes', 'کلاس‌ها', '🏫', 'blue' ),
			// افزودن دانش‌آموز: یک کلاس ← مستقیم فرم همان کلاس؛ چند کلاس ← فهرست کلاس‌ها
			array( 1 === count( $cids ) ? self::add_student_url( $cids[0] ) : HS_Shell::url( 'classes' ), 'افزودن دانش‌آموز', '➕', 'teal' ),
			array( 'attendance', 'حضور و غیاب', '✅', 'green' ),
			array( 'points', 'تشویق و امتیاز', '⭐', 'orange' ),
			array( 'assessment', 'ارزیابی', '📝', 'pink' ),
			array( 'online-builder', 'تکلیف‌ساز', '🧪', 'violet' ),
			array( 'calendar', 'تقویم', '📅', 'teal' ),
		) );

		if ( count( $cids ) > 1 ) {
			// چند کلاس: برای هر کلاس یک تب جدا (نه زیر هم)
			$cids = array_slice( $cids, 0, 8 );
			$html .= '<div class="hs-ctabs" data-hs-ctabs><div class="hs-class-tabs" role="tablist" aria-label="انتخاب کلاس">';
			foreach ( $cids as $i => $cid ) {
				$html .= '<button type="button" role="tab" class="' . ( 0 === $i ? 'is-active' : '' ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" data-ctab="' . esc_attr( $cid ) . '">🏫 ' . esc_html( get_the_title( $cid ) ) . '</button>';
			}
			$html .= '</div>';
			foreach ( $cids as $i => $cid ) {
				$html .= '<div class="hs-ctab-panel' . ( 0 === $i ? ' is-active' : '' ) . '" role="tabpanel" data-cpanel="' . esc_attr( $cid ) . '">' . self::teacher_panel( $uid, array( $cid ) ) . '</div>';
			}
			$html .= '</div>';
		} else {
			$html .= self::teacher_panel( $uid, $cids );
		}

		// جدول کلاس‌ها
		$html .= '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>🏫 کلاس‌های من</h3><a class="hs-btn hs-btn-sm hs-btn-primary" href="' . esc_url( HS_Shell::url( 'classes' ) ) . '">+ ساخت کلاس</a></div>';
		if ( $cids ) {
			$html .= '<div class="hs-table-wrap"><table class="hs-table"><thead><tr><th>نام کلاس</th><th>پایه</th><th>سال تحصیلی</th><th>دانش‌آموز</th><th>کد دعوت</th><th></th></tr></thead><tbody>';
			foreach ( array_slice( $cids, 0, 8 ) as $cid ) {
				$url   = add_query_arg( 'class_id', $cid, HS_Shell::url( 'classes' ) );
				$html .= '<tr><td><strong>' . esc_html( get_the_title( $cid ) ) . '</strong></td>'
					. '<td><span class="hs-pill hs-pill-violet">' . esc_html( get_post_meta( $cid, '_hs_grade', true ) ?: '—' ) . '</span></td>'
					. '<td>' . esc_html( get_post_meta( $cid, '_hs_year', true ) ) . '</td>'
					. '<td>' . esc_html( HS_Util::fa_digits( HS_Class_Students::count( $cid ) ) ) . '</td>'
					. '<td><code class="hs-code">' . esc_html( get_post_meta( $cid, '_hs_invite_code', true ) ) . '</code></td>'
					. '<td><a class="hs-btn hs-btn-sm hs-btn-primary" href="' . esc_url( self::add_student_url( $cid ) ) . '">+ دانش‌آموز</a> <a class="hs-btn hs-btn-sm hs-btn-ghost" href="' . esc_url( $url ) . '">مدیریت</a></td></tr>';
			}
			$html .= '</tbody></table></div>';
		} else {
			$html .= self::empty_note( 'هنوز کلاسی نساخته‌اید. روی «ساخت کلاس» بزنید 🎉' );
		}
		$html .= '</section>';

		if ( class_exists( 'HS_Bale_API' ) && HS_Bale_API::is_enabled() ) {
			$html .= '<section class="hs-card hs-dash-card">' . do_shortcode( '[hamshagerdi_bale_link]' ) . '</section>';
		}
		return $html;
	}

	/* =============== نمای والد =============== */

	/** نمای والد را افزونه‌ی «پنل والدین» می‌سازد */
	private static function parent_view( $uid ) {
		$default = '<section class="hs-card hs-dash-card"><h3>👨‍👩‍👧 پنل والدین</h3><p class="hs-muted">برای دیدن وضعیت فرزندان، افزونه‌ی «پنل والدین» باید فعال باشد.</p></section>';
		return apply_filters( 'hs_parent_dashboard_html', $default, $uid ) . self::events_card();
	}

	/* =============== نمای دانش‌آموز =============== */

	private static function student_view( $uid ) {
		$classes = class_exists( 'HS_Parent_Link' ) ? HS_Parent_Link::student_classes( $uid ) : array();
		$cids    = wp_list_pluck( $classes, 'id' );
		$today   = current_time( 'Y-m-d' );
		$att     = self::att_counts( $cids, current_time( 'Y-m' ) . '-01', $today, $uid );

		$html  = '<div class="hs-stats">';
		$html .= self::stat( '⭐', 'امتیاز من', self::points_total( $cids, $uid ), 'orange' );
		$html .= self::stat( '📚', 'تکلیف باز', self::open_homework_count( $cids, $uid ), 'pink' );
		$html .= self::stat( '✅', 'حضور این ماه', self::att_rate( $att ) . '٪', 'green' );
		$html .= self::stat( '🏫', 'کلاس‌ها', count( $cids ), 'violet' );
		$html .= '</div><div class="hs-grid hs-grid-2">';

		$html .= '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>🏫 کلاس‌های من</h3></div>';
		if ( $classes ) {
			$html .= '<ul class="hs-list">';
			foreach ( $classes as $i => $c ) {
				$html .= '<li><span class="hs-av hs-av-' . ( $i % 5 ) . '">' . esc_html( self::initial( $c['title'] ) ) . '</span><div><strong>' . esc_html( $c['title'] ) . '</strong><small>معلم: ' . esc_html( $c['teacher'] ) . '</small></div></li>';
			}
			$html .= '</ul>';
		} else {
			$html .= self::empty_note( 'هنوز عضو کلاسی نیستی. کد دعوت را از معلمت بگیر.' );
		}
		$html .= '<a class="hs-btn hs-btn-primary" href="' . esc_url( HS_Shell::url( 'student' ) ) . '">🎮 ورود به دنیای من</a></section>';
		$html .= self::events_card() . '</div>';
		return apply_filters( 'hs_student_dashboard_html', $html, $uid ); // افزونه‌ی «پنل دانش‌آموز» برنامه‌ی من را اضافه می‌کند
	}
}

HS_Dashboard::init();
