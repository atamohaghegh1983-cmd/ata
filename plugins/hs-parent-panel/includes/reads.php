<?php
/**
 * «دیده شد» برای اطلاعیه‌ها
 * والد وقتی اطلاعیه را در پنل می‌بیند ثبت می‌شود؛ معلم کنار هر اطلاعیه می‌بیند چند والد دیده‌اند.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Reads {

	public static function init() {
		add_action( 'hs_announcement_meta', array( __CLASS__, 'teacher_badge' ), 10, 2 );
	}

	public static function mark( $uid, $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		if ( ! $ids ) {
			return;
		}
		$t   = HSPP_DB::t( 'reads' );
		$now = HSPP_DB::now();
		foreach ( $ids as $id ) {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $t (ann_id, user_id, read_at) VALUES (%d, %d, %s)", $id, $uid, $now ) ); // phpcs:ignore
		}
	}

	public static function readers( $ann_id ) {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . HSPP_DB::t( 'reads' ) . ' WHERE ann_id = %d', $ann_id ) ) ); // phpcs:ignore
	}

	/** والدین کلاس */
	public static function class_parents( $class_id ) {
		$p = array();
		foreach ( (array) get_post_meta( $class_id, '_hs_students', true ) as $sid ) {
			foreach ( HS_Parent_Link::get_parents( (int) $sid ) as $pid ) {
				$p[ (int) $pid ] = true;
			}
		}
		return array_keys( $p );
	}

	/** کنار اطلاعیه در پنل معلم: «👁 ۵ از ۱۸ والد» + فهرست نام‌ها */
	public static function teacher_badge( $item, $class_id ) {
		$parents = self::class_parents( $class_id );
		if ( ! $parents ) {
			return;
		}
		$seen  = array_intersect( self::readers( $item['id'] ), $parents );
		$names = array();
		foreach ( $seen as $pid ) {
			$names[] = HSPP_Names::name( $pid, $class_id );
		}
		$unseen = array();
		foreach ( array_diff( $parents, $seen ) as $pid ) {
			$unseen[] = HSPP_Names::name( $pid, $class_id );
		}
		echo '<details class="hspp-seen"><summary>👁 ' . esc_html( count( $seen ) . ' از ' . count( $parents ) . ' والد دیده‌اند' ) . '</summary>'
			. ( $names ? '<p>✅ ' . esc_html( implode( '، ', $names ) ) . '</p>' : '' )
			. ( $unseen ? '<p>⏳ ' . esc_html( implode( '، ', $unseen ) ) . '</p>' : '' ) . '</details>';
	}

	/** کارت «اطلاعیه‌های جدید» در خانه‌ی پنل والدین */
	public static function latest_card( $uid, $child_id ) {
		if ( ! $child_id || ! HSPP_Perms::can( $uid, $child_id, 'announce' ) || ! class_exists( 'HS_Comms_DB' ) ) {
			return '';
		}
		$items = array();
		foreach ( HS_Parent_Link::student_classes( $child_id ) as $c ) {
			foreach ( HS_Comms_DB::get_announcements( $c['id'], 5 ) as $a ) {
				$items[] = $a;
			}
		}
		if ( ! $items ) {
			return '';
		}
		usort( $items, function ( $x, $y ) {
			return strcmp( $y['created_at'], $x['created_at'] );
		} );
		global $wpdb;
		$h = '<section class="hs-pp-card"><h3>📢 اطلاعیه‌های تازه</h3><ul class="hs-pp-list">';
		foreach ( array_slice( $items, 0, 3 ) as $a ) {
			$new = ! $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . HSPP_DB::t( 'reads' ) . ' WHERE ann_id = %d AND user_id = %d', $a['id'], $uid ) ); // phpcs:ignore
			$h  .= '<li><strong>' . ( $new ? '<span class="hs-pp-new">جدید</span> ' : '' ) . esc_html( $a['title'] ? $a['title'] : 'اطلاعیه' ) . '</strong><span>' . esc_html( wp_html_excerpt( $a['body'], 120, '…' ) ) . '</span></li>';
		}
		$h .= '</ul><a class="hs-pp-link" href="' . esc_url( add_query_arg( array( 'pp' => 'messages', 'child' => $child_id ), HSPP_Integration::page_url( 'parent' ) ) ) . '">همه‌ی اطلاعیه‌ها ←</a></section>';
		return $h;
	}
}

HSPP_Reads::init();
