<?php
/**
 * رابط تکلیف آنلاین + دفتر تکالیف دانش‌آموز/والد
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Online_HW_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_online_hw', array( __CLASS__, 'render_take' ) );
		add_shortcode( 'hamshagerdi_notebook', array( __CLASS__, 'render_notebook' ) );
		add_shortcode( 'hamshagerdi_online_builder', array( __CLASS__, 'render_builder' ) );
	}

	/**
	 * سازنده تکلیف آنلاین برای معلم
	 */
	public static function render_builder( $atts = array() ) {
		if ( ! is_user_logged_in() || ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">فقط معلمان.</div>';
		}

		$classes = self::teacher_classes();
		$selected = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : ( $classes[0]['id'] ?? 0 );

		ob_start();
		?>
		<div class="hs-online-builder" id="hs-online-builder" data-class-id="<?php echo esc_attr( $selected ); ?>">
			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2><?php echo esc_html( HS_Hubs::title( 'online-builder', 'تکلیف‌ساز آنلاین' ) ); ?></h2>
					<p class="hs-muted">سؤال چندگزینه‌ای، درست/نادرست و جای‌خالی با تصحیح خودکار</p>
				</div>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state"><p>ابتدا کلاس بسازید.</p></div>
			<?php else : ?>

			<form id="hs-form-online-hw" class="hs-card">
				<div class="hs-form-grid">
					<div class="hs-form-row">
						<label>کلاس</label>
						<select id="hs-oh-class">
							<?php foreach ( $classes as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected, $c['id'] ); ?>><?php echo esc_html( $c['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row">
						<label>مهلت</label>
						<input type="date" id="hs-oh-due">
					</div>
				</div>
				<div class="hs-form-row">
					<label>عنوان تکلیف <span class="required">*</span></label>
					<input type="text" id="hs-oh-title" required placeholder="مثلاً: تمرین نشانه ب — آنلاین">
				</div>
				<div class="hs-form-grid">
					<div class="hs-form-row">
						<label>درس</label>
						<input type="text" id="hs-oh-subject" placeholder="فارسی، ریاضی...">
					</div>
					<div class="hs-form-row">
						<label>توضیح</label>
						<input type="text" id="hs-oh-desc" placeholder="اختیاری">
					</div>
				</div>

				<hr style="border:none;border-top:1px solid #E2E8F0;margin:1.25rem 0;">
				<div class="hs-card-header-row">
					<h3 style="margin:0;">سؤالات</h3>
					<button type="button" class="hs-btn hs-btn-sm hs-btn-primary" id="hs-oh-add-q">+ سؤال</button>
				</div>
				<div id="hs-oh-questions"></div>

				<div class="hs-form-actions" style="margin-top:1.25rem;">
					<button type="submit" class="hs-btn hs-btn-primary hs-btn-lg">ساخت و انتشار تکلیف آنلاین</button>
				</div>
				<div class="hs-form-message" id="hs-oh-msg"></div>
			</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * صفحه پاسخ به تکلیف آنلاین
	 */
	public static function render_take( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای پاسخ به تکلیف وارد شوید.</div>';
		}

		$hw_id = isset( $_GET['homework_id'] ) ? absint( $_GET['homework_id'] ) : 0;
		if ( ! $hw_id && ! empty( $atts['id'] ) ) {
			$hw_id = absint( $atts['id'] );
		}
		if ( ! $hw_id ) {
			return '<div class="hs-alert hs-alert-warning">تکلیف مشخص نشده.</div>';
		}

		$hw = HS_Homework_DB::get( $hw_id );
		if ( ! $hw ) {
			return '<div class="hs-alert hs-alert-error">تکلیف یافت نشد.</div>';
		}

		$questions = HS_Online_HW_DB::get_questions( $hw_id );
		$student_id = get_current_user_id();

		// اگر معلم است و student_id در URL دارد
		if ( isset( $_GET['student_id'] ) && HS_Roles::can_manage_class( $hw['class_id'] ) ) {
			$student_id = absint( $_GET['student_id'] );
		}

		$submission = HS_Online_HW_DB::get_submission( $hw_id, $student_id );
		$already = ! empty( $submission );

		ob_start();
		?>
		<div class="hs-online-take" id="hs-online-take"
			 data-homework-id="<?php echo esc_attr( $hw_id ); ?>"
			 data-student-id="<?php echo esc_attr( $student_id ); ?>">

			<div class="hs-att-header">
				<div>
					<h2><?php echo esc_html( $hw['title'] ); ?></h2>
					<p class="hs-muted">
						<?php if ( $hw['subject'] ) echo esc_html( $hw['subject'] ) . ' · '; ?>
						<?php if ( $hw['due_date'] ) echo 'مهلت: ' . esc_html( $hw['due_date'] ); ?>
						· <?php echo count( $questions ); ?> سؤال
					</p>
					<?php if ( $hw['description'] ) : ?>
						<p><?php echo esc_html( $hw['description'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( empty( $questions ) ) : ?>
				<div class="hs-empty-state"><p>این تکلیف هنوز سؤال آنلاین ندارد.</p></div>
			<?php elseif ( $already ) : ?>
				<div class="hs-card hs-online-result">
					<h3>پاسخ شما ثبت شده است</h3>
					<div class="hs-sr-grid">
						<div class="hs-sr-stat">
							<div class="num"><?php echo esc_html( $submission['auto_score'] ); ?></div>
							<div class="lbl">نمره شما</div>
						</div>
						<div class="hs-sr-stat">
							<div class="num"><?php echo esc_html( $submission['max_score'] ); ?></div>
							<div class="lbl">از حداکثر</div>
						</div>
					</div>
					<p class="hs-muted">زمان ارسال: <?php echo esc_html( $submission['submitted_at'] ); ?></p>
					<?php if ( ! empty( $submission['image_url'] ) ) : ?>
						<p><a href="<?php echo esc_url( $submission['image_url'] ); ?>" target="_blank">مشاهده تصویر ارسالی</a></p>
					<?php endif; ?>
				</div>
			<?php else : ?>

			<form id="hs-form-take-hw" class="hs-card">
				<?php
				$n = 1;
				foreach ( $questions as $q ) :
					$qid = (int) $q['id'];
					$type = $q['question_type'];
					?>
					<div class="hs-oq" data-qid="<?php echo esc_attr( $qid ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
						<div class="hs-oq-head">
							<span class="hs-oq-num"><?php echo $n++; ?></span>
							<div class="hs-oq-prompt"><?php echo esc_html( $q['prompt'] ); ?></div>
							<span class="hs-oq-pts"><?php echo (int) $q['points']; ?> نمره</span>
						</div>
						<?php if ( ! empty( $q['image_url'] ) ) : ?>
							<img class="hs-oq-img" src="<?php echo esc_url( $q['image_url'] ); ?>" alt="">
						<?php endif; ?>

						<div class="hs-oq-answer">
							<?php if ( $type === 'multiple_choice' || $type === 'image_choice' ) : ?>
								<?php foreach ( $q['options'] as $i => $opt ) :
									$val = is_array( $opt ) ? ( $opt['text'] ?? '' ) : $opt;
									?>
									<label class="hs-oq-option">
										<input type="radio" name="q_<?php echo esc_attr( $qid ); ?>" value="<?php echo esc_attr( $val ); ?>">
										<span><?php echo esc_html( $val ); ?></span>
									</label>
								<?php endforeach; ?>
							<?php elseif ( $type === 'true_false' ) : ?>
								<label class="hs-oq-option"><input type="radio" name="q_<?php echo esc_attr( $qid ); ?>" value="true"><span>درست</span></label>
								<label class="hs-oq-option"><input type="radio" name="q_<?php echo esc_attr( $qid ); ?>" value="false"><span>نادرست</span></label>
							<?php elseif ( $type === 'fill_blank' ) : ?>
								<input type="text" class="hs-oq-fill" name="q_<?php echo esc_attr( $qid ); ?>" placeholder="پاسخ خود را بنویسید...">
							<?php else : ?>
								<textarea class="hs-oq-fill" name="q_<?php echo esc_attr( $qid ); ?>" rows="3" placeholder="پاسخ تشریحی..."></textarea>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<div class="hs-form-row" style="margin-top:1rem;">
					<label>عکس پاسخ (اختیاری — اگر روی کاغذ نوشتید)</label>
					<?php echo HS_Upload::picker( 'hs-oh-image', 'general', array( 'label' => 'گرفتن/انتخاب عکس پاسخ' ) ); // phpcs:ignore ?>
				</div>

				<div class="hs-form-actions">
					<button type="submit" class="hs-btn hs-btn-primary hs-btn-lg">ارسال پاسخ‌ها</button>
				</div>
				<div class="hs-form-message" id="hs-take-msg"></div>
			</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * دفتر تکالیف دانش‌آموز / والد
	 */
	public static function render_notebook( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای مشاهده دفتر تکالیف وارد شوید.</div>';
		}

		$user_id = get_current_user_id();
		// والد: اگر student_id داده شود و دسترسی داشته باشد — فعلاً خود کاربر یا معلم
		$student_id = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : $user_id;
		$class_id = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;

		// امنیت: فقط خود دانش‌آموز، والدِ تأییدشده یا معلمِ یکی از کلاس‌هایش
		if ( $student_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			$allowed = class_exists( 'HS_Parent_Link' ) && HS_Parent_Link::can_view( $user_id, $student_id );
			if ( ! $allowed ) {
				$sc = get_user_meta( $student_id, '_hs_classes', true );
				foreach ( (array) $sc as $c ) {
					if ( HS_Roles::can_manage_class( (int) $c ) ) {
						$allowed = true;
						break;
					}
				}
			}
			if ( ! $allowed ) {
				$student_id = $user_id;
			}
		}

		// کلاس‌های دانش‌آموز
		$user_classes = get_user_meta( $student_id, '_hs_classes', true );
		if ( ! is_array( $user_classes ) ) {
			$user_classes = array();
		}

		// اگر معلم است و class_id دارد
		if ( ! $class_id && HS_Roles::is_teacher_or_admin() ) {
			$classes = self::teacher_classes();
			$class_id = $classes[0]['id'] ?? 0;
		} elseif ( ! $class_id && ! empty( $user_classes ) ) {
			$class_id = (int) $user_classes[0];
		}

		if ( ! $class_id ) {
			return '<div class="hs-empty-state"><p>کلاسی برای نمایش تکالیف یافت نشد.</p></div>';
		}

		$can_view = ( $student_id === $user_id )
			|| HS_Roles::can_manage_class( $class_id )
			|| in_array( $class_id, array_map( 'absint', $user_classes ), true );

		if ( ! $can_view ) {
			return '<div class="hs-alert hs-alert-warning">دسترسی ندارید.</div>';
		}

		$items = HS_Online_HW_DB::student_notebook( $class_id, $student_id );
		$student = get_userdata( $student_id );
		$class = get_post( $class_id );

		ob_start();
		?>
		<div class="hs-notebook">
			<div class="hs-att-header">
				<div>
					<h2><?php echo esc_html( HS_Hubs::title( 'notebook', 'دفتر تکالیف' ) ); ?></h2>
					<p class="hs-muted">
						<?php echo $student ? esc_html( $student->display_name ) : ''; ?>
						<?php if ( $class ) echo ' · ' . esc_html( $class->post_title ); ?>
					</p>
				</div>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="hs-empty-state"><p>هنوز تکلیفی برای این کلاس ثبت نشده.</p></div>
			<?php else : ?>
				<ul class="hs-nb-list">
					<?php foreach ( $items as $item ) :
						$hw = $item['homework'];
						$sub = $item['submission'];
						$st  = $item['status'];
						$done = $sub || ( $st && $st['status'] === 'done' );
						$overdue = $hw['due_date'] && $hw['due_date'] < current_time( 'Y-m-d' ) && ! $done;
						$mode = isset( $hw['mode'] ) ? $hw['mode'] : 'simple';
						$take_url = add_query_arg( array(
							'homework_id' => $hw['id'],
							'student_id'  => $student_id,
						), home_url( '/panel/online-hw/' ) );
						?>
						<li class="hs-nb-item <?php echo $done ? 'done' : ( $overdue ? 'overdue' : '' ); ?>">
							<div class="hs-nb-info">
								<strong><?php echo esc_html( $hw['title'] ); ?></strong>
								<span class="hs-muted">
									<?php if ( $hw['subject'] ) echo esc_html( $hw['subject'] ) . ' · '; ?>
									<?php if ( $hw['due_date'] ) echo 'مهلت: ' . esc_html( $hw['due_date'] ); ?>
									<?php if ( $item['questions'] ) echo ' · ' . $item['questions'] . ' سؤال'; ?>
								</span>
							</div>
							<div class="hs-nb-status">
								<?php if ( $sub ) : ?>
									<span class="hs-status-pill hs-status-active">
										ارسال‌شده · <?php echo esc_html( $sub['auto_score'] . '/' . $sub['max_score'] ); ?>
									</span>
								<?php elseif ( $done ) : ?>
									<span class="hs-status-pill hs-status-active">انجام‌شده</span>
								<?php elseif ( $overdue ) : ?>
									<span class="hs-status-pill hs-status-inactive">گذشته از مهلت</span>
								<?php else : ?>
									<span class="hs-status-pill hs-status-pending">انجام نشده</span>
								<?php endif; ?>
								<?php if ( $mode === 'online' && ! $sub ) : ?>
									<a href="<?php echo esc_url( $take_url ); ?>" class="hs-btn hs-btn-sm hs-btn-primary">شروع</a>
								<?php elseif ( $mode === 'online' && $sub ) : ?>
									<a href="<?php echo esc_url( $take_url ); ?>" class="hs-btn hs-btn-sm hs-btn-ghost">نتیجه</a>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function teacher_classes() {
		$user_id  = get_current_user_id();
		$is_admin = current_user_can( 'hs_manage_all_classes' );
		$args = array(
			'post_type' => 'hs_class', 'post_status' => 'publish', 'posts_per_page' => 50,
			'orderby' => 'title', 'order' => 'ASC',
		);
		if ( ! $is_admin ) {
			$args['meta_query'] = array( array( 'key' => '_hs_teacher_id', 'value' => $user_id ) );
		}
		$q = new WP_Query( $args );
		$out = array();
		while ( $q->have_posts() ) {
			$q->the_post();
			$out[] = array( 'id' => get_the_ID(), 'title' => get_the_title() );
		}
		wp_reset_postdata();
		return $out;
	}
}

HS_Online_HW_Frontend::init();
