<?php
/**
 * راه‌اندازی خودکار صفحات پنل معلم
 *
 * صفحات ساخته‌شده:
 * - /panel/           → قالب «پنل معلم» (داشبورد)
 * - /panel/classes/   → [hamshagerdi_classes]
 * - /panel/attendance/→ [hamshagerdi_attendance]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Setup_Pages {

	/**
	 * تعریف صفحات مورد نیاز
	 */
	public static function get_pages_config() {
		return array(
			'panel' => array(
				'title'    => 'پنل معلم',
				'slug'     => 'panel',
				'content'  => '', // قالب page-panel.php محتوا را می‌سازد
				'template' => 'page-panel.php',
				'parent'   => 0,
			),
			'classes' => array(
				'title'    => 'کلاس‌های من',
				'slug'     => 'classes',
				'content'  => '[hamshagerdi_classes]',
				'template' => '',
				'parent'   => 'panel', // slug والد
			),
			'attendance' => array(
				'title'    => 'حضور و غیاب',
				'slug'     => 'attendance',
				'content'  => '[hamshagerdi_attendance]',
				'template' => '',
				'parent'   => 'panel',
			),
			'points' => array(
				'title'    => 'تشویق و امتیاز',
				'slug'     => 'points',
				'content'  => '[hamshagerdi_points]',
				'template' => '',
				'parent'   => 'panel',
			),
			'messages' => array(
				'title'    => 'ارتباط با والدین',
				'slug'     => 'messages',
				'content'  => '[hamshagerdi_comms]',
				'template' => '',
				'parent'   => 'panel',
			),
			'assessment' => array(
				'title'    => 'ارزیابی و نمرات',
				'slug'     => 'assessment',
				'content'  => '[hamshagerdi_assessment]',
				'template' => '',
				'parent'   => 'panel',
			),
			'insights' => array(
				'title'    => 'تحلیل نمرات',
				'slug'     => 'insights',
				'content'  => '[hamshagerdi_grades_insights]',
				'template' => '',
				'parent'   => 'panel',
			),
			'reports' => array(
				'title'    => 'گزارش پیشرفت',
				'slug'     => 'reports',
				'content'  => '[hamshagerdi_reports]',
				'template' => '',
				'parent'   => 'panel',
			),
			'homework' => array(
				'title'    => 'تکالیف',
				'slug'     => 'homework',
				'content'  => '[hamshagerdi_homework]',
				'template' => '',
				'parent'   => 'panel',
			),
			'portfolio' => array(
				'title'    => 'پورتفولیو',
				'slug'     => 'portfolio',
				'content'  => '[hamshagerdi_portfolio]',
				'template' => '',
				'parent'   => 'panel',
			),
			'tools' => array(
				'title'    => 'ابزارهای کلاس',
				'slug'     => 'tools',
				'content'  => '[hamshagerdi_tools]',
				'template' => '',
				'parent'   => 'panel',
			),
			'worksheets' => array(
				'title'    => 'کتابخانه کاربرگ',
				'slug'     => 'worksheets',
				'content'  => '[hamshagerdi_worksheets]',
				'template' => '',
				'parent'   => 'panel',
			),
			'online-builder' => array(
				'title'    => 'تکلیف‌ساز آنلاین',
				'slug'     => 'online-builder',
				'content'  => '[hamshagerdi_online_builder]',
				'template' => '',
				'parent'   => 'panel',
			),
			'online-hw' => array(
				'title'    => 'پاسخ تکلیف آنلاین',
				'slug'     => 'online-hw',
				'content'  => '[hamshagerdi_online_hw]',
				'template' => '',
				'parent'   => 'panel',
			),
			'notebook' => array(
				'title'    => 'دفتر تکالیف',
				'slug'     => 'notebook',
				'content'  => '[hamshagerdi_notebook]',
				'template' => '',
				'parent'   => 'panel',
			),
			'content-studio' => array(
				'title'    => 'استودیو محتوا',
				'slug'     => 'content-studio',
				'content'  => '[hamshagerdi_content_studio]',
				'template' => '',
				'parent'   => 'panel',
			),
			'worksheet-dashboard' => array(
				'title'    => 'داشبورد کاربرگ',
				'slug'     => 'worksheet-dashboard',
				'content'  => '[hamshagerdi_worksheet_dashboard]',
				'template' => '',
				'parent'   => 'panel',
			),
			'student' => array(
				'title'    => 'دنیای دانش‌آموز',
				'slug'     => 'student',
				'content'  => '[hamshagerdi_student_world]',
				'template' => '',
				'parent'   => 'panel',
			),
			// صفحه‌های «پنل والدین» و «گروه والدین» را افزونه‌ی جداگانه‌ی «پنل والدین» می‌سازد
			'timetable' => array(
				'title'    => 'برنامه هفتگی کلاس',
				'slug'     => 'timetable',
				'content'  => '[hamshagerdi_timetable]',
				'template' => '',
				'parent'   => 'panel',
			),
			'budget' => array(
				'title'    => 'بودجه‌بندی کلاس',
				'slug'     => 'budget',
				'content'  => '[hamshagerdi_budget]',
				'template' => '',
				'parent'   => 'panel',
			),
			'calendar' => array(
				'title'    => 'تقویم مناسبتی',
				'slug'     => 'calendar',
				'content'  => '[hamshagerdi_calendar]',
				'template' => '',
				'parent'   => 'panel',
			),
		);
	}

	/**
	 * ساخت یا به‌روزرسانی همه صفحات
	 *
	 * @return array نتیجه: [ 'created' => [...], 'updated' => [...], 'urls' => [...] ]
	 */
	public static function create_all() {
		$config  = self::get_pages_config();
		$created = array();
		$updated = array();
		$urls    = array();
		$ids     = array(); // slug => post_id

		// مرحله ۱: صفحه والد (panel)
		foreach ( $config as $key => $page ) {
			if ( $page['parent'] !== 0 ) {
				continue;
			}
			$result = self::create_or_update_page( $page, 0 );
			$ids[ $key ] = $result['id'];
			if ( $result['action'] === 'created' ) {
				$created[] = $page['title'];
			} elseif ( $result['action'] === 'updated' ) {
				$updated[] = $page['title'];
			}
			$urls[ $key ] = get_permalink( $result['id'] );
		}

		// مرحله ۲: صفحات فرزند
		foreach ( $config as $key => $page ) {
			if ( $page['parent'] === 0 ) {
				continue;
			}
			$parent_id = isset( $ids[ $page['parent'] ] ) ? $ids[ $page['parent'] ] : 0;

			// اگر والد با slug پیدا نشد، از مسیر جستجو کن
			if ( ! $parent_id ) {
				$parent_post = get_page_by_path( $page['parent'] );
				$parent_id   = $parent_post ? $parent_post->ID : 0;
			}

			$result = self::create_or_update_page( $page, $parent_id );
			$ids[ $key ] = $result['id'];
			if ( $result['action'] === 'created' ) {
				$created[] = $page['title'];
			} elseif ( $result['action'] === 'updated' ) {
				$updated[] = $page['title'];
			}
			$urls[ $key ] = get_permalink( $result['id'] );
		}

		// ذخیره شناسه‌ها برای استفاده بعدی
		update_option( 'hs_class_page_ids', $ids );
		update_option( 'hs_class_pages_setup', current_time( 'mysql' ) );

		// فلاش لینک‌ها
		flush_rewrite_rules( false );

		return array(
			'created' => $created,
			'updated' => $updated,
			'urls'    => $urls,
			'ids'     => $ids,
		);
	}

	/**
	 * ساخت یا به‌روزرسانی یک صفحه
	 */
	private static function create_or_update_page( $page, $parent_id = 0 ) {
		$existing = null;

		// جستجو بر اساس slug (با در نظر گرفتن والد)
		if ( $parent_id ) {
			$children = get_pages( array(
				'parent'      => $parent_id,
				'post_status' => array( 'publish', 'draft', 'private' ),
			) );
			foreach ( $children as $child ) {
				if ( $child->post_name === $page['slug'] ) {
					$existing = $child;
					break;
				}
			}
		} else {
			$existing = get_page_by_path( $page['slug'] );
		}

		// اگر با path کامل پیدا نشد، جستجوی عمومی
		if ( ! $existing ) {
			$found = get_posts( array(
				'name'           => $page['slug'],
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
			) );
			if ( $found ) {
				$existing = $found[0];
			}
		}

		$data = array(
			'post_title'   => $page['title'],
			'post_name'    => $page['slug'],
			'post_content' => $page['content'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_parent'  => $parent_id,
			'post_author'  => get_current_user_id() ?: 1,
		);

		if ( $existing ) {
			$data['ID'] = $existing->ID;
			// اگر محتوا خالی است و ما شورت‌کد داریم، به‌روز کن
			// اگر صفحه از قبل محتوا دارد و شورت‌کد ما نیست، فقط والد و عنوان را تنظیم کن
			$current_content = $existing->post_content;
			if ( empty( $page['content'] ) ) {
				// داشبورد — محتوا را دست نزن (قالب مسئول است)
				unset( $data['post_content'] );
			} elseif ( strpos( $current_content, $page['content'] ) === false ) {
				// شورت‌کد وجود ندارد — اضافه کن (یا جایگزین کن اگر خالی بود)
				if ( trim( $current_content ) === '' ) {
					$data['post_content'] = $page['content'];
				} else {
					// محتوا دارد ولی شورت‌کد نیست — شورت‌کد را به انتها اضافه کن
					$data['post_content'] = $current_content . "\n\n" . $page['content'];
				}
			} else {
				unset( $data['post_content'] ); // شورت‌کد از قبل هست
			}

			wp_update_post( $data );
			$post_id = $existing->ID;
			$action  = 'updated';
		} else {
			$post_id = wp_insert_post( $data, true );
			if ( is_wp_error( $post_id ) ) {
				return array( 'id' => 0, 'action' => 'error', 'error' => $post_id->get_error_message() );
			}
			$action = 'created';
		}

		// تنظیم قالب صفحه
		if ( ! empty( $page['template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', $page['template'] );
		}

		return array(
			'id'     => $post_id,
			'action' => $action,
		);
	}

	/**
	 * بررسی اینکه صفحات راه‌اندازی شده‌اند یا نه
	 */
	public static function is_setup() {
		$ids = get_option( 'hs_class_page_ids', array() );
		if ( empty( $ids['panel'] ) ) {
			return false;
		}
		$panel = get_post( $ids['panel'] );
		return $panel && $panel->post_status === 'publish';
	}

	/**
	 * دریافت URL یک صفحه
	 */
	public static function get_url( $key ) {
		$ids = get_option( 'hs_class_page_ids', array() );
		if ( ! empty( $ids[ $key ] ) ) {
			$url = get_permalink( $ids[ $key ] );
			if ( $url ) {
				return $url;
			}
		}
		// fallback
		$map = array(
			'panel'      => home_url( '/panel/' ),
			'classes'    => home_url( '/panel/classes/' ),
			'attendance' => home_url( '/panel/attendance/' ),
			'points'     => home_url( '/panel/points/' ),
			'messages'   => home_url( '/panel/messages/' ),
			'assessment' => home_url( '/panel/assessment/' ),
			'reports'    => home_url( '/panel/reports/' ),
			'homework'   => home_url( '/panel/homework/' ),
			'portfolio'  => home_url( '/panel/portfolio/' ),
			'tools'      => home_url( '/panel/tools/' ),
			'worksheets' => home_url( '/panel/worksheets/' ),
			'online-builder' => home_url( '/panel/online-builder/' ),
			'online-hw'  => home_url( '/panel/online-hw/' ),
			'notebook'   => home_url( '/panel/notebook/' ),
			'content-studio' => home_url( '/panel/content-studio/' ),
			'student'    => home_url( '/panel/student/' ),
			'parent'     => home_url( '/panel/parent/' ),
			'parent-chat'=> home_url( '/panel/parent-chat/' ),
			'calendar'   => home_url( '/panel/calendar/' ),
			'budget'     => home_url( '/panel/budget/' ),
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : home_url( '/' );
	}

	/**
	 * نوتیس ادمین + دکمه راه‌اندازی
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// فقط در صفحات مرتبط
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->id, array( 'dashboard', 'plugins', 'edit-page', 'page' ), true ) ) {
			// همچنین در صفحه ویرایش hs_class
			if ( $screen->post_type !== 'hs_class' ) {
				return;
			}
		}

		if ( self::is_setup() ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=hs_setup_pages' ),
			'hs_setup_pages'
		);
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong>همشاگردی — مدیریت کلاس:</strong>
				صفحات پنل معلم هنوز ساخته نشده‌اند.
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary" style="margin-right:8px;">
					راه‌اندازی خودکار صفحات پنل
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * هندلر دکمه راه‌اندازی
	 */
	public static function handle_setup_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		check_admin_referer( 'hs_setup_pages' );

		$result = self::create_all();

		$messages = array();
		if ( ! empty( $result['created'] ) ) {
			$messages[] = 'صفحات ساخته‌شده: ' . implode( '، ', $result['created'] );
		}
		if ( ! empty( $result['updated'] ) ) {
			$messages[] = 'صفحات به‌روزشده: ' . implode( '، ', $result['updated'] );
		}
		if ( empty( $messages ) ) {
			$messages[] = 'همه صفحات از قبل آماده بودند.';
		}

		$redirect = add_query_arg( array(
			'hs_setup' => '1',
			'hs_msg'   => urlencode( implode( ' | ', $messages ) ),
		), admin_url( 'edit.php?post_type=page' ) );

		wp_redirect( $redirect );
		exit;
	}

	/**
	 * پیام موفقیت بعد از راه‌اندازی
	 */
	public static function setup_success_notice() {
		if ( ! isset( $_GET['hs_setup'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg = isset( $_GET['hs_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['hs_msg'] ) ) : 'صفحات پنل آماده شدند.';
		$ids = get_option( 'hs_class_page_ids', array() );
		?>
		<div class="notice notice-success is-dismissible">
			<p><strong>راه‌اندازی پنل همشاگردی انجام شد.</strong> <?php echo esc_html( $msg ); ?></p>
			<?php if ( ! empty( $ids ) ) : ?>
				<ul style="list-style:disc;margin:0.5rem 1.5rem;">
					<?php if ( ! empty( $ids['panel'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['panel'] ) ); ?>" target="_blank">پنل معلم (داشبورد)</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['panel'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['classes'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['classes'] ) ); ?>" target="_blank">کلاس‌های من</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['classes'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['attendance'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['attendance'] ) ); ?>" target="_blank">حضور و غیاب</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['attendance'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['points'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['points'] ) ); ?>" target="_blank">تشویق و امتیاز</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['points'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['messages'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['messages'] ) ); ?>" target="_blank">ارتباط با والدین</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['messages'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['assessment'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['assessment'] ) ); ?>" target="_blank">ارزیابی و نمرات</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['assessment'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['reports'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['reports'] ) ); ?>" target="_blank">گزارش پیشرفت</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['reports'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['homework'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['homework'] ) ); ?>" target="_blank">تکالیف</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['homework'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['portfolio'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['portfolio'] ) ); ?>" target="_blank">پورتفولیو</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['portfolio'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['tools'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['tools'] ) ); ?>" target="_blank">ابزارهای کلاس</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['tools'] ) ) ); ?></code></li>
					<?php endif; ?>
					<?php if ( ! empty( $ids['worksheets'] ) ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $ids['worksheets'] ) ); ?>" target="_blank">کتابخانه کاربرگ</a>
							— <code><?php echo esc_html( wp_make_link_relative( get_permalink( $ids['worksheets'] ) ) ); ?></code></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * منوی ابزار در پیشخوان (زیر کلاس‌ها)
	 */
	public static function add_tools_submenu() {
		add_submenu_page(
			'edit.php?post_type=hs_class',
			'راه‌اندازی صفحات پنل',
			'راه‌اندازی صفحات',
			'manage_options',
			'hs-setup-pages',
			array( __CLASS__, 'render_tools_page' )
		);
	}

	/**
	 * صفحه ابزار راه‌اندازی
	 */
	public static function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$result = null;
		if ( isset( $_POST['hs_do_setup'] ) && check_admin_referer( 'hs_setup_pages_form' ) ) {
			$result = self::create_all();
		}

		$ids  = get_option( 'hs_class_page_ids', array() );
		$when = get_option( 'hs_class_pages_setup', '' );
		?>
		<div class="wrap">
			<h1>راه‌اندازی صفحات پنل معلم</h1>
			<p>با یک کلیک، صفحات زیر ساخته می‌شوند و شورت‌کدها داخل آن‌ها قرار می‌گیرند:</p>

			<table class="widefat striped" style="max-width:720px;margin:1rem 0;">
				<thead>
					<tr>
						<th>صفحه</th>
						<th>آدرس</th>
						<th>محتوا / قالب</th>
						<th>وضعیت</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>پنل معلم</strong></td>
						<td><code>/panel/</code></td>
						<td>قالب «پنل معلم» (داشبورد)</td>
						<td><?php echo ! empty( $ids['panel'] ) && get_post( $ids['panel'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>کلاس‌های من</strong></td>
						<td><code>/panel/classes/</code></td>
						<td><code>[hamshagerdi_classes]</code></td>
						<td><?php echo ! empty( $ids['classes'] ) && get_post( $ids['classes'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>حضور و غیاب</strong></td>
						<td><code>/panel/attendance/</code></td>
						<td><code>[hamshagerdi_attendance]</code></td>
						<td><?php echo ! empty( $ids['attendance'] ) && get_post( $ids['attendance'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>تشویق و امتیاز</strong></td>
						<td><code>/panel/points/</code></td>
						<td><code>[hamshagerdi_points]</code></td>
						<td><?php echo ! empty( $ids['points'] ) && get_post( $ids['points'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>ارتباط با والدین</strong></td>
						<td><code>/panel/messages/</code></td>
						<td><code>[hamshagerdi_comms]</code></td>
						<td><?php echo ! empty( $ids['messages'] ) && get_post( $ids['messages'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>ارزیابی و نمرات</strong></td>
						<td><code>/panel/assessment/</code></td>
						<td><code>[hamshagerdi_assessment]</code></td>
						<td><?php echo ! empty( $ids['assessment'] ) && get_post( $ids['assessment'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>گزارش پیشرفت</strong></td>
						<td><code>/panel/reports/</code></td>
						<td><code>[hamshagerdi_reports]</code></td>
						<td><?php echo ! empty( $ids['reports'] ) && get_post( $ids['reports'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>تکالیف</strong></td>
						<td><code>/panel/homework/</code></td>
						<td><code>[hamshagerdi_homework]</code></td>
						<td><?php echo ! empty( $ids['homework'] ) && get_post( $ids['homework'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>پورتفولیو</strong></td>
						<td><code>/panel/portfolio/</code></td>
						<td><code>[hamshagerdi_portfolio]</code></td>
						<td><?php echo ! empty( $ids['portfolio'] ) && get_post( $ids['portfolio'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>ابزارهای کلاس</strong></td>
						<td><code>/panel/tools/</code></td>
						<td><code>[hamshagerdi_tools]</code></td>
						<td><?php echo ! empty( $ids['tools'] ) && get_post( $ids['tools'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>کتابخانه کاربرگ</strong></td>
						<td><code>/panel/worksheets/</code></td>
						<td><code>[hamshagerdi_worksheets]</code></td>
						<td><?php echo ! empty( $ids['worksheets'] ) && get_post( $ids['worksheets'] ) ? '<span style="color:green;">✓ آماده</span>' : '<span style="color:#999;">ساخته نشده</span>'; ?></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $result ) : ?>
				<div class="notice notice-success inline">
					<p>
						<?php
						if ( ! empty( $result['created'] ) ) {
							echo 'ساخته‌شده: <strong>' . esc_html( implode( '، ', $result['created'] ) ) . '</strong><br>';
						}
						if ( ! empty( $result['updated'] ) ) {
							echo 'به‌روزشده: <strong>' . esc_html( implode( '، ', $result['updated'] ) ) . '</strong><br>';
						}
						if ( empty( $result['created'] ) && empty( $result['updated'] ) ) {
							echo 'همه صفحات از قبل موجود بودند.';
						}
						?>
					</p>
					<?php if ( ! empty( $result['urls'] ) ) : ?>
						<ul>
							<?php foreach ( $result['urls'] as $key => $url ) : ?>
								<li><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'hs_setup_pages_form' ); ?>
				<p>
					<button type="submit" name="hs_do_setup" class="button button-primary button-hero">
						<?php echo self::is_setup() ? 'بازسازی / به‌روزرسانی صفحات' : 'ساخت صفحات پنل'; ?>
					</button>
				</p>
			</form>

			<?php if ( $when ) : ?>
				<p class="description">آخرین راه‌اندازی: <?php echo esc_html( $when ); ?></p>
			<?php endif; ?>

			<hr>
			<h2>نکته</h2>
			<p>
				اگر پیوندهای یکتا (Permalinks) را روی «نام نوشته» نگذاشته‌اید،
				از <strong>تنظیمات → پیوندهای یکتا → ذخیره تغییرات</strong> یک‌بار ذخیره کنید تا آدرس‌ها درست کار کنند.
			</p>
		</div>
		<?php
	}

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_success_notice' ) );
		add_action( 'admin_post_hs_setup_pages', array( __CLASS__, 'handle_setup_action' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_tools_submenu' ), 20 );
	}
}

HS_Setup_Pages::init();
