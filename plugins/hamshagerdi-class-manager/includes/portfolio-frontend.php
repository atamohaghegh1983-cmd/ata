<?php
/**
 * رابط کاربری پورتفولیو
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Portfolio_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_portfolio', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای استفاده باید وارد شوید.</div>';
		}
		if ( ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان در دسترس است.</div>';
		}

		$classes = self::teacher_classes();
		$selected = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;
		if ( ! $selected && ! empty( $classes ) ) {
			$selected = $classes[0]['id'];
		}
		$filter_student = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;

		ob_start();
		?>
		<div class="hs-portfolio-panel" id="hs-portfolio-panel" data-class-id="<?php echo esc_attr( $selected ); ?>">

			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2>پورتفولیو دانش‌آموزان</h2>
					<p class="hs-muted">ثبت و نمایش آثار، پروژه‌ها و دستاوردهای دانش‌آموزان</p>
				</div>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">📁</div>
					<h3>هنوز کلاسی ندارید</h3>
					<a href="<?php echo esc_url( home_url( '/panel/classes/' ) ); ?>" class="hs-btn hs-btn-primary">+ ساخت کلاس</a>
				</div>
			<?php else : ?>

			<div class="hs-card hs-att-filters">
				<div class="hs-att-filter-row" style="grid-template-columns:1fr 1fr auto;">
					<div class="hs-form-row">
						<label>کلاس</label>
						<select id="hs-pf-class">
							<?php foreach ( $classes as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected, $c['id'] ); ?>>
									<?php echo esc_html( $c['title'] ); ?>
									<?php if ( $c['grade'] ) echo ' (' . esc_html( $c['grade'] ) . ')'; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row">
						<label>فیلتر دانش‌آموز</label>
						<select id="hs-pf-student-filter">
							<option value="0">همه دانش‌آموزان</option>
							<?php
							if ( $selected && HS_Roles::can_manage_class( $selected ) ) {
								foreach ( HS_Class_Students::get_students( $selected ) as $s ) {
									printf(
										'<option value="%d" %s>%s</option>',
										$s->ID,
										selected( $filter_student, $s->ID, false ),
										esc_html( $s->display_name )
									);
								}
							}
							?>
						</select>
					</div>
					<div class="hs-form-row hs-att-filter-actions">
						<label>&nbsp;</label>
						<button type="button" class="hs-btn hs-btn-primary" id="hs-pf-load">نمایش</button>
					</div>
				</div>
			</div>

			<?php
			if ( $selected && HS_Roles::can_manage_class( $selected ) ) {
				$students = HS_Class_Students::get_students( $selected );
				$args = array( 'limit' => 80 );
				if ( $filter_student ) {
					$args['student_id'] = $filter_student;
				}
				$items = HS_Portfolio_DB::get_by_class( $selected, $args );
				$total = HS_Portfolio_DB::count_by_class( $selected );
				$pending = HS_Portfolio_DB::count_by_class( $selected, 'pending' );

				echo self::render_add_form( $selected, $students );
				echo self::render_gallery( $items, $total, $pending );
			}
			?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_add_form( $class_id, $students ) {
		$types = HS_Portfolio_DB::types();
		ob_start();
		?>
		<div class="hs-card">
			<h3>افزودن اثر به پورتفولیو</h3>
			<form id="hs-form-add-portfolio">
				<div class="hs-form-grid">
					<div class="hs-form-row">
						<label>دانش‌آموز <span class="required">*</span></label>
						<select id="hs-pf-student" required>
							<option value="">— انتخاب کنید —</option>
							<?php foreach ( $students as $s ) : ?>
								<option value="<?php echo esc_attr( $s->ID ); ?>"><?php echo esc_html( $s->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row">
						<label>نوع اثر</label>
						<select id="hs-pf-type">
							<?php foreach ( $types as $key => $t ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>">
									<?php echo esc_html( $t['icon'] . ' ' . $t['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="hs-form-row">
					<label>عنوان <span class="required">*</span></label>
					<input type="text" id="hs-pf-title" placeholder="مثلاً: نقاشی فصل پاییز" required>
				</div>
				<div class="hs-form-row">
					<label>توضیحات</label>
					<textarea id="hs-pf-desc" rows="2" placeholder="توضیح کوتاه درباره این اثر..."></textarea>
				</div>
				<div class="hs-form-row">
					<label>تصویر اثر (اختیاری)</label>
					<?php echo HS_Upload::picker( 'hs-pf-media', 'general', array( 'label' => 'انتخاب عکس اثر' ) ); // phpcs:ignore ?>
				</div>
				<div class="hs-form-actions">
					<button type="submit" class="hs-btn hs-btn-primary">+ افزودن به پورتفولیو</button>
				</div>
				<div class="hs-form-message" id="hs-pf-msg"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_gallery( $items, $total, $pending ) {
		$types = HS_Portfolio_DB::types();
		$statuses = HS_Portfolio_DB::statuses();

		ob_start();
		?>
		<div class="hs-card">
			<div class="hs-card-header-row">
				<h3>آثار پورتفولیو <span class="hs-count-badge"><?php echo (int) $total; ?></span></h3>
				<?php if ( $pending > 0 ) : ?>
					<span class="hs-badge" style="background:#FEF3C7;color:#92400E;"><?php echo (int) $pending; ?> در انتظار تأیید</span>
				<?php endif; ?>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<p class="hs-muted">هنوز اثری ثبت نشده. از فرم بالا اولین اثر را اضافه کنید.</p>
			<?php else : ?>
				<div class="hs-pf-gallery" id="hs-pf-gallery">
					<?php foreach ( $items as $item ) :
						$user = get_userdata( $item['student_id'] );
						$type = isset( $types[ $item['item_type'] ] ) ? $types[ $item['item_type'] ] : $types['other'];
						$st   = isset( $statuses[ $item['status'] ] ) ? $statuses[ $item['status'] ] : $statuses['approved'];
						$has_img = ! empty( $item['media_url'] ) && preg_match( '/\.(jpe?g|png|gif|webp)(\?|$)/i', $item['media_url'] );
						?>
						<article class="hs-pf-card" data-id="<?php echo esc_attr( $item['id'] ); ?>" data-status="<?php echo esc_attr( $item['status'] ); ?>">
							<?php if ( $has_img ) : ?>
								<div class="hs-pf-thumb">
									<img src="<?php echo esc_url( $item['media_url'] ); ?>" alt="" loading="lazy">
								</div>
							<?php else : ?>
								<div class="hs-pf-thumb hs-pf-thumb-icon">
									<span><?php echo esc_html( $type['icon'] ); ?></span>
								</div>
							<?php endif; ?>
							<div class="hs-pf-body">
								<div class="hs-pf-meta">
									<span class="hs-pf-type"><?php echo esc_html( $type['icon'] . ' ' . $type['label'] ); ?></span>
									<span class="hs-pf-status" style="color:<?php echo esc_attr( $st['color'] ); ?>">
										<?php echo esc_html( $st['label'] ); ?>
									</span>
								</div>
								<h4><?php echo esc_html( $item['title'] ); ?></h4>
								<p class="hs-pf-student"><?php echo $user ? esc_html( $user->display_name ) : '—'; ?></p>
								<?php if ( $item['description'] ) : ?>
									<p class="hs-pf-desc"><?php echo esc_html( mb_substr( $item['description'], 0, 100 ) ); ?></p>
								<?php endif; ?>
								<?php if ( $item['media_url'] && ! $has_img ) : ?>
									<a href="<?php echo esc_url( $item['media_url'] ); ?>" target="_blank" rel="noopener" class="hs-pf-link">مشاهده فایل ↗</a>
								<?php endif; ?>
								<div class="hs-pf-actions">
									<?php if ( $item['status'] !== 'approved' ) : ?>
										<button type="button" class="hs-btn hs-btn-sm hs-btn-primary hs-pf-approve" data-id="<?php echo esc_attr( $item['id'] ); ?>">تأیید</button>
									<?php endif; ?>
									<?php if ( $item['status'] !== 'rejected' ) : ?>
										<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-pf-reject" data-id="<?php echo esc_attr( $item['id'] ); ?>">رد</button>
									<?php endif; ?>
									<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-pf-delete" data-id="<?php echo esc_attr( $item['id'] ); ?>">حذف</button>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
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
			$out[] = array(
				'id'    => get_the_ID(),
				'title' => get_the_title(),
				'grade' => get_post_meta( get_the_ID(), '_hs_grade', true ),
			);
		}
		wp_reset_postdata();
		return $out;
	}
}

HS_Portfolio_Frontend::init();
