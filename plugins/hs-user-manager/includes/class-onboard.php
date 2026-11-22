<?php
/**
 * «تکمیل ثبت‌نام»: بعد از اولین ورود (با هر فرمی، حتی پینوا) و قبل از رسیدن به پنل،
 * هر نقش اطلاعات لازم خودش را وارد می‌کند. هرچه بشود، خودکار وصل می‌شود:
 * - والدین: کد دعوت کلاس ← اتصال فوری؛ وگرنه شماره‌ی معلم ← درخواست اتصال برای معلم.
 * - معلم: نام مدرسه (اختیاری) ← برای اتصال بعدی به مدرسه.
 * - مدیر مدرسه: نام مدرسه و شهر ← درخواست نقش مدیر (نیازمند تأیید مدیر سایت).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSUM_Onboard {

	const DONE = '_hsum_onboarded';
	const DATA = '_hsum_profile';
	const STAFF_PENDING = '_hsum_staff_pending';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'gate' ), 4 );
		add_action( 'admin_post_hsum_onboard', array( __CLASS__, 'save' ) );
		add_action( 'hsum_status_changed', array( __CLASS__, 'on_status_changed' ), 20, 3 );
	}

	/** پس از تأیید مدیر مدرسه، فهرست کادری که پیش از تأیید ثبت کرده بود دعوت می‌شود */
	public static function on_status_changed( $uid, $status, $old = '' ) {
		if ( 'active' !== $status || ! class_exists( 'HS_SO_Store' ) || ! class_exists( 'HSUM_Staff' ) || ! method_exists( 'HS_SO_Store', 'manager_approved' ) ) {
			return;
		}
		$pend = get_user_meta( $uid, self::STAFF_PENDING, true );
		if ( ! is_array( $pend ) || empty( $pend['school'] ) || ! HS_SO_Store::manager_approved( $uid ) ) {
			return;
		}
		if ( (int) get_post_meta( (int) $pend['school'], '_hs_manager_id', true ) !== (int) $uid ) {
			delete_user_meta( $uid, self::STAFF_PENDING );
			return;
		}
		delete_user_meta( $uid, self::STAFF_PENDING );
		if ( ! empty( $pend['rows'] ) ) {
			HSUM_Staff::sync( (int) $pend['school'], (array) $pend['rows'], $uid );
		}
	}

	private static function role_of( $u ) {
		foreach ( array( 'school_admin', 'teacher', 'parent', 'student' ) as $r ) {
			if ( in_array( $r, (array) $u->roles, true ) ) {
				return $r;
			}
		}
		$m = class_exists( 'HSUM_Membership' ) ? HSUM_Membership::get( $u->ID ) : null;
		return is_array( $m ) && ! empty( $m['role'] ) ? $m['role'] : '';
	}

	/** این کاربر باید فرم را پر کند؟ (مدیر سایت و دانش‌آموز نه) */
	public static function needs( $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		if ( ! $uid || user_can( $uid, 'manage_options' ) || get_user_meta( $uid, self::DONE, true ) ) {
			return false;
		}
		$u = get_userdata( $uid );
		return $u && 'student' !== self::role_of( $u );
	}

	public static function url() {
		return add_query_arg( 'onboard', 1, HSUM_Front::page_url() );
	}

	/** ورود به پنل قبل از تکمیل ثبت‌نام ← فرم */
	public static function gate() {
		if ( ! is_user_logged_in() || ! self::needs() || ! class_exists( 'HS_Shell' ) || ! HS_Shell::is_panel_request() ) {
			return;
		}
		if ( (int) get_queried_object_id() === (int) get_option( 'hsum_page_id' ) ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/* ------------------------------ فرم ------------------------------ */

	public static function form() {
		$u    = wp_get_current_user();
		$role = self::role_of( $u );
		$p    = (array) get_user_meta( $u->ID, self::DATA, true );
		$m    = class_exists( 'HSUM_Phone' ) ? HSUM_Phone::user_mobile( $u->ID ) : '';
		$v    = function ( $k, $d = '' ) use ( $p ) {
			return esc_attr( $p[ $k ] ?? $d );
		};
		$join = isset( $_GET['join'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['join'] ) ) ) : ''; // phpcs:ignore
		$roles = array( 'parent' => array( '🏡', 'والدین', 'فرزندم در کلاس است' ), 'teacher' => array( '👩‍🏫', 'معلم', 'کلاس دارم یا می‌سازم' ), 'school_admin' => array( '🏫', 'مدیر مدرسه', 'مدرسه را مدیریت می‌کنم' ) );
		ob_start();
		?>
		<div class="hsum-onb" dir="rtl">
			<div class="hsum-onb-head"><span>👋</span><div><h2>تکمیل ثبت‌نام</h2><p>چند سؤال کوتاه تا پنل مخصوص شما آماده شود. فیلدهای ستاره‌دار لازم‌اند.</p></div></div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hsum-onb-form">
				<?php wp_nonce_field( 'hsum_onboard' ); ?>
				<input type="hidden" name="action" value="hsum_onboard">

				<fieldset class="hsum-onb-roles"><legend>نقش شما *</legend>
					<?php foreach ( $roles as $k => $r ) : ?>
						<label class="hsum-onb-role"><input type="radio" name="role" value="<?php echo esc_attr( $k ); ?>" <?php checked( $role ? $role : ( $join ? 'parent' : '' ), $k ); ?> <?php disabled( $role && $role !== $k && 'school_admin' !== $k ); ?> required>
							<span class="i"><?php echo esc_html( $r[0] ); ?></span><b><?php echo esc_html( $r[1] ); ?></b><small><?php echo esc_html( $r[2] ); ?></small></label>
					<?php endforeach; ?>
				</fieldset>

				<div class="hsum-onb-grid hsum-onb-common">
					<label>نام *<input name="first_name" data-req-common value="<?php echo esc_attr( self::real_name( $u->first_name, $u ) ); ?>" placeholder="مثلاً علی"></label>
					<label>نام خانوادگی *<input name="last_name" data-req-common value="<?php echo esc_attr( self::real_name( $u->last_name, $u ) ); ?>" placeholder="مثلاً محمدی"></label>
					<label>شماره موبایل<input value="<?php echo esc_attr( $m ); ?>" dir="ltr" disabled></label>
					<label class="hsum-onb-city">شهر<input name="city" value="<?php echo $v( 'city' ); ?>"></label>
				</div>

				<div class="hsum-onb-part hsum-onb-cards" data-for="parent">
					<section class="hsum-onb-card">
						<h3>🧒 اطلاعات فرزند</h3>
						<div class="hsum-onb-grid">
							<label>نام *<input name="child_first" value="<?php echo $v( 'child_first' ); ?>" data-req="parent"></label>
							<label>نام خانوادگی *<input name="child_last" value="<?php echo $v( 'child_last' ); ?>" data-req="parent"></label>
							<label>استان<?php echo self::province_select( 'province', $p['province'] ?? '' ); // phpcs:ignore ?></label>
							<label>شهر<input name="child_city" value="<?php echo $v( 'child_city' ); ?>"></label>
							<label class="wide">آدرس مدرسه<input name="school_address" value="<?php echo $v( 'school_address' ); ?>"></label>
						</div>
					</section>

					<section class="hsum-onb-card">
						<h3>👨‍👩‍👧 والدین</h3>
						<div class="hsum-onb-sub">پدر</div>
						<div class="hsum-onb-grid g3">
							<label>نام<input name="father_first" value="<?php echo $v( 'father_first' ); ?>"></label>
							<label>نام خانوادگی<input name="father_last" value="<?php echo $v( 'father_last' ); ?>"></label>
							<label>شماره همراه<input name="father_mobile" dir="ltr" inputmode="tel" maxlength="13" value="<?php echo $v( 'father_mobile' ); ?>" placeholder="09…"></label>
						</div>
						<div class="hsum-onb-sub">مادر</div>
						<div class="hsum-onb-grid g3">
							<label>نام<input name="mother_first" value="<?php echo $v( 'mother_first' ); ?>"></label>
							<label>نام خانوادگی<input name="mother_last" value="<?php echo $v( 'mother_last' ); ?>"></label>
							<label>شماره همراه<input name="mother_mobile" dir="ltr" inputmode="tel" maxlength="13" value="<?php echo $v( 'mother_mobile' ); ?>" placeholder="09…"></label>
						</div>
						<div class="hsum-onb-grid">
							<label class="wide">آدرس<input name="home_address" value="<?php echo $v( 'home_address' ); ?>"></label>
							<label>شماره منزل<input name="home_phone" dir="ltr" inputmode="tel" maxlength="12" value="<?php echo $v( 'home_phone' ); ?>"></label>
							<label>کد پستی منزل<input name="postal_code" dir="ltr" inputmode="numeric" maxlength="10" value="<?php echo $v( 'postal_code' ); ?>"></label>
							<label>نسبت شما با فرزند<select name="relation"><option value="mother">مادر</option><option value="father">پدر</option><option value="guardian">سرپرست</option></select></label>
						</div>
					</section>

					<section class="hsum-onb-card">
						<h3>🏫 مدرسه و کلاس</h3>
						<div class="hsum-onb-grid">
							<label>نام مدرسه<input name="school_name" value="<?php echo $v( 'school_name' ); ?>"></label>
							<label>نام مدیر مدرسه<input name="manager_name" value="<?php echo $v( 'manager_name' ); ?>"></label>
							<label>نام معلم<input name="teacher_name" value="<?php echo $v( 'teacher_name' ); ?>"></label>
							<label>موبایل معلم<input name="teacher_mobile" dir="ltr" inputmode="tel" value="<?php echo $v( 'teacher_mobile' ); ?>" placeholder="09…"></label>
							<label class="wide">کد دعوت کلاس (روی کارت کلاس یا پیامک معلم)
								<span class="hsum-code-row"><input name="code" dir="ltr" value="<?php echo esc_attr( $join ); ?>" placeholder="مثلاً 9HMU3U"><button type="button" class="hsum-qr-scan">▦ اسکن QR</button></span></label>
						</div>
						<div class="hsum-qr-cam" hidden><video playsinline muted></video><p>کیوآر کد کلاس را جلوی دوربین بگیرید…</p><button type="button" class="hsum-qr-stop">بستن دوربین</button></div>
						<div class="hsum-kids"><?php foreach ( (array) ( $p['kids'] ?? array() ) as $i => $k ) { echo self::kid_box( $i + 1, $k ); } // phpcs:ignore ?></div>
						<template id="hsum-kid-tpl"><?php echo self::kid_box( '__N__', array() ); // phpcs:ignore ?></template>
						<button type="button" class="hsum-kid-add" title="فرزند دیگری دارید؟">＋ افزودن فرزند دیگر</button>
						<p class="hsum-onb-hint">اگر معلم شماره‌ی شما را ثبت کرده باشد، خودکار وصل می‌شوید. با <b>کد دعوت</b> یا <b>اسکن کیوآر کد کلاس</b> فوری وصل می‌شوید؛ با <b>موبایل معلم</b>، درخواست اتصال برای معلم فرستاده می‌شود.</p>
					</section>
				</div>

				<div class="hsum-onb-part" data-for="teacher">
					<h3>اطلاعات معلم</h3>
					<div class="hsum-onb-grid">
						<label>نام مدرسه<input name="school_name_t" value="<?php echo $v( 'school_name' ); ?>" placeholder="اگر هنوز مدرسه ندارید، خالی بگذارید"></label>
						<label>پایه یا درس<input name="subject" value="<?php echo $v( 'subject' ); ?>" placeholder="مثلاً پایه‌ی سوم"></label>
						<label>نام مدیر مدرسه<input name="manager_name_t" value="<?php echo $v( 'manager_name' ); ?>"></label>
						<label>موبایل مدیر مدرسه<input name="manager_mobile_t" dir="ltr" inputmode="tel" value="<?php echo $v( 'manager_mobile' ); ?>" placeholder="09…"></label>
						<label class="wide">نشانی مدرسه<input name="school_address_t" value="<?php echo $v( 'school_address' ); ?>"></label>
					</div>
					<p class="hsum-onb-hint">بعد از ثبت، کلاس خودتان را می‌سازید و والدین را با شماره، کد دعوت یا لینک کلاس دعوت می‌کنید. اگر بعداً مدیر مدرسه دعوتتان کند، کلاس‌هایتان به مدرسه وصل می‌شود.</p>
				</div>

				<div class="hsum-onb-part hsum-onb-cards" data-for="school_admin">
					<section class="hsum-onb-card">
						<h3>🏫 اطلاعات مدرسه</h3>
						<div class="hsum-onb-grid">
							<label>نام مدرسه *<input name="school_name_m" value="<?php echo $v( 'school_name' ); ?>" data-req="school_admin"></label>
							<label>مقطع<select name="school_type" class="hsum-level-src"><?php foreach ( array( 'preschool' => 'پیش‌دبستانی', 'primary' => 'ابتدایی', 'middle' => 'متوسطه‌ی اول', 'high' => 'متوسطه‌ی دوم' ) as $k => $o ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $p['school_type'] ?? 'primary', $k, false ) . '>' . esc_html( $o ) . '</option>'; } ?></select></label>
							<label>استان *<?php echo str_replace( '<select ', '<select data-req="school_admin" ', self::province_select( 'province_m', $p['province'] ?? '' ) ); // phpcs:ignore ?></label>
							<label>شهر *<input name="city_m" value="<?php echo $v( 'city' ); ?>" data-req="school_admin"></label>
							<label class="wide">آدرس مدرسه *<input name="school_address_m" value="<?php echo $v( 'school_address' ); ?>" data-req="school_admin"></label>
							<label>کد پستی مدرسه (۱۰ رقم) *<input name="postal_code_m" data-req="school_admin" dir="ltr" inputmode="numeric" maxlength="10" value="<?php echo $v( 'postal_code' ); ?>"></label>
							<label>تلفن مدرسه<input name="school_phone" dir="ltr" inputmode="tel" maxlength="12" value="<?php echo $v( 'school_phone' ); ?>"></label>
						</div>
					</section>
					<section class="hsum-onb-card">
						<h3>🪪 احراز مدیر</h3>
						<div class="hsum-onb-grid">
							<label>کد پرسنلی *<input name="personnel_code" dir="ltr" inputmode="numeric" maxlength="12" value="<?php echo $v( 'personnel_code' ); ?>" data-req="school_admin"></label>
							<label>کد مدرسه (کد آموزش‌وپرورش) *<input name="school_code" data-req="school_admin" dir="ltr" inputmode="numeric" maxlength="12" value="<?php echo $v( 'school_code' ); ?>"></label>

						</div>
						<p class="hsum-onb-hint">نقش مدیر مدرسه بعد از بررسی کد پرسنلی و تأیید مدیر سایت فعال می‌شود. همه‌ی این اطلاعات بعداً از «مدرسه من ← اطلاعات مدرسه» قابل ویرایش است.</p>
					</section>
					<section class="hsum-onb-card">
						<h3>👥 معاونان و معلمان <small>(اختیاری؛ بعداً هم می‌توانید اضافه کنید)</small></h3>
						<?php echo HSUM_Staff::section( class_exists( 'HS_SO_Store' ) ? HS_SO_Store::manager_school_id( $u->ID ) : 0 ); // phpcs:ignore ?>
					</section>
				</div>

				<button class="hsum-btn hsum-onb-go">ذخیره و ورود به پنل</button>
			</form>
		</div>
		<h3 style="display:none"></h3>
		<script>
		(function(){
		/* اسکن کیوآر کد کلاس با دوربین (اگر مرورگر پشتیبانی کند)؛ وگرنه راهنمای دوربین گوشی */
		var btn=document.querySelector('.hsum-qr-scan'),box=document.querySelector('.hsum-qr-cam');if(!btn||!box)return;
		var vid=box.querySelector('video'),stream=null,timer=null,inp=document.querySelector('[name=code]');
		function stop(){if(timer)clearInterval(timer);if(stream)stream.getTracks().forEach(function(t){t.stop()});stream=null;box.hidden=true;}
		function take(txt){var m=String(txt).match(/\/join\/([A-Za-z0-9-]{4,32})/);inp.value=(m?m[1]:String(txt)).toUpperCase().trim();stop();inp.focus();inp.style.borderColor='#2EA043';}
		btn.addEventListener('click',function(){
			if(!('BarcodeDetector' in window)||!navigator.mediaDevices){alert('در این مرورگر اسکن داخل صفحه پشتیبانی نمی‌شود. دوربین گوشی را روی کیوآر کد بگیرید و لینکی را که ظاهر می‌شود باز کنید؛ مستقیم به همین مرحله برمی‌گردید و کد پر شده است.');return;}
			var det=new BarcodeDetector({formats:['qr_code']});
			navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(function(s){stream=s;vid.srcObject=s;vid.play();box.hidden=false;
				timer=setInterval(function(){det.detect(vid).then(function(r){if(r&&r[0])take(r[0].rawValue);}).catch(function(){});},400);
			}).catch(function(){alert('اجازه‌ی دسترسی به دوربین داده نشد.');});
		});
		box.querySelector('.hsum-qr-stop').addEventListener('click',stop);
		})();
		(function(){var a=document.querySelector('.hsum-kid-add'),w=document.querySelector('.hsum-kids'),t=document.getElementById('hsum-kid-tpl');if(!a||!w||!t)return;
			a.addEventListener('click',function(){var n=w.children.length+1;w.insertAdjacentHTML('beforeend',t.innerHTML.replace(/__N__/g,n).replace(/__L__/g,n+1));var b=w.lastElementChild;b.querySelector('input').focus();});
			w.addEventListener('click',function(e){if(e.target.closest('.hsum-kid-del'))e.target.closest('.hsum-kid').remove();});})();
		(function(){var f=document.querySelector('.hsum-onb-form');if(!f)return;function u(){var r=(f.querySelector('[name=role]:checked')||{}).value;var c=f.querySelector('.hsum-onb-common');if(c){c.hidden=(r==='parent');var ct=c.querySelector('.hsum-onb-city');if(ct)ct.hidden=(r==='school_admin');c.querySelectorAll('[data-req-common]').forEach(function(i){i.required=!!r&&r!=='parent'})}f.querySelectorAll('.hsum-onb-part').forEach(function(p){var on=p.dataset.for===r;p.hidden=!on;p.querySelectorAll('[data-req]').forEach(function(i){i.required=on&&i.dataset.req===r})})}f.addEventListener('change',u);u();})();
		</script>
		<?php
		return ob_get_clean();
	}

	/** فرزند اضافه (برای والدینی که بیش از یک فرزند دارند) */
	private static function kid_box( $n, $k ) {
		$v = function ( $x ) use ( $k ) { return esc_attr( $k[ $x ] ?? '' ); };
		$f = 'kids[' . $n . ']';
		return '<div class="hsum-kid"><div class="hsum-onb-sub">🧒 فرزند ' . ( is_numeric( $n ) ? esc_html( $n + 1 ) : '__L__' ) . ' <button type="button" class="hsum-kid-del" aria-label="حذف">×</button></div><div class="hsum-onb-grid">'
			. '<label>نام فرزند<input name="' . $f . '[first]" value="' . $v( 'first' ) . '"></label>'
			. '<label>نام خانوادگی<input name="' . $f . '[last]" value="' . $v( 'last' ) . '"></label>'
			. '<label>نام مدرسه<input name="' . $f . '[school]" value="' . $v( 'school' ) . '"></label>'
			. '<label>نام معلم<input name="' . $f . '[teacher]" value="' . $v( 'teacher' ) . '"></label>'
			. '<label>موبایل معلم<input name="' . $f . '[teacher_mobile]" dir="ltr" inputmode="tel" value="' . $v( 'teacher_mobile' ) . '" placeholder="09…"></label>'
			. '<label>کد دعوت کلاس<input name="' . $f . '[code]" dir="ltr" value="" placeholder="مثلاً 9HMU3U"></label>'
			. '</div></div>';
	}

	/** نام پیش‌فرضی که سامانه‌ی ورود می‌گذارد («کاربر»، شماره، نام کاربری) واقعی نیست */
	private static function real_name( $n, $u ) {
		$n = trim( (string) $n );
		$fake = array( 'کاربر', 'کاربر جدید', 'user', 'User', $u->user_login, preg_replace( '/\D/', '', $u->user_login ) );
		return ( '' === $n || in_array( $n, $fake, true ) || preg_match( '/^(\+?98|0)?9\d{9}$/', $n ) || preg_match( '/^کاربر\s*\d*$/u', $n ) ) ? '' : $n;
	}

	private static function province_select( $name, $cur ) {
		$list = array( 'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز', 'ایلام', 'بوشهر', 'تهران', 'چهارمحال و بختیاری', 'خراسان جنوبی', 'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان', 'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان', 'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان', 'گیلان', 'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد' );
		$h = '<select name="' . esc_attr( $name ) . '"><option value="">انتخاب…</option>';
		foreach ( $list as $p ) {
			$h .= '<option' . selected( $cur, $p, false ) . '>' . esc_html( $p ) . '</option>';
		}
		return $h . '</select>';
	}

	/* ------------------------------ ذخیره ------------------------------ */

	/**
	 * منطق مشترک تکمیل ثبت‌نام (فرم سایت و API اپ).
	 * از ۲.۶.۳ از save() جدا شد تا اپ موبایل همان قواعد را بدون تکرار کد اجرا کند.
	 *
	 * @param int   $uid کاربر
	 * @param array $in  داده‌ی فرم (unslash شده)
	 * @return array{message:string,role:string,to:string}|WP_Error
	 */
	public static function process( $uid, $in ) {
		$u   = get_userdata( (int) $uid );
		if ( ! $u ) {
			return new WP_Error( 'user', 'کاربر یافت نشد.' );
		}
		$uid = (int) $u->ID;
		$in  = (array) $in;
		$t   = function ( $k ) use ( $in ) {
			return sanitize_text_field( $in[ $k ] ?? '' );
		};
		$cur  = self::role_of( $u );
		$role = in_array( $in['role'] ?? '', array( 'parent', 'teacher', 'school_admin' ), true ) ? $in['role'] : '';
		if ( ! $role ) {
			return new WP_Error( 'onboard', 'نقش خود را انتخاب کنید.' );
		}
		$first = $t( 'first_name' );
		$last  = $t( 'last_name' );
		if ( 'parent' === $role ) {
			// والد: نام از کارت «والدین» و بر اساس نسبت (سرپرست → پدر، وگرنه مادر)
			$rel   = sanitize_key( $in['relation'] ?? '' );
			$order = 'mother' === $rel ? array( 'mother', 'father' ) : array( 'father', 'mother' );
			foreach ( $order as $who ) {
				if ( '' !== $t( $who . '_first' ) && '' !== $t( $who . '_last' ) ) {
					$first = $t( $who . '_first' );
					$last  = $t( $who . '_last' );
					break;
				}
			}
			if ( '' === $first || '' === $last ) {
				return new WP_Error( 'onboard', 'در کارت «والدین»، نام و نام خانوادگی خودتان را بنویسید.' );
			}
		}
		if ( '' === $first || '' === $last ) {
			return new WP_Error( 'onboard', 'نام و نام خانوادگی لازم است.' );
		}
		wp_update_user( array( 'ID' => $uid, 'first_name' => $first, 'last_name' => $last, 'display_name' => $first . ' ' . $last ) );

		$p = array( 'city' => 'parent' === $role ? $t( 'child_city' ) : $t( 'city' ) );
		$msg = 'ثبت‌نام کامل شد.';

		// نقش: کسی که هنوز نقش ندارد (مثلاً با پینوا ثبت‌نام کرده) نقش انتخابی را می‌گیرد
		if ( ! $cur ) {
			$needs = in_array( $role, (array) hsum_opt( 'approval_roles' ), true );
			if ( $needs ) {
				HSUM_Membership::request_role( $uid, $role );
			} else {
				$u->set_role( $role );
			}
		} elseif ( 'school_admin' === $role && 'school_admin' !== $cur ) {
			HSUM_Membership::request_role( $uid, 'school_admin' );
		}

		if ( 'parent' === $role ) {
			$p += array(
				'child_first' => $t( 'child_first' ), 'child_last' => $t( 'child_last' ),
				'relation' => sanitize_key( $in['relation'] ?? 'parent' ), 				'father_first' => $t( 'father_first' ), 'father_last' => $t( 'father_last' ),
				'mother_first' => $t( 'mother_first' ), 'mother_last' => $t( 'mother_last' ),
				'father_mobile' => HSUM_Phone::norm( $t( 'father_mobile' ) ), 'mother_mobile' => HSUM_Phone::norm( $t( 'mother_mobile' ) ),
				'province' => $t( 'province' ), 'child_city' => $t( 'child_city' ), 'school_address' => $t( 'school_address' ),
				'home_address' => $t( 'home_address' ), 'home_phone' => preg_replace( '/\D/', '', $t( 'home_phone' ) ), 'postal_code' => preg_replace( '/\D/', '', $t( 'postal_code' ) ),
				'school_name' => $t( 'school_name' ), 'manager_name' => $t( 'manager_name' ),
				'teacher_name' => $t( 'teacher_name' ), 'teacher_mobile' => $t( 'teacher_mobile' ),
			);
			$p['child_name'] = trim( $p['child_first'] . ' ' . $p['child_last'] );
			if ( mb_strlen( $p['child_name'] ) < 3 ) {
				return new WP_Error( 'onboard', 'نام و نام خانوادگی فرزند را بنویسید.' );
			}
			$linked = class_exists( 'HSUM_Links' ) && HSUM_Links::for_parent( $uid );
			$code   = strtoupper( $t( 'code' ) );
			if ( $code ) {
				$r = HSUM_Invites::parent_join( $code, $uid, $p['child_name'] );
				if ( is_wp_error( $r ) ) {
					return new WP_Error( 'onboard', 'کد دعوت: ' . $r->get_error_message() );
				}
				$msg = 'به کلاس وصل شدید.';
			} elseif ( ! $linked && $p['teacher_mobile'] ) {
				$r = HSUM_Requests::create( $uid, array( 'teacher_mobile' => $p['teacher_mobile'], 'child_name' => $p['child_name'], 'message' => trim( $p['school_name'] . ' ' . $p['teacher_name'] ) ) );
				$msg = is_wp_error( $r ) ? 'ثبت شد؛ ولی درخواست اتصال فرستاده نشد: ' . $r->get_error_message() : 'ثبت شد؛ درخواست اتصال برای معلم فرستاده شد.';
			} elseif ( $linked ) {
				$msg = 'شما به فرزندتان وصل هستید.';
			} else {
				$msg = 'ثبت شد. برای اتصال به کلاس، کد دعوت یا شماره‌ی معلم را در همین صفحه وارد کنید.';
			}
			// فرزندان دیگر
			$p['kids'] = array();
			$extra     = 0;
			foreach ( (array) ( $in['kids'] ?? array() ) as $k ) {
				$k = array_map( 'sanitize_text_field', array_intersect_key( (array) $k, array_flip( array( 'first', 'last', 'school', 'teacher', 'teacher_mobile', 'code' ) ) ) );
				$name = trim( ( $k['first'] ?? '' ) . ' ' . ( $k['last'] ?? '' ) );
				if ( mb_strlen( $name ) < 3 ) {
					continue;
				}
				$kc = strtoupper( $k['code'] ?? '' );
				unset( $k['code'] );
				$p['kids'][] = $k;
				if ( $kc ) {
					$r = HSUM_Invites::parent_join( $kc, $uid, $name );
				} elseif ( ! empty( $k['teacher_mobile'] ) ) {
					$r = HSUM_Requests::create( $uid, array( 'teacher_mobile' => $k['teacher_mobile'], 'child_name' => $name, 'message' => trim( ( $k['school'] ?? '' ) . ' ' . ( $k['teacher'] ?? '' ) ) ) );
				} else {
					continue;
				}
				$extra += is_wp_error( $r ) ? 0 : 1;
			}
			if ( $extra ) {
				$msg .= ' برای ' . $extra . ' فرزند دیگر هم اتصال/درخواست ثبت شد.';
			}
		} elseif ( 'teacher' === $role ) {
			$p += array( 'school_name' => $t( 'school_name_t' ), 'subject' => $t( 'subject' ), 'manager_name' => $t( 'manager_name_t' ), 'manager_mobile' => HSUM_Phone::norm( $t( 'manager_mobile_t' ) ), 'school_address' => $t( 'school_address_t' ) );
			if ( $p['school_name'] ) {
				update_user_meta( $uid, '_hs_school_name', $p['school_name'] );
			}
			if ( $p['manager_mobile'] ) {
				update_user_meta( $uid, '_hs_manager_mobile', $p['manager_mobile'] );
				// کلاس‌های فعلی معلم که موبایل مدیر ندارند ← برای اتصال، به مدیر معرفی شوند
				foreach ( get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_hs_teacher_id', 'meta_value' => $uid ) ) as $cid ) { // phpcs:ignore
					if ( ! get_post_meta( $cid, '_hs_manager_mobile', true ) ) {
						update_post_meta( $cid, '_hs_manager_mobile', $p['manager_mobile'] );
					}
				}
			}
			$msg = 'خوش آمدید! حالا کلاس خودتان را بسازید.';
		} else {
			$p = array_merge( $p, array(
				'school_name' => $t( 'school_name_m' ), 'school_type' => $t( 'school_type' ), 'school_address' => $t( 'school_address_m' ),
				'province' => $t( 'province_m' ), 'city' => $t( 'city_m' ), 'postal_code' => preg_replace( '/\D/', '', $t( 'postal_code_m' ) ),
				'school_phone' => preg_replace( '/\D/', '', $t( 'school_phone' ) ), 'school_code' => preg_replace( '/\D/', '', $t( 'school_code' ) ),
			) );
			$pc = preg_replace( '/\D/', '', strtr( $t( 'personnel_code' ), array( '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9' ) ) );
			$p['personnel_code'] = $pc;
			if ( strlen( $pc ) < 6 ) {
				return new WP_Error( 'onboard', 'کد پرسنلی معتبر وارد کنید (فقط عدد).' );
			}
			update_user_meta( $uid, '_hs_personnel_code', $pc );
			if ( '' === $p['province'] || '' === $p['school_address'] ) {
				return new WP_Error( 'onboard', 'استان و آدرس مدرسه لازم است.' );
			}
			if ( '' === $p['school_name'] ) {
				return new WP_Error( 'onboard', 'نام مدرسه لازم است.' );
			}
			update_user_meta( $uid, '_hs_school_name', $p['school_name'] );
			$msg = 'ثبت شد؛ نقش مدیر مدرسه بعد از تأیید مدیر سایت فعال می‌شود.';
			// پرونده‌ی مدرسه (افزونه‌ی سازمان مدرسه) ساخته/به‌روز می‌شود تا بعداً قابل ویرایش باشد
			if ( class_exists( 'HS_SO_Store' ) ) {
				// مدرسه‌ی خودِ کاربر (حتی اگر هنوز تأیید نشده) تا با هر بار ارسال فرم، مدرسه‌ی تکراری ساخته نشود
				$sid = method_exists( 'HS_SO_Store', 'owned_school_id' ) ? HS_SO_Store::owned_school_id( $uid ) : HS_SO_Store::manager_school_id( $uid );
				$res = HS_SO_Store::save_school( $sid, array(
					'name' => $p['school_name'], 'province' => $p['province'], 'city' => $p['city'], 'address' => $p['school_address'],
					'postal' => $p['postal_code'], 'code' => $p['school_code'], 'phone' => $p['school_phone'], 'level' => $p['school_type'],
				), $uid );
				if ( is_wp_error( $res ) ) {
					update_user_meta( $uid, self::DATA, $p ); // تا فرم با همین اطلاعات دوباره پر شود
					return new WP_Error( 'onboard', $res->get_error_message() );
				}
				// امنیت (۲.۶.۳): دعوت کادر فقط از مدیرِ تأییدشده. دعوت، حساب موجودِ آن شماره را مستقیم
				// عضو کادر می‌کند و نقش معلم می‌دهد؛ پس پیش از تأیید فقط ذخیره می‌شود و بعد از تأیید اجرا می‌شود.
				$approved = ! method_exists( 'HS_SO_Store', 'manager_approved' ) || HS_SO_Store::manager_approved( $uid );
				if ( $approved ) {
					$st = HSUM_Staff::sync( (int) $res, $in['staff'] ?? array(), $uid );
					if ( $st['added'] ) {
						$msg .= ' برای ' . $st['added'] . ' نفر از کادر، دعوت‌نامه ساخته شد.';
					}
					if ( $st['errors'] ) {
						$msg .= ' ' . implode( ' ', $st['errors'] );
					}
					delete_user_meta( $uid, self::STAFF_PENDING );
				} else {
					$staff = array_values( array_filter( (array) ( $in['staff'] ?? array() ), 'is_array' ) );
					update_user_meta( $uid, self::STAFF_PENDING, array( 'school' => (int) $res, 'rows' => array_slice( $staff, 0, 60 ) ) );
					if ( $staff ) {
						$msg .= ' فهرست کادر ذخیره شد و پس از تأیید شما، دعوت‌نامه‌ها ساخته می‌شوند.';
					}
				}
			}
		}
		update_user_meta( $uid, self::DATA, $p );
		update_user_meta( $uid, self::DONE, time() );
		HSUM_Audit::log( 'onboard', $uid, array( 'role' => $role ) );

		$to = 'teacher' === $role ? home_url( '/panel/classes/' ) : ( 'parent' === $role && empty( $linked ) && empty( $code ) ? HSUM_Front::page_url() : home_url( '/panel/' ) );
		return array( 'message' => $msg, 'role' => $role, 'to' => $to );
	}

	/** فرم سایت */
	public static function save() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'hsum_onboard' );
		$r = self::process( get_current_user_id(), wp_unslash( $_POST ) );
		if ( is_wp_error( $r ) ) {
			wp_safe_redirect( add_query_arg( array( 'onboard' => 1, 'hsum_err' => rawurlencode( $r->get_error_message() ) ), HSUM_Front::page_url() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'hsum_msg', rawurlencode( $r['message'] ), $r['to'] ) );
		exit;
	}
}
