<?php
/**
 * «اطلاعات من و فرزندم»
 * - والد هر اطلاعاتی که درباره‌ی خودش و فرزندش ثبت شده را می‌بیند (شفافیت) و آن‌چه مربوط به خانواده است را ویرایش می‌کند.
 * - یادداشت‌های داخلی معلم نمایش داده نمی‌شود.
 * - هر ویرایش ثبت می‌شود و با پیام داخلی به معلم کلاس اطلاع داده می‌شود.
 * - تغییر شماره موبایل والد از این‌جا ممکن نیست (شناسه‌ی ورود و اتصال است).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSPP_Profile {

	/** فیلدهای فرزند: [برچسب، قابل ویرایش توسط والد، نوع] */
	public static function child_fields() {
		return array(
			'first_name'   => array( 'نام', true, 'text' ),
			'last_name'    => array( 'نام خانوادگی', true, 'text' ),
			'birth_date'   => array( 'تاریخ تولد', true, 'text' ),
			'parent_name'  => array( 'نام والد (در پرونده)', true, 'text' ),
			'parent_phone' => array( 'شماره والد (در پرونده)', false, 'text' ),
			'class_number' => array( 'شماره در کلاس', false, 'text' ),
		);
	}

	/** اطلاعاتی که خود خانواده اضافه می‌کند */
	public static function family_fields() {
		return array(
			'_hspp_health'    => array( 'نکات سلامتی / حساسیت (برای معلم)', 'textarea' ),
			'_hspp_emergency' => array( 'شماره تماس اضطراری دیگر', 'text' ),
		);
	}

	public static function init() {
		add_action( 'admin_post_hspp_profile', array( __CLASS__, 'save' ) );
	}

	public static function en_digits( $s ) {
		return strtr( (string) $s, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
	}

	private static function back( $msg, $err = false ) {
		$u = wp_get_referer() ? wp_get_referer() : home_url( '/panel/parent/?pp=profile' );
		wp_safe_redirect( add_query_arg( $err ? 'hspp_err' : 'hspp_msg', rawurlencode( $msg ), remove_query_arg( array( 'hspp_msg', 'hspp_err' ), $u ) ) );
		exit;
	}

	public static function save() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'hspp_profile' );
		$uid  = get_current_user_id();
		$what = sanitize_key( $_POST['what'] ?? '' );
		$in   = wp_unslash( $_POST );

		if ( 'me' === $what ) {
			$first = sanitize_text_field( $in['first_name'] ?? '' );
			$last  = sanitize_text_field( $in['last_name'] ?? '' );
			if ( '' === $first || '' === $last ) {
				self::back( 'نام و نام خانوادگی را وارد کنید.', true );
			}
			$email = sanitize_email( $in['email'] ?? '' );
			$args  = array( 'ID' => $uid, 'first_name' => $first, 'last_name' => $last, 'display_name' => trim( $first . ' ' . $last ) );
			if ( $email && is_email( $email ) ) {
				$other = email_exists( $email );
				if ( $other && (int) $other !== $uid ) {
					self::back( 'این ایمیل برای حساب دیگری ثبت شده است.', true );
				}
				$args['user_email'] = $email;
			}
			wp_update_user( $args );
			foreach ( (array) ( $in['gname'] ?? array() ) as $cid => $name ) {
				$r = HSPP_Names::set( $uid, absint( $cid ), $name );
				if ( is_wp_error( $r ) ) {
					self::back( $r->get_error_message(), true );
				}
			}
			HSPP_DB::log( 'profile_me', $uid, array( 'first' => $first, 'last' => $last ) );
			self::back( 'اطلاعات شما ذخیره شد.' );
		}

		// رمز ورود فرزند (دسترسی دانش‌آموز با اجازه‌ی والدین)
		if ( 'kidpin' === $what ) {
			$sid = absint( $in['child'] ?? 0 );
			if ( ! HS_Parent_Link::can_view( $uid, $sid ) || ! class_exists( 'HSSP_Login' ) ) {
				self::back( 'دسترسی ندارید.', true );
			}
			if ( ! empty( $in['revoke'] ) ) {
				HSSP_Login::revoke( $sid );
				self::back( 'دسترسی فرزند بسته شد.' );
			}
			$pin  = strtr( (string) ( $in['pin'] ?? '' ), array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) );
			$pin2 = strtr( (string) ( $in['pin2'] ?? '' ), array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) );
			if ( $pin !== $pin2 ) {
				self::back( 'دو رمز یکی نیستند.', true );
			}
			if ( in_array( $pin, array( '0000', '1234', '1111', '4321' ), true ) ) {
				self::back( 'این رمز خیلی ساده است؛ رمز دیگری انتخاب کنید.', true );
			}
			$r = HSSP_Login::set_pin( $sid, $pin );
			if ( is_wp_error( $r ) ) {
				self::back( $r->get_error_message(), true );
			}
			self::back( 'رمز ورود فرزند ذخیره شد.' );
		}

		if ( 'child' === $what ) {
			$sid = absint( $in['child'] ?? 0 );
			if ( ! HS_Parent_Link::can_view( $uid, $sid ) || user_can( $uid, 'manage_options' ) && ! in_array( $sid, HS_Parent_Link::get_children( $uid ), true ) ) {
				self::back( 'دسترسی ندارید.', true );
			}
			$old     = class_exists( 'HS_Student_Profile' ) ? HS_Student_Profile::get( $sid ) : array();
			$data    = array();
			$changes = array();
			foreach ( self::child_fields() as $k => $f ) {
				if ( ! $f[1] || ! isset( $in[ $k ] ) ) {
					continue;
				}
				$v = sanitize_text_field( 'birth_date' === $k ? self::en_digits( $in[ $k ] ) : $in[ $k ] );
				if ( in_array( $k, array( 'first_name', 'last_name' ), true ) && '' === $v ) {
					self::back( 'نام و نام خانوادگی فرزند نمی‌تواند خالی باشد.', true );
				}
				if ( (string) ( $old[ $k ] ?? '' ) !== $v ) {
					$data[ $k ]    = $v;
					$changes[]     = $f[0] . ': «' . ( $old[ $k ] ?? '' ) . '» ← «' . $v . '»';
				}
			}
			// عکس انتخابی والدین
			if ( ! empty( $_FILES['child_photo']['tmp_name'] ) && class_exists( 'HS_Uploads' ) && is_uploaded_file( $_FILES['child_photo']['tmp_name'] ) ) { // phpcs:ignore
				$up = HS_Uploads::handle( $_FILES['child_photo'], 'student_photo' ); // phpcs:ignore
				if ( is_wp_error( $up ) ) {
					self::back( 'عکس ذخیره نشد: ' . $up->get_error_message(), true );
				}
				if ( ! empty( $up['url'] ) ) {
					$data['photo_url'] = $up['url'];
					$changes[]         = 'عکس فرزند تغییر کرد';
				}
			}
			if ( $data && class_exists( 'HS_Student_Profile' ) ) {
				HS_Student_Profile::save( $sid, $data );
			}
			foreach ( self::family_fields() as $k => $f ) {
				if ( isset( $in[ $k ] ) ) {
					$v = 'textarea' === $f[1] ? sanitize_textarea_field( $in[ $k ] ) : sanitize_text_field( self::en_digits( $in[ $k ] ) );
					if ( (string) get_user_meta( $sid, $k, true ) !== $v ) {
						update_user_meta( $sid, $k, $v );
						$changes[] = $f[0] . ': ' . ( '' === $v ? '(حذف شد)' : $v );
					}
				}
			}
			if ( ! empty( $in['confirm'] ) ) {
				update_user_meta( $sid, '_hspp_confirmed_' . $uid, current_time( 'mysql' ) );
			}
			if ( $changes ) {
				HSPP_DB::log( 'profile_child', $sid, array( 'changes' => $changes ) );
				self::notify_teachers( $uid, $sid, $changes );
			}
			self::back( $changes ? 'اطلاعات فرزند ذخیره شد و به معلم اطلاع داده شد.' : ( ! empty( $in['confirm'] ) ? 'تأیید شما ثبت شد. ممنون.' : 'تغییری نبود.' ) );
		}
		self::back( 'درخواست نامعتبر.', true );
	}

	/** پیام داخلی به معلم(های) فرزند */
	private static function notify_teachers( $parent, $sid, $changes ) {
		if ( ! class_exists( 'HS_Comms_DB' ) ) {
			return;
		}
		$s = get_userdata( $sid );
		foreach ( HSPP_Perms::student_classes( $sid ) as $cid ) {
			$t = (int) get_post_meta( $cid, '_hs_teacher_id', true );
			if ( $t ) {
				HS_Comms_DB::send_message( $parent, $t, "ℹ️ اطلاعات پرونده‌ی " . ( $s ? $s->display_name : '' ) . " توسط والد ویرایش شد:\n" . implode( "\n", $changes ), $cid, $sid );
			}
		}
	}

	/* ---------- نمایش ---------- */

	public static function render( $parent_id, $children, $child_id ) {
		$u   = get_userdata( $parent_id );
		$act = esc_url( admin_url( 'admin-post.php' ) );
		$h   = '';
		if ( isset( $_GET['hspp_msg'] ) ) {
			$h .= '<div class="hs-pp-note ok">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hspp_msg'] ) ) ) . '</div>';
		}
		if ( isset( $_GET['hspp_err'] ) ) {
			$h .= '<div class="hs-pp-note err">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hspp_err'] ) ) ) . '</div>';
		}

		// اطلاعات من
		$mobile = class_exists( 'HSUM_Phone' ) ? HSUM_Phone::user_mobile( $parent_id ) : (string) get_user_meta( $parent_id, '_hs_mobile', true );
		$h     .= '<section class="hs-pp-card"><h3>👤 اطلاعات من</h3><form method="post" action="' . $act . '" class="hs-pp-form">' . wp_nonce_field( 'hspp_profile', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="hspp_profile"><input type="hidden" name="what" value="me">'
			. '<label>نام <input name="first_name" value="' . esc_attr( $u->first_name ) . '" required></label>'
			. '<label>نام خانوادگی <input name="last_name" value="' . esc_attr( $u->last_name ) . '" required></label>'
			. '<label>موبایل <input value="' . esc_attr( $mobile ) . '" dir="ltr" disabled><small>شناسه‌ی ورود شماست؛ برای تغییر با مدیر سایت تماس بگیرید.</small></label>'
			. '<label>ایمیل (اختیاری) <input type="email" name="email" dir="ltr" value="' . esc_attr( false !== strpos( $u->user_email, '@hamshagerdi.local' ) ? '' : $u->user_email ) . '"></label>';

		// نام نمایشی در گروه‌ها
		$classes = array();
		foreach ( $children as $sid ) {
			foreach ( HSPP_Perms::student_classes( $sid ) as $cid ) {
				$classes[ $cid ] = get_the_title( $cid );
			}
		}
		if ( $classes ) {
			$h .= '<fieldset class="hs-pp-fs"><legend>نام شما در گروه والدین</legend><p class="hs-pp-muted">پیش‌فرض نقش شماست (مثل «مادرِ سارا»). خالی بگذارید تا به پیش‌فرض برگردد. شماره‌ی موبایل شما در گروه نمایش داده نمی‌شود.</p>';
			foreach ( $classes as $cid => $title ) {
				$custom = (string) get_user_meta( $parent_id, HSPP_Names::META . $cid, true );
				$h     .= '<label>' . esc_html( $title ) . ' <input name="gname[' . (int) $cid . ']" value="' . esc_attr( $custom ) . '" placeholder="' . esc_attr( HSPP_Names::default_name( $parent_id, $cid ) ) . '" maxlength="40"></label>';
			}
			$h .= '</fieldset>';
		}
		$h .= '<button class="hs-pp-btn hs-pp-btn-primary">ذخیره</button></form></section>';

		// اطلاعات فرزند
		if ( $child_id && class_exists( 'HS_Student_Profile' ) ) {
			$p    = HS_Student_Profile::get( $child_id );
			$conf = get_user_meta( $child_id, '_hspp_confirmed_' . $parent_id, true );
			$h   .= '<section class="hs-pp-card"><h3>🧒 اطلاعات ' . esc_html( get_userdata( $child_id )->display_name ) . '</h3>'
				. '<p class="hs-pp-muted">این اطلاعات را مدرسه یا معلم ثبت کرده است. اگر چیزی نادرست است اصلاح کنید؛ معلم کلاس باخبر می‌شود.' . ( $conf ? ' آخرین تأیید شما: ' . esc_html( mysql2date( 'Y/m/d', $conf ) ) : '' ) . '</p>'
				. '<form method="post" action="' . $act . '" class="hs-pp-form" enctype="multipart/form-data">' . wp_nonce_field( 'hspp_profile', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="hspp_profile"><input type="hidden" name="what" value="child"><input type="hidden" name="child" value="' . (int) $child_id . '">'
				. '<div class="hs-pp-photo">' . ( ! empty( $p['photo_url'] ) ? '<img src="' . esc_url( $p['photo_url'] ) . '" alt="" width="72" height="72">' : '<span>📷</span>' )
				. '<label>عکس فرزند (در کلاس کنار نامش دیده می‌شود)<input type="file" name="child_photo" accept="image/*"></label></div>';
			foreach ( self::child_fields() as $k => $f ) {
				$v  = (string) ( $p[ $k ] ?? '' );
				$h .= '<label>' . esc_html( $f[0] ) . ' <input ' . ( $f[1] ? 'name="' . esc_attr( $k ) . '"' : 'disabled' ) . ' value="' . esc_attr( $v ) . '"' . ( 'birth_date' === $k ? ' placeholder="۱۳۹۸/۰۵/۱۲"' : '' ) . ( 'parent_phone' === $k ? ' dir="ltr"' : '' ) . '>' . ( $f[1] ? '' : '<small>فقط مدرسه تغییر می‌دهد</small>' ) . '</label>';
			}
			foreach ( self::family_fields() as $k => $f ) {
				$v  = (string) get_user_meta( $child_id, $k, true );
				$h .= 'textarea' === $f[1]
					? '<label>' . esc_html( $f[0] ) . ' <textarea name="' . esc_attr( $k ) . '" rows="2">' . esc_textarea( $v ) . '</textarea></label>'
					: '<label>' . esc_html( $f[0] ) . ' <input name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '" dir="ltr"></label>';
			}
			$h .= '<label class="hs-pp-check"><input type="checkbox" name="confirm" value="1"> اطلاعات بالا را دیدم و درست است</label>'
				. '<button class="hs-pp-btn hs-pp-btn-primary">ذخیره</button></form></section>';

			// ورود فرزند به همشاگردی با رمز ۴ رقمی که والدین انتخاب می‌کنند
			if ( class_exists( 'HSSP_Login' ) ) {
				$classes = (array) get_user_meta( $child_id, '_hs_classes', true );
				$cid     = $classes ? (int) reset( $classes ) : 0;
				$code    = $cid ? HSSP_Login::class_code( $cid ) : '';
				$on      = HSSP_Login::parent_ok( $child_id );
				$h .= '<section class="hs-pp-card"><h3>🔑 ورود فرزند به همشاگردی</h3>'
					. '<p class="hs-pp-muted">فرزندتان بدون موبایل، با <b>کد کلاس</b> و یک <b>رمز ۴ رقمی</b> که شما انتخاب می‌کنید وارد دنیای دانش‌آموزی خودش می‌شود.</p>'
					. ( $on ? '<p>✅ دسترسی فعال است.</p>' : '<p>🔒 هنوز دسترسی ایجاد نشده.</p>' )
					. ( $code ? '<p>کد کلاس: <b dir="ltr" style="letter-spacing:3px">' . esc_html( $code ) . '</b> · <a href="' . esc_url( HSSP_Login::url( $code, $child_id ) ) . '" target="_blank">صفحه‌ی ورود فرزند</a></p>' : '' )
					. '<form method="post" action="' . $act . '" class="hs-pp-form hs-pp-pin">' . wp_nonce_field( 'hspp_profile', '_wpnonce', true, false )
					. '<input type="hidden" name="action" value="hspp_profile"><input type="hidden" name="what" value="kidpin"><input type="hidden" name="child" value="' . (int) $child_id . '">'
					. '<label>رمز ۴ رقمی <input name="pin" inputmode="numeric" pattern="[0-9۰-۹]{4}" maxlength="4" required dir="ltr" autocomplete="new-password"></label>'
					. '<label>تکرار رمز <input name="pin2" inputmode="numeric" pattern="[0-9۰-۹]{4}" maxlength="4" required dir="ltr" autocomplete="new-password"></label>'
					. '<button class="hs-pp-btn hs-pp-btn-primary">' . ( $on ? 'تغییر رمز' : 'ایجاد دسترسی' ) . '</button></form>'
					. ( $on ? '<form method="post" action="' . $act . '">' . wp_nonce_field( 'hspp_profile', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="hspp_profile"><input type="hidden" name="what" value="kidpin"><input type="hidden" name="child" value="' . (int) $child_id . '"><input type="hidden" name="revoke" value="1"><button class="hs-pp-btn">بستن دسترسی</button></form>' : '' )
					. '</section>';
			}
		}

		// دسترسی‌های فعال (شفافیت)
		if ( $child_id ) {
			$h .= '<section class="hs-pp-card"><h3>🔐 آن‌چه در پنل می‌بینید</h3><ul class="hs-pp-perms">';
			foreach ( HSPP_Perms::features() as $k => $f ) {
				$h .= '<li>' . ( HSPP_Perms::can( $parent_id, $child_id, $k ) ? '✅' : '🚫' ) . ' ' . esc_html( $f[0] ) . '</li>';
			}
			$h .= '</ul><p class="hs-pp-muted">این دسترسی‌ها را مدرسه تعیین می‌کند.</p></section>';
		}
		return $h;
	}
}

HSPP_Profile::init();
