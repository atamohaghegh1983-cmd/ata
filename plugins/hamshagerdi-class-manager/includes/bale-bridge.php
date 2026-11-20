<?php
/**
 * پل پیام سایت ↔ بله + تنظیمات و webhook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Bale_Bridge {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );

		// وقتی پیام معلم↔والد در سایت ارسال شد
		add_action( 'hs_message_sent', array( __CLASS__, 'on_site_message' ), 10, 5 );

		add_shortcode( 'hamshagerdi_bale_link', array( __CLASS__, 'render_link_box' ) );
		add_action( 'wp_ajax_hs_bale_new_code', array( __CLASS__, 'ajax_new_code' ) );
		add_action( 'wp_ajax_hs_bale_unlink', array( __CLASS__, 'ajax_unlink' ) );
	}

	public static function admin_menu() {
		add_options_page(
			'ربات بله همشاگردی',
			'بله همشاگردی',
			'manage_options',
			'hs-bale-settings',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function register_settings() {
		register_setting( 'hs_bale', HS_Bale_API::OPT_TOKEN, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'hs_bale', HS_Bale_API::OPT_ENABLED, array( 'type' => 'boolean' ) );
	}

	public static function settings_page() {
		$token = HS_Bale_API::token();
		$enabled = HS_Bale_API::is_enabled();
		$webhook = HS_Bale_API::webhook_url();
		?>
		<div class="wrap">
			<h1>ربات بله — همشاگردی</h1>
			<p>ارتباط والدین و معلمان از طریق ربات بله. پیام‌های پنل در بله هم ارسال می‌شوند.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'hs_bale' ); ?>
				<table class="form-table">
					<tr>
						<th>فعال‌سازی</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( HS_Bale_API::OPT_ENABLED ); ?>" value="1" <?php checked( get_option( HS_Bale_API::OPT_ENABLED ) ); ?>>
								ربات بله فعال باشد
							</label>
						</td>
					</tr>
					<tr>
						<th>توکن ربات</th>
						<td>
							<input type="password" autocomplete="off" class="regular-text" name="<?php echo esc_attr( HS_Bale_API::OPT_TOKEN ); ?>"
								value="<?php echo esc_attr( $token ); ?>" placeholder="از @botfather در بله بگیرید">
							<p class="description">توکن را از ربات سازنده بله دریافت کنید.</p>
						</td>
					</tr>
					<tr>
						<th>آدرس Webhook</th>
						<td>
							<code><?php echo esc_html( $webhook ); ?></code>
							<p class="description">این آدرس را در تنظیمات webhook ربات بله قرار دهید تا اتصال حساب و پیام‌های ورودی کار کند.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'ذخیره' ); ?>
			</form>
			<?php if ( $token ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'hs_bale_webhook' ); ?>
				<input type="hidden" name="hs_set_webhook" value="1">
				<?php submit_button( 'ثبت خودکار Webhook در بله', 'secondary' ); ?>
			</form>
			<?php
			if ( isset( $_POST['hs_set_webhook'] ) && check_admin_referer( 'hs_bale_webhook' ) ) {
				$r = HS_Bale_API::request( 'setWebhook', array( 'url' => $webhook ) );
				if ( is_wp_error( $r ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $r->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success"><p>Webhook ثبت شد.</p></div>';
				}
			}
			endif;
			?>
		</div>
		<?php
	}

	public static function register_rest() {
		register_rest_route( 'hamshagerdi/v1', '/bale-webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * دریافت آپدیت از بله
	 */
	public static function webhook( $request ) {
		// احراز هویت درخواست با کلید مخفی در آدرس
		$key = (string) $request->get_param( 'key' );
		if ( ! $key || ! hash_equals( HS_Bale_API::webhook_secret(), $key ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}
		if ( ! HS_Bale_API::is_enabled() ) {
			return new WP_REST_Response( array( 'ok' => true ) );
		}
		$data = $request->get_json_params();
		if ( empty( $data['message'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ) );
		}

		$msg = $data['message'];
		$chat_id = isset( $msg['chat']['id'] ) ? (string) $msg['chat']['id'] : '';
		$text = isset( $msg['text'] ) ? trim( $msg['text'] ) : '';

		if ( ! $chat_id ) {
			return new WP_REST_Response( array( 'ok' => true ) );
		}

		// اتصال: /start CODE یا فقط CODE
		if ( preg_match( '/^\/start(?:\s+(.+))?$/ui', $text, $m ) || preg_match( '/^([A-Za-z0-9]{6,8})$/', $text, $m ) ) {
			$code = isset( $m[1] ) ? trim( $m[1] ) : '';
			if ( $code ) {
				$rl = 'bale_' . $chat_id;
				if ( HS_Util::rl_blocked( $rl, 5 ) ) {
					HS_Bale_API::send_message( $chat_id, '⏳ تلاش‌های ناموفق زیاد بود. ۱۰ دقیقه بعد دوباره امتحان کنید.' );
					return new WP_REST_Response( array( 'ok' => true ) );
				}
				$result = HS_Bale_API::link_by_code( $code, $chat_id );
				if ( is_wp_error( $result ) ) {
					HS_Util::rl_hit( $rl, 10 * MINUTE_IN_SECONDS );
					HS_Bale_API::send_message( $chat_id, '❌ ' . $result->get_error_message() );
				} else {
					$user = get_userdata( $result );
					$name = $user ? $user->display_name : '';
					HS_Bale_API::send_message( $chat_id, "✅ حساب «{$name}» به ربات همشاگردی متصل شد.\nاز این پس پیام‌های معلم/والد اینجا هم می‌آید." );
				}
				return new WP_REST_Response( array( 'ok' => true ) );
			}
			HS_Bale_API::send_message( $chat_id, "سلام 👋\nبرای اتصال حساب، کد ۶ حرفی را از پنل سایت بگیرید و اینجا بفرستید.\nیا: /start کد" );
			return new WP_REST_Response( array( 'ok' => true ) );
		}

		// پیام عادی از بله → ذخیره در سایت اگر کاربر متصل باشد
		$user_id = self::user_by_chat( $chat_id );
		if ( ! $user_id || ! $text ) {
			if ( ! $user_id ) {
				HS_Bale_API::send_message( $chat_id, 'حساب شما متصل نیست. کد اتصال را از پنل سایت بگیرید.' );
			}
			return new WP_REST_Response( array( 'ok' => true ) );
		}

		// اگر والد است و معلم مشخص از آخرین مکالمه — ساده: پیام به inbox معلم کلاس اول فرزند
		self::inbound_from_bale( $user_id, $text );

		HS_Bale_API::send_message( $chat_id, '✓ پیام در پنل همشاگردی ثبت شد.' );
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	private static function user_by_chat( $chat_id ) {
		$users = get_users( array(
			'meta_key'   => HS_Bale_API::META_CHAT,
			'meta_value' => $chat_id,
			'number'     => 1,
			'fields'     => 'ID',
		) );
		return $users ? (int) $users[0] : 0;
	}

	/**
	 * پیام ورودی بله را به معلم/والد مقابل در سایت می‌فرستد
	 */
	private static function inbound_from_bale( $sender_id, $text ) {
		if ( ! class_exists( 'HS_Comms_DB' ) ) {
			return;
		}

		$user = get_userdata( $sender_id );
		$roles = $user ? (array) $user->roles : array();

		// والد → معلم کلاس فرزند
		if ( class_exists( 'HS_Parent_Link' ) && ( in_array( 'parent', $roles, true ) || HS_Parent_Link::get_children( $sender_id ) ) ) {
			$children = HS_Parent_Link::get_children( $sender_id );
			foreach ( $children as $sid ) {
				$classes = HS_Parent_Link::student_classes( $sid );
				if ( empty( $classes ) ) {
					continue;
				}
				$teacher_id = (int) ( $classes[0]['teacher_id'] ?? 0 );
				$class_id = (int) ( $classes[0]['id'] ?? 0 );
				if ( $teacher_id ) {
					HS_Comms_DB::send_message( $sender_id, $teacher_id, '[بله] ' . $text, $class_id, $sid );
					// اعلان بله به معلم
					HS_Bale_API::notify_user( $teacher_id, "📩 پیام والد از بله:\n" . $text );
					return;
				}
			}
		}

		// معلم → آخرین والد مرتبط سخت است؛ فقط در inbox خودش لاگ می‌شود
		// معلم می‌تواند از پنل پاسخ دهد
	}

	/**
	 * هوک پس از ارسال پیام در سایت
	 */
	public static function on_site_message( $sender_id, $recipient_id, $body, $class_id = 0, $student_id = 0 ) {
		if ( ! HS_Bale_API::is_enabled() ) {
			return;
		}
		// از ارسال حلقه [بله] جلوگیری
		if ( strpos( $body, '[بله]' ) === 0 ) {
			return;
		}

		$sender = get_userdata( $sender_id );
		$name = $sender ? $sender->display_name : 'کاربر';
		$text = "💬 پیام جدید از {$name} (همشاگردی):\n" . $body;
		if ( $student_id ) {
			$st = get_userdata( $student_id );
			if ( $st ) {
				$text .= "\n👤 مربوط به: " . $st->display_name;
			}
		}
		$text .= "\n\nپاسخ را در بله بفرستید یا از پنل سایت جواب دهید.";

		HS_Bale_API::notify_user( $recipient_id, $text );
	}

	public static function render_link_box() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( ! HS_Bale_API::is_enabled() ) {
			return '<div class="hs-bale-box"><p>ربات بله فعلاً فعال نیست.</p></div>';
		}

		$user_id = get_current_user_id();
		$chat = HS_Bale_API::get_chat_id( $user_id );
		$code = HS_Bale_API::get_link_code( $user_id );

		ob_start();
		?>
		<div class="hs-bale-box" id="hs-bale-box">
			<h3>📱 اتصال به ربات بله</h3>
			<?php if ( $chat ) : ?>
				<p class="hs-bale-ok">✓ حساب شما به بله متصل است.</p>
				<button type="button" class="hs-pp-btn hs-pp-btn-soft" id="hs-bale-unlink">قطع اتصال</button>
			<?php else : ?>
				<p>کد زیر را در ربات بله بفرستید (یا <code>/start <?php echo esc_html( $code ); ?></code>):</p>
				<div class="hs-bale-code"><?php echo esc_html( $code ); ?></div>
				<button type="button" class="hs-pp-btn hs-pp-btn-soft" id="hs-bale-refresh">کد جدید</button>
				<p class="hs-pp-muted">کد یک ساعت اعتبار دارد.</p>
			<?php endif; ?>
			<div class="hs-form-message" id="hs-bale-msg"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_new_code() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'خطا' ) );
		}
		$code = HS_Bale_API::generate_link_code( get_current_user_id() );
		wp_send_json_success( array( 'code' => $code ) );
	}

	public static function ajax_unlink() {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'خطا' ) );
		}
		HS_Bale_API::unlink( get_current_user_id() );
		wp_send_json_success( array( 'message' => 'قطع شد.' ) );
	}
}

HS_Bale_Bridge::init();
