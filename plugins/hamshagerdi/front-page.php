<?php
/**
 * صفحه‌ی اصلی همشاگردی — مسیر کاربر: ورود ← انتخاب نقش ← پنل مخصوص هر نقش
 */
get_header();
$icons = array( 'teacher' => 'teacher', 'parent' => 'parent', 'student' => 'student', 'school_admin' => 'school' );
$logged = is_user_logged_in();
$slugs = array( 'teacher' => 'teachers', 'parent' => 'parents', 'student' => 'students', 'school_admin' => 'schools' );

$roles = array(
	'teacher'      => array( 'معلم', '#F57C00', '📚', 'کلاست را بساز، حضور بزن، تکلیف بده و با یک لمس به خانواده‌ها خبر بده.', array( 'ساخت کلاس، حتی بدون مدرسه', 'حضور و غیاب و امتیاز با یک لمس', 'تکلیف، کاربرگ و پیام به والدین' ), 'ورود معلم' ),
	'parent'       => array( 'والدین', '#1565C0', '🏡', 'روز فرزندت را ببین: حضور، تکلیف، نشان‌ها و پیام معلم، همان لحظه.', array( 'خلاصه‌ی روزانه و نمودار پیشرفت', 'گفتگو با معلم و گروه کلاس', 'درخواست جلسه و تقویم مدرسه' ), 'ورود والدین' ),
	'student'      => array( 'دانش‌آموز', '#00A884', '🎒', 'دنیای رنگی خودت: نشان جمع کن، تکلیف بفرست و کارهایت را به همه نشان بده.', array( 'ورود با کارت کلاس و QR', 'نشان‌ها، جایزه‌ها و بازی', 'پورتفولیوی کارهای خودم' ), 'ورود دانش‌آموز' ),
	'school_admin' => array( 'مدیر مدرسه', '#0F2F6B', '⭐', 'مدرسه را ثبت کن، معاونین را تعیین کن و همه‌ی کلاس‌ها را یک‌جا ببین.', array( 'ثبت مدرسه و کادر', 'واگذاری و پیگیری وظایف', 'گزارش و نظارت بر کلاس‌ها' ), 'ورود مدیر' ),
);
?>

<!-- ===== هیرو ===== -->
<section class="hst-hero2">
	<div class="hst-blob hst-blob-a" aria-hidden="true"></div>
	<div class="hst-blob hst-blob-b" aria-hidden="true"></div>
	<div class="hst-wrap hst-hero2-in">
		<div class="hst-hero2-text">
			<span class="hst-chip hst-in" style="--d:0">✨ برای معلم‌ها، خانواده‌ها و بچه‌ها</span>
			<h1 class="hst-in" style="--d:1"><?php echo esc_html( hst_mod( 'hst_hero_title', 'هر روزِ کلاس،' ) ); ?><br><span class="hst-grad"><?php echo esc_html( hst_mod( 'hst_hero_mark', 'یک قصه‌ی تازه' ) ); ?></span></h1>
			<p class="hst-lead hst-in" style="--d:2"><?php echo esc_html( hst_mod( 'hst_hero_text', 'همشاگردی معلم، خانواده و بچه‌ها را کنار هم می‌آورد: حضور و غیاب، تکلیف، امتیاز، پیام و پورتفولیو؛ همه در یک خانه‌ی شاد.' ) ); ?></p>
			<?php if ( $logged ) : ?>
				<div class="hst-pick hst-in" style="--d:3">
					<b>میانبرهای من</b>
					<div class="hst-mine"><?php echo hst_my_shortcuts(); // phpcs:ignore ?></div>
				</div>
			<?php else : ?>
			<div class="hst-pick hst-in" style="--d:3">
				<b>ورود به‌عنوان…</b>
				<div class="hst-pick-row">
					<?php foreach ( $roles as $k => $r ) : ?>
						<a class="hst-pick-btn" href="<?php echo hst_login_url( $k ); ?>" style="--rc:<?php echo esc_attr( $r[1] ); ?>">
							<span class="hst-pick-ico"><svg viewBox="0 0 64 64" aria-hidden="true"><use href="#hst-r-<?php echo esc_attr( $icons[ $k ] ); ?>"/></svg></span><span><?php echo esc_html( $r[0] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<div class="hst-stage" data-hst-parallax aria-hidden="true">
			<div class="hst-ring"></div>
			<div class="hst-phone" data-depth="10">
				<div class="hst-phone-top"><span class="hst-ph-av"><?php echo hst_role_icon( 'teacher' ); ?></span><div><b>کلاس سوم الف</b><small>امروز · ۲۶ دانش‌آموز</small></div></div>
				<div class="hst-feed">
					<div class="hst-post-mini hst-pop" style="--d:0"><span class="hst-dot o"></span><p><b>حضور و غیاب</b> ثبت شد</p><span class="hst-ok">۲۴ ✓</span></div>
					<div class="hst-post-mini hst-pop" style="--d:1"><span class="hst-dot b"></span><p><b>تکلیف ریاضی</b> برای پنجشنبه</p></div>
					<div class="hst-photo hst-pop" style="--d:2"><span>🔬</span><span>🌱</span><span>🎨</span><small>آزمایش امروز کلاس</small></div>
					<div class="hst-post-mini hst-pop" style="--d:3"><span class="hst-dot g"></span><p><b>سارا</b> نشان همکاری گرفت</p><span class="hst-badge-mini">⭐</span></div>
				</div>
			</div>
			<div class="hst-float hst-f1" data-depth="26"><span class="hst-plus">+۱</span><div><b>همکاری</b><small>آرش</small></div></div>
			<div class="hst-float hst-f2" data-depth="-20"><span>💬</span><div><b>مادرِ سارا</b><small>ممنون خانم معلم 🌷</small></div></div>
			<div class="hst-float hst-f3" data-depth="18"><div class="hst-mini-ring" style="--v:92"><b>۹۲٪</b></div><small>حضور این ماه</small></div>
			<div class="hst-kidfloat hst-k1" data-depth="34"><?php echo hst_avatar( 1 ); ?></div>
			<div class="hst-kidfloat hst-k2" data-depth="-28"><?php echo hst_avatar( 2 ); ?></div>
			<span class="hst-spark s1">✦</span><span class="hst-spark s2">✦</span><span class="hst-spark s3">●</span>
		</div>
	</div>
	<a class="hst-scroll" href="#roles" aria-label="ادامه"><span></span></a>
</section>

<!-- ===== نوار امکانات متحرک ===== -->
<div class="hst-marquee" aria-hidden="true"><div class="hst-marquee-in">
	<?php for ( $i = 0; $i < 2; $i++ ) { foreach ( array( '✅ حضور و غیاب', '📒 تکلیف', '📄 کاربرگ', '⭐ امتیاز و تشویق', '💬 پیام به والدین', '🏫 ارتباط با مدیر', '📅 تقویم مناسبتی', '🗓️ تقویم آموزشی', '🎒 پورتفولیو', '📈 روند پیشرفت', '📰 مقالات آموزشی' ) as $t ) { echo '<span>' . esc_html( $t ) . '</span>'; } } ?>
</div></div>

<?php if ( ! $logged ) : ?>
<!-- ===== نقش‌ها ===== -->
<section id="roles" class="hst-sec hst-wrap">
	<header class="hst-sec-head hst-center hst-reveal"><span class="hst-eyebrow">یک خانه، چهار در</span><h2>تو کدام همشاگردی هستی؟</h2><p>هر نقش پنل مخصوص خودش را دارد. روی کارتت بزن و بیشتر ببین.</p></header>
	<div class="hst-roles">
		<?php $i = 0; foreach ( $roles as $k => $r ) : ?>
			<a class="hst-role2 hst-reveal" style="--rc:<?php echo esc_attr( $r[1] ); ?>;--d:<?php echo (int) $i++; ?>" href="<?php echo hst_page( $slugs[ $k ] ); ?>" data-hst-tilt>
				<div class="hst-role2-art">
					<span class="hst-role2-badge"><svg viewBox="0 0 64 64" aria-hidden="true"><use href="#hst-r-<?php echo esc_attr( $icons[ $k ] ); ?>"/></svg></span>
					<span class="hst-bubble"><?php echo esc_html( 'سلام! من ' . $r[0] . ' هستم' ); ?></span>
				</div>
				<div class="hst-role2-body">
					<h3><?php echo esc_html( $r[0] ); ?></h3>
					<p><?php echo esc_html( $r[3] ); ?></p>
					<ul><?php foreach ( $r[4] as $b ) { echo '<li>' . esc_html( $b ) . '</li>'; } ?></ul>
					<span class="hst-role2-go">آشنایی با پنل <?php echo esc_html( $r[0] ); ?> <?php echo hst_icon( 'arrow' ); ?></span>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php endif; ?>

<?php
$extra = array();
for ( $n = 1; $n <= 3; $n++ ) {
	if ( get_theme_mod( "hst_x{$n}_on" ) && get_theme_mod( "hst_x{$n}_title" ) ) {
		$extra[] = $n;
	}
}
if ( $extra ) : ?>
<section class="hst-sec hst-wrap hst-extras">
	<?php foreach ( $extra as $j => $n ) : $img = (int) get_theme_mod( "hst_x{$n}_img" ); $btn = get_theme_mod( "hst_x{$n}_btn" ); $url = get_theme_mod( "hst_x{$n}_url" ); ?>
		<article class="hst-extra hst-reveal<?php echo $img ? '' : ' no-img'; ?>" style="--d:<?php echo (int) $j; ?>">
			<?php if ( $img ) : ?><div class="hst-extra-img"><?php echo wp_get_attachment_image( $img, 'large', false, array( 'loading' => 'lazy' ) ); ?></div><?php endif; ?>
			<div class="hst-extra-body">
				<h2><?php echo esc_html( get_theme_mod( "hst_x{$n}_title" ) ); ?></h2>
				<?php if ( get_theme_mod( "hst_x{$n}_text" ) ) : ?><p><?php echo nl2br( esc_html( get_theme_mod( "hst_x{$n}_text" ) ) ); ?></p><?php endif; ?>
				<?php if ( $btn && $url ) : ?><a class="hst-btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $btn ); ?> <?php echo hst_icon( 'arrow' ); ?></a><?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ( ! $logged ) : ?>
<!-- ===== مسیر شروع ===== -->
<section id="how" class="hst-sec hst-wrap">
	<div class="hst-path hst-reveal">
		<div class="hst-path-head"><span class="hst-eyebrow">مسیر شروع</span><h2>سه قدم تا اولین روز کلاس</h2></div>
		<ol class="hst-path-steps">
			<li><span class="hst-path-n">۱</span><b>ورود با موبایل</b><small>شماره‌ات را بزن و کد پیامکی را وارد کن.</small></li>
			<li><span class="hst-path-n">۲</span><b>انتخاب نقش</b><small>معلم، مدیر، والد یا دانش‌آموز؛ هرکدام پنل خودش.</small></li>
			<li><span class="hst-path-n">۳</span><b>شروع در پنل</b><small>معلم کلاس می‌سازد، مدیر مدرسه را، والد به فرزندش وصل می‌شود.</small></li>
		</ol>
		<a class="hst-btn hst-btn-lg" href="<?php echo hst_login_url(); ?>"><?php echo is_user_logged_in() ? 'رفتن به پنل من' : 'ورود یا ثبت‌نام'; ?> <?php echo hst_icon( 'arrow' ); ?></a>
	</div>
</section>

<!-- ===== گشت در پنل‌ها (تب‌های خودکار) ===== -->
<section id="tour" class="hst-sec hst-wrap">
	<header class="hst-sec-head hst-center hst-reveal"><span class="hst-eyebrow">گشت‌وگذار</span><h2>داخل همشاگردی چه خبر است؟</h2></header>
	<div class="hst-tour hst-reveal" data-hst-tour>
		<div class="hst-tabs" role="tablist">
			<?php $i = 0; foreach ( $roles as $k => $r ) : ?>
				<button role="tab" class="hst-tab<?php echo 0 === $i ? ' is-on' : ''; ?>" style="--rc:<?php echo esc_attr( $r[1] ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" data-i="<?php echo (int) $i++; ?>"><?php echo hst_role_icon( $k ); ?><span><?php echo esc_html( $r[0] ); ?></span><i class="hst-tab-bar"></i></button>
			<?php endforeach; ?>
		</div>
		<div class="hst-panes">
			<!-- معلم -->
			<div class="hst-pane is-on" role="tabpanel">
				<div class="hst-screen">
					<div class="hst-scr-head"><b>کلاس سوم الف</b><span class="hst-scr-chip">حضور و غیاب</span></div>
					<div class="hst-kids-grid">
						<?php foreach ( array( array( 'آرش', '#1565C0', 3 ), array( 'سارا', '#F57C00', 5 ), array( 'نیما', '#00A884', 2 ), array( 'هستی', '#E5487A', 4 ), array( 'رضا', '#7C5CFC', 1 ), array( 'یاسمن', '#FFB300', 3 ), array( 'امید', '#0B8BE0', 2 ), array( 'مهسا', '#F57C00', 4 ) ) as $j => $s ) : ?>
							<button class="hst-kidcard" data-hst-point style="--d:<?php echo (int) $j; ?>"><?php echo hst_avatar( $j ); ?><b><?php echo esc_html( $s[0] ); ?></b><span class="hst-pts"><?php echo (int) $s[2]; ?></span></button>
						<?php endforeach; ?>
					</div>
					<small class="hst-hint">👆 روی بچه‌ها بزن تا امتیاز بگیرند</small>
				</div>
				<div class="hst-pane-text"><h3>کلاس، با یک لمس</h3><p>حضور بزن، به رفتار خوب امتیاز بده و تکلیف و کاربرگ بفرست. والدین همان لحظه خبردار می‌شوند.</p><a class="hst-link" href="<?php echo hst_login_url( 'teacher' ); ?>">ورود معلم <?php echo hst_icon( 'arrow' ); ?></a></div>
			</div>
			<!-- والدین -->
			<div class="hst-pane" role="tabpanel">
				<div class="hst-screen">
					<div class="hst-scr-head"><b>روز سارا</b><span class="hst-scr-chip b">امروز</span></div>
					<div class="hst-chat">
						<div class="hst-msg in">سلام، سارا امروز در آزمایش علوم خیلی خوب همکاری کرد 🌱</div>
						<div class="hst-msg out">چه عالی! ممنون از شما 🌷</div>
						<div class="hst-msg in hst-typing"><i></i><i></i><i></i></div>
					</div>
					<div class="hst-daysum"><span>✅ حاضر</span><span>📒 ۱ تکلیف</span><span>⭐ ۲ نشان</span></div>
				</div>
				<div class="hst-pane-text"><h3>پنجره‌ای به کلاس</h3><p>خلاصه‌ی روزانه، نمودار پیشرفت ماهانه، گفتگو با معلم و گروه کلاس؛ بدون شلوغی و با ساعت سکوت.</p><a class="hst-link" href="<?php echo hst_login_url( 'parent' ); ?>">ورود والدین <?php echo hst_icon( 'arrow' ); ?></a></div>
			</div>
			<!-- دانش‌آموز -->
			<div class="hst-pane" role="tabpanel">
				<div class="hst-screen hst-screen-kid">
					<div class="hst-scr-head"><b>دنیای من</b><span class="hst-scr-chip g">۱۲ ستاره</span></div>
					<div class="hst-stickers"><span>⭐<small>همکاری</small></span><span>📚<small>کتاب‌خوان</small></span><span>🎨<small>خلاق</small></span><span>🚀<small>کنجکاو</small></span><span>🧩<small>حل مسئله</small></span><span>🌈<small>مهربان</small></span></div>
					<div class="hst-goal"><small>تا نشان بعدی</small><div class="hst-goal-bar"><i style="--v:70%"></i></div></div>
				</div>
				<div class="hst-pane-text"><h3>دنیای رنگی بچه‌ها</h3><p>ورود ساده با کارت کلاس، نشان و جایزه، کتابخانه، بازی‌های متناسب با پایه و پورتفولیوی کارهای خودش.</p><a class="hst-link" href="<?php echo hst_login_url( 'student' ); ?>">ورود دانش‌آموز <?php echo hst_icon( 'arrow' ); ?></a></div>
			</div>
			<!-- مدیر -->
			<div class="hst-pane" role="tabpanel">
				<div class="hst-screen">
					<div class="hst-scr-head"><b>مدرسه‌ی نمونه</b><span class="hst-scr-chip n">نمای کلی</span></div>
					<div class="hst-school">
						<?php foreach ( array( array( 'اول', 96 ), array( 'دوم', 91 ), array( 'سوم', 94 ), array( 'چهارم', 88 ), array( 'پنجم', 93 ), array( 'ششم', 90 ) ) as $j => $c ) : ?>
							<div class="hst-bar-row"><span>پایه‌ی <?php echo esc_html( $c[0] ); ?></span><div><i style="--v:<?php echo (int) $c[1]; ?>%;--d:<?php echo (int) $j; ?>"></i></div></div>
						<?php endforeach; ?>
						<small class="hst-hint">داده‌های نمایشی</small>
					</div>
				</div>
				<div class="hst-pane-text"><h3>کل مدرسه در یک نگاه</h3><p>ثبت مدرسه، معاونین و شرح وظایف، واگذاری و پیگیری کارها و نظارت فقط‌خواندنی بر همه‌ی کلاس‌ها.</p><a class="hst-link" href="<?php echo hst_login_url( 'school_admin' ); ?>">ورود مدیر <?php echo hst_icon( 'arrow' ); ?></a></div>
			</div>
		</div>
	</div>
</section>

<?php endif; ?>

<!-- ===== امکانات ===== -->
<section id="features" class="hst-sec hst-wrap">
	<header class="hst-sec-head hst-center hst-reveal"><span class="hst-eyebrow">جعبه‌ابزار کلاس</span><h2>هرچه یک روز مدرسه لازم دارد</h2></header>
	<div class="hst-feats">
		<?php
		$f = array(
			array( '✅', 'حضور و غیاب', 'ثبت سریع و خبر خودکار به خانواده', '#F57C00' ),
			array( '📒', 'تکلیف', 'عادی یا آنلاین، با ارسال عکس', '#1565C0' ),
			array( '📄', 'کاربرگ', 'بارگذاری و دسترسی برای کلاس', '#00A884' ),
			array( '⭐', 'تشویقی و انضباطی', 'امتیاز رفتاری با نشان‌های رنگی', '#FFB300' ),
			array( '💬', 'ارتباط با والدین و مدیر', 'پیام خصوصی و گروه کلاس', '#E5487A' ),
			array( '📅', 'تقویم مناسبتی و آموزشی', 'تعطیلات، مناسبت‌ها و بودجه‌بندی', '#7C5CFC' ),
			array( '🎒', 'پورتفولیو', 'کارنامه‌ی رشد هر دانش‌آموز', '#0B8BE0' ),
			array( '📈', 'روند پیشرفت', 'نمودار ماهانه بدون مقایسه', '#0F2F6B' ),
		);
		foreach ( $f as $i => $x ) : ?>
			<div class="hst-feat2 hst-reveal" style="--fc:<?php echo esc_attr( $x[3] ); ?>;--d:<?php echo (int) ( $i % 4 ); ?>" data-hst-tilt>
				<span class="hst-feat2-ico"><?php echo esc_html( $x[0] ); ?></span><b><?php echo esc_html( $x[1] ); ?></b><small><?php echo esc_html( $x[2] ); ?></small>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<!-- ===== پیشرفت ===== -->
<section class="hst-sec hst-wrap">
	<div class="hst-grow hst-reveal">
		<div class="hst-grow-text">
			<span class="hst-eyebrow">پورتفولیو و پیشرفت</span>
			<h2>هر قدم کوچک، <span class="hst-grad">دیده می‌شود</span></h2>
			<p>نمره‌ها، تکالیف و تشویق‌ها خودکار در پورتفولیوی بچه جمع می‌شوند و خانواده روند ماهانه را می‌بیند؛ بدون رتبه‌بندی و مقایسه با دیگران.</p>
		</div>
		<div class="hst-grow-art" aria-hidden="true">
			<svg viewBox="0 0 400 220" class="hst-line"><defs><linearGradient id="hstg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#F57C00" stop-opacity=".35"/><stop offset="1" stop-color="#F57C00" stop-opacity="0"/></linearGradient></defs>
				<path class="hst-area" d="M390 170 L320 140 L250 150 L180 105 L110 90 L40 45 L40 210 L390 210Z" fill="url(#hstg)"/>
				<path class="hst-stroke" d="M390 170 L320 140 L250 150 L180 105 L110 90 L40 45" fill="none" stroke="#F57C00" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
				<circle cx="40" cy="45" r="9" fill="#fff" stroke="#F57C00" stroke-width="5" class="hst-endpt"/></svg>
			<div class="hst-grow-kid"><?php echo hst_avatar( 3 ); ?></div>
			<span class="hst-grow-tag">⭐ نشان جدید!</span>
		</div>
	</div>
</section>

<?php
$posts = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 6, 'ignore_sticky_posts' => true ) );
$mcats = hst_mag_cats( 8 );
if ( $posts->have_posts() || $mcats ) : ?>
<!-- ===== مجله ===== -->
<section id="majalle" class="hst-sec hst-wrap">
	<header class="hst-sec-head hst-sec-head-row hst-reveal"><div><span class="hst-eyebrow">مجله‌ی همشاگردی</span><h2>آموزشی، تربیتی، روانشناسی</h2></div>
		<a class="hst-link" href="<?php echo esc_url( hst_blog_url() ); ?>">همه‌ی مطالب <?php echo hst_icon( 'arrow' ); ?></a></header>
	<?php if ( $mcats ) : ?>
	<nav class="hst-magcats hst-reveal" aria-label="دسته‌های مجله">
		<?php foreach ( $mcats as $i => $c ) : ?>
			<a class="hst-magcat t<?php echo (int) ( $i % 5 ); ?>" href="<?php echo esc_url( get_category_link( $c ) ); ?>"><b><?php echo esc_html( $c->name ); ?></b><small><?php echo esc_html( number_format_i18n( $c->count ) ); ?> مطلب</small></a>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>
	<?php if ( $posts->have_posts() ) : ?>
	<div class="hst-grid hst-g3"><?php while ( $posts->have_posts() ) { $posts->the_post(); get_template_part( 'parts/card' ); } wp_reset_postdata(); ?></div>
	<?php endif; ?>
	<?php if ( ! is_user_logged_in() ) : ?><p class="hst-mag-note">🔒 مطالب تخصصی هر پایه، برای معلمان و خانواده‌های عضو کامل نمایش داده می‌شود. <a href="<?php echo esc_url( wp_login_url( home_url( '/' ) ) ); ?>">ثبت‌نام رایگان</a></p><?php endif; ?>
</section>
<?php endif; ?>

<!-- ===== حریم خصوصی ===== -->
<section class="hst-sec hst-wrap">
	<div class="hst-safe hst-reveal">
		<span class="hst-safe-ico"><?php echo hst_icon( 'shield' ); ?></span>
		<div><h3>اطلاعات بچه‌ها، امانت است</h3><p>هر کس فقط چیزی را می‌بیند که به او مربوط است: والد فقط فرزند خودش، معلم فقط کلاس خودش. مدرسه و خانواده تعیین می‌کنند چه چیزی دیده شود.</p></div>
	</div>
</section>

<section id="faq" class="hst-sec hst-wrap hst-faq-wrap">
	<header class="hst-sec-head hst-reveal"><span class="hst-eyebrow">سؤالات</span><h2>سؤالات پرتکرار</h2><p>جوابت اینجا نیست؟ با ما در تماس باش.</p></header>
	<div class="hst-faq">
		<?php
		$q = array(
			array( 'بدون مدرسه هم می‌شود از همشاگردی استفاده کرد؟', 'بله. معلم بعد از ورود و انتخاب نقش «معلم»، در پنل خودش کلاس می‌سازد و خانواده‌ها را دعوت می‌کند؛ اگر بعداً مدرسه عضو شد، کلاس به آن وصل می‌شود.' ),
			array( 'والدین چطور به فرزندشان وصل می‌شوند؟', 'با کد دعوت کلاس یا شماره موبایلی که معلم ثبت کرده؛ نسبت (پدر، مادر، سرپرست) هم مشخص می‌شود.' ),
			array( 'دانش‌آموزان کوچک چطور وارد می‌شوند؟', 'با کارت کلاس: یک کد QR و رمز ۴ رقمی، بدون نیاز به موبایل.' ),
			array( 'اپ موبایل دارد؟', 'پنل روی گوشی نصب می‌شود و نسخه‌ی اندروید هم در دسترس است.' ),
		);
		foreach ( $q as $x ) : ?>
			<details class="hst-card"><summary><?php echo esc_html( $x[0] ); ?><span aria-hidden="true">+</span></summary><p><?php echo esc_html( $x[1] ); ?></p></details>
		<?php endforeach; ?>
	</div>
</section>

<?php if ( ! $logged ) : ?>
<section class="hst-sec hst-wrap">
	<div class="hst-cta3 hst-reveal">
		<span class="hst-cta3-kid" aria-hidden="true"><?php echo hst_avatar( 4 ); ?></span>
		<div class="hst-cta3-text"><h2><?php echo esc_html( hst_mod( 'hst_cta_title', 'بیا با هم شروع کنیم' ) ); ?></h2><p><?php echo esc_html( hst_mod( 'hst_cta_text', 'وارد شو، نقشت را انتخاب کن و پنل مخصوص خودت را ببین.' ) ); ?></p></div>
		<a class="hst-btn hst-btn-white hst-btn-lg" href="<?php echo is_user_logged_in() ? esc_url( home_url( '/panel/' ) ) : hst_login_url(); ?>"><?php echo is_user_logged_in() ? 'رفتن به پنل من' : 'ورود یا ثبت‌نام'; ?> <?php echo hst_icon( 'arrow' ); ?></a>
	</div>
</section>
<?php endif; ?>
<?php get_footer(); ?>
