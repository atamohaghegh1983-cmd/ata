<?php
/**
 * CPT کاربرگ (کاربرگ‌های همشاگردی)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Worksheet_CPT {

	const POST_TYPE = 'hs_worksheet';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		// بدون گوتنبرگ — ویرایش فقط از داشبورد کاربرگ
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );
		add_filter( 'template_include', array( __CLASS__, 'single_template' ), 99 );
		add_action( 'load-post.php', array( __CLASS__, 'redirect_classic_edit' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'redirect_classic_edit' ) );
	}

	public static function disable_block_editor( $use, $post_type ) {
		if ( self::POST_TYPE === $post_type ) {
			return false;
		}
		return $use;
	}

	/** پوسته اختصاصی تک‌نمایش کاربرگ */
	public static function single_template( $template ) {
		if ( is_singular( self::POST_TYPE ) ) {
			$custom = HS_CLASS_PATH . 'templates/single-worksheet.php';
			if ( is_readable( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}

	/** ویرایش از پیشخوان → داشبورد کاربرگ */
	public static function redirect_classic_edit() {
		global $typenow;
		if ( self::POST_TYPE !== $typenow ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			// معلم هم می‌تواند
		}
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$url = admin_url( 'admin.php?page=hs-worksheet-dashboard' );
		if ( $id ) {
			$url = add_query_arg( 'edit', $id, $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	public static function register() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'               => 'کاربرگ‌ها',
				'singular_name'      => 'کاربرگ',
				'add_new'            => 'افزودن کاربرگ',
				'add_new_item'       => 'افزودن کاربرگ جدید',
				'edit_item'          => 'ویرایش کاربرگ',
				'new_item'           => 'کاربرگ جدید',
				'view_item'          => 'مشاهده کاربرگ',
				'search_items'       => 'جستجوی کاربرگ',
				'not_found'          => 'کاربرگی یافت نشد',
				'menu_name'          => 'کاربرگ‌های همشاگردی',
			),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-media-document',
			'menu_position'       => 26,
			'has_archive'         => true,
			'rewrite'             => array( 'slug' => 'worksheets' ),
			'supports'            => array( 'title', 'thumbnail' ),
			'show_in_rest'        => false,
			'capability_type'     => 'post',
		) );

		register_taxonomy( 'hs_ws_grade', self::POST_TYPE, array(
			'labels' => array(
				'name' => 'پایه تحصیلی',
				'singular_name' => 'پایه',
			),
			'public' => true,
			'hierarchical' => true,
			'rewrite' => array( 'slug' => 'worksheet-grade' ),
			'show_admin_column' => true,
			'show_in_rest' => true,
		) );

		register_taxonomy( 'hs_ws_subject', self::POST_TYPE, array(
			'labels' => array(
				'name' => 'درس',
				'singular_name' => 'درس',
			),
			'public' => true,
			'hierarchical' => true,
			'rewrite' => array( 'slug' => 'worksheet-subject' ),
			'show_admin_column' => true,
			'show_in_rest' => true,
		) );
	}

	public static function grades() {
		return array(
			'pre' => 'پیش‌دبستانی',
			'1'   => 'اول دبستان',
			'2'   => 'دوم دبستان',
			'3'   => 'سوم دبستان',
			'4'   => 'چهارم دبستان',
			'5'   => 'پنجم دبستان',
			'6'   => 'ششم دبستان',
		);
	}

	public static function subjects() {
		return array(
			'farsi'      => 'فارسی',
			'emla'       => 'املا',
			'reading'    => 'روان‌خوانی',
			'math'       => 'ریاضی',
			'creativity' => 'خلاقیت',
			'life'       => 'مهارت زندگی',
			'science'    => 'علوم',
			'other'      => 'سایر',
		);
	}

	public static function difficulties() {
		return array(
			'easy'   => 'آسان',
			'medium' => 'متوسط',
			'hard'   => 'چالشی',
		);
	}

	public static function types() {
		return array(
			'visual'    => 'تصویری',
			'math'      => 'ریاضی',
			'writing'   => 'دست‌ورزی و نوشتن',
			'reading'   => 'درک مطلب',
			'logic'     => 'هوش و خلاقیت',
			'science'   => 'مشاهده و تجربه',
			'mixed'     => 'ترکیبی',
		);
	}

	public static function access_levels() {
		return array(
			'public'  => 'عمومی (همه)',
			'teachers'=> 'فقط معلمان',
			'buyers'  => 'خریداران کتاب',
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'hs_ws_details',
			'مشخصات کاربرگ',
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'hs_ws_save', 'hs_ws_nonce' );
		$topic      = get_post_meta( $post->ID, '_hs_ws_topic', true );
		$difficulty = get_post_meta( $post->ID, '_hs_ws_difficulty', true ) ?: 'medium';
		$duration   = get_post_meta( $post->ID, '_hs_ws_duration', true );
		$qcount     = get_post_meta( $post->ID, '_hs_ws_qcount', true );
		$pdf_url    = get_post_meta( $post->ID, '_hs_ws_pdf', true );
		$interactive= get_post_meta( $post->ID, '_hs_ws_interactive', true );
		$type       = get_post_meta( $post->ID, '_hs_ws_type', true ) ?: 'mixed';
		$access     = get_post_meta( $post->ID, '_hs_ws_access', true ) ?: 'public';
		$grade_meta = get_post_meta( $post->ID, '_hs_ws_grade', true );
		$subject_meta = get_post_meta( $post->ID, '_hs_ws_subject', true );
		?>
		<style>
			.hs-ws-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
			.hs-ws-grid label { display: block; font-weight: 600; margin-bottom: 4px; }
			.hs-ws-grid input, .hs-ws-grid select { width: 100%; }
			.hs-ws-full { grid-column: 1 / -1; }
		</style>
		<div class="hs-ws-grid">
			<div>
				<label>پایه (کلید)</label>
				<select name="hs_ws_grade">
					<option value="">—</option>
					<?php foreach ( self::grades() as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $grade_meta, $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label>درس (کلید)</label>
				<select name="hs_ws_subject">
					<option value="">—</option>
					<?php foreach ( self::subjects() as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $subject_meta, $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="hs-ws-full">
				<label>موضوع</label>
				<input type="text" name="hs_ws_topic" value="<?php echo esc_attr( $topic ); ?>" placeholder="مثلاً: نشانه ب، جمع تا ۱۰">
			</div>
			<div>
				<label>سطح دشواری</label>
				<select name="hs_ws_difficulty">
					<?php foreach ( self::difficulties() as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $difficulty, $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label>نوع کاربرگ</label>
				<select name="hs_ws_type">
					<?php foreach ( self::types() as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label>زمان تقریبی (دقیقه)</label>
				<input type="number" name="hs_ws_duration" value="<?php echo esc_attr( $duration ); ?>" min="1" max="120">
			</div>
			<div>
				<label>تعداد سؤال</label>
				<input type="number" name="hs_ws_qcount" value="<?php echo esc_attr( $qcount ); ?>" min="1" max="100">
			</div>
			<div class="hs-ws-full">
				<label>لینک PDF قابل چاپ</label>
				<input type="url" name="hs_ws_pdf" value="<?php echo esc_attr( $pdf_url ); ?>" placeholder="https://...pdf" dir="ltr">
			</div>
			<div class="hs-ws-full">
				<label>لینک نسخه تعاملی آنلاین (اختیاری)</label>
				<input type="url" name="hs_ws_interactive" value="<?php echo esc_attr( $interactive ); ?>" placeholder="https://..." dir="ltr">
			</div>
			<div>
				<label>سطح دسترسی</label>
				<select name="hs_ws_access">
					<?php foreach ( self::access_levels() as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $access, $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<p style="margin-top:12px;color:#666;">عنوان پست = نام کاربرگ. توضیحات کوتاه را در چکیده بنویسید. تصویر شاخص برای پیش‌نمایش استفاده می‌شود.</p>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['hs_ws_nonce'] ) || ! wp_verify_nonce( $_POST['hs_ws_nonce'], 'hs_ws_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'hs_ws_grade'        => '_hs_ws_grade',
			'hs_ws_subject'      => '_hs_ws_subject',
			'hs_ws_topic'        => '_hs_ws_topic',
			'hs_ws_difficulty'   => '_hs_ws_difficulty',
			'hs_ws_type'         => '_hs_ws_type',
			'hs_ws_duration'     => '_hs_ws_duration',
			'hs_ws_qcount'       => '_hs_ws_qcount',
			'hs_ws_pdf'          => '_hs_ws_pdf',
			'hs_ws_interactive'  => '_hs_ws_interactive',
			'hs_ws_access'       => '_hs_ws_access',
		);

		foreach ( $fields as $post_key => $meta_key ) {
			if ( ! isset( $_POST[ $post_key ] ) ) {
				continue;
			}
			$val = wp_unslash( $_POST[ $post_key ] );
			if ( in_array( $post_key, array( 'hs_ws_pdf', 'hs_ws_interactive' ), true ) ) {
				$val = esc_url_raw( $val );
			} elseif ( in_array( $post_key, array( 'hs_ws_duration', 'hs_ws_qcount' ), true ) ) {
				$val = absint( $val );
			} else {
				$val = sanitize_text_field( $val );
			}
			update_post_meta( $post_id, $meta_key, $val );
		}
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( $k === 'title' ) {
				$new['hs_grade'] = 'پایه';
				$new['hs_subject'] = 'درس';
				$new['hs_diff'] = 'سطح';
			}
		}
		return $new;
	}

	public static function column_content( $col, $post_id ) {
		if ( $col === 'hs_grade' ) {
			$g = get_post_meta( $post_id, '_hs_ws_grade', true );
			$grades = self::grades();
			echo isset( $grades[ $g ] ) ? esc_html( $grades[ $g ] ) : '—';
		}
		if ( $col === 'hs_subject' ) {
			$s = get_post_meta( $post_id, '_hs_ws_subject', true );
			$subjects = self::subjects();
			echo isset( $subjects[ $s ] ) ? esc_html( $subjects[ $s ] ) : '—';
		}
		if ( $col === 'hs_diff' ) {
			$d = get_post_meta( $post_id, '_hs_ws_difficulty', true );
			$diffs = self::difficulties();
			echo isset( $diffs[ $d ] ) ? esc_html( $diffs[ $d ] ) : '—';
		}
	}

	/**
	 * کوئری کتابخانه با فیلتر
	 */
	public static function query( $args = array() ) {
		$q = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => isset( $args['limit'] ) ? absint( $args['limit'] ) : 24,
			'paged'          => isset( $args['paged'] ) ? absint( $args['paged'] ) : 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$meta_query = array();
		if ( ! empty( $args['grade'] ) ) {
			$meta_query[] = array( 'key' => '_hs_ws_grade', 'value' => sanitize_text_field( $args['grade'] ) );
		}
		if ( ! empty( $args['subject'] ) ) {
			$meta_query[] = array( 'key' => '_hs_ws_subject', 'value' => sanitize_text_field( $args['subject'] ) );
		}
		if ( ! empty( $args['difficulty'] ) ) {
			$meta_query[] = array( 'key' => '_hs_ws_difficulty', 'value' => sanitize_text_field( $args['difficulty'] ) );
		}
		if ( ! empty( $args['type'] ) ) {
			$meta_query[] = array( 'key' => '_hs_ws_type', 'value' => sanitize_text_field( $args['type'] ) );
		}
		if ( ! empty( $meta_query ) ) {
			$q['meta_query'] = $meta_query;
		}
		if ( ! empty( $args['s'] ) ) {
			$q['s'] = sanitize_text_field( $args['s'] );
		}

		return new WP_Query( $q );
	}

	public static function get_card_data( $post_id ) {
		$grades = self::grades();
		$subjects = self::subjects();
		$diffs = self::difficulties();
		$types = self::types();
		$g = get_post_meta( $post_id, '_hs_ws_grade', true );
		$s = get_post_meta( $post_id, '_hs_ws_subject', true );
		$d = get_post_meta( $post_id, '_hs_ws_difficulty', true );
		$t = get_post_meta( $post_id, '_hs_ws_type', true );

		return array(
			'id'          => $post_id,
			'title'       => get_the_title( $post_id ),
			'excerpt'     => get_the_excerpt( $post_id ),
			'thumb'       => get_the_post_thumbnail_url( $post_id, 'medium' ),
			'grade'       => $g,
			'grade_label' => isset( $grades[ $g ] ) ? $grades[ $g ] : '',
			'subject'     => $s,
			'subject_label' => isset( $subjects[ $s ] ) ? $subjects[ $s ] : '',
			'topic'       => get_post_meta( $post_id, '_hs_ws_topic', true ),
			'difficulty'  => $d,
			'diff_label'  => isset( $diffs[ $d ] ) ? $diffs[ $d ] : '',
			'type'        => $t,
			'type_label'  => isset( $types[ $t ] ) ? $types[ $t ] : '',
			'duration'    => get_post_meta( $post_id, '_hs_ws_duration', true ),
			'qcount'      => get_post_meta( $post_id, '_hs_ws_qcount', true ),
			'pdf'         => get_post_meta( $post_id, '_hs_ws_pdf', true ),
			'video'       => get_post_meta( $post_id, '_hs_ws_video', true ),
			'file'        => get_post_meta( $post_id, '_hs_ws_file', true ),
			'body'        => get_post_meta( $post_id, '_hs_ws_body', true ),
			'interactive' => get_post_meta( $post_id, '_hs_ws_interactive', true ),
			'access'      => get_post_meta( $post_id, '_hs_ws_access', true ) ?: 'public',
			'url'         => get_permalink( $post_id ),
		);
	}
}

HS_Worksheet_CPT::init();
