<?php
/**
 * دسترسی به مطالب بر اساس «مخاطب» و «پایه».
 *
 * مخاطب (hs_audience): عمومی | والدین | معلمان   — مطلب بدون مخاطب = عمومی
 * پایه  (hs_grade):    پیش‌دبستانی … دوازدهم    — مطلب بدون پایه = برای همه‌ی پایه‌ها
 *
 * قوانین:
 * - بازدیدکننده‌ی واردنشده فقط مطالب «عمومی» بدون پایه را کامل می‌بیند؛ بقیه = چند خط اول + دعوت به ورود/ثبت‌نام.
 * - والد: عمومی + والدین. اگر مطلب پایه دارد، باید با پایه‌ی یکی از فرزندانش بخواند.
 * - معلم: عمومی + والدین + معلمان؛ مطلب پایه‌دار فقط برای پایه‌ی کلاس‌های خودش.
 *   معلمی که هنوز کلاس ندارد یک پایه انتخاب می‌کند (_hsar_pref_grade) تا وقتی کلاس بسازد.
 * - مدیر مدرسه: مثل معلم، با همه‌ی پایه‌های کلاس‌های مدرسه‌اش.
 * - دانش‌آموز: عمومی (پایه‌دار فقط پایه‌ی خودش).
 * - مدیر سایت / ویرایشگر / نویسنده‌ی مطلب: همه.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSAR_Access {

	const TAX_GRADE = 'hs_grade';
	const TAX_AUD   = 'hs_audience';
	const PREF      = '_hsar_pref_grade';

	private static $cache = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_filter( 'the_content', array( __CLASS__, 'gate_content' ), 9999 );
		add_filter( 'the_content_feed', array( __CLASS__, 'gate_feed' ), 9999 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'gate_excerpt' ), 9999, 2 );
		add_filter( 'rest_prepare_post', array( __CLASS__, 'gate_rest' ), 10, 3 );
		add_filter( 'post_class', array( __CLASS__, 'post_class' ), 10, 3 );
		add_shortcode( 'hs_my_content', array( __CLASS__, 'shortcode' ) );
		add_filter( 'hs_shell_menu', array( __CLASS__, 'menu' ), 30, 2 );
		add_action( 'admin_post_hsar_pref_grade', array( __CLASS__, 'save_pref' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_page' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'style' ) );
	}

	/* ------------------------------ ثبت ------------------------------ */

	public static function grades() {
		if ( class_exists( 'HS_Class_Meta' ) && method_exists( 'HS_Class_Meta', 'grades' ) ) {
			return array_values( (array) HS_Class_Meta::grades() );
		}
		return array( 'پیش‌دبستانی', 'اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم' );
	}

	public static function audiences() {
		return array( 'general' => 'عمومی', 'parents' => 'والدین', 'teachers' => 'معلمان' );
	}

	public static function register() {
		$common = array( 'public' => true, 'show_ui' => true, 'show_in_rest' => true, 'show_admin_column' => true, 'show_in_quick_edit' => true, 'hierarchical' => true, 'rewrite' => false );
		register_taxonomy( self::TAX_GRADE, array( 'post' ), $common + array(
			'labels' => array( 'name' => 'پایه', 'singular_name' => 'پایه', 'menu_name' => 'پایه‌ها', 'all_items' => 'همه‌ی پایه‌ها', 'add_new_item' => 'افزودن پایه' ),
			'query_var' => 'paye',
		) );
		register_taxonomy( self::TAX_AUD, array( 'post' ), $common + array(
			'labels' => array( 'name' => 'مخاطب', 'singular_name' => 'مخاطب', 'menu_name' => 'مخاطب', 'all_items' => 'همه', 'add_new_item' => 'افزودن مخاطب' ),
			'query_var' => 'mokhatab',
		) );
		if ( get_option( 'hsar_terms_v' ) !== '1' ) {
			foreach ( self::grades() as $i => $g ) {
				if ( ! term_exists( $g, self::TAX_GRADE ) ) {
					wp_insert_term( $g, self::TAX_GRADE, array( 'slug' => 'grade-' . $i ) );
				}
			}
			foreach ( self::audiences() as $slug => $name ) {
				if ( ! term_exists( $slug, self::TAX_AUD ) ) {
					wp_insert_term( $name, self::TAX_AUD, array( 'slug' => $slug ) );
				}
			}
			update_option( 'hsar_terms_v', '1' );
		}
	}

	/* ------------------------------ کاربر ------------------------------ */

	private static function class_grades( $class_ids ) {
		$g = array();
		foreach ( (array) $class_ids as $cid ) {
			$v = trim( (string) get_post_meta( (int) $cid, '_hs_grade', true ) );
			if ( '' !== $v ) {
				$g[] = $v;
			}
		}
		return $g;
	}

	private static function classes_where( $key, $value ) {
		if ( ! post_type_exists( 'hs_class' ) ) {
			return array();
		}
		return get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 300, 'meta_key' => $key, 'meta_value' => $value ) ); // phpcs:ignore
	}

	/** کلاس‌هایی که کاربر معلمشان است (مالک، یا عضو کادر از طریق مدرسه نه) */
	public static function teacher_class_ids( $uid ) {
		return self::classes_where( '_hs_teacher_id', $uid );
	}

	/**
	 * پروفایل دسترسی کاربر
	 * @return array{role:string,aud:string[],grades:string[]|string}  grades='all' یعنی همه‌ی پایه‌ها
	 */
	public static function profile( $uid = null ) {
		$uid = null === $uid ? get_current_user_id() : (int) $uid;
		if ( isset( self::$cache[ $uid ] ) ) {
			return self::$cache[ $uid ];
		}
		$p = array( 'role' => 'guest', 'aud' => array( 'general' ), 'grades' => array() );
		$u = $uid ? get_userdata( $uid ) : null;
		if ( $u ) {
			$roles = (array) $u->roles;
			if ( user_can( $u, 'edit_others_posts' ) ) {
				$p = array( 'role' => 'editor', 'aud' => array_keys( self::audiences() ), 'grades' => 'all' );
			} elseif ( array_intersect( $roles, array( 'school_admin' ) ) ) {
				$sid    = class_exists( 'HS_SO_Store' ) ? (int) HS_SO_Store::manager_school_id( $uid ) : 0;
				$ids    = array_merge( $sid ? self::classes_where( '_hs_school_id', $sid ) : array(), self::teacher_class_ids( $uid ) );
				$grades = self::class_grades( $ids );
				$p      = array( 'role' => 'manager', 'aud' => array_keys( self::audiences() ), 'grades' => $grades ? $grades : 'all' );
			} elseif ( array_intersect( $roles, array( 'teacher' ) ) ) {
				$grades = self::class_grades( self::teacher_class_ids( $uid ) );
				if ( ! $grades ) {
					$pref   = (string) get_user_meta( $uid, self::PREF, true );
					$grades = $pref ? array( $pref ) : array();
				}
				$p = array( 'role' => 'teacher', 'aud' => array_keys( self::audiences() ), 'grades' => $grades );
			} elseif ( array_intersect( $roles, array( 'parent' ) ) ) {
				$ids = array();
				$kids = class_exists( 'HS_Parent_Link' ) ? HS_Parent_Link::get_children( $uid ) : array();
				foreach ( $kids as $k ) {
					$ids = array_merge( $ids, (array) get_user_meta( $k, '_hs_classes', true ) );
				}
				$p = array( 'role' => 'parent', 'aud' => array( 'general', 'parents' ), 'grades' => self::class_grades( $ids ) );
			} elseif ( array_intersect( $roles, array( 'student' ) ) ) {
				$p = array( 'role' => 'student', 'aud' => array( 'general' ), 'grades' => self::class_grades( (array) get_user_meta( $uid, '_hs_classes', true ) ) );
			} else {
				$p = array( 'role' => 'member', 'aud' => array( 'general' ), 'grades' => array() );
			}
			if ( is_array( $p['grades'] ) ) {
				$p['grades'] = array_values( array_unique( $p['grades'] ) );
			}
		}
		return self::$cache[ $uid ] = apply_filters( 'hsar_access_profile', $p, $uid );
	}

	/* ------------------------------ مطلب ------------------------------ */

	public static function post_terms( $post ) {
		$aud = wp_get_post_terms( $post->ID, self::TAX_AUD, array( 'fields' => 'slugs' ) );
		$gr  = wp_get_post_terms( $post->ID, self::TAX_GRADE, array( 'fields' => 'names' ) );
		$aud = is_wp_error( $aud ) || ! $aud ? array( 'general' ) : $aud;
		$gr  = is_wp_error( $gr ) ? array() : $gr;
		return array( $aud, $gr );
	}

	/** @return true|string  true = اجازه؛ رشته = دلیل قفل */
	public static function check( $post, $uid = null ) {
		$post = get_post( $post );
		if ( ! $post || 'post' !== $post->post_type ) {
			return true;
		}
		$uid = null === $uid ? get_current_user_id() : (int) $uid;
		if ( $uid && ( (int) $post->post_author === $uid || user_can( $uid, 'edit_post', $post->ID ) ) ) {
			return true;
		}
		list( $aud, $gr ) = self::post_terms( $post );
		$p = self::profile( $uid );
		if ( 'guest' === $p['role'] ) {
			return ( in_array( 'general', $aud, true ) && ! $gr ) ? true : 'login';
		}
		if ( ! array_intersect( $aud, $p['aud'] ) ) {
			return 'audience';
		}
		if ( $gr && 'all' !== $p['grades'] && ! array_intersect( $gr, (array) $p['grades'] ) ) {
			return $p['grades'] ? 'grade' : ( 'teacher' === $p['role'] ? 'nograde' : 'grade' );
		}
		return true;
	}

	public static function can( $post, $uid = null ) {
		return true === self::check( $post, $uid );
	}

	private static function lock_box( $post, $why ) {
		list( $aud, $gr ) = self::post_terms( $post );
		$names = self::audiences();
		$who   = implode( ' و ', array_map( function ( $a ) use ( $names ) { return $names[ $a ] ?? $a; }, $aud ) );
		$gtxt  = $gr ? ' پایه‌ی ' . implode( '، ', $gr ) : '';
		$login = add_query_arg( 'redirect_to', rawurlencode( get_permalink( $post ) ), wp_login_url( get_permalink( $post ) ) );
		switch ( $why ) {
			case 'login':
				$t = 'این مطلب تخصصی است' . ( $gtxt ? ' (' . trim( $gtxt ) . ')' : '' ) . '. برای خواندن کامل آن وارد شوید یا رایگان ثبت‌نام کنید.';
				$b = '<a class="hsar-lock-btn" href="' . esc_url( $login ) . '">ورود / ثبت‌نام رایگان</a>';
				break;
			case 'nograde':
				$t = 'این مطلب برای معلمان' . $gtxt . ' است. هنوز کلاسی نساخته‌اید؛ پایه‌ی خود را انتخاب کنید یا کلاس بسازید.';
				$b = '<a class="hsar-lock-btn" href="' . esc_url( home_url( '/panel/my-content/' ) ) . '">انتخاب پایه</a>';
				break;
			case 'grade':
				$t = 'این مطلب مخصوص' . ( $who ? ' ' . $who : '' ) . $gtxt . ' است و با پایه‌ی کلاس‌های شما هم‌خوان نیست.';
				$b = '<a class="hsar-lock-btn ghost" href="' . esc_url( home_url( '/panel/my-content/' ) ) . '">مطالب پایه‌ی من</a>';
				break;
			default:
				$t = 'این مطلب مخصوص ' . $who . ' است.';
				$b = '';
		}
		return '<div class="hsar-lock" role="note"><span class="hsar-lock-ico" aria-hidden="true">🔒</span><p>' . esc_html( $t ) . '</p>' . $b . '</div>';
	}

	private static function teaser( $post ) {
		$txt = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		return '<p class="hsar-teaser">' . esc_html( wp_trim_words( $txt, 55, '…' ) ) . '</p>';
	}

	public static function gate_content( $content ) {
		$post = get_post();
		if ( ! $post || 'post' !== $post->post_type || is_admin() ) {
			return $content;
		}
		$why = self::check( $post );
		if ( true === $why ) {
			return $content;
		}
		self::style();
		return self::teaser( $post ) . self::lock_box( $post, $why );
	}

	public static function gate_feed( $content ) {
		$post = get_post();
		return ( $post && ! self::can( $post, 0 ) ) ? self::teaser( $post ) : $content;
	}

	/** خلاصه‌ی خودکار نباید متن قفل‌شده را لو بدهد؛ فقط ۲۵ کلمه */
	public static function gate_excerpt( $excerpt, $post = null ) {
		$post = get_post( $post );
		if ( ! $post || 'post' !== $post->post_type || self::can( $post ) || has_excerpt( $post ) ) {
			return $excerpt;
		}
		return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 25, '…' );
	}

	public static function gate_rest( $res, $post, $req ) {
		if ( ! self::can( $post ) && isset( $res->data['content']['rendered'] ) ) {
			$res->data['content']['rendered']  = self::teaser( $post );
			$res->data['content']['protected'] = true;
		}
		return $res;
	}

	public static function post_class( $classes, $class, $post_id ) {
		if ( ! is_admin() && 'post' === get_post_type( $post_id ) && ! self::can( $post_id ) ) {
			$classes[] = 'hsar-locked';
		}
		return $classes;
	}

	/** برچسب‌های کوچک پایه/مخاطب برای کارت‌ها (قالب از آن استفاده می‌کند) */
	public static function badges( $post ) {
		list( $aud, $gr ) = self::post_terms( get_post( $post ) );
		$h = '';
		if ( ! self::can( $post ) ) {
			$h .= '<span class="hsar-b lock">🔒</span>';
		}
		foreach ( $gr as $g ) {
			$h .= '<span class="hsar-b g">' . esc_html( $g ) . '</span>';
		}
		if ( ! in_array( 'general', $aud, true ) ) {
			$n = self::audiences();
			foreach ( $aud as $a ) {
				$h .= '<span class="hsar-b a">' . esc_html( $n[ $a ] ?? $a ) . '</span>';
			}
		}
		return $h ? '<span class="hsar-badges">' . $h . '</span>' : '';
	}

	/* ------------------------------ «مطالب پایه‌ی من» ------------------------------ */

	public static function menu( $menu, $type ) {
		if ( ! is_array( $menu ) ) {
			return $menu;
		}
		$item = array(
			'teacher' => array( 'my-content', 'مطالب پایه‌ی من', '📚', 'blue' ),
			'manager' => array( 'my-content', 'مطالب آموزشی', '📚', 'blue' ),
			'parent'  => array( 'my-content', 'مجله‌ی والدین', '📖', 'blue' ),
		)[ $type ] ?? null;
		if ( ! $item ) {
			return $menu;
		}
		$menu[] = array( 'مطالب', array( $item ) );
		return $menu;
	}

	public static function ensure_page() {
		$id = (int) get_option( 'hsar_mycontent_page' );
		if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		$panel = get_page_by_path( 'panel' );
		$id    = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'مطالب پایه‌ی من', 'post_name' => 'my-content', 'post_parent' => $panel ? $panel->ID : 0, 'post_content' => '[hs_my_content]' ) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'hsar_mycontent_page', (int) $id );
		}
		return (int) $id;
	}

	public static function save_pref() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'hsar_pref' );
		$g = sanitize_text_field( wp_unslash( $_POST['grade'] ?? '' ) );
		if ( in_array( $g, self::grades(), true ) ) {
			update_user_meta( get_current_user_id(), self::PREF, $g );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/panel/my-content/' ) );
		exit;
	}

	public static function shortcode( $atts = array() ) {
		self::style();
		if ( ! is_user_logged_in() ) {
			return '<div class="hsar-lock"><p>برای دیدن مطالب مخصوص خودتان وارد شوید.</p><a class="hsar-lock-btn" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">ورود / ثبت‌نام</a></div>';
		}
		$uid = get_current_user_id();
		$p   = self::profile( $uid );
		$h   = '<div class="hsar-mine" dir="rtl">';
		// معلم بدون کلاس: انتخاب پایه
		if ( 'teacher' === $p['role'] && ! self::class_grades( self::teacher_class_ids( $uid ) ) ) {
			$cur = (string) get_user_meta( $uid, self::PREF, true );
			$opt = '<option value="">انتخاب پایه…</option>';
			foreach ( self::grades() as $g ) {
				$opt .= '<option' . selected( $cur, $g, false ) . '>' . esc_html( $g ) . '</option>';
			}
			$h .= '<form class="hsar-pref" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'hsar_pref', '_wpnonce', true, false )
				. '<input type="hidden" name="action" value="hsar_pref_grade"><label>هنوز کلاسی نساخته‌اید؛ پایه‌ای که تدریس می‌کنید:</label><select name="grade" onchange="this.form.submit()">' . $opt . '</select>'
				. '<small>وقتی کلاس بسازید، پایه از روی کلاس‌هایتان تعیین می‌شود.</small></form>';
		} elseif ( is_array( $p['grades'] ) && $p['grades'] ) {
			$h .= '<p class="hsar-mine-head">مطالب مخصوص پایه‌ی <b>' . esc_html( implode( '، ', $p['grades'] ) ) . '</b> و مطالب عمومی</p>';
		}
		$cat  = absint( $_GET['cat'] ?? 0 ); // phpcs:ignore
		$q    = array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 60, 'ignore_sticky_posts' => true );
		$tax  = array( 'relation' => 'AND' );
		$tax[] = array( 'relation' => 'OR',
			array( 'taxonomy' => self::TAX_AUD, 'field' => 'slug', 'terms' => $p['aud'] ),
			array( 'taxonomy' => self::TAX_AUD, 'operator' => 'NOT EXISTS' ) );
		if ( 'all' !== $p['grades'] ) {
			$g = array( 'relation' => 'OR', array( 'taxonomy' => self::TAX_GRADE, 'operator' => 'NOT EXISTS' ) );
			if ( $p['grades'] ) {
				$g[] = array( 'taxonomy' => self::TAX_GRADE, 'field' => 'name', 'terms' => $p['grades'] );
			}
			$tax[] = $g;
		}
		$q['tax_query'] = $tax; // phpcs:ignore
		if ( $cat ) {
			$q['cat'] = $cat;
		}
		$posts = get_posts( $q );
		// فیلتر دسته‌ها
		$cats = array();
		foreach ( $posts as $po ) {
			foreach ( get_the_category( $po->ID ) as $c ) {
				$cats[ $c->term_id ] = $c->name;
			}
		}
		if ( $cats || $cat ) {
			$h .= '<nav class="hsar-pills"><a href="' . esc_url( remove_query_arg( 'cat' ) ) . '" class="' . ( $cat ? '' : 'on' ) . '">همه</a>';
			foreach ( $cats as $id => $n ) {
				$h .= '<a href="' . esc_url( add_query_arg( 'cat', $id ) ) . '" class="' . ( $cat === $id ? 'on' : '' ) . '">' . esc_html( $n ) . '</a>';
			}
			$h .= '</nav>';
		}
		if ( ! $posts ) {
			return $h . '<p class="hsar-empty">هنوز مطلبی برای شما منتشر نشده است.</p></div>';
		}
		$h .= '<div class="hsar-grid">';
		foreach ( $posts as $po ) {
			$img = get_the_post_thumbnail( $po, 'medium', array( 'loading' => 'lazy' ) );
			$c   = get_the_category( $po->ID );
			$h  .= '<a class="hsar-item" href="' . esc_url( get_permalink( $po ) ) . '"><span class="hsar-img">' . ( $img ? $img : '📄' ) . '</span><span class="hsar-body">'
				. ( $c ? '<small>' . esc_html( $c[0]->name ) . '</small>' : '' ) . '<b>' . esc_html( get_the_title( $po ) ) . '</b>' . self::badges( $po ) . '</span></a>';
		}
		return $h . '</div></div>';
	}

	private static $styled = false;
	public static function style() {
		if ( self::$styled ) {
			return;
		}
		self::$styled = true;
		wp_enqueue_style( 'hsar-access', HSAR_URL . 'assets/access.css', array(), HSAR_VERSION );
	}
}
