<?php
/**
 * کتابخانه کاربرگ + ارسال تکلیف از کاربرگ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Worksheet_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_worksheets', array( __CLASS__, 'render_library' ) );
		add_action( 'wp_ajax_hs_assign_worksheet', array( __CLASS__, 'assign_worksheet' ) );
	}

	/**
	 * کتابخانه عمومی / پنل معلم
	 */
	public static function render_library( $atts = array() ) {
		$atts = shortcode_atts( array(
			'mode' => 'public', // public | teacher
		), $atts, 'hamshagerdi_worksheets' );

		$grade    = isset( $_GET['ws_grade'] ) ? sanitize_text_field( $_GET['ws_grade'] ) : '';
		$subject  = isset( $_GET['ws_subject'] ) ? sanitize_text_field( $_GET['ws_subject'] ) : '';
		$diff     = isset( $_GET['ws_diff'] ) ? sanitize_text_field( $_GET['ws_diff'] ) : '';
		$search   = isset( $_GET['ws_s'] ) ? sanitize_text_field( $_GET['ws_s'] ) : '';
		$paged    = max( 1, isset( $_GET['ws_page'] ) ? absint( $_GET['ws_page'] ) : 1 );

		$query = HS_Worksheet_CPT::query( array(
			'grade'      => $grade,
			'subject'    => $subject,
			'difficulty' => $diff,
			's'          => $search,
			'paged'      => $paged,
			'limit'      => 12,
		) );

		$is_teacher = is_user_logged_in() && HS_Roles::is_teacher_or_admin();
		$classes = array();
		if ( $is_teacher ) {
			$classes = self::teacher_classes();
		}

		ob_start();
		?>
		<div class="hs-ws-library" id="hs-ws-library">
			<div class="hs-att-header">
				<div>
					<?php if ( $is_teacher ) : ?>
						<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<?php endif; ?>
					<h2><?php echo esc_html( HS_Hubs::title( 'worksheets', 'کتابخانه کاربرگ همشاگردی' ) ); ?></h2>
					<p class="hs-muted">کاربرگ‌های قابل چاپ و تمرین‌های آماده برای پایه اول تا ششم</p>
				</div>
			</div>

			<!-- فیلترها -->
			<form class="hs-card hs-ws-filters" method="get" action="">
				<?php
				// حفظ سایر query params صفحه
				foreach ( $_GET as $k => $v ) {
					if ( in_array( $k, array( 'ws_grade', 'ws_subject', 'ws_diff', 'ws_s', 'ws_page' ), true ) ) {
						continue;
					}
					printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $k ), esc_attr( $v ) );
				}
				?>
				<div class="hs-att-filter-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr)) auto;">
					<div class="hs-form-row">
						<label>پایه</label>
						<select name="ws_grade">
							<option value="">همه</option>
							<?php foreach ( HS_Worksheet_CPT::grades() as $k => $v ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $grade, $k ); ?>><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row">
						<label>درس</label>
						<select name="ws_subject">
							<option value="">همه</option>
							<?php foreach ( HS_Worksheet_CPT::subjects() as $k => $v ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $subject, $k ); ?>><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row">
						<label>سطح</label>
						<select name="ws_diff">
							<option value="">همه</option>
							<?php foreach ( HS_Worksheet_CPT::difficulties() as $k => $v ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $diff, $k ); ?>><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row">
						<label>جستجو</label>
						<input type="search" name="ws_s" value="<?php echo esc_attr( $search ); ?>" placeholder="موضوع یا عنوان...">
					</div>
					<div class="hs-form-row hs-att-filter-actions">
						<label>&nbsp;</label>
						<button type="submit" class="hs-btn hs-btn-primary">اعمال فیلتر</button>
					</div>
				</div>
			</form>

			<?php if ( ! $query->have_posts() ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">📄</div>
					<h3>کاربرگی با این فیلتر یافت نشد</h3>
					<p class="hs-muted">از پیشخوان وردپرس → کاربرگ‌های همشاگردی، کاربرگ جدید اضافه کنید.</p>
				</div>
			<?php else : ?>
				<div class="hs-ws-grid">
					<?php
					while ( $query->have_posts() ) {
						$query->the_post();
						$card = HS_Worksheet_CPT::get_card_data( get_the_ID() );
						echo self::render_card( $card, $is_teacher, $classes );
					}
					wp_reset_postdata();
					?>
				</div>

				<?php if ( $query->max_num_pages > 1 ) : ?>
					<div class="hs-ws-pagination">
						<?php
						for ( $i = 1; $i <= $query->max_num_pages; $i++ ) {
							$url = add_query_arg( 'ws_page', $i );
							$cls = $i === $paged ? 'hs-btn hs-btn-primary hs-btn-sm' : 'hs-btn hs-btn-ghost hs-btn-sm';
							printf( '<a class="%s" href="%s">%d</a> ', esc_attr( $cls ), esc_url( $url ), $i );
						}
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $is_teacher && ! empty( $classes ) ) : ?>
			<!-- مودال ارسال به کلاس -->
			<div class="hs-report-modal" id="hs-ws-assign-modal" style="display:none;">
				<div class="hs-report-modal-backdrop" id="hs-ws-assign-close"></div>
				<div class="hs-report-modal-box" style="max-width:440px;">
					<button type="button" class="hs-report-modal-x" id="hs-ws-assign-x">×</button>
					<h3 style="margin-top:0;">ارسال کاربرگ به کلاس</h3>
					<p class="hs-muted" id="hs-ws-assign-title"></p>
					<form id="hs-form-assign-ws">
						<input type="hidden" id="hs-ws-assign-id" value="">
						<div class="hs-form-row">
							<label>کلاس</label>
							<select id="hs-ws-assign-class" required>
								<?php foreach ( $classes as $c ) : ?>
									<option value="<?php echo esc_attr( $c['id'] ); ?>"><?php echo esc_html( $c['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="hs-form-row">
							<label>مهلت تحویل</label>
							<input type="date" id="hs-ws-assign-due">
						</div>
						<div class="hs-form-row">
							<label>توضیح برای دانش‌آموز / والد</label>
							<textarea id="hs-ws-assign-note" rows="2" placeholder="اختیاری..."></textarea>
						</div>
						<button type="submit" class="hs-btn hs-btn-primary">ارسال به‌عنوان تکلیف شب</button>
						<div class="hs-form-message" id="hs-ws-assign-msg"></div>
					</form>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_card( $card, $is_teacher, $classes ) {
		ob_start();
		?>
		<article class="hs-ws-card">
			<div class="hs-ws-card-thumb">
				<?php if ( $card['thumb'] ) : ?>
					<img src="<?php echo esc_url( $card['thumb'] ); ?>" alt="" loading="lazy">
				<?php else : ?>
					<div class="hs-ws-card-placeholder">📄</div>
				<?php endif; ?>
				<?php if ( $card['diff_label'] ) : ?>
					<span class="hs-ws-badge hs-ws-diff-<?php echo esc_attr( $card['difficulty'] ); ?>"><?php echo esc_html( $card['diff_label'] ); ?></span>
				<?php endif; ?>
			</div>
			<div class="hs-ws-card-body">
				<div class="hs-ws-card-meta">
					<?php if ( $card['grade_label'] ) : ?>
						<span><?php echo esc_html( $card['grade_label'] ); ?></span>
					<?php endif; ?>
					<?php if ( $card['subject_label'] ) : ?>
						<span><?php echo esc_html( $card['subject_label'] ); ?></span>
					<?php endif; ?>
				</div>
				<h3><?php echo esc_html( $card['title'] ); ?></h3>
				<?php if ( $card['topic'] ) : ?>
					<p class="hs-ws-topic"><?php echo esc_html( $card['topic'] ); ?></p>
				<?php endif; ?>
				<div class="hs-ws-card-stats">
					<?php if ( $card['duration'] ) : ?>
						<span>⏱ <?php echo (int) $card['duration']; ?> دقیقه</span>
					<?php endif; ?>
					<?php if ( $card['qcount'] ) : ?>
						<span>❓ <?php echo (int) $card['qcount']; ?> سؤال</span>
					<?php endif; ?>
					<?php if ( $card['type_label'] ) : ?>
						<span><?php echo esc_html( $card['type_label'] ); ?></span>
					<?php endif; ?>
				</div>
				<div class="hs-ws-card-actions">
					<?php if ( $card['pdf'] ) : ?>
						<a href="<?php echo esc_url( $card['pdf'] ); ?>" class="hs-btn hs-btn-sm hs-btn-primary" target="_blank" rel="noopener">دانلود PDF</a>
					<?php endif; ?>
					<?php if ( $card['interactive'] ) : ?>
						<a href="<?php echo esc_url( $card['interactive'] ); ?>" class="hs-btn hs-btn-sm hs-btn-ghost" target="_blank" rel="noopener">نسخه آنلاین</a>
					<?php endif; ?>
					<?php if ( $is_teacher && ! empty( $classes ) ) : ?>
						<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-ws-assign-btn"
							data-id="<?php echo esc_attr( $card['id'] ); ?>"
							data-title="<?php echo esc_attr( $card['title'] ); ?>">
							ارسال به کلاس
						</button>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * ارسال کاربرگ به‌عنوان تکلیف شب (اتصال به سیستم تکالیف)
	 */
	public static function assign_worksheet() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده.' ) );
		}
		if ( ! is_user_logged_in() || ! HS_Roles::is_teacher_or_admin() ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$ws_id    = isset( $_POST['worksheet_id'] ) ? absint( $_POST['worksheet_id'] ) : 0;
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$due      = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $ws_id || ! $class_id || ! HS_Roles::can_manage_class( $class_id ) ) {
			wp_send_json_error( array( 'message' => 'اطلاعات نامعتبر.' ) );
		}

		$ws = get_post( $ws_id );
		if ( ! $ws || $ws->post_type !== HS_Worksheet_CPT::POST_TYPE ) {
			wp_send_json_error( array( 'message' => 'کاربرگ یافت نشد.' ) );
		}

		$card = HS_Worksheet_CPT::get_card_data( $ws_id );
		$desc_parts = array();
		if ( $note ) {
			$desc_parts[] = $note;
		}
		if ( $card['topic'] ) {
			$desc_parts[] = 'موضوع: ' . $card['topic'];
		}
		if ( $card['pdf'] ) {
			$desc_parts[] = 'دانلود: ' . $card['pdf'];
		}
		if ( $card['interactive'] ) {
			$desc_parts[] = 'نسخه آنلاین: ' . $card['interactive'];
		}

		$subject_label = $card['subject_label'] ?: '';
		$hw_id = HS_Homework_DB::create(
			$class_id,
			$ws->post_title,
			implode( "\n", $desc_parts ),
			$subject_label,
			$due ?: null
		);

		// ذخیره لینک به کاربرگ
		global $wpdb;
		// meta روی تکلیف نداریم؛ از description استفاده شد. می‌توانیم option جدا نگه داریم:
		update_option( 'hs_hw_ws_' . $hw_id, $ws_id, false );

		wp_send_json_success( array(
			'message'     => 'تکلیف شب برای کلاس ثبت شد.',
			'homework_id' => $hw_id,
			'redirect'    => add_query_arg( array(
				'class_id'    => $class_id,
				'homework_id' => $hw_id,
			), home_url( '/panel/homework/' ) ),
		) );
	}

	private static function teacher_classes() {
		$user_id  = get_current_user_id();
		$is_admin = current_user_can( 'hs_manage_all_classes' );
		$args = array(
			'post_type'      => 'hs_class',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! $is_admin ) {
			$args['meta_query'] = array(
				array( 'key' => '_hs_teacher_id', 'value' => $user_id ),
			);
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

HS_Worksheet_Frontend::init();
