<?php
/**
 * رابط کاربری ارتباط: اطلاعیه، فید کلاسی، پیام والدین
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Comms_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_announcements', array( __CLASS__, 'render_announcements' ) );
		add_shortcode( 'hamshagerdi_messages', array( __CLASS__, 'render_messages' ) );
		add_shortcode( 'hamshagerdi_comms', array( __CLASS__, 'render_combined' ) );
	}

	/**
	 * صفحه ترکیبی ارتباط (اطلاعیه + فید + پیام)
	 */
	public static function render_combined( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-alert hs-alert-warning">برای استفاده باید وارد شوید.</div>';
		}
		if ( ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش فقط برای معلمان در دسترس است.</div>';
		}

		$classes = self::get_teacher_classes();
		$selected = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0;
		if ( ! $selected && ! empty( $classes ) ) {
			$selected = $classes[0]['id'];
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'announce';
		if ( ! in_array( $tab, array( 'announce', 'feed', 'messages' ), true ) ) {
			$tab = 'announce';
		}

		ob_start();
		?>
		<div class="hs-comms-panel" id="hs-comms-panel" data-class-id="<?php echo esc_attr( $selected ); ?>">
			<div class="hs-att-header">
				<div>
					<a href="<?php echo esc_url( home_url( '/panel/' ) ); ?>" class="hs-back-link">← بازگشت به داشبورد</a>
					<h2>ارتباط با والدین</h2>
					<p class="hs-muted">اطلاعیه کلاس، فید گروهی، پیام خصوصی و گفتگوی عمومی با والدین</p>
				</div>
			</div>

			<?php if ( empty( $classes ) ) : ?>
				<div class="hs-empty-state">
					<div class="hs-empty-icon">💬</div>
					<h3>هنوز کلاسی ندارید</h3>
					<a href="<?php echo esc_url( home_url( '/panel/classes/' ) ); ?>" class="hs-btn hs-btn-primary">+ ساخت کلاس</a>
				</div>
			<?php else : ?>

			<div class="hs-card hs-att-filters">
				<div class="hs-att-filter-row" style="grid-template-columns:1fr auto;">
					<div class="hs-form-row">
						<label>کلاس</label>
						<select id="hs-comms-class">
							<?php foreach ( $classes as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $selected, $c['id'] ); ?>>
									<?php echo esc_html( $c['title'] ); ?>
									<?php if ( $c['grade'] ) echo ' (' . esc_html( $c['grade'] ) . ')'; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hs-form-row hs-att-filter-actions">
						<label>&nbsp;</label>
						<button type="button" class="hs-btn hs-btn-primary" id="hs-comms-load">نمایش</button>
					</div>
				</div>
			</div>

			<nav class="hs-comms-tabs">
				<a href="<?php echo esc_url( add_query_arg( array( 'class_id' => $selected, 'tab' => 'announce' ) ) ); ?>"
				   class="hs-comms-tab <?php echo $tab === 'announce' ? 'active' : ''; ?>">📢 اطلاعیه‌ها</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'class_id' => $selected, 'tab' => 'feed' ) ) ); ?>"
				   class="hs-comms-tab <?php echo $tab === 'feed' ? 'active' : ''; ?>">📖 فید کلاس</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'class_id' => $selected, 'tab' => 'messages' ) ) ); ?>"
				   class="hs-comms-tab <?php echo $tab === 'messages' ? 'active' : ''; ?>">💬 پیام‌ها
					<?php
					$unread = HS_Comms_DB::count_unread( get_current_user_id() );
					if ( $unread > 0 ) {
						echo '<span class="nav-badge">' . (int) $unread . '</span>';
					}
					?>
				</a>
			</nav>

			<?php
			if ( $selected && HS_Roles::can_manage_class( $selected ) ) {
				if ( $tab === 'announce' ) {
					echo self::render_announce_tab( $selected );
				} elseif ( $tab === 'feed' ) {
					echo self::render_feed_tab( $selected );
				} else {
					echo self::render_messages_tab( $selected );
				}
			}
			?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_announce_tab( $class_id ) {
		$items = HS_Comms_DB::get_announcements( $class_id, 30, true ); // معلم: بایگانی‌شده‌ها هم
		ob_start();
		?>
		<div class="hs-comms-tab-content" id="hs-tab-announce">
			<div class="hs-card">
				<h3>ارسال اطلاعیه به والدین کلاس</h3>
				<p class="hs-muted">این پیام برای همه والدین مرتبط با کلاس قابل مشاهده خواهد بود.</p>
				<form id="hs-form-announcement">
					<div class="hs-form-row">
						<label>عنوان (اختیاری)</label>
						<input type="text" id="hs-ann-title" placeholder="مثلاً: جلسه اولیا — چهارشنبه">
					</div>
					<div class="hs-form-row">
						<label>متن اطلاعیه <span class="required">*</span></label>
						<textarea id="hs-ann-body" rows="4" placeholder="متن اطلاعیه را بنویسید..." required></textarea>
					</div>
					<label class="hs-check-label">
						<input type="checkbox" id="hs-ann-pinned"> سنجاق کردن (بالای لیست)
					</label>
					<div class="hs-form-actions">
						<button type="submit" class="hs-btn hs-btn-primary">📢 انتشار اطلاعیه</button>
					</div>
					<div class="hs-form-message" id="hs-ann-message"></div>
				</form>
			</div>

			<div class="hs-card">
				<h3>اطلاعیه‌های قبلی</h3>
				<ul class="hs-ann-list" id="hs-ann-list">
					<?php if ( empty( $items ) ) : ?>
						<li class="hs-muted hs-ann-empty">هنوز اطلاعیه‌ای ثبت نشده.</li>
					<?php else : ?>
						<?php foreach ( $items as $item ) :
							$author = get_userdata( $item['author_id'] );
							?>
							<li class="hs-ann-item <?php echo $item['is_pinned'] ? 'pinned' : ''; ?>" data-id="<?php echo esc_attr( $item['id'] ); ?>">
								<?php if ( $item['is_pinned'] ) : ?><span class="hs-pin">📌</span><?php endif; ?>
								<?php
								$st = HS_Comms_DB::ann_state( $item );
								if ( 'archived' === $st ) {
									echo '<span class="hs-pill">🗄️ بایگانی (دیگر به والدین نمایش داده نمی‌شود)</span> ';
								} elseif ( 'scheduled' === $st ) {
									echo '<span class="hs-pill">⏳ از ' . esc_html( self::format_date( $item['show_from'] ) ) . ' نمایش داده می‌شود</span> ';
								} elseif ( ! empty( $item['show_until'] ) ) {
									echo '<span class="hs-pill">⏱️ تا ' . esc_html( self::format_date( $item['show_until'] ) ) . '</span> ';
								}
								?>
								<?php if ( $item['title'] ) : ?>
									<strong class="hs-ann-title"><?php echo esc_html( $item['title'] ); ?></strong>
								<?php endif; ?>
								<div class="hs-ann-body"><?php echo wp_kses_post( wpautop( $item['body'] ) ); ?></div>
								<div class="hs-ann-meta">
									<span><?php echo $author ? esc_html( $author->display_name ) : ''; ?></span>
									<span><?php echo esc_html( self::format_date( $item['created_at'] ) ); ?></span>
									<button type="button" class="hs-btn-remove hs-ann-delete" data-id="<?php echo esc_attr( $item['id'] ); ?>" title="حذف">×</button>
								</div>
								<?php do_action( 'hs_announcement_meta', $item, $class_id ); // «دیده شد» از افزونه‌ی پنل والدین ?>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_feed_tab( $class_id ) {
		$posts = HS_Comms_DB::get_feed( $class_id );
		ob_start();
		?>
		<div class="hs-comms-tab-content" id="hs-tab-feed">
			<div class="hs-card">
				<h3>فید کلاس (Class Story)</h3>
				<p class="hs-muted">فعالیت‌ها و لحظات کلاس را با والدین به اشتراک بگذارید.</p>
				<form id="hs-form-feed">
					<div class="hs-form-row">
						<textarea id="hs-feed-body" rows="3" placeholder="امروز در کلاس چه خبر بود؟" required></textarea>
					</div>
					<div class="hs-form-actions">
						<button type="submit" class="hs-btn hs-btn-primary">📖 انتشار در فید</button>
					</div>
					<div class="hs-form-message" id="hs-feed-message"></div>
				</form>
			</div>

			<div class="hs-feed-list" id="hs-feed-list">
				<?php if ( empty( $posts ) ) : ?>
					<div class="hs-empty-state hs-feed-empty">
						<p>هنوز پستی در فید نیست. اولین لحظه کلاس را ثبت کنید.</p>
					</div>
				<?php else : ?>
					<?php foreach ( $posts as $p ) :
						$author = get_userdata( $p['author_id'] );
						?>
						<article class="hs-feed-card" data-id="<?php echo esc_attr( $p['id'] ); ?>">
							<div class="hs-feed-header">
								<div class="hs-student-avatar"><?php echo esc_html( mb_substr( $author ? $author->display_name : 'م', 0, 1 ) ); ?></div>
								<div>
									<strong><?php echo $author ? esc_html( $author->display_name ) : 'معلم'; ?></strong>
									<span class="hs-muted"><?php echo esc_html( self::format_date( $p['created_at'] ) ); ?></span>
								</div>
								<button type="button" class="hs-btn-remove hs-feed-delete" data-id="<?php echo esc_attr( $p['id'] ); ?>">×</button>
							</div>
							<div class="hs-feed-body"><?php echo wp_kses_post( wpautop( $p['body'] ) ); ?></div>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_messages_tab( $class_id ) {
		$mt = isset( $_GET['mt'] ) && 'public' === $_GET['mt'] ? 'public' : 'private'; // phpcs:ignore WordPress.Security.NonceVerification
		$base = array( 'class_id' => $class_id, 'tab' => 'messages' );
		$seg  = '<nav class="hs-msg-seg" aria-label="نوع پیام">'
			. '<a class="hs-msg-seg-item' . ( 'private' === $mt ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( array_merge( $base, array( 'mt' => 'private' ) ) ) ) . '">🔒 پیام خصوصی <small>به یک والد</small></a>'
			. '<a class="hs-msg-seg-item' . ( 'public' === $mt ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( array_merge( $base, array( 'mt' => 'public' ) ) ) ) . '">👥 پیام عمومی <small>گفتگوی همهٔ والدین کلاس</small></a>'
			. '</nav>';
		if ( 'public' === $mt ) {
			$chat = shortcode_exists( 'hamshagerdi_parent_chat' )
				? HS_Parent_Chat_Frontend::render( array( 'class_id' => $class_id, 'embed' => 1 ) )
				: '';
			return '<div class="hs-comms-tab-content" id="hs-tab-messages">' . $seg
				. '<p class="hs-muted hs-msg-note">پیام‌های این بخش را همهٔ والدین و معلم این کلاس می‌بینند. متن، عکس و پیام صوتی ارسال کنید.</p>' . $chat . '</div>';
		}
		$inbox = HS_Comms_DB::get_inbox( get_current_user_id() );
		// دانش‌آموزان کلاس برای ارتباط «درباره این دانش‌آموز»
		$students = HS_Class_Students::get_students( $class_id );

		// گیرندگان مجاز: فقط والدینِ دانش‌آموزان همین کلاس
		$parent_ids = array();
		foreach ( $students as $st ) {
			$parent_ids = array_merge( $parent_ids, HS_Parent_Link::get_parents( $st->ID ) );
		}
		$parent_ids = array_values( array_unique( array_map( 'absint', $parent_ids ) ) );
		$parents    = $parent_ids ? get_users( array( 'include' => $parent_ids, 'orderby' => 'display_name' ) ) : array();

		ob_start();
		?>
		<div class="hs-comms-tab-content" id="hs-tab-messages">
			<?php echo $seg; // phpcs:ignore ?>
			<div class="hs-msg-layout">
				<aside class="hs-msg-inbox hs-card">
					<h3>صندوق پیام</h3>
					<ul class="hs-msg-threads" id="hs-msg-threads">
						<?php if ( empty( $inbox ) ) : ?>
							<li class="hs-muted">هنوز پیامی نیست. از فرم مقابل پیام جدید بفرستید.</li>
						<?php else : ?>
							<?php foreach ( $inbox as $m ) :
								$me = get_current_user_id();
								$other_id = (int) $m['sender_id'] === $me ? (int) $m['recipient_id'] : (int) $m['sender_id'];
								$other = get_userdata( $other_id );
								$unread = (int) $m['recipient_id'] === $me && ! (int) $m['is_read'];
								?>
								<li class="hs-msg-thread <?php echo $unread ? 'unread' : ''; ?>" data-other-id="<?php echo esc_attr( $other_id ); ?>">
									<strong><?php echo $other ? esc_html( $other->display_name ) : 'کاربر'; ?></strong>
									<span class="hs-msg-preview"><?php echo esc_html( HS_Comms_DB::preview( $m ) ); ?></span>
								</li>
							<?php endforeach; ?>
						<?php endif; ?>
					</ul>
				</aside>

				<div class="hs-msg-main">
					<div class="hs-card" id="hs-msg-compose">
						<h3>پیام جدید</h3>
						<form id="hs-form-message">
							<div class="hs-form-row">
								<label>گیرنده (والد)</label>
								<select id="hs-msg-recipient" required>
									<option value="">— انتخاب کنید —</option>
									<?php foreach ( $parents as $p ) : ?>
										<option value="<?php echo esc_attr( $p->ID ); ?>"><?php echo esc_html( $p->display_name ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( empty( $parents ) ) : ?>
									<p class="hs-muted" style="margin-top:0.35rem;font-size:0.85rem;">هنوز والدی به دانش‌آموزان این کلاس متصل نشده است. از بخش «کد والد» هر دانش‌آموز، والد را متصل کنید.</p>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $students ) ) : ?>
							<div class="hs-form-row">
								<label>درباره دانش‌آموز (اختیاری)</label>
								<select id="hs-msg-student">
									<option value="0">— عمومی —</option>
									<?php foreach ( $students as $s ) : ?>
										<option value="<?php echo esc_attr( $s->ID ); ?>"><?php echo esc_html( $s->display_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php endif; ?>
							<div class="hs-form-row">
								<label>متن پیام</label>
								<textarea id="hs-msg-body" rows="3" placeholder="پیام خود را بنویسید..."></textarea>
							</div>
							<div class="hs-form-row">
								<label>عکس (اختیاری)</label>
								<?php echo HS_Upload::picker( 'hs-msg-image', 'general', array( 'label' => 'ارسال عکس' ) ); // phpcs:ignore ?>
							</div>
							<button type="submit" class="hs-btn hs-btn-primary">ارسال پیام</button>
							<div class="hs-form-message" id="hs-msg-send-message"></div>
						</form>
					</div>

					<div class="hs-card" id="hs-msg-chat" style="display:none;">
						<div class="hs-msg-chat-header">
							<button type="button" class="hs-btn hs-btn-sm hs-btn-ghost" id="hs-msg-back">← بازگشت</button>
							<strong id="hs-msg-chat-name"></strong>
						</div>
						<div class="hs-msg-chat-body" id="hs-msg-chat-body"></div>
						<form id="hs-form-reply" class="hs-msg-reply">
							<input type="hidden" id="hs-msg-reply-to" value="">
							<input type="hidden" id="hs-msg-reply-media" value="">
							<input type="file" id="hs-msg-reply-file" accept="image/*" hidden>
							<button type="button" class="hs-btn hs-btn-ghost" id="hs-msg-reply-clip" title="پیوست عکس">📎</button>
							<input type="text" id="hs-msg-reply-body" placeholder="پاسخ...">
							<button type="submit" class="hs-btn hs-btn-primary">ارسال</button>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** شورت‌کد جدا برای سازگاری */
	public static function render_announcements( $atts = array() ) {
		$_GET['tab'] = 'announce';
		return self::render_combined( $atts );
	}

	public static function render_messages( $atts = array() ) {
		$_GET['tab'] = 'messages';
		return self::render_combined( $atts );
	}

	private static function get_teacher_classes() {
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

	private static function format_date( $mysql_date ) {
		$ts = strtotime( $mysql_date );
		if ( ! $ts ) {
			return $mysql_date;
		}
		$diff = time() - $ts;
		if ( $diff < 3600 ) {
			return floor( $diff / 60 ) . ' دقیقه پیش';
		}
		if ( $diff < 86400 ) {
			return floor( $diff / 3600 ) . ' ساعت پیش';
		}
		return date_i18n( 'Y/m/d H:i', $ts );
	}
}

HS_Comms_Frontend::init();
