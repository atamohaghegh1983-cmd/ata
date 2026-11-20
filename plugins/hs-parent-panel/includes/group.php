<?php
/**
 * گروه والدین هر کلاس — ناظر و قوانین
 *
 * ناظران: معلم کلاس، مدیر سایت، و حداکثر ۲ دستیار (از والدین همان کلاس) که معلم انتخاب می‌کند.
 * ناظر می‌تواند: پیام حذف کند، عضو را موقت بی‌صدا کند، گروه را فقط‌خواندنی کند، ساعت سکوت و ارسال عکس را تنظیم کند،
 *               پیام سنجاق کند، نظرسنجی بسازد/ببندد، گزارش‌ها را ببیند.
 * دستیار نمی‌تواند دستیار تعیین کند یا گروه را خاموش کند.
 * همه: گزارش پیام نامناسب، رأی در نظرسنجی، تغییر نام نمایشی خود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Group {

	const MAX_ASSISTANTS = 2;

	public static function init() {
		foreach ( array( 'settings', 'assistant', 'mute', 'delete', 'report', 'pin', 'poll_create', 'poll_vote', 'poll_close', 'report_close', 'name', 'members' ) as $a ) {
			add_action( 'wp_ajax_hspp_g_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
	}

	/* ---------- وضعیت ---------- */

	public static function settings( $class_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'groups' ) . ' WHERE class_id = %d', $class_id ), ARRAY_A ); // phpcs:ignore
		return wp_parse_args( (array) $row, array(
			'class_id' => (int) $class_id, 'enabled' => 1, 'read_only' => 0, 'allow_images' => 1,
			'quiet_on' => 1, 'quiet_from' => 22, 'quiet_to' => 7, 'pinned_id' => 0, 'rules' => '',
		) );
	}

	private static function save_settings( $class_id, $data ) {
		global $wpdb;
		$s = array_merge( self::settings( $class_id ), $data, array( 'class_id' => (int) $class_id ) );
		$wpdb->replace( HSPP_DB::t( 'groups' ), $s );
	}

	public static function is_teacher( $class_id, $uid ) {
		if ( ! $class_id ) {
			return false;
		}
		return (int) get_post_meta( $class_id, '_hs_teacher_id', true ) === (int) $uid || ( class_exists( 'HS_Roles' ) && HS_Roles::can_manage_class( $class_id, $uid ) );
	}

	/** حافظه‌ی همین درخواست؛ با تغییر دستیاران پاک می‌شود */
	public static $asst_cache = array();

	public static function is_assistant( $class_id, $uid ) {
		global $wpdb;
		$k = $class_id . ':' . $uid;
		if ( ! isset( self::$asst_cache[ $k ] ) ) {
			self::$asst_cache[ $k ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . HSPP_DB::t( 'assistants' ) . ' WHERE class_id = %d AND user_id = %d', $class_id, $uid ) ); // phpcs:ignore
		}
		return self::$asst_cache[ $k ];
	}

	/** ناظر: مدیر سایت، معلم کلاس، دستیار */
	public static function is_mod( $class_id, $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		return user_can( $uid, 'manage_options' ) || self::is_teacher( $class_id, $uid ) || ( $class_id && self::is_assistant( $class_id, $uid ) );
	}

	public static function assistants( $class_id ) {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . HSPP_DB::t( 'assistants' ) . ' WHERE class_id = %d', $class_id ) ) ); // phpcs:ignore
	}

	public static function muted_until( $class_id, $uid ) {
		global $wpdb;
		$u = $wpdb->get_var( $wpdb->prepare( 'SELECT until FROM ' . HSPP_DB::t( 'mutes' ) . ' WHERE class_id = %d AND user_id = %d', $class_id, $uid ) ); // phpcs:ignore
		return ( $u && strtotime( $u ) > current_time( 'timestamp' ) ) ? $u : '';
	}

	public static function in_quiet( $s ) {
		if ( empty( $s['quiet_on'] ) ) {
			return false;
		}
		$h = (int) current_time( 'G' );
		$f = (int) $s['quiet_from'];
		$t = (int) $s['quiet_to'];
		return $f > $t ? ( $h >= $f || $h < $t ) : ( $h >= $f && $h < $t );
	}

	/** اعضای والدِ کلاس (برای انتخاب دستیار/بی‌صدا) */
	public static function parent_members( $class_id ) {
		$out = array();
		foreach ( (array) get_post_meta( $class_id, '_hs_students', true ) as $sid ) {
			foreach ( HS_Parent_Link::get_parents( (int) $sid ) as $pid ) {
				$out[ (int) $pid ] = HSPP_Names::name( (int) $pid, $class_id );
			}
		}
		return $out;
	}

	/**
	 * آیا این کاربر الان می‌تواند در این گروه بنویسد؟
	 * @return true|WP_Error
	 */
	public static function can_post( $class_id, $uid, $msg_type = 'text' ) {
		if ( self::is_mod( $class_id, $uid ) ) {
			return true;
		}
		$s = self::settings( $class_id );
		if ( $class_id && ! $s['enabled'] ) {
			return new WP_Error( 'off', 'گروه این کلاس غیرفعال است.' );
		}
		if ( $s['read_only'] ) {
			return new WP_Error( 'ro', 'گروه فعلاً «فقط‌خواندنی» است؛ فقط معلم و دستیاران پیام می‌فرستند.' );
		}
		$m = $class_id ? self::muted_until( $class_id, $uid ) : '';
		if ( $m ) {
			return new WP_Error( 'mute', 'امکان ارسال پیام برای شما تا ' . mysql2date( 'H:i Y/m/d', $m ) . ' بسته است.' );
		}
		if ( self::in_quiet( $s ) ) {
			return new WP_Error( 'quiet', sprintf( 'ساعت سکوت گروه (%d تا %d)؛ لطفاً بعداً پیام بفرستید.', $s['quiet_from'], $s['quiet_to'] ) );
		}
		if ( 'image' === $msg_type && ! $s['allow_images'] ) {
			return new WP_Error( 'img', 'ارسال عکس در این گروه بسته است.' );
		}
		return true;
	}

	/** وضعیت برای نمایش در چت */
	public static function state( $class_id, $uid ) {
		global $wpdb;
		$s     = self::settings( $class_id );
		$can   = self::can_post( $class_id, $uid );
		$poll  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'polls' ) . ' WHERE class_id = %d AND closed = 0 ORDER BY id DESC LIMIT 1', $class_id ), ARRAY_A ); // phpcs:ignore
		$pin   = $s['pinned_id'] ? HS_Parent_Chat_DB::get( $s['pinned_id'] ) : null;
		$state = array(
			'is_mod'    => self::is_mod( $class_id, $uid ),
			'is_owner'  => self::is_teacher( $class_id, $uid ) || user_can( $uid, 'manage_options' ),
			'can_post'  => true === $can,
			'why'       => is_wp_error( $can ) ? $can->get_error_message() : '',
			'images'    => (bool) $s['allow_images'],
			'read_only' => (bool) $s['read_only'],
			'pinned'    => ( $pin && empty( $pin['deleted'] ) ) ? HS_Parent_Chat_Handlers::format_msg( $pin ) : null,
			'poll'      => $poll ? self::poll_view( $poll, $uid ) : null,
			'my_name'   => $class_id ? HSPP_Names::name( $uid, $class_id ) : '',
			'reports'   => 0,
		);
		if ( $state['is_mod'] && $class_id ) {
			$state['reports'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HSPP_DB::t( 'reports' ) . " WHERE class_id = %d AND status = 'open'", $class_id ) ); // phpcs:ignore
		}
		return $state;
	}

	public static function poll_view( $poll, $uid ) {
		global $wpdb;
		$opts   = (array) json_decode( $poll['options'], true );
		$counts = array_fill( 0, count( $opts ), 0 );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT choice, COUNT(*) n FROM ' . HSPP_DB::t( 'votes' ) . ' WHERE poll_id = %d GROUP BY choice', $poll['id'] ), ARRAY_A ) as $r ) { // phpcs:ignore
			if ( isset( $counts[ (int) $r['choice'] ] ) ) {
				$counts[ (int) $r['choice'] ] = (int) $r['n'];
			}
		}
		$mine = $wpdb->get_var( $wpdb->prepare( 'SELECT choice FROM ' . HSPP_DB::t( 'votes' ) . ' WHERE poll_id = %d AND user_id = %d', $poll['id'], $uid ) ); // phpcs:ignore
		return array( 'id' => (int) $poll['id'], 'q' => $poll['question'], 'opts' => $opts, 'counts' => $counts, 'mine' => null === $mine ? -1 : (int) $mine, 'total' => array_sum( $counts ) );
	}

	/* ---------- AJAX ---------- */

	private static function req( $mod = true, $owner = false ) {
		if ( ! check_ajax_referer( 'hs_class_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده؛ صفحه را تازه کنید.' ) );
		}
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$uid      = get_current_user_id();
		if ( ! HS_Parent_Chat_Handlers::can_access_class( $class_id, $uid ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) );
		}
		if ( $owner && ! ( self::is_teacher( $class_id, $uid ) || user_can( $uid, 'manage_options' ) ) ) {
			wp_send_json_error( array( 'message' => 'فقط معلم کلاس.' ) );
		}
		if ( $mod && ! self::is_mod( $class_id, $uid ) ) {
			wp_send_json_error( array( 'message' => 'فقط ناظران گروه.' ) );
		}
		return array( $class_id, $uid );
	}

	private static function p( $k ) {
		return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
	}

	/** تنظیمات گروه (ناظر؛ خاموش‌کردن کامل فقط معلم) */
	public static function ajax_settings() {
		list( $cid, $uid ) = self::req();
		$d = array(
			'read_only'    => (int) ! empty( $_POST['read_only'] ) ? 1 : 0,
			'allow_images' => ! empty( $_POST['allow_images'] ) ? 1 : 0,
			'quiet_on'     => ! empty( $_POST['quiet_on'] ) ? 1 : 0,
			'quiet_from'   => min( 23, absint( self::p( 'quiet_from' ) ) ),
			'quiet_to'     => min( 23, absint( self::p( 'quiet_to' ) ) ),
			'rules'        => sanitize_textarea_field( wp_unslash( $_POST['rules'] ?? '' ) ),
		);
		if ( isset( $_POST['enabled'] ) && ( self::is_teacher( $cid, $uid ) || user_can( $uid, 'manage_options' ) ) ) {
			$d['enabled'] = (int) ! empty( $_POST['enabled'] );
		}
		self::save_settings( $cid, $d );
		HSPP_DB::log( 'group_settings', $cid, $d );
		wp_send_json_success( array( 'message' => 'تنظیمات گروه ذخیره شد.' ) );
	}

	/** افزودن/حذف دستیار (فقط معلم کلاس یا مدیر سایت) */
	public static function ajax_assistant() {
		global $wpdb;
		list( $cid ) = self::req( false, true );
		$user = absint( self::p( 'user_id' ) );
		$add  = ! empty( $_POST['add'] );
		if ( $add ) {
			if ( ! isset( self::parent_members( $cid )[ $user ] ) ) {
				wp_send_json_error( array( 'message' => 'دستیار باید از والدین همین کلاس باشد.' ) );
			}
			if ( count( self::assistants( $cid ) ) >= self::MAX_ASSISTANTS ) {
				wp_send_json_error( array( 'message' => 'حداکثر ' . self::MAX_ASSISTANTS . ' دستیار.' ) );
			}
			$wpdb->replace( HSPP_DB::t( 'assistants' ), array( 'class_id' => $cid, 'user_id' => $user, 'added_by' => get_current_user_id(), 'created_at' => HSPP_DB::now() ) );
		} else {
			$wpdb->delete( HSPP_DB::t( 'assistants' ), array( 'class_id' => $cid, 'user_id' => $user ) );
		}
		self::$asst_cache = array();
		HSPP_DB::log( 'assistant', $cid, array( 'user' => $user, 'add' => $add ) );
		wp_send_json_success( array( 'message' => $add ? 'دستیار تعیین شد.' : 'دستیار برداشته شد.', 'assistants' => self::assistants( $cid ) ) );
	}

	/** بی‌صدا کردن (ساعت؛ ۰ = برداشتن) */
	public static function ajax_mute() {
		global $wpdb;
		list( $cid, $uid ) = self::req();
		$user  = absint( self::p( 'user_id' ) );
		$hours = min( 24 * 30, absint( self::p( 'hours' ) ) );
		if ( self::is_mod( $cid, $user ) ) {
			wp_send_json_error( array( 'message' => 'ناظران را نمی‌توان بی‌صدا کرد.' ) );
		}
		if ( $hours ) {
			$wpdb->replace( HSPP_DB::t( 'mutes' ), array( 'class_id' => $cid, 'user_id' => $user, 'until' => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $hours * HOUR_IN_SECONDS ), 'by_id' => $uid ) );
		} else {
			$wpdb->delete( HSPP_DB::t( 'mutes' ), array( 'class_id' => $cid, 'user_id' => $user ) );
		}
		HSPP_DB::log( 'mute', $cid, array( 'user' => $user, 'hours' => $hours ) );
		wp_send_json_success( array( 'message' => $hours ? 'عضو بی‌صدا شد.' : 'بی‌صدا برداشته شد.' ) );
	}

	/** حذف پیام: ناظر، یا فرستنده‌ی پیام (تا ۱۵ دقیقه) */
	public static function ajax_delete() {
		global $wpdb;
		list( $cid, $uid ) = self::req( false );
		$m = HS_Parent_Chat_DB::get( absint( self::p( 'msg_id' ) ) );
		if ( ! $m || (int) $m['class_id'] !== $cid ) {
			wp_send_json_error( array( 'message' => 'پیام یافت نشد.' ) );
		}
		$own = (int) $m['sender_id'] === $uid && strtotime( $m['created_at'] ) > current_time( 'timestamp' ) - 15 * MINUTE_IN_SECONDS;
		if ( ! $own && ! self::is_mod( $cid, $uid ) ) {
			wp_send_json_error( array( 'message' => 'فقط ناظران گروه (یا فرستنده تا ۱۵ دقیقه) می‌توانند پیام را حذف کنند.' ) );
		}
		$wpdb->update( HS_Parent_Chat_DB::table(), array( 'deleted' => 1, 'deleted_by' => $uid ), array( 'id' => $m['id'] ) );
		$wpdb->update( HSPP_DB::t( 'reports' ), array( 'status' => 'done' ), array( 'msg_id' => $m['id'] ) );
		if ( ! $own ) {
			HSPP_DB::log( 'msg_delete', $cid, array( 'msg' => (int) $m['id'], 'sender' => (int) $m['sender_id'] ) );
		}
		wp_send_json_success( array( 'message' => 'پیام حذف شد.' ) );
	}

	public static function ajax_report() {
		global $wpdb;
		list( $cid, $uid ) = self::req( false );
		$mid = absint( self::p( 'msg_id' ) );
		$m   = HS_Parent_Chat_DB::get( $mid );
		if ( ! $m || (int) $m['class_id'] !== $cid ) {
			wp_send_json_error( array( 'message' => 'پیام یافت نشد.' ) );
		}
		$wpdb->replace( HSPP_DB::t( 'reports' ), array( 'msg_id' => $mid, 'class_id' => $cid, 'reporter_id' => $uid, 'reason' => mb_substr( self::p( 'reason' ), 0, 250 ), 'status' => 'open', 'created_at' => HSPP_DB::now() ) );
		wp_send_json_success( array( 'message' => 'گزارش شما برای ناظران گروه فرستاده شد. ممنون.' ) );
	}

	public static function ajax_report_close() {
		global $wpdb;
		list( $cid ) = self::req();
		$wpdb->update( HSPP_DB::t( 'reports' ), array( 'status' => 'done' ), array( 'msg_id' => absint( self::p( 'msg_id' ) ), 'class_id' => $cid ) );
		wp_send_json_success( array( 'message' => 'بررسی شد.' ) );
	}

	public static function ajax_pin() {
		list( $cid ) = self::req();
		$mid = absint( self::p( 'msg_id' ) );
		if ( $mid ) {
			$m = HS_Parent_Chat_DB::get( $mid );
			if ( ! $m || (int) $m['class_id'] !== $cid ) {
				wp_send_json_error( array( 'message' => 'پیام یافت نشد.' ) );
			}
		}
		self::save_settings( $cid, array( 'pinned_id' => $mid ) );
		wp_send_json_success( array( 'message' => $mid ? 'پیام سنجاق شد.' : 'سنجاق برداشته شد.' ) );
	}

	public static function ajax_poll_create() {
		global $wpdb;
		list( $cid, $uid ) = self::req();
		$q    = mb_substr( self::p( 'question' ), 0, 200 );
		$opts = array_values( array_filter( array_map( function ( $o ) {
			return mb_substr( sanitize_text_field( $o ), 0, 80 );
		}, preg_split( '/\r?\n/', (string) wp_unslash( $_POST['options'] ?? '' ) ) ) ) );
		if ( '' === $q || count( $opts ) < 2 || count( $opts ) > 6 ) {
			wp_send_json_error( array( 'message' => 'سؤال و ۲ تا ۶ گزینه (هر خط یک گزینه) لازم است.' ) );
		}
		$wpdb->update( HSPP_DB::t( 'polls' ), array( 'closed' => 1 ), array( 'class_id' => $cid, 'closed' => 0 ) );
		$wpdb->insert( HSPP_DB::t( 'polls' ), array( 'class_id' => $cid, 'question' => $q, 'options' => wp_json_encode( $opts, JSON_UNESCAPED_UNICODE ), 'created_by' => $uid, 'created_at' => HSPP_DB::now() ) );
		wp_send_json_success( array( 'message' => 'نظرسنجی ساخته شد.' ) );
	}

	public static function ajax_poll_vote() {
		global $wpdb;
		list( $cid, $uid ) = self::req( false );
		$poll = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HSPP_DB::t( 'polls' ) . ' WHERE id = %d AND class_id = %d AND closed = 0', absint( self::p( 'poll_id' ) ), $cid ), ARRAY_A ); // phpcs:ignore
		$ch   = absint( self::p( 'choice' ) );
		if ( ! $poll || $ch >= count( (array) json_decode( $poll['options'], true ) ) ) {
			wp_send_json_error( array( 'message' => 'نظرسنجی بسته است.' ) );
		}
		$wpdb->replace( HSPP_DB::t( 'votes' ), array( 'poll_id' => $poll['id'], 'user_id' => $uid, 'choice' => $ch ) );
		wp_send_json_success( array( 'poll' => self::poll_view( $poll, $uid ) ) );
	}

	public static function ajax_poll_close() {
		global $wpdb;
		list( $cid ) = self::req();
		$wpdb->update( HSPP_DB::t( 'polls' ), array( 'closed' => 1 ), array( 'class_id' => $cid, 'closed' => 0 ) );
		wp_send_json_success( array( 'message' => 'نظرسنجی بسته شد.' ) );
	}

	/** نام نمایشی خودم در این گروه */
	public static function ajax_name() {
		list( $cid, $uid ) = self::req( false );
		$r = HSPP_Names::set( $uid, $cid, self::p( 'name' ) );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => 'نام نمایشی شما ذخیره شد.', 'name' => HSPP_Names::name( $uid, $cid ) ) );
	}

	/** پنل ناظر: اعضا، دستیاران، بی‌صداها، گزارش‌ها، تنظیمات */
	public static function ajax_members() {
		global $wpdb;
		list( $cid ) = self::req();
		$asst  = self::assistants( $cid );
		$mem   = array();
		foreach ( self::parent_members( $cid ) as $id => $name ) {
			$mem[] = array( 'id' => $id, 'name' => $name, 'assistant' => in_array( $id, $asst, true ), 'muted' => self::muted_until( $cid, $id ) );
		}
		$reports = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT msg_id, COUNT(*) n, GROUP_CONCAT(reason SEPARATOR " | ") reasons FROM ' . HSPP_DB::t( 'reports' ) . " WHERE class_id = %d AND status = 'open' GROUP BY msg_id", $cid ), ARRAY_A ) as $r ) { // phpcs:ignore
			$m = HS_Parent_Chat_DB::get( $r['msg_id'] );
			if ( $m && empty( $m['deleted'] ) ) {
				$reports[] = array( 'msg' => HS_Parent_Chat_Handlers::format_msg( $m ), 'n' => (int) $r['n'], 'reasons' => $r['reasons'] );
			}
		}
		wp_send_json_success( array( 'members' => $mem, 'reports' => $reports, 'settings' => self::settings( $cid ), 'max_assistants' => self::MAX_ASSISTANTS ) );
	}
}

HSPP_Group::init();
