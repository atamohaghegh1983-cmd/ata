<?php
/**
 * حریم خصوصی کاربران پنل (معلم، دانش‌آموز، والد، مدیر مدرسه)
 *
 * نام کاربری کسانی که با موبایل ثبت‌نام کرده‌اند همان شماره‌ی موبایل است و وردپرس
 * آن را در «نامک» (slug) نویسنده نشان می‌دهد. این فایل همه‌ی راه‌های عمومی دیدن آن را می‌بندد:
 *  - /wp-json/wp/v2/users (فهرست و تک‌کاربر) برای کسی که اجازه‌ی list_users ندارد
 *  - /wp-json/wp/v2/hs_class (فهرست همه‌ی کلاس‌ها و نویسنده‌شان)
 *  - برگه‌های نویسنده (/author/…) و تغییر مسیر ?author=N
 *  - نقشه‌ی سایت کاربران و کلاس‌ها، و نشانی نویسنده در oEmbed
 *  - تصویر Gravatar: نشانی آن هش ایمیل است و ایمیل این کاربران از روی موبایل ساخته شده
 *    (09…@hamshagerdi.local)؛ هش به‌راحتی به شماره برمی‌گردد. به‌جایش تصویر پیش‌فرض محلی.
 *
 * @since 1.7.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Privacy {

	public static function init() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_rest' ), 10, 3 );
		add_filter( 'register_post_type_args', array( __CLASS__, 'class_cpt_args' ), 20, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'guard_author_pages' ), 1 );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'sitemap_provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'sitemap_post_types' ) );
		add_filter( 'oembed_response_data', array( __CLASS__, 'oembed' ), 10, 2 );
		add_filter( 'author_link', array( __CLASS__, 'author_link' ), 10, 2 );
		add_filter( 'pre_get_avatar_data', array( __CLASS__, 'avatar' ), 20, 2 ); // پس از عکس اختصاصی (اولویت ۱۰)
	}

	public static function default_avatar_url() {
		return HS_CLASS_URL . 'assets/img/avatar-default.svg';
	}

	/** کاربر پنل بدون عکس اختصاصی ← تصویر پیش‌فرض محلی (بدون هش ایمیل) */
	public static function avatar( $args, $id_or_email ) {
		if ( ! empty( $args['url'] ) ) {
			return $args; // عکس اختصاصی دارد
		}
		$uid = 0;
		if ( is_numeric( $id_or_email ) ) {
			$uid = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$uid = (int) $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$uid = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$uid = (int) $id_or_email->user_id;
			if ( ! $uid && preg_match( '/@hamshagerdi\.local$/i', (string) $id_or_email->comment_author_email ) ) {
				$args['url'] = self::default_avatar_url();
				return $args;
			}
		} elseif ( is_string( $id_or_email ) ) {
			if ( preg_match( '/@hamshagerdi\.local$/i', $id_or_email ) ) {
				$args['url'] = self::default_avatar_url();
				return $args;
			}
			$u   = is_email( $id_or_email ) ? get_user_by( 'email', $id_or_email ) : false;
			$uid = $u ? (int) $u->ID : 0;
		}
		if ( $uid && self::is_private_user( $uid ) ) {
			$args['url']          = self::default_avatar_url();
			$args['found_avatar'] = false;
		}
		return $args;
	}

	/** کاربری که یکی از نقش‌های پنل را دارد (حتی اگر نقش دیگری هم داشته باشد) و مدیر سایت نیست */
	public static function is_private_user( $user_id ) {
		$u = get_userdata( (int) $user_id );
		if ( ! $u ) {
			return false;
		}
		if ( user_can( $u, 'manage_options' ) || user_can( $u, 'edit_others_posts' ) ) {
			return false; // مدیران و ویراستاران سایت، نویسندگان عمومی‌اند
		}
		return (bool) array_intersect( (array) $u->roles, array( 'teacher', 'school_admin', 'student', 'parent', 'hs_pending', 'subscriber' ) );
	}

	/** /wp/v2/users فقط برای کسی که list_users دارد؛ /users/me برای خودِ کاربر وارد شده آزاد است */
	public static function guard_rest( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}
		$route = untrailingslashit( (string) $request->get_route() );
		if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
			if ( '/wp/v2/users/me' === $route && is_user_logged_in() ) {
				return $result;
			}
			if ( ! current_user_can( 'list_users' ) ) {
				return new WP_Error( 'rest_forbidden', 'اجازه‌ی دیدن فهرست کاربران را ندارید.', array( 'status' => is_user_logged_in() ? 403 : 401 ) );
			}
		}
		return $result;
	}

	/** کلاس‌ها در REST عمومی وردپرس نباشند (اپ از /hamshagerdi/v1 با بررسی دسترسی استفاده می‌کند) */
	public static function class_cpt_args( $args, $post_type ) {
		if ( 'hs_class' === $post_type ) {
			$args['show_in_rest'] = false;
		}
		return $args;
	}

	/** برگه‌ی نویسنده برای کاربران پنل ← ۴۰۴ (پیش از تغییر مسیر ?author=N که نامک را لو می‌دهد) */
	public static function guard_author_pages() {
		if ( ! is_author() ) {
			return;
		}
		$author = get_queried_object();
		$uid    = $author instanceof WP_User ? $author->ID : (int) get_query_var( 'author' );
		if ( ! $uid || self::is_private_user( $uid ) ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	public static function sitemap_provider( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	public static function sitemap_post_types( $types ) {
		unset( $types['hs_class'] );
		return $types;
	}

	public static function oembed( $data, $post ) {
		if ( $post && self::is_private_user( $post->post_author ) ) {
			unset( $data['author_url'] );
		}
		return $data;
	}

	/** پیوند نویسنده‌ی کاربران پنل به صفحه‌ی اصلی می‌رود، نه /author/0912… */
	public static function author_link( $link, $author_id ) {
		return self::is_private_user( $author_id ) ? home_url( '/' ) : $link;
	}
}

HS_Privacy::init();
