<?php
/**
 * صفحه‌ی «خانواده و دعوت» در پنل (/panel/family/) — شورت‌کد [hs_family]
 * - والد: تأیید فرزندان پیشنهادی، فرزندان متصل، دعوت والد دوم، ورود کد کلاس، درخواست از معلم/مدرسه
 * - معلم: کد و لینک دعوت هر کلاس، وضعیت والدین، درخواست‌ها، ورود گروهی، دعوت‌های مدرسه
 * - مدیر مدرسه: معرفی معلم با موبایل، کلاس‌ها و درخواست‌های مدرسه
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Front {

	const SLUG = 'family';

	public static function init() {
		add_shortcode( 'hs_family', array( __CLASS__, 'render' ) );
		add_filter( 'hs_shell_menu', array( __CLASS__, 'menu' ), 20, 2 );
		add_filter( 'hs_parent_linked', array( __CLASS__, 'is_linked' ), 10, 2 );
		add_filter( 'hs_parent_link_form_html', array( __CLASS__, 'parent_link_box' ) );
		add_action( 'admin_post_hsum_do', array( __CLASS__, 'handle' ) );
	}

	public static function page_url() {
		$id = (int) get_option( 'hsum_page_id' );
		return ( $id && 'publish' === get_post_status( $id ) ) ? get_permalink( $id ) : home_url( '/panel/' . self::SLUG . '/' );
	}

	public static function create_page() {
		$id = (int) get_option( 'hsum_page_id' );
		if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}
		$panel = get_page_by_path( 'panel' );
		$id    = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'خانواده و دعوت',
			'post_name'    => self::SLUG,
			'post_parent'  => $panel ? $panel->ID : 0,
			'post_content' => '[hs_family]',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'hsum_page_id', (int) $id );
		}
		return (int) $id;
	}

	/** والد دست‌کم به یک فرزند متصل است؟ */
	public static function is_linked( $linked, $uid ) {
		if ( $linked ) {
			return true;
		}
		foreach ( (array) HSUM_Links::for_parent( $uid ) as $l ) {
			if ( 'active' === $l['status'] ) {
				return true;
			}
		}
		return class_exists( 'HS_Parent_Link' ) && method_exists( 'HS_Parent_Link', 'get_children' ) && (bool) HS_Parent_Link::get_children( $uid );
	}

	/** منوی پنل */
	public static function menu( $menu, $type ) {
		// والدِ متصل دیگر «اتصال فرزند» را در منو نمی‌بیند (نشان 🔗 روی کارت نقشش است؛ افزودن فرزند دیگر از تکمیل ثبت‌نام/صفحه‌ی اتصال)
		if ( in_array( $type, array( 'parent', 'other' ), true ) && self::is_linked( false, get_current_user_id() ) ) {
			return $menu;
		}
		$item = array(
			'teacher' => array( self::SLUG, 'خانواده و دعوت', '👪', 'teal' ),
			'manager' => array( self::SLUG, 'معلمان و دعوت', '✉️', 'teal' ),
			'parent'  => array( self::SLUG, 'اتصال فرزند', '🔗', 'teal' ),
			'other'   => array( self::SLUG, 'اتصال فرزند', '🔗', 'teal' ),
		)[ $type ] ?? null;
		if ( ! $item || ! is_array( $menu ) ) {
			return $menu;
		}
		foreach ( $menu as $i => $group ) {
			if ( in_array( $group[0], array( 'ارتباط', 'مدیریت مدرسه', 'فرزندان من' ), true ) ) {
				$menu[ $i ][1][] = $item;
				return $menu;
			}
		}
		$menu[] = array( '', array( $item ) );
		return $menu;
	}

	/** کادر «اتصال فرزند» در پنل والدین مدیریت کلاس (به‌جای فقط کد ۸ کاراکتری) */
	public static function parent_link_box( $html ) {
		return '<div class="hs-pp-link-box"><p>برای اتصال به فرزندتان، از معلم کد یا لینک دعوت کلاس را بگیرید، یا اگر معلم شماره‌ی شما را ثبت کرده، فقط اتصال را تأیید کنید.</p><p><a class="hs-pp-btn hs-pp-btn-primary" href="' . esc_url( self::page_url() ) . '">اتصال فرزند</a></p>'
			. '<details><summary>کد ۸ کاراکتری والد دارید؟</summary>' . $html . '</details></div>';
	}

	/* ---------- عملیات (فرم‌ها) ---------- */

	public static function handle() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'hsum_do' );
		$uid = get_current_user_id();
		$do  = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		$p   = function ( $k ) {
			return sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
		};
		$r   = new WP_Error( 'do', 'درخواست نامعتبر.' );
		$ok  = 'انجام شد.';
		switch ( $do ) {
			case 'link_confirm':
				$r  = HSUM_Links::confirm( absint( $p( 'id' ) ), $uid );
				$ok = 'به حساب فرزندتان متصل شدید.';
				break;
			case 'link_reject':
				$r = HSUM_Links::reject( absint( $p( 'id' ) ), $uid );
				break;
			case 'invite_second':
				$r  = HSUM_Links::invite_second( absint( $p( 'student' ) ), $p( 'mobile' ), sanitize_key( $p( 'relation' ) ), $uid );
				$ok = 'دعوت ثبت شد؛ وقتی والد دوم با همین شماره وارد شود، اتصال را تأیید می‌کند.';
				break;
			case 'join':
				$r = HSUM_Invites::parent_join( $p( 'code' ), $uid, $p( 'child' ) );
				if ( ! is_wp_error( $r ) ) {
					$ok = $r['message'];
				}
				break;
			case 'request':
				$r  = HSUM_Requests::create( $uid, array( 'teacher_mobile' => $p( 'teacher_mobile' ), 'school_id' => absint( $p( 'school' ) ), 'child_name' => $p( 'child' ), 'message' => $p( 'message' ) ) );
				$ok = 'درخواست شما فرستاده شد.';
				break;
			case 'req_approve':
				$r  = HSUM_Requests::approve( absint( $p( 'id' ) ), absint( $p( 'student' ) ) );
				$ok = 'والد متصل شد.';
				break;
			case 'req_reject':
				$r = HSUM_Requests::reject( absint( $p( 'id' ) ), $p( 'reason' ) );
				break;
			case 'new_code':
				$cid = absint( $p( 'class' ) );
				$r   = HSUM_Invites::can_manage_class( $cid ) ? HSUM_Invites::new_class_code( $cid ) : new WP_Error( 'cap', 'دسترسی ندارید.' );
				$ok  = 'کد جدید ساخته شد؛ کد قبلی دیگر کار نمی‌کند.';
				break;
			case 'propose':
				$cid = absint( $p( 'class' ) );
				$sid = absint( $p( 'student' ) );
				$in  = in_array( $sid, array_map( 'intval', (array) get_post_meta( $cid, '_hs_students', true ) ), true );
				$r   = HSUM_Invites::can_manage_class( $cid ) && $in ? HSUM_Links::propose( $sid, $p( 'mobile' ), sanitize_key( $p( 'relation' ) ), $cid, 'teacher' ) : new WP_Error( 'cap', 'دسترسی ندارید.' );
				$ok  = 'شماره والد ثبت شد.';
				break;
			case 'link_remove':
				$r = HSUM_Links::remove( absint( $p( 'id' ) ) );
				break;
			case 'invite_teacher':
				$r  = HSUM_Invites::invite_teacher( absint( $p( 'school' ) ), $p( 'mobile' ), $p( 'name' ) );
				$ok = 'معلم دعوت شد.';
				break;
			case 'invite_revoke':
				$r = HSUM_Invites::revoke( absint( $p( 'id' ) ) );
				break;
			case 'claim_class':
				$r  = HSUM_Invites::claim_class( absint( $p( 'id' ) ), $uid );
				$ok = 'کلاس به مدرسه وصل شد.';
				break;
			case 'school_accept':
			case 'school_decline':
				$r  = HSUM_Invites::answer_teacher_invite( absint( $p( 'id' ) ), $uid, 'school_accept' === $do );
				$ok = 'school_accept' === $do ? 'به مدرسه پیوستید.' : 'دعوت رد شد.';
				break;
		}
		$url = wp_get_referer() ? wp_get_referer() : self::page_url();
		$url = remove_query_arg( array( 'hsum_msg', 'hsum_err', 'join' ), $url );
		wp_safe_redirect( add_query_arg( is_wp_error( $r ) ? 'hsum_err' : 'hsum_msg', rawurlencode( is_wp_error( $r ) ? $r->get_error_message() : $ok ), $url ) );
		exit;
	}

	private static function form( $do, $fields, $button, $class = 'hsum-btn', $confirm = '' ) {
		$h  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hsum-inline"' . ( $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>';
		$h .= wp_nonce_field( 'hsum_do', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="hsum_do"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		foreach ( $fields as $k => $v ) {
			$h .= is_int( $k ) ? $v : '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		return $h . '<button class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button></form>';
	}

	private static function rel_select( $name = 'relation' ) {
		$h = '<select name="' . esc_attr( $name ) . '">';
		foreach ( HSUM_Links::relations() as $k => $l ) {
			$h .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>';
	}

	/* ---------- نمایش ---------- */

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<p>برای این بخش <a href="' . esc_url( wp_login_url( self::page_url() ) ) . '">وارد شوید</a>.</p>';
		}
		wp_enqueue_style( 'hsum', HSUM_URL . 'assets/front.css', ( wp_style_is( 'hs-core', 'registered' ) ? array( 'hs-core' ) : array() ), HSUM_VERSION );
		$u   = wp_get_current_user();
		// تکمیل ثبت‌نام: قبل از هر چیز دیگری
		if ( HSUM_Onboard::needs() || ( isset( $_GET['onboard'] ) && ! current_user_can( 'manage_options' ) ) ) { // phpcs:ignore
			$err = isset( $_GET['hsum_err'] ) ? '<div class="hsum-note err">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hsum_err'] ) ) ) . '</div>' : ''; // phpcs:ignore
			return '<div class="hsum" dir="rtl">' . $err . HSUM_Onboard::form() . '</div>';
		}
		$out = '<div class="hsum" dir="rtl">';
		if ( isset( $_GET['hsum_msg'] ) ) {
			$out .= '<div class="hsum-note ok">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hsum_msg'] ) ) ) . '</div>';
		}
		if ( isset( $_GET['hsum_err'] ) ) {
			$out .= '<div class="hsum-note err">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hsum_err'] ) ) ) . '</div>';
		}
		if ( isset( $_GET['hsum_done'] ) ) {
			$out .= '<div class="hsum-note ok">ورود گروهی انجام شد — ' . esc_html( sanitize_text_field( wp_unslash( $_GET['hsum_done'] ) ) ) . '</div>';
		}

		$m = HSUM_Membership::get( $u->ID );
		if ( in_array( $m['status'], array( 'pending', 'rejected' ), true ) ) {
			$out .= '<div class="hsum-note warn">درخواست عضویت شما به‌عنوان «' . esc_html( HSUM_Membership::roles()[ $m['role'] ] ?? '' ) . '» ' . ( 'pending' === $m['status'] ? 'در انتظار تأیید مدیر سامانه است.' : 'تأیید نشد.' . ( $m['reason'] ? ' دلیل: ' . esc_html( $m['reason'] ) : '' ) ) . '</div>';
		}

		$out .= self::school_invites_box( $u->ID );

		$roles   = (array) $u->roles;
		$teacher = array_intersect( $roles, array( 'teacher', 'administrator' ) ) || HSUM_Invites::managed_school( $u->ID );
		if ( $teacher ) {
			$out .= self::manager_box( $u->ID ) . self::teacher_box( $u->ID );
		}
		if ( ! $teacher || in_array( 'parent', $roles, true ) ) {
			$out .= self::parent_box( $u->ID );
		}
		return $out . '</div>';
	}

	/** دعوت مدرسه برای معلم */
	private static function school_invites_box( $uid ) {
		$inv = HSUM_Invites::teacher_invites_for( $uid );
		if ( ! $inv ) {
			return '';
		}
		$h = '<section class="hsum-card"><h2>✉️ دعوت به مدرسه</h2>';
		foreach ( $inv as $i ) {
			$h .= '<div class="hsum-row"><span>شما به‌عنوان معلم «<b>' . esc_html( get_the_title( $i['school_id'] ) ) . '</b>» دعوت شده‌اید.</span>'
				. self::form( 'school_accept', array( 'id' => $i['id'] ), 'می‌پذیرم' ) . self::form( 'school_decline', array( 'id' => $i['id'] ), 'نه', 'hsum-btn ghost' ) . '</div>';
		}
		return $h . '</section>';
	}

	private static function parent_box( $uid ) {
		$links = HSUM_Links::for_parent( $uid );
		$join  = isset( $_GET['join'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['join'] ) ) ) : '';
		$h     = '';

		// کد دعوت از لینک /join/CODE
		if ( $join ) {
			$inv = HSUM_Invites::by_code( $join );
			$h  .= '<section class="hsum-card hsum-hi"><h2>🔗 پیوستن به کلاس</h2>';
			if ( $inv ) {
				$h .= '<p>دعوت به کلاس <b>' . esc_html( get_the_title( $inv['class_id'] ) ) . '</b>. نام و نام خانوادگی فرزندتان را بنویسید:</p>'
					. self::form( 'join', array( 'code' => $join, '<input name="child" required placeholder="مثلاً علی رضایی">' ), 'اتصال' );
			} else {
				$h .= '<p class="hsum-err">این کد دعوت معتبر نیست یا منقضی شده است. از معلم کد جدید بگیرید.</p>';
			}
			$h .= '</section>';
		}

		$wait = array_filter( $links, function ( $l ) {
			return 'awaiting' === $l['status'];
		} );
		if ( $wait ) {
			$h .= '<section class="hsum-card hsum-hi"><h2>🔔 تأیید اتصال به فرزند</h2><p>معلم یا مدرسه شماره‌ی شما را به‌عنوان والدِ این دانش‌آموزان ثبت کرده است. اگر درست است، تأیید کنید:</p>';
			foreach ( $wait as $l ) {
				$s  = get_userdata( $l['student_id'] );
				$h .= '<div class="hsum-row"><span><b>' . esc_html( $s ? $s->display_name : '—' ) . '</b>' . ( $l['class_id'] ? ' — کلاس ' . esc_html( get_the_title( $l['class_id'] ) ) : '' ) . ' (' . esc_html( HSUM_Links::relations()[ $l['relation'] ] ?? '' ) . ')</span>'
					. self::form( 'link_confirm', array( 'id' => $l['id'] ), 'فرزند من است' ) . self::form( 'link_reject', array( 'id' => $l['id'] ), 'نه', 'hsum-btn ghost', 'این دانش‌آموز فرزند شما نیست؟' ) . '</div>';
			}
			$h .= '</section>';
		}

		$active = array_filter( $links, function ( $l ) {
			return 'active' === $l['status'];
		} );
		if ( $active ) {
			$h .= '<section class="hsum-card"><h2>👨‍👩‍👧 فرزندان من</h2>';
			foreach ( $active as $l ) {
				$s      = get_userdata( $l['student_id'] );
				$others = count( HSUM_Links::for_student( $l['student_id'] ) );
				$h     .= '<div class="hsum-row"><span><b>' . esc_html( $s ? $s->display_name : '—' ) . '</b>' . ( $l['class_id'] ? ' — ' . esc_html( get_the_title( $l['class_id'] ) ) : '' ) . '</span>';
				if ( $others < HSUM_Links::MAX_PARENTS ) {
					$h .= '<details><summary>دعوت والد دوم</summary>' . self::form( 'invite_second', array( 'student' => $l['student_id'], '<input name="mobile" dir="ltr" required placeholder="0912…">', self::rel_select() ), 'دعوت' ) . '</details>';
				}
				$h .= '</div>';
			}
			$h .= '<p><a class="hsum-btn" href="' . esc_url( home_url( '/panel/parent/' ) ) . '">رفتن به پنل والدین</a></p></section>';
		}

		// اتصال جدید
		$h .= '<section class="hsum-card"><h2>' . ( $active ? '➕ اتصال فرزند دیگر' : '🔗 هنوز کلاسی به حساب شما متصل نشده است' ) . '</h2>';
		$h .= '<div class="hsum-grid"><div><h3>کد دعوت کلاس دارید؟</h3>' . self::form( 'join', array( '<input name="code" required dir="ltr" placeholder="K-XXXXXX">', '<input name="child" required placeholder="نام و نام خانوادگی فرزند">' ), 'اتصال' ) . '</div>';
		$h .= '<div><h3>کد ندارید؟ درخواست از معلم</h3>' . self::form( 'request', array( '<input name="child" required placeholder="نام و نام خانوادگی فرزند">', '<input name="teacher_mobile" dir="ltr" placeholder="موبایل معلم (اختیاری)">', self::school_select(), '<input name="message" placeholder="کلاس/پایه، توضیح">' ), 'ارسال درخواست' ) . '<p class="hsum-muted">اگر معلم هنوز عضو سایت نیست، با پیامک به سایت دعوت می‌شود.</p></div></div>';
		$reqs = HSUM_Requests::for_parent( $uid );
		if ( $reqs ) {
			$h .= '<h3>درخواست‌های من</h3><table class="hsum-table"><tr><th>فرزند</th><th>به</th><th>وضعیت</th></tr>';
			foreach ( $reqs as $r ) {
				$to = $r['class_id'] ? get_the_title( $r['class_id'] ) : ( $r['teacher_id'] ? get_userdata( $r['teacher_id'] )->display_name : ( $r['teacher_mobile'] ? $r['teacher_mobile'] : ( $r['school_id'] ? get_the_title( $r['school_id'] ) : '—' ) ) );
				$h .= '<tr><td>' . esc_html( $r['child_name'] ) . '</td><td>' . esc_html( $to ) . '</td><td>' . esc_html( HSUM_Requests::status_labels()[ $r['status'] ] ?? $r['status'] ) . '</td></tr>';
			}
			$h .= '</table>';
		}
		return $h . '</section>';
	}

	private static function school_select() {
		if ( ! class_exists( 'HS_SO_Store' ) ) {
			return '';
		}
		$schools = get_posts( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		if ( ! $schools ) {
			return '';
		}
		$h = '<select name="school"><option value="">مدرسه (اختیاری)</option>';
		foreach ( $schools as $s ) {
			$h .= '<option value="' . (int) $s->ID . '">' . esc_html( $s->post_title ) . '</option>';
		}
		return $h . '</select>';
	}

	private static function manager_box( $uid ) {
		$school = HSUM_Invites::managed_school( $uid );
		if ( ! $school ) {
			return '';
		}
		$h  = '<section class="hsum-card"><h2>🏫 معرفی معلم به «' . esc_html( get_the_title( $school ) ) . '»</h2><p class="hsum-muted">شماره موبایل معلم ملاک است؛ وقتی با همین شماره وارد/عضو شود، دعوت را می‌پذیرد و (پس از تأیید مدیر سامانه) به مدرسه متصل می‌شود.</p>';
		$h .= self::form( 'invite_teacher', array( 'school' => $school, '<input name="name" placeholder="نام معلم">', '<input name="mobile" dir="ltr" required placeholder="0912…">' ), 'دعوت معلم' );
		$claims = HSUM_Invites::claimable_classes( $uid );
		if ( $claims ) {
			$h .= '<h3>🔗 کلاس‌هایی که معلم، شما را مدیر مدرسه‌شان معرفی کرده</h3><table class="hsum-table"><tr><th>کلاس</th><th>معلم</th><th>مدرسه (نوشته‌ی معلم)</th><th></th></tr>';
			foreach ( $claims as $cid ) {
				$tu  = get_userdata( HSUM_Invites::class_teacher( $cid ) );
				$h  .= '<tr><td>' . esc_html( get_the_title( $cid ) ) . '</td><td>' . esc_html( $tu ? $tu->display_name : '—' ) . '</td><td>' . esc_html( get_post_meta( $cid, '_hs_school', true ) ) . '</td><td>'
					. self::form( 'claim_class', array( 'id' => $cid ), 'اتصال به مدرسه‌ی من' ) . '</td></tr>';
			}
			$h .= '</table>';
		}
		$inv = HSUM_Invites::school_invites( $school );
		if ( $inv ) {
			$st = array( 'active' => 'در انتظار پاسخ', 'accepted' => 'پذیرفت', 'declined' => 'رد کرد', 'revoked' => 'لغو شد' );
			$h .= '<table class="hsum-table"><tr><th>معلم</th><th>موبایل</th><th>عضو سایت</th><th>وضعیت</th><th></th></tr>';
			foreach ( $inv as $i ) {
				$h .= '<tr><td>' . esc_html( $i['name'] ) . '</td><td dir="ltr">' . esc_html( $i['mobile'] ) . '</td><td>' . ( $i['user_id'] ? '✅' : '—' ) . '</td><td>' . esc_html( $st[ $i['status'] ] ?? $i['status'] ) . '</td><td>'
					. ( 'active' === $i['status'] ? self::form( 'invite_revoke', array( 'id' => $i['id'] ), 'لغو', 'hsum-link' ) : '' ) . '</td></tr>';
			}
			$h .= '</table>';
		}
		return $h . '</section>';
	}

	private static function teacher_box( $uid ) {
		$classes = HSUM_Invites::manageable_classes( $uid );
		$h       = '';

		// درخواست‌ها
		$inbox = HSUM_Requests::inbox( $uid );
		if ( $inbox ) {
			$h .= '<section class="hsum-card hsum-hi"><h2>📥 درخواست‌های والدین (' . count( $inbox ) . ')</h2>';
			foreach ( $inbox as $r ) {
				$p    = get_userdata( $r['parent_id'] );
				$opts = '<select name="student" required><option value="">انتخاب دانش‌آموز…</option>';
				foreach ( $classes as $c ) {
					if ( $r['class_id'] && (int) $r['class_id'] !== $c->ID ) {
						continue;
					}
					$opts .= '<optgroup label="' . esc_attr( $c->post_title ) . '">';
					foreach ( (array) get_post_meta( $c->ID, '_hs_students', true ) as $sid ) {
						$s = get_userdata( (int) $sid );
						if ( $s ) {
							$opts .= '<option value="' . (int) $s->ID . '">' . esc_html( $s->display_name ) . '</option>';
						}
					}
					$opts .= '</optgroup>';
				}
				$opts .= '</select>';
				$h    .= '<div class="hsum-req"><p><b>' . esc_html( $p ? $p->display_name : '—' ) . '</b> <span dir="ltr">' . esc_html( HSUM_Phone::user_mobile( $r['parent_id'] ) ) . '</span> — فرزند: <b>' . esc_html( $r['child_name'] ) . '</b>'
					. ( $r['class_id'] ? ' — کلاس ' . esc_html( get_the_title( $r['class_id'] ) ) : '' ) . ( $r['message'] ? '<br><small>' . esc_html( $r['message'] ) . '</small>' : '' ) . '</p>'
					. self::form( 'req_approve', array( 'id' => $r['id'], $opts ), 'اتصال' ) . self::form( 'req_reject', array( 'id' => $r['id'], '<input name="reason" placeholder="دلیل (اختیاری)">' ), 'رد', 'hsum-btn ghost' ) . '</div>';
			}
			$h .= '</section>';
		}

		if ( ! $classes ) {
			return $h . '<section class="hsum-card"><p>هنوز کلاسی ندارید. ابتدا از «کلاس‌ها» کلاس بسازید.</p></section>';
		}

		$h .= '<section class="hsum-card"><h2>👪 دعوت والدین و وضعیت اتصال</h2><p class="hsum-muted">برای هر کلاس یک کد مشترک دارید. کد فقط کلاس را مشخص می‌کند؛ والد فقط وقتی به فرزند وصل می‌شود که شماره‌اش با شماره‌ی ثبت‌شده‌ی همان دانش‌آموز یکی باشد، وگرنه درخواستش برای شما می‌آید.</p>';
		$sel = isset( $_GET['class'] ) ? absint( $_GET['class'] ) : $classes[0]->ID;
		$h  .= '<form method="get" class="hsum-inline"><select name="class" onchange="this.form.submit()">';
		foreach ( $classes as $c ) {
			$h .= '<option value="' . (int) $c->ID . '" ' . selected( $sel, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
		}
		$h .= '</select></form>';
		if ( ! HSUM_Invites::can_manage_class( $sel ) ) {
			$sel = $classes[0]->ID;
		}
		$inv  = HSUM_Invites::class_invite( $sel );
		$link = HSUM_Invites::link_url( $inv['code'] );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$msg  = sprintf( "والدین گرامی کلاس %s\nبرای دیدن وضعیت تحصیلی فرزندتان در %s، روی این لینک بزنید و با شماره موبایل خودتان وارد شوید:\n%s\nکد کلاس: %s", get_the_title( $sel ), $site, $link, $inv['code'] );
		$h   .= '<div class="hsum-code"><div><small>کد کلاس</small><b dir="ltr">' . esc_html( $inv['code'] ) . '</b></div><div><small>لینک دعوت</small><code dir="ltr">' . esc_html( $link ) . '</code></div>'
			. '<button type="button" class="hsum-btn" onclick="navigator.clipboard.writeText(this.dataset.t);this.textContent=\'کپی شد ✓\'" data-t="' . esc_attr( $msg ) . '">کپی پیام دعوت</button>'
			. self::form( 'new_code', array( 'class' => $sel ), 'کد جدید', 'hsum-btn ghost', 'کد فعلی باطل شود؟' ) . '</div>';

		// وضعیت والدین هر دانش‌آموز
		$labels = HSUM_Links::status_labels();
		$h     .= '<table class="hsum-table"><tr><th>دانش‌آموز</th><th>والدین</th><th>ثبت شماره والد</th></tr>';
		foreach ( HSUM_Links::class_overview( $sel ) as $row ) {
			$cells = array();
			foreach ( $row['links'] as $l ) {
				$cells[] = '<span class="hsum-pill s-' . esc_attr( $l['status'] ) . '"><span dir="ltr">' . esc_html( $l['parent_mobile'] ) . '</span> · ' . esc_html( $labels[ $l['status'] ] ?? $l['status'] ) . '</span> ' . self::form( 'link_remove', array( 'id' => $l['id'] ), '×', 'hsum-link', 'این اتصال حذف شود؟' );
			}
			$add = count( $row['links'] ) < HSUM_Links::MAX_PARENTS && ! $row['links']
				? self::form( 'propose', array( 'class' => $sel, 'student' => $row['student']->ID, '<input name="mobile" dir="ltr" required placeholder="0912…" style="width:120px">', self::rel_select() ), 'ثبت', 'hsum-btn small' )
				: '<small class="hsum-muted">' . ( $row['links'] ? 'والد دوم را والد اول دعوت می‌کند' : '' ) . '</small>';
			$h .= '<tr><td>' . esc_html( $row['student']->display_name ) . '</td><td>' . ( $cells ? implode( '<br>', $cells ) : '<span class="hsum-muted">—</span>' ) . '</td><td>' . $add . '</td></tr>';
		}
		$h .= '</table>';

		// ورود گروهی
		$h .= self::import_box( $sel );
		return $h . '</section>';
	}

	private static function import_box( $class_id ) {
		if ( ! HSUM_Import::available() ) {
			return '';
		}
		$h  = '<details class="hsum-import" ' . ( isset( $_GET['hsum_imp'] ) ? 'open' : '' ) . '><summary>📥 ورود گروهی دانش‌آموزان و شماره والدین از اکسل</summary>';
		$tk = isset( $_GET['hsum_imp'] ) ? sanitize_text_field( wp_unslash( $_GET['hsum_imp'] ) ) : '';
		$d  = $tk ? HSUM_Import::pending( $tk ) : null;
		if ( $d ) {
			$cnt = array_count_values( wp_list_pluck( $d['rows'], 'state' ) );
			$h  .= '<p>پیش‌نمایش کلاس <b>' . esc_html( get_the_title( $d['class'] ) ) . '</b>: ' . (int) ( $cnt['ok'] ?? 0 ) . ' جدید، ' . (int) ( $cnt['exists'] ?? 0 ) . ' موجود، ' . (int) ( $cnt['error'] ?? 0 ) . ' خطا.</p>';
			$h  .= '<table class="hsum-table"><tr><th>سطر</th><th>دانش‌آموز</th><th>موبایل والد</th><th>نسبت</th><th>وضعیت</th></tr>';
			$st  = array( 'ok' => '✅ جدید', 'exists' => 'ℹ️ موجود', 'error' => '❌ خطا' );
			foreach ( $d['rows'] as $r ) {
				$h .= '<tr class="r-' . esc_attr( $r['state'] ) . '"><td>' . (int) $r['line'] . '</td><td>' . esc_html( $r['first'] . ' ' . $r['last'] ) . '</td><td dir="ltr">' . esc_html( $r['mobile'] ? $r['mobile'] : $r['mobile_raw'] ) . '</td><td>' . esc_html( HSUM_Links::relations()[ $r['relation'] ] ) . '</td><td>' . esc_html( $st[ $r['state'] ] . ( $r['note'] ? ' — ' . $r['note'] : '' ) ) . '</td></tr>';
			}
			$h .= '</table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'hsum_import_apply', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="hsum_import_apply"><input type="hidden" name="token" value="' . esc_attr( $tk ) . '"><button class="hsum-btn">ثبت نهایی (سطرهای خطادار رد می‌شوند)</button> <a href="' . esc_url( remove_query_arg( 'hsum_imp' ) ) . '">انصراف</a></form>';
		} else {
			$h .= '<p class="hsum-muted">ستون‌ها: نام، نام خانوادگی، موبایل والد، نسبت والد (پدر/مادر)، شماره در کلاس. دانش‌آموزِ موجود دوباره ساخته نمی‌شود. <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hsum_import_sample' ), 'x' ) ) . '">دریافت فایل نمونه</a></p>';
			$h .= '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'hsum_import', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="hsum_import_upload"><input type="hidden" name="class_id" value="' . (int) $class_id . '"><input type="file" name="file" accept=".xlsx,.csv" required> <button class="hsum-btn">پیش‌نمایش</button></form>';
		}
		return $h . '</details>';
	}
}
