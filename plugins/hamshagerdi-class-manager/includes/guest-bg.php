<?php
/**
 * تصویر پس‌زمینه‌ی صفحه‌ی ورود هر بخش (معلم، دانش‌آموز، والدین، مدیر مدرسه)
 *
 * محل: کل صفحه، سرِ صفحه، پایین صفحه (فوتر)، حاشیه‌ی چپ، حاشیه‌ی راست، یا آزاد (جابه‌جایی با موس)
 * تنظیمات: شفافیت، اندازه، و نقطه‌ی تمرکز/محل تصویر با کشیدن موس روی پیش‌نمایش.
 * افزونه‌های دیگر (مثلاً ساختار مدرسه) همین بخش را در منوی خودشان نشان می‌دهند: HS_Guest_Bg::admin_box( 'school_admin' ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Guest_Bg {

	const OPT = 'hs_guest_bg';

	public static function init() {
		add_action( 'admin_menu', function () {
			add_options_page( 'تصویر صفحه‌ی ورود بخش‌ها', 'تصویر صفحه‌ی ورود', 'manage_options', 'hs-guest-bg', array( __CLASS__, 'page' ) );
		} );
		add_action( 'admin_post_hs_guest_bg_save', array( __CLASS__, 'save' ) );
	}

	public static function modes() {
		return array(
			'full'   => 'کل صفحه',
			'top'    => 'سرِ صفحه',
			'bottom' => 'پایین صفحه (فوتر)',
			'right'  => 'حاشیه‌ی راست',
			'left'   => 'حاشیه‌ی چپ',
			'free'   => 'آزاد (جابه‌جایی با موس)',
		);
	}

	public static function defaults( $role ) {
		$d = array( 'url' => '', 'opacity' => 20, 'mode' => 'full', 'x' => 50, 'y' => 50, 'size' => 35 );
		if ( 'teacher' === $role ) {
			$d['url'] = HS_CLASS_URL . 'assets/img/teacher-bg.jpg';
		}
		return $d;
	}

	public static function get( $role ) {
		$all = (array) get_option( self::OPT, array() );
		$c   = isset( $all[ $role ] ) ? (array) $all[ $role ] : array();
		return wp_parse_args( $c, self::defaults( $role ) );
	}

	/* ---------- نمایش در صفحه ---------- */

	public static function html( $role ) {
		$c = self::get( $role );
		if ( ! $c['url'] || $c['opacity'] <= 0 ) {
			return '';
		}
		$style = sprintf( '--o:%s;--x:%s%%;--y:%s%%;--s:%s;', $c['opacity'] / 100, (float) $c['x'], (float) $c['y'], (float) $c['size'] );
		if ( 'free' === $c['mode'] ) {
			return '<img class="hs-guest-bg hs-guest-bg-free" src="' . esc_url( $c['url'] ) . '" alt="" aria-hidden="true" style="' . esc_attr( $style ) . '">';
		}
		$style .= "background-image:url('" . esc_url( $c['url'] ) . "')";
		return '<div class="hs-guest-bg hs-guest-bg-' . esc_attr( $c['mode'] ) . '" aria-hidden="true" style="' . esc_attr( $style ) . '"></div>';
	}

	/* ---------- تنظیمات ---------- */

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.' );
		}
		check_admin_referer( 'hs_guest_bg' );
		$role = sanitize_key( wp_unslash( $_POST['role'] ?? '' ) );
		$all  = (array) get_option( self::OPT, array() );
		if ( ! empty( $_POST['reset'] ) ) {
			unset( $all[ $role ] );
		} else {
			$mode         = sanitize_key( wp_unslash( $_POST['mode'] ?? 'full' ) );
			$clamp        = function ( $v, $lo, $hi ) {
				return max( $lo, min( $hi, round( (float) $v, 1 ) ) );
			};
			$all[ $role ] = array(
				'url'     => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
				'opacity' => (int) $clamp( $_POST['opacity'] ?? 20, 0, 100 ),
				'mode'    => array_key_exists( $mode, self::modes() ) ? $mode : 'full',
				'x'       => $clamp( $_POST['x'] ?? 50, 0, 100 ),
				'y'       => $clamp( $_POST['y'] ?? 50, 0, 100 ),
				'size'    => $clamp( $_POST['size'] ?? 35, 5, 100 ),
			);
		}
		update_option( self::OPT, $all );
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'options-general.php?page=hs-guest-bg' );
		wp_safe_redirect( add_query_arg( 'hs_bg_saved', 1, $back ) );
		exit;
	}

	/** صفحه‌ی تنظیمات معلم / دانش‌آموز / والدین (مدیر مدرسه در منوی افزونه‌ی خودش) */
	public static function page() {
		$tabs = array( 'teacher' => 'معلم', 'student' => 'دانش‌آموز', 'parent' => 'والدین' );
		$cur  = isset( $_GET['role'] ) && isset( $tabs[ $_GET['role'] ] ) ? sanitize_key( $_GET['role'] ) : 'teacher';
		echo '<div class="wrap" dir="rtl"><h1>تصویر صفحه‌ی ورود</h1><nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $l ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $k === $cur ? 'nav-tab-active' : '', esc_url( add_query_arg( array( 'page' => 'hs-guest-bg', 'role' => $k ), admin_url( 'options-general.php' ) ) ), esc_html( $l ) );
		}
		echo '</nav>';
		self::admin_box( $cur );
		echo '</div>';
	}

	/** فرم تنظیم تصویر یک بخش (قابل استفاده در منوی هر افزونه) */
	public static function admin_box( $role ) {
		wp_enqueue_media();
		$c = self::get( $role );
		if ( ! empty( $_GET['hs_bg_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hs-bg-form" style="max-width:980px">
			<?php wp_nonce_field( 'hs_guest_bg' ); ?>
			<input type="hidden" name="action" value="hs_guest_bg_save">
			<input type="hidden" name="role" value="<?php echo esc_attr( $role ); ?>">
			<input type="hidden" name="url" value="<?php echo esc_attr( $c['url'] ); ?>">
			<input type="hidden" name="x" value="<?php echo esc_attr( $c['x'] ); ?>">
			<input type="hidden" name="y" value="<?php echo esc_attr( $c['y'] ); ?>">

			<div style="display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:24px;align-items:start;margin-top:16px">
				<div>
					<div class="hs-bg-preview" title="برای جابه‌جایی تصویر، روی پیش‌نمایش بکشید">
						<div class="hs-bg-layer"></div>
						<div class="hs-bg-mock" aria-hidden="true">
							<i style="width:40%;height:9%;top:6%;right:5%"></i>
							<i style="width:55%;height:4%;top:19%;right:5%"></i>
							<i style="width:27%;height:46%;top:30%;left:5%;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.12)"></i>
							<i style="width:28%;height:14%;top:30%;right:5%"></i><i style="width:28%;height:14%;top:30%;right:35%"></i>
							<i style="width:28%;height:14%;top:48%;right:5%"></i><i style="width:28%;height:14%;top:48%;right:35%"></i>
						</div>
					</div>
					<p class="description">پیش‌نمایش صفحه‌ی ورود. <strong>با موس روی تصویر بکشید</strong> تا جای آن تنظیم شود.</p>
				</div>
				<div>
					<p>
						<button type="button" class="button hs-bg-pick">انتخاب تصویر از رسانه</button>
						<button type="button" class="button-link hs-bg-clear" style="color:#b32d2e;margin-right:8px">حذف تصویر</button>
					</p>
					<p><label><strong>محل تصویر</strong><br>
						<select name="mode" class="hs-bg-mode" style="width:100%">
							<?php foreach ( self::modes() as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c['mode'], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select></label></p>
					<p><label><strong>شفافیت</strong> <output class="hs-bg-o-out"><?php echo (int) $c['opacity']; ?>٪</output><br>
						<input type="range" name="opacity" min="0" max="100" step="5" value="<?php echo (int) $c['opacity']; ?>" class="hs-bg-o" style="width:100%"></label></p>
					<p class="hs-bg-size-row"><label><strong class="hs-bg-size-label">اندازه</strong> <output class="hs-bg-s-out"><?php echo (float) $c['size']; ?>٪</output><br>
						<input type="range" name="size" min="5" max="100" step="1" value="<?php echo (float) $c['size']; ?>" class="hs-bg-s" style="width:100%"></label></p>
					<?php submit_button( 'ذخیره', 'primary', 'submit', false ); ?>
					<button type="submit" name="reset" value="1" class="button" style="margin-right:6px">بازگشت به پیش‌فرض</button>
				</div>
			</div>
		</form>
		<style>
			.hs-bg-preview{position:relative;aspect-ratio:16/10;background:#FFF8F2;border:1px solid #dcdcde;border-radius:12px;overflow:hidden;cursor:grab;user-select:none;touch-action:none}
			.hs-bg-preview.is-drag{cursor:grabbing}
			.hs-bg-layer{position:absolute;background-repeat:no-repeat;background-size:cover;pointer-events:none}
			.hs-bg-layer img{display:block;width:100%;height:auto}
			.hs-bg-mock i{position:absolute;background:rgba(245,124,0,.13);border-radius:6px}
		</style>
		<script>
		jQuery(function ($) {
			var $f = $('.hs-bg-form'), $pv = $f.find('.hs-bg-preview'), $L = $f.find('.hs-bg-layer');
			var v = function (n) { return $f.find('[name=' + n + ']').val(); };
			var labels = { top: 'ارتفاع', bottom: 'ارتفاع', left: 'پهنا', right: 'پهنا', free: 'پهنای تصویر' };
			function draw() {
				var m = v('mode'), url = v('url'), s = +v('size'), x = +v('x'), y = +v('y');
				$f.find('.hs-bg-o-out').text(v('opacity') + '٪');
				$f.find('.hs-bg-s-out').text(s + '٪');
				$f.find('.hs-bg-size-row').toggle(m !== 'full');
				$f.find('.hs-bg-size-label').text(labels[m] || 'اندازه');
				$L.attr('style', '').empty().css({ opacity: v('opacity') / 100 });
				if (!url) { return; }
				if (m === 'free') {
					$L.append($('<img alt="">').attr('src', url)).css({ left: x + '%', top: y + '%', width: s + '%', transform: 'translate(-50%,-50%)' });
					return;
				}
				var css = { backgroundImage: 'url("' + url + '")', backgroundPosition: x + '% ' + y + '%' };
				var box = { full: { inset: 0 }, top: { top: 0, left: 0, right: 0, height: s + '%' }, bottom: { bottom: 0, left: 0, right: 0, height: s + '%' },
					left: { top: 0, bottom: 0, left: 0, width: s + '%' }, right: { top: 0, bottom: 0, right: 0, width: s + '%' } }[m];
				$L.css($.extend(css, box, { top: box.top, left: box.left, right: box.right, bottom: box.bottom }));
				if (m === 'full') { $L.css({ top: 0, left: 0, right: 0, bottom: 0 }); }
			}
			$f.on('input change', 'select, input[type=range]', draw);

			// کشیدن با موس: «آزاد» ← محل مرکز تصویر؛ بقیه ← نقطه‌ی تمرکز تصویر
			var drag = null;
			$pv.on('pointerdown', function (e) {
				if (!v('url')) { return; }
				drag = { sx: e.clientX, sy: e.clientY, x: +v('x'), y: +v('y'), w: $pv.width(), h: $pv.height() };
				$pv.addClass('is-drag')[0].setPointerCapture(e.pointerId);
			}).on('pointermove', function (e) {
				if (!drag) { return; }
				var dx = (e.clientX - drag.sx) / drag.w * 100, dy = (e.clientY - drag.sy) / drag.h * 100;
				if (v('mode') !== 'free') { dx = -dx; dy = -dy; } // کشیدن تصویر به راست = دیدن بخش چپ آن
				var c = function (n) { return Math.max(0, Math.min(100, Math.round(n * 10) / 10)); };
				$f.find('[name=x]').val(c(drag.x + dx));
				$f.find('[name=y]').val(c(drag.y + dy));
				draw();
			}).on('pointerup pointercancel', function () { drag = null; $pv.removeClass('is-drag'); });

			var frame;
			$f.find('.hs-bg-pick').on('click', function () {
				frame = frame || wp.media({ title: 'تصویر صفحه‌ی ورود', library: { type: 'image' }, multiple: false, button: { text: 'استفاده از این تصویر' } });
				frame.off('select').on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					$f.find('[name=url]').val(a.sizes && a.sizes.large ? a.sizes.large.url : a.url);
					draw();
				});
				frame.open();
			});
			$f.find('.hs-bg-clear').on('click', function () { $f.find('[name=url]').val(''); draw(); });
			draw();
		});
		</script>
		<?php
	}
}

HS_Guest_Bg::init();
