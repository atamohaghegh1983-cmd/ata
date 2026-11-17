<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * مجله (افزونه‌ی «مقالات»).
 * فهرست: عنوان، خلاصه‌ی کوتاه، برچسب مخاطب/پایه و وضعیت قفل.
 * متن کامل فقط وقتی برمی‌گردد که HSAR_Access::check() برای همین کاربر true بدهد.
 */
class HS_API_Routes_Articles {

	public static function register() {
		$ns    = HS_API_NS;
		$authd = array( 'HS_API_Routes', 'authed' );
		register_rest_route( $ns, '/articles', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'index' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/articles/filters', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'filters' ), 'permission_callback' => $authd ) );
		register_rest_route( $ns, '/articles/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'one' ), 'permission_callback' => $authd ) );
	}

	private static function ready() {
		return class_exists( 'HSAR_Access' );
	}

	private static function unavailable() {
		return HS_API_Util::err( 'مجله روی سایت فعال نیست.', 503, 'hs_unavailable' );
	}

	private static function lock_text( $why ) {
		switch ( $why ) {
			case 'login':    return 'برای خواندن این مطلب وارد شوید.';
			case 'audience': return 'این مطلب برای گروه دیگری از کاربران است.';
			case 'nograde':  return 'هنوز کلاسی نساخته‌اید؛ پایه‌ی خود را در سایت انتخاب کنید.';
			case 'grade':    return 'این مطلب برای پایه‌ی دیگری است.';
			case 'password': return 'این مطلب رمزدار است.';
		}
		return 'دسترسی به این مطلب ممکن نیست.';
	}

	/** @return true|string */
	private static function access( WP_Post $post, $uid ) {
		if ( post_password_required( $post ) || '' !== $post->post_password ) {
			return 'password';
		}
		return HSAR_Access::check( $post, $uid );
	}

	private static function short_text( WP_Post $post, $words = 30 ) {
		$txt = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		return wp_trim_words( $txt, $words, '…' );
	}

	private static function row( WP_Post $post, $uid ) {
		list( $aud, $gr ) = HSAR_Access::post_terms( $post );
		$why   = self::access( $post, $uid );
		$names = HSAR_Access::audiences();
		$thumb = get_the_post_thumbnail_url( $post, 'medium_large' );
		return array(
			'id'        => $post->ID,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'excerpt'   => self::short_text( $post, true === $why ? 40 : 25 ),
			'date'      => get_post_time( 'Y-m-d', false, $post ),
			'image'     => $thumb ? $thumb : '',
			'audiences' => array_values( array_map( function ( $a ) use ( $names ) { return array( 'id' => $a, 'title' => $names[ $a ] ?? $a ); }, (array) $aud ) ),
			'grades'    => array_values( (array) $gr ),
			'locked'    => true !== $why,
			'lock'      => true === $why ? null : array( 'reason' => $why, 'message' => self::lock_text( $why ) ),
			'link'      => get_permalink( $post ),
		);
	}

	public static function filters() {
		if ( ! self::ready() ) { return self::unavailable(); }
		$p = HSAR_Access::profile( get_current_user_id() );
		return array(
			'audiences' => HSAR_Access::audiences(),
			'grades'    => HSAR_Access::grades(),
			'my'        => array( 'role' => $p['role'], 'audiences' => array_values( (array) $p['aud'] ), 'grades' => $p['grades'] ),
		);
	}

	public static function index( WP_REST_Request $r ) {
		if ( ! self::ready() ) { return self::unavailable(); }
		$uid      = get_current_user_id();
		$page     = max( 1, min( 200, (int) HS_API_Util::p( $r, 'page', 1 ) ) );
		$per      = max( 1, min( 30, (int) HS_API_Util::p( $r, 'per_page', 10 ) ) );
		$args     = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => $per,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'tax_query'           => array(), // phpcs:ignore
		);
		$search = HS_API_Util::text( HS_API_Util::p( $r, 'search' ), 100 );
		if ( '' !== $search ) { $args['s'] = $search; }
		$aud = sanitize_key( (string) HS_API_Util::p( $r, 'audience' ) );
		if ( $aud && isset( HSAR_Access::audiences()[ $aud ] ) ) {
			$args['tax_query'][] = array( 'taxonomy' => HSAR_Access::TAX_AUD, 'field' => 'slug', 'terms' => $aud );
		}
		$grade = HS_API_Util::text( HS_API_Util::p( $r, 'grade' ), 40 );
		if ( '' !== $grade ) {
			$args['tax_query'][] = array( 'taxonomy' => HSAR_Access::TAX_GRADE, 'field' => 'name', 'terms' => $grade );
		}
		// «مطالب پایه‌ی من»: فقط پایه‌های کاربر
		if ( rest_sanitize_boolean( HS_API_Util::p( $r, 'mine', false ) ) ) {
			$p = HSAR_Access::profile( $uid );
			if ( is_array( $p['grades'] ) && $p['grades'] ) {
				$args['tax_query'][] = array( 'taxonomy' => HSAR_Access::TAX_GRADE, 'field' => 'name', 'terms' => $p['grades'] );
			}
		}
		if ( count( $args['tax_query'] ) > 1 ) { $args['tax_query']['relation'] = 'AND'; }
		$q     = new WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			$items[] = self::row( $post, $uid );
		}
		return array( 'page' => $page, 'per_page' => $per, 'total' => (int) $q->found_posts, 'pages' => (int) $q->max_num_pages, 'items' => $items );
	}

	public static function one( WP_REST_Request $r ) {
		if ( ! self::ready() ) { return self::unavailable(); }
		$uid     = get_current_user_id();
		$article = get_post( (int) $r['id'] );
		if ( ! $article || 'post' !== $article->post_type || 'publish' !== $article->post_status ) {
			return HS_API_Util::err( 'مطلب پیدا نشد.', 404, 'hs_not_found' );
		}
		$out = self::row( $article, $uid );
		if ( $out['locked'] ) {
			$out['content'] = '';
			$out['teaser']  = self::short_text( $article, 55 );
			return $out;
		}
		// متن کامل با همان فیلترهای سایت (دروازه‌ی مقالات دوباره همین کاربر را بررسی می‌کند)
		$GLOBALS['post'] = $article; // phpcs:ignore
		setup_postdata( $article );
		$html = apply_filters( 'the_content', $article->post_content ); // phpcs:ignore
		wp_reset_postdata();
		$out['content'] = $html;
		$out['teaser']  = '';
		return $out;
	}
}
