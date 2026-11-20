<?php
/**
 * پنل والدین همشاگردی — دفتر ارتباط خانه و مدرسه
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Parent_Panel_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_parent_panel', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_hs_parent_link_child', array( __CLASS__, 'ajax_link_child' ) );
		add_action( 'wp_ajax_hs_parent_unlink_child', array( __CLASS__, 'ajax_unlink_child' ) );
		add_action( 'wp_ajax_hs_parent_send_msg', array( __CLASS__, 'ajax_send_msg' ) );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return self::gate();
		}

		$user_id = get_current_user_id();
		$section = isset( $_GET['pp'] ) ? sanitize_key( $_GET['pp'] ) : 'home';
		$child_id = isset( $_GET['child'] ) ? absint( $_GET['child'] ) : 0;

		$children = HS_Parent_Link::get_children( $user_id );

		// معلم/ادمین می‌تواند child را از URL ببیند
		if ( $child_id && ! HS_Parent_Link::can_view( $user_id, $child_id ) ) {
			$child_id = 0;
		}
		if ( ! $child_id && ! empty( $children ) ) {
			$child_id = $children[0];
		}

		// بخش ← مجوز
		$need = array( 'homework' => 'homework', 'attendance' => 'attendance', 'books' => 'records', 'meetings' => 'meetings', 'calendar' => 'calendar', 'practice' => 'practice' );
		$deny = isset( $need[ $section ] ) && $child_id && ! HSPP_Perms::can( $user_id, $child_id, $need[ $section ] );
		if ( 'progress' === $section && $child_id && ! self::any_progress( $user_id, $child_id ) ) {
			$deny = true;
		}
		if ( 'messages' === $section && $child_id && ! HSPP_Perms::can( $user_id, $child_id, 'announce' ) && ! HSPP_Perms::can( $user_id, $child_id, 'messages' ) ) {
			$deny = true;
		}

		ob_start();
		?>
		<div class="hs-pp" id="hs-parent-panel" data-child="<?php echo esc_attr( $child_id ); ?>">
			<?php echo self::switcher( $children, $child_id, $section ); ?>
			<?php echo self::nav( $section, $child_id ); ?>
			<div class="hs-pp-body">
				<?php
				if ( empty( $children ) && ! in_array( $section, array( 'settings', 'link', 'profile' ), true ) ) {
					echo self::render_link_form();
				} elseif ( $deny ) {
					echo self::child_header( $child_id ) . '<section class="hs-pp-card"><p class="hs-pp-muted">🔒 مدرسه این بخش را برای این فرزند در دسترس والدین قرار نداده است.</p></section>';
				} else {
					switch ( $section ) {
						case 'profile':
							echo HSPP_Profile::render( $user_id, $children, $child_id ); // phpcs:ignore
							break;
						case 'homework':
							echo self::render_homework( $child_id );
							break;
						case 'progress':
							echo self::child_header( $child_id ) . HSPP_Progress::render( $user_id, $child_id ); // phpcs:ignore
							break;
						case 'meetings':
							echo self::child_header( $child_id ) . HSPP_Meetings::render( $user_id, $child_id ); // phpcs:ignore
							break;
						case 'calendar':
							echo self::child_header( $child_id ) . HSPP_Calendar::render( $user_id, $child_id ); // phpcs:ignore
							break;
						case 'practice':
							echo self::child_header( $child_id ) . HSPP_Practice::render( $user_id, $child_id ); // phpcs:ignore
							break;
						case 'attendance':
							echo self::render_attendance( $child_id );
							break;
						case 'messages':
							echo self::render_messages( $child_id );
							break;
						case 'books':
							echo self::render_books( $child_id );
							break;
						case 'settings':
						case 'link':
							echo self::render_settings( $user_id, $children );
							break;
						case 'children':
							echo self::render_children_list( $children, $child_id );
							break;
						default:
							echo self::render_home( $child_id, $children );
					}
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** گزارش پیشرفت وقتی معنا دارد که دست‌کم یکی از بخش‌هایش باز باشد */
	private static function any_progress( $uid, $sid ) {
		foreach ( array( 'assessment', 'attendance', 'homework', 'points', 'practice' ) as $f ) {
			if ( HSPP_Perms::can( $uid, $sid, $f ) ) {
				return true;
			}
		}
		return false;
	}

	private static function url( $section = 'home', $child_id = 0 ) {
		$args = array( 'pp' => $section );
		if ( $child_id ) {
			$args['child'] = $child_id;
		}
		return add_query_arg( $args, HSPP_Integration::page_url( 'parent' ) );
	}

	/** جابه‌جایی بین فرزندان (بالای پنل) */
	private static function switcher( $children, $current, $section ) {
		if ( count( $children ) < 2 ) {
			return '';
		}
		$h = '<nav class="hs-pp-kids" aria-label="انتخاب فرزند">';
		foreach ( $children as $sid ) {
			$u = get_userdata( $sid );
			if ( $u ) {
				$h .= '<a class="' . ( (int) $sid === (int) $current ? 'is-on' : '' ) . '" href="' . esc_url( self::url( $section, $sid ) ) . '"><span>' . esc_html( mb_substr( $u->display_name, 0, 1 ) ) . '</span>' . esc_html( $u->display_name ) . '</a>';
			}
		}
		return $h . '</nav>';
	}

	private static function gate() {
		ob_start();
		?>
		<div class="hs-pp hs-pp-gate">
			<div class="hs-pp-gate-card">
				<div class="hs-pp-gate-icon">👨‍👩‍👧</div>
				<h2>پنل والدین همشاگردی</h2>
				<p>همراه یادگیری فرزند شما 🌱</p>
				<a class="hs-pp-btn hs-pp-btn-primary" href="<?php echo esc_url( wp_login_url( home_url( '/panel/parent/' ) ) ); ?>">ورود به پنل</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function nav( $current, $child_id ) {
		$items = array(
			'home'       => array( 'icon' => '🏠', 'label' => 'خانه' ),
			'children'   => array( 'icon' => '👧', 'label' => 'فرزندان' ),
			'homework'   => array( 'icon' => '📚', 'label' => 'تکالیف' ),
			'progress'   => array( 'icon' => '📊', 'label' => 'گزارش پیشرفت' ),
			'attendance' => array( 'icon' => '✅', 'label' => 'حضور' ),
			'calendar'   => array( 'icon' => '🗓️', 'label' => 'تقویم' ),
			'practice'   => array( 'icon' => '🏡', 'label' => 'تمرین در خانه' ),
			'meetings'   => array( 'icon' => '🤝', 'label' => 'جلسه با معلم' ),
			'messages'   => array( 'icon' => '💬', 'label' => 'پیام' ),
			'books'      => array( 'icon' => '📖', 'label' => 'کتاب' ),
			'chat'       => array( 'icon' => '🗨️', 'label' => 'گروه والدین' ),
			'profile'    => array( 'icon' => '👤', 'label' => 'اطلاعات' ),
			'settings'   => array( 'icon' => '⚙️', 'label' => 'تنظیمات' ),
		);
		$uid  = get_current_user_id();
		$need = array( 'homework' => 'homework', 'attendance' => 'attendance', 'books' => 'records', 'chat' => 'group', 'meetings' => 'meetings', 'calendar' => 'calendar', 'practice' => 'practice' );
		if ( $child_id && ! self::any_progress( $uid, $child_id ) ) {
			unset( $items['progress'] );
		}
		foreach ( $need as $k => $f ) {
			if ( $child_id && ! HSPP_Perms::can( $uid, $child_id, $f ) ) {
				unset( $items[ $k ] );
			}
		}
		if ( $child_id && ! HSPP_Perms::can( $uid, $child_id, 'announce' ) && ! HSPP_Perms::can( $uid, $child_id, 'messages' ) ) {
			unset( $items['messages'] );
		}
		ob_start();
		?>
		<nav class="hs-pp-nav">
			<?php foreach ( $items as $key => $item ) :
				$href = ( $key === 'chat' ) ? HSPP_Integration::page_url( 'parent-chat' ) : self::url( $key, $child_id );
				?>
				<a href="<?php echo esc_url( $href ); ?>"
				   class="hs-pp-nav-item <?php echo $current === $key ? 'active' : ''; ?>">
					<span><?php echo $item['icon']; ?></span>
					<small><?php echo esc_html( $item['label'] ); ?></small>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
		return ob_get_clean();
	}

	private static function child_header( $child_id ) {
		$user = get_userdata( $child_id );
		if ( ! $user ) {
			return '';
		}
		$classes = HS_Parent_Link::student_classes( $child_id );
		$c0 = $classes[0] ?? null;
		ob_start();
		?>
		<div class="hs-pp-child-bar">
			<div class="hs-pp-avatar"><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></div>
			<div>
				<strong><?php echo esc_html( $user->display_name ); ?></strong>
				<?php if ( $c0 ) : ?>
					<span><?php echo esc_html( $c0['title'] ); ?>
						<?php if ( $c0['teacher'] ) echo ' · معلم: ' . esc_html( $c0['teacher'] ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_home( $child_id, $children ) {
		if ( ! $child_id ) {
			return self::render_link_form();
		}
		$user = get_userdata( $child_id );
		$classes = HS_Parent_Link::student_classes( $child_id );
		$class_id = $classes[0]['id'] ?? 0;

		$hw_done = 0;
		$hw_total = 0;
		$pending = array();
		if ( $class_id ) {
			$hws = HS_Homework_DB::get_class_homework( $class_id, 20 );
			$hw_total = count( $hws );
			foreach ( $hws as $hw ) {
				$st = HS_Homework_DB::get_statuses( $hw['id'] );
				$mine = $st[ $child_id ] ?? null;
				$done = $mine && in_array( $mine['status'], array( 'done', 'late' ), true );
				if ( class_exists( 'HS_Online_HW_DB' ) ) {
					$sub = HS_Online_HW_DB::get_submission( $hw['id'], $child_id );
					if ( $sub ) {
						$done = true;
					}
				}
				if ( $done ) {
					$hw_done++;
				} else {
					$pending[] = $hw;
				}
			}
		}

		$pid     = get_current_user_id();
		$can_hw  = HSPP_Perms::can( $pid, $child_id, 'homework' );
		$can_pt  = HSPP_Perms::can( $pid, $child_id, 'points' );
		if ( ! $can_hw ) {
			$pending = array();
		}
		$stars = ( $class_id && $can_pt ) ? HS_Points_DB::get_student_total( $class_id, $child_id ) : 0;
		$msgs = HS_Comms_DB::get_inbox( $child_id, 5 );
		// والد هم inbox خودش را می‌بیند
		$parent_msgs = HS_Comms_DB::get_inbox( get_current_user_id(), 5 );

		ob_start();
		?>
		<div class="hs-pp-hero">
			<h1>سلام، به پنل والدین خوش آمدید</h1>
			<p>وضعیت یادگیری <?php echo $user ? esc_html( $user->display_name ) : 'فرزند شما'; ?></p>
		</div>
		<?php echo self::child_header( $child_id );
		echo HSPP_Digest::card( get_current_user_id(), $child_id ); // phpcs:ignore ?>

		<div class="hs-pp-stats">
			<?php if ( $can_hw ) : ?>
			<div class="hs-pp-stat">
				<div class="num"><?php echo (int) $hw_done; ?><small>/<?php echo (int) $hw_total; ?></small></div>
				<div class="lbl">تکلیف انجام‌شده</div>
			</div>
			<?php endif; ?>
			<?php if ( $can_pt ) : ?>
			<div class="hs-pp-stat">
				<div class="num"><?php echo (int) $stars; ?></div>
				<div class="lbl">ستاره</div>
			</div>
			<?php endif; ?>
			<?php echo HSPP_Integration::today_attendance_stat( $pid, $child_id ); // phpcs:ignore ?>
		</div>

		<?php echo HSPP_Reads::latest_card( $pid, $child_id ); // phpcs:ignore ?>

		<?php if ( $can_hw ) : ?>
		<section class="hs-pp-card">
			<h3>تکالیف باز</h3>
			<?php if ( empty( $pending ) ) : ?>
				<p class="hs-pp-muted">تکلیف باز و معوقی نیست. 👍</p>
			<?php else : ?>
				<ul class="hs-pp-list">
					<?php foreach ( array_slice( $pending, 0, 5 ) as $hw ) : ?>
						<li>
							<strong><?php echo esc_html( $hw['title'] ); ?></strong>
							<span>
								<?php if ( $hw['subject'] ) echo esc_html( $hw['subject'] ) . ' · '; ?>
								<?php if ( $hw['due_date'] ) echo 'مهلت: ' . esc_html( $hw['due_date'] ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="hs-pp-link" href="<?php echo esc_url( self::url( 'homework', $child_id ) ); ?>">مشاهده همه تکالیف ←</a>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<section class="hs-pp-card">
			<h3>دسترسی سریع</h3>
			<div class="hs-pp-quick">
				<a href="<?php echo esc_url( self::url( 'homework', $child_id ) ); ?>">📚 تکالیف</a>
				<a href="<?php echo esc_url( self::url( 'progress', $child_id ) ); ?>">📊 پیشرفت</a>
				<a href="<?php echo esc_url( self::url( 'attendance', $child_id ) ); ?>">🗓️ حضور</a>
				<a href="<?php echo esc_url( self::url( 'messages', $child_id ) ); ?>">💬 پیام‌ها</a>
				<?php if ( HSPP_Perms::can( $pid, $child_id, 'group' ) ) : ?><a href="<?php echo esc_url( HSPP_Integration::page_url( 'parent-chat' ) ); ?>">🗨️ گروه والدین</a><?php endif; ?>
				<a href="<?php echo esc_url( self::url( 'profile', $child_id ) ); ?>">👤 اطلاعات من و فرزندم</a>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_homework( $child_id ) {
		if ( ! $child_id ) {
			return '<p class="hs-pp-muted">فرزندی انتخاب نشده.</p>';
		}
		$classes = HS_Parent_Link::student_classes( $child_id );
		$class_id = $classes[0]['id'] ?? 0;
		$hws = $class_id ? HS_Homework_DB::get_class_homework( $class_id, 40 ) : array();

		ob_start();
		echo self::child_header( $child_id );
		?>
		<section class="hs-pp-card">
			<h3>📚 تکالیف و تمرین</h3>
			<?php if ( empty( $hws ) ) : ?>
				<p class="hs-pp-muted">تکلیفی ثبت نشده است.</p>
			<?php else : ?>
				<ul class="hs-pp-hw">
					<?php foreach ( $hws as $hw ) :
						$st = HS_Homework_DB::get_statuses( $hw['id'] );
						$mine = $st[ $child_id ] ?? null;
						$sub = class_exists( 'HS_Online_HW_DB' ) ? HS_Online_HW_DB::get_submission( $hw['id'], $child_id ) : null;
						$done = $sub || ( $mine && in_array( $mine['status'], array( 'done', 'late' ), true ) );
						$overdue = ! $done && ! empty( $hw['due_date'] ) && $hw['due_date'] < current_time( 'Y-m-d' );
						?>
						<li class="<?php echo $done ? 'done' : ( $overdue ? 'late' : '' ); ?>">
							<div class="hs-pp-hw-top">
								<strong><?php echo esc_html( $hw['title'] ); ?></strong>
								<span class="hs-pp-badge <?php echo $done ? 'ok' : ( $overdue ? 'bad' : 'wait' ); ?>">
									<?php echo $done ? 'انجام‌شده' : ( $overdue ? 'عقب‌افتاده' : 'در انتظار' ); ?>
								</span>
							</div>
							<?php if ( $hw['subject'] || $hw['due_date'] ) : ?>
								<p class="hs-pp-meta">
									<?php if ( $hw['subject'] ) echo esc_html( $hw['subject'] ); ?>
									<?php if ( $hw['due_date'] ) echo ' · مهلت ' . esc_html( $hw['due_date'] ); ?>
								</p>
							<?php endif; ?>
							<?php if ( $hw['description'] ) : ?>
								<p class="hs-pp-desc"><?php echo esc_html( mb_substr( $hw['description'], 0, 160 ) ); ?></p>
							<?php endif; ?>
							<?php if ( $sub ) : ?>
								<p class="hs-pp-score">نمره آنلاین: <?php echo esc_html( $sub['auto_score'] . '/' . $sub['max_score'] ); ?></p>
							<?php endif; ?>
							<?php if ( $mine && ! empty( $mine['feedback'] ) ) : ?>
								<p class="hs-pp-fb">بازخورد معلم: <?php echo esc_html( $mine['feedback'] ); ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_attendance( $child_id ) {
		if ( ! $child_id ) {
			return '<p class="hs-pp-muted">فرزندی انتخاب نشده.</p>';
		}
		$classes = HS_Parent_Link::student_classes( $child_id );
		$class_id = $classes[0]['id'] ?? 0;

		$year  = (int) current_time( 'Y' );
		$month = (int) current_time( 'n' );
		// تقویم شمسی ساده نداریم؛ از ماه میلادی جدول استفاده می‌کنیم
		$rows = array();
		if ( $class_id ) {
			global $wpdb;
			$table = HS_Attendance_DB::table_name();
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT att_date, status, note FROM {$table}
				 WHERE class_id = %d AND student_id = %d
				 ORDER BY att_date DESC LIMIT 40",
				$class_id,
				$child_id
			), ARRAY_A );
		}

		$labels = array(
			'present'   => 'حاضر',
			'absent'    => 'غایب',
			'late'      => 'تأخیر',
			'excused'   => 'موجه',
		);

		$counts = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0 );
		foreach ( $rows as $r ) {
			if ( isset( $counts[ $r['status'] ] ) ) {
				$counts[ $r['status'] ]++;
			}
		}

		ob_start();
		echo self::child_header( $child_id );
		?>
		<section class="hs-pp-card">
			<h3>🗓️ حضور و غیاب</h3>
			<div class="hs-pp-stats">
				<div class="hs-pp-stat"><div class="num"><?php echo (int) $counts['present']; ?></div><div class="lbl">حاضر</div></div>
				<div class="hs-pp-stat"><div class="num"><?php echo (int) $counts['absent']; ?></div><div class="lbl">غایب</div></div>
				<div class="hs-pp-stat"><div class="num"><?php echo (int) $counts['late']; ?></div><div class="lbl">تأخیر</div></div>
			</div>
			<?php if ( empty( $rows ) ) : ?>
				<p class="hs-pp-muted">هنوز حضور و غیابی ثبت نشده.</p>
			<?php else : ?>
				<ul class="hs-pp-list">
					<?php foreach ( $rows as $r ) : ?>
						<li>
							<strong><?php echo esc_html( $r['att_date'] ); ?></strong>
							<span><?php echo esc_html( $labels[ $r['status'] ] ?? $r['status'] ); ?>
								<?php if ( ! empty( $r['note'] ) ) echo ' — ' . esc_html( $r['note'] ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_messages( $child_id ) {
		$parent_id = get_current_user_id();
		$classes = $child_id ? HS_Parent_Link::student_classes( $child_id ) : array();
		$class_id = $classes[0]['id'] ?? 0;
		$teacher_id = $classes[0]['teacher_id'] ?? 0;

		$can_ann = ! $child_id || HSPP_Perms::can( $parent_id, $child_id, 'announce' );
		$can_msg = ! $child_id || HSPP_Perms::can( $parent_id, $child_id, 'messages' );
		$announcements = array();
		if ( $can_ann ) {
			foreach ( HS_Parent_Link::student_classes( $child_id ) as $c ) { // همه‌ی کلاس‌های فرزند
				foreach ( HS_Comms_DB::get_announcements( $c['id'], 15 ) as $a ) {
					$a['class_title'] = $c['title'];
					$announcements[]  = $a;
				}
			}
			usort( $announcements, function ( $x, $y ) {
				return strcmp( $y['created_at'], $x['created_at'] );
			} );
			HSPP_Reads::mark( $parent_id, wp_list_pluck( $announcements, 'id' ) );
		}
		$inbox = $can_msg ? HS_Comms_DB::get_inbox( $parent_id, 20 ) : array();
		if ( ! $can_msg ) {
			$teacher_id = 0;
		}

		ob_start();
		if ( $child_id ) {
			echo self::child_header( $child_id );
		}
		?>
		<?php if ( $can_ann ) : ?>
		<section class="hs-pp-card">
			<h3>📢 اطلاعیه‌های کلاس</h3>
			<?php if ( empty( $announcements ) ) : ?>
				<p class="hs-pp-muted">اطلاعیه‌ای نیست.</p>
			<?php else : ?>
				<ul class="hs-pp-list">
					<?php foreach ( $announcements as $a ) : ?>
						<li>
							<strong><?php echo ! empty( $a['is_pinned'] ) ? '📌 ' : ''; ?><?php echo esc_html( $a['title'] ? $a['title'] : 'اطلاعیه' ); ?></strong>
							<span><?php echo esc_html( $a['body'] ?? '' ); ?></span>
							<small><?php echo esc_html( $a['class_title'] . ' · ' . mysql2date( 'Y/m/d', $a['created_at'] ) ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<?php if ( $teacher_id ) : ?>
		<section class="hs-pp-card">
			<h3>💬 پیام به معلم</h3>
			<form id="hs-pp-msg-form">
				<input type="hidden" id="hs-pp-msg-teacher" value="<?php echo esc_attr( $teacher_id ); ?>">
				<input type="hidden" id="hs-pp-msg-class" value="<?php echo esc_attr( $class_id ); ?>">
				<input type="hidden" id="hs-pp-msg-child" value="<?php echo esc_attr( $child_id ); ?>">
				<textarea id="hs-pp-msg-body" rows="3" placeholder="پیام خود را بنویسید..."></textarea>
				<?php echo HS_Upload::picker( 'hs-pp-msg-image', 'general', array( 'label' => 'ارسال عکس' ) ); // phpcs:ignore ?>
				<button type="submit" class="hs-pp-btn hs-pp-btn-primary">ارسال پیام</button>
				<div class="hs-form-message" id="hs-pp-msg-status"></div>
			</form>
		</section>
		<?php endif; ?>

		<?php if ( $can_msg ) : ?>
		<section class="hs-pp-card">
			<h3>صندوق پیام شما</h3>
			<?php if ( empty( $inbox ) ) : ?>
				<p class="hs-pp-muted">پیامی ندارید.</p>
			<?php else : ?>
				<ul class="hs-pp-list">
					<?php foreach ( $inbox as $m ) :
						$sender = get_userdata( $m['sender_id'] );
						?>
						<li>
							<strong><?php echo $sender ? esc_html( $sender->display_name ) : 'کاربر'; ?></strong>
							<span><?php echo esc_html( $m['body'] ); ?></span>
							<?php if ( ! empty( $m['media_url'] ) ) : ?>
								<a href="<?php echo esc_url( $m['media_url'] ); ?>" target="_blank" rel="noopener"><img class="hs-msg-img" src="<?php echo esc_url( $m['media_url'] ); ?>" alt="عکس پیام"></a>
							<?php endif; ?>
							<small><?php echo esc_html( $m['created_at'] ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	private static function render_books( $child_id ) {
		ob_start();
		if ( $child_id ) {
			echo self::child_header( $child_id );
		}
		?>
		<section class="hs-pp-card">
			<h3>📖 کتاب‌های پیشنهادی همشاگردی</h3>
			<p class="hs-pp-muted">به‌زودی معلم می‌تواند کتاب مناسب پایه را پیشنهاد دهد و شما مستقیم به فروشگاه magpub.ir بروید.</p>
			<a class="hs-pp-btn hs-pp-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">مشاهده فروشگاه</a>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_children_list( $children, $current ) {
		ob_start();
		?>
		<section class="hs-pp-card">
			<h3>👧 فرزندان من</h3>
			<?php if ( empty( $children ) ) : ?>
				<p class="hs-pp-muted">هنوز فرزندی متصل نیست.</p>
				<?php echo self::render_link_form(); ?>
			<?php else : ?>
				<ul class="hs-pp-children">
					<?php foreach ( $children as $sid ) :
						$u = get_userdata( $sid );
						if ( ! $u ) {
							continue;
						}
						$classes = HS_Parent_Link::student_classes( $sid );
						?>
						<li class="<?php echo (int) $sid === (int) $current ? 'active' : ''; ?>">
							<a href="<?php echo esc_url( self::url( 'home', $sid ) ); ?>">
								<strong><?php echo esc_html( $u->display_name ); ?></strong>
								<span><?php echo ! empty( $classes ) ? esc_html( $classes[0]['title'] ) : 'بدون کلاس'; ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="hs-pp-link" href="<?php echo esc_url( self::url( 'settings' ) ); ?>">+ افزودن فرزند دیگر</a>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_settings( $parent_id, $children ) {
		ob_start();
		?>
		<section class="hs-pp-card">
			<h3>⚙️ تنظیمات و اتصال فرزند</h3>
			<?php echo self::render_link_form(); ?>
		</section>
		<?php if ( ! empty( $children ) ) : ?>
		<section class="hs-pp-card">
			<h3>فرزندان متصل</h3>
			<ul class="hs-pp-list">
				<?php foreach ( $children as $sid ) :
					$u = get_userdata( $sid );
					if ( ! $u ) continue;
					?>
					<li>
						<strong><?php echo esc_html( $u->display_name ); ?></strong>
						<span><?php echo esc_html( $u->user_login ); ?></span>
						<button type="button" class="hs-pp-btn hs-pp-btn-soft hs-pp-unlink" data-id="<?php echo esc_attr( $sid ); ?>">قطع اتصال</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	private static function render_link_form() {
		ob_start();
		?>
		<div class="hs-pp-link-box">
			<p>برای مشاهده وضعیت فرزند، «نام کاربری» و «کد والد» را وارد کنید. هر دو را معلم فرزندتان به شما می‌دهد.</p>
			<form id="hs-pp-link-form" class="hs-pp-link-form">
				<input type="text" id="hs-pp-child-login" placeholder="نام کاربری دانش‌آموز" required autocomplete="off">
				<input type="text" id="hs-pp-child-code" placeholder="کد والد (۸ کاراکتر)" required autocomplete="off" maxlength="8" style="text-transform:uppercase">
				<button type="submit" class="hs-pp-btn hs-pp-btn-primary">اتصال فرزند</button>
			</form>
			<div class="hs-form-message" id="hs-pp-link-msg"></div>
		</div>
		<?php
		// افزونه‌ی «مدیریت کاربران و عضویت» راه‌های اتصال دیگر (کد کلاس، تأیید شماره) را اضافه می‌کند
		return apply_filters( 'hs_parent_link_form_html', ob_get_clean() );
	}

	/* ---------- AJAX ---------- */

	public static function ajax_link_child() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست نامعتبر.' ) );
		}
		$login = isset( $_POST['student_login'] ) ? sanitize_text_field( wp_unslash( $_POST['student_login'] ) ) : '';
		$pcode  = isset( $_POST['parent_code'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_code'] ) ) : '';
		$result = HS_Parent_Link::link_by_student_login( get_current_user_id(), $login, $pcode );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => 'فرزند با موفقیت متصل شد.' ) );
	}

	public static function ajax_unlink_child() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست نامعتبر.' ) );
		}
		$sid = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;
		HS_Parent_Link::unlink( get_current_user_id(), $sid );
		wp_send_json_success( array( 'message' => 'اتصال قطع شد.' ) );
	}

	public static function ajax_send_msg() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست نامعتبر.' ) );
		}
		$teacher = isset( $_POST['teacher_id'] ) ? absint( $_POST['teacher_id'] ) : 0;
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$child = isset( $_POST['child_id'] ) ? absint( $_POST['child_id'] ) : 0;
		$body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		$media = isset( $_POST['media_url'] ) ? esc_url_raw( wp_unslash( $_POST['media_url'] ) ) : '';
		if ( $media && 0 !== strpos( $media, wp_upload_dir()['baseurl'] . '/' ) ) {
			$media = '';
		}

		if ( ! $teacher || ( '' === $body && '' === $media ) ) {
			wp_send_json_error( array( 'message' => 'پیام خالی است.' ) );
		}
		// گیرنده فقط معلمِ یکی از کلاس‌های همین فرزند؛ با مجوز «پیام به معلم»
		$uid = get_current_user_id();
		if ( ! $child || ! HS_Parent_Link::can_view( $uid, $child ) || ! HSPP_Perms::can( $uid, $child, 'messages' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}
		$ok = false;
		foreach ( HSPP_Perms::student_classes( $child ) as $c ) {
			if ( (int) get_post_meta( $c, '_hs_teacher_id', true ) === $teacher ) {
				$ok       = true;
				$class_id = $c;
			}
		}
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => 'این گیرنده معلم فرزند شما نیست.' ) );
		}

		HS_Comms_DB::send_message( get_current_user_id(), $teacher, $body, $class_id, $child, $media );
		wp_send_json_success( array( 'message' => 'پیام ارسال شد.' ) );
	}
}

HS_Parent_Panel_Frontend::init();
