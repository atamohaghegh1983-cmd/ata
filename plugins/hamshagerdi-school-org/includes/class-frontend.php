<?php
/**
 * رابط کاربری داخل پنل همشاگردی: [hamshagerdi_school_org]  →  /panel/school/
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_SO_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_school_org', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 120 );
		add_filter( 'hs_shell_menu', array( __CLASS__, 'menu' ), 10, 2 );
		add_filter( 'hs_manager_dashboard_html', array( __CLASS__, 'manager_dashboard' ), 10, 2 );
	}

	/* ---------------- زیرساخت ---------------- */

	public static function url( $tab = '' ) {
		$u = class_exists( 'HS_Shell' ) ? HS_Shell::url( 'school' ) : home_url( '/panel/school/' );
		return $tab ? add_query_arg( 'sotab', $tab, $u ) : $u;
	}

	public static function ensure_page() {
		$existing = get_page_by_path( 'panel/school' );
		if ( ! $existing ) {
			$existing = get_page_by_path( 'school' );
		}
		if ( $existing ) {
			update_option( 'hs_so_page_id', (int) $existing->ID );
			return;
		}
		$parent = get_page_by_path( 'panel' );
		$id     = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'مدرسه من',
			'post_name'    => 'school',
			'post_content' => '[hamshagerdi_school_org]',
			'post_parent'  => $parent ? $parent->ID : 0,
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'hs_so_page_id', (int) $id );
			flush_rewrite_rules( false );
		}
	}

	public static function assets() {
		if ( ! is_user_logged_in() || ! is_page() ) {
			return;
		}
		$on = false;
		if ( class_exists( 'HS_Shell' ) && HS_Shell::is_panel_request() ) {
			$on = true;
		} else {
			$p  = get_queried_object();
			$on = $p instanceof WP_Post && has_shortcode( $p->post_content, 'hamshagerdi_school_org' );
		}
		if ( ! $on ) {
			return;
		}
		wp_enqueue_style( 'hs-so', HS_SO_URL . 'assets/css/org.css', ( wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array() ), HS_SO_VERSION );
		wp_enqueue_script( 'hs-so', HS_SO_URL . 'assets/js/org.js', array( 'jquery' ), HS_SO_VERSION, true );
		wp_localize_script( 'hs-so', 'hsSo', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'hs_so_nonce' ),
			'schoolId' => HS_SO_Store::school_id_for_user( get_current_user_id() ),
		) );
	}

	/** منوی پنل */
	public static function menu( $menu, $type ) {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return $menu;
		}
		$sid = HS_SO_Store::school_id_for_user( $uid );
		if ( 'manager' === $type ) {
			$item = array( 'school', 'مدرسه من', '🏛️', 'violet' );
			array_splice( $menu, 1, 0, array( array( 'مدیریت مدرسه', array( $item ) ) ) );
			// «تقویم مناسبتی» از منوی پایه هم می‌ماند
			$menu = array_values( array_filter( $menu, function ( $g ) {
				return ! ( 'مدیریت مدرسه' === $g[0] && count( $g[1] ) < 1 );
			} ) );
			return $menu;
		}
		if ( 'teacher' === $type && $sid ) {
			$label = HS_SO_Store::is_manager( $sid, $uid ) ? 'مدرسه من' : 'وظایف و مدرسه';
			array_splice( $menu, 1, 0, array( array( 'مدرسه', array( array( 'school', $label, '🏛️', 'violet' ) ) ) ) );
		}
		return $menu;
	}

	/** داشبورد ریشه برای مدیر (ناظر) */
	public static function manager_dashboard( $html, $uid ) {
		self::assets_force();
		return '<div class="hs-so">' . self::render_home_for( $uid ) . '</div>';
	}

	private static function assets_force() {
		wp_enqueue_style( 'hs-so', HS_SO_URL . 'assets/css/org.css', ( wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array() ), HS_SO_VERSION );
		wp_enqueue_script( 'hs-so', HS_SO_URL . 'assets/js/org.js', array( 'jquery' ), HS_SO_VERSION, true );
		wp_localize_script( 'hs-so', 'hsSo', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'hs_so_nonce' ),
			'schoolId' => HS_SO_Store::school_id_for_user( get_current_user_id() ),
		) );
	}

	/* ---------------- ابزارهای نمایش ---------------- */

	private static function fa( $v ) {
		return esc_html( HS_SO_Store::fa( $v ) );
	}

	private static function date( $ymd ) {
		return $ymd && class_exists( 'HS_Util' ) ? HS_Util::jdate( $ymd ) : '';
	}

	private static function stat( $icon, $label, $value, $color, $sub = '' ) {
		return '<div class="hs-stat hs-c-' . esc_attr( $color ) . '"><span class="hs-stat-ico">' . esc_html( $icon ) . '</span>'
			. '<div class="hs-stat-body"><div class="hs-stat-label">' . esc_html( $label ) . '</div>'
			. '<div class="hs-stat-val">' . self::fa( $value ) . '</div>'
			. ( $sub ? '<div class="hs-stat-sub">' . esc_html( $sub ) . '</div>' : '' ) . '</div></div>';
	}

	private static function province_options( $sel = '' ) {
		return class_exists( 'HS_Util' ) && method_exists( 'HS_Util', 'province_options' ) ? HS_Util::province_options( $sel ) : '<option value="' . esc_attr( $sel ) . '">' . esc_html( $sel ) . '</option>';
	}

	/* ---------------- ورودی اصلی ---------------- */

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای استفاده باید وارد شوید.</div>';
		}
		if ( ! post_type_exists( HS_SO_Store::CPT ) ) {
			return '';
		}
		$uid  = get_current_user_id();
		$user = wp_get_current_user();
		$sid  = HS_SO_Store::school_id_for_user( $uid );
		$can_create = ! $sid && array_intersect( (array) $user->roles, array( 'school_admin', 'administrator' ) );

		ob_start();
		echo '<div class="hs-so" id="hs-so" data-school="' . esc_attr( $sid ) . '">';

		if ( ! $sid ) {
			echo self::header( null, '' );
			if ( $can_create ) {
				echo self::intro_form();
			} else {
				echo '<div class="hs-empty-state"><div class="hs-empty-icon">🏛️</div><h3>هنوز مدرسه‌ای برای شما ثبت نشده</h3><p>مدیر مدرسه باید شما را به کادر مدرسه اضافه کند.</p></div>';
			}
			echo '</div>';
			return ob_get_clean();
		}

		$school = HS_SO_Store::get_school( $sid );
		$is_mgr = HS_SO_Store::is_manager( $sid );
		$sup    = HS_SO_Store::can_supervise( $sid );

		$tabs = $is_mgr
			? array( 'home' => '🏠 نمای کلی', 'school' => '🏛️ اطلاعات مدرسه', 'staff' => '👥 معاونین و کادر', 'tasks' => '✅ وظایف', 'monitor' => '🔍 نظارت', 'chart' => '🌳 چارت' )
			: array( 'mytasks' => '✅ وظایف من', 'myjob' => '📋 شرح وظایف من' ) + ( $sup ? array( 'monitor' => '🔍 نظارت' ) : array() ) + array( 'chart' => '🌳 چارت' );
		$tab = isset( $_GET['sotab'] ) ? sanitize_key( $_GET['sotab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $tab ] ) ) {
			reset( $tabs );
			$tab = key( $tabs );
		}

		echo self::header( $school, $is_mgr ? '' : 'عضو کادر' );
		echo '<nav class="hs-comms-tabs hs-so-tabs">';
		foreach ( $tabs as $k => $label ) {
			echo '<a class="hs-comms-tab' . ( $k === $tab ? ' active' : '' ) . '" href="' . esc_url( self::url( $k ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'home':
				echo self::home( $sid, $school );
				break;
			case 'school':
				echo self::school_form( $school );
				break;
			case 'staff':
				echo self::staff_tab( $sid );
				break;
			case 'tasks':
				echo self::tasks_tab( $sid, true );
				break;
			case 'mytasks':
				echo self::tasks_tab( $sid, false );
				break;
			case 'myjob':
				echo self::myjob( $sid );
				break;
			case 'monitor':
				echo self::monitor_tab( $sid, $is_mgr );
				break;
			case 'chart':
				echo self::chart( $sid, $school );
				break;
		}
		echo '</div>';
		return ob_get_clean();
	}

	private static function render_home_for( $uid ) {
		$sid = HS_SO_Store::school_id_for_user( $uid );
		ob_start();
		if ( ! $sid ) {
			echo self::intro_form();
			return ob_get_clean();
		}
		$school = HS_SO_Store::get_school( $sid );
		echo self::home( $sid, $school );
		return ob_get_clean();
	}

	private static function header( $school, $note ) {
		$h  = '<div class="hs-att-header"><div>';
		$h .= '<h2>' . esc_html( $school ? $school['name'] : 'مدرسه من' ) . '</h2>';
		if ( $school ) {
			$h .= '<p class="hs-muted">' . esc_html( trim( $school['province'] . '، ' . $school['city'], '، ' ) ) . ( $school['code'] ? ' · شمارهٔ مدرسه: ' . HS_SO_Store::fa( $school['code'] ) : '' ) . ( $note ? ' · ' . $note : '' ) . '</p>';
		} else {
			$h .= '<p class="hs-muted">معرفی مدرسه، معاونین، وظایف و نظارت</p>';
		}
		$h .= '</div></div>';
		if ( $school && HS_SO_Store::is_admin_user() ) {
			$all = get_posts( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
			if ( count( $all ) > 1 ) {
				$h .= '<form method="get" class="hs-so-switch"><label>مدرسه (مدیر سایت): <select name="school_id" onchange="this.form.submit()">';
				foreach ( $all as $p ) {
					$h .= '<option value="' . (int) $p->ID . '"' . selected( $school['id'], $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
				}
				$h .= '</select></label></form>';
			}
		}
		return $h;
	}

	/* ---------------- معرفی مدرسه ---------------- */

	private static function intro_form() {
		return '<div class="hs-card hs-so-intro"><div class="hs-so-intro-ico">🏛️</div><h3>مدرسهٔ خود را معرفی کنید</h3><p class="hs-muted">با ثبت مشخصات مدرسه می‌توانید معاونین و کادر را اضافه کنید، شرح وظایف بنویسید، وظیفه واگذار کنید و فعالیت کلاس‌ها را زیر نظر بگیرید.</p>'
			. self::school_form( null, true ) . '</div>';
	}

	private static function school_form( $s, $bare = false ) {
		$s   = $s ? $s : array( 'name' => '', 'province' => '', 'city' => '', 'address' => '', 'postal' => '', 'code' => '', 'phone' => '', 'level' => '' );
		$lv  = '';
		foreach ( HS_SO_Store::levels() as $k => $l ) {
			$lv .= '<option value="' . esc_attr( $k ) . '"' . selected( $s['level'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$f  = '<form class="hs-so-form' . ( $bare ? '' : ' hs-card' ) . '" data-so="save_school">';
		$f .= '<div class="hs-form-grid"><div class="hs-form-row"><label>نام مدرسه <span class="required">*</span></label><input type="text" name="name" value="' . esc_attr( $s['name'] ) . '" required placeholder="مثلاً: دبستان شهید بهشتی"></div>';
		$f .= '<div class="hs-form-row"><label>شمارهٔ (کد) مدرسه <span class="required">*</span></label><input type="text" name="code" inputmode="numeric" value="' . esc_attr( HS_SO_Store::fa( $s['code'] ) ) . '" required dir="ltr"></div></div>';
		$f .= '<div class="hs-form-grid"><div class="hs-form-row"><label>استان <span class="required">*</span></label><select name="province" required>' . self::province_options( $s['province'] ) . '</select></div>';
		$f .= '<div class="hs-form-row"><label>شهر <span class="required">*</span></label><input type="text" name="city" value="' . esc_attr( $s['city'] ) . '" required></div></div>';
		$f .= '<div class="hs-form-row"><label>آدرس کامل <span class="required">*</span></label><textarea name="address" rows="2" required placeholder="خیابان، کوچه، پلاک…">' . esc_textarea( $s['address'] ) . '</textarea></div>';
		$f .= '<div class="hs-form-grid"><div class="hs-form-row"><label>کد پستی (۱۰ رقم) <span class="required">*</span></label><input type="text" name="postal" inputmode="numeric" maxlength="10" value="' . esc_attr( HS_SO_Store::fa( $s['postal'] ) ) . '" required dir="ltr"></div>';
		$f .= '<div class="hs-form-row"><label>تلفن مدرسه</label><input type="text" name="phone" value="' . esc_attr( HS_SO_Store::fa( $s['phone'] ) ) . '" dir="ltr"></div></div>';
		$f .= '<div class="hs-form-grid"><div class="hs-form-row"><label>مقطع</label><select name="level">' . $lv . '</select></div>';
		$pc  = (string) get_user_meta( get_current_user_id(), '_hs_personnel_code', true );
		$f .= '<div class="hs-form-row"><label>کد پرسنلی مدیر <span class="required">*</span></label><input type="text" name="personnel_code" inputmode="numeric" maxlength="12" value="' . esc_attr( HS_SO_Store::fa( $pc ) ) . '" required dir="ltr"><small class="hs-muted">با تغییر کد پرسنلی، نقش مدیر دوباره منتظر تأیید مدیر سایت می‌ماند.</small></div></div>';
		$f .= '<div class="hs-form-actions"><button type="submit" class="hs-btn hs-btn-primary">' . ( $bare ? '🏛️ ثبت مدرسه' : 'ذخیره اطلاعات' ) . '</button></div><div class="hs-form-message"></div></form>';
		return $f;
	}

	/* ---------------- نمای کلی ---------------- */

	private static function home( $sid, $school ) {
		$staff   = HS_SO_Store::staff_list( $sid );
		$deputies = array_filter( $staff, function ( $r ) {
			return HS_SO_Structure::is_deputy( $r['position'] );
		} );
		$teachers = array_filter( $staff, function ( $r ) {
			return 'teacher' === $r['position'];
		} );
		$cids  = HS_SO_Monitor::class_ids( $sid );
		$rows  = HS_SO_Monitor::class_rows( $cids );
		$stu   = array_sum( wp_list_pluck( $rows, 'students' ) );
		$tc    = HS_SO_Store::task_counts( $sid );
		$feed  = HS_SO_Monitor::feed( $cids, 8 );
		$is_mgr = HS_SO_Store::is_manager( $sid );

		$h  = '<div class="hs-stats">';
		$h .= self::stat( '🧑‍💼', 'معاونین', count( $deputies ), 'violet' );
		$h .= self::stat( '👩‍🏫', 'آموزگاران', count( $teachers ), 'green' );
		$h .= self::stat( '🏫', 'کلاس‌ها', count( $cids ), 'blue' );
		$h .= self::stat( '🎒', 'دانش‌آموزان', $stu, 'orange' );
		$h .= self::stat( '✅', 'وظایف باز', $tc['open'], 'teal', $tc['overdue'] ? HS_SO_Store::fa( $tc['overdue'] ) . ' مورد عقب‌افتاده' : '' );
		$h .= '</div>';

		$h .= '<div class="hs-so-cols"><section class="hs-card"><h3>🏛️ مشخصات مدرسه</h3><dl class="hs-so-dl">';
		$h .= '<dt>نشانی</dt><dd>' . esc_html( $school['province'] . '، ' . $school['city'] . '، ' . $school['address'] ) . '</dd>';
		$h .= '<dt>کد پستی</dt><dd>' . self::fa( $school['postal'] ) . '</dd>';
		$h .= '<dt>شمارهٔ مدرسه</dt><dd>' . self::fa( $school['code'] ) . '</dd>';
		if ( $school['phone'] ) {
			$h .= '<dt>تلفن</dt><dd>' . self::fa( $school['phone'] ) . '</dd>';
		}
		$mgr = get_userdata( $school['manager_id'] );
		$h  .= '<dt>مدیر</dt><dd>' . esc_html( $mgr ? $mgr->display_name : '—' ) . '</dd></dl>';
		if ( $is_mgr ) {
			$h .= '<a class="hs-btn hs-btn-sm hs-btn-ghost" href="' . esc_url( self::url( 'school' ) ) . '">ویرایش مشخصات</a>';
		}
		$h .= '</section><section class="hs-card"><h3>🔍 آخرین فعالیت‌های کلاس‌ها</h3>' . self::feed_html( $feed ) . '<a class="hs-btn hs-btn-sm hs-btn-ghost" href="' . esc_url( self::url( 'monitor' ) ) . '">نظارت کامل</a></section></div>';

		if ( $is_mgr && ! $staff ) {
			$h .= '<div class="hs-alert hs-alert-warning">هنوز کادری ثبت نشده است. از تب «معاونین و کادر» معاونین و آموزگاران را اضافه کنید تا کلاس‌ها در نظارت دیده شوند.</div>';
		}
		return $h;
	}

	private static function feed_html( $feed ) {
		if ( ! $feed ) {
			return '<p class="hs-muted">فعالیتی در ۱۴ روز اخیر ثبت نشده است.</p>';
		}
		$h = '<ul class="hs-so-feed">';
		foreach ( $feed as $e ) {
			$h .= '<li><span class="hs-so-feed-ico">' . esc_html( $e['icon'] ) . '</span><div><strong>' . esc_html( $e['text'] ) . '</strong><small>' . esc_html( $e['class'] . ( $e['by'] ? ' · ' . $e['by'] : '' ) . ' · ' . HS_SO_Monitor::ago( $e['ts'] ) ) . '</small></div></li>';
		}
		return $h . '</ul>';
	}

	/* ---------------- معاونین و کادر ---------------- */

	private static function position_options( $sel = '' ) {
		$o = '';
		foreach ( HS_SO_Structure::positions() as $k => $p ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $sel, $k, false ) . '>' . esc_html( $p['title'] ) . '</option>';
		}
		return $o;
	}

	private static function staff_tab( $sid ) {
		$staff = HS_SO_Store::staff_list( $sid );
		if ( class_exists( 'HSUM_Staff' ) ) {
			// ثبت معاونان و معلمان با دعوت‌نامه (کد، کیوآر، پیامک) از افزونه‌ی «کاربران و عضویت»
			$h = HSUM_Staff::panel( $sid );
			$h .= '<section class="hs-card"><h3>📋 شرح وظایف کادر عضوشده</h3>';
			return $h . self::staff_people( $staff );
		}
		$h  = '<section class="hs-card"><h3>➕ افزودن معاون یا عضو کادر</h3>';
		$h .= '<div class="hs-so-seg"><label><input type="radio" name="so_mode" value="new" checked> ساخت حساب جدید</label><label><input type="radio" name="so_mode" value="existing"> کاربر موجود در سایت</label></div>';
		$h .= '<form class="hs-so-form" data-so="staff_add" data-creds="1"><input type="hidden" name="mode" value="new">';
		$h .= '<div class="hs-so-mode-new"><div class="hs-form-grid"><div class="hs-form-row"><label>نام</label><input type="text" name="first_name"></div><div class="hs-form-row"><label>نام خانوادگی</label><input type="text" name="last_name"></div></div>';
		$h .= '<div class="hs-form-row"><label>شمارهٔ موبایل (نام کاربری می‌شود)</label><input type="text" name="phone" dir="ltr" inputmode="numeric" placeholder="09xxxxxxxxx"></div></div>';
		$h .= '<div class="hs-so-mode-existing" hidden><div class="hs-form-row"><label>نام کاربری یا ایمیل کاربر</label><input type="text" name="lookup" dir="ltr"></div></div>';
		$h .= '<div class="hs-form-grid"><div class="hs-form-row"><label>سمت</label><select name="position">' . self::position_options( 'deputy_edu' ) . '</select></div><div class="hs-form-row"><label>عنوان دلخواه (اختیاری)</label><input type="text" name="title" placeholder="مثلاً: معاون آموزشی پایه‌های بالا"></div></div>';
		$h .= '<label class="hs-check-label"><input type="checkbox" name="can_view" value="1"> اجازهٔ نظارت فقط‌خواندنی بر کلاس‌ها</label>';
		$h .= '<div class="hs-form-actions"><button type="submit" class="hs-btn hs-btn-primary">افزودن به کادر</button></div><div class="hs-form-message"></div></form></section>';

		$h .= '<section class="hs-card"><h3>👥 کادر مدرسه</h3>';
		return $h . self::staff_people( $staff );
	}

	private static function staff_people( $staff ) {
		$h = '';
		if ( ! $staff ) {
			return $h . '<p class="hs-muted">هنوز کسی اضافه نشده است.</p></section>';
		}
		$h .= '<div class="hs-so-people">';
		foreach ( $staff as $r ) {
			$pos   = HS_SO_Structure::positions()[ $r['position'] ] ?? array( 'color' => 'green' );
			$data  = array( 'id' => (int) $r['id'], 'name' => $r['name'], 'position' => $r['position'], 'title' => $r['title'], 'job_desc' => $r['job_desc'], 'can_view' => (int) $r['can_view'], 'job_final' => $r['job_final'] );
			$h    .= '<article class="hs-so-person hs-c-' . esc_attr( $pos['color'] ) . '">' . get_avatar( $r['user_id'], 54, '', '', array( 'class' => 'hs-so-av' ) );
			$h    .= '<div class="hs-so-person-body"><strong>' . esc_html( $r['name'] ) . '</strong><span class="hs-pill">' . esc_html( $r['label'] ) . '</span>';
			$h    .= '<small>' . esc_html( $r['login'] ) . ( $r['can_view'] ? ' · 👁️ ناظر' : '' ) . '</small></div>';
			$h    .= '<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-so-staff-edit" data-staff="' . esc_attr( wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ) . '">شرح وظایف / ویرایش</button></article>';
		}
		$h .= '</div></section>';
		$h .= self::staff_modal();
		return $h;
	}

	private static function staff_modal() {
		return '<div class="hs-modal" id="hs-so-staff-modal" hidden><div class="hs-modal-box"><h3 id="hs-so-sm-name">ویرایش</h3>'
			. '<form class="hs-so-form" data-so="staff_update"><input type="hidden" name="id">'
			. '<div class="hs-form-row"><label>سمت</label><select name="position">' . self::position_options() . '</select></div>'
			. '<div class="hs-form-row"><label>عنوان دلخواه</label><input type="text" name="title"></div>'
			. '<div class="hs-form-row"><label>شرح وظایف <small class="hs-muted">(هر خط یک وظیفه؛ خالی بگذارید تا شرح پیش‌فرض سمت نمایش داده شود)</small></label><textarea name="job_desc" rows="7"></textarea></div>'
			. '<label class="hs-check-label"><input type="checkbox" name="can_view" value="1"> اجازهٔ نظارت فقط‌خواندنی</label>'
			. '<div class="hs-form-actions"><button type="submit" class="hs-btn hs-btn-primary">ذخیره</button><button type="button" class="hs-btn hs-btn-danger" id="hs-so-staff-remove">حذف از کادر</button><button type="button" class="hs-btn hs-btn-ghost hs-so-close">بستن</button></div><div class="hs-form-message"></div></form></div></div>';
	}

	/* ---------------- وظایف ---------------- */

	private static function tasks_tab( $sid, $as_manager ) {
		$uid    = get_current_user_id();
		$args   = array();
		if ( ! $as_manager ) {
			$args['assignee'] = $uid;
		}
		$f = isset( $_GET['st'] ) ? sanitize_key( $_GET['st'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$a = $as_manager && isset( $_GET['who'] ) ? absint( $_GET['who'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $f ) {
			$args['status'] = $f;
		}
		if ( $a ) {
			$args['assignee'] = $a;
		}
		$tasks = HS_SO_Store::tasks( $sid, $args );
		$staff = HS_SO_Store::staff_list( $sid );
		$h     = '';

		if ( $as_manager ) {
			$opts = '';
			foreach ( $staff as $r ) {
				$opts .= '<option value="' . (int) $r['user_id'] . '">' . esc_html( $r['name'] . ' — ' . $r['label'] ) . '</option>';
			}
			$dom = '';
			foreach ( HS_SO_Structure::domains() as $k => $l ) {
				$dom .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
			}
			$h .= '<section class="hs-card"><h3>➕ واگذاری وظیفهٔ جدید</h3>';
			if ( ! $staff ) {
				$h .= '<p class="hs-muted">ابتدا از تب «معاونین و کادر» اعضای کادر را اضافه کنید.</p></section>';
			} else {
				$h .= '<form class="hs-so-form" data-so="task_save"><input type="hidden" name="id" value="">';
				$h .= '<div class="hs-form-row"><label>عنوان وظیفه <span class="required">*</span></label><input type="text" name="title" required placeholder="مثلاً: تکمیل بودجه‌بندی ترم دوم"></div>';
				$h .= '<div class="hs-form-row"><label>شرح</label><textarea name="description" rows="2"></textarea></div>';
				$h .= '<div class="hs-form-grid"><div class="hs-form-row"><label>مسئول انجام</label><select name="assignee_id" required>' . $opts . '</select></div><div class="hs-form-row"><label>مهلت</label><input type="date" name="due_date"></div></div>';
				$h .= '<div class="hs-form-grid"><div class="hs-form-row"><label>حوزه</label><select name="domain">' . $dom . '</select></div><div class="hs-form-row"><label>اولویت</label><select name="priority"><option value="normal">عادی</option><option value="high">فوری</option></select></div></div>';
				$h .= '<div class="hs-form-actions"><button type="submit" class="hs-btn hs-btn-primary">ثبت وظیفه</button></div><div class="hs-form-message"></div></form></section>';
			}
		}

		$h .= '<section class="hs-card"><div class="hs-card-header-row"><h3>' . ( $as_manager ? '📌 وظایف واگذارشده' : '📌 وظایف من' ) . '</h3><form method="get" class="hs-so-filter">';
		$h .= '<input type="hidden" name="sotab" value="' . ( $as_manager ? 'tasks' : 'mytasks' ) . '">';
		if ( isset( $_GET['school_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$h .= '<input type="hidden" name="school_id" value="' . absint( $_GET['school_id'] ) . '">'; // phpcs:ignore WordPress.Security.NonceVerification
		}
		$h .= '<select name="st" onchange="this.form.submit()"><option value="">همهٔ وضعیت‌ها</option>';
		foreach ( HS_SO_Structure::statuses() as $k => $l ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $f, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$h .= '</select>';
		if ( $as_manager ) {
			$h .= '<select name="who" onchange="this.form.submit()"><option value="0">همهٔ افراد</option>';
			foreach ( $staff as $r ) {
				$h .= '<option value="' . (int) $r['user_id'] . '"' . selected( $a, $r['user_id'], false ) . '>' . esc_html( $r['name'] ) . '</option>';
			}
			$h .= '</select>';
		}
		$h .= '</form></div>';

		if ( ! $tasks ) {
			return $h . '<p class="hs-muted">وظیفه‌ای یافت نشد.</p></section>';
		}
		$h .= '<div class="hs-so-tasks">';
		foreach ( $tasks as $t ) {
			$cls  = 'hs-so-task is-' . $t['status'] . ( $t['overdue'] ? ' is-overdue' : '' ) . ( 'high' === $t['priority'] ? ' is-high' : '' );
			$h   .= '<article class="' . esc_attr( $cls ) . '"><div class="hs-so-task-main"><strong>' . esc_html( $t['title'] ) . '</strong>';
			if ( $t['description'] ) {
				$h .= '<p>' . nl2br( esc_html( $t['description'] ) ) . '</p>';
			}
			$h .= '<div class="hs-so-task-meta"><span class="hs-pill hs-so-st-' . esc_attr( $t['status'] ) . '">' . esc_html( HS_SO_Structure::statuses()[ $t['status'] ] ) . '</span>';
			if ( 'high' === $t['priority'] ) {
				$h .= '<span class="hs-pill hs-so-high">⚡ فوری</span>';
			}
			if ( $t['domain'] && isset( HS_SO_Structure::domains()[ $t['domain'] ] ) ) {
				$h .= '<span class="hs-pill">' . esc_html( HS_SO_Structure::domains()[ $t['domain'] ] ) . '</span>';
			}
			if ( $as_manager ) {
				$h .= '<span>👤 ' . esc_html( $t['assignee'] ) . '</span>';
			}
			if ( $t['due_date'] ) {
				$h .= '<span' . ( $t['overdue'] ? ' class="hs-so-late"' : '' ) . '>⏰ ' . esc_html( self::date( $t['due_date'] ) ) . ( $t['overdue'] ? ' (عقب‌افتاده)' : '' ) . '</span>';
			}
			$h .= '</div>';
			if ( $t['report'] ) {
				$h .= '<p class="hs-so-report">💬 گزارش: ' . nl2br( esc_html( $t['report'] ) ) . '</p>';
			}
			$h .= '</div><div class="hs-so-task-acts">';
			if ( (int) $t['assignee_id'] === $uid ) {
				$h .= '<button type="button" class="hs-btn hs-btn-sm hs-btn-primary hs-so-task-status" data-task="' . esc_attr( wp_json_encode( array( 'id' => (int) $t['id'], 'status' => $t['status'], 'report' => $t['report'], 'title' => $t['title'] ), JSON_UNESCAPED_UNICODE ) ) . '">ثبت وضعیت</button>';
			}
			if ( $as_manager ) {
				$h .= '<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-so-task-edit" data-task="' . esc_attr( wp_json_encode( array( 'id' => (int) $t['id'], 'title' => $t['title'], 'description' => $t['description'], 'assignee_id' => (int) $t['assignee_id'], 'due_date' => $t['due_date'], 'domain' => $t['domain'], 'priority' => $t['priority'] ), JSON_UNESCAPED_UNICODE ) ) . '">ویرایش</button>';
				$h .= '<button type="button" class="hs-btn hs-btn-sm hs-btn-danger hs-so-task-del" data-id="' . (int) $t['id'] . '">حذف</button>';
			}
			$h .= '</div></article>';
		}
		$h .= '</div></section>';

		// مودال وضعیت (مسئول)
		$h .= '<div class="hs-modal" id="hs-so-status-modal" hidden><div class="hs-modal-box"><h3 id="hs-so-stm-title">وضعیت وظیفه</h3><form class="hs-so-form" data-so="task_status"><input type="hidden" name="id">'
			. '<div class="hs-form-row"><label>وضعیت</label><select name="status">';
		foreach ( HS_SO_Structure::statuses() as $k => $l ) {
			$h .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		$h .= '</select></div><div class="hs-form-row"><label>گزارش کار (اختیاری)</label><textarea name="report" rows="3"></textarea></div>'
			. '<div class="hs-form-actions"><button type="submit" class="hs-btn hs-btn-primary">ثبت</button><button type="button" class="hs-btn hs-btn-ghost hs-so-close">بستن</button></div><div class="hs-form-message"></div></form></div></div>';
		return $h;
	}

	/* ---------------- شرح وظایف من ---------------- */

	private static function myjob( $sid ) {
		$row = HS_SO_Store::staff_row_by_user( get_current_user_id() );
		if ( ! $row ) {
			return '<div class="hs-alert hs-alert-warning">شما در کادر این مدرسه ثبت نشده‌اید.</div>';
		}
		$list = HS_SO_Store::staff_list( $sid );
		$me   = null;
		foreach ( $list as $r ) {
			if ( (int) $r['user_id'] === get_current_user_id() ) {
				$me = $r;
			}
		}
		$lines = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $me['job_final'] ) ) );
		$h     = '<section class="hs-card"><h3>📋 شرح وظایف: ' . esc_html( $me['label'] ) . '</h3>';
		if ( $lines ) {
			$h .= '<ul class="hs-so-duties">';
			foreach ( $lines as $l ) {
				$h .= '<li>' . esc_html( $l ) . '</li>';
			}
			$h .= '</ul>';
		} else {
			$h .= '<p class="hs-muted">مدیر هنوز شرح وظایفی برای شما ننوشته است.</p>';
		}
		return $h . '</section>';
	}

	/* ---------------- نظارت ---------------- */

	private static function monitor_tab( $sid, $is_mgr ) {
		if ( ! HS_SO_Monitor::available() ) {
			return '<div class="hs-alert hs-alert-warning">برای نظارت، افزونهٔ «همشاگردی — مدیریت کلاس» باید فعال باشد.</div>';
		}
		$tid  = isset( $_GET['t'] ) ? absint( $_GET['t'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$cids = HS_SO_Monitor::class_ids( $sid, $tid );
		$all  = HS_SO_Monitor::class_rows( HS_SO_Monitor::class_ids( $sid ) );
		$rows = $tid ? array_values( array_filter( $all, function ( $r ) use ( $tid ) {
			return (int) $r['teacher_id'] === $tid;
		} ) ) : $all;
		$teachers = HS_SO_Monitor::teacher_rows( $sid, $all );
		$feed     = HS_SO_Monitor::feed( $cids, 25 );

		$h  = '<div class="hs-so-ro">🔒 حالت ناظر — فقط مشاهده. مدیر مدرسه در امور کلاس‌ها (حضور، تکلیف، نمره، پیام‌ها…) دخالت نمی‌کند و پیام‌های خصوصی والدین نمایش داده نمی‌شود. آمار ۳۰ روز اخیر است.</div>';
		$h .= '<section class="hs-card"><div class="hs-card-header-row"><h3>👩‍🏫 آموزگاران</h3></div>';
		if ( ! $teachers ) {
			$h .= '<p class="hs-muted">هنوز کلاسی برای کادر مدرسه ثبت نشده است. آموزگاران را از تب «معاونین و کادر» با سمت «آموزگار» اضافه کنید؛ کلاس‌های آن‌ها خودکار اینجا دیده می‌شود.</p>';
		} else {
			$h .= '<div class="hs-table-wrap"><table class="hs-table"><thead><tr><th>آموزگار</th><th>کلاس</th><th>دانش‌آموز</th><th>حضور</th><th>تکلیف</th><th>آخرین فعالیت</th></tr></thead><tbody>';
			foreach ( $teachers as $t ) {
				$stale = ! $t['last_ts'] || ( time() - $t['last_ts'] ) > 7 * DAY_IN_SECONDS;
				$h    .= '<tr><td><a href="' . esc_url( add_query_arg( array( 'sotab' => 'monitor', 't' => $t['id'] ), self::url() ) ) . '">' . esc_html( $t['name'] ) . '</a></td><td>' . self::fa( $t['classes'] ) . '</td><td>' . self::fa( $t['students'] ) . '</td><td>' . ( null === $t['att_rate'] ? '—' : self::fa( $t['att_rate'] ) . '٪' ) . '</td><td>' . self::fa( $t['homework'] ) . '</td><td class="' . ( $stale ? 'hs-so-late' : '' ) . '">' . esc_html( HS_SO_Monitor::ago( $t['last_ts'] ) ) . '</td></tr>';
			}
			$h .= '</tbody></table></div>';
		}
		$h .= '</section>';

		$h .= '<section class="hs-card"><div class="hs-card-header-row"><h3>🏫 کلاس‌ها' . ( $tid ? ' <a class="hs-so-clear" href="' . esc_url( self::url( 'monitor' ) ) . '">(نمایش همه)</a>' : '' ) . '</h3></div>';
		if ( ! $rows ) {
			$h .= '<p class="hs-muted">کلاسی یافت نشد.</p>';
		} else {
			$h .= '<div class="hs-so-classes">';
			foreach ( $rows as $r ) {
				$rate = $r['att_rate'];
				$h   .= '<article class="hs-so-class"><header><strong>' . esc_html( $r['title'] ) . '</strong><span class="hs-pill">' . esc_html( $r['grade'] ?: '—' ) . '</span></header><small>👩‍🏫 ' . esc_html( $r['teacher'] ) . ' · 🎒 ' . self::fa( $r['students'] ) . ' دانش‌آموز</small>';
				$h   .= '<div class="hs-so-bar" title="حضور ۳۰ روز اخیر"><i style="width:' . ( null === $rate ? 0 : (int) $rate ) . '%"></i></div><small>' . ( null === $rate ? 'حضور و غیابی ثبت نشده' : 'حضور: ' . HS_SO_Store::fa( $rate ) . '٪' ) . '</small>';
				$h   .= '<div class="hs-so-chips"><span>📚 ' . self::fa( $r['homework'] ) . '</span><span>📝 ' . self::fa( $r['assess'] ) . '</span><span>⭐ ' . self::fa( $r['points'] ) . '</span><span>📢 ' . self::fa( $r['comms'] ) . '</span><span>🎨 ' . self::fa( $r['portfolio'] ) . '</span></div>';
				$h   .= '<footer><small>آخرین فعالیت: ' . esc_html( HS_SO_Monitor::ago( $r['last_ts'] ) ) . '</small><button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-so-class-open" data-class="' . (int) $r['id'] . '">مشاهده جزئیات</button></footer></article>';
			}
			$h .= '</div><p class="hs-muted hs-so-legend">📚 تکلیف · 📝 ارزیابی · ⭐ امتیاز · 📢 اطلاعیه/فید · 🎨 پورتفولیو (۳۰ روز اخیر)</p>';
		}
		$h .= '</section>';

		$h .= '<section class="hs-card"><h3>🕒 فعالیت‌های اخیر</h3>' . self::feed_html( $feed ) . '</section>';
		$h .= '<div class="hs-modal" id="hs-so-class-modal" hidden><div class="hs-modal-box hs-so-wide"><h3 id="hs-so-cm-title">کلاس</h3><div id="hs-so-cm-body"></div><div class="hs-form-actions"><button type="button" class="hs-btn hs-btn-ghost hs-so-close">بستن</button></div></div></div>';
		return $h;
	}

	/* ---------------- چارت ---------------- */

	private static function chart( $sid, $school ) {
		$staff = HS_SO_Store::staff_list( $sid );
		$mgr   = get_userdata( $school['manager_id'] );
		$by    = array();
		foreach ( $staff as $r ) {
			$by[ $r['position'] ][] = $r;
		}
		$node = function ( $title, $name, $user_id, $color, $extra = '' ) {
			return '<div class="hs-so-node-card hs-c-' . esc_attr( $color ) . '">' . ( $user_id ? get_avatar( $user_id, 44, '', '', array( 'class' => 'hs-so-av' ) ) : '' ) . '<strong>' . esc_html( $name ) . '</strong><span>' . esc_html( $title ) . '</span>' . $extra . '</div>';
		};
		$used = array();
		$kids = function ( $parent ) use ( $by, $node, &$kids, &$used ) {
			$h = '';
			foreach ( HS_SO_Structure::positions() as $k => $p ) {
				if ( $p['parent'] !== $parent || empty( $by[ $k ] ) ) {
					continue;
				}
				foreach ( $by[ $k ] as $r ) {
					$h .= '<li>' . $node( $r['label'], $r['name'], $r['user_id'], $p['color'] );
					$sub = '';
					if ( empty( $used[ $k ] ) ) {
						$used[ $k ] = true;
						$sub        = $kids( $k );
					}
					$h .= $sub ? '<ul>' . $sub . '</ul>' : '';
					$h .= '</li>';
				}
			}
			return $h;
		};
		// فرزندان هر سمت فقط یک‌بار (زیر اولین معاون همان سمت) نمایش داده می‌شوند
		$tree = '<ul class="hs-so-tree"><li>' . $node( 'مدیر مدرسه', $mgr ? $mgr->display_name : '—', $school['manager_id'], 'violet' ) . '<ul>' . $kids( 'manager' ) . '</ul></li></ul>';
		return '<section class="hs-card"><h3>🌳 چارت سازمانی</h3><div class="hs-so-tree-wrap">' . $tree . '</div></section>';
	}
}
