<?php
/**
 * ابزارهای کلاس: تایمر، انتخاب تصادفی، گروه‌ساز
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Tools_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_tools', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای استفاده باید وارد شوید.</div>';
		}
		if ( ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان در دسترس است.</div>';
		}

		$classes = self::teacher_classes();
		$selected = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;
		if ( ! $selected && ! empty( $classes ) ) {
			$selected = $classes[0]['id'];
		}

		$students = array();
		if ( $selected && HS_Roles::can_manage_class( $selected ) ) {
			$users = HS_Class_Students::get_students( $selected );
			foreach ( $users as $u ) {
				$students[] = array(
					'id'   => $u->ID,
					'name' => $u->display_name,
				);
			}
		}

		ob_start();
		?>
		<div class="hs-tools-panel" id="hs-tools-panel"
			 data-class-id="<?php echo esc_attr( $selected ); ?>"
			 data-students="<?php echo esc_attr( wp_json_encode( $students, JSON_UNESCAPED_UNICODE ) ); ?>">

			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2>ابزارهای کلاس</h2>
					<p class="hs-muted">تایمر، انتخاب تصادفی، گروه‌ساز و امتیاز گروه، تاس و سکه، سروصداسنج</p>
				</div>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">🛠️</div>
					<h3>هنوز کلاسی ندارید</h3>
					<a href="<?php echo esc_url( home_url( '/panel/classes/' ) ); ?>" class="hs-btn hs-btn-primary">+ ساخت کلاس</a>
				</div>
			<?php else : ?>

			<div class="hs-card hs-att-filters">
				<div class="hs-att-filter-row" style="grid-template-columns:1fr auto;">
					<div class="hs-form-row">
						<label>کلاس (برای لیست دانش‌آموزان)</label>
						<select id="hs-tools-class">
							<?php foreach ( $classes as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected, $c['id'] ); ?>>
									<?php echo esc_html( $c['title'] ); ?>
									— <?php echo count( $students ) && $selected === $c['id'] ? count( $students ) : '…'; ?> نفر
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row hs-att-filter-actions">
						<label>&nbsp;</label>
						<button type="button" class="hs-btn hs-btn-primary" id="hs-tools-load">بارگذاری</button>
					</div>
				</div>
			</div>

			<nav class="hs-comms-tabs">
				<button type="button" class="hs-comms-tab active" data-tool="timer">⏱️ تایمر</button>
				<button type="button" class="hs-comms-tab" data-tool="picker">🎲 انتخاب تصادفی</button>
				<button type="button" class="hs-comms-tab" data-tool="groups">👥 گروه‌ساز و امتیاز</button>
				<button type="button" class="hs-comms-tab" data-tool="dice">🎯 تاس و سکه</button>
				<button type="button" class="hs-comms-tab" data-tool="noise">🔊 سروصداسنج</button>
			</nav>

			<!-- تایمر -->
			<div class="hs-tool-section" id="hs-tool-timer">
				<div class="hs-card hs-timer-card">
					<div class="hs-timer-display" id="hs-timer-display">05:00</div>
					<div class="hs-timer-presets">
						<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-timer-preset" data-sec="60">۱ دقیقه</button>
						<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-timer-preset" data-sec="180">۳ دقیقه</button>
						<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-timer-preset" data-sec="300">۵ دقیقه</button>
						<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-timer-preset" data-sec="600">۱۰ دقیقه</button>
						<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-timer-preset" data-sec="900">۱۵ دقیقه</button>
					</div>
					<div class="hs-timer-custom">
						<label>دقیقه:</label>
						<input type="number" id="hs-timer-min" min="0" max="99" value="5">
						<label>ثانیه:</label>
						<input type="number" id="hs-timer-sec" min="0" max="59" value="0">
						<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-timer-set">تنظیم</button>
					</div>
					<div class="hs-timer-controls">
						<button type="button" class="hs-btn hs-btn-primary hs-btn-lg" id="hs-timer-start">▶ شروع</button>
						<button type="button" class="hs-btn hs-btn-ghost hs-btn-lg" id="hs-timer-pause" disabled>⏸ توقف</button>
						<button type="button" class="hs-btn hs-btn-ghost hs-btn-lg" id="hs-timer-reset">↺ ریست</button>
					</div>
					<div class="hs-timer-mode">
						<label class="hs-check-label">
							<input type="radio" name="timer_mode" value="countdown" checked> شمارش معکوس
						</label>
						<label class="hs-check-label">
							<input type="radio" name="timer_mode" value="stopwatch"> کرونومتر
						</label>
					</div>
					<p class="hs-timer-status" id="hs-timer-status"></p>
				</div>
			</div>

			<!-- انتخاب تصادفی -->
			<div class="hs-tool-section" id="hs-tool-picker" style="display:none;">
				<div class="hs-card">
					<?php if ( empty( $students ) ) : ?>
						<p class="hs-muted">ابتدا کلاس را با دانش‌آموز بارگذاری کنید.</p>
					<?php else : ?>
						<div class="hs-picker-stage">
							<div class="hs-picker-result" id="hs-picker-result">
								<span class="hs-picker-placeholder">برای انتخاب کلیک کنید</span>
							</div>
							<button type="button" class="hs-btn hs-btn-primary hs-btn-lg" id="hs-picker-go">🎲 انتخاب دانش‌آموز</button>
							<label class="hs-check-label" style="margin-top:1rem;">
								<input type="checkbox" id="hs-picker-exclude" checked>
								حذف از دور بعدی (تا همه انتخاب شوند)
							</label>
							<p class="hs-muted" id="hs-picker-remaining" style="margin-top:0.5rem;"></p>
						</div>
						<div class="hs-picker-history">
							<h4>انتخاب‌شده‌ها</h4>
							<ul id="hs-picker-history"></ul>
							<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-picker-reset">شروع مجدد لیست</button>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- گروه‌ساز -->
			<div class="hs-tool-section" id="hs-tool-groups" style="display:none;">
				<div class="hs-card">
					<?php if ( empty( $students ) ) : ?>
						<p class="hs-muted">ابتدا کلاس را با دانش‌آموز بارگذاری کنید.</p>
					<?php else : ?>
						<div class="hs-groups-controls">
							<div class="hs-form-row">
								<label>تعداد گروه</label>
								<input type="number" id="hs-groups-count" min="2" max="12" value="4">
							</div>
							<button type="button" class="hs-btn hs-btn-primary" id="hs-groups-make">ساخت گروه‌های تصادفی</button>
						</div>
						<div class="hs-groups-grid" id="hs-groups-grid"></div>
					<?php endif; ?>
				</div>
			</div>

			<!-- تاس و سکه -->
			<div class="hs-tool-section" id="hs-tool-dice" style="display:none;">
				<div class="hs-card hs-dice-card">
					<div class="hs-dice-out" id="hs-dice-out"><span class="hs-die">⚀</span></div>
					<p class="hs-muted" id="hs-dice-sum"></p>
					<div class="hs-dice-controls">
						<label for="hs-dice-count">تعداد تاس</label>
						<select id="hs-dice-count"><option value="1">۱</option><option value="2">۲</option><option value="3">۳</option></select>
						<button type="button" class="hs-btn hs-btn-primary" id="hs-dice-roll">🎲 بینداز</button>
					</div>
					<hr class="hs-tool-sep">
					<div class="hs-coin" id="hs-coin">🪙</div>
					<p class="hs-muted" id="hs-coin-res"></p>
					<button type="button" class="hs-btn hs-btn-ghost" id="hs-coin-flip">🪙 پرتاب سکه</button>
				</div>
			</div>

			<!-- سروصداسنج -->
			<div class="hs-tool-section" id="hs-tool-noise" style="display:none;">
				<div class="hs-card hs-noise-card">
					<p class="hs-muted">میزان سروصدای کلاس را با میکروفون دستگاه نشان می‌دهد. صدا ضبط یا ذخیره نمی‌شود.</p>
					<div class="hs-noise-meter"><div class="hs-noise-bar" id="hs-noise-bar"></div></div>
					<p class="hs-noise-state" id="hs-noise-state">خاموش</p>
					<div class="hs-dice-controls">
						<label for="hs-noise-limit">حد مجاز سروصدا</label>
						<input type="range" id="hs-noise-limit" min="20" max="90" value="60">
						<button type="button" class="hs-btn hs-btn-primary" id="hs-noise-toggle">🎙️ شروع اندازه‌گیری</button>
					</div>
				</div>
			</div>

			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function teacher_classes() {
		$user_id  = get_current_user_id();
		$is_admin = current_user_can( 'hs_manage_all_classes' );
		$args = array(
			'post_type'      => 'hs_class',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! $is_admin ) {
			$args['meta_query'] = array(
				array( 'key' => '_hs_teacher_id', 'value' => $user_id ),
			);
		}
		$q = new WP_Query( $args );
		$out = array();
		while ( $q->have_posts() ) {
			$q->the_post();
			$out[] = array(
				'id'    => get_the_ID(),
				'title' => get_the_title(),
			);
		}
		wp_reset_postdata();
		return $out;
	}
}

HS_Tools_Frontend::init();
