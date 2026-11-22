<?php
/**
 * نام نمایشی والدین در گروه کلاس
 * پیش‌فرض: نقش + نام فرزند در همان کلاس، مثل «مادرِ سارا احمدی»؛ والد می‌تواند تغییرش دهد.
 * شماره موبایل هیچ‌وقت نمایش داده نمی‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Names {

	const META = '_hspp_group_name_';

	public static function relation_label( $parent_id, $student_id ) {
		global $wpdb;
		$rel = '';
		if ( class_exists( 'HSUM_DB' ) ) {
			$rel = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT relation FROM ' . HSUM_DB::t( 'links' ) . " WHERE parent_id = %d AND student_id = %d AND status = 'active' LIMIT 1", $parent_id, $student_id ) ); // phpcs:ignore
		}
		return array( 'father' => 'پدرِ', 'mother' => 'مادرِ', 'guardian' => 'سرپرستِ' )[ $rel ] ?? 'والدِ';
	}

	/** فرزندان این والد در این کلاس */
	public static function children_in_class( $parent_id, $class_id ) {
		$out = array();
		foreach ( HS_Parent_Link::get_children( $parent_id ) as $sid ) {
			if ( in_array( (int) $class_id, HSPP_Perms::student_classes( $sid ), true ) ) {
				$out[] = (int) $sid;
			}
		}
		return $out;
	}

	public static function default_name( $parent_id, $class_id ) {
		$kids = self::children_in_class( $parent_id, $class_id );
		if ( ! $kids ) {
			$u = get_userdata( $parent_id );
			return $u ? $u->display_name : 'کاربر';
		}
		$s = get_userdata( $kids[0] );
		return self::relation_label( $parent_id, $kids[0] ) . ' ' . ( $s ? $s->display_name : '' );
	}

	public static function name( $user_id, $class_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return 'کاربر';
		}
		// معلم کلاس و مدیران با نام خودشان
		if ( (int) get_post_meta( $class_id, '_hs_teacher_id', true ) === (int) $user_id ) {
			return $u->display_name . ' (معلم)';
		}
		if ( user_can( $u, 'manage_options' ) ) {
			return $u->display_name . ' (مدیر سایت)';
		}
		$custom = trim( (string) get_user_meta( $user_id, self::META . (int) $class_id, true ) );
		$name   = $custom ? $custom : self::default_name( $user_id, $class_id );
		if ( HSPP_Group::is_assistant( $class_id, $user_id ) ) {
			$name .= ' (دستیار)';
		}
		return $name;
	}

	public static function set( $user_id, $class_id, $name ) {
		$name = trim( wp_strip_all_tags( (string) $name ) );
		if ( '' === $name ) {
			delete_user_meta( $user_id, self::META . (int) $class_id ); // بازگشت به پیش‌فرض
			return true;
		}
		if ( mb_strlen( $name ) > 40 ) {
			return new WP_Error( 'long', 'نام نمایشی حداکثر ۴۰ حرف.' );
		}
		if ( preg_match( '/(?:0|\+98|98)?9\d{9}/u', HSPP_Profile::en_digits( $name ) ) ) {
			return new WP_Error( 'phone', 'شماره تلفن در نام نمایشی مجاز نیست.' );
		}
		if ( preg_match( '/معلم|مدیر|دستیار|ادمین|admin/ui', $name ) ) {
			return new WP_Error( 'role', 'از عنوان‌هایی مثل معلم، مدیر یا دستیار در نام استفاده نکنید.' );
		}
		update_user_meta( $user_id, self::META . (int) $class_id, sanitize_text_field( $name ) );
		return true;
	}
}
