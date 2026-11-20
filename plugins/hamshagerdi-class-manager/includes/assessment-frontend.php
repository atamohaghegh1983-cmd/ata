<?php
/**
 * رابط کاربری ارزیابی گروهی
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Assessment_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_assessment', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای استفاده باید وارد شوید.</div>';
		}
		if ( ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان در دسترس است.</div>';
		}

		$classes = self::teacher_classes();
		$selected_class = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;
		if ( ! $selected_class && ! empty( $classes ) ) {
			$selected_class = $classes[0]['id'];
		}
		$assessment_id = isset( $_GET['assessment_id'] ) ? absint( $_GET['assessment_id'] ) : 0;

		ob_start();
		?>
		<div class="hs-assess-panel" id="hs-assess-panel"
			 data-class-id="<?php echo esc_attr( $selected_class ); ?>"
			 data-assessment-id="<?php echo esc_attr( $assessment_id ); ?>">

			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2>ارزیابی و نمرات</h2>
					<p class="hs-muted">ساخت ارزیابی گروهی و ثبت وضعیت همه دانش‌آموزان در یک صفحه</p>
				</div>
				<?php if ( ! empty( $classes ) ) : ?>
					<a class="hs-btn hs-btn-ghost" href="<?php echo esc_url( add_query_arg( 'class_id', $selected_class, HS_Shell::url( 'insights' ) ) ); ?>">📊 تحلیل نمرات و نمودارها</a>
				<?php endif; ?>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">📝</div>
					<h3>هنوز کلاسی ندارید</h3>
					<a href="<?php echo esc_url( home_url( '/panel/classes/' ) ); ?>" class="hs-btn hs-btn-primary">+ ساخت کلاس</a>
				</div>
			<?php elseif ( $assessment_id && HS_Roles::can_manage_class( $selected_class ) ) : ?>
				<?php echo self::render_marking( $selected_class, $assessment_id ); ?>
			<?php else : ?>
				<?php echo self::render_list( $classes, $selected_class ); ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * لیست ارزیابی‌ها + فرم ساخت
	 */
	private static function render_list( $classes, $selected_class ) {
		$assessments = array();
		if ( $selected_class && HS_Roles::can_manage_class( $selected_class ) ) {
			$assessments = HS_Assessment_DB::get_class_assessments( $selected_class );
		}
		$levels = HS_Assessment_DB::descriptive_levels();

		ob_start();
		?>
		<div class="hs-card hs-att-filters">
			<div class="hs-att-filter-row" style="grid-template-columns:1fr auto;">
				<div class="hs-form-row">
					<label>کلاس</label>
					<select id="hs-assess-class">
						<?php foreach ( $classes as $c ) : ?>
							<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected_class, $c['id'] ); ?>>
								<?php echo esc_html( $c['title'] ); ?>
								<?php if ( $c['grade'] ) echo ' (' . esc_html( $c['grade'] ) . ')'; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="hs-form-row hs-att-filter-actions">
					<label>&nbsp;</label>
					<button type="button" class="hs-btn hs-btn-primary" id="hs-assess-load">نمایش</button>
				</div>
			</div>
		</div>

		<div class="hs-card">
			<h3>ساخت ارزیابی جدید</h3>
			<p class="hs-muted">مثلاً: ارزیابی روان‌خوانی آبان، املای درس ۵، آزمون ریاضی فصل ۲</p>
			<form id="hs-form-create-assessment">
				<div class="hs-form-row">
					<label>عنوان ارزیابی <span class="required">*</span></label>
					<input type="text" id="hs-assess-title" placeholder="مثلاً: ارزیابی روان‌خوانی آبان" required>
				</div>
				<div class="hs-form-grid">
					<div class="hs-form-row">
						<label>درس / موضوع</label>
						<input type="text" id="hs-assess-subject" placeholder="فارسی، ریاضی، ...">
					</div>
					<div class="hs-form-row">
						<label>تاریخ</label>
						<input type="date" id="hs-assess-date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
					</div>
				</div>
				<div class="hs-form-row">
					<label>نوع ارزیابی</label>
					<div class="hs-assess-scale-options">
						<label class="hs-assess-scale-opt">
							<input type="radio" name="scale_type" value="descriptive" checked>
							<span>توصیفی (خیلی خوب / خوب / قابل قبول / نیاز به تلاش)</span>
						</label>
						<label class="hs-assess-scale-opt">
							<input type="radio" name="scale_type" value="numeric">
							<span>نمره‌ای (۰ تا ۲۰)</span>
						</label>
					</div>
				</div>
				<div class="hs-form-actions">
					<button type="submit" class="hs-btn hs-btn-primary">+ ساخت و شروع ارزیابی</button>
				</div>
				<div class="hs-form-message" id="hs-assess-create-msg"></div>
			</form>
		</div>

		<div class="hs-card">
			<h3>ارزیابی‌های قبلی</h3>
			<?php if ( empty( $assessments ) ) : ?>
				<p class="hs-muted">هنوز ارزیابی‌ای برای این کلاس ثبت نشده.</p>
			<?php else : ?>
				<ul class="hs-assess-list" id="hs-assess-list">
					<?php foreach ( $assessments as $a ) :
						$url = add_query_arg( array(
							'class_id'      => $selected_class,
							'assessment_id' => $a['id'],
						) );
						$type_label = $a['scale_type'] === 'numeric' ? 'نمره‌ای' : 'توصیفی';
						?>
						<li class="hs-assess-list-item" data-id="<?php echo esc_attr( $a['id'] ); ?>">
							<div class="hs-assess-list-info">
								<a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( $a['title'] ); ?></strong></a>
								<span class="hs-muted">
									<?php if ( $a['subject'] ) echo esc_html( $a['subject'] ) . ' · '; ?>
									<?php echo esc_html( $type_label ); ?> ·
									<?php echo esc_html( $a['assess_date'] ); ?>
								</span>
							</div>
							<div class="hs-assess-list-actions">
								<a href="<?php echo esc_url( $url ); ?>" class="hs-btn hs-btn-sm hs-btn-primary">ثبت نتایج</a>
								<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-assess-delete" data-id="<?php echo esc_attr( $a['id'] ); ?>">حذف</button>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * صفحه ثبت نتایج برای همه دانش‌آموزان
	 */
	private static function render_marking( $class_id, $assessment_id ) {
		$assessment = HS_Assessment_DB::get_assessment( $assessment_id );
		if ( ! $assessment || (int) $assessment['class_id'] !== (int) $class_id ) {
			return '<div class="hs-alert hs-alert-error">ارزیابی یافت نشد.</div>';
		}

		$students = HS_Class_Students::get_students( $class_id );
		$results  = HS_Assessment_DB::get_results( $assessment_id );
		$levels   = HS_Assessment_DB::descriptive_levels();
		$is_numeric = $assessment['scale_type'] === 'numeric';
		$back_url = add_query_arg( 'class_id', $class_id, home_url( '/panel/assessment/' ) );
		$summary  = HS_Assessment_DB::get_summary( $assessment_id );

		ob_start();
		?>
		<div class="hs-assess-marking">
			<div class="hs-panel-header" style="margin-bottom:1rem;">
				<div>
					<a href="<?php echo esc_url( $back_url ); ?>" class="hs-back-link">← بازگشت به لیست ارزیابی‌ها</a>
					<h2 style="margin:0.25rem 0;"><?php echo esc_html( $assessment['title'] ); ?></h2>
					<p class="hs-muted" style="margin:0;">
						<?php if ( $assessment['subject'] ) echo esc_html( $assessment['subject'] ) . ' · '; ?>
						<?php echo $is_numeric ? 'نمره‌ای (۰–۲۰)' : 'توصیفی'; ?> ·
						<?php echo esc_html( $assessment['assess_date'] ); ?>
					</p>
				</div>
			</div>

			<?php if ( empty( $students ) ) : ?>
				<div class="hs-empty-state">
					<p>دانش‌آموزی در کلاس نیست.</p>
					<a href="<?php echo esc_url( add_query_arg( 'class_id', $class_id, home_url( '/panel/classes/' ) ) ); ?>" class="hs-btn hs-btn-primary">افزودن دانش‌آموز</a>
				</div>
			<?php else : ?>

			<?php if ( ! $is_numeric ) : ?>
			<div class="hs-att-bulk hs-card">
				<span class="hs-att-bulk-label">اعمال برای همه:</span>
				<?php foreach ( $levels as $key => $lv ) :
					if ( $key === 'absent' ) continue;
					?>
					<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-assess-bulk" data-level="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $lv['label'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<form id="hs-form-assess-results">
				<div class="hs-card hs-assess-results-card">
					<div class="hs-assess-results-header">
						<span>دانش‌آموز</span>
						<span><?php echo $is_numeric ? 'نمره' : 'سطح'; ?></span>
						<span>توضیح (اختیاری)</span>
					</div>

					<?php foreach ( $students as $student ) :
						$sid = $student->ID;
						$cur = isset( $results[ $sid ] ) ? $results[ $sid ] : null;
						$cur_level = $cur ? $cur['level_key'] : '';
						$cur_score = $cur && $cur['score'] !== null ? $cur['score'] : '';
						$cur_comment = $cur ? $cur['comment'] : '';
						?>
						<div class="hs-assess-result-row" data-student-id="<?php echo esc_attr( $sid ); ?>">
							<div class="hs-assess-st-name">
								<?php echo HS_Student_Sheet::avatar( $student->ID, $student->display_name ); // phpcs:ignore ?>
								<strong><?php echo esc_html( $student->display_name ); ?></strong>
							</div>
							<div class="hs-assess-st-value">
								<?php if ( $is_numeric ) : ?>
									<input type="number" class="hs-assess-score" min="0" max="20" step="0.25"
										   value="<?php echo esc_attr( $cur_score ); ?>" placeholder="۰–۲۰">
								<?php else : ?>
									<div class="hs-assess-levels">
										<?php foreach ( $levels as $key => $lv ) : ?>
											<label class="hs-assess-level-btn <?php echo $cur_level === $key ? 'active' : ''; ?>"
												   style="<?php echo $cur_level === $key ? 'border-color:' . esc_attr( $lv['color'] ) . ';background:' . esc_attr( $lv['color'] ) . '18;' : ''; ?>">
												<input type="radio" name="level[<?php echo esc_attr( $sid ); ?>]"
													   value="<?php echo esc_attr( $key ); ?>" <?php checked( $cur_level, $key ); ?>>
												<span><?php echo esc_html( $lv['label'] ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
							<div class="hs-assess-st-comment">
								<input type="text" class="hs-assess-comment" value="<?php echo esc_attr( $cur_comment ); ?>" placeholder="توضیح کوتاه...">
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="hs-att-footer">
					<div class="hs-assess-summary" id="hs-assess-summary">
						<?php if ( $is_numeric && ! empty( $summary['count'] ) ) : ?>
							<span>میانگین: <strong><?php echo esc_html( $summary['avg'] ); ?></strong></span>
						<?php elseif ( ! empty( $summary['levels'] ) ) : ?>
							<?php foreach ( $summary['levels'] as $k => $cnt ) :
								if ( ! $cnt ) continue;
								$lab = isset( $levels[ $k ] ) ? $levels[ $k ]['label'] : $k;
								?>
								<span><?php echo esc_html( $lab ); ?>: <strong><?php echo (int) $cnt; ?></strong></span>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<button type="submit" class="hs-btn hs-btn-primary hs-btn-lg" id="hs-assess-save">
						💾 ذخیره نتایج
					</button>
				</div>
				<div class="hs-form-message" id="hs-assess-save-msg"></div>
			</form>
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
				'grade' => get_post_meta( get_the_ID(), '_hs_grade', true ),
			);
		}
		wp_reset_postdata();
		return $out;
	}
}

HS_Assessment_Frontend::init();
