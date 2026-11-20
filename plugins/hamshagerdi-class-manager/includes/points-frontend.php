<?php
/**
 * رابط کاربری سیستم امتیاز و تشویق
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Points_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_points', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای استفاده از سیستم امتیاز باید وارد شوید. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">ورود</a></div>';
		}
		if ( ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان در دسترس است.</div>';
		}

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
		$query = new WP_Query( $args );
		$classes = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$cid = get_the_ID();
			$classes[] = array(
				'id'    => $cid,
				'title' => get_the_title(),
				'grade' => get_post_meta( $cid, '_hs_grade', true ),
				'count' => HS_Class_Students::count( $cid ),
			);
		}
		wp_reset_postdata();

		$selected_class = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;
		if ( ! $selected_class && ! empty( $classes ) ) {
			$selected_class = $classes[0]['id'];
		}

		$students   = array();
		$behaviors  = array();
		$scores     = array();
		$recent     = array();

		if ( $selected_class && HS_Roles::can_manage_class( $selected_class ) ) {
			$students  = HS_Class_Students::get_students( $selected_class );
			$behaviors = HS_Points_DB::get_behaviors( $selected_class );
			$scores    = HS_Points_DB::get_class_leaderboard( $selected_class );
			$recent    = HS_Points_DB::get_recent( $selected_class, 15 );
		}

		// مرتب‌سازی دانش‌آموزان بر اساس امتیاز
		$student_rows = array();
		foreach ( $students as $user ) {
			$sid = $user->ID;
			$student_rows[] = array(
				'id'    => $sid,
				'name'  => $user->display_name,
				'total' => isset( $scores[ $sid ] ) ? $scores[ $sid ] : 0,
			);
		}
		usort( $student_rows, function( $a, $b ) {
			return $b['total'] - $a['total'];
		} );

		$positive = array_filter( $behaviors, function( $b ) { return (int) $b['is_positive'] === 1; } );
		$negative = array_filter( $behaviors, function( $b ) { return (int) $b['is_positive'] === 0; } );

		ob_start();
		?>
		<div class="hs-points-panel" id="hs-points-panel" data-class-id="<?php echo esc_attr( $selected_class ); ?>">

			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2>تشویق و امتیاز</h2>
					<p class="hs-muted">ثبت رفتارهای مثبت و امتیازدهی به دانش‌آموزان</p>
				</div>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">⭐</div>
					<h3>هنوز کلاسی ندارید</h3>
					<p>ابتدا یک کلاس بسازید و دانش‌آموزان را اضافه کنید.</p>
					<a href="<?php echo esc_url( home_url( '/panel/classes/' ) ); ?>" class="hs-btn hs-btn-primary">+ ساخت کلاس</a>
				</div>
			<?php else : ?>

			<!-- انتخاب کلاس -->
			<div class="hs-card hs-att-filters">
				<div class="hs-att-filter-row" style="grid-template-columns: 1fr auto;">
					<div class="hs-form-row">
						<label for="hs-pts-class">کلاس</label>
						<select id="hs-pts-class">
							<?php foreach ( $classes as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected_class, $c['id'] ); ?>>
									<?php echo esc_html( $c['title'] ); ?>
									<?php if ( $c['grade'] ) echo ' (' . esc_html( $c['grade'] ) . ')'; ?>
									— <?php echo (int) $c['count']; ?> نفر
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row hs-att-filter-actions">
						<label>&nbsp;</label>
						<button type="button" class="hs-btn hs-btn-primary" id="hs-pts-load">نمایش</button>
					</div>
				</div>
			</div>

			<?php if ( $selected_class && empty( $students ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">👤</div>
					<h3>دانش‌آموزی در این کلاس نیست</h3>
					<a href="<?php echo esc_url( add_query_arg( 'class_id', $selected_class, home_url( '/panel/classes/' ) ) ); ?>" class="hs-btn hs-btn-primary">افزودن دانش‌آموز</a>
				</div>
			<?php elseif ( $selected_class ) : ?>

			<div class="hs-pts-layout">
				<!-- ستون اصلی: اعطای امتیاز -->
				<div class="hs-pts-main">

					<!-- روش اصلی: روی دانش‌آموز بزنید -->
					<div class="hs-card">
						<h3>روی دانش‌آموز بزنید</h3>
						<p class="hs-muted">پروفایل دانش‌آموز باز می‌شود؛ تشویق، «نیاز به توجه» یا یادداشت را همان‌جا با یک لمس ثبت کنید.</p>
						<div class="hs-pts-roster">
							<?php foreach ( $students as $st ) { echo HS_Student_Sheet::name_button( $selected_class, $st ); } // phpcs:ignore ?>
						</div>
					</div>

					<details class="hs-pts-bulk">
					<summary>امتیاز گروهی (یک رفتار برای چند دانش‌آموز با هم)</summary>
					<!-- انتخاب رفتار -->
					<div class="hs-card">
						<h3>۱. انتخاب رفتار</h3>
						<div class="hs-pts-behaviors">
							<div class="hs-pts-beh-group">
								<span class="hs-pts-beh-label">مثبت</span>
								<div class="hs-pts-beh-list" id="hs-pts-positive">
									<?php foreach ( $positive as $b ) : ?>
										<button type="button"
											class="hs-pts-beh-btn"
											data-id="<?php echo esc_attr( $b['id'] ); ?>"
											data-points="<?php echo esc_attr( $b['points'] ); ?>"
											data-name="<?php echo esc_attr( $b['name'] ); ?>"
											data-icon="<?php echo esc_attr( $b['icon'] ); ?>">
											<span class="beh-icon"><?php echo esc_html( $b['icon'] ); ?></span>
											<span class="beh-name"><?php echo esc_html( $b['name'] ); ?></span>
											<span class="beh-pts">+<?php echo (int) $b['points']; ?></span>
										</button>
									<?php endforeach; ?>
								</div>
							</div>
							<?php if ( ! empty( $negative ) ) : ?>
							<div class="hs-pts-beh-group">
								<span class="hs-pts-beh-label">نیاز به بهبود</span>
								<div class="hs-pts-beh-list" id="hs-pts-negative">
									<?php foreach ( $negative as $b ) : ?>
										<button type="button"
											class="hs-pts-beh-btn hs-pts-beh-neg"
											data-id="<?php echo esc_attr( $b['id'] ); ?>"
											data-points="<?php echo esc_attr( $b['points'] ); ?>"
											data-name="<?php echo esc_attr( $b['name'] ); ?>"
											data-icon="<?php echo esc_attr( $b['icon'] ); ?>">
											<span class="beh-icon"><?php echo esc_html( $b['icon'] ); ?></span>
											<span class="beh-name"><?php echo esc_html( $b['name'] ); ?></span>
											<span class="beh-pts"><?php echo (int) $b['points']; ?></span>
										</button>
									<?php endforeach; ?>
								</div>
							</div>
							<?php endif; ?>
						</div>
						<p class="hs-pts-selected" id="hs-pts-selected-label">رفتاری انتخاب نشده</p>
					</div>

					<!-- انتخاب دانش‌آموزان -->
					<div class="hs-card">
						<div class="hs-card-header-row">
							<h3>۲. انتخاب دانش‌آموزان</h3>
							<div>
								<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-pts-select-all">همه</button>
								<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-pts-select-none">هیچکدام</button>
							</div>
						</div>
						<div class="hs-pts-students" id="hs-pts-students">
							<?php foreach ( $student_rows as $s ) : ?>
								<label class="hs-pts-student-item">
									<input type="checkbox" name="student_ids[]" value="<?php echo esc_attr( $s['id'] ); ?>">
									<?php echo HS_Student_Sheet::avatar( $s['id'], $s['name'] ); // phpcs:ignore ?>
									<span class="hs-pts-st-name"><?php echo esc_html( $s['name'] ); ?></span>
									<span class="hs-pts-st-score" data-student-id="<?php echo esc_attr( $s['id'] ); ?>"><?php echo (int) $s['total']; ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="hs-form-row" style="margin-top:1rem;">
							<label for="hs-pts-note">یادداشت (اختیاری)</label>
							<input type="text" id="hs-pts-note" placeholder="مثلاً: کمک عالی در کار گروهی امروز">
						</div>
						<div class="hs-form-actions" style="margin-top:1rem;">
							<button type="button" class="hs-btn hs-btn-primary hs-btn-lg" id="hs-pts-award" disabled>
								ثبت امتیاز
							</button>
						</div>
						<div class="hs-form-message" id="hs-pts-message"></div>
					</div>
					</details>
					<?php echo HS_Student_Sheet::render( $selected_class ); // phpcs:ignore ?>
				</div>

				<!-- سایدبار: رتبه‌بندی + تاریخچه -->
				<div class="hs-pts-side">
					<div class="hs-card">
						<h3>🏆 رتبه‌بندی کلاس</h3>
						<ol class="hs-pts-leaderboard" id="hs-pts-leaderboard">
							<?php
							$rank = 1;
							foreach ( $student_rows as $s ) :
								$medal = $rank === 1 ? '🥇' : ( $rank === 2 ? '🥈' : ( $rank === 3 ? '🥉' : $rank . '.' ) );
								?>
								<li>
									<span class="lb-rank"><?php echo esc_html( $medal ); ?></span>
									<span class="lb-name"><?php echo esc_html( $s['name'] ); ?></span>
									<span class="lb-score" data-student-id="<?php echo esc_attr( $s['id'] ); ?>"><?php echo (int) $s['total']; ?></span>
								</li>
							<?php
								$rank++;
							endforeach;
							?>
						</ol>
					</div>

					<div class="hs-card">
						<h3>فعالیت اخیر</h3>
						<ul class="hs-pts-recent" id="hs-pts-recent">
							<?php if ( empty( $recent ) ) : ?>
								<li class="hs-muted">هنوز امتیازی ثبت نشده.</li>
							<?php else : ?>
								<?php foreach ( $recent as $r ) :
									$u = get_userdata( $r['student_id'] );
									$sign = (int) $r['points'] >= 0 ? '+' : '';
									?>
									<li>
										<span class="rec-icon"><?php echo esc_html( $r['behavior_icon'] ?: '⭐' ); ?></span>
										<span class="rec-text">
											<strong><?php echo $u ? esc_html( $u->display_name ) : '—'; ?></strong>
											<?php echo esc_html( $r['behavior_name'] ?: '' ); ?>
											<em><?php echo $sign . (int) $r['points']; ?></em>
										</span>
									</li>
								<?php endforeach; ?>
							<?php endif; ?>
						</ul>
					</div>
				</div>
			</div>

			<?php endif; // students ?>
			<?php endif; // classes ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

HS_Points_Frontend::init();
