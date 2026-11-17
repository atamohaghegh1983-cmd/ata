<?php
/**
 * AJAX استودیو محتوا
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Content_Studio_Handlers {

	public static function init() {
		add_action( 'wp_ajax_hs_studio_upload', array( __CLASS__, 'upload_and_extract' ) );
		add_action( 'wp_ajax_hs_studio_save', array( __CLASS__, 'save_project' ) );
		add_action( 'wp_ajax_hs_studio_publish', array( __CLASS__, 'publish' ) );
		add_action( 'wp_ajax_hs_studio_delete', array( __CLASS__, 'delete_project' ) );
		add_action( 'wp_ajax_hs_studio_add_media', array( __CLASS__, 'add_media' ) );
	}

	private static function verify() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'وارد شوید.' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) && ! HS_Roles::is_teacher_or_admin() ) {
			wp_send_json_error( array( 'message' => 'دسترسی کافی ندارید.' ) );
		}
	}

	/**
	 * آپلود فایل + استخراج متن
	 */
	public static function upload_and_extract() {
		self::verify();

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => 'فایلی ارسال نشد.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file = $_FILES['file'];
		$allowed = array(
			'pdf'  => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'txt'  => 'text/plain',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
			'mp4'  => 'video/mp4',
			'webm' => 'video/webm',
		);

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! isset( $allowed[ $ext ] ) ) {
			wp_send_json_error( array( 'message' => 'فرمت مجاز نیست. PDF، DOCX، TXT، تصویر یا MP4.' ) );
		}

		$overrides = array( 'test_form' => false, 'mimes' => $allowed );
		$movefile = wp_handle_upload( $file, $overrides );

		if ( isset( $movefile['error'] ) ) {
			wp_send_json_error( array( 'message' => $movefile['error'] ) );
		}

		$attachment = array(
			'post_mime_type' => $movefile['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $movefile['file'] );
		if ( ! is_wp_error( $attach_id ) ) {
			$meta = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
			wp_update_attachment_metadata( $attach_id, $meta );
		}

		$result = array(
			'url'      => $movefile['url'],
			'file'     => $movefile['file'],
			'type'     => $movefile['type'],
			'ext'      => $ext,
			'attach_id'=> is_wp_error( $attach_id ) ? 0 : $attach_id,
			'name'     => $file['name'],
		);

		// استخراج متن برای اسناد
		if ( in_array( $ext, array( 'pdf', 'docx', 'txt' ), true ) ) {
			$extracted = HS_Content_Extract::from_file( $movefile['file'], $movefile['type'] );
			if ( is_wp_error( $extracted ) ) {
				$result['extract_error'] = $extracted->get_error_message();
				$result['text'] = '';
				$result['html'] = '';
			} else {
				$result['text'] = $extracted;
				$result['html'] = HS_Content_Extract::text_to_html( $extracted );
			}
		} else {
			$result['text'] = '';
			$result['html'] = '';
			$result['is_media'] = true;
		}

		wp_send_json_success( $result );
	}

	/**
	 * ذخیره پیش‌نویس پروژه
	 */
	public static function save_project() {
		self::verify();

		$id    = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$text  = isset( $_POST['extracted_text'] ) ? wp_unslash( $_POST['extracted_text'] ) : '';
		$html  = isset( $_POST['html_content'] ) ? wp_kses_post( wp_unslash( $_POST['html_content'] ) ) : '';
		$meta  = isset( $_POST['meta'] ) ? json_decode( wp_unslash( $_POST['meta'] ), true ) : array();
		$media = isset( $_POST['media'] ) ? json_decode( wp_unslash( $_POST['media'] ), true ) : array();

		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		if ( ! is_array( $media ) ) {
			$media = array();
		}

		// پاکسازی media
		$clean_media = array();
		foreach ( $media as $m ) {
			$clean_media[] = array(
				'url'       => esc_url_raw( $m['url'] ?? '' ),
				'type'      => sanitize_key( $m['type'] ?? 'file' ),
				'name'      => sanitize_text_field( $m['name'] ?? '' ),
				'attach_id' => absint( $m['attach_id'] ?? 0 ),
			);
		}

		$data = array(
			'title'          => $title ?: 'بدون عنوان',
			'extracted_text' => $text,
			'html_content'   => $html,
			'meta'           => array(
				'grade'    => sanitize_text_field( $meta['grade'] ?? '' ),
				'subject'  => sanitize_text_field( $meta['subject'] ?? '' ),
				'topic'    => sanitize_text_field( $meta['topic'] ?? '' ),
				'publish_as' => sanitize_key( $meta['publish_as'] ?? 'page' ),
			),
			'media'          => $clean_media,
			'source_type'    => sanitize_key( $_POST['source_type'] ?? 'mixed' ),
		);

		if ( $id ) {
			$existing = HS_Content_Studio_DB::get( $id );
			if ( ! $existing ) {
				wp_send_json_error( array( 'message' => 'پروژه یافت نشد.' ) );
			}
			if ( (int) $existing['created_by'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
			}
			HS_Content_Studio_DB::update( $id, $data );
		} else {
			$id = HS_Content_Studio_DB::create( $data );
		}

		wp_send_json_success( array(
			'message'    => 'ذخیره شد.',
			'project_id' => $id,
			'project'    => HS_Content_Studio_DB::get( $id ),
		) );
	}

	/**
	 * پایان کار: ساخت صفحه یا کاربرگ و انتشار
	 */
	public static function publish() {
		self::verify();

		$id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		// یا داده‌های مستقیم
		if ( ! $id ) {
			// اول ذخیره
			$_POST['title'] = $_POST['title'] ?? '';
			self::save_project_internal();
			// save_project sends json - we need inline logic
		}

		// خواندن از POST مستقیم برای یک‌مرحله‌ای بودن
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$html  = isset( $_POST['html_content'] ) ? wp_kses_post( wp_unslash( $_POST['html_content'] ) ) : '';
		$text  = isset( $_POST['extracted_text'] ) ? wp_unslash( $_POST['extracted_text'] ) : '';
		$meta  = isset( $_POST['meta'] ) ? json_decode( wp_unslash( $_POST['meta'] ), true ) : array();
		$media = isset( $_POST['media'] ) ? json_decode( wp_unslash( $_POST['media'] ), true ) : array();

		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		if ( ! is_array( $media ) ) {
			$media = array();
		}
		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'عنوان صفحه الزامی است.' ) );
		}

		$publish_as = sanitize_key( $meta['publish_as'] ?? 'page' );
		$content_html = $html;
		if ( empty( $content_html ) && $text ) {
			$content_html = HS_Content_Extract::text_to_html( $text );
		}

		// افزودن رسانه‌ها به محتوا
		$media_html = '';
		$featured_id = 0;
		foreach ( $media as $m ) {
			$url = esc_url( $m['url'] ?? '' );
			$type = $m['type'] ?? '';
			$ext = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );
			if ( ! $url ) {
				continue;
			}
			if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) || ( isset( $m['type'] ) && strpos( $m['type'], 'image' ) !== false ) ) {
				$media_html .= '<figure class="hs-studio-img"><img src="' . $url . '" alt=""></figure>' . "\n";
				if ( ! $featured_id && ! empty( $m['attach_id'] ) ) {
					$featured_id = absint( $m['attach_id'] );
				}
			} elseif ( in_array( $ext, array( 'mp4', 'webm' ), true ) || strpos( $type, 'video' ) !== false ) {
				$media_html .= '<figure class="hs-studio-video"><video controls src="' . $url . '" style="max-width:100%"></video></figure>' . "\n";
			} elseif ( $ext === 'pdf' ) {
				$media_html .= '<p><a class="hs-btn" href="' . $url . '" target="_blank" rel="noopener">📄 دانلود PDF</a></p>' . "\n";
			} else {
				$media_html .= '<p><a href="' . $url . '" target="_blank" rel="noopener">📎 ' . esc_html( $m['name'] ?? 'فایل' ) . '</a></p>' . "\n";
			}
		}

		$full_content = $media_html . $content_html;

		$post_id = 0;

		if ( $publish_as === 'worksheet' && post_type_exists( 'hs_worksheet' ) ) {
			$post_id = wp_insert_post( array(
				'post_title'   => $title,
				'post_content' => $full_content,
				'post_excerpt' => mb_substr( wp_strip_all_tags( $text ?: $html ), 0, 180 ),
				'post_status'  => 'publish',
				'post_type'    => 'hs_worksheet',
				'post_author'  => get_current_user_id(),
			), true );

			if ( ! is_wp_error( $post_id ) ) {
				if ( ! empty( $meta['grade'] ) ) {
					update_post_meta( $post_id, '_hs_ws_grade', sanitize_text_field( $meta['grade'] ) );
				}
				if ( ! empty( $meta['subject'] ) ) {
					update_post_meta( $post_id, '_hs_ws_subject', sanitize_text_field( $meta['subject'] ) );
				}
				if ( ! empty( $meta['topic'] ) ) {
					update_post_meta( $post_id, '_hs_ws_topic', sanitize_text_field( $meta['topic'] ) );
				}
				// PDF اول را به‌عنوان لینک PDF کاربرگ
				foreach ( $media as $m ) {
					$url = $m['url'] ?? '';
					if ( $url && preg_match( '/\.pdf$/i', $url ) ) {
						update_post_meta( $post_id, '_hs_ws_pdf', esc_url_raw( $url ) );
						break;
					}
				}
				if ( $featured_id ) {
					set_post_thumbnail( $post_id, $featured_id );
				}
			}
		} else {
			// صفحه معمولی وردپرس
			$post_id = wp_insert_post( array(
				'post_title'   => $title,
				'post_content' => $full_content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			), true );

			if ( ! is_wp_error( $post_id ) && $featured_id ) {
				set_post_thumbnail( $post_id, $featured_id );
			}
		}

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		// ذخیره/به‌روزرسانی پروژه
		$project_data = array(
			'title'             => $title,
			'status'            => 'published',
			'extracted_text'    => $text,
			'html_content'      => $full_content,
			'published_post_id' => $post_id,
			'meta'              => $meta,
			'media'             => $media,
		);

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		if ( $project_id ) {
			HS_Content_Studio_DB::update( $project_id, $project_data );
		} else {
			$project_id = HS_Content_Studio_DB::create( array_merge( $project_data, array( 'source_type' => 'mixed' ) ) );
			HS_Content_Studio_DB::update( $project_id, array( 'status' => 'published', 'published_post_id' => $post_id ) );
		}

		wp_send_json_success( array(
			'message'    => 'صفحه منتشر شد.',
			'post_id'    => $post_id,
			'project_id' => $project_id,
			'url'        => get_permalink( $post_id ),
			'edit_url'   => get_edit_post_link( $post_id, 'raw' ),
		) );
	}

	public static function delete_project() {
		self::verify();
		$id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$p = HS_Content_Studio_DB::get( $id );
		if ( ! $p ) {
			wp_send_json_error( array( 'message' => 'یافت نشد.' ) );
		}
		if ( (int) $p['created_by'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		HS_Content_Studio_DB::delete( $id );
		wp_send_json_success( array( 'message' => 'حذف شد.' ) );
	}

	public static function add_media() {
		// همان upload بدون استخراج اجباری
		self::upload_and_extract();
	}
}

HS_Content_Studio_Handlers::init();
