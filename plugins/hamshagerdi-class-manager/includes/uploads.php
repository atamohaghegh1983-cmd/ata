<?php
/**
 * آپلود عکس از دستگاه (کامپیوتر / موبایل) برای همهٔ فرم‌های «لینک عکس»
 * مثل ارسال عکس در پیام‌رسان‌ها: انتخاب از گالری، دوربین یا فایل‌ها.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Upload {

	const MAX_BYTES = 6291456; // ۶ مگابایت (قبل از کوچک‌سازی)

	public static function init() {
		add_action( 'wp_ajax_hs_upload_image', array( __CLASS__, 'ajax_upload' ) );
	}

	/** تغییر پوشهٔ آپلود */
	public static function upload_dir( $dirs ) {
		$sub            = '/hs-media/' . gmdate( 'Y' );
		$dirs['subdir'] = $sub;
		$dirs['path']   = $dirs['basedir'] . $sub;
		$dirs['url']    = $dirs['baseurl'] . $sub;
		return $dirs;
	}

	private static function fail( $msg, $code = 400 ) {
		wp_send_json_error( array( 'message' => $msg ), $code );
	}

	/**
	 * مدیریت یک فایل تصویر آپلودشده: اعتبارسنجی، ذخیره، چرخش EXIF و کوچک‌سازی.
	 *
	 * @param array  $file    ردیف $_FILES
	 * @param string $purpose student_photo | general
	 * @return array|WP_Error  [ url, attach_id ]
	 */
	public static function handle( $file, $purpose = 'general' ) {
		if ( ! is_array( $file ) || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload', 'آپلود ناموفق بود. دوباره تلاش کنید.' );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'size', 'حجم عکس باید کمتر از ۶ مگابایت باشد.' );
		}
		$mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new WP_Error( 'type', 'فقط عکس JPG، PNG، WebP یا GIF پذیرفته می‌شود.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uid = get_current_user_id();
		$ext = $check['ext'];
		add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		$moved = wp_handle_upload( $file, array(
			'test_form'                => false,
			'mimes'                    => $mimes,
			'unique_filename_callback' => function () use ( $uid, $ext ) {
				return 'p' . $uid . '-' . strtolower( wp_generate_password( 10, false ) ) . '.' . $ext;
			},
		) );
		remove_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );

		if ( ! is_array( $moved ) || ! empty( $moved['error'] ) ) {
			return new WP_Error( 'move', 'ذخیره عکس ممکن نشد.' );
		}

		$editor = wp_get_image_editor( $moved['file'] );
		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $moved['file'] );
			return new WP_Error( 'image', 'این فایل یک عکس معتبر نیست.' );
		}
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}
		if ( 'student_photo' === $purpose ) {
			$editor->resize( 400, 400, true );
		} else {
			$size = $editor->get_size();
			if ( $size && ( $size['width'] > 1600 || $size['height'] > 1600 ) ) {
				$editor->resize( 1600, 1600, false );
			}
		}
		if ( 'image/gif' !== $moved['type'] ) {
			$editor->set_quality( 82 );
			$saved = $editor->save( $moved['file'] );
			if ( is_wp_error( $saved ) ) {
				wp_delete_file( $moved['file'] );
				return new WP_Error( 'save', 'پردازش عکس ناموفق بود.' );
			}
		}

		$attach_id = wp_insert_attachment( array(
			'post_mime_type' => $moved['type'],
			'post_title'     => 'hs-' . $uid . '-' . time(),
			'post_content'   => '',
			'post_status'    => 'inherit',
		), $moved['file'] );
		if ( ! is_wp_error( $attach_id ) && $attach_id ) {
			wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $moved['file'] ) );
		} else {
			$attach_id = 0;
		}
		return array( 'url' => esc_url_raw( $moved['url'] ), 'attach_id' => (int) $attach_id );
	}

	public static function ajax_upload() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			self::fail( 'نشست نامعتبر. صفحه را رفرش کنید.', 403 );
		}
		$purpose = isset( $_POST['purpose'] ) ? sanitize_key( $_POST['purpose'] ) : 'general';
		if ( 'student_photo' === $purpose && ! HS_Roles::is_teacher_or_admin() ) {
			self::fail( 'دسترسی غیرمجاز.', 403 );
		}
		if ( empty( $_FILES['file'] ) ) {
			self::fail( 'فایلی انتخاب نشده است.' );
		}
		$res = self::handle( $_FILES['file'], $purpose ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_wp_error( $res ) ) {
			self::fail( $res->get_error_message() );
		}
		wp_send_json_success( array_merge( $res, array( 'message' => 'عکس آپلود شد ✅' ) ) );
	}

	/**
	 * فیلد انتخاب عکس. input مقدار (URL) را نگه می‌دارد و با همان id/class قابل خواندن است.
	 *
	 * @param string $id      شناسهٔ input مقدار
	 * @param string $purpose student_photo | general
	 * @param array  $o       label, value, class, placeholder
	 */
	public static function picker( $id, $purpose = 'general', $o = array() ) {
		$o     = wp_parse_args( $o, array( 'label' => 'انتخاب عکس', 'value' => '', 'class' => '', 'empty' => 'عکسی انتخاب نشده' ) );
		$value = (string) $o['value'];
		ob_start();
		?>
		<div class="hs-imgpick" data-purpose="<?php echo esc_attr( $purpose ); ?>">
			<div class="hs-imgpick-row">
				<span class="hs-imgpick-prev<?php echo $value ? ' has' : ''; ?>"><?php echo $value ? '<img src="' . esc_url( $value ) . '" alt="">' : '🖼️'; ?></span>
				<div class="hs-imgpick-ctrl">
					<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-imgpick-btn">📷 <?php echo esc_html( $o['label'] ); ?></button>
					<button type="button" class="hs-imgpick-clear" hidden>حذف</button>
					<span class="hs-imgpick-state"><?php echo esc_html( $value ? 'عکس انتخاب شد' : $o['empty'] ); ?></span>
					<button type="button" class="hs-imgpick-linkbtn">یا لینک عکس</button>
				</div>
			</div>
			<input type="file" class="hs-imgpick-file" accept="image/*" hidden>
			<input type="url" id="<?php echo esc_attr( $id ); ?>" class="hs-imgpick-val <?php echo esc_attr( $o['class'] ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="https://..." dir="ltr" hidden>
		</div>
		<?php
		return ob_get_clean();
	}
}

HS_Upload::init();
