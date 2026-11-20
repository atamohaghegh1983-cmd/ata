<?php
/**
 * «دنیای من» — صفحه‌ی دانش‌آموز
 * بخش‌ها: خانه، برنامه، تکالیف، کارنامه، کتابخانه، بازی، جایزه، پیام — هر کدام با مجوز خودش.
 * دو ظاهر: کودکانه (پیش‌دبستانی تا سوم) و ساده (چهارم به بالا). معلم می‌تواند عوضش کند.
 * امنیت: نمای دانش‌آموز دیگر فقط برای معلمِ کلاس همان دانش‌آموز یا مدیر.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_World {

	public static function init() {
		add_shortcode( 'hamshagerdi_student_world', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_hs_student_submit_hw_photo', array( __CLASS__, 'submit_photo' ) );
		add_action( 'wp_ajax_hs_student_game_score', array( __CLASS__, 'save_game_score' ) );
	}

	/** بخش ← مجوز */
	public static function sections() {
		return array(
			'home'     => array( '🏠', 'خانه', '' ),
			'plan'     => array( '🗓️', 'برنامه', 'calendar' ),
			'homework' => array( '📚', 'تکالیف', 'homework' ),
			'report'   => array( '📊', 'کارنامه', '' ),
			'library'  => array( '📖', 'کتابخانه', 'library' ),
			'games'    => array( '🎮', 'بازی', 'games' ),
			'rewards'  => array( '⭐', 'جایزه', 'points' ),
			'messages' => array( '💬', 'پیام', 'messages' ),
		);
	}

	private static function allowed( $sid, $section, $cid ) {
		$s = self::sections();
		if ( ! isset( $s[ $section ] ) ) {
			return false;
		}
		if ( 'report' === $section ) {
			foreach ( array( 'assessment', 'attendance', 'points', 'homework' ) as $f ) {
				if ( HSSP_Perms::can( $sid, $f, $cid ) ) {
					return true;
				}
			}
			return false;
		}
		return '' === $s[ $section ][2] || HSSP_Perms::can( $sid, $s[ $section ][2], $cid );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return self::login_gate();
		}
		$uid     = get_current_user_id();
		$section = isset( $_GET['sw'] ) ? sanitize_key( $_GET['sw'] ) : 'home'; // phpcs:ignore
		$sid     = $uid;
		if ( isset( $_GET['student_id'] ) && (int) $_GET['student_id'] !== $uid ) { // phpcs:ignore
			$want = absint( $_GET['student_id'] ); // phpcs:ignore
			if ( HSSP_Util::can_view_student( $uid, $want ) ) {
				$sid = $want;
			} else {
				return '<div class="hs-card"><p>🔒 فقط معلمِ همین دانش‌آموز می‌تواند صفحه‌ی او را ببیند.</p></div>';
			}
		}
		$class_ids = HSSP_Util::student_classes( $sid );
		$cid       = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : ( $class_ids[0] ?? 0 ); // phpcs:ignore
		if ( $cid && ! in_array( $cid, $class_ids, true ) ) {
			$cid = $class_ids[0] ?? 0;
		}
		if ( ! self::allowed( $sid, $section, $cid ) ) {
			$section = 'home';
		}
		$theme = $cid ? HSSP_Settings::theme( $cid ) : 'kid';
		$game  = isset( $_GET['game'] ) ? sanitize_key( $_GET['game'] ) : ''; // phpcs:ignore
		$ctx   = array( 'sid' => $sid, 'cid' => $cid, 'classes' => $class_ids, 'theme' => $theme, 'viewer' => $uid );

		ob_start();
		?>
		<div class="hs-kid hssp-theme-<?php echo esc_attr( $theme ); ?>" id="hs-student-world" dir="rtl" data-student="<?php echo esc_attr( $sid ); ?>" data-class="<?php echo esc_attr( $cid ); ?>">
			<?php if ( 'kid' === $theme ) : ?>
			<div class="hs-kid-bg" aria-hidden="true"><span class="hs-kid-blob b1"></span><span class="hs-kid-blob b2"></span><span class="hs-kid-blob b3"></span></div>
			<?php endif; ?>
			<?php if ( $sid !== $uid ) : ?>
				<div class="hssp-teacher-bar">👀 نمای دانش‌آموز: <strong><?php echo esc_html( get_userdata( $sid )->display_name ); ?></strong> (فقط مشاهده)</div>
			<?php endif; ?>
			<?php echo self::class_switch( $ctx, $section ); // phpcs:ignore ?>
			<?php echo self::nav( $section, $ctx ); // phpcs:ignore ?>
			<div class="hs-kid-body">
				<?php
				switch ( $section ) {
					case 'plan':
						echo self::plan( $ctx ); // phpcs:ignore
						break;
					case 'homework':
						echo self::homework( $ctx ); // phpcs:ignore
						break;
					case 'report':
						echo self::report( $ctx ); // phpcs:ignore
						break;
					case 'library':
						echo HSSP_Library::student_view( $sid, $cid, isset( $_GET['item'] ) ? (int) $_GET['item'] : 0, self::url( 'library', $ctx ) ); // phpcs:ignore
						break;
					case 'games':
						echo $game ? self::game( $game, $ctx ) : self::games_hub( $ctx ); // phpcs:ignore
						break;
					case 'rewards':
						echo self::rewards( $ctx ); // phpcs:ignore
						break;
					case 'messages':
						echo self::messages( $ctx ); // phpcs:ignore
						break;
					default:
						echo self::home( $ctx ); // phpcs:ignore
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function url( $section, $ctx, $extra = array() ) {
		$args = array_merge( array( 'sw' => $section ), $extra );
		if ( $ctx['cid'] && count( $ctx['classes'] ) > 1 ) {
			$args['class_id'] = $ctx['cid'];
		}
		if ( $ctx['sid'] !== $ctx['viewer'] ) {
			$args['student_id'] = $ctx['sid'];
		}
		return add_query_arg( $args, HSSP_Util::page_url( 'student' ) );
	}

	private static function class_switch( $ctx, $section ) {
		if ( count( $ctx['classes'] ) < 2 ) {
			return '';
		}
		$h = '<nav class="hssp-class-switch">';
		foreach ( $ctx['classes'] as $c ) {
			$h .= '<a class="' . ( $c === $ctx['cid'] ? 'is-on' : '' ) . '" href="' . esc_url( self::url( $section, array_merge( $ctx, array( 'cid' => $c ) ) ) ) . '">🏫 ' . esc_html( get_the_title( $c ) ) . '</a>';
		}
		return $h . '</nav>';
	}

	private static function nav( $current, $ctx ) {
		$h = '<nav class="hs-kid-nav" role="navigation">';
		foreach ( self::sections() as $k => $it ) {
			if ( ! self::allowed( $ctx['sid'], $k, $ctx['cid'] ) ) {
				continue;
			}
			$h .= '<a href="' . esc_url( self::url( $k, $ctx ) ) . '" class="hs-kid-nav-item ' . ( $current === $k ? 'active' : '' ) . '"><span class="hs-kid-nav-bubble">' . esc_html( $it[0] ) . '</span><span class="hs-kid-nav-label">' . esc_html( $it[1] ) . '</span></a>';
		}
		return $h . '</nav>';
	}

	private static function login_gate() {
		return '<div class="hs-kid hs-kid-gate"><div class="hs-kid-gate-card"><div class="hs-kid-mascot">🦊</div><h2>دنیای دانش‌آموز</h2><p class="hs-kid-tagline">یاد بگیر، بازی کن، پیشرفت کن!</p>'
			. '<a href="' . esc_url( HSSP_Login::url() ) . '" class="hs-kid-btn hs-kid-btn-big">ورود با کارت کلاس 🎫</a>'
			. '<p><a href="' . esc_url( wp_login_url( HSSP_Util::page_url( 'student' ) ) ) . '">ورود با موبایل یا نام کاربری</a></p></div></div>';
	}

	/* ---------- داده‌های مشترک ---------- */

	/** وضعیت تکلیف برای دانش‌آموز: [done(bool), status row, online submission] */
	public static function hw_state( $hw, $sid ) {
		$st   = HS_Homework_DB::get_statuses( $hw['id'] );
		$mine = $st[ $sid ] ?? null;
		$sub  = class_exists( 'HS_Online_HW_DB' ) ? HS_Online_HW_DB::get_submission( $hw['id'], $sid ) : null;
		return array( $sub || ( $mine && in_array( $mine['status'], array( 'done', 'late' ), true ) ), $mine, $sub );
	}

	public static function open_homework( $sid, $cid ) {
		$out = array();
		if ( ! $cid || ! HSSP_Perms::can( $sid, 'homework', $cid ) ) {
			return $out;
		}
		foreach ( HS_Homework_DB::get_class_homework( $cid, 30 ) as $hw ) {
			if ( ! self::hw_state( $hw, $sid )[0] ) {
				$out[] = $hw;
			}
		}
		usort( $out, function ( $a, $b ) {
			return strcmp( $a['due_date'] ? $a['due_date'] : '9999', $b['due_date'] ? $b['due_date'] : '9999' );
		} );
		return $out;
	}

	private static function due_text( $d ) {
		if ( ! $d ) {
			return '';
		}
		$days = (int) round( ( strtotime( $d ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
		if ( $days < 0 ) {
			return 'مهلت گذشته';
		}
		return 0 === $days ? 'مهلت: امروز' : ( 1 === $days ? 'مهلت: فردا' : 'مهلت: ' . HSSP_Util::weekday( $d ) . ' ' . HSSP_Util::d( $d, false ) );
	}

	private static function hw_href( $hw, $ctx ) {
		if ( isset( $hw['mode'] ) && 'online' === $hw['mode'] ) {
			$a = array( 'homework_id' => $hw['id'] );
			if ( $ctx['sid'] !== $ctx['viewer'] ) {
				$a['student_id'] = $ctx['sid'];
			}
			return add_query_arg( $a, HSSP_Util::page_url( 'online-hw' ) );
		}
		return self::url( 'homework', $ctx );
	}

	/* ---------- خانه ---------- */

	private static function home( $ctx ) {
		if ( 'kid' === $ctx['theme'] ) {
			return self::home_kid( $ctx );
		}
		$sid   = $ctx['sid'];
		$cid   = $ctx['cid'];
		$u     = get_userdata( $sid );
		$first = $u ? ( $u->first_name ? $u->first_name : explode( ' ', trim( $u->display_name ) )[0] ) : '';
		$tname = '';
		if ( $cid ) {
			$t     = get_userdata( (int) get_post_meta( $cid, '_hs_teacher_id', true ) );
			$tname = $t ? $t->display_name : '';
		}
		$stars = $cid && HSSP_Perms::can( $sid, 'points', $cid ) && class_exists( 'HS_Points_DB' ) ? HS_Points_DB::get_student_total( $cid, $sid ) : null;
		$open  = self::open_homework( $sid, $cid );
		$hour  = (int) current_time( 'G' );
		$greet = $hour < 12 ? array( '🌅', 'صبح بخیر' ) : ( $hour < 17 ? array( '☀️', 'روز بخیر' ) : array( '🌙', 'عصر بخیر' ) );
		$kid   = 'kid' === $ctx['theme'];

		$h  = '<header class="hs-kid-hero">' . ( $kid ? '<div class="hs-kid-hero-mascot" aria-hidden="true">🦊</div>' : '' ) . '<div class="hs-kid-hero-text">'
			. '<p class="hs-kid-greet">' . esc_html( $greet[0] . ' ' . $greet[1] ) . '</p><h1>سلام ' . esc_html( $first ) . '!</h1>'
			. '<p class="hs-kid-hero-sub">' . ( $kid ? 'امروز آماده‌ای چیزهای تازه یاد بگیری؟' : HSSP_Util::weekday( current_time( 'Y-m-d' ) ) . ' ' . HSSP_Util::d( current_time( 'Y-m-d' ) ) ) . '</p>';
		if ( $cid ) {
			$h .= '<div class="hs-kid-class-chip"><span>🏫</span><span>' . esc_html( get_the_title( $cid ) ) . '</span>' . ( $tname ? '<span class="dot">·</span><span>' . esc_html( $tname ) . '</span>' : '' ) . '</div>';
		} else {
			$h .= '<div class="hs-kid-class-chip warn">از معلم کد دعوت بگیر تا به کلاس بپیوندی</div>';
		}
		$h .= '</div>' . ( null !== $stars ? '<div class="hs-kid-star-orb"><div class="hs-kid-star-num">' . (int) $stars . '</div><div class="hs-kid-star-lbl">⭐ امتیاز</div></div>' : '' ) . '</header>';

		if ( HSSP_Perms::can( $sid, 'homework', $cid ) ) {
			if ( $open ) {
				$h     .= '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>📋 کارهای من</h2><span class="hs-kid-count-pill">' . count( $open ) . ' تا</span></div><div class="hs-kid-task-stack">';
				$colors = array( 'task-purple', 'task-coral', 'task-teal', 'task-amber' );
				foreach ( array_slice( $open, 0, 4 ) as $i => $hw ) {
					$online = isset( $hw['mode'] ) && 'online' === $hw['mode'];
					$h     .= '<a class="hs-kid-task ' . esc_attr( $colors[ $i % 4 ] ) . '" href="' . esc_url( self::hw_href( $hw, $ctx ) ) . '"><div class="hs-kid-task-emoji">' . ( $online ? '💻' : '📘' ) . '</div>'
						. '<div class="hs-kid-task-info"><strong>' . esc_html( $hw['title'] ) . '</strong><span>' . esc_html( trim( $hw['subject'] . ' · ' . self::due_text( $hw['due_date'] ), ' ·' ) ) . '</span></div><div class="hs-kid-task-arrow">شروع</div></a>';
				}
				$h .= '</div></section>';
			} else {
				$h .= '<section class="hs-kid-block"><div class="hs-kid-celebrate"><div class="hs-kid-celebrate-emoji">🎉</div><p>' . ( $kid ? 'آفرین! کار عقب‌افتاده‌ای نداری.' : 'تکلیف باز نداری.' ) . '</p></div></section>';
			}
		}
		$tiles = array( 'plan' => array( '🗓️', 'برنامه', 't-hw' ), 'report' => array( '📊', 'کارنامه', 't-star' ), 'library' => array( '📖', 'کتابخانه', 't-lib' ), 'games' => array( '🎮', 'بازی', 't-game' ) );
		$th    = '';
		foreach ( $tiles as $k => $t ) {
			if ( self::allowed( $sid, $k, $cid ) ) {
				$th .= '<a class="hs-kid-tile ' . esc_attr( $t[2] ) . '" href="' . esc_url( self::url( $k, $ctx ) ) . '"><span class="hs-kid-tile-icon">' . esc_html( $t[0] ) . '</span><strong>' . esc_html( $t[1] ) . '</strong></a>';
			}
		}
		if ( $th ) {
			$h .= '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>✨ میانبرها</h2></div><div class="hs-kid-grid-4">' . $th . '</div></section>';
		}
		return $h;
	}


	/** صحنه‌ی آسمان و جزیره‌ها (SVG خودمان) */
	private static function scene() {
		return '<svg class="wk-scene" viewBox="0 0 400 260" preserveAspectRatio="xMidYMax slice" aria-hidden="true">'
			. '<defs><linearGradient id="wkSky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7cc8ff"/><stop offset="1" stop-color="#e6f6ff"/></linearGradient>'
			. '<linearGradient id="wkIsl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8b6b4a"/><stop offset="1" stop-color="#5b4331"/></linearGradient></defs>'
			. '<rect width="400" height="260" fill="url(#wkSky)"/>'
			. '<circle cx="335" cy="48" r="26" fill="#ffe27a"/><circle cx="335" cy="48" r="38" fill="#ffe27a" opacity=".25"/>'
			. '<g fill="#fff" opacity=".95"><ellipse cx="70" cy="52" rx="34" ry="13"/><ellipse cx="92" cy="44" rx="22" ry="14"/><ellipse cx="250" cy="30" rx="28" ry="10"/><ellipse cx="268" cy="24" rx="16" ry="10"/></g>'
			// جزیره‌ی چپ با برج کتابخانه
			. '<g class="wk-float1"><path d="M8 168 Q60 150 112 168 L92 196 Q60 214 30 196Z" fill="url(#wkIsl)"/><path d="M8 168 Q60 150 112 168 Q60 176 8 168Z" fill="#6cc46a"/>'
			. '<rect x="40" y="112" width="34" height="52" rx="4" fill="#f4d9b0"/><path d="M34 114 L57 82 L80 114Z" fill="#8f6ad8"/><rect x="50" y="132" width="14" height="20" rx="7" fill="#7a5130"/>'
			. '<rect x="44" y="120" width="6" height="8" rx="2" fill="#7fd0ff"/><rect x="64" y="120" width="6" height="8" rx="2" fill="#7fd0ff"/></g>'
			// جزیره‌ی راست با درخت
			. '<g class="wk-float2"><path d="M290 150 Q340 134 392 150 L372 176 Q342 192 312 176Z" fill="url(#wkIsl)"/><path d="M290 150 Q340 134 392 150 Q340 158 290 150Z" fill="#6cc46a"/>'
			. '<rect x="337" y="112" width="7" height="34" fill="#7a5130"/><circle cx="340" cy="104" r="20" fill="#3fae5a"/><circle cx="328" cy="112" r="12" fill="#4cc06a"/><circle cx="353" cy="112" r="12" fill="#4cc06a"/>'
			. '<circle cx="333" cy="100" r="3" fill="#ff7a7a"/><circle cx="348" cy="108" r="3" fill="#ff7a7a"/></g>'
			// بالن
			. '<g class="wk-float3"><g transform="translate(-60 -12) scale(.7)"><ellipse cx="205" cy="70" rx="17" ry="21" fill="#ff8fa3"/><path d="M190 76 Q205 98 220 76" fill="#ff6b88"/><path d="M196 89 L200 104 M214 89 L210 104" stroke="#7a5130" stroke-width="1.5"/><rect x="198" y="103" width="14" height="9" rx="2" fill="#c98a4b"/></g></g>'
			// تپه‌ها
			. '<path d="M0 230 Q100 196 200 222 Q300 248 400 214 L400 260 L0 260Z" fill="#9fe08f"/><path d="M0 244 Q120 222 230 242 Q320 258 400 236 L400 260 L0 260Z" fill="#7dd27a"/>'
			. '</svg>';
	}

	private static function home_kid( $ctx ) {
		$sid   = $ctx['sid'];
		$cid   = $ctx['cid'];
		$u     = get_userdata( $sid );
		$first = $u ? ( $u->first_name ? $u->first_name : explode( ' ', trim( $u->display_name ) )[0] ) : '';
		$stars = $cid && HSSP_Perms::can( $sid, 'points', $cid ) && class_exists( 'HS_Points_DB' ) ? (int) HS_Points_DB::get_student_total( $cid, $sid ) : null;
		$open  = self::open_homework( $sid, $cid );
		$hour  = (int) current_time( 'G' );
		$greet = $hour < 12 ? 'صبح بخیر' : ( $hour < 17 ? 'روز بخیر' : 'عصر بخیر' );
		$lib   = ( $cid && HSSP_Perms::can( $sid, 'library', $cid ) ) ? HSSP_Library::items( $cid ) : array();
		$reads = $lib ? HSSP_Library::reads_of( $sid ) : array();
		$games = ( $cid && HSSP_Perms::can( $sid, 'games', $cid ) ) ? HSSP_Settings::class_games( $cid ) : array();

		// ماجرای امروز: اولین کار باز ← داستان نخوانده ← یک بازی
		$feat = null;
		if ( $open ) {
			$feat = array( '📘', 'ماجرای امروز: ' . $open[0]['title'], trim( $open[0]['subject'] . ' · ' . self::due_text( $open[0]['due_date'] ), ' ·' ), self::hw_href( $open[0], $ctx ), 'شروع کن' );
		} else {
			foreach ( $lib as $it ) {
				if ( ! isset( $reads[ (int) $it['id'] ] ) ) {
					$feat = array( $it['icon'], 'یک داستان تازه: ' . $it['title'], 'بخوان و ستاره بده!', self::url( 'library', $ctx, array( 'item' => $it['id'] ) ), 'بخوان' );
					break;
				}
			}
			if ( ! $feat && $games ) {
				$g    = HSSP_Settings::games()[ $games[ array_rand( $games ) ] ];
				$feat = array( $g[0], 'بازی امروز: ' . $g[1], $g[2], self::url( 'games', $ctx, array( 'game' => array_search( $g, HSSP_Settings::games(), true ) ) ), 'بازی کن' );
			}
		}

		$h  = '<section class="wk-hero">' . self::scene() . '<div class="wk-hero-in">';
		$h .= '<p class="wk-greet">☀️ ' . esc_html( $greet ) . '</p><h1 class="wk-title">سلام ' . esc_html( $first ) . '!<br><span>امروز چی کشف می‌کنی؟</span></h1>';
		if ( $feat ) {
			$h .= '<a class="wk-cta" href="' . esc_url( $feat[3] ) . '">' . esc_html( $feat[4] ) . ' 🚀</a>';
		}
		$h .= '</div></section>';

		// نوار وضعیت
		$h .= '<div class="wk-strip">';
		if ( null !== $stars ) {
			$h .= '<span>⭐ <b>' . $stars . '</b> امتیاز</span>';
		}
		if ( HSSP_Perms::can( $sid, 'homework', $cid ) ) {
			$h .= '<span>📚 <b>' . count( $open ) . '</b> کار باز</span>';
		}
		if ( $lib ) {
			$h .= '<span>📖 <b>' . count( array_intersect_key( $reads, array_flip( wp_list_pluck( $lib, 'id' ) ) ) ) . '</b> خوانده</span>';
		}
		$h .= $cid ? '<span>🏫 ' . esc_html( get_the_title( $cid ) ) . '</span>' : '<span>🔑 از معلم کد کلاس بگیر</span>';
		$h .= '</div>';

		// کاوش کن
		$tiles = array(
			'plan'     => array( '🗓️', 'برنامه', 't1' ),
			'homework' => array( '📚', 'تکالیف', 't2' ),
			'report'   => array( '📊', 'کارنامه', 't3' ),
			'library'  => array( '📖', 'کتابخانه', 't4' ),
			'games'    => array( '🎮', 'بازی', 't5' ),
			'rewards'  => array( '🏆', 'جایزه', 't6' ),
		);
		$th = '';
		foreach ( $tiles as $k => $t ) {
			if ( self::allowed( $sid, $k, $cid ) ) {
				$th .= '<a class="wk-tile ' . $t[2] . '" href="' . esc_url( self::url( $k, $ctx ) ) . '"><span class="wk-tile-ic">' . esc_html( $t[0] ) . '</span><b>' . esc_html( $t[1] ) . '</b></a>';
			}
		}
		if ( $th ) {
			$h .= '<section class="wk-sec wk-wave"><h2 class="wk-h">✨ کاوش کن ✨</h2><div class="wk-tiles">' . $th . '</div></section>';
		}

		// کارهای من
		if ( $open ) {
			$h .= '<section class="wk-sec"><div class="wk-head"><h2 class="wk-h">📋 کارهای من</h2><a href="' . esc_url( self::url( 'plan', $ctx ) ) . '">همه ←</a></div><div class="wk-row">';
			$c  = array( 'c1', 'c2', 'c3', 'c4' );
			foreach ( array_slice( $open, 0, 6 ) as $i => $hw ) {
				$online = isset( $hw['mode'] ) && 'online' === $hw['mode'];
				$h     .= '<a class="wk-task ' . $c[ $i % 4 ] . '" href="' . esc_url( self::hw_href( $hw, $ctx ) ) . '"><span class="wk-task-ic">' . ( $online ? '💻' : '📘' ) . '</span><b>' . esc_html( $hw['title'] ) . '</b><small>' . esc_html( trim( $hw['subject'] . ' · ' . self::due_text( $hw['due_date'] ), ' ·' ) ) . '</small></a>';
			}
			$h .= '</div></section>';
		}

		// داستان‌های کلاس
		if ( $lib ) {
			$h .= '<section class="wk-sec wk-wave2"><div class="wk-head"><h2 class="wk-h">📚 داستان‌های کلاس</h2><a href="' . esc_url( self::url( 'library', $ctx ) ) . '">همه ←</a></div><div class="wk-row">';
			foreach ( array_slice( $lib, 0, 8 ) as $i => $it ) {
				$r  = isset( $reads[ (int) $it['id'] ] );
				$h .= '<a class="wk-story s' . ( $i % 4 ) . '" href="' . esc_url( self::url( 'library', $ctx, array( 'item' => $it['id'] ) ) ) . '"><div class="wk-cover"><span>' . esc_html( $it['icon'] ) . '</span><i class="wk-spark">✦</i><i class="wk-spark b">✧</i></div>'
					. '<b>' . esc_html( $it['title'] ) . '</b><em>' . ( $r ? '✅ خواندی' : 'تازه' ) . '</em></a>';
			}
			$h .= '</div></section>';
		}

		// بازی کن و یاد بگیر
		if ( $games ) {
			$all = HSSP_Settings::games();
			$h  .= '<section class="wk-sec"><div class="wk-head"><h2 class="wk-h">🎮 بازی کن و یاد بگیر</h2><a href="' . esc_url( self::url( 'games', $ctx ) ) . '">همه ←</a></div><div class="wk-row">';
			foreach ( $games as $i => $gid ) {
				if ( isset( $all[ $gid ] ) ) {
					$h .= '<a class="wk-game g' . ( $i % 5 ) . '" href="' . esc_url( self::url( 'games', $ctx, array( 'game' => $gid ) ) ) . '"><span class="wk-game-ic">' . esc_html( $all[ $gid ][0] ) . '</span><b>' . esc_html( $all[ $gid ][1] ) . '</b><span class="wk-play">▶</span></a>';
				}
			}
			$h .= '</div></section>';
		}

		// ماجرای امروز
		if ( $feat ) {
			$h .= '<section class="wk-sec"><a class="wk-feat" href="' . esc_url( $feat[3] ) . '"><div class="wk-feat-art"><span>' . esc_html( $feat[0] ) . '</span></div><div class="wk-feat-tx"><b>' . esc_html( $feat[1] ) . '</b><small>' . esc_html( $feat[2] ) . '</small><span class="wk-cta sm">' . esc_html( $feat[4] ) . ' ←</span></div></a></section>';
		}
		return $h . '<div class="wk-foot">🦊 هر روز یک قدم! 🌱</div>';
	}

	/* ---------- برنامه‌ی من (۱۴ روز) ---------- */

	public static function plan_events( $sid, $from, $to ) {
		$ev  = array();
		$add = function ( $d, $icon, $title, $sub, $kind, $href = '' ) use ( &$ev ) {
			$ev[ substr( $d, 0, 10 ) ][] = compact( 'icon', 'title', 'sub', 'kind', 'href' );
		};
		$classes = HSSP_Util::student_classes( $sid );
		foreach ( $classes as $cid ) {
			if ( HSSP_Perms::can( $sid, 'homework', $cid ) ) {
				foreach ( HS_Homework_DB::get_class_homework( $cid, 60 ) as $hw ) {
					if ( $hw['due_date'] && $hw['due_date'] >= $from && $hw['due_date'] <= $to ) {
						$done = self::hw_state( $hw, $sid )[0];
						$add( $hw['due_date'], $done ? '✅' : '📚', $hw['title'], $hw['subject'] . ( $done ? ' · انجام شد' : '' ), $done ? 'done' : 'hw' );
					}
				}
			}
			if ( HSSP_Perms::can( $sid, 'calendar', $cid ) && class_exists( 'HS_Assessment_DB' ) ) {
				foreach ( HS_Assessment_DB::get_class_assessments( $cid, 60 ) as $a ) {
					if ( $a['assess_date'] && $a['assess_date'] >= $from && $a['assess_date'] <= $to ) {
						$add( $a['assess_date'], '📝', 'ارزیابی: ' . $a['title'], $a['subject'], 'assess' );
					}
				}
			}
			if ( HSSP_Perms::can( $sid, 'library', $cid ) ) {
				foreach ( HSSP_Library::items( $cid ) as $it ) {
					if ( substr( $it['created_at'], 0, 10 ) >= gmdate( 'Y-m-d', strtotime( $from . ' -3 days' ) ) && substr( $it['created_at'], 0, 10 ) <= $to ) {
						$add( max( $from, substr( $it['created_at'], 0, 10 ) ), $it['icon'], 'تازه در کتابخانه: ' . $it['title'], '', 'lib' );
					}
				}
			}
		}
		if ( $classes && class_exists( 'HS_Calendar_DB' ) && HSSP_Perms::can( $sid, 'calendar' ) ) {
			$schools = array_filter( array_map( array( 'HSSP_Perms', 'class_school' ), $classes ) );
			foreach ( (array) HS_Calendar_DB::in_range( $from, $to ) as $e ) {
				$ok = ( 'class' === $e['audience'] && in_array( (int) $e['class_id'], $classes, true ) )
					|| ( 'all' === $e['audience'] && ( empty( $e['school_id'] ) || in_array( (int) $e['school_id'], $schools, true ) ) && ( empty( $e['class_id'] ) || in_array( (int) $e['class_id'], $classes, true ) ) );
				if ( $ok ) {
					$add( $e['event_date'], '📌', $e['title'], '', 'event' );
				}
			}
		}
		ksort( $ev );
		return $ev;
	}

	private static function plan( $ctx ) {
		$from = current_time( 'Y-m-d' );
		$to   = gmdate( 'Y-m-d', strtotime( $from . ' +14 days' ) );
		$ev   = self::plan_events( $ctx['sid'], $from, $to );
		$late = array();
		foreach ( self::open_homework( $ctx['sid'], $ctx['cid'] ) as $hw ) {
			if ( $hw['due_date'] && $hw['due_date'] < $from ) {
				$late[] = $hw;
			}
		}
		$h = '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>🗓️ برنامه‌ی دو هفته‌ی من</h2></div>';
		if ( $late ) {
			$h .= '<div class="hssp-late"><strong>⏰ عقب‌افتاده:</strong> ';
			foreach ( $late as $hw ) {
				$h .= '<a href="' . esc_url( self::hw_href( $hw, $ctx ) ) . '">' . esc_html( $hw['title'] ) . '</a> ';
			}
			$h .= '</div>';
		}
		if ( ! $ev ) {
			return $h . '<div class="hs-kid-celebrate"><div class="hs-kid-celebrate-emoji">🌈</div><p>در دو هفته‌ی آینده برنامه‌ی ثبت‌شده‌ای نداری.</p></div></section>';
		}
		$h .= '<ol class="hssp-plan">';
		foreach ( $ev as $d => $items ) {
			$n   = (int) round( ( strtotime( $d ) - strtotime( $from ) ) / DAY_IN_SECONDS );
			$rel = 0 === $n ? 'امروز' : ( 1 === $n ? 'فردا' : $n . ' روز دیگر' );
			$h  .= '<li class="' . ( $n <= 1 ? 'is-soon' : '' ) . '"><div class="hssp-plan-d"><b>' . esc_html( HSSP_Util::weekday( $d ) ) . '</b>' . esc_html( HSSP_Util::d( $d, false ) ) . '<small>' . esc_html( $rel ) . '</small></div><ul>';
			foreach ( $items as $it ) {
				$h .= '<li class="k-' . esc_attr( $it['kind'] ) . '"><span>' . esc_html( $it['icon'] ) . '</span> ' . esc_html( $it['title'] ) . ( $it['sub'] ? ' <small>' . esc_html( $it['sub'] ) . '</small>' : '' ) . '</li>';
			}
			$h .= '</ul></li>';
		}
		return $h . '</ol></section>';
	}

	/* ---------- تکالیف ---------- */

	private static function homework( $ctx ) {
		$sid = $ctx['sid'];
		$cid = $ctx['cid'];
		if ( ! $cid ) {
			return '<div class="hs-kid-celebrate"><p>هنوز کلاسی نداری.</p></div>';
		}
		$hws = HS_Homework_DB::get_class_homework( $cid, 40 );
		$h   = '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>📚 تکلیف‌های من</h2></div>';
		if ( ! $hws ) {
			return $h . '<div class="hs-kid-celebrate"><div class="hs-kid-celebrate-emoji">📭</div><p>معلم هنوز تکلیفی نفرستاده.</p></div></section>';
		}
		$self = $sid === $ctx['viewer'];
		$h   .= '<div class="hs-kid-hw-stack">';
		foreach ( $hws as $hw ) {
			list( $done, $mine, $sub ) = self::hw_state( $hw, $sid );
			$online = isset( $hw['mode'] ) && 'online' === $hw['mode'];
			$h     .= '<article class="hs-kid-hw ' . ( $done ? 'done' : '' ) . '" data-hw="' . (int) $hw['id'] . '"><div class="hs-kid-hw-flag">' . ( $done ? '✅' : '📝' ) . '</div><div class="hs-kid-hw-body"><strong>' . esc_html( $hw['title'] ) . '</strong>';
			if ( $hw['subject'] || $hw['due_date'] ) {
				$h .= '<span class="hs-kid-hw-meta">' . esc_html( trim( $hw['subject'] . ' · ' . self::due_text( $hw['due_date'] ), ' ·' ) ) . '</span>';
			}
			if ( ! empty( $hw['description'] ) ) {
				$h .= '<div class="hssp-hw-desc">' . nl2br( esc_html( $hw['description'] ) ) . '</div>';
			}
			if ( $sub && HSSP_Perms::can( $sid, 'assessment', $cid ) ) {
				$h .= '<span class="hs-kid-hw-score">نتیجه: ' . esc_html( $sub['auto_score'] . ' از ' . $sub['max_score'] ) . '</span>';
			} elseif ( $mine && ! empty( $mine['feedback'] ) && 0 !== strpos( $mine['feedback'], 'عکس پاسخ:' ) ) {
				$h .= '<span class="hs-kid-hw-fb">💬 ' . esc_html( $mine['feedback'] ) . '</span>';
			}
			$h .= '<div class="hs-kid-hw-acts">';
			if ( $online ) {
				$h .= '<a class="hs-kid-btn" href="' . esc_url( self::hw_href( $hw, $ctx ) ) . '">' . ( $done ? 'نتیجه' : 'شروع آنلاین' ) . '</a>';
			}
			if ( ! $done && $self && class_exists( 'HS_Upload' ) ) {
				$h .= HS_Upload::picker( 'hs-sw-photo-' . (int) $hw['id'], 'general', array( 'label' => 'عکس دفتر', 'class' => 'hs-kid-photo-input hs-sw-photo-input' ) )
					. '<button type="button" class="hs-kid-btn hs-kid-btn-soft hs-sw-submit-photo" data-hw="' . (int) $hw['id'] . '">📷 ثبت عکس</button>';
			}
			$h .= '</div></div></article>';
		}
		return $h . '</div></section>';
	}

	/* ---------- کارنامه ---------- */

	public static function report_data( $sid, $cid, $y, $m ) {
		$ym = sprintf( '%04d-%02d', $y, $m );
		$d  = array( 'att' => null, 'points' => null, 'hw' => null, 'assess' => null, 'reads' => 0 );
		if ( HSSP_Perms::can( $sid, 'attendance', $cid ) && class_exists( 'HS_Attendance_DB' ) ) {
			$all      = HS_Attendance_DB::get_monthly_summary( $cid, $y, $m );
			$d['att'] = array_merge( array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0 ), (array) ( $all[ $sid ] ?? array() ) );
		}
		if ( HSSP_Perms::can( $sid, 'points', $cid ) && class_exists( 'HS_Points_DB' ) ) {
			$d['points'] = array( 'plus' => 0, 'list' => array() );
			foreach ( HS_Points_DB::get_student_history( $cid, $sid, 200 ) as $p ) {
				if ( 0 === strpos( (string) $p['created_at'], $ym ) && (int) $p['points'] > 0 ) {
					$d['points']['plus'] += (int) $p['points'];
					$key = $p['behavior_name'] ? $p['behavior_name'] : 'امتیاز';
					$d['points']['list'][ $key ] = array( $p['behavior_icon'] ? $p['behavior_icon'] : '⭐', ( $d['points']['list'][ $key ][1] ?? 0 ) + 1 );
				}
			}
		}
		if ( HSSP_Perms::can( $sid, 'homework', $cid ) ) {
			$t = 0;
			$k = 0;
			foreach ( HS_Homework_DB::get_class_homework( $cid, 200 ) as $hw ) {
				$when = $hw['due_date'] ? $hw['due_date'] : $hw['created_at'];
				if ( 0 === strpos( (string) $when, $ym ) ) {
					$t++;
					$k += self::hw_state( $hw, $sid )[0] ? 1 : 0;
				}
			}
			$d['hw'] = array( $k, $t );
		}
		if ( HSSP_Perms::can( $sid, 'assessment', $cid ) && class_exists( 'HS_Assessment_DB' ) ) {
			$d['assess'] = array();
			$lv          = HS_Assessment_DB::descriptive_levels();
			foreach ( HS_Assessment_DB::get_class_assessments( $cid, 100 ) as $a ) {
				$when = $a['assess_date'] ? $a['assess_date'] : $a['created_at'];
				if ( 0 !== strpos( (string) $when, $ym ) ) {
					continue;
				}
				$r = HS_Assessment_DB::get_results( $a['id'] )[ $sid ] ?? null;
				if ( ! $r ) {
					continue;
				}
				$val = '';
				$col = '#4F46E5';
				if ( 'numeric' === $a['scale_type'] && null !== $r['score'] && '' !== $r['score'] ) {
					$val = rtrim( rtrim( (string) $r['score'], '0' ), '.' );
				} elseif ( ! empty( $r['level_key'] ) && isset( $lv[ $r['level_key'] ] ) ) {
					$val = $lv[ $r['level_key'] ]['label'];
					$col = $lv[ $r['level_key'] ]['color'];
				}
				if ( '' !== $val ) {
					$d['assess'][] = array( 'title' => $a['title'], 'subject' => $a['subject'], 'value' => $val, 'color' => $col, 'comment' => (string) $r['comment'] );
				}
			}
		}
		if ( HSSP_Perms::can( $sid, 'library', $cid ) ) {
			foreach ( HSSP_Library::reads_of( $sid ) as $r ) {
				$d['reads'] += 0 === strpos( $r['read_at'], $ym ) ? 1 : 0;
			}
		}
		return $d;
	}

	private static function report( $ctx ) {
		$sid = $ctx['sid'];
		$cid = $ctx['cid'];
		if ( ! $cid ) {
			return '<div class="hs-kid-celebrate"><p>هنوز کلاسی نداری.</p></div>';
		}
		$months = array();
		$t      = strtotime( current_time( 'Y-m-01' ) );
		for ( $i = 0; $i < 4; $i++ ) {
			$ts       = strtotime( "-$i month", $t );
			$months[] = array( (int) gmdate( 'Y', $ts ), (int) gmdate( 'n', $ts ) );
		}
		$mo = isset( $_GET['mo'] ) ? max( 0, min( 3, (int) $_GET['mo'] ) ) : 0; // phpcs:ignore
		list( $y, $m ) = $months[ $mo ];
		$d    = self::report_data( $sid, $cid, $y, $m );
		$kid  = 'kid' === $ctx['theme'];
		$lab  = function ( $yy, $mm ) {
			$a = sprintf( '%04d-%02d-01', $yy, $mm );
			return HSSP_Util::d( $a, false ) . ' تا ' . HSSP_Util::d( gmdate( 'Y-m-t', strtotime( $a ) ), false );
		};
		$h = '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>📊 کارنامه‌ی من</h2></div><nav class="hssp-months">';
		foreach ( $months as $i => $mm ) {
			$h .= '<a class="' . ( $i === $mo ? 'is-on' : '' ) . '" href="' . esc_url( self::url( 'report', $ctx, array( 'mo' => $i ) ) ) . '">' . esc_html( 0 === $i ? 'این ماه' : $lab( $mm[0], $mm[1] ) ) . '</a>';
		}
		$h .= '</nav><div class="hssp-stats">';
		if ( $d['att'] ) {
			$h .= '<div class="hssp-stat s-green"><b>' . (int) $d['att']['present'] . '</b><span>روز حاضر' . ( $d['att']['absent'] ? ' · ' . (int) $d['att']['absent'] . ' غیبت' : '' ) . ( $d['att']['late'] ? ' · ' . (int) $d['att']['late'] . ' تأخیر' : '' ) . '</span></div>';
		}
		if ( $d['hw'] ) {
			$h .= '<div class="hssp-stat s-blue"><b>' . (int) $d['hw'][0] . '/' . (int) $d['hw'][1] . '</b><span>تکلیف انجام دادی</span></div>';
		}
		if ( $d['points'] ) {
			$h .= '<div class="hssp-stat s-amber"><b>' . (int) $d['points']['plus'] . '</b><span>امتیاز تشویقی گرفتی</span></div>';
		}
		if ( $d['reads'] ) {
			$h .= '<div class="hssp-stat s-pink"><b>' . (int) $d['reads'] . '</b><span>کتاب و داستان خواندی</span></div>';
		}
		$h .= '</div>';
		if ( $d['points'] && $d['points']['list'] ) {
			$h .= '<h3 class="hs-kid-mini-title">' . ( $kid ? '🌟 چیزهایی که برایش تشویق شدی' : 'نقاط قوت این ماه' ) . '</h3><div class="hssp-chips">';
			arsort( $d['points']['list'] );
			foreach ( $d['points']['list'] as $name => $v ) {
				$h .= '<span>' . esc_html( $v[0] . ' ' . $name ) . ( $v[1] > 1 ? ' ×' . (int) $v[1] : '' ) . '</span>';
			}
			$h .= '</div>';
		}
		if ( null !== $d['assess'] ) {
			$h .= '<h3 class="hs-kid-mini-title">📝 ارزیابی‌ها</h3>';
			if ( $d['assess'] ) {
				$h .= '<ul class="hssp-assess">';
				foreach ( $d['assess'] as $a ) {
					$h .= '<li><div><strong>' . esc_html( $a['title'] ) . '</strong>' . ( $a['subject'] ? ' <small>' . esc_html( $a['subject'] ) . '</small>' : '' ) . ( $a['comment'] ? '<p>💬 ' . esc_html( $a['comment'] ) . '</p>' : '' ) . '</div><span class="hssp-lv" style="--c:' . esc_attr( $a['color'] ) . '">' . esc_html( $a['value'] ) . '</span></li>';
				}
				$h .= '</ul>';
			} else {
				$h .= '<p class="hssp-muted">در این ماه هنوز نتیجه‌ای ثبت نشده.</p>';
			}
		}
		$h .= '<p class="hssp-muted hssp-encourage">' . ( $kid ? '🌱 هر روز یک قدم! مهم این است که از ماه قبل بهتر شوی.' : 'این کارنامه فقط برای خودت و خانواده‌ات است؛ با کسی مقایسه نمی‌شوی.' ) . '</p>';
		return $h . '</section>';
	}

	/* ---------- بازی ---------- */

	private static function games_hub( $ctx ) {
		$all = HSSP_Settings::games();
		$ids = $ctx['cid'] ? HSSP_Settings::class_games( $ctx['cid'] ) : array( 'letters', 'count', 'add', 'memory' );
		$h   = '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>🎮 بازی و یادگیری</h2></div><p class="hs-kid-lead">یک بازی انتخاب کن' . ( HSSP_Perms::can( $ctx['sid'], 'points', $ctx['cid'] ) ? ' و امتیاز بگیر! (روزی ۵ امتیاز)' : '!' ) . '</p><div class="hs-kid-games">';
		if ( ! $ids ) {
			return $h . '</div><div class="hs-kid-celebrate"><p>معلمت فعلاً بازی‌ای روشن نکرده.</p></div></section>';
		}
		foreach ( $ids as $id ) {
			if ( ! isset( $all[ $id ] ) ) {
				continue;
			}
			$g  = $all[ $id ];
			$h .= '<a class="hs-kid-game-card ' . esc_attr( $g[5] ) . '" href="' . esc_url( self::url( 'games', $ctx, array( 'game' => $id ) ) ) . '"><span class="hs-kid-game-icon">' . esc_html( $g[0] ) . '</span><strong>' . esc_html( $g[1] ) . '</strong><span>' . esc_html( $g[2] ) . '</span><span class="hs-kid-game-play">بازی ←</span></a>';
		}
		return $h . '</div></section>';
	}

	private static function game( $game, $ctx ) {
		$back = self::url( 'games', $ctx );
		if ( ! in_array( $game, HSSP_Settings::class_games( $ctx['cid'] ), true ) ) {
			return '<section class="hs-kid-block"><a class="hs-kid-back" href="' . esc_url( $back ) . '">← بازگشت</a><p>این بازی برای کلاس تو روشن نیست.</p></section>';
		}
		$g     = HSSP_Settings::games()[ $game ];
		$grade = $ctx['cid'] ? HSSP_Util::grade_num( $ctx['cid'] ) : 1;
		return '<section class="hs-kid-block hs-kid-game-play"><a class="hs-kid-back" href="' . esc_url( $back ) . '">← بازگشت به بازی‌ها</a><h2 class="hssp-game-title">' . esc_html( $g[0] . ' ' . $g[1] ) . '</h2>'
			. '<div id="hs-kid-game-root" data-game="' . esc_attr( $game ) . '" data-class="' . (int) $ctx['cid'] . '" data-grade="' . (int) max( 0, $grade ) . '" data-self="' . ( $ctx['sid'] === $ctx['viewer'] ? 1 : 0 ) . '"><div class="hs-kid-game-loading">در حال آماده‌سازی بازی... 🎲</div></div>'
			. '<div class="hs-kid-game-scoreboard" id="hs-kid-scoreboard" style="display:none;"><div class="hs-kid-celebrate"><div class="hs-kid-celebrate-emoji" id="hs-kid-result-emoji">⭐</div><p id="hs-kid-result-text"></p><p id="hs-kid-result-msg" class="hssp-muted"></p>'
			. '<button type="button" class="hs-kid-btn hs-kid-btn-big" id="hs-kid-play-again">دوباره بازی کن</button> <a class="hs-kid-btn hs-kid-btn-soft" href="' . esc_url( $back ) . '">بازی‌های دیگر</a></div></div></section>';
	}

	/* ---------- جایزه ---------- */

	private static function rewards( $ctx ) {
		$sid     = $ctx['sid'];
		$cid     = $ctx['cid'];
		$stars   = $cid ? (int) HS_Points_DB::get_student_total( $cid, $sid ) : 0;
		$history = $cid ? HS_Points_DB::get_student_history( $cid, $sid, 15 ) : array();
		$badges  = array( array( 1, '🌱', 'جوانه' ), array( 10, '⭐', 'ستاره' ), array( 25, '🔥', 'تلاشگر' ), array( 50, '🏆', 'قهرمان' ), array( 100, '👑', 'افسانه' ) );
		$next    = null;
		foreach ( $badges as $b ) {
			if ( $stars < $b[0] ) {
				$next = $b;
				break;
			}
		}
		$h = '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>🏆 جایزه‌های من</h2></div><div class="hs-kid-trophy"><div class="hs-kid-trophy-num">' . $stars . '</div><div class="hs-kid-trophy-lbl">امتیاز جمع کرده‌ای</div>'
			. '<div class="hs-kid-trophy-bar"><div class="hs-kid-trophy-fill" style="width:' . ( $next ? (int) min( 100, $stars * 100 / $next[0] ) : 100 ) . '%"></div></div>'
			. ( $next ? '<small>' . (int) ( $next[0] - $stars ) . ' امتیاز تا نشان «' . esc_html( $next[2] ) . '» ' . esc_html( $next[1] ) . '</small>' : '<small>همه‌ی نشان‌ها را گرفتی! 👑</small>' ) . '</div><div class="hs-kid-badge-row">';
		foreach ( $badges as $b ) {
			$on = $stars >= $b[0];
			$h .= '<div class="hs-kid-medal ' . ( $on ? 'on' : 'off' ) . '"><span>' . esc_html( $b[1] ) . '</span><strong>' . esc_html( $b[2] ) . '</strong><small>' . ( $on ? 'باز شد!' : (int) $b[0] . '+' ) . '</small></div>';
		}
		$h .= '</div>';
		$pos = array_filter( $history, function ( $x ) {
			return (int) $x['points'] > 0;
		} );
		if ( $pos ) {
			$h .= '<h3 class="hs-kid-mini-title">آخرین امتیازها</h3><ul class="hs-kid-star-log">';
			foreach ( $pos as $x ) {
				$h .= '<li><span>' . esc_html( $x['behavior_icon'] ? $x['behavior_icon'] : '⭐' ) . '</span><span>' . esc_html( $x['behavior_name'] ? $x['behavior_name'] : ( $x['note'] ? $x['note'] : 'امتیاز' ) ) . '</span><strong>+' . (int) $x['points'] . '</strong></li>';
			}
			$h .= '</ul>';
		}
		return $h . '</section>';
	}

	/* ---------- پیام ---------- */

	private static function messages( $ctx ) {
		$sid   = $ctx['sid'];
		$inbox = class_exists( 'HS_Comms_DB' ) ? HS_Comms_DB::get_inbox( $sid, 25 ) : array();
		$ann   = array();
		foreach ( $ctx['classes'] as $c ) {
			if ( HSSP_Perms::can( $sid, 'messages', $c ) ) {
				$ann = array_merge( $ann, HS_Comms_DB::get_announcements( $c, 10 ) );
			}
		}
		usort( $ann, function ( $a, $b ) {
			return strcmp( $b['created_at'], $a['created_at'] );
		} );
		$h = '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>💬 پیام‌ها و اطلاعیه‌ها</h2></div>';
		if ( ! $inbox && ! $ann ) {
			return $h . '<div class="hs-kid-celebrate"><div class="hs-kid-celebrate-emoji">💌</div><p>پیام تازه‌ای نیست.</p></div></section>';
		}
		foreach ( array_slice( $ann, 0, 15 ) as $a ) {
			$h .= '<div class="hs-kid-msg-bubble announce"><strong>📢 ' . esc_html( $a['title'] ? $a['title'] : 'اطلاعیه کلاس' ) . '</strong><p>' . nl2br( esc_html( $a['body'] ) ) . '</p><small>' . esc_html( HSSP_Util::d( $a['created_at'], false ) ) . '</small></div>';
		}
		foreach ( $inbox as $m ) {
			$s  = get_userdata( $m['sender_id'] );
			$h .= '<div class="hs-kid-msg-bubble"><strong>' . esc_html( $s ? $s->display_name : 'معلم' ) . '</strong><p>' . nl2br( esc_html( $m['body'] ) ) . '</p><small>' . esc_html( HSSP_Util::d( $m['created_at'], false ) ) . '</small></div>';
		}
		return $h . '</section>';
	}

	/* ---------- AJAX ---------- */

	public static function submit_photo() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده؛ صفحه را تازه کن.' ) );
		}
		$uid = get_current_user_id();
		$hw  = HS_Homework_DB::get( absint( $_POST['homework_id'] ?? 0 ) );
		$url = esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ), array( 'http', 'https' ) );
		if ( ! $hw ) {
			wp_send_json_error( array( 'message' => 'تکلیف پیدا نشد.' ) );
		}
		// فقط خود دانش‌آموزِ همان کلاس (نمای معلم فقط‌خواندنی است)
		if ( ! HSSP_Util::in_class( $uid, $hw['class_id'] ) || ! HSSP_Perms::can( $uid, 'homework', $hw['class_id'] ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) );
		}
		if ( ! $url ) {
			wp_send_json_error( array( 'message' => 'ابتدا عکس را انتخاب کن.' ) );
		}
		HS_Homework_DB::set_status( $hw['id'], $uid, 'done', 'عکس پاسخ: ' . $url );
		wp_send_json_success( array( 'message' => 'آفرین! پاسخت ثبت شد ⭐' ) );
	}

	public static function save_game_score() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'خطا' ) );
		}
		$sid   = get_current_user_id();
		$cid   = absint( $_POST['class_id'] ?? 0 );
		$score = absint( $_POST['score'] ?? 0 );
		$total = max( 1, absint( $_POST['total'] ?? 5 ) );
		$game  = sanitize_key( $_POST['game'] ?? '' );
		if ( ! $cid || ! HSSP_Util::in_class( $sid, $cid ) || ! in_array( $game, HSSP_Settings::class_games( $cid ), true ) || ! HSSP_Perms::can( $sid, 'games', $cid ) ) {
			wp_send_json_error( array( 'message' => 'این بازی برای کلاس تو روشن نیست.' ) );
		}
		if ( ! HSSP_Perms::can( $sid, 'points', $cid ) || $score * 2 < min( $total, 10 ) ) {
			wp_send_json_success( array( 'message' => $score * 2 < min( $total, 10 ) ? 'برای امتیاز، دست‌کم نصف را درست جواب بده. دوباره امتحان کن!' : 'آفرین!' ) );
		}
		$key  = 'hs_game_' . $sid . '_' . current_time( 'Ymd' );
		$used = (int) get_transient( $key );
		if ( $used >= 5 ) {
			wp_send_json_success( array( 'message' => 'امتیاز بازی امروزت کامل شد. فردا دوباره! 🌙' ) );
		}
		set_transient( $key, $used + 1, DAY_IN_SECONDS );
		HS_Points_DB::award( $cid, $sid, 0, 1, 'بازی: ' . HSSP_Settings::games()[ $game ][1] );
		wp_send_json_success( array( 'message' => '⭐ یک امتیاز گرفتی! (' . ( 5 - $used - 1 ) . ' امتیاز دیگر امروز)' ) );
	}
}

HSSP_World::init();
