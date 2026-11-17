<?php
/**
 * رابط کاربری گزارش پیشرفت
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Reports_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_reports', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای مشاهده گزارش باید وارد شوید.</div>';
		}
		if ( ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان در دسترس است.</div>';
		}

		$classes = self::teacher_classes();
		$selected = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;
		if ( ! $selected && ! empty( $classes ) ) {
			$selected = $classes[0]['id'];
		}

		$year  = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) date( 'Y' );
		$month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : (int) date( 'n' );

		ob_start();
		?>
		<div class="hs-reports-panel" id="hs-reports-panel"
			 data-class-id="<?php echo esc_attr( $selected ); ?>"
			 data-year="<?php echo esc_attr( $year ); ?>"
			 data-month="<?php echo esc_attr( $month ); ?>">

			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2>گزارش پیشرفت</h2>
					<p class="hs-muted">نمای یکپارچه حضور، امتیاز و ارزیابی دانش‌آموزان</p>
				</div>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">📊</div>
					<h3>هنوز کلاسی ندارید</h3>
					<a href="<?php echo esc_url( home_url( '/panel/classes/' ) ); ?>" class="hs-btn hs-btn-primary">+ ساخت کلاس</a>
				</div>
			<?php else : ?>

			<div class="hs-card hs-att-filters">
				<div class="hs-att-filter-row" style="grid-template-columns:1fr 140px 140px auto;">
					<div class="hs-form-row">
						<label>کلاس</label>
						<select id="hs-report-class">
							<?php foreach ( $classes as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected, $c['id'] ); ?>>
									<?php echo esc_html( $c['title'] ); ?>
									<?php if ( $c['grade'] ) echo ' (' . esc_html( $c['grade'] ) . ')'; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row">
						<label>ماه</label>
						<select id="hs-report-month">
							<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
								<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $month, $m ); ?>>
									<?php echo esc_html( HS_Reports::month_label( $m ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
					</div>
					<div class="hs-form-row">
						<label>سال</label>
						<select id="hs-report-year">
							<?php
							$cy = (int) date( 'Y' );
							for ( $y = $cy; $y >= $cy - 2; $y-- ) :
								?>
								<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $year, $y ); ?>><?php echo esc_html( $y ); ?></option>
							<?php endfor; ?>
						</select>
					</div>
					<div class="hs-form-row hs-att-filter-actions">
						<label>&nbsp;</label>
						<button type="button" class="hs-btn hs-btn-primary" id="hs-report-load">نمایش</button>
					</div>
				</div>
			</div>

			<?php
			if ( $selected && HS_Roles::can_manage_class( $selected ) ) {
				$overview = HS_Reports::class_overview( $selected, $year, $month );
				$progress = HS_Reports::students_progress( $selected, $year, $month );
				echo self::render_overview( $overview );
				echo self::render_table( $progress, $selected );
			}
			?>
			<?php endif; ?>

			<!-- مودال گزارش دانش‌آموز -->
			<div class="hs-report-modal" id="hs-report-modal" style="display:none;">
				<div class="hs-report-modal-backdrop" id="hs-report-modal-close"></div>
				<div class="hs-report-modal-box">
					<button type="button" class="hs-report-modal-x" id="hs-report-modal-x">×</button>
					<div id="hs-report-modal-content">
						<p class="hs-muted">در حال بارگذاری...</p>
					</div>
					<div class="hs-report-modal-actions">
						<button type="button" class="hs-btn hs-btn-ghost" id="hs-report-print">چاپ / ذخیره PDF</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_overview( $o ) {
		ob_start();
		?>
		<div class="dash-stats hs-report-stats">
			<div class="dash-stat">
				<div class="dash-stat-number"><?php echo (int) $o['student_count']; ?></div>
				<div class="dash-stat-label">دانش‌آموز</div>
			</div>
			<div class="dash-stat">
				<div class="dash-stat-number">
					<?php echo $o['attendance_rate'] !== null ? (int) $o['attendance_rate'] . '%' : '—'; ?>
				</div>
				<div class="dash-stat-label">نرخ حضور ماه</div>
			</div>
			<div class="dash-stat">
				<div class="dash-stat-number"><?php echo (int) $o['recorded_days']; ?></div>
				<div class="dash-stat-label">روز ثبت‌شده</div>
			</div>
			<div class="dash-stat">
				<div class="dash-stat-number"><?php echo esc_html( $o['points_avg'] ); ?></div>
				<div class="dash-stat-label">میانگین امتیاز</div>
			</div>
			<div class="dash-stat">
				<div class="dash-stat-number"><?php echo (int) $o['assessment_count']; ?></div>
				<div class="dash-stat-label">ارزیابی اخیر</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_table( $progress, $class_id ) {
		$students = $progress['students'];
		$assessments = $progress['assessments'];

		ob_start();
		?>
		<div class="hs-card">
			<h3>پیشرفت دانش‌آموزان</h3>
			<?php if ( empty( $students ) ) : ?>
				<p class="hs-muted">دانش‌آموزی در این کلاس نیست.</p>
			<?php else : ?>
				<div class="hs-report-table-wrap">
					<table class="hs-report-table">
						<thead>
							<tr>
								<th>#</th>
								<th>نام</th>
								<th>امتیاز</th>
								<th>حضور</th>
								<th>غیبت</th>
								<th>تأخیر</th>
								<th>نرخ حضور</th>
								<?php foreach ( array_slice( $assessments, 0, 3 ) as $a ) : ?>
									<th title="<?php echo esc_attr( $a['title'] ); ?>">
										<?php echo esc_html( mb_substr( $a['title'], 0, 12 ) ); ?>
										<?php if ( mb_strlen( $a['title'] ) > 12 ) echo '…'; ?>
									</th>
								<?php endforeach; ?>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i = 1;
							foreach ( $students as $s ) :
								$rate_class = '';
								if ( $s['att_rate'] !== null ) {
									if ( $s['att_rate'] >= 90 ) {
										$rate_class = 'rate-good';
									} elseif ( $s['att_rate'] >= 75 ) {
										$rate_class = 'rate-mid';
									} else {
										$rate_class = 'rate-low';
									}
								}
								?>
								<tr>
									<td><?php echo $i++; ?></td>
									<td class="hs-report-name"><?php echo esc_html( $s['name'] ); ?></td>
									<td><strong><?php echo (int) $s['points']; ?></strong></td>
									<td><?php echo (int) $s['att_present']; ?></td>
									<td><?php echo (int) $s['att_absent']; ?></td>
									<td><?php echo (int) $s['att_late']; ?></td>
									<td class="<?php echo esc_attr( $rate_class ); ?>">
										<?php echo $s['att_rate'] !== null ? (int) $s['att_rate'] . '%' : '—'; ?>
									</td>
									<?php
									$shown = 0;
									foreach ( $s['assessments'] as $ai ) {
										if ( $shown >= 3 ) {
											break;
										}
										echo '<td>' . esc_html( $ai['value'] ) . '</td>';
										$shown++;
									}
									while ( $shown < min( 3, count( $assessments ) ) ) {
										echo '<td>—</td>';
										$shown++;
									}
									?>
									<td>
										<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-report-student-btn"
											data-student-id="<?php echo esc_attr( $s['id'] ); ?>">
											گزارش
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
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
				'grade' => get_post_meta( get_the_ID(), '_hs_grade', true ),
			);
		}
		wp_reset_postdata();
		return $out;
	}
}

HS_Reports_Frontend::init();
