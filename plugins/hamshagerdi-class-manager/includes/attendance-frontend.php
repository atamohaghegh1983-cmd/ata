<?php
/**
 * رابط کاربری فرانت‌اند حضور و غیاب
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Attendance_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_attendance', array( __CLASS__, 'render' ) );
	}

	/**
	 * شورت‌کد اصلی: [hamshagerdi_attendance]
	 */
	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای ثبت حضور و غیاب باید وارد شوید. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">ورود</a></div>';
		}

		if ( ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان و مدیران مدرسه در دسترس است.</div>';
		}

		$user_id  = get_current_user_id();
		$is_admin = current_user_can( 'hs_manage_all_classes' );

		// لیست کلاس‌های معلم
		$args = array(
			'post_type'      => 'hs_class',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! $is_admin ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_hs_teacher_id',
					'value' => $user_id,
				),
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

		// کلاس پیش‌فرض از query string یا اولین کلاس
		$selected_class = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;
		if ( ! $selected_class && ! empty( $classes ) ) {
			$selected_class = $classes[0]['id'];
		}

		// تاریخ پیش‌فرض: امروز
		$selected_date = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : date( 'Y-m-d' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
			$selected_date = date( 'Y-m-d' );
		}

		// دانش‌آموزان کلاس انتخاب‌شده
		$students = array();
		$existing = array();
		$is_recorded = false;
		if ( $selected_class && HS_Roles::can_manage_class( $selected_class ) ) {
			$students    = HS_Class_Students::get_students( $selected_class );
			$existing    = HS_Attendance_DB::get_by_class_date( $selected_class, $selected_date );
			$is_recorded = HS_Attendance_DB::is_recorded( $selected_class, $selected_date );
		}

		$status_labels = array(
			'present' => 'حاضر',
			'absent'  => 'غایب',
			'late'    => 'تأخیر',
			'excused' => 'موجه',
		);
		// آیکن هر وضعیت (داخل دایره)
		// گرافیک قلم‌مویی (پُر، با سرهای گرد و ضخامت متغیر)
		$status_icons = array(
			'present' => '<path class="f" d="M3.9 12.6c1-1.1 2.5-1.2 3.6-.3l2.6 2.3 7.6-9.1c.9-1.1 2.5-1.3 3.5-.4 1 .8 1.1 2.2.4 3.2l-9.3 11.4c-.8 1-2.3 1.1-3.3.3l-4.9-4.1c-1-.9-1.1-2.3-.2-3.3z"/>',
			'absent'  => '<path class="f" d="M5.4 4.9c.9-.9 2.3-.9 3.2 0L12 8.6l3.6-3.8c.9-.9 2.3-1 3.3-.1.9.9 1 2.3.1 3.3l-3.7 3.9 3.6 3.9c.9.9.8 2.4-.1 3.2-.9.9-2.4.8-3.2-.1L12 15.1l-3.5 3.8c-.9.9-2.3 1-3.2.1-1-.9-1-2.3-.1-3.2l3.6-3.9-3.5-3.8c-.9-1-.9-2.4.1-3.2z"/>',
			'late'    => '<path class="f" fill-rule="evenodd" d="M12 3.2a8.8 8.8 0 1 1 0 17.6 8.8 8.8 0 0 1 0-17.6zm0 3a5.8 5.8 0 1 0 0 11.6 5.8 5.8 0 0 0 0-11.6z"/><path class="f" d="M11.9 7.9c.8 0 1.4.6 1.4 1.4v2.3l1.9 1.3c.6.4.8 1.3.3 1.9-.4.6-1.3.8-1.9.3l-2.5-1.8c-.4-.3-.6-.7-.6-1.1V9.3c0-.8.6-1.4 1.4-1.4z"/>',
			'excused' => '<path class="f" d="M10.4 4.2h3.2c.8 0 1.4.6 1.4 1.4V9h3.4c.8 0 1.4.6 1.4 1.4v3.2c0 .8-.6 1.4-1.4 1.4H15v3.4c0 .8-.6 1.4-1.4 1.4h-3.2c-.8 0-1.4-.6-1.4-1.4V15H5.6c-.8 0-1.4-.6-1.4-1.4v-3.2c0-.8.6-1.4 1.4-1.4H9V5.6c0-.8.6-1.4 1.4-1.4z"/>',
		);
		$sort_mode = $selected_class ? HS_Class_Students::sort_mode( $selected_class ) : 'first';

		ob_start();
		?>
		<div class="hs-attendance-panel" id="hs-attendance-panel"
			 data-class-id="<?php echo esc_attr( $selected_class ); ?>"
			 data-date="<?php echo esc_attr( $selected_date ); ?>">

			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2>حضور و غیاب</h2>
					<p class="hs-muted">ثبت روزانه حضور، غیبت، تأخیر و غیبت موجه</p>
				</div>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">👥</div>
					<h3>هنوز کلاسی ندارید</h3>
					<p>ابتدا یک کلاس بسازید و دانش‌آموزان را اضافه کنید.</p>
					<a href="<?php echo esc_url( home_url( '/panel/classes/' ) ); ?>" class="hs-btn hs-btn-primary">+ ساخت کلاس</a>
				</div>
			<?php else : ?>

			<!-- نوار بالا: کلاس، هفته، عملیات سریع -->
			<div class="hs-att-bar hs-card">
				<div class="hs-att-bar-row">
					<label class="hs-att-class">
						<span class="screen-reader-text">کلاس</span>
						<select id="hs-att-class" name="class_id">
							<?php foreach ( $classes as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected_class, $c['id'] ); ?>><?php echo esc_html( $c['title'] ); ?> · <?php echo esc_html( HS_Util::fa_digits( (int) $c['count'] ) ); ?> نفر</option>
							<?php endforeach; ?>
						</select>
					</label>
					<div class="hs-week" data-target="#hs-att-date" aria-label="انتخاب روز">
						<button type="button" class="hs-week-nav" data-week="-1" aria-label="هفته‌ی قبل">›</button>
						<div class="hs-week-days" role="listbox"></div>
						<button type="button" class="hs-week-nav" data-week="1" aria-label="هفته‌ی بعد">‹</button>
						<button type="button" class="hs-week-today">امروز</button>
					</div>
					<input type="hidden" id="hs-att-date" name="date" value="<?php echo esc_attr( $selected_date ); ?>">
					<button type="button" id="hs-att-load" hidden>نمایش</button>
				</div>
				<?php if ( $selected_class && ! empty( $students ) ) : ?>
				<div class="hs-att-bar-row hs-att-bar-sub">
					<span class="hs-week-label"></span>
					<?php if ( $is_recorded ) : ?><span class="hs-att-recorded">✓ ثبت شده</span><?php endif; ?>
					<div class="hs-att-quick">
						<button type="button" class="hs-chip" data-bulk="present">همه حاضر</button>
						<button type="button" class="hs-chip" data-bulk="absent">همه غایب</button>
						<button type="button" class="hs-chip" data-bulk="clear">پاک کردن</button>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( $selected_class && empty( $students ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">👤</div>
					<h3>دانش‌آموزی در این کلاس نیست</h3>
					<p>از صفحه مدیریت کلاس، دانش‌آموز اضافه کنید.</p>
					<a href="<?php echo esc_url( add_query_arg( 'class_id', $selected_class, home_url( '/panel/classes/' ) ) ); ?>" class="hs-btn hs-btn-primary">مدیریت کلاس</a>
				</div>
			<?php elseif ( $selected_class && ! empty( $students ) ) : ?>

			<!-- ترتیب نمایش -->
			<div class="hs-att-sortbar hs-card" data-class-id="<?php echo esc_attr( $selected_class ); ?>">
				<span class="hs-att-bulk-label">ترتیب نمایش:</span>
				<div class="hs-seg" role="radiogroup" aria-label="ترتیب نمایش دانش‌آموزان">
					<?php foreach ( array( 'first' => 'نام', 'last' => 'نام خانوادگی', 'custom' => 'دلخواه' ) as $k => $l ) : ?>
						<label class="<?php echo $sort_mode === $k ? 'active' : ''; ?>"><input type="radio" name="hs_sort" value="<?php echo esc_attr( $k ); ?>" <?php checked( $sort_mode, $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</div>
				<span class="hs-att-legend" aria-hidden="true">
					<?php foreach ( $status_labels as $key => $label ) : ?><span class="status-<?php echo esc_attr( $key ); ?>"><svg viewBox="0 0 24 24"><?php echo $status_icons[ $key ]; // phpcs:ignore ?></svg><?php echo esc_html( $label ); ?></span><?php endforeach; ?>
				</span>
				<span class="hs-att-sort-hint" <?php echo 'custom' === $sort_mode ? '' : 'hidden'; ?>>⇅ ردیف‌ها را با کشیدن دستگیره یا دکمه‌های ▲▼ جابه‌جا کنید؛ خودکار ذخیره می‌شود.</span>
			</div>

			<!-- لیست دانش‌آموزان -->
			<form id="hs-att-form" class="hs-att-form">
				<div class="hs-att-list hs-card<?php echo 'custom' === $sort_mode ? ' is-sorting' : ''; ?>">
					<div class="hs-att-list-header">
						<span class="hs-att-col-name">نام دانش‌آموز</span>
						<span class="hs-att-col-status">وضعیت</span>
						<span class="hs-att-col-note">یادداشت</span>
					</div>

					<?php foreach ( $students as $student ) :
						$sid    = $student->ID;
						$cur    = isset( $existing[ $sid ] ) ? $existing[ $sid ]['status'] : 'present';
						$note   = isset( $existing[ $sid ] ) ? $existing[ $sid ]['note'] : '';
						?>
						<div class="hs-att-row" data-student-id="<?php echo esc_attr( $sid ); ?>">
							<div class="hs-att-col-name">
								<span class="hs-att-handle" draggable="true" title="کشیدن برای جابه‌جایی" aria-hidden="true">⋮⋮</span>
								<span class="hs-att-move"><button type="button" data-move="-1" aria-label="بالاتر">▲</button><button type="button" data-move="1" aria-label="پایین‌تر">▼</button></span>
								<?php echo HS_Student_Sheet::name_button( $selected_class, $student ); // phpcs:ignore ?>
							</div>
							<div class="hs-att-col-status">
								<div class="hs-att-status-group hs-att-icons" role="radiogroup" aria-label="<?php echo esc_attr( 'وضعیت ' . $student->display_name ); ?>">
									<?php foreach ( $status_labels as $key => $label ) : ?>
										<label class="hs-att-status-btn status-<?php echo esc_attr( $key ); ?> <?php echo $cur === $key ? 'active' : ''; ?>" title="<?php echo esc_attr( $label ); ?>">
											<input type="radio"
												   name="status[<?php echo esc_attr( $sid ); ?>]"
												   value="<?php echo esc_attr( $key ); ?>"
												   aria-label="<?php echo esc_attr( $label ); ?>"
												   <?php checked( $cur, $key ); ?>>
											<svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $status_icons[ $key ]; // phpcs:ignore ?></svg>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="hs-att-col-note">
								<input type="text"
									   name="note[<?php echo esc_attr( $sid ); ?>]"
									   value="<?php echo esc_attr( $note ); ?>"
									   placeholder="اختیاری..."
									   class="hs-att-note-input">
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- خلاصه و ذخیره -->
				<div class="hs-att-footer">
					<div class="hs-att-summary" id="hs-att-summary">
						<span class="sum-present">حاضر: <strong>0</strong></span>
						<span class="sum-absent">غایب: <strong>0</strong></span>
						<span class="sum-late">تأخیر: <strong>0</strong></span>
						<span class="sum-excused">موجه: <strong>0</strong></span>
					</div>
					<button type="submit" class="hs-btn hs-btn-primary hs-btn-lg" id="hs-att-save">
						💾 ذخیره حضور و غیاب
					</button>
				</div>
				<div class="hs-form-message" id="hs-att-message"></div>
			</form>

			<!-- گزارش ماهانه (ساده) -->
			<div class="hs-att-report-section">
				<button type="button" class="hs-btn hs-btn-ghost" id="hs-att-toggle-report">
					📊 گزارش ماهانه این کلاس
				</button>
				<div id="hs-att-monthly-report" class="hs-card" style="display:none;margin-top:1rem;">
					<div class="hs-att-report-loading">در حال بارگذاری...</div>
					<div class="hs-att-report-content" style="display:none;"></div>
				</div>
			</div>

			<?php echo HS_Student_Sheet::render( $selected_class ); // phpcs:ignore ?>
			<?php endif; // students ?>
			<?php endif; // classes ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

HS_Attendance_Frontend::init();
