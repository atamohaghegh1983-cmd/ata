<?php
/** تنظیمات هر کلاس + فهرست بازی‌ها */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSSP_Settings {

	const META = '_hssp_settings';

	/** id => [آیکون, عنوان, توضیح, از پایه, تا پایه, رنگ] */
	public static function games() {
		return array(
			'letters' => array( '🔤', 'پیدا کن نشانه را', 'حرف درست را انتخاب کن', 0, 1, 'g-purple' ),
			'count'   => array( '🔢', 'بشمار!', 'تعداد شکل‌ها را بگو', 0, 1, 'g-coral' ),
			'words'   => array( '🧩', 'کلمه را کامل کن', 'نشانه‌ی جاافتاده را پیدا کن', 1, 3, 'g-purple' ),
			'add'     => array( '➕', 'جمع', 'جمع متناسب با پایه‌ی تو', 1, 4, 'g-teal' ),
			'sub'     => array( '➖', 'تفریق', 'تفریق متناسب با پایه‌ی تو', 1, 5, 'g-coral' ),
			'compare' => array( '⚖️', 'بزرگ‌تر یا کوچک‌تر؟', 'علامت درست را بگذار', 1, 4, 'g-amber' ),
			'mul'     => array( '✖️', 'جدول ضرب', 'ضرب‌ها را سریع جواب بده', 2, 7, 'g-teal' ),
			'div'     => array( '➗', 'تقسیم', 'تقسیم بدون باقی‌مانده', 3, 7, 'g-purple' ),
			'frac'    => array( '🍕', 'کسرها', 'کدام کسر بزرگ‌تر است؟', 4, 8, 'g-amber' ),
			'mental'  => array( '🧮', 'محاسبه‌ی ذهنی', 'ترکیب عمل‌ها، عدد منفی و درصد', 6, 12, 'g-coral' ),
			'memory'  => array( '🧠', 'حافظه', 'جفت‌ها را پیدا کن', 0, 12, 'g-amber' ),
		);
	}

	public static function get( $cid ) {
		$s = get_post_meta( $cid, self::META, true );
		return wp_parse_args( is_array( $s ) ? $s : array(), array( 'theme' => 'auto', 'games' => null, 'login' => 0 ) );
	}

	public static function save( $cid, $data ) {
		$s = self::get( $cid );
		if ( isset( $data['theme'] ) && in_array( $data['theme'], array( 'auto', 'kid', 'teen' ), true ) ) {
			$s['theme'] = $data['theme'];
		}
		if ( array_key_exists( 'games', $data ) ) {
			$s['games'] = null === $data['games'] ? null : array_values( array_intersect( (array) $data['games'], array_keys( self::games() ) ) );
		}
		if ( isset( $data['login'] ) ) {
			$s['login'] = $data['login'] ? 1 : 0;
		}
		update_post_meta( $cid, self::META, $s );
		return $s;
	}

	/** ظاهر: kid (اول تا سوم و پیش‌دبستانی) یا teen */
	public static function theme( $cid ) {
		$s = self::get( $cid );
		if ( 'auto' !== $s['theme'] ) {
			return $s['theme'];
		}
		$g = HSSP_Util::grade_num( $cid );
		return ( $g < 0 || $g <= 3 ) ? 'kid' : 'teen';
	}

	public static function suitable( $id, $grade ) {
		$g = self::games()[ $id ] ?? null;
		if ( ! $g ) {
			return false;
		}
		return $grade < 0 ? $g[3] <= 3 : ( $grade >= $g[3] && $grade <= $g[4] );
	}

	/** بازی‌های فعال کلاس */
	public static function class_games( $cid ) {
		$s     = self::get( $cid );
		$grade = HSSP_Util::grade_num( $cid );
		if ( is_array( $s['games'] ) ) {
			return $s['games'];
		}
		return array_values( array_filter( array_keys( self::games() ), function ( $id ) use ( $grade ) {
			return HSSP_Settings::suitable( $id, $grade );
		} ) );
	}
}
