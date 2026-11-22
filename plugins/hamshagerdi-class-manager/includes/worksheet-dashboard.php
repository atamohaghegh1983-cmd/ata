<?php
/**
 * داشبورد مدیریت کاربرگ — مخصوص مدیر سایت
 * آپلود فایل/عکس/متن/فیلم + تعیین عنوان و آدرس صفحه
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Worksheet_Dashboard {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_action( 'wp_ajax_hs_ws_dash_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_hs_ws_dash_upload', array( __CLASS__, 'ajax_upload' ) );
		add_action( 'wp_ajax_hs_ws_dash_delete', array( __CLASS__, 'ajax_delete' ) );
		add_action( 'wp_ajax_hs_ws_dash_get', array( __CLASS__, 'ajax_get' ) );

		add_shortcode( 'hamshagerdi_worksheet_dashboard', array( __CLASS__, 'render_front' ) );
	}

	private static function can_manage() {
		return current_user_can( 'manage_options' )
			|| current_user_can( 'hs_manage_all_classes' )
			|| ( class_exists( 'HS_Roles' ) && HS_Roles::is_teacher_or_admin() );
	}

	public static function menu() {
		// مدیر: منوی کامل — معلم: اگر قابلیت manage دارد از طریق پنل فرانت استفاده می‌کند
		$cap = current_user_can( 'manage_options' ) ? 'manage_options' : 'read';
		if ( ! current_user_can( 'manage_options' ) && ! self::can_manage() ) {
			return;
		}
		add_menu_page(
			'داشبورد کاربرگ',
			'داشبورد کاربرگ',
			$cap,
			'hs-worksheet-dashboard',
			array( __CLASS__, 'render_admin' ),
			'dashicons-welcome-learn-more',
			25
		);
	}

	public static function assets( $hook ) {
		if ( strpos( (string) $hook, 'hs-worksheet-dashboard' ) === false ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style(
			'hs-ws-dash',
			HS_CLASS_URL . 'assets/css/worksheet-dashboard.css',
			array( 'hs-core' ),
			HS_CLASS_VERSION
		);
		wp_enqueue_script(
			'hs-ws-dash',
			HS_CLASS_URL . 'assets/js/worksheet-dashboard.js',
			array( 'jquery' ),
			HS_CLASS_VERSION,
			true
		);
		wp_localize_script( 'hs-ws-dash', 'hsWsDash', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hs_ws_dash' ),
			'i18n'    => array(
				'saving'  => 'در حال ذخیره...',
				'saved'   => 'ذخیره و منتشر شد',
				'error'   => 'خطا رخ داد',
				'confirm' => 'این کاربرگ حذف شود؟',
				'needTitle' => 'عنوان الزامی است',
			),
		) );
	}

	public static function render_front() {
		if ( ! is_user_logged_in() || ! self::can_manage() ) {
			return '<div class="hs-alert hs-alert-warning">فقط مدیر و معلم به داشبورد کاربرگ دسترسی دارند.</div>';
		}
		ob_start();
		self::render_body();
		return ob_get_clean();
	}

	public static function render_admin() {
		if ( ! self::can_manage() ) {
			wp_die( 'دسترسی ندارید.' );
		}
		echo '<div class="wrap hs-ws-dash-wrap">';
		self::render_body();
		echo '</div>';
		// اگر از لینک ویرایش آمده
		$edit = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		if ( $edit ) {
			printf(
				'<script>jQuery(function($){ $(".hs-ws-edit[data-id=\"%d\"]").trigger("click"); });</script>',
				$edit
			);
		}
	}

	private static function render_body() {
		$grades   = class_exists( 'HS_Worksheet_CPT' ) ? HS_Worksheet_CPT::grades() : array( '1' => 'اول', '2' => 'دوم' );
		$subjects = class_exists( 'HS_Worksheet_CPT' ) ? HS_Worksheet_CPT::subjects() : array( 'farsi' => 'فارسی', 'math' => 'ریاضی' );
		$types    = class_exists( 'HS_Worksheet_CPT' ) ? HS_Worksheet_CPT::types() : array( 'pdf' => 'PDF', 'mixed' => 'ترکیبی' );
		$list     = self::list_worksheets( 40 );
		?>
		<div class="hs-ws-dash" id="hs-ws-dash" dir="rtl">
			<header class="hs-ws-dash-head">
				<div>
					<h1><?php echo esc_html( HS_Hubs::title( 'worksheet-dashboard', '📚 داشبورد مدیریت کاربرگ' ) ); ?></h1>
					<p>فایل، عکس، متن و فیلم بارگذاری کنید؛ عنوان و آدرس صفحه را خودتان تعیین کنید و منتشر کنید.</p>
				</div>
				<button type="button" class="button button-primary button-hero" id="hs-ws-new-btn">+ کاربرگ جدید</button>
			</header>

			<div class="hs-ws-dash-grid">
				<!-- فرم ساخت / ویرایش -->
				<section class="hs-ws-card hs-ws-form-card" id="hs-ws-form-card">
					<h2 id="hs-ws-form-title">کاربرگ جدید</h2>
					<input type="hidden" id="hs-ws-id" value="0">

					<div class="hs-ws-fields">
						<label>
							<span>عنوان صفحه *</span>
							<input type="text" id="hs-ws-title" placeholder="مثلاً: تمرین نشانه ب — پایه اول" class="regular-text">
						</label>

						<label>
							<span>آدرس صفحه (اسلاگ)</span>
							<div class="hs-ws-slug-row">
								<span class="hs-ws-slug-prefix"><?php echo esc_html( home_url( '/worksheets/' ) ); ?></span>
								<input type="text" id="hs-ws-slug" placeholder="tamrin-neshane-b" pattern="[a-z0-9\-_]+">
							</div>
							<small>فقط حروف انگلیسی، عدد و خط تیره. خالی بماند تا از عنوان ساخته شود.</small>
						</label>

						<div class="hs-ws-row-2">
							<label>
								<span>پایه</span>
								<select id="hs-ws-grade">
									<option value="">—</option>
									<?php foreach ( $grades as $k => $v ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>
								<span>درس</span>
								<select id="hs-ws-subject">
									<option value="">—</option>
									<?php foreach ( $subjects as $k => $v ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>

						<div class="hs-ws-row-2">
							<label>
								<span>موضوع</span>
								<input type="text" id="hs-ws-topic" placeholder="موضوع کوتاه">
							</label>
							<label>
								<span>نوع</span>
								<select id="hs-ws-type">
									<?php foreach ( $types as $k => $v ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>

						<label>
							<span>متن / توضیح کاربرگ</span>
							<textarea id="hs-ws-content" rows="8" placeholder="متن تمرین، راهنما یا محتوای صفحه را اینجا بنویسید..."></textarea>
						</label>
					</div>

					<!-- آپلودها -->
					<div class="hs-ws-uploads">
						<h3>پیوست‌ها</h3>
						<div class="hs-ws-upload-grid">
							<div class="hs-ws-upload-box" data-kind="pdf">
								<input type="file" id="hs-ws-file-pdf" accept=".pdf,application/pdf" hidden>
								<button type="button" class="hs-ws-up-btn" data-target="hs-ws-file-pdf">📄 فایل PDF / کاربرگ</button>
								<div class="hs-ws-up-preview" id="hs-ws-prev-pdf"></div>
							</div>
							<div class="hs-ws-upload-box" data-kind="image">
								<input type="file" id="hs-ws-file-image" accept="image/*" hidden>
								<button type="button" class="hs-ws-up-btn" data-target="hs-ws-file-image">🖼️ تصویر</button>
								<div class="hs-ws-up-preview" id="hs-ws-prev-image"></div>
							</div>
							<div class="hs-ws-upload-box" data-kind="video">
								<input type="file" id="hs-ws-file-video" accept="video/mp4,video/webm,.mp4,.webm" hidden>
								<button type="button" class="hs-ws-up-btn" data-target="hs-ws-file-video">🎬 فیلم</button>
								<div class="hs-ws-up-preview" id="hs-ws-prev-video"></div>
							</div>
							<div class="hs-ws-upload-box" data-kind="file">
								<input type="file" id="hs-ws-file-other" accept=".doc,.docx,.txt,.zip" hidden>
								<button type="button" class="hs-ws-up-btn" data-target="hs-ws-file-other">📎 فایل دیگر</button>
								<div class="hs-ws-up-preview" id="hs-ws-prev-file"></div>
							</div>
						</div>
						<input type="hidden" id="hs-ws-pdf-url" value="">
						<input type="hidden" id="hs-ws-pdf-id" value="0">
						<input type="hidden" id="hs-ws-image-url" value="">
						<input type="hidden" id="hs-ws-image-id" value="0">
						<input type="hidden" id="hs-ws-video-url" value="">
						<input type="hidden" id="hs-ws-video-id" value="0">
						<input type="hidden" id="hs-ws-file-url" value="">
						<input type="hidden" id="hs-ws-file-id" value="0">
					</div>

					<div class="hs-ws-actions">
						<button type="button" class="button button-primary button-large" id="hs-ws-publish">✅ پایان و انتشار</button>
						<button type="button" class="button button-large" id="hs-ws-draft">ذخیره پیش‌نویس</button>
						<button type="button" class="button button-link" id="hs-ws-reset">پاک کردن فرم</button>
					</div>
					<div class="hs-ws-msg" id="hs-ws-msg"></div>
				</section>

				<!-- فهرست -->
				<aside class="hs-ws-card hs-ws-list-card">
					<h2>کاربرگ‌های منتشرشده</h2>
					<?php if ( empty( $list ) ) : ?>
						<p class="hs-ws-empty">هنوز کاربرگی نیست. از فرم سمت راست اولین مورد را بسازید.</p>
					<?php else : ?>
						<ul class="hs-ws-list" id="hs-ws-list">
							<?php foreach ( $list as $item ) : ?>
								<li data-id="<?php echo esc_attr( $item['id'] ); ?>">
									<div>
										<strong><?php echo esc_html( $item['title'] ); ?></strong>
										<small><?php echo esc_html( $item['status_label'] ); ?> · <?php echo esc_html( $item['date'] ); ?></small>
										<?php if ( $item['url'] ) : ?>
											<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener">مشاهده ↗</a>
										<?php endif; ?>
									</div>
									<div class="hs-ws-list-actions">
										<button type="button" class="button button-small hs-ws-edit" data-id="<?php echo esc_attr( $item['id'] ); ?>">ویرایش</button>
										<button type="button" class="button button-small hs-ws-del" data-id="<?php echo esc_attr( $item['id'] ); ?>">حذف</button>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="hs-ws-hint">
						همچنین می‌توانید از منوی <strong>کاربرگ‌های همشاگردی</strong> در پیشخوان، فهرست کامل را ببینید.
					</p>
				</aside>
			</div>
		</div>
		<?php
	}

	private static function list_worksheets( $limit = 40 ) {
		$q = new WP_Query( array(
			'post_type'      => 'hs_worksheet',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		$out = array();
		foreach ( $q->posts as $p ) {
			$out[] = array(
				'id'           => $p->ID,
				'title'        => $p->post_title,
				'status'       => $p->post_status,
				'status_label' => 'publish' === $p->post_status ? 'منتشر' : 'پیش‌نویس',
				'date'         => get_the_date( 'Y/m/d', $p ),
				'url'          => get_permalink( $p ),
			);
		}
		return $out;
	}

	/* ---------- AJAX ---------- */

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_ws_dash', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ) );
		}
		if ( ! is_user_logged_in() || ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) );
		}
	}

	public static function ajax_upload() {
		self::verify();
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => 'فایلی ارسال نشد.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$kind = isset( $_POST['kind'] ) ? sanitize_key( $_POST['kind'] ) : 'file';
		$max  = ( 'video' === $kind ) ? 40 * MB_IN_BYTES : 15 * MB_IN_BYTES;
		if ( ! empty( $_FILES['file']['size'] ) && (int) $_FILES['file']['size'] > $max ) {
			wp_send_json_error( array( 'message' => 'حجم فایل بیش از حد مجاز است.' ) );
		}

		$mimes = array(
			'pdf'   => array( 'pdf' => 'application/pdf' ),
			'image' => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' ),
			'video' => array( 'mp4' => 'video/mp4', 'webm' => 'video/webm' ),
			'file'  => array(
				'pdf' => 'application/pdf',
				'doc' => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'txt' => 'text/plain',
				'zip' => 'application/zip',
			),
		);
		$allowed = isset( $mimes[ $kind ] ) ? $mimes[ $kind ] : $mimes['file'];

		$overrides = array( 'test_form' => false, 'mimes' => $allowed );
		$moved = wp_handle_upload( $_FILES['file'], $overrides );
		if ( isset( $moved['error'] ) ) {
			wp_send_json_error( array( 'message' => $moved['error'] ) );
		}

		$attachment = array(
			'post_mime_type' => $moved['type'],
			'post_title'     => sanitize_file_name( pathinfo( $_FILES['file']['name'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $moved['file'] );
		if ( ! is_wp_error( $attach_id ) && 0 === strpos( $moved['type'], 'image/' ) ) {
			$meta = wp_generate_attachment_metadata( $attach_id, $moved['file'] );
			wp_update_attachment_metadata( $attach_id, $meta );
		}

		wp_send_json_success( array(
			'url'       => $moved['url'],
			'attach_id' => is_wp_error( $attach_id ) ? 0 : (int) $attach_id,
			'name'      => basename( $moved['file'] ),
			'kind'      => $kind,
			'type'      => $moved['type'],
		) );
	}

	public static function ajax_save() {
		self::verify();

		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$slug    = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$status  = ( isset( $_POST['status'] ) && 'draft' === $_POST['status'] ) ? 'draft' : 'publish';
		$grade   = isset( $_POST['grade'] ) ? sanitize_key( $_POST['grade'] ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_key( $_POST['subject'] ) : '';
		$topic   = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$type    = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'mixed';

		$pdf_url   = isset( $_POST['pdf_url'] ) ? esc_url_raw( wp_unslash( $_POST['pdf_url'] ) ) : '';
		$pdf_id    = isset( $_POST['pdf_id'] ) ? absint( $_POST['pdf_id'] ) : 0;
		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$image_id  = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		$video_url = isset( $_POST['video_url'] ) ? esc_url_raw( wp_unslash( $_POST['video_url'] ) ) : '';
		$video_id  = isset( $_POST['video_id'] ) ? absint( $_POST['video_id'] ) : 0;
		$file_url  = isset( $_POST['file_url'] ) ? esc_url_raw( wp_unslash( $_POST['file_url'] ) ) : '';
		$file_id   = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;

		if ( ! $title ) {
			wp_send_json_error( array( 'message' => 'عنوان الزامی است.' ) );
		}

		// محتوا در متا ذخیره می‌شود؛ پوسته اختصاصی آن را می‌چیند (بدون گوتنبرگ)
		$postarr = array(
			'post_title'   => $title,
			'post_content' => '', // پوسته از متا می‌خواند
			'post_status'  => $status,
			'post_type'    => 'hs_worksheet',
			'post_author'  => get_current_user_id(),
			'post_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 28 ),
		);
		if ( $slug ) {
			$postarr['post_name'] = $slug;
		}

		if ( $id ) {
			$existing = get_post( $id );
			if ( ! $existing || 'hs_worksheet' !== $existing->post_type ) {
				wp_send_json_error( array( 'message' => 'کاربرگ یافت نشد.' ) );
			}
			$postarr['ID'] = $id;
			$post_id = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		// متا — منبع حقیقت برای پوسته
		update_post_meta( $post_id, '_hs_ws_body', $content );
		update_post_meta( $post_id, '_hs_ws_grade', $grade );
		update_post_meta( $post_id, '_hs_ws_subject', $subject );
		update_post_meta( $post_id, '_hs_ws_topic', $topic );
		update_post_meta( $post_id, '_hs_ws_type', $type );
		update_post_meta( $post_id, '_hs_ws_pdf', $pdf_url );
		update_post_meta( $post_id, '_hs_ws_pdf_id', $pdf_id );
		update_post_meta( $post_id, '_hs_ws_video', $video_url );
		update_post_meta( $post_id, '_hs_ws_video_id', $video_id );
		update_post_meta( $post_id, '_hs_ws_file', $file_url );
		update_post_meta( $post_id, '_hs_ws_file_id', $file_id );
		update_post_meta( $post_id, '_hs_ws_image_url', $image_url );
		update_post_meta( $post_id, '_hs_ws_access', 'public' );

		if ( $image_id ) {
			set_post_thumbnail( $post_id, $image_id );
		} elseif ( $image_url ) {
			update_post_meta( $post_id, '_hs_ws_image_url', $image_url );
		}

		// taxonomy اگر وجود دارد
		if ( $grade && taxonomy_exists( 'hs_ws_grade' ) ) {
			wp_set_object_terms( $post_id, $grade, 'hs_ws_grade', false );
		}
		if ( $subject && taxonomy_exists( 'hs_ws_subject' ) ) {
			wp_set_object_terms( $post_id, $subject, 'hs_ws_subject', false );
		}

		wp_send_json_success( array(
			'message' => ( 'publish' === $status ) ? 'منتشر شد.' : 'پیش‌نویس ذخیره شد.',
			'id'      => (int) $post_id,
			'url'     => get_permalink( $post_id ),
			'edit'    => get_edit_post_link( $post_id, 'raw' ),
			'status'  => $status,
		) );
	}

	public static function ajax_get() {
		self::verify();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$p  = get_post( $id );
		if ( ! $p || 'hs_worksheet' !== $p->post_type ) {
			wp_send_json_error( array( 'message' => 'یافت نشد.' ) );
		}

		$content = get_post_meta( $p->ID, '_hs_ws_body', true );
		if ( ! $content ) {
			$content = $p->post_content;
			$content = preg_replace( '/<figure[\s\S]*?<\/figure>/i', '', $content );
			$content = preg_replace( '/<p class="hs-ws-download"[\s\S]*?<\/p>/i', '', $content );
			$content = trim( wp_strip_all_tags( $content ) );
		}

		wp_send_json_success( array(
			'id'        => $p->ID,
			'title'     => $p->post_title,
			'slug'      => $p->post_name,
			'content'   => $content,
			'status'    => $p->post_status,
			'grade'     => get_post_meta( $p->ID, '_hs_ws_grade', true ),
			'subject'   => get_post_meta( $p->ID, '_hs_ws_subject', true ),
			'topic'     => get_post_meta( $p->ID, '_hs_ws_topic', true ),
			'type'      => get_post_meta( $p->ID, '_hs_ws_type', true ) ?: 'mixed',
			'pdf_url'   => get_post_meta( $p->ID, '_hs_ws_pdf', true ),
			'pdf_id'    => (int) get_post_meta( $p->ID, '_hs_ws_pdf_id', true ),
			'image_url' => get_the_post_thumbnail_url( $p->ID, 'medium' ) ?: (string) get_post_meta( $p->ID, '_hs_ws_image_url', true ),
			'image_id'  => (int) get_post_thumbnail_id( $p->ID ),
			'video_url' => get_post_meta( $p->ID, '_hs_ws_video', true ),
			'video_id'  => (int) get_post_meta( $p->ID, '_hs_ws_video_id', true ),
			'file_url'  => get_post_meta( $p->ID, '_hs_ws_file', true ),
			'file_id'   => (int) get_post_meta( $p->ID, '_hs_ws_file_id', true ),
			'url'       => get_permalink( $p ),
		) );
	}

	public static function ajax_delete() {
		self::verify();
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$p  = get_post( $id );
		if ( ! $p || 'hs_worksheet' !== $p->post_type ) {
			wp_send_json_error( array( 'message' => 'یافت نشد.' ) );
		}
		wp_trash_post( $id );
		wp_send_json_success( array( 'message' => 'به زباله‌دان منتقل شد.' ) );
	}
}

HS_Worksheet_Dashboard::init();
