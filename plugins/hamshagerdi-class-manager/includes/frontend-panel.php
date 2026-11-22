<?php
/**
 * رابط کاربری فرانت‌اند پنل کلاس‌ها
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Frontend_Panel {

	public static function init() {
		add_shortcode( 'hamshagerdi_classes', array( __CLASS__, 'render_classes_page' ) );
		add_shortcode( 'hamshagerdi_class_detail', array( __CLASS__, 'render_class_detail' ) );
		add_shortcode( 'hamshagerdi_join_class', array( __CLASS__, 'render_join_form' ) );

		// ریدایرکت هوشمند برای /panel/classes/
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_panel_routes' ) );
	}

	/**
	 * اگر صفحه panel/classes وجود داشته باشد و شورت‌کد نداشته باشد، محتوا را جایگزین می‌کند
	 */
	public static function maybe_handle_panel_routes() {
		// این بخش اختیاری است و می‌تواند بعداً با rewrite rule کامل‌تر شود
	}

	/**
	 * صفحه لیست کلاس‌ها + فرم ساخت
	 */
	public static function render_classes_page( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای مشاهده کلاس‌ها باید وارد شوید. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">ورود</a></div>';
		}

		if ( ! HS_Roles::can_create_class() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان و مدیران مدرسه در دسترس است.</div>';
		}

		$user_id = get_current_user_id();
		$is_admin = current_user_can( 'hs_manage_all_classes' );

		$args = array(
			'post_type'      => 'hs_class',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
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
		$class_id = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;

		ob_start();
		?>
		<div class="hs-panel-classes" id="hs-panel-classes">

			<?php if ( $class_id && HS_Roles::can_manage_class( $class_id ) ) : ?>
				<?php echo self::render_single_class( $class_id ); ?>
			<?php else : ?>

			<div class="hs-panel-header">
				<div>
					<h2>کلاس‌های من</h2>
					<p class="hs-muted">مدیریت کلاس‌ها، دانش‌آموزان و کد دعوت</p>
				</div>
				<button type="button" class="hs-btn hs-btn-primary" id="hs-btn-new-class">
					+ ساخت کلاس جدید
				</button>
			</div>

			<!-- فرم ساخت کلاس (مخفی) -->
			<div class="hs-card hs-create-form" id="hs-create-class-form" style="display:none;">
				<h3>ساخت کلاس جدید</h3>
				<form id="hs-form-create-class" class="hs-compact-form">
					<div class="hs-cf-grid">
						<label class="hs-cf w2">نام کلاس <span class="required">*</span>
							<input type="text" id="hs-class-title" name="title" placeholder="مثلاً: سوم الف" required maxlength="40"></label>
						<label class="hs-cf">پایه
							<select id="hs-class-grade" name="grade"><?php echo HS_Class_Meta::grade_options(); // phpcs:ignore ?></select></label>
						<label class="hs-cf">سال تحصیلی
							<input type="text" id="hs-class-year" name="year" value="<?php echo esc_attr( HS_Util::academic_year() ); ?>" maxlength="9" dir="ltr"></label>

						<label class="hs-cf w2">نام مدرسه <span class="required">*</span>
							<input type="text" id="hs-class-school" name="school" placeholder="مثلاً: دبستان شهید بهشتی" required></label>
						<label class="hs-cf">استان <span class="required">*</span>
							<select id="hs-class-province" name="province" required><?php echo HS_Util::province_options(); // phpcs:ignore ?></select></label>
						<label class="hs-cf">شهر <span class="required">*</span>
							<input type="text" id="hs-class-city" name="city" placeholder="مثلاً: شیراز" required></label>

						<label class="hs-cf w4">نشانی مدرسه
							<input type="text" name="school_address" placeholder="خیابان، کوچه، پلاک (اختیاری)"></label>

						<label class="hs-cf w2">نام مدیر مدرسه
							<input type="text" name="manager_name" placeholder="اختیاری"></label>
						<label class="hs-cf w2">موبایل مدیر مدرسه
							<input type="tel" name="manager_mobile" dir="ltr" inputmode="tel" placeholder="09…  (اختیاری)" maxlength="13"></label>
						<p class="hs-cf-hint w4">وقتی مدیر با همین شماره وارد همشاگردی شود، این کلاس در پنلش نمایش داده می‌شود و با تأیید او به مدرسه وصل می‌شود.</p>

						<label class="hs-cf w4">توضیحات
							<textarea id="hs-class-desc" name="description" rows="2" placeholder="اختیاری"></textarea></label>
					</div>
					<div class="hs-form-actions">
						<button type="submit" class="hs-btn hs-btn-primary">ساخت کلاس</button>
						<button type="button" class="hs-btn hs-btn-ghost" id="hs-btn-cancel-create">انصراف</button>
					</div>
					<div class="hs-form-message" id="hs-create-message"></div>
				</form>
			</div>

			<!-- لیست کلاس‌ها -->
			<?php if ( $query->have_posts() ) : ?>
				<div class="hs-classes-grid">
					<?php while ( $query->have_posts() ) : $query->the_post();
						$cid     = get_the_ID();
						$grade   = get_post_meta( $cid, '_hs_grade', true );
						$year    = get_post_meta( $cid, '_hs_year', true );
						$invite  = get_post_meta( $cid, '_hs_invite_code', true );
						$school_line = trim( implode( '، ', array_filter( array( get_post_meta( $cid, '_hs_school', true ), get_post_meta( $cid, '_hs_city', true ), get_post_meta( $cid, '_hs_province', true ) ) ) ) );
						$count   = HS_Class_Students::count( $cid );
						$detail_url = add_query_arg( 'class_id', $cid, get_permalink() );
						?>
						<article class="hs-class-card">
							<div class="hs-class-card-header">
								<h3><a href="<?php echo esc_url( $detail_url ); ?>"><?php the_title(); ?></a></h3>
								<?php if ( $grade ) : ?>
									<span class="hs-badge"><?php echo esc_html( $grade ); ?></span>
								<?php endif; ?>
							</div>
							<div class="hs-class-card-body">
								<div class="hs-class-meta">
									<span>👥 <?php echo (int) $count; ?> دانش‌آموز</span>
									<?php if ( $year ) : ?>
										<span>📅 <?php echo esc_html( $year ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( $school_line ) : ?>
									<div class="hs-class-school">🏫 <?php echo esc_html( $school_line ); ?></div>
								<?php endif; ?>
								<?php if ( $invite ) : ?>
									<div class="hs-invite-box">
										<span class="hs-invite-label">کد دعوت:</span>
										<code class="hs-invite-code" data-code="<?php echo esc_attr( $invite ); ?>"><?php echo esc_html( $invite ); ?></code>
										<button type="button" class="hs-btn-copy" title="کپی کد">📋</button>
										<button type="button" class="hs-qr-open" data-url="<?php echo esc_url( home_url( '/join/' . $invite . '/' ) ); ?>" data-title="<?php echo esc_attr( get_the_title() ); ?>" data-code="<?php echo esc_attr( $invite ); ?>" title="کیوآر کد کلاس">▦ QR</button>
									</div>
								<?php endif; ?>
							</div>
							<div class="hs-class-card-footer">
								<a href="<?php echo esc_url( $detail_url ); ?>" class="hs-btn hs-btn-sm hs-btn-primary">مدیریت کلاس</a>
							</div>
						</article>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">👥</div>
					<h3>هنوز کلاسی نساخته‌اید</h3>
					<p>اولین کلاس خود را بسازید و دانش‌آموزان را اضافه کنید.</p>
					<button type="button" class="hs-btn hs-btn-primary" id="hs-btn-new-class-empty">+ ساخت اولین کلاس</button>
				</div>
			<?php endif; ?>

			<?php endif; // end if class_id ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * نمایش جزئیات یک کلاس
	 */
	public static function render_single_class( $class_id ) {
		$post = get_post( $class_id );
		if ( ! $post || $post->post_type !== 'hs_class' ) {
			return '<div class="hs-alert hs-alert-error">کلاس یافت نشد.</div>';
		}

		$grade   = get_post_meta( $class_id, '_hs_grade', true );
		$year    = get_post_meta( $class_id, '_hs_year', true );
		$invite  = get_post_meta( $class_id, '_hs_invite_code', true );
		$desc    = get_post_meta( $class_id, '_hs_description', true );
		$school  = get_post_meta( $class_id, '_hs_school', true );
		$city    = get_post_meta( $class_id, '_hs_city', true );
		$province = get_post_meta( $class_id, '_hs_province', true );
		$students = HS_Class_Students::get_students( $class_id );
		$back_url = remove_query_arg( 'class_id' );

		ob_start();
		?>
		<div class="hs-class-detail" data-class-id="<?php echo esc_attr( $class_id ); ?>">

			<div class="hs-panel-header">
				<div>
					<a href="<?php echo esc_url( $back_url ); ?>" class="hs-back-link">← بازگشت به لیست کلاس‌ها</a>
					<h2><?php echo esc_html( $post->post_title ); ?></h2>
					<div class="hs-class-meta-line">
						<?php if ( $grade ) : ?><span class="hs-badge"><?php echo esc_html( $grade ); ?></span><?php endif; ?>
						<?php if ( $year ) : ?><span class="hs-muted"><?php echo esc_html( $year ); ?></span><?php endif; ?>
					</div>
				</div>
			</div>

			<div class="hs-detail-grid">
				<!-- ستون اصلی -->
				<div class="hs-detail-main">

					<!-- کد دعوت -->
					<div class="hs-card">
						<h3>کد دعوت کلاس</h3>
						<p class="hs-muted">این کد را برای دانش‌آموزان یا والدین بفرستید تا به کلاس بپیوندند.</p>
						<div class="hs-invite-large">
							<code id="hs-invite-display"><?php echo esc_html( $invite ); ?></code>
							<button type="button" class="hs-btn hs-btn-sm hs-btn-copy" data-code="<?php echo esc_attr( $invite ); ?>">کپی کد</button>
							<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-btn-regen-code">تولید کد جدید</button>
						</div>
					</div>

					<!-- لیست دانش‌آموزان -->
					<div class="hs-card">
						<div class="hs-card-header-row">
							<h3>دانش‌آموزان <span class="hs-count-badge" id="hs-student-count"><?php echo count( $students ); ?></span></h3>
							<div class="hs-card-actions">
								<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-import-open">📤 ورود از اکسل</button>
								<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-export-students" data-class-id="<?php echo esc_attr( $class_id ); ?>">📥 خروجی اکسل</button>
							</div>
						</div>

						<div class="hs-import-box" id="hs-import-box" hidden>
							<h4>ورود دانش‌آموزان از اکسل</h4>
							<p class="hs-muted">فایل xlsx یا csv با ستون‌های: شماره، نام، نام خانوادگی، نام والد، تلفن والد، تاریخ تولد (شمسی مثل ۱۳۹۵/۰۴/۱۲)، یادداشت. ردیف اول باید عنوان ستون‌ها باشد. دانش‌آموزانی که قبلاً در کلاس هستند دوباره ثبت نمی‌شوند.</p>
							<div class="hs-import-actions">
								<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-import-template">⬇️ دانلود فایل نمونه</button>
								<input type="file" id="hs-import-file" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
								<button type="button" class="hs-btn hs-btn-sm hs-btn-primary" id="hs-import-go">شروع ورود</button>
							</div>
							<div class="hs-form-message" id="hs-import-msg"></div>
							<div id="hs-import-result"></div>
						</div>

						<!-- فرم ثبت دانش‌آموز -->
						<form id="hs-form-add-student" class="hs-student-register-form">
							<div class="hs-form-grid">
								<div class="hs-form-row">
									<label>نام <span class="required">*</span></label>
									<input type="text" id="hs-st-first" required placeholder="نام">
								</div>
								<div class="hs-form-row">
									<label>نام خانوادگی <span class="required">*</span></label>
									<input type="text" id="hs-st-last" required placeholder="نام خانوادگی">
								</div>
							</div>
							<div class="hs-form-grid">
								<div class="hs-form-row">
									<label>نام و نام خانوادگی پدر</label>
									<input type="text" id="hs-st-parent" placeholder="اختیاری">
								</div>
								<div class="hs-form-row">
									<label>موبایل پدر</label>
									<input type="tel" id="hs-st-phone" placeholder="09…" dir="ltr" inputmode="tel" maxlength="13">
								</div>
							</div>
							<div class="hs-form-grid">
								<div class="hs-form-row">
									<label>نام و نام خانوادگی مادر</label>
									<input type="text" id="hs-st-parent2" placeholder="اختیاری">
								</div>
								<div class="hs-form-row">
									<label>موبایل مادر</label>
									<input type="tel" id="hs-st-phone2" placeholder="09…" dir="ltr" inputmode="tel" maxlength="13">
								</div>
							</div>
							<p class="hs-cf-hint">هر کدام از پدر یا مادر که با همین شماره در همشاگردی وارد شود، خودکار به این دانش‌آموز و کلاس وصل می‌شود.</p>
							<div class="hs-form-grid">
								<div class="hs-form-row">
									<label>شماره در کلاس</label>
									<input type="text" id="hs-st-number" placeholder="مثلاً ۱۲">
								</div>
								<div class="hs-form-row">
									<label>تاریخ تولد</label>
									<input type="date" id="hs-st-birth">
								</div>
							</div>
							<div class="hs-form-row">
								<label>عکس دانش‌آموز (اختیاری)</label>
								<?php echo HS_Upload::picker( 'hs-st-photo', 'student_photo', array( 'label' => 'انتخاب عکس از گالری/دوربین' ) ); // phpcs:ignore ?>
							</div>
							<div class="hs-form-actions">
								<button type="submit" class="hs-btn hs-btn-primary">+ ثبت دانش‌آموز</button>
							</div>
							<div class="hs-form-message" id="hs-add-student-message"></div>
						</form>

						<?php
						$profiles = HS_Student_Profile::list_for_class( $class_id );
						$statuses = HS_Student_Profile::statuses();
						// کلاس‌های معلم برای انتقال
						$teacher_classes = array();
						$tq = new WP_Query( array(
							'post_type' => 'hs_class', 'post_status' => 'publish', 'posts_per_page' => 50,
							'meta_query' => current_user_can( 'hs_manage_all_classes' ) ? array() : array(
								array( 'key' => '_hs_teacher_id', 'value' => get_current_user_id() ),
							),
						) );
						while ( $tq->have_posts() ) { $tq->the_post();
							if ( get_the_ID() != $class_id ) {
								$teacher_classes[] = array( 'id' => get_the_ID(), 'title' => get_the_title() );
							}
						}
						wp_reset_postdata();
						?>

						<?php if ( ! empty( $profiles ) ) : ?>
							<div class="hs-student-table-wrap">
								<table class="hs-report-table hs-student-table" id="hs-student-list">
									<thead>
										<tr>
											<th>#</th>
											<th>دانش‌آموز</th>
											<th>والد</th>
											<th>تلفن</th>
											<th>وضعیت</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $profiles as $p ) :
											$status_label = isset( $statuses[ $p['status'] ] ) ? $statuses[ $p['status'] ] : $p['status'];
											$initial = mb_substr( $p['display_name'], 0, 1 );
											?>
											<tr data-user-id="<?php echo esc_attr( $p['user_id'] ); ?>">
												<td><?php echo esc_html( $p['class_number'] ?: '—' ); ?></td>
												<td class="hs-report-name">
													<div class="hs-assess-st-name">
														<?php if ( $p['photo_url'] ) : ?>
															<img class="hs-student-photo" src="<?php echo esc_url( $p['photo_url'] ); ?>" alt="">
														<?php else : ?>
															<div class="hs-student-avatar"><?php echo esc_html( $initial ); ?></div>
														<?php endif; ?>
														<strong><?php echo esc_html( $p['display_name'] ); ?></strong>
													</div>
												</td>
												<td><?php echo esc_html( $p['parent_name'] ?: '—' ); ?></td>
												<td dir="ltr"><?php echo esc_html( $p['parent_phone'] ?: '—' ); ?></td>
												<td><span class="hs-status-pill hs-status-<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
												<td>
													<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-st-edit" data-user-id="<?php echo esc_attr( $p['user_id'] ); ?>">پرونده</button>
													<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-st-remove" title="حذف از کلاس" data-user-id="<?php echo esc_attr( $p['user_id'] ); ?>" data-name="<?php echo esc_attr( $p['display_name'] ); ?>" style="color:#c0392b">🗑 حذف</button>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php else : ?>
							<div class="hs-empty-students" id="hs-empty-students">
								<p>هنوز دانش‌آموزی ثبت نشده.</p>
							</div>
							<div class="hs-student-table-wrap" style="display:none;">
								<table class="hs-report-table hs-student-table" id="hs-student-list"><tbody></tbody></table>
							</div>
						<?php endif; ?>
					</div>

					<!-- مودال پرونده دانش‌آموز -->
					<div class="hs-report-modal" id="hs-st-modal" style="display:none;">
						<div class="hs-report-modal-backdrop" id="hs-st-modal-close"></div>
						<div class="hs-report-modal-box" style="max-width:520px;">
							<button type="button" class="hs-report-modal-x" id="hs-st-modal-x">×</button>
							<h3 style="margin-top:0;">پرونده دانش‌آموز</h3>
							<form id="hs-form-st-profile">
								<input type="hidden" id="hs-st-edit-id" value="">
								<div class="hs-form-grid">
									<div class="hs-form-row"><label>نام</label><input type="text" id="hs-ed-first"></div>
									<div class="hs-form-row"><label>نام خانوادگی</label><input type="text" id="hs-ed-last"></div>
								</div>
								<div class="hs-form-grid">
									<div class="hs-form-row"><label>نام والد</label><input type="text" id="hs-ed-parent"></div>
									<div class="hs-form-row"><label>تلفن والد</label><input type="text" id="hs-ed-phone" dir="ltr"></div>
								</div>
								<div class="hs-form-grid">
									<div class="hs-form-row"><label>شماره کلاس</label><input type="text" id="hs-ed-number"></div>
									<div class="hs-form-row"><label>تاریخ تولد</label><input type="date" id="hs-ed-birth"></div>
								</div>
								<div class="hs-form-row"><label>عکس دانش‌آموز</label><?php echo HS_Upload::picker( 'hs-ed-photo', 'student_photo', array( 'label' => 'تغییر عکس' ) ); // phpcs:ignore ?></div>
								<div class="hs-form-row">
									<label>وضعیت ثبت‌نام</label>
									<select id="hs-ed-status">
										<?php foreach ( $statuses as $sk => $sl ) : ?>
											<option value="<?php echo esc_attr( $sk ); ?>"><?php echo esc_html( $sl ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="hs-form-row"><label>یادداشت</label><textarea id="hs-ed-notes" rows="2"></textarea></div>
								<div class="hs-form-row"><label>کد اتصال والد (به والد بدهید)</label>
									<div style="display:flex;gap:0.5rem;"><input type="text" id="hs-ed-pcode" readonly style="flex:1;font-family:monospace;letter-spacing:2px;"><button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-ed-pcode-regen">کد جدید</button></div>
								</div>
								<?php if ( ! empty( $teacher_classes ) ) : ?>
								<div class="hs-form-row">
									<label>انتقال به کلاس دیگر</label>
									<div style="display:flex;gap:0.5rem;">
										<select id="hs-ed-transfer" style="flex:1;">
											<option value="">— انتخاب کلاس —</option>
											<?php foreach ( $teacher_classes as $tc ) : ?>
												<option value="<?php echo esc_attr( $tc['id'] ); ?>"><?php echo esc_html( $tc['title'] ); ?></option>
											<?php endforeach; ?>
										</select>
										<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-st-transfer-btn">انتقال</button>
									</div>
								</div>
								<?php endif; ?>
								<div class="hs-form-actions">
									<button type="submit" class="hs-btn hs-btn-primary">ذخیره پرونده</button>
								</div>
								<div class="hs-form-message" id="hs-st-profile-msg"></div>
							</form>
						</div>
					</div>
				</div>

				<!-- سایدبار تنظیمات -->
				<div class="hs-detail-side">
					<div class="hs-card">
						<h3>تنظیمات کلاس</h3>
						<form id="hs-form-update-class">
							<div class="hs-form-row">
								<label>نام کلاس</label>
								<input type="text" name="title" value="<?php echo esc_attr( $post->post_title ); ?>">
							</div>
							<div class="hs-form-row">
								<label>پایه</label>
								<select name="grade">
									<option value="">—</option>
									<?php
									$grades = HS_Class_Meta::grades();
									foreach ( $grades as $g ) {
										printf( '<option value="%s" %s>%s</option>', esc_attr( $g ), selected( $grade, $g, false ), esc_html( $g ) );
									}
									?>
								</select>
							</div>
							<div class="hs-form-row">
								<label>سال تحصیلی</label>
								<input type="text" name="year" value="<?php echo esc_attr( $year ); ?>">
							</div>
							<div class="hs-form-row">
								<label>نام مدرسه</label>
								<input type="text" name="school" value="<?php echo esc_attr( $school ); ?>">
							</div>
							<div class="hs-form-row">
								<label>استان</label>
								<select name="province"><?php echo HS_Util::province_options( $province ); // phpcs:ignore ?></select>
							</div>
							<div class="hs-form-row">
								<label>شهر</label>
								<input type="text" name="city" value="<?php echo esc_attr( $city ); ?>">
							</div>
							<div class="hs-form-row">
								<label>توضیحات</label>
								<textarea name="description" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
							</div>
							<button type="submit" class="hs-btn hs-btn-primary hs-btn-block">ذخیره تغییرات</button>
							<div class="hs-form-message" id="hs-update-message"></div>
						</form>
					</div>

					<div class="hs-card hs-danger-zone">
						<h3>منطقه خطر</h3>
						<p class="hs-muted">حذف کلاس غیرقابل بازگشت است.</p>
						<button type="button" class="hs-btn hs-btn-danger hs-btn-block" id="hs-btn-delete-class">حذف کلاس</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * شورت‌کد فرم پیوستن با کد دعوت (برای دانش‌آموز/والد)
	 */
	public static function render_join_form( $atts = array() ) {
		ob_start();
		?>
		<div class="hs-join-box">
			<h3>پیوستن به کلاس</h3>
			<p>کد دعوت ۶ حرفی را که معلم داده وارد کنید.</p>
			<form id="hs-form-join-class">
				<input type="text" name="code" id="hs-join-code" placeholder="مثلاً: A7K3M9" maxlength="8" style="text-transform:uppercase;letter-spacing:2px;font-size:1.2em;text-align:center;">
				<button type="submit" class="hs-btn hs-btn-primary">پیوستن به کلاس</button>
			</form>
			<div class="hs-form-message" id="hs-join-message"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * شورت‌کد جزئیات کلاس (اگر بخواهند جداگانه استفاده کنند)
	 */
	public static function render_class_detail( $atts = array() ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id = absint( $atts['id'] ?: ( isset( $_GET['class_id'] ) ? $_GET['class_id'] : 0 ) );
		if ( ! $id || ! HS_Roles::can_manage_class( $id ) ) {
			return '<div class="hs-alert">دسترسی غیرمجاز یا کلاس نامعتبر.</div>';
		}
		return self::render_single_class( $id );
	}
}

HS_Frontend_Panel::init();
