<?php
/**
 * رابط چت والدین — شبیه پیام‌رسان
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Parent_Chat_Frontend {

	public static function init() {
		add_shortcode( 'hamshagerdi_parent_chat', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="hs-pchat-gate"><p>برای ورود به گفتگوی والدین وارد حساب شوید.</p>
				<a class="hs-pp-btn hs-pp-btn-primary" href="' . esc_url( wp_login_url( home_url( '/panel/parent-chat/' ) ) ) . '">ورود</a></div>';
		}

		$user_id = get_current_user_id();

		// کلاس‌هایی که والد به آن‌ها دسترسی دارد
		$rooms = self::available_rooms( $user_id );
		$embed    = ! empty( $atts['embed'] );
		$class_id = isset( $_GET['chat_class'] ) ? absint( $_GET['chat_class'] ) : -1;
		if ( isset( $atts['class_id'] ) ) {
			$class_id = absint( $atts['class_id'] );
		}

		// پیش‌فرض: اولین کلاس یا عمومی
		if ( $class_id < 0 ) {
			$class_id = ! empty( $rooms ) ? (int) $rooms[0]['id'] : 0;
		}

		// اعتبارسنجی دسترسی
		if ( ! HS_Parent_Chat_Handlers::can_access_class( $class_id, $user_id ) ) {
			if ( ! empty( $rooms ) ) {
				$class_id = (int) $rooms[0]['id'];
			} else {
				$class_id = 0;
				if ( ! HS_Parent_Chat_Handlers::can_access_class( 0, $user_id ) ) {
					return '<div class="hs-pchat-gate"><p>برای استفاده از گفتگو، ابتدا فرزند خود را در <a href="' . esc_url( home_url( '/panel/parent/' ) ) . '">پنل والدین</a> متصل کنید.</p></div>';
				}
			}
		}

		$room_title = 'گفتگوی عمومی والدین';
		foreach ( $rooms as $r ) {
			if ( (int) $r['id'] === (int) $class_id ) {
				$room_title = $r['title'];
				break;
			}
		}
		if ( $class_id === 0 ) {
			$room_title = 'گفتگوی عمومی والدین';
		}

		ob_start();
		?>
		<div class="hs-pchat" id="hs-pchat"
			 data-class="<?php echo esc_attr( $class_id ); ?>"
			 data-me="<?php echo esc_attr( $user_id ); ?>">

			<header class="hs-pchat-header">
				<?php if ( ! $embed ) : ?>
				<div class="hs-pchat-header-top">
					<a href="<?php echo esc_url( home_url( '/panel/parent/' ) ); ?>" class="hs-pchat-back">← پنل والدین</a>
					<h2>💬 گفتگوی والدین</h2>
				</div>
				<?php endif; ?>
				<?php if ( ! $embed && count( $rooms ) > 1 || ( count( $rooms ) === 1 && $rooms[0]['id'] !== 0 ) ) : ?>
				<div class="hs-pchat-rooms">
					<a href="<?php echo esc_url( add_query_arg( 'chat_class', 0, home_url( '/panel/parent-chat/' ) ) ); ?>"
					   class="hs-pchat-room-chip <?php echo $class_id === 0 ? 'active' : ''; ?>">عمومی</a>
					<?php foreach ( $rooms as $r ) :
						if ( (int) $r['id'] === 0 ) continue;
						?>
						<a href="<?php echo esc_url( add_query_arg( 'chat_class', $r['id'], home_url( '/panel/parent-chat/' ) ) ); ?>"
						   class="hs-pchat-room-chip <?php echo (int) $class_id === (int) $r['id'] ? 'active' : ''; ?>">
							<?php echo esc_html( $r['title'] ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<div class="hs-pchat-room-bar">
					<p class="hs-pchat-room-name"><?php echo esc_html( $room_title ); ?></p>
					<?php if ( $class_id ) : ?>
						<button type="button" class="hs-pchat-chip" id="hspp-myname" title="نام شما در این گروه">✏️ <span></span></button>
						<button type="button" class="hs-pchat-chip hs-pchat-mod-only" id="hspp-mod-btn" hidden>⚙️ مدیریت گروه <b id="hspp-rep-count" hidden></b></button>
					<?php endif; ?>
				</div>
			</header>

			<div class="hspp-top" id="hspp-top"></div>
			<div class="hspp-modpanel" id="hspp-modpanel" hidden></div>

			<div class="hs-pchat-messages" id="hs-pchat-messages">
				<div class="hs-pchat-loading">در حال بارگذاری پیام‌ها...</div>
			</div>

			<div class="hs-pchat-reply-bar" id="hs-pchat-reply-bar" style="display:none;">
				<div class="hs-pchat-reply-info">
					<strong id="hs-pchat-reply-name"></strong>
					<span id="hs-pchat-reply-preview"></span>
				</div>
				<button type="button" class="hs-pchat-reply-cancel" id="hs-pchat-reply-cancel">×</button>
			</div>

			<div class="hspp-why" id="hspp-why" hidden></div>
			<div class="hs-pchat-composer">
				<button type="button" class="hs-pchat-icon-btn" id="hs-pchat-img-btn" title="ارسال تصویر">🖼️</button>
				<input type="file" id="hs-pchat-img-input" accept="image/*" hidden>
				<button type="button" class="hs-pchat-icon-btn" id="hs-pchat-voice-btn" title="پیام صوتی">🎤</button>
				<div class="hs-pchat-input-wrap">
					<textarea id="hs-pchat-input" rows="1" placeholder="پیام بنویسید..."></textarea>
				</div>
				<button type="button" class="hs-pchat-send" id="hs-pchat-send" title="ارسال">➤</button>
			</div>

			<div class="hs-pchat-voice-bar" id="hs-pchat-voice-bar" style="display:none;">
				<span class="hs-pchat-rec-dot"></span>
				<span id="hs-pchat-rec-time">0:00</span>
				<button type="button" class="hs-pchat-btn-soft" id="hs-pchat-voice-cancel">لغو</button>
				<button type="button" class="hs-pchat-btn-send-voice" id="hs-pchat-voice-send">ارسال صوت</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * لیست اتاق‌های قابل دسترسی برای کاربر
	 */
	private static function available_rooms( $user_id ) {
		$rooms = array();
		// عمومی همیشه اگر والد/معلم باشد
		$rooms[] = array( 'id' => 0, 'title' => 'گفتگوی عمومی والدین' );

		$children = class_exists( 'HS_Parent_Link' ) ? HS_Parent_Link::get_children( $user_id ) : array();
		$seen = array();
		foreach ( $children as $sid ) {
			$classes = HS_Parent_Link::student_classes( $sid );
			foreach ( $classes as $c ) {
				if ( ! isset( $seen[ $c['id'] ] ) ) {
					$seen[ $c['id'] ] = true;
					$rooms[] = array( 'id' => $c['id'], 'title' => $c['title'] );
				}
			}
		}

		// معلم: کلاس‌های خودش
		if ( HS_Roles::is_teacher_or_admin( $user_id ) ) {
			$q = new WP_Query( array(
				'post_type'      => 'hs_class',
				'posts_per_page' => 30,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'   => '_hs_teacher_id',
						'value' => $user_id,
					),
				),
			) );
			foreach ( $q->posts as $p ) {
				if ( ! isset( $seen[ $p->ID ] ) ) {
					$seen[ $p->ID ] = true;
					$rooms[] = array( 'id' => $p->ID, 'title' => $p->post_title );
				}
			}
		}

		// فقط گروه‌هایی که کاربر واقعاً اجازه دارد (مجوز «گروه والدین»، گروه روشن)
		$rooms = array_values( array_filter( $rooms, function ( $r ) use ( $user_id ) {
			return HS_Parent_Chat_Handlers::can_access_class( (int) $r['id'], $user_id );
		} ) );
		return $rooms;
	}
}

HS_Parent_Chat_Frontend::init();
