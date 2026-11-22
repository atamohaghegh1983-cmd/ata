<?php
/**
 * اتصال به پنل (قالب، منو، داشبورد، صفحه‌ی ورود)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Integration {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_filter( 'hs_shell_menu', array( __CLASS__, 'menu' ), 15, 2 );
		add_filter( 'hs_parent_dashboard_html', array( __CLASS__, 'dashboard' ), 10, 2 );
		add_filter( 'hs_guest_landing_roles', array( __CLASS__, 'guest_role' ) );
	}

	public static function pages() {
		return array(
			'parent'        => array( 'پنل والدین', '[hamshagerdi_parent_panel]' ),
			'parent-chat'   => array( 'گروه والدین', '[hamshagerdi_parent_chat]' ),
			'parent-access' => array( 'دسترسی والدین', '[hs_parent_access]' ),
			'parent-meetings' => array( 'درخواست‌های جلسه', '[hs_parent_meetings]' ),
			'home-practice'   => array( 'تمرین در خانه', '[hs_home_practice]' ),
		);
	}

	public static function page_url( $slug ) {
		$p = get_page_by_path( 'panel/' . $slug );
		if ( ! $p ) {
			$p = get_page_by_path( $slug );
		}
		return $p ? get_permalink( $p ) : home_url( '/panel/' . $slug . '/' );
	}

	/** صفحه‌ها اگر نبودند ساخته می‌شوند (صفحه‌های قبلی «مدیریت کلاس» همان‌طور می‌مانند) */
	public static function create_pages() {
		$panel = get_page_by_path( 'panel' );
		foreach ( self::pages() as $slug => $p ) {
			if ( get_page_by_path( 'panel/' . $slug ) || get_page_by_path( $slug ) ) {
				continue;
			}
			wp_insert_post( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $p[0],
				'post_name'    => $slug,
				'post_parent'  => $panel ? $panel->ID : 0,
				'post_content' => $p[1],
			) );
		}
	}

	public static function assets() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$on = class_exists( 'HS_Shell' ) && HS_Shell::is_panel_request();
		if ( ! $on ) {
			$post = get_post();
			$on   = $post && ( has_shortcode( $post->post_content, 'hamshagerdi_parent_panel' ) || has_shortcode( $post->post_content, 'hamshagerdi_parent_chat' ) || has_shortcode( $post->post_content, 'hs_parent_access' ) || has_shortcode( $post->post_content, 'hs_parent_meetings' ) || has_shortcode( $post->post_content, 'hs_home_practice' ) );
		}
		if ( ! $on ) {
			return;
		}
		wp_enqueue_style( 'hspp', HSPP_URL . 'assets/parent.css', ( wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array() ), HSPP_VERSION );
		wp_enqueue_script( 'hspp', HSPP_URL . 'assets/parent.js', array( 'jquery' ), HSPP_VERSION, true );
		wp_localize_script( 'hspp', 'hsParent', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hs_class_nonce' ),
		) );
	}

	/** منوی پنل */
	public static function menu( $menu, $type ) {
		if ( ! is_array( $menu ) ) {
			return $menu;
		}
		if ( 'parent' === $type || ( 'other' === $type && HS_Parent_Link::get_children( get_current_user_id() ) ) ) {
			$items = array(
				array( 'parent', 'پنل والدین', '👨‍👩‍👧', 'blue' ),
				array( 'parent-chat', 'گروه والدین', '💬', 'pink', 'unread' ),
			);
			$done = false;
			foreach ( $menu as $i => $g ) { // در گروه موجود «فرزندان من» ادغام شود
				if ( 'فرزندان من' === $g[0] ) {
					$menu[ $i ][1] = array_merge( $items, (array) $g[1] );
					$done          = true;
					break;
				}
			}
			if ( ! $done ) {
				$menu[] = array( 'فرزندان من', $items );
			}
		}
		if ( 'teacher' === $type ) {
			$n   = HSPP_Meetings::pending_count( get_current_user_id() );
			$add = array(
				'ارتباط'        => array( 'parent-meetings', 'درخواست‌های جلسه' . ( $n ? ' (' . $n . ')' : '' ), '🤝', 'teal' ),
				'تکلیف و محتوا' => array( 'home-practice', 'تمرین در خانه', '🏡', 'green' ),
			);
			foreach ( $menu as $i => $g ) {
				if ( isset( $add[ $g[0] ] ) ) {
					$menu[ $i ][1][] = $add[ $g[0] ];
					unset( $add[ $g[0] ] );
				}
			}
			if ( $add ) {
				$menu[] = array( 'والدین', array_values( $add ) );
			}
		}
		if ( 'manager' === $type ) {
			foreach ( $menu as $i => $g ) {
				if ( 'مدیریت مدرسه' === $g[0] ) {
					$menu[ $i ][1][] = array( 'parent-access', 'دسترسی والدین', '🔐', 'violet' );
					return $menu;
				}
			}
			$menu[] = array( 'مدیریت مدرسه', array( array( 'parent-access', 'دسترسی والدین', '🔐', 'violet' ) ) );
		}
		return $menu;
	}

	public static function today_attendance_stat( $pid, $child_id ) {
		if ( ! $child_id || ! HSPP_Perms::can( $pid, $child_id, 'attendance' ) || ! class_exists( 'HS_Attendance_DB' ) ) {
			return '';
		}
		$labels = array( 'present' => array( '✅', 'حاضر' ), 'absent' => array( '❌', 'غایب' ), 'late' => array( '⏰', 'تأخیر' ), 'excused' => array( '📝', 'غیبت موجه' ) );
		$today  = current_time( 'Y-m-d' );
		$st     = '';
		foreach ( HSPP_Perms::student_classes( $child_id ) as $cid ) {
			$rows = HS_Attendance_DB::get_by_class_date( $cid, $today );
			if ( isset( $rows[ $child_id ] ) ) {
				$st = $rows[ $child_id ]['status'];
				break;
			}
		}
		$l = $labels[ $st ] ?? array( '—', 'ثبت نشده' );
		return '<div class="hs-pp-stat"><div class="num">' . esc_html( $l[0] ) . '</div><div class="lbl">امروز: ' . esc_html( $l[1] ) . '</div></div>';
	}

	/** داشبورد اصلی (/panel/) برای والد: کارت هر فرزند */
	public static function dashboard( $html, $uid ) {
		$kids = HS_Parent_Link::get_children( $uid );
		if ( ! $kids ) {
			$link = class_exists( 'HSUM_Front' ) ? HSUM_Front::page_url() : self::page_url( 'parent' );
			return '<section class="hs-card hs-dash-card"><h3>👋 هنوز فرزندی به حساب شما متصل نیست</h3><p class="hs-muted">کد یا لینک دعوت کلاس را از معلم بگیرید، یا اگر معلم شماره‌ی شما را ثبت کرده، اتصال را تأیید کنید.</p><a class="hs-btn hs-btn-primary" href="' . esc_url( $link ) . '">اتصال فرزند</a></section>';
		}
		$h = '<div class="hs-grid hs-grid-kids">';
		foreach ( $kids as $sid ) {
			$u = get_userdata( $sid );
			if ( ! $u ) {
				continue;
			}
			$classes = HS_Parent_Link::student_classes( $sid );
			$c0      = $classes[0] ?? null;
			$school  = '';
			if ( $c0 ) {
				$s      = HSPP_Perms::class_school( $c0['id'] );
				$school = $s ? get_the_title( $s ) : (string) get_post_meta( $c0['id'], '_hs_school', true );
			}
			$open = 0;
			if ( HSPP_Perms::can( $uid, $sid, 'homework' ) && class_exists( 'HS_Homework_DB' ) ) {
				foreach ( $classes as $c ) {
					foreach ( HS_Homework_DB::get_class_homework( $c['id'], 20 ) as $hw ) {
						$st = HS_Homework_DB::get_statuses( $hw['id'] );
						if ( empty( $st[ $sid ] ) || ! in_array( $st[ $sid ]['status'], array( 'done', 'late' ), true ) ) {
							$open++;
						}
					}
				}
			}
			$h .= '<section class="hs-card hs-kid"><div class="hs-kid-head"><span class="hs-av hs-av-lg">' . esc_html( mb_substr( $u->display_name, 0, 1 ) ) . '</span><div><h3>' . esc_html( $u->display_name ) . '</h3>'
				. '<small>' . esc_html( implode( ' · ', array_filter( array( $school, $c0 ? $c0['title'] : 'بدون کلاس', $c0 && $c0['teacher'] ? 'معلم: ' . $c0['teacher'] : '' ) ) ) ) . '</small></div></div>'
				. '<div class="hs-pp-stats">' . self::today_attendance_stat( $uid, $sid )
				. ( HSPP_Perms::can( $uid, $sid, 'homework' ) ? '<div class="hs-pp-stat"><div class="num">' . (int) $open . '</div><div class="lbl">تکلیف باز</div></div>' : '' ) . '</div>'
				. HSPP_Reads::latest_card( $uid, $sid )
				. '<a class="hs-btn hs-btn-primary hs-btn-block" href="' . esc_url( add_query_arg( 'child', $sid, self::page_url( 'parent' ) ) ) . '">جزئیات</a></section>';
		}
		return $h . '</div>';
	}

	/** صفحه‌ی ورود «والدین» برای مهمان (مدیریت کلاس ≥ 1.4) */
	public static function guest_role( $roles ) {
		$roles['parent'] = array(
			'title' => 'پنل والدین',
			'short' => 'والدین',
			'lead'  => 'از وضعیت درسی فرزندتان باخبر باشید، با معلم و والدین کلاس در ارتباط بمانید.',
			'link'  => 'parent',
			'slugs' => array( 'parent', 'parent-chat' ),
			'items' => array(
				array( '✅', 'حضور و غیاب', 'غیبت و تأخیر فرزندتان را همان روز ببینید' ),
				array( '📝', 'تکالیف', 'تکلیف‌های داده‌شده و وضعیت انجام' ),
				array( '📊', 'نمرات و گزارش پیشرفت', 'ارزیابی‌ها و گزارش معلم' ),
				array( '📢', 'اطلاعیه‌ها', 'خبرهای کلاس و مدرسه' ),
				array( '💬', 'گروه والدین کلاس', 'گفتگو با والدین دیگر، با نظارت معلم' ),
				array( '👨‍👩‍👧‍👦', 'چند فرزند', 'همه‌ی فرزندانتان در یک حساب' ),
			),
		);
		return $roles;
	}
}

HSPP_Integration::init();
