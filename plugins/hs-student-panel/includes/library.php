<?php
/**
 * کتابخانه‌ی کلاس: معلم داستان/متن یا پیوند می‌گذارد؛ دانش‌آموز می‌خواند، «خواندم» می‌زند و ستاره می‌دهد.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_Library {

	public static function init() {
		add_action( 'admin_post_hssp_lib', array( __CLASS__, 'handle' ) );
	}

	public static function kinds() {
		return array( 'story' => array( '📖', 'داستان یا متن' ), 'link' => array( '🔗', 'پیوند (فیلم، صوت، سایت)' ), 'poem' => array( '🎵', 'شعر و سرود' ) );
	}

	public static function items( $cid, $only_active = true ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSSP_DB::t( 'library' ) . ' WHERE class_id = %d' . ( $only_active ? ' AND active = 1' : '' ) . ' ORDER BY id DESC LIMIT 200', $cid ), ARRAY_A ); // phpcs:ignore
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HSSP_DB::t( 'library' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function reads_of( $sid ) {
		global $wpdb;
		$out = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSSP_DB::t( 'reads' ) . ' WHERE student_id = %d', $sid ), ARRAY_A ) as $r ) { // phpcs:ignore
			$out[ (int) $r['item_id'] ] = $r;
		}
		return $out;
	}

	public static function readers( $item ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSSP_DB::t( 'reads' ) . ' WHERE item_id = %d ORDER BY read_at DESC', $item ), ARRAY_A ); // phpcs:ignore
	}

	private static function form( $op, $hidden, $inner, $attrs = '' ) {
		$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $attrs . '>' . wp_nonce_field( 'hssp_lib', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="hssp_lib"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
		foreach ( $hidden as $k => $v ) {
			$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		return $h . $inner . '</form>';
	}

	/* ---------- دانش‌آموز ---------- */

	public static function student_view( $sid, $cid, $item_id, $base ) {
		if ( $item_id ) {
			$it = self::get( $item_id );
			if ( ! $it || ! $it['active'] || ! in_array( (int) $it['class_id'], HSSP_Util::student_classes( $sid ), true ) ) {
				$item_id = 0;
			} else {
				$read = self::reads_of( $sid )[ $item_id ] ?? null;
				$h    = '<section class="hs-kid-block hssp-reader"><a class="hs-kid-back" href="' . esc_url( $base ) . '">← بازگشت به کتابخانه</a>'
					. '<h2>' . esc_html( $it['icon'] . ' ' . $it['title'] ) . '</h2>';
				if ( $it['body'] ) {
					$h .= '<div class="hssp-reader-text">' . wpautop( esc_html( $it['body'] ) ) . '</div>';
				}
				if ( $it['url'] ) {
					$h .= '<p><a class="hs-kid-btn" href="' . esc_url( $it['url'] ) . '" target="_blank" rel="noopener nofollow">🔗 باز کردن</a></p>';
				}
				if ( (int) get_current_user_id() === (int) $sid ) {
					$stars = '';
					for ( $i = 1; $i <= 3; $i++ ) {
						$stars .= '<button name="stars" value="' . $i . '" class="hssp-star' . ( $read && (int) $read['stars'] >= $i ? ' on' : '' ) . '" title="' . $i . ' ستاره">★</button>';
					}
					$h .= '<div class="hssp-read-box">' . ( $read ? '<p>✅ خواندی! چقدر دوستش داشتی؟</p>' : '<p>خواندی؟ ستاره بده و تمام!</p>' )
						. self::form( 'read', array( 'id' => $item_id ), '<div class="hssp-stars">' . $stars . '</div>' ) . '</div>';
				}
				return $h . '</section>';
			}
		}
		$items = array();
		foreach ( HSSP_Util::student_classes( $sid ) as $c ) {
			if ( HSSP_Perms::can( $sid, 'library', $c ) ) {
				$items = array_merge( $items, self::items( $c ) );
			}
		}
		$reads = self::reads_of( $sid );
		$h     = HSSP_Util::notice() . '<section class="hs-kid-block"><div class="hs-kid-block-head"><h2>📖 کتابخانه‌ی کلاس</h2>'
			. ( $items ? '<span class="hs-kid-count-pill">' . count( array_intersect_key( $reads, array_flip( wp_list_pluck( $items, 'id' ) ) ) ) . ' از ' . count( $items ) . ' خوانده‌ای</span>' : '' ) . '</div>';
		if ( ! $items ) {
			return $h . '<div class="hs-kid-celebrate"><div class="hs-kid-celebrate-emoji">📚</div><p>معلمت هنوز چیزی در کتابخانه نگذاشته.</p></div></section>';
		}
		$h .= '<div class="hs-kid-games">';
		$colors = array( 'g-purple', 'g-teal', 'g-coral', 'g-amber' );
		foreach ( $items as $i => $it ) {
			$r  = $reads[ (int) $it['id'] ] ?? null;
			$h .= '<a class="hs-kid-game-card ' . esc_attr( $colors[ $i % 4 ] ) . ( $r ? ' hssp-read' : '' ) . '" href="' . esc_url( add_query_arg( 'item', (int) $it['id'], $base ) ) . '">'
				. '<span class="hs-kid-game-icon">' . esc_html( $it['icon'] ) . '</span><strong>' . esc_html( $it['title'] ) . '</strong>'
				. '<span>' . esc_html( self::kinds()[ $it['kind'] ][1] ?? '' ) . '</span><span class="hs-kid-game-play">' . ( $r ? '✅ ' . str_repeat( '★', (int) $r['stars'] ) : 'بخوان ←' ) . '</span></a>';
		}
		return $h . '</div></section>';
	}

	/* ---------- معلم ---------- */

	public static function teacher_view( $cid ) {
		$k = '';
		foreach ( self::kinds() as $key => $v ) {
			$k .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $v[0] . ' ' . $v[1] ) . '</option>';
		}
		$h = '<section class="hs-card"><h3>➕ افزودن به کتابخانه‌ی کلاس</h3>'
			. self::form( 'add', array( 'class_id' => $cid ),
				'<div class="hssp-row"><label>نوع<select name="kind">' . $k . '</select></label><label>شکلک<input name="icon" maxlength="4" value="📖"></label></div>'
				. '<label>عنوان<input name="title" required maxlength="190"></label>'
				. '<label>متن (برای داستان و شعر)<textarea name="body" rows="6" maxlength="20000"></textarea></label>'
				. '<label>پیوند (اختیاری)<input type="url" name="url" dir="ltr" placeholder="https://"></label>'
				. '<button class="hs-btn hs-btn-primary">افزودن</button>', 'class="hssp-form"' ) . '</section>';
		$items = self::items( $cid, false );
		if ( $items ) {
			$total = count( (array) get_post_meta( $cid, '_hs_students', true ) );
			$h    .= '<section class="hs-card"><h3>📚 کتابخانه (' . count( $items ) . ')</h3><ul class="hssp-list">';
			foreach ( $items as $it ) {
				$rd  = self::readers( $it['id'] );
				$avg = $rd ? round( array_sum( wp_list_pluck( $rd, 'stars' ) ) / count( $rd ), 1 ) : 0;
				$h  .= '<li class="' . ( $it['active'] ? '' : 'is-off' ) . '"><div><strong>' . esc_html( $it['icon'] . ' ' . $it['title'] ) . '</strong>'
					. '<details><summary>👀 ' . count( $rd ) . ' از ' . (int) $total . ' خوانده‌اند' . ( $avg ? ' · میانگین ' . $avg . ' ★' : '' ) . '</summary><ul>';
				foreach ( $rd as $r ) {
					$u  = get_userdata( $r['student_id'] );
					$h .= '<li>' . esc_html( $u ? $u->display_name : '—' ) . ' ' . str_repeat( '★', (int) $r['stars'] ) . '</li>';
				}
				$h .= '</ul></details></div><div class="hssp-acts">'
					. self::form( 'toggle', array( 'id' => $it['id'] ), '<button class="hssp-link">' . ( $it['active'] ? 'پنهان' : 'نمایش' ) . '</button>' )
					. self::form( 'del', array( 'id' => $it['id'] ), '<button class="hssp-link hssp-danger">حذف</button>', 'onsubmit="return confirm(\'حذف شود؟\')"' ) . '</div></li>';
			}
			$h .= '</ul></section>';
		}
		return $h;
	}

	public static function handle() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'hssp_lib' ) ) {
			wp_die( 'نامعتبر' );
		}
		global $wpdb;
		$uid = get_current_user_id();
		$op  = sanitize_key( $_POST['op'] ?? '' );
		$t   = HSSP_DB::t( 'library' );
		if ( 'add' === $op ) {
			$cid = (int) ( $_POST['class_id'] ?? 0 );
			if ( ! HSSP_Util::is_class_teacher( $uid, $cid ) ) {
				HSSP_Util::back( 'اجازه‌ی این کار را ندارید.' );
			}
			$title = mb_substr( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 0, 190 );
			$url   = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ), array( 'http', 'https' ) );
			$url   = preg_match( '#^https?://#i', $url ) ? $url : '';
			$body  = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ), 0, 20000 );
			if ( '' === $title || ( '' === $body && '' === $url ) ) {
				HSSP_Util::back( 'عنوان و متن یا پیوند لازم است.' );
			}
			$kind = sanitize_key( $_POST['kind'] ?? '' );
			$icon = mb_substr( sanitize_text_field( wp_unslash( $_POST['icon'] ?? '' ) ), 0, 4 );
			$wpdb->insert( $t, array( 'class_id' => $cid, 'author_id' => $uid, 'kind' => isset( self::kinds()[ $kind ] ) ? $kind : 'story', 'title' => $title, 'body' => $body, 'url' => $url, 'icon' => $icon ? $icon : '📖', 'active' => 1, 'created_at' => HSSP_DB::now() ) );
			HSSP_Util::back( 'به کتابخانه اضافه شد.' );
		}
		$id = (int) ( $_POST['id'] ?? 0 );
		$it = self::get( $id );
		if ( ! $it ) {
			HSSP_Util::back( 'پیدا نشد.' );
		}
		if ( 'read' === $op ) {
			if ( ! $it['active'] || ! HSSP_Util::in_class( $uid, $it['class_id'] ) || ! HSSP_Perms::can( $uid, 'library', $it['class_id'] ) ) {
				HSSP_Util::back( 'اجازه‌ی این کار را ندارید.' );
			}
			$wpdb->replace( HSSP_DB::t( 'reads' ), array( 'item_id' => $id, 'student_id' => $uid, 'stars' => max( 1, min( 3, (int) ( $_POST['stars'] ?? 1 ) ) ), 'read_at' => HSSP_DB::now() ) );
			HSSP_Util::back( 'آفرین! ثبت شد ⭐' );
		}
		if ( ! HSSP_Util::is_class_teacher( $uid, $it['class_id'] ) ) {
			HSSP_Util::back( 'اجازه‌ی این کار را ندارید.' );
		}
		if ( 'del' === $op ) {
			$wpdb->delete( $t, array( 'id' => $id ) );
			$wpdb->delete( HSSP_DB::t( 'reads' ), array( 'item_id' => $id ) );
			HSSP_Util::back( 'حذف شد.' );
		}
		if ( 'toggle' === $op ) {
			$wpdb->update( $t, array( 'active' => $it['active'] ? 0 : 1 ), array( 'id' => $id ) );
			HSSP_Util::back( 'ذخیره شد.' );
		}
		HSSP_Util::back( 'نامعتبر.' );
	}
}

HSSP_Library::init();
