<?php
/**
 * صفحه‌ی «نوشته‌ها ← انتشار مقاله»: ورود متن/TXT/HTML/ZIP، تنظیمات سئو در همان فرم،
 * بررسی کیفیت و انتشار.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSAR_Admin {

	const SLUG = 'hsar-publish';
	private static $report = null;

	public static function init() {
		add_action( 'admin_menu', function () {
			add_posts_page( 'انتشار مقاله', 'انتشار مقاله', 'edit_posts', self::SLUG, array( __CLASS__, 'page' ) );
		} );
		add_action( 'admin_init', array( __CLASS__, 'handle' ) );
		add_action( 'admin_enqueue_scripts', function ( $hook ) {
			if ( false !== strpos( $hook, self::SLUG ) ) {
				wp_enqueue_style( 'hsar-admin', HSAR_URL . 'assets/admin.css', array(), HSAR_VERSION );
				wp_enqueue_script( 'hsar-admin', HSAR_URL . 'assets/admin.js', array(), HSAR_VERSION, true );
			}
		} );
		// چک‌باکس «فهرست مطالب» در ویرایشگر نوشته
		add_action( 'add_meta_boxes_post', function () {
			add_meta_box( 'hsar-toc', 'فهرست مطالب', function ( $p ) {
				wp_nonce_field( 'hsar_toc', 'hsar_toc_n' );
				echo '<label><input type="checkbox" name="hsar_no_toc" value="1" ' . checked( get_post_meta( $p->ID, '_hsar_no_toc', true ), '1', false ) . '> برای این مقاله فهرست مطالب نمایش داده نشود</label>';
			}, 'post', 'side', 'low' );
		} );
		add_action( 'save_post_post', function ( $id ) {
			if ( isset( $_POST['hsar_toc_n'] ) && wp_verify_nonce( sanitize_key( $_POST['hsar_toc_n'] ), 'hsar_toc' ) && current_user_can( 'edit_post', $id ) ) {
				empty( $_POST['hsar_no_toc'] ) ? delete_post_meta( $id, '_hsar_no_toc' ) : update_post_meta( $id, '_hsar_no_toc', '1' );
			}
		} );
	}

	private static function opt() {
		return wp_parse_args( (array) get_option( 'hsar_opt', array() ), array( 'webp' => 1, 'quality' => 82, 'max_w' => 1600, 'min_words' => 300, 'block_bad' => 1 ) );
	}

	/* ------------------------------ پردازش ------------------------------ */

	public static function handle() {
		if ( empty( $_POST['hsar_action'] ) || ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] ) {
			return;
		}
		check_admin_referer( 'hsar_publish' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'اجازه‌ی این کار را ندارید.' );
		}
		@set_time_limit( 300 ); // phpcs:ignore
		$in     = wp_unslash( $_POST );
		$action = sanitize_key( $in['hsar_action'] );
		$opt    = self::opt();
		$opt['webp']      = ! empty( $in['webp'] ) ? 1 : 0;
		$opt['quality']   = max( 40, min( 100, absint( $in['quality'] ?? 82 ) ) );
		$opt['max_w']     = absint( $in['max_w'] ?? 1600 );
		$opt['min_words'] = absint( $in['min_words'] ?? 300 );
		$opt['block_bad'] = ! empty( $in['block_bad'] ) ? 1 : 0;
		update_option( 'hsar_opt', $opt, false );

		$form = self::form_values( $in );
		$src  = self::collect( $in, $form );
		$res  = array( 'errors' => $src['errors'], 'items' => array(), 'saved' => 'publish' === $action );

		foreach ( $src['articles'] as $a ) {
			// فیلدهای فرم: برای مقاله‌ی تکی همه‌چیز؛ برای چند مقاله فقط وقتی خود فایل مقدار نداشته باشد
			foreach ( array( 'title', 'seo_title', 'description', 'keyword', 'slug', 'image', 'excerpt' ) as $k ) {
				if ( '' !== $form[ $k ] && ( 1 === count( $src['articles'] ) || '' === $a[ $k ] ) && ! in_array( $k, count( $src['articles'] ) > 1 ? array( 'title', 'slug' ) : array(), true ) ) {
					$a[ $k ] = $form[ $k ];
				}
			}
			$chk  = HSAR_SEO::check( $a, $opt );
			$item = array( 'title' => $a['title'], 'file' => $a['_file'] ?? '', 'check' => $chk, 'id' => 0, 'status' => '', 'img_err' => array() );
			if ( 'publish' === $action ) {
				$r = self::save_post( $a, $form, $opt, $chk, $src );
				$item = array_merge( $item, $r );
			}
			$res['items'][] = $item;
		}
		if ( ! empty( $src['zip'] ) ) {
			$src['zip']->close();
		}
		self::$report = $res;
		set_transient( 'hsar_last_form_' . get_current_user_id(), $form, HOUR_IN_SECONDS );
	}

	private static function form_values( $in ) {
		$t = function ( $k ) use ( $in ) {
			return sanitize_text_field( $in[ $k ] ?? '' );
		};
		return array(
			'title'       => $t( 'title' ),
			'seo_title'   => $t( 'seo_title' ),
			'description' => sanitize_textarea_field( $in['description'] ?? '' ),
			'keyword'     => $t( 'keyword' ),
			'slug'        => sanitize_title( $in['slug'] ?? '' ),
			'image'       => esc_url_raw( $in['image'] ?? '' ),
			'excerpt'     => sanitize_textarea_field( $in['excerpt'] ?? '' ),
			'canonical'   => esc_url_raw( $in['canonical'] ?? '' ),
			'noindex'     => ! empty( $in['noindex'] ),
			'no_toc'      => ! empty( $in['no_toc'] ),
			'cats'        => array_map( 'absint', (array) ( $in['cats'] ?? array() ) ),
			'tags'        => $t( 'tags' ),
			'grades'      => array_values( array_intersect( (array) ( $in['grades'] ?? array() ), HSAR_Access::grades() ) ),
			'aud'         => array_values( array_intersect( (array) ( $in['aud'] ?? array() ), array_keys( HSAR_Access::audiences() ) ) ),
			'status'      => in_array( $in['status'] ?? '', array( 'draft', 'pending', 'publish', 'future' ), true ) ? $in['status'] : 'draft',
			'date'        => $t( 'date' ),
			'source'      => sanitize_key( $in['source'] ?? 'paste' ),
		);
	}

	/** @return array{articles:array,errors:array,zip:?ZipArchive,zip_images:array} */
	private static function collect( $in, $form ) {
		$out = array( 'articles' => array(), 'errors' => array(), 'zip' => null, 'zip_images' => array() );
		if ( 'file' === $form['source'] ) {
			if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore
				$out['errors'][] = 'فایلی انتخاب نشده است.';
				return $out;
			}
			$name = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ?? '' ) ); // phpcs:ignore
			$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			$tmp  = $_FILES['file']['tmp_name']; // phpcs:ignore
			if ( 'zip' === $ext ) {
				$z = HSAR_Parser::zip( $tmp );
				$out['errors']     = $z['errors'];
				$out['articles']   = $z['articles'];
				$out['zip']        = $z['_zip'] ?? null;
				$out['zip_images'] = $z['images'];
				if ( ! $z['articles'] && ! $z['errors'] ) {
					$out['errors'][] = 'داخل ZIP فایل html یا txt پیدا نشد.';
				}
			} elseif ( in_array( $ext, array( 'txt', 'md', 'html', 'htm' ), true ) ) {
				$out['articles'][] = HSAR_Parser::from_string( file_get_contents( $tmp ), in_array( $ext, array( 'txt', 'md' ), true ) ? 'txt' : 'html' ) + array( '_file' => $name ); // phpcs:ignore
			} else {
				$out['errors'][] = 'فرمت فایل پشتیبانی نمی‌شود (فقط txt، html، zip).';
			}
		} else {
			$raw = (string) ( $in['body'] ?? '' );
			if ( '' === trim( $raw ) ) {
				$out['errors'][] = 'متن مقاله خالی است.';
			} else {
				$out['articles'][] = HSAR_Parser::from_string( $raw );
			}
		}
		return $out;
	}

	private static function save_post( $a, $form, $opt, $chk, $src ) {
		$status = $form['status'];
		$forced = false;
		if ( $chk['blocking'] && $opt['block_bad'] && in_array( $status, array( 'publish', 'future' ), true ) ) {
			$status = 'draft';
			$forced = true;
		}
		if ( in_array( $status, array( 'publish', 'future' ), true ) && ! current_user_can( 'publish_posts' ) ) {
			$status = 'pending';
		}
		$post = array(
			'post_type'    => 'post',
			'post_title'   => wp_strip_all_tags( $a['title'] ),
			'post_content' => '',
			'post_status'  => 'draft',
			'post_excerpt' => $a['excerpt'],
			'post_author'  => get_current_user_id(),
		);
		if ( $a['slug'] ) {
			$post['post_name'] = sanitize_title( $a['slug'] );
		}
		$id = wp_insert_post( $post, true );
		if ( is_wp_error( $id ) ) {
			return array( 'status' => 'error', 'img_err' => array( $id->get_error_message() ) );
		}
		$base = ! empty( $a['_file'] ) ? dirname( $a['_file'] ) : '';
		$img  = HSAR_Images::process( $a['content'], $id, $opt + array( 'alt' => $a['keyword'] ?: $a['title'] ), $src['zip'], $src['zip_images'], '.' === $base ? '' : $base );

		// تصویر شاخص: آدرس داده‌شده، وگرنه اولین تصویر متن
		$thumb = 0;
		if ( $a['image'] ) {
			$t = preg_match( '#^https?://#', $a['image'] ) ? HSAR_Images::from_url( $a['image'], $id, $a['title'], $opt ) : HSAR_Images::from_zip( $a['image'], $base, $src['zip'], $src['zip_images'], $id, $a['title'], $opt );
			is_wp_error( $t ) ? $img['errors'][] = 'تصویر شاخص: ' . $t->get_error_message() : $thumb = $t;
		}
		if ( ! $thumb && $img['ids'] ) {
			$thumb = $img['ids'][0];
		}
		if ( $thumb ) {
			set_post_thumbnail( $id, $thumb );
		}

		// دسته‌ها: فرم + نام‌های داخل فایل (ساخته می‌شوند اگر نباشند)
		$cats = array_filter( $form['cats'] );
		foreach ( preg_split( '/[,،]/u', (string) $a['category'] ) as $c ) {
			$c = trim( $c );
			if ( '' === $c ) {
				continue;
			}
			$term = term_exists( $c, 'category' );
			if ( ! $term && current_user_can( 'manage_categories' ) ) {
				$term = wp_insert_term( $c, 'category' );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$cats[] = (int) $term['term_id'];
			}
		}
		$tags = array_filter( array_map( 'trim', preg_split( '/[,،]/u', $form['tags'] . ',' . $a['tags'] ) ) );

		$up = array(
			'ID'           => $id,
			'post_content' => $img['html'],
			'post_status'  => $status,
		);
		if ( 'future' === $status && $form['date'] ) {
			$ts = strtotime( $form['date'] );
			if ( $ts ) {
				$up['post_date']     = wp_date( 'Y-m-d H:i:s', $ts );
				$up['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}
		wp_update_post( $up );
		if ( $cats ) {
			wp_set_post_categories( $id, array_unique( $cats ) );
		}
		if ( $tags ) {
			wp_set_post_tags( $id, $tags );
		}
		// پایه و مخاطب: از فرم، یا از سرصفحه‌ی فایل (grade: اول، دوم / audience: معلمان)
		$grades = $form['grades'];
		foreach ( array_filter( array_map( 'trim', preg_split( '/[,،]/u', (string) ( $a['grade'] ?? '' ) ) ) ) as $g ) {
			if ( in_array( $g, HSAR_Access::grades(), true ) ) {
				$grades[] = $g;
			}
		}
		$aud  = $form['aud'];
		$amap = array_flip( HSAR_Access::audiences() );
		foreach ( array_filter( array_map( 'trim', preg_split( '/[,،]/u', (string) ( $a['audience'] ?? '' ) ) ) ) as $x ) {
			$aud[] = $amap[ $x ] ?? $x;
		}
		if ( $grades ) {
			wp_set_object_terms( $id, array_values( array_unique( $grades ) ), HSAR_Access::TAX_GRADE );
		}
		$aud = array_values( array_intersect( array_unique( $aud ), array_keys( HSAR_Access::audiences() ) ) );
		if ( $aud ) {
			wp_set_object_terms( $id, $aud, HSAR_Access::TAX_AUD );
		}
		HSAR_SEO::save( $id, $a + array( 'noindex' => $form['noindex'], 'canonical' => $form['canonical'] ) );
		if ( $form['no_toc'] ) {
			update_post_meta( $id, '_hsar_no_toc', '1' );
		}
		update_post_meta( $id, '_hsar_score', $chk['score'] );
		return array( 'id' => $id, 'status' => get_post_status( $id ), 'forced' => $forced, 'img_err' => $img['errors'], 'imgs' => count( $img['ids'] ) );
	}

	/* ------------------------------ صفحه ------------------------------ */

	public static function page() {
		$o = self::opt();
		$f = self::$report ? (array) get_transient( 'hsar_last_form_' . get_current_user_id() ) : array();
		$v = function ( $k, $d = '' ) use ( $f ) {
			return isset( $f[ $k ] ) ? $f[ $k ] : $d;
		};
		$labels = array( 'draft' => 'پیش‌نویس', 'pending' => 'در انتظار بررسی', 'publish' => 'منتشر شده', 'future' => 'زمان‌بندی‌شده', 'error' => 'خطا' );
		?>
		<div class="wrap hsar" dir="rtl">
			<h1>انتشار مقاله</h1>
			<p class="description">متن را بچسبانید یا فایل TXT، HTML یا ZIP بارگذاری کنید. تصاویر از آدرسشان دانلود و در کتابخانه‌ی رسانه ذخیره می‌شوند. موتور سئو: <b><?php echo esc_html( array( 'rankmath' => 'Rank Math', 'yoast' => 'Yoast', 'own' => 'داخلی همین افزونه' )[ HSAR_SEO::engine() ] ); ?></b></p>

			<?php if ( self::$report ) : $r = self::$report; ?>
				<div class="hsar-report">
					<?php foreach ( $r['errors'] as $e ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( $e ); ?></p></div><?php endforeach; ?>
					<?php foreach ( $r['items'] as $it ) : $c = $it['check']; ?>
						<div class="hsar-card">
							<div class="hsar-card-head">
								<span class="hsar-score s<?php echo (int) ( $c['score'] >= 75 ? 3 : ( $c['score'] >= 50 ? 2 : 1 ) ); ?>"><?php echo (int) $c['score']; ?></span>
								<div><b><?php echo esc_html( $it['title'] ); ?></b><?php if ( $it['file'] ) : ?><small> — <?php echo esc_html( $it['file'] ); ?></small><?php endif; ?>
								<?php if ( $r['saved'] && $it['id'] ) : ?><br><span class="hsar-st"><?php echo esc_html( $labels[ $it['status'] ] ?? $it['status'] ); ?></span>
									<a href="<?php echo esc_url( get_edit_post_link( $it['id'] ) ); ?>">ویرایش</a> · <a href="<?php echo esc_url( get_permalink( $it['id'] ) ); ?>" target="_blank">مشاهده</a> · <?php echo (int) ( $it['imgs'] ?? 0 ); ?> تصویر ذخیره شد
									<?php if ( ! empty( $it['forced'] ) ) : ?><br><span class="hsar-bad">به‌خاطر خطای کیفیت، به‌جای انتشار پیش‌نویس شد.</span><?php endif; ?>
								<?php endif; ?></div>
							</div>
							<ul class="hsar-checks"><?php foreach ( $c['items'] as $i ) : ?><li class="<?php echo esc_attr( $i[0] ); ?>"><?php echo esc_html( $i[1] ); ?></li><?php endforeach; ?></ul>
							<?php foreach ( (array) $it['img_err'] as $e ) : ?><p class="hsar-bad">⚠ <?php echo esc_html( $e ); ?></p><?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data" class="hsar-form">
				<?php wp_nonce_field( 'hsar_publish' ); ?>
				<div class="hsar-cols">
					<div class="hsar-main">
						<div class="hsar-box">
							<div class="hsar-tabs" role="tablist">
								<label><input type="radio" name="source" value="paste" <?php checked( 'file' !== $v( 'source' ) ); ?>> چسباندن متن یا HTML</label>
								<label><input type="radio" name="source" value="file" <?php checked( 'file', $v( 'source' ) ); ?>> بارگذاری فایل (TXT / HTML / ZIP)</label>
							</div>
							<div data-src="paste">
								<textarea name="body" rows="16" class="large-text" placeholder="# عنوان مقاله&#10;&#10;پاراگراف اول…&#10;&#10;## تیتر بخش&#10;&#10;![متن جایگزین](https://example.com/photo.jpg)"></textarea>
								<p class="description">پشتیبانی: HTML، یا متن ساده با <code>#</code> برای عنوان، <code>##</code> و <code>###</code> برای تیترها، <code>-</code> برای فهرست، <code>**پررنگ**</code> و <code>![alt](آدرس تصویر)</code>. سرصفحه‌ی اختیاری <code>---</code> با title، description، keyword، slug، category، tags، image.</p>
							</div>
							<div data-src="file">
								<input type="file" name="file" accept=".txt,.md,.html,.htm,.zip">
								<p class="description">در ZIP هر فایل html/txt یک مقاله است و تصاویری که با آدرس نسبی در متن آمده‌اند از داخل ZIP برداشته می‌شوند. برای چند مقاله، عنوان و نامک از خود فایل‌ها خوانده می‌شود و بقیه‌ی فیلدهای سمت چپ مقدار پیش‌فرض هستند.</p>
							</div>
						</div>

						<div class="hsar-box">
							<h2>سئو</h2>
							<div class="hsar-serp" aria-live="polite"><span class="u"><?php echo esc_html( home_url( '/' ) ); ?><i data-p="slug"></i></span><span class="t" data-p="title"></span><span class="d" data-p="desc"></span></div>
							<table class="form-table">
								<tr><th><label for="hsar-title">عنوان مقاله</label></th><td><input id="hsar-title" name="title" class="large-text" value="<?php echo esc_attr( $v( 'title' ) ); ?>" placeholder="خالی = از خود متن"></td></tr>
								<tr><th><label for="hsar-seo">عنوان سئو</label></th><td><input id="hsar-seo" name="seo_title" class="large-text" data-count="65" value="<?php echo esc_attr( $v( 'seo_title' ) ); ?>" placeholder="خالی = همان عنوان مقاله"><span class="hsar-count"></span></td></tr>
								<tr><th><label for="hsar-desc">توضیح متا</label></th><td><textarea id="hsar-desc" name="description" rows="3" class="large-text" data-count="160"><?php echo esc_textarea( $v( 'description' ) ); ?></textarea><span class="hsar-count"></span></td></tr>
								<tr><th><label for="hsar-kw">کلمه کلیدی کانونی</label></th><td><input id="hsar-kw" name="keyword" class="regular-text" value="<?php echo esc_attr( $v( 'keyword' ) ); ?>"></td></tr>
								<tr><th><label for="hsar-slug">نامک (آدرس)</label></th><td><input id="hsar-slug" name="slug" class="regular-text" value="<?php echo esc_attr( $v( 'slug' ) ); ?>" placeholder="خالی = از عنوان"></td></tr>
								<tr><th><label for="hsar-ex">خلاصه</label></th><td><textarea id="hsar-ex" name="excerpt" rows="2" class="large-text"><?php echo esc_textarea( $v( 'excerpt' ) ); ?></textarea></td></tr>
								<tr><th><label for="hsar-can">آدرس canonical</label></th><td><input id="hsar-can" name="canonical" dir="ltr" class="large-text" value="<?php echo esc_attr( $v( 'canonical' ) ); ?>" placeholder="فقط اگر مقاله قبلاً جای دیگری منتشر شده"></td></tr>
								<tr><th>نمایه‌سازی</th><td><label><input type="checkbox" name="noindex" value="1" <?php checked( $v( 'noindex' ) ); ?>> در نتایج گوگل نمایش داده نشود (noindex)</label></td></tr>
							</table>
						</div>
					</div>

					<div class="hsar-side">
						<div class="hsar-box">
							<h2>انتشار</h2>
							<p><label>وضعیت<br><select name="status" data-status>
								<?php foreach ( array( 'draft' => 'پیش‌نویس', 'pending' => 'در انتظار بررسی', 'publish' => 'انتشار فوری', 'future' => 'زمان‌بندی' ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'status', 'draft' ), $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
							</select></label></p>
							<p data-future><label>زمان انتشار<br><input type="datetime-local" name="date" value="<?php echo esc_attr( $v( 'date' ) ); ?>"></label></p>
							<p><label><input type="checkbox" name="block_bad" value="1" <?php checked( $o['block_bad'] ); ?>> اگر بررسی کیفیت خطای جدی داشت، پیش‌نویس شود</label></p>
							<p><label><input type="checkbox" name="no_toc" value="1" <?php checked( $v( 'no_toc' ) ); ?>> بدون فهرست مطالب</label></p>
							<div class="hsar-actions">
								<button class="button" name="hsar_action" value="check">فقط بررسی کیفیت</button>
								<button class="button button-primary" name="hsar_action" value="publish">ذخیره و انتشار</button>
							</div>
						</div>
						<div class="hsar-box">
							<h2>دسته و برچسب</h2>
							<div class="hsar-cats"><?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $c ) : ?><label><input type="checkbox" name="cats[]" value="<?php echo (int) $c->term_id; ?>" <?php checked( in_array( $c->term_id, (array) $v( 'cats', array() ), true ) ); ?>> <?php echo esc_html( $c->name ); ?></label><?php endforeach; ?></div>
							<p><label>برچسب‌ها (با کاما)<br><input name="tags" class="widefat" value="<?php echo esc_attr( $v( 'tags' ) ); ?>"></label></p>
						</div>
						<div class="hsar-box">
							<h2>چه کسی بخواند؟</h2>
							<p class="description">بدون انتخاب = عمومی (همه، حتی بازدیدکننده). مطلب «والدین» یا «معلمان» یا پایه‌دار فقط برای کاربران واردشده‌ی همان گروه کامل نمایش داده می‌شود.</p>
							<div class="hsar-cats"><?php foreach ( HSAR_Access::audiences() as $k => $n ) : ?><label><input type="checkbox" name="aud[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $v( 'aud', array() ), true ) ); ?>> <?php echo esc_html( $n ); ?></label><?php endforeach; ?></div>
							<h3>پایه</h3>
							<div class="hsar-cats"><?php foreach ( HSAR_Access::grades() as $g ) : ?><label><input type="checkbox" name="grades[]" value="<?php echo esc_attr( $g ); ?>" <?php checked( in_array( $g, (array) $v( 'grades', array() ), true ) ); ?>> <?php echo esc_html( $g ); ?></label><?php endforeach; ?></div>
						</div>
						<div class="hsar-box">
							<h2>تصاویر</h2>
							<p><label>آدرس تصویر شاخص<br><input name="image" dir="ltr" class="widefat" value="<?php echo esc_attr( $v( 'image' ) ); ?>" placeholder="https://… (خالی = اولین تصویر متن)"></label></p>
							<p><label><input type="checkbox" name="webp" value="1" <?php checked( $o['webp'] ); ?>> تبدیل به WebP</label></p>
							<p><label>کیفیت <input type="number" name="quality" min="40" max="100" value="<?php echo (int) $o['quality']; ?>" style="width:70px"></label>
								<label>حداکثر عرض <input type="number" name="max_w" min="0" step="100" value="<?php echo (int) $o['max_w']; ?>" style="width:80px"> px</label></p>
							<p><label>حداقل کلمه <input type="number" name="min_words" min="0" step="50" value="<?php echo (int) $o['min_words']; ?>" style="width:80px"></label></p>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}
}
