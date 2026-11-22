<?php
/**
 * صفحه‌های ادغام‌شده (هاب): چند بخش مشابه با یک عنوان و یک ردیف در منو، با تب‌های داخلی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Hubs {

	/**
	 * گروه‌ها: کلید => عنوان، آیکون، رنگ، تب‌ها (slug => برچسب)
	 * اولین تبِ موجود، «صفحهٔ اصلی» گروه در منو است.
	 */
	public static function groups() {
		return array(
			'weekly'   => array(
				'title' => 'برنامه هفتگی و بودجه‌بندی',
				'icon'  => '🗓️',
				'color' => 'teal',
				'tabs'  => array(
					'weekly' => '🗓️ برنامه هفتگی',
					'budget' => '📐 بودجه‌بندی کلاس',
				),
			),
			'homework' => array(
				'title' => 'تکالیف و دفتر تکالیف',
				'icon'  => '📚',
				'color' => 'blue',
				'tabs'  => array(
					'homework' => '📚 تکالیف',
					'notebook' => '📓 دفتر تکالیف',
				),
			),
			'worksheets' => array(
				'title' => 'کتابخانه و داشبورد کاربرگ',
				'icon'  => '🗂️',
				'color' => 'green',
				'tabs'  => array(
					'worksheets'          => '🗂️ کتابخانه کاربرگ',
					'worksheet-dashboard' => '📊 داشبورد و مدیریت کاربرگ',
				),
			),
			'studio'   => array(
				'title' => 'استودیو محتوا و تکلیف‌ساز',
				'icon'  => '🎬',
				'color' => 'pink',
				'tabs'  => array(
					'content-studio' => '🎬 استودیو محتوا',
					'online-builder' => '🧪 تکلیف‌ساز آنلاین',
				),
			),
		);
	}

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'inject_tabs' ), 8 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_teacher_chat' ) );
		// عنوان افزونهٔ «برنامه هفتگی» هم‌نام با تب بودجه‌بندی شود
		add_filter( 'hs_wp_page_title', function ( $t ) {
			return '📅 ' . HS_Hubs::title( 'weekly', ltrim( preg_replace( '/^📅\s*/u', '', $t ) ) );
		} );
	}

	/** «گفتگوی والدین» برای معلم همان «ارتباط با والدین» (پیام عمومی) است */
	public static function redirect_teacher_chat() {
		if ( ! is_page( 'parent-chat' ) || ! is_user_logged_in() || ! HS_Roles::is_teacher_or_admin() ) {
			return;
		}
		$args = array( 'tab' => 'messages', 'mt' => 'public' );
		if ( isset( $_GET['chat_class'] ) && absint( $_GET['chat_class'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['class_id'] = absint( $_GET['chat_class'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		wp_safe_redirect( add_query_arg( $args, HS_Shell::url( 'messages' ) ) );
		exit;
	}

	/** تب‌های قابل‌استفاده یک گروه (فقط بخش‌هایی که فعال‌اند) */
	public static function tabs( $key ) {
		$groups = self::groups();
		if ( empty( $groups[ $key ] ) ) {
			return array();
		}
		$tabs = array();
		foreach ( $groups[ $key ]['tabs'] as $slug => $label ) {
			if ( 'weekly' === $slug && ! shortcode_exists( 'hamshagerdi_weekly_plan' ) ) {
				continue;
			}
			$tabs[ $slug ] = $label;
		}
		return $tabs;
	}

	/** گروهِ یک slug (یا '') */
	public static function group_of( $slug ) {
		foreach ( self::groups() as $key => $g ) {
			if ( isset( $g['tabs'][ $slug ] ) ) {
				return $key;
			}
		}
		return '';
	}

	/** slug اصلی (آیتم منو) برای هر slug؛ اگر عضو هاب نباشد همان slug */
	public static function primary( $slug ) {
		$key = self::group_of( $slug );
		if ( ! $key ) {
			return $slug;
		}
		$tabs = self::tabs( $key );
		if ( count( $tabs ) < 2 ) {
			return $slug;
		}
		reset( $tabs );
		return key( $tabs );
	}

	/** عنوان صفحه: اگر در هاب فعال است عنوان مشترک، وگرنه پیش‌فرض */
	public static function title( $slug, $default ) {
		$key = self::group_of( $slug );
		if ( $key && count( self::tabs( $key ) ) > 1 && HS_Roles::is_teacher_or_admin() ) {
			$g = self::groups();
			return $g[ $key ]['title'];
		}
		return $default;
	}

	/** منوی معلم: ردیف‌های ادغام‌شده */
	public static function apply_to_menu( $menu ) {
		$groups = self::groups();
		foreach ( $menu as $i => $section ) {
			$new = array();
			$done = array();
			foreach ( $section[1] as $it ) {
				$key = self::group_of( $it[0] );
				if ( $key && count( self::tabs( $key ) ) > 1 ) {
					if ( isset( $done[ $key ] ) ) {
						continue;
					}
					$done[ $key ] = true;
					$tabs         = self::tabs( $key );
					reset( $tabs );
					$it = array( key( $tabs ), $groups[ $key ]['title'], $groups[ $key ]['icon'], $groups[ $key ]['color'] );
				}
				$new[] = $it;
			}
			$menu[ $i ][1] = $new;
		}
		return $menu;
	}

	/** نوار تب بالای صفحهٔ هر بخش */
	public static function inject_tabs( $content ) {
		static $busy = false;
		if ( $busy || is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_user_logged_in() ) {
			return $content;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! HS_Shell::is_panel_request() || ! HS_Roles::is_teacher_or_admin() ) {
			return $content;
		}
		$key = self::group_of( $post->post_name );
		if ( ! $key ) {
			return $content;
		}
		$tabs = self::tabs( $key );
		if ( count( $tabs ) < 2 ) {
			return $content;
		}
		$g   = self::groups();
		$out = '<nav class="hs-hub-tabs" aria-label="' . esc_attr( $g[ $key ]['title'] ) . '"><span class="hs-hub-links">';
		foreach ( $tabs as $slug => $label ) {
			$url = HS_Shell::url( $slug );
			if ( isset( $_GET['class_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$url = add_query_arg( 'class_id', absint( $_GET['class_id'] ), $url ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			$out .= '<a class="hs-hub-tab' . ( $slug === $post->post_name ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		$out .= '</span></nav>';
		return $out . $content;
	}
}

HS_Hubs::init();
