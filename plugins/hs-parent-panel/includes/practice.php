<?php
/**
 * کتابخانه‌ی «تمرین در خانه»
 * معلم برای کلاس تمرین/فعالیت پیشنهادی می‌گذارد (متن، لینک، زمان تقریبی)؛
 * والد بعد از انجام با فرزند «انجام شد» می‌زند و می‌تواند یادداشت کوتاه بگذارد. معلم آمار هر تمرین را می‌بیند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Practice {

	public static function init() {
		add_action( 'admin_post_hspp_prac', array( __CLASS__, 'handle' ) );
		add_shortcode( 'hs_home_practice', array( __CLASS__, 'teacher_page' ) );
	}

	public static function for_class( $cid, $only_active = true ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'practice' ) . ' WHERE class_id = %d' . ( $only_active ? ' AND active = 1' : '' ) . ' ORDER BY id DESC LIMIT 100', $cid ), ARRAY_A ); // phpcs:ignore
	}

	public static function done_map( $sid, $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		if ( ! $ids ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'practice_done' ) . ' WHERE student_id = %d AND practice_id IN (' . implode( ',', $ids ) . ')', $sid ), ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['practice_id'] ] = $r;
		}
		return $out;
	}

	public static function done_list( $pid ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'practice_done' ) . ' WHERE practice_id = %d ORDER BY done_at DESC', $pid ), ARRAY_A ); // phpcs:ignore
	}

	public static function month_count( $cid, $sid, $ym ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSPP_DB::t( 'practice_done' ) . ' d JOIN ' . HSPP_DB::t( 'practice' ) . ' p ON p.id = d.practice_id WHERE p.class_id = %d AND d.student_id = %d AND d.done_at LIKE %s', $cid, $sid, $wpdb->esc_like( $ym ) . '%' ) ); // phpcs:ignore
	}

	public static function new_since( $cid, $since ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'practice' ) . ' WHERE class_id = %d AND active = 1 AND created_at >= %s', $cid, $since ), ARRAY_A ); // phpcs:ignore
	}

	private static function form_start( $op, $extra = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $extra . '>' . wp_nonce_field( 'hspp_prac', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="hspp_prac"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
	}

	private static function item_body( $p ) {
		return '<strong>' . esc_html( $p['title'] ) . '</strong>'
			. ( $p['subject'] || $p['minutes'] ? ' <small class="hs-pp-muted">' . esc_html( trim( $p['subject'] . ( $p['minutes'] ? ' · حدود ' . (int) $p['minutes'] . ' دقیقه' : '' ), ' ·' ) ) . '</small>' : '' )
			. ( $p['body'] ? '<div class="hspp-prac-body">' . nl2br( esc_html( $p['body'] ) ) . '</div>' : '' )
			. ( $p['url'] ? '<a class="hspp-prac-link" href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener nofollow">🔗 باز کردن</a>' : '' );
	}

	/* ---------- والد ---------- */

	public static function render( $pid, $sid ) {
		$h       = HSPP_Util::notice() . '<section class="hs-pp-card"><h3>🏡 تمرین در خانه</h3><p class="hs-pp-muted">فعالیت‌هایی که معلم برای تمرین در خانه پیشنهاد داده. بعد از انجام با فرزندتان «انجام شد» را بزنید تا معلم هم ببیند.</p></section>';
		$classes = HSPP_Util::child_classes( $pid, $sid, 'practice' );
		$any     = false;
		foreach ( $classes as $c ) {
			$items = self::for_class( $c['id'] );
			if ( ! $items ) {
				continue;
			}
			$any  = true;
			$done = self::done_map( $sid, wp_list_pluck( $items, 'id' ) );
			$h   .= '<section class="hs-pp-card"><h3>🏫 ' . esc_html( $c['title'] ) . ' <small>(' . count( array_intersect_key( $done, array_flip( wp_list_pluck( $items, 'id' ) ) ) ) . ' از ' . count( $items ) . ' انجام شده)</small></h3><ul class="hspp-prac">';
			foreach ( $items as $p ) {
				$d  = $done[ (int) $p['id'] ] ?? null;
				$h .= '<li class="' . ( $d ? 'is-done' : '' ) . '"><div>' . self::item_body( $p ) . '</div>';
				if ( $d ) {
					$h .= '<div class="hspp-prac-act"><span class="hspp-badge" style="--c:#10B981">✓ انجام شد ' . esc_html( HSPP_Util::d( $d['done_at'], false ) ) . '</span>' . ( $d['note'] ? '<small>' . esc_html( $d['note'] ) . '</small>' : '' )
						. self::form_start( 'undo' ) . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"><input type="hidden" name="child" value="' . (int) $sid . '"><button class="hspp-link">برگرداندن</button></form></div>';
				} else {
					$h .= '<div class="hspp-prac-act">' . self::form_start( 'done', 'class="hspp-inline"' ) . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"><input type="hidden" name="child" value="' . (int) $sid . '">'
						. '<input name="note" maxlength="200" placeholder="یادداشت کوتاه (اختیاری)"><button class="hs-pp-btn hs-pp-btn-primary">✓ انجام شد</button></form></div>';
				}
				$h .= '</li>';
			}
			$h .= '</ul></section>';
		}
		if ( ! $any ) {
			$h .= '<section class="hs-pp-card"><p class="hs-pp-muted">معلم هنوز تمرینی برای خانه نگذاشته است.</p></section>';
		}
		return $h;
	}

	/* ---------- معلم ---------- */

	public static function teacher_page() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid     = get_current_user_id();
		$classes = current_user_can( 'manage_options' ) ? array_map( 'intval', get_posts( array( 'post_type' => 'hs_class', 'numberposts' => 200, 'fields' => 'ids' ) ) ) : HSPP_Util::teacher_classes( $uid );
		if ( ! $classes ) {
			return '<p class="hs-muted">ابتدا یک کلاس بسازید.</p>';
		}
		$cid = isset( $_GET['cls'] ) && in_array( (int) $_GET['cls'], $classes, true ) ? (int) $_GET['cls'] : $classes[0]; // phpcs:ignore
		$h   = '<div class="hspp-wrap" dir="rtl">' . HSPP_Util::notice();
		if ( count( $classes ) > 1 ) {
			$h .= '<nav class="hspp-levels">';
			foreach ( $classes as $c ) {
				$h .= '<a class="' . ( $c === $cid ? 'is-on' : '' ) . '" href="' . esc_url( add_query_arg( 'cls', $c ) ) . '">' . esc_html( get_the_title( $c ) ) . '</a>';
			}
			$h .= '</nav>';
		}
		$h .= '<section class="hs-card"><h2>🏡 تمرین در خانه — ' . esc_html( get_the_title( $cid ) ) . '</h2><p class="hs-muted">فعالیت پیشنهادی برای انجام با خانواده (مثلاً خواندن با صدای بلند، بازی شمارش، آزمایش ساده). والدین بعد از انجام علامت می‌زنند.</p>'
			. self::form_start( 'add', 'class="hspp-form"' ) . '<input type="hidden" name="class_id" value="' . (int) $cid . '">'
			. '<label>عنوان<input name="title" required maxlength="190" placeholder="مثلاً: خواندن داستان «...» با صدای بلند"></label>'
			. '<div class="hspp-row"><label>درس<input name="subject" maxlength="80" placeholder="فارسی"></label><label>زمان تقریبی (دقیقه)<input type="number" name="minutes" min="0" max="300" value="15"></label></div>'
			. '<label>توضیح برای والدین<textarea name="body" rows="3" maxlength="2000" placeholder="چه کار کنند و به چه نکته‌ای دقت کنند"></textarea></label>'
			. '<label>پیوند (اختیاری)<input type="url" name="url" dir="ltr" placeholder="https://"></label>'
			. '<button class="hs-btn hs-btn-primary">افزودن تمرین</button></form></section>';

		$items   = self::for_class( $cid, false );
		$total   = count( (array) get_post_meta( $cid, '_hs_students', true ) );
		if ( $items ) {
			$h .= '<section class="hs-card"><h3>تمرین‌ها</h3><ul class="hspp-prac">';
			foreach ( $items as $p ) {
				$done = self::done_list( $p['id'] );
				$h   .= '<li class="' . ( $p['active'] ? '' : 'is-off' ) . '"><div>' . self::item_body( $p ) . '<details><summary>✓ ' . count( $done ) . ' از ' . (int) $total . ' دانش‌آموز انجام داده‌اند</summary><ul>';
				foreach ( $done as $d ) {
					$s  = get_userdata( $d['student_id'] );
					$h .= '<li>' . esc_html( $s ? $s->display_name : '—' ) . ' <small>' . esc_html( HSPP_Util::d( $d['done_at'], false ) ) . '</small>' . ( $d['note'] ? ' — ' . esc_html( $d['note'] ) : '' ) . '</li>';
				}
				$h .= '</ul></details></div><div class="hspp-prac-act">'
					. self::form_start( 'toggle' ) . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"><button class="hspp-link">' . ( $p['active'] ? 'پنهان کردن' : 'نمایش دوباره' ) . '</button></form>'
					. self::form_start( 'del', 'onsubmit="return confirm(\'حذف شود؟\')"' ) . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"><button class="hspp-link hspp-danger">حذف</button></form></div></li>';
			}
			$h .= '</ul></section>';
		}
		return $h . '</div>';
	}

	private static function owns( $uid, $cid ) {
		return user_can( $uid, 'manage_options' ) || (int) get_post_meta( $cid, '_hs_teacher_id', true ) === (int) $uid;
	}

	public static function handle() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'hspp_prac' ) ) {
			wp_die( 'نامعتبر' );
		}
		global $wpdb;
		$uid = get_current_user_id();
		$op  = sanitize_key( $_POST['op'] ?? '' );
		$t   = HSPP_DB::t( 'practice' );
		$td  = HSPP_DB::t( 'practice_done' );

		if ( 'add' === $op ) {
			$cid = (int) ( $_POST['class_id'] ?? 0 );
			if ( ! self::owns( $uid, $cid ) ) {
				HSPP_Util::back( 'اجازه‌ی این کار را ندارید.' );
			}
			$title = mb_substr( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 0, 190 );
			if ( '' === $title ) {
				HSPP_Util::back( 'عنوان را بنویسید.' );
			}
			$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ), array( 'http', 'https' ) );
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				$url = '';
			}
			$wpdb->insert( $t, array(
				'class_id'   => $cid,
				'author_id'  => $uid,
				'title'      => $title,
				'subject'    => mb_substr( sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ), 0, 80 ),
				'body'       => mb_substr( sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ), 0, 2000 ),
				'url'        => $url,
				'minutes'    => max( 0, min( 300, (int) ( $_POST['minutes'] ?? 0 ) ) ),
				'active'     => 1,
				'created_at' => HSPP_DB::now(),
			) );
			HSPP_Util::back( 'تمرین اضافه شد و والدین آن را می‌بینند.' );
		}

		$id = (int) ( $_POST['id'] ?? 0 );
		$p  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore
		if ( ! $p ) {
			HSPP_Util::back( 'پیدا نشد.' );
		}
		if ( in_array( $op, array( 'del', 'toggle' ), true ) ) {
			if ( ! self::owns( $uid, $p['class_id'] ) ) {
				HSPP_Util::back( 'اجازه‌ی این کار را ندارید.' );
			}
			if ( 'del' === $op ) {
				$wpdb->delete( $t, array( 'id' => $id ) );
				$wpdb->delete( $td, array( 'practice_id' => $id ) );
				HSPP_Util::back( 'حذف شد.' );
			}
			$wpdb->update( $t, array( 'active' => $p['active'] ? 0 : 1 ), array( 'id' => $id ) );
			HSPP_Util::back( 'ذخیره شد.' );
		}
		$sid = (int) ( $_POST['child'] ?? 0 );
		if ( ! HSPP_Perms::can( $uid, $sid, 'practice', $p['class_id'] ) || ! in_array( (int) $p['class_id'], HSPP_Perms::student_classes( $sid ), true ) ) {
			HSPP_Util::back( 'اجازه‌ی این کار را ندارید.' );
		}
		if ( 'done' === $op && $p['active'] ) {
			$wpdb->replace( $td, array( 'practice_id' => $id, 'student_id' => $sid, 'parent_id' => $uid, 'note' => mb_substr( sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ), 0, 200 ), 'done_at' => HSPP_DB::now() ) );
			HSPP_Util::back( 'آفرین! ثبت شد 🌱' );
		}
		if ( 'undo' === $op ) {
			$wpdb->delete( $td, array( 'practice_id' => $id, 'student_id' => $sid ) );
			HSPP_Util::back( 'برگردانده شد.' );
		}
		HSPP_Util::back( 'نامعتبر.' );
	}
}

HSPP_Practice::init();
