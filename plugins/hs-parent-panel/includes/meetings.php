<?php
/**
 * درخواست جلسه با معلم
 * والد درخواست می‌دهد (موضوع، حضوری/تلفنی/آنلاین، زمان‌های پیشنهادی) ← معلم زمان می‌دهد یا رد می‌کند.
 * هر تغییر به طرف مقابل پیام داده می‌شود (و در صورت تنظیم پیامک، به والد پیامک).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Meetings {

	const MAX_PENDING = 3;

	public static function init() {
		add_action( 'admin_post_hspp_meet', array( __CLASS__, 'handle' ) );
		add_shortcode( 'hs_parent_meetings', array( __CLASS__, 'teacher_page' ) );
	}

	public static function topics() {
		return array( 'study' => 'وضعیت درسی', 'behavior' => 'رفتار و روابط', 'health' => 'سلامت و روحیه', 'other' => 'سایر' );
	}

	public static function modes() {
		return array( 'inperson' => '🏫 حضوری', 'phone' => '📞 تلفنی', 'online' => '💻 آنلاین' );
	}

	public static function statuses() {
		return array(
			'pending'   => array( 'در انتظار پاسخ معلم', '#F59E0B' ),
			'accepted'  => array( 'تأیید شد', '#10B981' ),
			'declined'  => array( 'رد شد', '#EF4444' ),
			'cancelled' => array( 'لغو شد', '#94A3B8' ),
			'done'      => array( 'برگزار شد', '#4F46E5' ),
		);
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'meetings' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function for_parent( $pid, $sid ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'meetings' ) . ' WHERE parent_id = %d AND student_id = %d ORDER BY id DESC LIMIT 30', $pid, $sid ), ARRAY_A ); // phpcs:ignore
	}

	public static function for_teacher( $tid ) {
		global $wpdb;
		$w = user_can( $tid, 'manage_options' ) ? '1=1' : $wpdb->prepare( 'teacher_id = %d', $tid );
		return (array) $wpdb->get_results( 'SELECT * FROM ' . HSPP_DB::t( 'meetings' ) . " WHERE $w ORDER BY FIELD(status,'pending','accepted','done','declined','cancelled'), COALESCE(slot, created_at) DESC LIMIT 100", ARRAY_A ); // phpcs:ignore
	}

	/** جلسه‌های تأییدشده‌ی آینده (برای تقویم و خلاصه) */
	public static function upcoming_for_parent( $pid, $from, $to ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'meetings' ) . " WHERE parent_id = %d AND status = 'accepted' AND slot BETWEEN %s AND %s ORDER BY slot", $pid, $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
	}

	public static function pending_count( $tid ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSPP_DB::t( 'meetings' ) . " WHERE teacher_id = %d AND status = 'pending'", $tid ) ); // phpcs:ignore
	}

	/** فهرست ۳۰ روز آینده با تاریخ شمسی */
	private static function date_select( $cur ) {
		$h = '<select name="date" required>';
		$t = strtotime( current_time( 'Y-m-d' ) . ' 12:00:00' );
		for ( $i = 0; $i < 30; $i++ ) {
			$d  = gmdate( 'Y-m-d', $t + $i * DAY_IN_SECONDS );
			$h .= '<option value="' . esc_attr( $d ) . '"' . selected( $d, $cur, false ) . '>' . esc_html( HSPP_Util::weekday( $d ) . ' ' . HSPP_Util::d( $d ) ) . '</option>';
		}
		return $h . '</select>';
	}

	private static function badge( $st ) {
		$s = self::statuses();
		$v = isset( $s[ $st ] ) ? $s[ $st ] : array( $st, '#94A3B8' );
		return '<span class="hspp-badge" style="--c:' . esc_attr( $v[1] ) . '">' . esc_html( $v[0] ) . '</span>';
	}

	private static function slot_text( $m ) {
		if ( empty( $m['slot'] ) ) {
			return '';
		}
		$d = substr( $m['slot'], 0, 10 );
		return HSPP_Util::weekday( $d ) . ' ' . HSPP_Util::d( $d ) . ' ساعت ' . substr( $m['slot'], 11, 5 );
	}

	/* ---------- والد ---------- */

	public static function render( $pid, $sid ) {
		$classes = HSPP_Util::child_classes( $pid, $sid, 'meetings' );
		$h       = HSPP_Util::notice();
		$h      .= '<section class="hs-pp-card"><h3>🤝 درخواست جلسه با معلم</h3>';
		$teachers = array();
		foreach ( $classes as $c ) {
			$t = (int) get_post_meta( $c['id'], '_hs_teacher_id', true );
			if ( $t ) {
				$teachers[ $c['id'] ] = $c['title'] . ( $c['teacher'] ? ' — ' . $c['teacher'] : '' );
			}
		}
		if ( ! $teachers ) {
			$h .= '<p class="hs-pp-muted">فعلاً معلمی برای درخواست جلسه در دسترس نیست.</p></section>';
		} else {
			$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hspp-form">'
				. wp_nonce_field( 'hspp_meet', '_wpnonce', true, false )
				. '<input type="hidden" name="action" value="hspp_meet"><input type="hidden" name="op" value="request"><input type="hidden" name="child" value="' . (int) $sid . '">'
				. '<label>کلاس و معلم<select name="class_id">';
			foreach ( $teachers as $cid => $label ) {
				$h .= '<option value="' . (int) $cid . '">' . esc_html( $label ) . '</option>';
			}
			$h .= '</select></label><label>موضوع<select name="topic">';
			foreach ( self::topics() as $k => $v ) {
				$h .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>';
			}
			$h .= '</select></label><label>نوع جلسه<select name="mode">';
			foreach ( self::modes() as $k => $v ) {
				$h .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>';
			}
			$h .= '</select></label>'
				. '<label>زمان‌هایی که برای شما مناسب است<input name="prefs" maxlength="200" placeholder="مثلاً شنبه یا دوشنبه بعد از ساعت ۱۲"></label>'
				. '<label>توضیح کوتاه (اختیاری)<textarea name="details" rows="3" maxlength="1000"></textarea></label>'
				. '<button class="hs-pp-btn hs-pp-btn-primary">ارسال درخواست</button></form>';
			$h .= '</section>';
		}

		$list = self::for_parent( $pid, $sid );
		if ( $list ) {
			$h .= '<section class="hs-pp-card"><h3>درخواست‌های شما</h3><ul class="hs-pp-list hspp-meets">';
			$topics = self::topics();
			$modes  = self::modes();
			foreach ( $list as $m ) {
				$h .= '<li><div><strong>' . esc_html( $topics[ $m['topic'] ] ?? $m['topic'] ) . '</strong> · ' . esc_html( $modes[ $m['mode'] ] ?? '' ) . ' ' . self::badge( $m['status'] )
					. ( $m['slot'] ? '<div class="hspp-slot">🗓 ' . esc_html( self::slot_text( $m ) ) . ( $m['place'] ? ' · ' . esc_html( $m['place'] ) : '' ) . '</div>' : '' )
					. ( $m['reply'] ? '<div class="hspp-reply">پاسخ معلم: ' . esc_html( $m['reply'] ) . '</div>' : '' )
					. '<small class="hs-pp-muted">درخواست: ' . esc_html( HSPP_Util::d( $m['created_at'] ) ) . '</small></div>';
				if ( in_array( $m['status'], array( 'pending', 'accepted' ), true ) ) {
					$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'این جلسه لغو شود؟\')">' . wp_nonce_field( 'hspp_meet', '_wpnonce', true, false )
						. '<input type="hidden" name="action" value="hspp_meet"><input type="hidden" name="op" value="cancel"><input type="hidden" name="id" value="' . (int) $m['id'] . '"><button class="hspp-link">لغو</button></form>';
				}
				$h .= '</li>';
			}
			$h .= '</ul></section>';
		}
		return $h;
	}

	/* ---------- معلم ---------- */

	public static function teacher_page() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$uid  = get_current_user_id();
		$list = self::for_teacher( $uid );
		$h    = '<div class="hspp-wrap" dir="rtl">' . HSPP_Util::notice() . '<section class="hs-card"><h2>🤝 درخواست‌های جلسه‌ی والدین</h2>';
		if ( ! $list ) {
			return $h . '<p class="hs-muted">هنوز درخواستی نرسیده است.</p></section></div>';
		}
		$topics = self::topics();
		$modes  = self::modes();
		$h     .= '<ul class="hspp-meets hspp-meets-t">';
		foreach ( $list as $m ) {
			$p   = get_userdata( $m['parent_id'] );
			$s   = get_userdata( $m['student_id'] );
			$pn  = HSPP_Names::relation_label( $m['parent_id'], $m['student_id'] ) . ' ' . ( $s ? $s->display_name : '' );
			$h  .= '<li class="hspp-meet is-' . esc_attr( $m['status'] ) . '"><div class="hspp-meet-top"><strong>' . esc_html( $pn ) . '</strong>'
				. ( $p && $pn !== $p->display_name ? ' <small>(' . esc_html( $p->display_name ) . ')</small>' : '' )
				. ' · ' . esc_html( get_the_title( $m['class_id'] ) ) . ' ' . self::badge( $m['status'] ) . '</div>'
				. '<div>موضوع: ' . esc_html( $topics[ $m['topic'] ] ?? '' ) . ' · ' . esc_html( $modes[ $m['mode'] ] ?? '' ) . '</div>'
				. ( $m['prefs'] ? '<div>زمان پیشنهادی والد: ' . esc_html( $m['prefs'] ) . '</div>' : '' )
				. ( $m['details'] ? '<div class="hspp-reply">' . nl2br( esc_html( $m['details'] ) ) . '</div>' : '' )
				. ( $m['slot'] ? '<div class="hspp-slot">🗓 ' . esc_html( self::slot_text( $m ) ) . ( $m['place'] ? ' · ' . esc_html( $m['place'] ) : '' ) . '</div>' : '' );
			if ( in_array( $m['status'], array( 'pending', 'accepted' ), true ) ) {
				$base = '<input type="hidden" name="action" value="hspp_meet"><input type="hidden" name="id" value="' . (int) $m['id'] . '">' . wp_nonce_field( 'hspp_meet', '_wpnonce', true, false );
				$h   .= '<details' . ( 'pending' === $m['status'] ? ' open' : '' ) . '><summary>' . ( 'pending' === $m['status'] ? 'پاسخ دادن' : 'تغییر زمان / نتیجه' ) . '</summary>'
					. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hspp-form hspp-inline">' . $base . '<input type="hidden" name="op" value="accept">'
					. '<label>تاریخ' . self::date_select( $m['slot'] ? substr( $m['slot'], 0, 10 ) : '' ) . '</label>'
					. '<label>ساعت<input type="time" name="time" required value="' . esc_attr( $m['slot'] ? substr( $m['slot'], 11, 5 ) : '' ) . '"></label>'
					. '<label>مکان یا پیوند<input name="place" maxlength="180" value="' . esc_attr( $m['place'] ) . '" placeholder="دفتر مدرسه / شماره تماس / لینک"></label>'
					. '<label>پیام به والد<input name="reply" maxlength="500" value="' . esc_attr( $m['reply'] ) . '"></label>'
					. '<button class="hs-btn hs-btn-primary">✅ تأیید و اعلام زمان</button></form>'
					. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hspp-form hspp-inline">' . $base . '<input type="hidden" name="op" value="decline">'
					. '<label>دلیل یا پیشنهاد<input name="reply" maxlength="500" placeholder="مثلاً لطفاً هفته‌ی بعد تماس بگیرید"></label><button class="hs-btn">رد درخواست</button></form>';
				if ( 'accepted' === $m['status'] ) {
					$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . $base . '<input type="hidden" name="op" value="done"><button class="hs-btn">✔ برگزار شد</button></form>';
				}
				$h .= '</details>';
			}
			$h .= '</li>';
		}
		return $h . '</ul></section></div>';
	}

	/* ---------- ذخیره ---------- */

	public static function handle() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'hspp_meet' ) ) {
			wp_die( 'نامعتبر' );
		}
		global $wpdb;
		$uid = get_current_user_id();
		$t   = HSPP_DB::t( 'meetings' );
		$op  = sanitize_key( $_POST['op'] ?? '' );
		$now = HSPP_DB::now();

		if ( 'request' === $op ) {
			$sid = (int) ( $_POST['child'] ?? 0 );
			$cid = (int) ( $_POST['class_id'] ?? 0 );
			if ( ! HSPP_Perms::can( $uid, $sid, 'meetings', $cid ) || ! in_array( $cid, HSPP_Perms::student_classes( $sid ), true ) ) {
				HSPP_Util::back( 'اجازه‌ی این کار را ندارید.' );
			}
			$tid = (int) get_post_meta( $cid, '_hs_teacher_id', true );
			if ( ! $tid ) {
				HSPP_Util::back( 'این کلاس معلم ندارد.' );
			}
			$pend = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE parent_id = %d AND student_id = %d AND status = 'pending'", $uid, $sid ) ); // phpcs:ignore
			if ( $pend >= self::MAX_PENDING ) {
				HSPP_Util::back( 'تا پاسخ معلم به درخواست‌های قبلی، درخواست جدید ممکن نیست.' );
			}
			$topic = sanitize_key( $_POST['topic'] ?? '' );
			$mode  = sanitize_key( $_POST['mode'] ?? '' );
			$row   = array(
				'class_id'   => $cid,
				'student_id' => $sid,
				'parent_id'  => $uid,
				'teacher_id' => $tid,
				'topic'      => isset( self::topics()[ $topic ] ) ? $topic : 'other',
				'mode'       => isset( self::modes()[ $mode ] ) ? $mode : 'inperson',
				'prefs'      => mb_substr( sanitize_text_field( wp_unslash( $_POST['prefs'] ?? '' ) ), 0, 200 ),
				'details'    => mb_substr( sanitize_textarea_field( wp_unslash( $_POST['details'] ?? '' ) ), 0, 1000 ),
				'status'     => 'pending',
				'created_at' => $now,
				'updated_at' => $now,
			);
			$wpdb->insert( $t, $row );
			$s = get_userdata( $sid );
			self::notify( $uid, $tid, '🤝 درخواست جلسه درباره‌ی ' . ( $s ? $s->display_name : '' ) . ' (' . self::topics()[ $row['topic'] ] . '، ' . wp_strip_all_tags( self::modes()[ $row['mode'] ] ) . ')' . ( $row['prefs'] ? "\nزمان پیشنهادی: " . $row['prefs'] : '' ), $cid, $sid );
			HSPP_DB::log( 'meet_request', (int) $wpdb->insert_id, array( 'class' => $cid ) );
			HSPP_Util::back( 'درخواست شما برای معلم فرستاده شد.' );
		}

		$m = self::get( (int) ( $_POST['id'] ?? 0 ) );
		if ( ! $m ) {
			HSPP_Util::back( 'پیدا نشد.' );
		}
		if ( 'cancel' === $op ) {
			if ( (int) $m['parent_id'] !== $uid || ! in_array( $m['status'], array( 'pending', 'accepted' ), true ) ) {
				HSPP_Util::back( 'اجازه‌ی این کار را ندارید.' );
			}
			$wpdb->update( $t, array( 'status' => 'cancelled', 'updated_at' => $now ), array( 'id' => $m['id'] ) );
			self::notify( $uid, $m['teacher_id'], '❎ والد درخواست جلسه را لغو کرد.', $m['class_id'], $m['student_id'] );
			HSPP_Util::back( 'لغو شد.' );
		}

		$is_teacher = (int) $m['teacher_id'] === $uid || (int) get_post_meta( $m['class_id'], '_hs_teacher_id', true ) === $uid || current_user_can( 'manage_options' );
		if ( ! $is_teacher ) {
			HSPP_Util::back( 'اجازه‌ی این کار را ندارید.' );
		}
		$reply = mb_substr( sanitize_text_field( wp_unslash( $_POST['reply'] ?? '' ) ), 0, 500 );
		if ( 'accept' === $op ) {
			$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
			$time = sanitize_text_field( wp_unslash( $_POST['time'] ?? '' ) );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
				HSPP_Util::back( 'تاریخ و ساعت را درست وارد کنید.' );
			}
			$up = array( 'status' => 'accepted', 'slot' => $date . ' ' . $time . ':00', 'place' => mb_substr( sanitize_text_field( wp_unslash( $_POST['place'] ?? '' ) ), 0, 180 ), 'reply' => $reply, 'updated_at' => $now );
			$wpdb->update( $t, $up, array( 'id' => $m['id'] ) );
			$txt = '✅ جلسه تأیید شد: ' . self::slot_text( array_merge( $m, $up ) ) . ( $up['place'] ? ' — ' . $up['place'] : '' ) . ( $reply ? "\n" . $reply : '' );
			self::notify( $uid, $m['parent_id'], $txt, $m['class_id'], $m['student_id'], true );
			HSPP_Util::back( 'زمان جلسه برای والد فرستاده شد.' );
		}
		if ( 'decline' === $op ) {
			$wpdb->update( $t, array( 'status' => 'declined', 'reply' => $reply, 'updated_at' => $now ), array( 'id' => $m['id'] ) );
			self::notify( $uid, $m['parent_id'], 'درخواست جلسه پذیرفته نشد.' . ( $reply ? ' ' . $reply : '' ), $m['class_id'], $m['student_id'], true );
			HSPP_Util::back( 'به والد اطلاع داده شد.' );
		}
		if ( 'done' === $op ) {
			$wpdb->update( $t, array( 'status' => 'done', 'updated_at' => $now ), array( 'id' => $m['id'] ) );
			HSPP_Util::back( 'ثبت شد.' );
		}
		HSPP_Util::back( 'نامعتبر.' );
	}

	private static function notify( $from, $to, $text, $cid, $sid, $sms = false ) {
		if ( class_exists( 'HS_Comms_DB' ) ) {
			HS_Comms_DB::send_message( $from, $to, $text, $cid, $sid );
		}
		if ( $sms && 'sms' === get_user_meta( $to, '_hspp_digest', true ) ) {
			HSPP_Util::sms( $to, $text );
		}
	}
}

HSPP_Meetings::init();
