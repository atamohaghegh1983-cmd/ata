<?php
/**
 * متادیتا و متاباکس کلاس
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Class_Meta {

	/** همه‌ی پایه‌ها، کامل */
	public static function grades() {
		return array( 'پیش‌دبستانی', 'اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم' );
	}

	public static function grade_options( $cur = '' ) {
		$h = '<option value="">انتخاب…</option>';
		foreach ( self::grades() as $g ) {
			$h .= '<option value="' . esc_attr( $g ) . '"' . selected( $cur, $g, false ) . '>' . esc_html( $g ) . '</option>';
		}
		return $h;
	}


	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_hs_class', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'wp_insert_post', array( __CLASS__, 'maybe_set_defaults' ), 10, 3 );
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'hs_class_details',
			'جزئیات کلاس',
			array( __CLASS__, 'render_details_box' ),
			'hs_class',
			'normal',
			'high'
		);

		add_meta_box(
			'hs_class_students',
			'دانش‌آموزان کلاس',
			array( __CLASS__, 'render_students_box' ),
			'hs_class',
			'normal',
			'default'
		);
	}

	public static function render_details_box( $post ) {
		wp_nonce_field( 'hs_class_meta_save', 'hs_class_meta_nonce' );

		$grade      = get_post_meta( $post->ID, '_hs_grade', true );
		$year       = get_post_meta( $post->ID, '_hs_year', true );
		$teacher_id = (int) get_post_meta( $post->ID, '_hs_teacher_id', true );
		$invite     = get_post_meta( $post->ID, '_hs_invite_code', true );
		$desc       = get_post_meta( $post->ID, '_hs_description', true );

		if ( ! $teacher_id && $post->post_author ) {
			$teacher_id = (int) $post->post_author;
		}

		$grades = array(
			'پیش‌دبستانی',
			'اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم',
			'هفتم', 'هشتم', 'نهم',
			'دهم', 'یازدهم', 'دوازدهم',
		);

		$current_year = '۱۴۰۵-۱۴۰۶'; // سال تحصیلی پیش‌فرض
		?>
		<table class="form-table hs-meta-table">
			<tr>
				<th><label for="hs_grade">پایه تحصیلی</label></th>
				<td>
					<select name="hs_grade" id="hs_grade">
						<option value="">— انتخاب کنید —</option>
						<?php foreach ( $grades as $g ) : ?>
							<option value="<?php echo esc_attr( $g ); ?>" <?php selected( $grade, $g ); ?>>
								<?php echo esc_html( $g ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="hs_year">سال تحصیلی</label></th>
				<td>
					<input type="text" name="hs_year" id="hs_year" value="<?php echo esc_attr( $year ?: $current_year ); ?>" class="regular-text" placeholder="مثلاً ۱۴۰۵-۱۴۰۶">
				</td>
			</tr>
			<tr>
				<th><label for="hs_school">نام مدرسه</label></th>
				<td><input type="text" name="hs_school" id="hs_school" value="<?php echo esc_attr( get_post_meta( $post->ID, '_hs_school', true ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="hs_province">استان</label></th>
				<td><select name="hs_province" id="hs_province"><?php echo HS_Util::province_options( get_post_meta( $post->ID, '_hs_province', true ) ); // phpcs:ignore ?></select></td>
			</tr>
			<tr>
				<th><label for="hs_city">شهر</label></th>
				<td><input type="text" name="hs_city" id="hs_city" value="<?php echo esc_attr( get_post_meta( $post->ID, '_hs_city', true ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="hs_teacher_id">معلم مسئول</label></th>
				<td>
					<?php
					wp_dropdown_users( array(
						'name'              => 'hs_teacher_id',
						'selected'          => $teacher_id,
						'show_option_none'  => '— انتخاب معلم —',
						'role__in'          => array( 'teacher', 'school_admin', 'administrator' ),
						'class'             => 'regular-text',
					) );
					?>
				</td>
			</tr>
			<tr>
				<th><label>کد دعوت</label></th>
				<td>
					<code style="font-size:1.1em;letter-spacing:1px;"><?php echo esc_html( $invite ?: 'پس از ذخیره تولید می‌شود' ); ?></code>
					<p class="description">دانش‌آموزان و والدین با این کد می‌توانند به کلاس بپیوندند.</p>
				</td>
			</tr>
			<tr>
				<th><label for="hs_description">توضیحات کوتاه</label></th>
				<td>
					<textarea name="hs_description" id="hs_description" rows="3" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function render_students_box( $post ) {
		$students = get_post_meta( $post->ID, '_hs_students', true );
		if ( ! is_array( $students ) ) {
			$students = array();
		}
		?>
		<div class="hs-students-admin">
			<p>تعداد دانش‌آموزان: <strong><?php echo count( $students ); ?></strong></p>
			<?php if ( ! empty( $students ) ) : ?>
				<ul style="max-height:300px;overflow:auto;border:1px solid #ddd;padding:10px;background:#fafafa;">
					<?php foreach ( $students as $uid ) :
						$user = get_userdata( $uid );
						if ( ! $user ) continue;
						?>
						<li>
							<?php echo esc_html( $user->display_name ); ?>
							<small style="color:#666;">(<?php echo esc_html( $user->user_login ); ?>)</small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p style="color:#666;">هنوز دانش‌آموزی اضافه نشده. از پنل فرانت‌اند یا با کد دعوت اضافه کنید.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['hs_class_meta_nonce'] ) || ! wp_verify_nonce( $_POST['hs_class_meta_nonce'], 'hs_class_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['hs_grade'] ) ) {
			update_post_meta( $post_id, '_hs_grade', sanitize_text_field( $_POST['hs_grade'] ) );
		}
		if ( isset( $_POST['hs_year'] ) ) {
			update_post_meta( $post_id, '_hs_year', sanitize_text_field( $_POST['hs_year'] ) );
		}
		foreach ( array( 'school' => 'hs_school', 'city' => 'hs_city', 'province' => 'hs_province' ) as $k => $f ) {
			if ( isset( $_POST[ $f ] ) ) {
				update_post_meta( $post_id, '_hs_' . $k, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
			}
		}
		if ( isset( $_POST['hs_teacher_id'] ) ) {
			update_post_meta( $post_id, '_hs_teacher_id', absint( $_POST['hs_teacher_id'] ) );
		}
		if ( isset( $_POST['hs_description'] ) ) {
			update_post_meta( $post_id, '_hs_description', sanitize_textarea_field( $_POST['hs_description'] ) );
		}

		// تولید کد دعوت اگر وجود ندارد
		$existing = get_post_meta( $post_id, '_hs_invite_code', true );
		if ( empty( $existing ) ) {
			$code = self::generate_invite_code();
			update_post_meta( $post_id, '_hs_invite_code', $code );
		}
	}

	/**
	 * تنظیم مقادیر پیش‌فرض هنگام ایجاد
	 */
	public static function maybe_set_defaults( $post_id, $post, $update ) {
		if ( $update || $post->post_type !== 'hs_class' ) {
			return;
		}
		if ( get_post_meta( $post_id, '_hs_invite_code', true ) ) {
			return;
		}

		update_post_meta( $post_id, '_hs_teacher_id', (int) $post->post_author );
		update_post_meta( $post_id, '_hs_invite_code', self::generate_invite_code() );
		update_post_meta( $post_id, '_hs_students', array() );
		update_post_meta( $post_id, '_hs_year', '۱۴۰۵-۱۴۰۶' );
	}

	/**
	 * تولید کد دعوت یکتا (۶ کاراکتر)
	 */
	public static function generate_invite_code() {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // بدون I,O,0,1 برای خوانایی
		do {
			$code = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
			}
			$exists = self::code_exists( $code );
		} while ( $exists );

		return $code;
	}

	private static function code_exists( $code ) {
		$q = new WP_Query( array(
			'post_type'      => 'hs_class',
			'meta_key'       => '_hs_invite_code',
			'meta_value'     => $code,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		return $q->have_posts();
	}
}

HS_Class_Meta::init();
