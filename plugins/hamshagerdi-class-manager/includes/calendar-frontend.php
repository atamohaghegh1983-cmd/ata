<?php
/**
 * تقویم مناسبتی — رابط کاربری
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Calendar_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_calendar', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_hs_cal_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_hs_cal_delete', array( __CLASS__, 'ajax_delete' ) );
	}

	public static function render( $atts = array() ) {
		// اگر افزونه‌ی «تقویم مناسبتی» فعال باشد، همان موتور اصلی است (یک تقویم واحد)
		if ( shortcode_exists( 'hamshagerdi_occasions' ) ) {
			return do_shortcode( '[hamshagerdi_occasions]' );
		}
		if ( ! is_user_logged_in() ) {
			return '<p>برای مشاهده تقویم وارد شوید.</p>';
		}

		$can_edit = current_user_can( 'manage_options' )
			|| HS_Roles::is_teacher_or_admin()
			|| in_array( 'school_admin', (array) wp_get_current_user()->roles, true );

		$events = HS_Calendar_DB::upcoming( 40 );
		$types = HS_Calendar_DB::types();
		$audiences = HS_Calendar_DB::audiences();

		// کلاس‌های معلم برای انتخاب
		$classes = array();
		if ( $can_edit ) {
			$q = new WP_Query( array(
				'post_type' => 'hs_class',
				'posts_per_page' => 50,
				'post_status' => 'publish',
			) );
			foreach ( $q->posts as $p ) {
				$classes[ $p->ID ] = $p->post_title;
			}
		}

		ob_start();
		?>
		<div class="hs-cal" id="hs-cal">
			<div class="hs-cal-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← داشبورد</a>
					<h2>📅 تقویم مناسبتی</h2>
					<p class="hs-muted">مناسبت‌ها، جلسات و تعطیلی‌ها — یادآوری خودکار از یک هفته قبل</p>
				</div>
			</div>

			<?php if ( $can_edit ) : ?>
			<div class="hs-card hs-cal-form-card">
				<h3>افزودن رویداد</h3>
				<form id="hs-cal-form" class="hs-cal-form">
					<div class="hs-form-grid">
						<div class="hs-form-row">
							<label>عنوان *</label>
							<input type="text" name="title" id="hs-cal-title" required placeholder="مثلاً: روز معلم">
						</div>
						<div class="hs-form-row">
							<label>تاریخ *</label>
							<input type="date" name="event_date" id="hs-cal-date" required>
						</div>
						<div class="hs-form-row">
							<label>نوع</label>
							<select name="event_type" id="hs-cal-type">
								<?php foreach ( $types as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="hs-form-row">
							<label>مخاطب یادآوری</label>
							<select name="audience" id="hs-cal-audience">
								<?php foreach ( $audiences as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="hs-form-row">
							<label>کلاس (اگر مخاطب «یک کلاس» باشد)</label>
							<select name="class_id" id="hs-cal-class">
								<option value="0">—</option>
								<?php foreach ( $classes as $id => $title ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="hs-form-row">
							<label>یادآوری چند روز قبل؟</label>
							<input type="number" name="remind_days" id="hs-cal-remind" value="7" min="1" max="30">
						</div>
					</div>
					<div class="hs-form-row">
						<label>توضیح / برنامه پیشنهادی</label>
						<textarea name="description" id="hs-cal-desc" rows="2" placeholder="مثلاً: تهیه کارت تبریک، هماهنگی با والدین..."></textarea>
					</div>
					<button type="submit" class="hs-btn hs-btn-primary">ثبت رویداد</button>
					<div class="hs-form-message" id="hs-cal-msg"></div>
				</form>
			</div>
			<?php endif; ?>

			<div class="hs-card">
				<h3>رویدادهای پیش‌رو</h3>
				<?php if ( empty( $events ) ) : ?>
					<p class="hs-muted">رویدادی ثبت نشده است.</p>
				<?php else : ?>
					<ul class="hs-cal-list">
						<?php foreach ( $events as $ev ) :
							$days_left = (int) ( ( strtotime( $ev['event_date'] ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
							?>
							<li class="hs-cal-item type-<?php echo esc_attr( $ev['event_type'] ); ?>" data-id="<?php echo esc_attr( $ev['id'] ); ?>">
								<div class="hs-cal-date">
									<strong><?php echo esc_html( $ev['event_date'] ); ?></strong>
									<small>
										<?php
										if ( $days_left === 0 ) {
											echo 'امروز';
										} elseif ( $days_left === 1 ) {
											echo 'فردا';
										} else {
											echo $days_left . ' روز دیگر';
										}
										?>
									</small>
								</div>
								<div class="hs-cal-body">
									<strong><?php echo esc_html( $ev['title'] ); ?></strong>
									<span class="hs-cal-tags">
										<?php echo esc_html( $types[ $ev['event_type'] ] ?? '' ); ?>
										· یادآوری <?php echo (int) $ev['remind_days']; ?> روز قبل
										<?php if ( ! empty( $ev['reminded'] ) ) echo ' · ✓ یادآوری شد'; ?>
									</span>
									<?php if ( $ev['description'] ) : ?>
										<p><?php echo esc_html( $ev['description'] ); ?></p>
									<?php endif; ?>
								</div>
								<?php if ( $can_edit ) : ?>
									<button type="button" class="hs-cal-del" data-id="<?php echo esc_attr( $ev['id'] ); ?>">حذف</button>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<?php if ( is_user_logged_in() ) : ?>
			<div class="hs-card">
				<?php echo do_shortcode( '[hamshagerdi_bale_link]' ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_save() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست نامعتبر' ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! HS_Roles::is_teacher_or_admin() ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید' ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$date = isset( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : '';
		if ( ! $title || ! $date ) {
			wp_send_json_error( array( 'message' => 'عنوان و تاریخ الزامی است.' ) );
		}

		$id = HS_Calendar_DB::insert( array(
			'title'       => $title,
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'event_date'  => $date,
			'event_type'  => isset( $_POST['event_type'] ) ? sanitize_key( $_POST['event_type'] ) : 'occasion',
			'audience'    => isset( $_POST['audience'] ) ? sanitize_key( $_POST['audience'] ) : 'teachers',
			'class_id'    => isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0,
			'remind_days' => isset( $_POST['remind_days'] ) ? absint( $_POST['remind_days'] ) : 7,
		) );

		wp_send_json_success( array( 'message' => 'رویداد ثبت شد.', 'id' => $id ) );
	}

	public static function ajax_delete() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست نامعتبر' ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! HS_Roles::is_teacher_or_admin() ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید' ) );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		HS_Calendar_DB::delete( $id );
		wp_send_json_success( array( 'message' => 'حذف شد.' ) );
	}
}

HS_Calendar_Frontend::init();
