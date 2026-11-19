<?php
/**
 * برنامه‌ی هفتگی کلاس‌ها: زنگ‌بندی (پیش‌فرض + قابل تنظیم برای مدرسه یا کلاس)، خانه‌های برنامه،
 * جلوگیری از تداخل معلم (هر معلم در هر زنگ فقط یک کلاس) و امتیاز «بهینه‌سازی برنامه».
 * ویرایش: معلم کلاس یا مدیر مدرسه. دیدن: همان‌ها + ناظر مدرسه، دانش‌آموزان کلاس و والدینشان.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Timetable {

	const META = '_hs_bells';

	public static function init() {
		add_shortcode( 'hamshagerdi_timetable', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_hs_timetable_save', array( __CLASS__, 'handle_save' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'hs_timetable';
	}

	public static function create_table() {
		global $wpdb;
		$t = self::table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			day tinyint(3) unsigned NOT NULL,
			period tinyint(3) unsigned NOT NULL,
			subject varchar(120) NOT NULL DEFAULT '',
			teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned DEFAULT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY cell (class_id, day, period),
			KEY teacher_slot (teacher_id, day, period)
		) {$wpdb->get_charset_collate()};" );
	}

	/* ------------------------------ روزها و زنگ‌ها ------------------------------ */

	public static function day_names() {
		return array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه' );
	}

	/** زنگ‌بندی پیش‌فرض (دبستان، شنبه تا چهارشنبه) */
	public static function default_bells() {
		return array(
			'days'  => 5,
			'slots' => array(
				array( 'type' => 'class', 'start' => '07:45', 'end' => '08:30' ),
				array( 'type' => 'class', 'start' => '08:35', 'end' => '09:20' ),
				array( 'type' => 'break', 'start' => '09:20', 'end' => '09:40' ),
				array( 'type' => 'class', 'start' => '09:40', 'end' => '10:25' ),
				array( 'type' => 'class', 'start' => '10:30', 'end' => '11:15' ),
				array( 'type' => 'break', 'start' => '11:15', 'end' => '11:30' ),
				array( 'type' => 'class', 'start' => '11:30', 'end' => '12:15' ),
			),
		);
	}

	public static function school_of( $class_id ) {
		return (int) get_post_meta( (int) $class_id, '_hs_school_id', true );
	}

	/** پاک‌سازی و اعتبارسنجی زنگ‌بندی ورودی؛ WP_Error یا آرایه */
	public static function clean_bells( $in ) {
		$days  = isset( $in['days'] ) ? (int) $in['days'] : 5;
		$days  = max( 1, min( 6, $days ) );
		$slots = array();
		$prev  = '';
		foreach ( array_slice( (array) ( $in['slots'] ?? array() ), 0, 14 ) as $s ) {
			$type  = 'break' === ( $s['type'] ?? '' ) ? 'break' : 'class';
			$start = self::hm( $s['start'] ?? '' );
			$end   = self::hm( $s['end'] ?? '' );
			if ( ! $start || ! $end ) {
				return new WP_Error( 'hs_bells', 'ساعت شروع و پایان هر زنگ را درست وارد کنید (مثل ۰۷:۴۵).' );
			}
			if ( $end <= $start ) {
				return new WP_Error( 'hs_bells', 'پایان زنگ ' . $start . ' باید بعد از شروع آن باشد.' );
			}
			if ( $prev && $start < $prev ) {
				return new WP_Error( 'hs_bells', 'زنگ‌ها هم‌پوشانی دارند (' . $start . '). ترتیب ساعت‌ها را درست کنید.' );
			}
			$prev    = $end;
			$slots[] = array( 'type' => $type, 'start' => $start, 'end' => $end );
		}
		if ( ! array_filter( $slots, function ( $s ) { return 'class' === $s['type']; } ) ) {
			return new WP_Error( 'hs_bells', 'دست‌کم یک زنگ درسی لازم است.' );
		}
		return array( 'days' => $days, 'slots' => $slots );
	}

	private static function hm( $v ) {
		$v = strtr( trim( (string) $v ), array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) );
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $v, $m ) || (int) $m[1] > 23 || (int) $m[2] > 59 ) {
			return '';
		}
		return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
	}

	/** زنگ‌بندی کلاس: تنظیم خود کلاس، وگرنه مدرسه، وگرنه پیش‌فرض. خروجی با شماره‌ی زنگ‌ها و منبع */
	public static function bells( $class_id ) {
		$src = 'default';
		$b   = get_post_meta( (int) $class_id, self::META, true );
		if ( is_array( $b ) && ! empty( $b['slots'] ) ) {
			$src = 'class';
		} else {
			$sid = self::school_of( $class_id );
			$b   = $sid ? get_post_meta( $sid, self::META, true ) : null;
			if ( is_array( $b ) && ! empty( $b['slots'] ) ) {
				$src = 'school';
			} else {
				$b = self::default_bells();
			}
		}
		return self::numbered( $b, $src );
	}

	public static function school_bells( $school_id ) {
		$b = get_post_meta( (int) $school_id, self::META, true );
		return is_array( $b ) && ! empty( $b['slots'] ) ? self::numbered( $b, 'school' ) : self::numbered( self::default_bells(), 'default' );
	}

	private static function numbered( $b, $src ) {
		$n = 0;
		foreach ( $b['slots'] as &$s ) {
			$s['period'] = 'class' === $s['type'] ? ++$n : 0;
		}
		unset( $s );
		$b['periods'] = $n;
		$b['source']  = $src;
		return $b;
	}

	/* ------------------------------ دسترسی ------------------------------ */

	public static function is_school_manager( $class_id, $uid ) {
		$sid = self::school_of( $class_id );
		return $sid && class_exists( 'HS_SO_Store' ) && HS_SO_Store::is_manager( $sid, $uid );
	}

	public static function can_edit( $class_id, $uid ) {
		return HS_Roles::can_manage_class( (int) $class_id, (int) $uid ) || self::is_school_manager( $class_id, $uid );
	}

	public static function can_view( $class_id, $uid ) {
		$class_id = (int) $class_id;
		if ( self::can_edit( $class_id, $uid ) ) {
			return true;
		}
		$sid = self::school_of( $class_id );
		if ( $sid && class_exists( 'HS_SO_Store' ) && HS_SO_Store::can_supervise( $sid, $uid ) ) {
			return true;
		}
		$members = array_map( 'intval', (array) get_post_meta( $class_id, '_hs_students', true ) );
		if ( in_array( (int) $uid, $members, true ) ) {
			return true;
		}
		if ( class_exists( 'HS_Parent_Link' ) ) {
			foreach ( $members as $s ) {
				if ( in_array( (int) $uid, array_map( 'intval', (array) HS_Parent_Link::get_parents( $s ) ), true ) ) {
					return true;
				}
			}
		}
		// معلم درسی که در برنامه‌ی این کلاس زنگ دارد
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE class_id = %d AND teacher_id = %d LIMIT 1', $class_id, $uid ) ); // phpcs:ignore
	}

	/** معلم‌هایی که می‌شود برای یک زنگ این کلاس انتخاب کرد: معلم کلاس + کادر آموزشی مدرسه */
	public static function teacher_options( $class_id ) {
		$ids = array( (int) get_post_meta( (int) $class_id, '_hs_teacher_id', true ) );
		$sid = self::school_of( $class_id );
		if ( $sid && class_exists( 'HS_SO_Store' ) ) {
			$ids = array_merge( $ids, HS_SO_Store::staff_user_ids( $sid ) );
		}
		$out = array();
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$u = get_userdata( $id );
			if ( $u ) {
				$out[ (int) $id ] = $u->display_name;
			}
		}
		return $out;
	}

	/* ------------------------------ خواندن و نوشتن ------------------------------ */

	public static function cells( $class_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE class_id = %d ORDER BY day, period', (int) $class_id ), ARRAY_A ); // phpcs:ignore
	}

	/** زنگ‌های یک معلم در همه‌ی کلاس‌ها */
	public static function teacher_cells( $teacher_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE teacher_id = %d ORDER BY day, period', (int) $teacher_id ), ARRAY_A ); // phpcs:ignore
	}

	/** آیا معلم در همین روز و زنگ در کلاس دیگری است؟ ردیف آن کلاس یا null */
	public static function conflict( $teacher_id, $day, $period, $class_id ) {
		global $wpdb;
		if ( ! $teacher_id ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE teacher_id = %d AND day = %d AND period = %d AND class_id <> %d LIMIT 1', (int) $teacher_id, (int) $day, (int) $period, (int) $class_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function conflict_message( $c, $teacher_id ) {
		$u    = get_userdata( (int) $teacher_id );
		$days = self::day_names();
		return sprintf(
			'«%s» در زنگ %s روز %s در کلاس «%s» است؛ هر معلم در هر زنگ فقط یک کلاس دارد. یکی را انتخاب کنید.',
			$u ? $u->display_name : 'این معلم',
			self::fa( (int) $c['period'] ),
			$days[ (int) $c['day'] ] ?? '',
			get_the_title( (int) $c['class_id'] )
		);
	}

	public static function fa( $n ) {
		return strtr( (string) $n, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/**
	 * ذخیره‌ی کل برنامه‌ی یک کلاس. $rows = [ {day, period, subject, teacher_id} ]؛ خانه‌ی بی‌درس پاک می‌شود.
	 * اگر تداخلی باشد هیچ چیز ذخیره نمی‌شود و WP_Error با فهرست تداخل‌ها برمی‌گردد.
	 */
	public static function save( $class_id, $rows, $uid ) {
		global $wpdb;
		$class_id = (int) $class_id;
		$bells    = self::bells( $class_id );
		$allowed  = self::teacher_options( $class_id );
		$default  = (int) get_post_meta( $class_id, '_hs_teacher_id', true );
		$clean    = array();
		$errors   = array();
		$seen     = array();
		foreach ( (array) $rows as $r ) {
			$day    = (int) ( $r['day'] ?? -1 );
			$period = (int) ( $r['period'] ?? 0 );
			$sub    = sanitize_text_field( (string) ( $r['subject'] ?? '' ) );
			$sub    = mb_substr( trim( $sub ), 0, 120 );
			if ( $day < 0 || $day >= $bells['days'] || $period < 1 || $period > $bells['periods'] ) {
				continue;
			}
			if ( '' === $sub ) {
				continue;
			}
			$tid = (int) ( $r['teacher_id'] ?? 0 );
			$tid = $tid && isset( $allowed[ $tid ] ) ? $tid : $default;
			$key = $tid . '_' . $day . '_' . $period;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = 1;
			$c            = self::conflict( $tid, $day, $period, $class_id );
			if ( $c ) {
				$errors[] = array( 'day' => $day, 'period' => $period, 'message' => self::conflict_message( $c, $tid ) );
			}
			$clean[] = array( 'day' => $day, 'period' => $period, 'subject' => $sub, 'teacher_id' => $tid );
		}
		if ( $errors ) {
			return new WP_Error( 'hs_timetable_conflict', $errors[0]['message'], array( 'conflicts' => $errors ) );
		}
		$wpdb->delete( self::table(), array( 'class_id' => $class_id ), array( '%d' ) );
		foreach ( $clean as $c ) {
			$wpdb->insert( self::table(), array_merge( $c, array( 'class_id' => $class_id, 'updated_by' => (int) $uid ) ), array( '%d', '%d', '%s', '%d', '%d', '%d' ) );
		}
		do_action( 'hs_timetable_saved', $class_id, (int) $uid );
		return count( $clean );
	}

	/* ------------------------------ بهینه‌سازی برنامه ------------------------------ */

	/**
	 * امتیاز برنامه (۰ تا ۱۰۰) و جزئیات: تداخل زمانی، پر بودن، تعادل دروس، پراکندگی، استراحت معلم
	 */
	public static function score( $class_id ) {
		$bells  = self::bells( $class_id );
		$cells  = self::cells( $class_id );
		$total  = max( 1, $bells['days'] * $bells['periods'] );
		$filled = count( $cells );
		$checks = array();

		// ۱. تداخل (اگر از بیرون، مثلاً ویرایش هم‌زمان، پیش آمده باشد)
		$conf = 0;
		foreach ( $cells as $c ) {
			if ( self::conflict( (int) $c['teacher_id'], (int) $c['day'], (int) $c['period'], $class_id ) ) {
				$conf++;
			}
		}
		$checks[] = array( 'key' => 'conflict', 'label' => 'بدون تداخل زمانی معلم', 'ok' => 0 === $conf, 'detail' => $conf ? self::fa( $conf ) . ' زنگ تداخل دارد' : '' );

		// ۲. پر بودن خانه‌ها
		$checks[] = array( 'key' => 'filled', 'label' => 'همه‌ی زنگ‌ها درس دارند', 'ok' => $filled >= $total, 'detail' => $filled < $total ? self::fa( $total - $filled ) . ' زنگ خالی' : '' );

		// ۳. تعادل: یک درس بیش از ۲ زنگ در یک روز
		$per_day = array();
		foreach ( $cells as $c ) {
			$per_day[ $c['day'] ][ $c['subject'] ] = ( $per_day[ $c['day'] ][ $c['subject'] ] ?? 0 ) + 1;
		}
		$heavy = array();
		foreach ( $per_day as $d => $subs ) {
			foreach ( $subs as $s => $n ) {
				if ( $n > 2 ) {
					$heavy[] = $s . ' (' . ( self::day_names()[ $d ] ?? '' ) . ')';
				}
			}
		}
		$checks[] = array( 'key' => 'balance', 'label' => 'تعادل دروس در هر روز', 'ok' => ! $heavy, 'detail' => $heavy ? 'بیش از ۲ زنگ در یک روز: ' . implode( '، ', array_slice( $heavy, 0, 3 ) ) : '' );

		// ۴. پراکندگی: درسی که در هفته چند زنگ دارد ولی همه در یک یا دو روز است
		$days_of = array();
		$count   = array();
		foreach ( $cells as $c ) {
			$days_of[ $c['subject'] ][ $c['day'] ] = 1;
			$count[ $c['subject'] ]               = ( $count[ $c['subject'] ] ?? 0 ) + 1;
		}
		$bunched = array();
		foreach ( $count as $s => $n ) {
			if ( $n >= 4 && count( $days_of[ $s ] ) <= 2 ) {
				$bunched[] = $s;
			}
		}
		$checks[] = array( 'key' => 'spread', 'label' => 'پراکندگی مناسب در هفته', 'ok' => ! $bunched, 'detail' => $bunched ? 'فقط در یکی دو روز: ' . implode( '، ', $bunched ) : '' );

		// ۵. استراحت کافی: معلمی که در یک روز بیش از ۴ زنگ پشت سر هم دارد (در همه‌ی کلاس‌هایش)
		$tired = array();
		foreach ( array_unique( array_map( 'intval', wp_list_pluck( $cells, 'teacher_id' ) ) ) as $tid ) {
			$slots = array();
			foreach ( self::teacher_cells( $tid ) as $c ) {
				$slots[ $c['day'] ][] = (int) $c['period'];
			}
			foreach ( $slots as $d => $ps ) {
				sort( $ps );
				$run = 1;
				for ( $i = 1; $i < count( $ps ); $i++ ) {
					$run = $ps[ $i ] === $ps[ $i - 1 ] + 1 ? $run + 1 : 1;
					if ( $run > 4 ) {
						$u       = get_userdata( $tid );
						$tired[] = ( $u ? $u->display_name : '' ) . ' (' . ( self::day_names()[ $d ] ?? '' ) . ')';
						break;
					}
				}
			}
		}
		$checks[] = array( 'key' => 'rest', 'label' => 'استراحت کافی معلم‌ها', 'ok' => ! $tired, 'detail' => $tired ? 'بیش از ۴ زنگ پشت سر هم: ' . implode( '، ', array_slice( array_unique( $tired ), 0, 3 ) ) : '' );

		$score = (int) round( 100 * min( 1, $filled / $total ) ) - 25 * $conf - 5 * count( $heavy ) - 5 * count( $bunched ) - 5 * count( $tired );
		return array( 'score' => max( 0, min( 100, $score ) ), 'checks' => $checks, 'filled' => $filled, 'total' => $total );
	}

	/* ------------------------------ صفحه‌ی سایت ------------------------------ */

	/** کلاس‌هایی که کاربر در سایت می‌بیند */
	public static function visible_classes( $uid ) {
		$ids = get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'meta_key' => '_hs_teacher_id', 'meta_value' => $uid ) ); // phpcs:ignore
		if ( class_exists( 'HS_SO_Store' ) ) {
			$sid = (int) HS_SO_Store::school_id_for_user( $uid );
			if ( $sid && HS_SO_Store::can_supervise( $sid, $uid ) ) {
				$ids = array_merge( $ids, get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 300, 'meta_key' => '_hs_school_id', 'meta_value' => $sid ) ) ); // phpcs:ignore
			}
		}
		if ( class_exists( 'HS_Parent_Link' ) ) {
			$kids = method_exists( 'HS_Parent_Link', 'get_children' ) ? (array) HS_Parent_Link::get_children( $uid ) : array();
			foreach ( array_merge( array( $uid ), $kids ) as $s ) {
				foreach ( (array) get_user_meta( $s, '_hs_classes', true ) as $c ) {
					$ids[] = (int) $c;
				}
			}
		}
		$out = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $cid ) {
			if ( $cid && 'publish' === get_post_status( $cid ) && self::can_view( $cid, $uid ) ) {
				$out[ $cid ] = get_the_title( $cid );
			}
		}
		return $out;
	}

	private static function color( $subject ) {
		$cols = array( '#E0ECFF', '#E3F6EC', '#FFF1D6', '#FDE2E7', '#EDE7FF', '#DDF5F7', '#FFE7D6' );
		return $cols[ abs( crc32( $subject ) ) % count( $cols ) ];
	}

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای دیدن برنامه‌ی هفتگی وارد شوید.</div>';
		}
		$uid     = get_current_user_id();
		$classes = self::visible_classes( $uid );
		if ( ! $classes ) {
			return '<div class="hs-card"><p>کلاسی برای نمایش برنامه نیست.</p></div>';
		}
		$cid = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $classes[ $cid ] ) ) {
			$cid = (int) key( $classes );
		}
		$edit   = isset( $_GET['edit'] ) && self::can_edit( $cid, $uid ); // phpcs:ignore WordPress.Security.NonceVerification
		$bells  = self::bells( $cid );
		$cells  = array();
		foreach ( self::cells( $cid ) as $c ) {
			$cells[ $c['day'] . '_' . $c['period'] ] = $c;
		}
		$days   = array_slice( self::day_names(), 0, $bells['days'] );
		$today  = ( (int) current_time( 'w' ) + 1 ) % 7; // شنبه = ۰
		$opts   = self::teacher_options( $cid );
		$msg    = isset( $_GET['hs_tt'] ) ? sanitize_text_field( wp_unslash( $_GET['hs_tt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$url    = remove_query_arg( array( 'edit', 'hs_tt' ) );

		ob_start();
		echo '<div class="hs-tt" dir="rtl">';
		echo '<style>.hs-tt table{width:100%;border-collapse:separate;border-spacing:6px}.hs-tt th{font-weight:700;color:#16213E;font-size:.9rem}.hs-tt td{vertical-align:top}.hs-tt .c{border-radius:12px;padding:8px;min-height:54px}.hs-tt .c b{display:block}.hs-tt .c small{color:#555}.hs-tt .br td{background:#EEF4FF;border-radius:12px;text-align:center;color:#2C5DA8;padding:8px}.hs-tt .today{outline:2px solid #F57C00}.hs-tt .bell{font-size:.8rem;color:#666;white-space:nowrap}.hs-tt-wrap{overflow-x:auto}.hs-tt input,.hs-tt select{width:100%;font-size:.85rem}</style>';
		echo '<div class="hs-card"><form method="get" class="hs-att-filter-row" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap"><div class="hs-form-row"><label>کلاس</label><select name="class_id" onchange="this.form.submit()">';
		foreach ( $classes as $id => $t ) {
			echo '<option value="' . (int) $id . '"' . selected( $id, $cid, false ) . '>' . esc_html( $t ) . '</option>';
		}
		echo '</select></div>';
		if ( self::can_edit( $cid, $uid ) && ! $edit ) {
			echo '<a class="hs-btn hs-btn-primary" href="' . esc_url( add_query_arg( array( 'class_id' => $cid, 'edit' => 1 ), $url ) ) . '">✏️ ویرایش برنامه</a>';
		}
		echo '</form>';
		if ( $msg ) {
			echo '<div class="hs-alert ' . ( 0 === strpos( $msg, 'ok' ) ? 'hs-alert-success' : 'hs-alert-warning' ) . '">' . esc_html( 0 === strpos( $msg, 'ok' ) ? 'برنامه ذخیره شد.' : $msg ) . '</div>';
		}
		echo '</div>';

		if ( $edit ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'hs_timetable_' . $cid );
			echo '<input type="hidden" name="action" value="hs_timetable_save"><input type="hidden" name="class_id" value="' . (int) $cid . '">';
		}
		echo '<div class="hs-card hs-tt-wrap"><table><thead><tr><th>زنگ</th>';
		foreach ( $days as $i => $d ) {
			echo '<th' . ( $i === $today ? ' style="color:#C24E00"' : '' ) . '>' . esc_html( $d ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $bells['slots'] as $s ) {
			if ( 'break' === $s['type'] ) {
				echo '<tr class="br"><td colspan="' . ( count( $days ) + 1 ) . '">☕ زنگ تفریح ' . esc_html( self::fa( $s['start'] . ' تا ' . $s['end'] ) ) . '</td></tr>';
				continue;
			}
			$p = (int) $s['period'];
			echo '<tr><td class="bell"><b>' . esc_html( self::fa( $p ) ) . '</b><br>' . esc_html( self::fa( $s['start'] . ' - ' . $s['end'] ) ) . '</td>';
			foreach ( $days as $d => $name ) {
				$c = $cells[ $d . '_' . $p ] ?? null;
				if ( $edit ) {
					echo '<td><input name="tt[' . (int) $d . '][' . $p . '][subject]" value="' . esc_attr( $c['subject'] ?? '' ) . '" placeholder="درس">';
					if ( count( $opts ) > 1 ) {
						echo '<select name="tt[' . (int) $d . '][' . $p . '][teacher_id]">';
						foreach ( $opts as $tid => $tn ) {
							echo '<option value="' . (int) $tid . '"' . selected( $tid, (int) ( $c['teacher_id'] ?? 0 ), false ) . '>' . esc_html( $tn ) . '</option>';
						}
						echo '</select>';
					}
					echo '</td>';
				} elseif ( $c ) {
					$t = get_userdata( (int) $c['teacher_id'] );
					echo '<td><div class="c' . ( $d === $today ? ' today' : '' ) . '" style="background:' . esc_attr( self::color( $c['subject'] ) ) . '"><b>' . esc_html( $c['subject'] ) . '</b><small>' . esc_html( $t ? $t->display_name : '' ) . '</small></div></td>';
				} else {
					echo '<td></td>';
				}
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		if ( $edit ) {
			echo '<div class="hs-form-actions"><button class="hs-btn hs-btn-primary">ذخیره‌ی برنامه</button> <a class="hs-btn hs-btn-ghost" href="' . esc_url( add_query_arg( 'class_id', $cid, $url ) ) . '">انصراف</a></div></form>';
		}
		if ( self::can_edit( $cid, $uid ) ) {
			$sc = self::score( $cid );
			echo '<div class="hs-card"><h3>بهینه‌سازی برنامه: ' . esc_html( self::fa( $sc['score'] ) ) . '٪</h3><ul class="hs-list">';
			foreach ( $sc['checks'] as $ch ) {
				echo '<li>' . ( $ch['ok'] ? '✅ ' : '⚠️ ' ) . esc_html( $ch['label'] ) . ( $ch['detail'] ? ' — <small>' . esc_html( $ch['detail'] ) . '</small>' : '' ) . '</li>';
			}
			echo '</ul></div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/** کارت داشبورد سایت: برنامه‌ی هفتگی خود معلم در همه‌ی کلاس‌هایش */
	public static function teacher_week_html( $uid ) {
		$cells = self::teacher_cells( $uid );
		$link  = home_url( '/panel/timetable/' );
		$head  = '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>🗓️ برنامه‌ی هفتگی من</h3><a href="' . esc_url( $link ) . '">' . ( $cells ? 'همه ←' : 'تنظیم برنامه ←' ) . '</a></div>';
		if ( ! $cells ) {
			return $head . '<p class="hs-muted">هنوز برنامه‌ی هفتگی کلاس‌ها وارد نشده است.</p></section>';
		}
		$bells = self::bells( (int) $cells[0]['class_id'] );
		$map   = array();
		foreach ( $cells as $c ) {
			$map[ $c['day'] . '_' . $c['period'] ] = $c;
		}
		$days  = array_slice( self::day_names(), 0, $bells['days'] );
		$today = ( (int) current_time( 'w' ) + 1 ) % 7;
		$h     = $head . '<div class="hs-tt hs-tt-wrap"><style>.hs-tt table{width:100%;border-collapse:separate;border-spacing:4px}.hs-tt .c{border-radius:10px;padding:6px;font-size:.85rem}.hs-tt .c small{display:block;color:#555}.hs-tt .bell{font-size:.75rem;color:#666;white-space:nowrap}.hs-tt-wrap{overflow-x:auto}</style><table><thead><tr><th></th>';
		foreach ( $days as $i => $d ) {
			$h .= '<th' . ( $i === $today ? ' style="color:#C24E00"' : '' ) . '>' . esc_html( $d ) . '</th>';
		}
		$h .= '</tr></thead><tbody>';
		foreach ( $bells['slots'] as $s ) {
			if ( 'class' !== $s['type'] ) {
				continue;
			}
			$p  = (int) $s['period'];
			$h .= '<tr><td class="bell">' . esc_html( self::fa( $p ) ) . '<br>' . esc_html( self::fa( $s['start'] ) ) . '</td>';
			foreach ( $days as $d => $n ) {
				$c  = $map[ $d . '_' . $p ] ?? null;
				$h .= '<td>' . ( $c ? '<div class="c" style="background:' . esc_attr( self::color( $c['subject'] ) ) . ( $d === $today ? ';outline:2px solid #F57C00' : '' ) . '"><b>' . esc_html( $c['subject'] ) . '</b><small>' . esc_html( get_the_title( (int) $c['class_id'] ) ) . '</small></div>' : '' ) . '</td>';
			}
			$h .= '</tr>';
		}
		return $h . '</tbody></table></div></section>';
	}

	public static function handle_save() {
		$cid = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$uid = get_current_user_id();
		if ( ! $cid || ! check_admin_referer( 'hs_timetable_' . $cid ) || ! self::can_edit( $cid, $uid ) ) {
			wp_die( 'دسترسی ندارید.' );
		}
		$rows = array();
		foreach ( (array) ( $_POST['tt'] ?? array() ) as $d => $ps ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			foreach ( (array) $ps as $p => $c ) {
				$rows[] = array( 'day' => (int) $d, 'period' => (int) $p, 'subject' => sanitize_text_field( wp_unslash( $c['subject'] ?? '' ) ), 'teacher_id' => (int) ( $c['teacher_id'] ?? 0 ) );
			}
		}
		$res  = self::save( $cid, $rows, $uid );
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/panel/timetable/' );
		$args = array( 'class_id' => $cid, 'hs_tt' => is_wp_error( $res ) ? $res->get_error_message() : 'ok' );
		if ( is_wp_error( $res ) ) {
			$args['edit'] = 1;
		}
		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'edit', 'hs_tt' ), $back ) ) );
		exit;
	}

	/** ذخیره‌ی زنگ‌بندی کلاس یا مدرسه از سایت (اپ هم از API همین را صدا می‌زند) */
	public static function save_bells( $scope, $id, $in, $uid ) {
		$b = self::clean_bells( $in );
		if ( is_wp_error( $b ) ) {
			return $b;
		}
		if ( 'school' === $scope ) {
			if ( ! class_exists( 'HS_SO_Store' ) || ! HS_SO_Store::is_manager( (int) $id, $uid ) ) {
				return new WP_Error( 'hs_forbidden', 'فقط مدیر مدرسه زنگ‌بندی مدرسه را تغییر می‌دهد.' );
			}
		} elseif ( ! self::can_edit( (int) $id, $uid ) ) {
			return new WP_Error( 'hs_forbidden', 'دسترسی ندارید.' );
		}
		// زنگ‌های حذف‌شده: خانه‌هایی که بیرون از زنگ‌بندی تازه افتاده‌اند پاک می‌شوند
		update_post_meta( (int) $id, self::META, $b );
		return true;
	}

	public static function reset_bells( $class_id ) {
		delete_post_meta( (int) $class_id, self::META );
	}
}

HS_Timetable::init();
