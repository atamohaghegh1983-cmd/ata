<?php
/**
 * پنل استودیو محتوا — بارگذاری، استخراج، انتشار
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Content_Studio_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_content_studio', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای استفاده از استودیو محتوا وارد شوید.</div>';
		}
		if ( ! current_user_can( 'manage_options' ) && ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">دسترسی ندارید.</div>';
		}

		$projects = HS_Content_Studio_DB::list_projects( array(
			'user_id' => get_current_user_id(),
			'limit'   => 20,
		) );

		$grades = class_exists( 'HS_Worksheet_CPT' ) ? HS_Worksheet_CPT::grades() : array(
			'1' => 'اول', '2' => 'دوم', '3' => 'سوم',
		);
		$subjects = class_exists( 'HS_Worksheet_CPT' ) ? HS_Worksheet_CPT::subjects() : array(
			'farsi' => 'فارسی', 'math' => 'ریاضی',
		);

		ob_start();
		?>
		<div class="hs-studio" id="hs-studio">

			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← داشبورد</a>
					<h2><?php echo esc_html( HS_Hubs::title( 'content-studio', 'استودیو محتوا' ) ); ?></h2>
					<p class="hs-muted">آپلود PDF یا Word، استخراج متن، افزودن عکس و ویدیو، سپس انتشار صفحه</p>
				</div>
			</div>

			<div class="hs-studio-layout">

				<!-- ستون اصلی: ویزارد -->
				<div class="hs-studio-main">

					<!-- مرحله ۱: آپلود -->
					<div class="hs-card hs-studio-step" data-step="1">
						<div class="hs-studio-step-label">مرحله ۱ — بارگذاری فایل منبع</div>
						<div class="hs-studio-drop" id="hs-studio-drop">
							<input type="file" id="hs-studio-file" accept=".pdf,.docx,.txt,.jpg,.jpeg,.png,.webp,.mp4,.webm" hidden>
							<div class="hs-studio-drop-inner">
								<div class="hs-studio-drop-icon">📂</div>
								<p><strong>کلیک کنید یا فایل را بکشید</strong></p>
								<p class="hs-muted">PDF · Word (DOCX) · TXT · تصویر · ویدیو MP4</p>
							</div>
						</div>
						<div class="hs-form-message" id="hs-studio-upload-msg"></div>
						<div id="hs-studio-upload-progress" style="display:none;">
							<div class="hs-studio-progress"><div class="hs-studio-progress-bar" id="hs-studio-bar"></div></div>
						</div>
					</div>

					<!-- مرحله ۲: متن استخراج‌شده -->
					<div class="hs-card hs-studio-step" data-step="2">
						<div class="hs-studio-step-label">مرحله ۲ — متن و محتوا</div>
						<div class="hs-form-row">
							<label>عنوان صفحه / کاربرگ <span class="required">*</span></label>
							<input type="text" id="hs-studio-title" placeholder="مثلاً: تمرین نشانه ب — پایه اول">
						</div>
						<div class="hs-form-grid">
							<div class="hs-form-row">
								<label>پایه</label>
								<select id="hs-studio-grade">
									<option value="">—</option>
									<?php foreach ( $grades as $k => $v ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="hs-form-row">
								<label>درس</label>
								<select id="hs-studio-subject">
									<option value="">—</option>
									<?php foreach ( $subjects as $k => $v ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="hs-form-row">
							<label>موضوع</label>
							<input type="text" id="hs-studio-topic" placeholder="موضوع کوتاه">
						</div>
						<div class="hs-form-row">
							<label>متن استخراج‌شده (قابل ویرایش)</label>
							<textarea id="hs-studio-text" rows="12" placeholder="پس از آپلود PDF یا Word، متن اینجا ظاهر می‌شود..."></textarea>
						</div>
						<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-studio-to-html">تبدیل متن به HTML</button>
						<div class="hs-form-row" style="margin-top:0.75rem;">
							<label>پیش‌نمایش HTML</label>
							<textarea id="hs-studio-html" rows="6" placeholder="HTML صفحه..."></textarea>
						</div>
					</div>

					<!-- مرحله ۳: رسانه -->
					<div class="hs-card hs-studio-step" data-step="3">
						<div class="hs-studio-step-label">مرحله ۳ — عکس، ویدیو، کاربرگ</div>
						<button type="button" class="hs-btn hs-btn-sm hs-btn-primary" id="hs-studio-add-media">+ افزودن فایل رسانه</button>
						<input type="file" id="hs-studio-media-file" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.pdf" hidden>
						<ul class="hs-studio-media-list" id="hs-studio-media-list"></ul>
					</div>

					<!-- مرحله ۴: انتشار -->
					<div class="hs-card hs-studio-step" data-step="4">
						<div class="hs-studio-step-label">مرحله ۴ — انتشار</div>
						<div class="hs-form-row">
							<label>انتشار به‌صورت</label>
							<select id="hs-studio-publish-as">
								<option value="page">صفحه وردپرس</option>
								<option value="worksheet">کاربرگ همشاگردی</option>
							</select>
						</div>
						<div class="hs-studio-actions">
							<button type="button" class="hs-btn hs-btn-ghost" id="hs-studio-save">ذخیره پیش‌نویس</button>
							<button type="button" class="hs-btn hs-btn-primary hs-btn-lg" id="hs-studio-publish">✓ پایان و انتشار</button>
						</div>
						<div class="hs-form-message" id="hs-studio-msg"></div>
						<div id="hs-studio-result" style="display:none;" class="hs-studio-result">
							<p>صفحه منتشر شد.</p>
							<a href="#" id="hs-studio-view-link" class="hs-btn hs-btn-primary" target="_blank">مشاهده صفحه</a>
						</div>
					</div>

				</div>

				<!-- سایدبار پروژه‌ها -->
				<aside class="hs-studio-side">
					<div class="hs-card">
						<h3>پروژه‌های اخیر</h3>
						<?php if ( empty( $projects ) ) : ?>
							<p class="hs-muted">هنوز پروژه‌ای ندارید.</p>
						<?php else : ?>
							<ul class="hs-studio-project-list">
								<?php foreach ( $projects as $p ) : ?>
									<li>
										<strong><?php echo esc_html( $p['title'] ?: 'بدون عنوان' ); ?></strong>
										<small>
											<?php echo esc_html( $p['status'] === 'published' ? 'منتشرشده' : 'پیش‌نویس' ); ?>
											· <?php echo esc_html( mysql2date( 'Y/m/d', $p['updated_at'] ) ); ?>
										</small>
										<?php if ( ! empty( $p['published_post_id'] ) ) : ?>
											<a href="<?php echo esc_url( get_permalink( $p['published_post_id'] ) ); ?>" target="_blank">مشاهده</a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
					<div class="hs-card">
						<h3>راهنما</h3>
						<ol class="hs-studio-help">
							<li>فایل PDF یا Word را آپلود کنید تا متن استخراج شود.</li>
							<li>عنوان، پایه و درس را پر کنید و متن را ویرایش کنید.</li>
							<li>در صورت نیاز عکس، ویدیو یا PDF کاربرگ اضافه کنید.</li>
							<li>دکمه <strong>پایان و انتشار</strong> را بزنید تا صفحه ساخته شود.</li>
						</ol>
					</div>
				</aside>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

HS_Content_Studio_Frontend::init();
