<?php
/**
 * «پروفایل سریع دانش‌آموز»: با زدن روی نام دانش‌آموز (حضور و غیاب، امتیازها و…) باز می‌شود.
 * عکس، امتیاز کل، ثبت تشویق یا «نیاز به توجه» با یک لمس، یادداشت، و سابقه‌ی امتیازها.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Student_Sheet {

	private static $printed = false;

	/** دایره‌ی آواتار: عکس دانش‌آموز اگر باشد، وگرنه حرف اول نام */
	public static function avatar( $user_id, $name = '', $size = '' ) {
		$p   = class_exists( 'HS_Student_Profile' ) ? (array) HS_Student_Profile::get( $user_id ) : array();
		$url = ! empty( $p['photo_url'] ) ? esc_url( $p['photo_url'] ) : '';
		$name = $name ? $name : ( $p['display_name'] ?? '' );
		$cls = 'hs-student-avatar' . ( $url ? ' has-photo' : '' ) . ( $size ? ' ' . $size : '' );
		$in  = $url ? '<img src="' . $url . '" alt="" loading="lazy">' : esc_html( mb_substr( $name, 0, 1 ) );
		return '<span class="' . esc_attr( $cls ) . '" data-avatar="' . (int) $user_id . '">' . $in . '</span>';
	}

	/** دکمه‌ی نام (باز کردن پروفایل) */
	public static function name_button( $class_id, $user ) {
		$p     = class_exists( 'HS_Student_Profile' ) ? (array) HS_Student_Profile::get( $user->ID ) : array();
		$total = class_exists( 'HS_Points_DB' ) ? (int) HS_Points_DB::get_student_total( $class_id, $user->ID ) : 0;
		return '<button type="button" class="hs-qa-open" data-student-id="' . (int) $user->ID . '" data-name="' . esc_attr( $user->display_name ) . '" data-photo="' . esc_attr( $p['photo_url'] ?? '' ) . '" title="پروفایل، امتیاز و یادداشت">'
			. self::avatar( $user->ID, $user->display_name )
			. '<strong>' . esc_html( $user->display_name ) . '</strong>'
			. '<span class="hs-qa-pts' . ( $total < 0 ? ' neg' : '' ) . '" data-student-id="' . (int) $user->ID . '">' . esc_html( HS_Util::fa_digits( $total ) ) . '</span>'
			. '</button>';
	}

	/** پنجره (یک بار در صفحه) */
	public static function render( $class_id ) {
		if ( self::$printed || ! $class_id ) {
			return '';
		}
		self::$printed = true;
		$beh = class_exists( 'HS_Points_DB' ) ? HS_Points_DB::get_behaviors( $class_id ) : array();
		$pos = array_filter( $beh, function ( $b ) { return (int) $b['points'] >= 0; } );
		$neg = array_filter( $beh, function ( $b ) { return (int) $b['points'] < 0; } );
		$btn = function ( $b, $cls ) {
			$p = (int) $b['points'];
			return '<button type="button" class="hs-qa-skill ' . $cls . '" data-behavior="' . (int) $b['id'] . '">'
				. '<span class="hs-qa-badge">' . esc_html( HS_Util::fa_digits( ( $p >= 0 ? '+' : '−' ) . abs( $p ) ) ) . '</span>'
				. '<span class="hs-qa-ico">' . esc_html( $b['icon'] ) . '</span>'
				. '<span class="hs-qa-name">' . esc_html( $b['name'] ) . '</span></button>';
		};
		ob_start();
		?>
		<div class="hs-qa-modal" id="hs-qa-modal" hidden role="dialog" aria-modal="true" aria-labelledby="hs-qa-title" data-class-id="<?php echo (int) $class_id; ?>">
			<div class="hs-qa-box">
				<div class="hs-qa-head">
					<label class="hs-qa-photo" title="تغییر عکس">
						<span class="hs-qa-photo-img"></span>
						<span class="hs-qa-photo-edit" aria-hidden="true">📷</span>
						<input type="file" accept="image/*" class="hs-qa-photo-file" aria-label="انتخاب عکس دانش‌آموز">
					</label>
					<div class="hs-qa-who"><h3 id="hs-qa-title"></h3><span class="hs-qa-total">امتیاز کل: <b>۰</b></span></div>
					<button type="button" class="hs-qa-close" aria-label="بستن">×</button>
				</div>

				<section class="hs-qa-sec pos">
					<h4>👍 تشویق <small>با هر لمس، امتیاز مثبت ثبت می‌شود و خانواده خبردار می‌شود</small></h4>
					<div class="hs-qa-grid"><?php foreach ( $pos as $b ) { echo $btn( $b, 'pos' ); } // phpcs:ignore ?>
						<?php if ( ! $pos ) : ?><p class="hs-muted">رفتار تشویقی تعریف نشده.</p><?php endif; ?></div>
				</section>
				<?php if ( $neg ) : ?>
				<section class="hs-qa-sec neg">
					<h4>⚠️ نیاز به توجه <small>امتیاز منفی؛ برای رفتاری که باید بهتر شود</small></h4>
					<div class="hs-qa-grid"><?php foreach ( $neg as $b ) { echo $btn( $b, 'neg' ); } // phpcs:ignore ?></div>
				</section>
				<?php endif; ?>

				<label class="hs-qa-note"><span>📝 یادداشت (اختیاری؛ همراه امتیاز بعدی ثبت می‌شود)</span><textarea rows="2" placeholder="مثلاً: در کار گروهی به دوستش کمک کرد"></textarea></label>
				<div class="hs-qa-msg" aria-live="polite"></div>

				<details class="hs-qa-hist"><summary>سابقه‌ی امتیازها</summary><ul></ul></details>
				<div class="hs-qa-foot">
					<a class="hs-btn hs-btn-ghost hs-btn-sm hs-qa-full" href="<?php echo esc_url( add_query_arg( 'class_id', $class_id, home_url( '/panel/classes/' ) ) ); ?>">پرونده‌ی کامل</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
