<?php
/**
 * رابط کاربری تکالیف
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Homework_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_homework', array( __CLASS__, 'render' ) );
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
		$homework_id = isset( $_GET['homework_id'] ) ? absint( $_GET['homework_id'] ) : 0;

		ob_start();
		?>
		<div class="hs-hw-panel" id="hs-hw-panel"
			 data-class-id="<?php echo esc_attr( $selected ); ?>"
			 data-homework-id="<?php echo esc_attr( $homework_id ); ?>">

			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2><?php echo esc_html( HS_Hubs::title( 'homework', 'تکالیف و تمرین' ) ); ?></h2>
					<p class="hs-muted">تعریف تکلیف، تاریخ تحویل و پیگیری انجام توسط دانش‌آموزان</p>
				</div>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">📚</div>
					<h3>هنوز کلاسی ندارید</h3>
					<a href="<?php echo esc_url( home_url( '/panel/classes/' ) ); ?>" class="hs-btn hs-btn-primary">+ ساخت کلاس</a>
				</div>
			<?php elseif ( $homework_id && HS_Roles::can_manage_class( $selected ) ) : ?>
				<?php echo self::render_tracking( $selected, $homework_id ); ?>
			<?php else : ?>
				<?php echo self::render_list( $classes, $selected ); ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_list( $classes, $selected ) {
		$list = array();
		if ( $selected && HS_Roles::can_manage_class( $selected ) ) {
			$list = HS_Homework_DB::get_class_homework( $selected );
		}
		$statuses = HS_Homework_DB::statuses();

		ob_start();
		?>
		<div class="hs-card hs-att-filters">
			<div class="hs-att-filter-row" style="grid-template-columns:1fr auto;">
				<div class="hs-form-row">
					<label>کلاس</label>
					<select id="hs-hw-class">
						<?php foreach ( $classes as $c ) : ?>
							<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected, $c['id'] ); ?>>
								<?php echo esc_html( $c['title'] ); ?>
								<?php if ( $c['grade'] ) echo ' (' . esc_html( $c['grade'] ) . ')'; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="hs-form-row hs-att-filter-actions">
					<label>&nbsp;</label>
					<button type="button" class="hs-btn hs-btn-primary" id="hs-hw-load">نمایش</button>
				</div>
			</div>
		</div>

		<div class="hs-card">
			<h3>تعریف تکلیف جدید</h3>
			<form id="hs-form-create-hw">
				<div class="hs-form-row">
					<label>عنوان تکلیف <span class="required">*</span></label>
					<input type="text" id="hs-hw-title" placeholder="مثلاً: تمرین صفحه ۴۲ ریاضی" required>
				</div>
				<div class="hs-form-grid">
					<div class="hs-form-row">
						<label>درس</label>
						<input type="text" id="hs-hw-subject" placeholder="ریاضی، فارسی، ...">
					</div>
					<div class="hs-form-row">
						<label>مهلت تحویل</label>
						<input type="date" id="hs-hw-due">
					</div>
				</div>
				<div class="hs-form-row">
					<label>توضیحات</label>
					<textarea id="hs-hw-desc" rows="2" placeholder="جزئیات تکلیف برای دانش‌آموزان و والدین..."></textarea>
				</div>
				<div class="hs-form-actions">
					<button type="submit" class="hs-btn hs-btn-primary">+ ثبت تکلیف</button>
				</div>
				<div class="hs-form-message" id="hs-hw-create-msg"></div>
			</form>
		</div>

		<div class="hs-card">
			<h3>تکالیف کلاس</h3>
			<?php if ( empty( $list ) ) : ?>
				<p class="hs-muted">هنوز تکلیفی تعریف نشده.</p>
			<?php else : ?>
				<ul class="hs-hw-list">
					<?php foreach ( $list as $hw ) :
						$url = add_query_arg( array(
							'class_id'    => $selected,
							'homework_id' => $hw['id'],
						) );
						$summary = HS_Homework_DB::get_summary( $hw['id'] );
						$done = (int) $summary['done'];
						$total_tracked = array_sum( $summary );
						$overdue = $hw['due_date'] && $hw['due_date'] < current_time( 'Y-m-d' );
						?>
						<li class="hs-hw-list-item <?php echo $overdue ? 'overdue' : ''; ?>" data-id="<?php echo esc_attr( $hw['id'] ); ?>">
							<div class="hs-hw-list-info">
								<a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( $hw['title'] ); ?></strong></a>
								<span class="hs-muted">
									<?php if ( $hw['subject'] ) echo esc_html( $hw['subject'] ) . ' · '; ?>
									<?php if ( $hw['due_date'] ) : ?>
										مهلت: <?php echo esc_html( $hw['due_date'] ); ?>
										<?php if ( $overdue ) echo ' <em style="color:#EF4444;">(گذشته)</em>'; ?>
									<?php else : ?>
										بدون مهلت
									<?php endif; ?>
									<?php if ( $total_tracked ) : ?>
										· انجام‌شده: <?php echo esc_html( $done ); ?>/<?php echo esc_html( $total_tracked ); ?>
									<?php endif; ?>
								</span>
							</div>
							<div class="hs-hw-list-actions">
								<a href="<?php echo esc_url( $url ); ?>" class="hs-btn hs-btn-sm hs-btn-primary">پیگیری</a>
								<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-hw-delete" data-id="<?php echo esc_attr( $hw['id'] ); ?>">حذف</button>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_tracking( $class_id, $homework_id ) {
		$hw = HS_Homework_DB::get( $homework_id );
		if ( ! $hw || (int) $hw['class_id'] !== (int) $class_id ) {
			return '<div class="hs-alert hs-alert-error">تکلیف یافت نشد.</div>';
		}

		$students = HS_Class_Students::get_students( $class_id );
		$statuses = HS_Homework_DB::get_statuses( $homework_id );
		$status_defs = HS_Homework_DB::statuses();
		$summary = HS_Homework_DB::get_summary( $homework_id );
		$back = add_query_arg( 'class_id', $class_id, home_url( '/panel/homework/' ) );

		ob_start();
		?>
		<div class="hs-hw-tracking">
			<div class="hs-panel-header" style="margin-bottom:1rem;">
				<div>
					<a href="<?php echo esc_url( $back ); ?>" class="hs-back-link">← بازگشت به لیست تکالیف</a>
					<h2 style="margin:0.25rem 0;"><?php echo esc_html( $hw['title'] ); ?></h2>
					<p class="hs-muted" style="margin:0;">
						<?php if ( $hw['subject'] ) echo esc_html( $hw['subject'] ) . ' · '; ?>
						<?php if ( $hw['due_date'] ) echo 'مهلت: ' . esc_html( $hw['due_date'] ); else echo 'بدون مهلت'; ?>
					</p>
					<?php foreach ( HS_Homework_DB::attachments( $hw ) as $att ) : ?>
						<a class="hs-pill" style="margin:0.4rem 0 0 0.3rem;display:inline-block" href="<?php echo esc_url( $att['url'] ); ?>" target="_blank" rel="noopener">📎 <?php echo esc_html( $att['name'] ); ?></a>
					<?php endforeach; ?>
					<?php if ( $hw['description'] ) : ?>
						<p style="margin:0.5rem 0 0;font-size:0.95rem;"><?php echo esc_html( $hw['description'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( empty( $students ) ) : ?>
				<div class="hs-empty-state"><p>دانش‌آموزی در کلاس نیست.</p></div>
			<?php else : ?>

			<div class="hs-att-bulk hs-card">
				<span class="hs-att-bulk-label">اعمال برای همه:</span>
				<?php foreach ( $status_defs as $key => $def ) : ?>
					<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-hw-bulk" data-status="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $def['label'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<form id="hs-form-hw-status">
				<div class="hs-card hs-assess-results-card">
					<div class="hs-assess-results-header">
						<span>دانش‌آموز</span>
						<span>وضعیت</span>
						<span>بازخورد</span>
					</div>
					<?php foreach ( $students as $student ) :
						$sid = $student->ID;
						$cur = isset( $statuses[ $sid ] ) ? $statuses[ $sid ] : null;
						$cur_status = $cur ? $cur['status'] : 'pending';
						$cur_fb = $cur ? $cur['feedback'] : '';
						?>
						<div class="hs-assess-result-row hs-hw-row" data-student-id="<?php echo esc_attr( $sid ); ?>">
							<div class="hs-assess-st-name">
								<?php echo HS_Student_Sheet::avatar( $student->ID, $student->display_name ); // phpcs:ignore ?>
								<strong><?php echo esc_html( $student->display_name ); ?></strong>
							</div>
							<div class="hs-assess-st-value">
								<div class="hs-assess-levels hs-hw-statuses">
									<?php foreach ( $status_defs as $key => $def ) : ?>
										<label class="hs-assess-level-btn hs-hw-status-btn <?php echo $cur_status === $key ? 'active' : ''; ?>"
											data-color="<?php echo esc_attr( $def['color'] ); ?>"
											style="<?php echo $cur_status === $key ? 'border-color:' . esc_attr( $def['color'] ) . ';background:' . esc_attr( $def['color'] ) . '18;' : ''; ?>">
											<input type="radio" name="status[<?php echo esc_attr( $sid ); ?>]"
												value="<?php echo esc_attr( $key ); ?>" <?php checked( $cur_status, $key ); ?>>
											<span><?php echo esc_html( $def['label'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="hs-assess-st-comment">
								<input type="text" class="hs-hw-feedback" value="<?php echo esc_attr( $cur_fb ); ?>" placeholder="بازخورد...">
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="hs-att-footer">
					<div class="hs-assess-summary" id="hs-hw-summary">
						<?php foreach ( $summary as $k => $cnt ) :
							if ( ! $cnt ) continue;
							$lab = isset( $status_defs[ $k ] ) ? $status_defs[ $k ]['label'] : $k;
							?>
							<span><?php echo esc_html( $lab ); ?>: <strong><?php echo (int) $cnt; ?></strong></span>
						<?php endforeach; ?>
					</div>
					<button type="submit" class="hs-btn hs-btn-primary hs-btn-lg" id="hs-hw-save">💾 ذخیره وضعیت</button>
				</div>
				<div class="hs-form-message" id="hs-hw-save-msg"></div>
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

HS_Homework_Frontend::init();
