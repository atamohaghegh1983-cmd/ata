<?php
/**
 * بودجه‌بندی کلاس: برنامه‌ریزی درس‌ها در طول سال تحصیلی و پیگیری پیشرفت.
 * داده‌ها در متای کلاس (_hs_budget) نگهداری می‌شود: [ [subject, planned, done], ... ]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Budget {

	const META = '_hs_budget';

	public static function init() {
		add_shortcode( 'hamshagerdi_budget', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_hs_budget_save', array( __CLASS__, 'ajax_save' ) );
	}

	/** دروس پیشنهادی دوره ابتدایی */
	public static function default_subjects() {
		return array( 'فارسی', 'ریاضی', 'علوم تجربی', 'مطالعات اجتماعی', 'هدیه‌های آسمان', 'قرآن', 'هنر', 'تربیت‌بدنی' );
	}

	public static function get( $class_id ) {
		$rows = get_post_meta( $class_id, self::META, true );
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			if ( empty( $r['subject'] ) ) {
				continue;
			}
			$planned = max( 0, (int) $r['planned'] );
			$out[]   = array(
				'subject' => (string) $r['subject'],
				'planned' => $planned,
				'done'    => min( $planned, max( 0, (int) $r['done'] ) ),
			);
		}
		return $out;
	}

	/** کسر گذشته از سال تحصیلی (۲۳ شهریور تا ۳۱ خرداد) بین ۰ و ۱ */
	public static function elapsed_fraction() {
		$now   = strtotime( current_time( 'Y-m-d' ) . ' 12:00:00 UTC' );
		$y     = (int) current_time( 'Y' );
		$start = strtotime( $y . '-09-23 12:00:00 UTC' );
		if ( $now < $start ) {
			$start = strtotime( ( $y - 1 ) . '-09-23 12:00:00 UTC' );
		}
		$end = strtotime( ( (int) gmdate( 'Y', $start ) + 1 ) . '-06-21 12:00:00 UTC' );
		return max( 0, min( 1, ( $now - $start ) / max( 1, $end - $start ) ) );
	}

	private static function status_pill( $pct, $expected ) {
		$diff = $pct - $expected;
		if ( $diff >= 5 ) {
			return '<span class="hs-pill hs-pill-ok">جلوتر از برنامه</span>';
		}
		if ( $diff <= -10 ) {
			return '<span class="hs-pill hs-pill-warn">عقب‌تر از برنامه</span>';
		}
		return '<span class="hs-pill hs-pill-info">مطابق برنامه</span>';
	}

	/** کارت داشبورد معلم */
	public static function dashboard_card( $class_ids ) {
		$exp  = (int) round( self::elapsed_fraction() * 100 );
		$html = '<section class="hs-card hs-dash-card"><div class="hs-card-head"><h3>📐 بودجه‌بندی کلاس</h3><a href="' . esc_url( HS_Shell::url( 'budget' ) ) . '">مدیریت ←</a></div>';
		$shown = 0;
		foreach ( array_slice( (array) $class_ids, 0, 6 ) as $cid ) {
			$rows = self::get( $cid );
			if ( ! $rows || $shown >= 2 ) {
				continue;
			}
			$shown++;
			$planned = array_sum( wp_list_pluck( $rows, 'planned' ) );
			$done    = array_sum( wp_list_pluck( $rows, 'done' ) );
			$total   = $planned ? (int) round( $done / $planned * 100 ) : 0;
			$html   .= '<div class="hs-budget-class"><div class="hs-budget-title"><span>' . esc_html( get_the_title( $cid ) ) . '</span>' . self::status_pill( $total, $exp ) . '</div>';
			foreach ( array_slice( $rows, 0, 4 ) as $r ) {
				$p     = $r['planned'] ? (int) round( $r['done'] / $r['planned'] * 100 ) : 0;
				$html .= '<div class="hs-bg-row"><div class="hs-bg-top"><span>' . esc_html( $r['subject'] ) . '</span><b>' . esc_html( HS_Util::fa_digits( $p ) ) . '٪</b></div>'
					. '<div class="hs-bg-track"><i class="hs-bg-fill" style="width:' . $p . '%"></i><i class="hs-bg-mark" style="right:' . $exp . '%" title="پیشرفت مورد انتظار"></i></div></div>';
			}
			$html .= '</div>';
		}
		if ( ! $shown ) {
			$html .= '<div class="hs-empty-state">هنوز بودجه‌بندی ثبت نشده است. تعداد درس‌ها یا جلسات برنامه‌ریزی‌شده هر درس را وارد کنید تا پیشرفت سال تحصیلی را ببینید.</div>'
				. '<p style="text-align:center;margin:.8rem 0 0"><a class="hs-btn hs-btn-sm hs-btn-primary" href="' . esc_url( HS_Shell::url( 'budget' ) ) . '">شروع بودجه‌بندی</a></p>';
		} else {
			$html .= '<p class="hs-hint">خط نارنجی = پیشرفت مورد انتظار تا امروز (' . esc_html( HS_Util::fa_digits( $exp ) ) . '٪ سال تحصیلی)</p>';
		}
		return $html . '</section>';
	}

	private static function teacher_classes() {
		$ids = get_posts( array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 200, 'no_found_rows' => true ) );
		$out = array();
		foreach ( $ids as $id ) {
			if ( HS_Roles::can_manage_class( $id ) ) {
				$out[ $id ] = get_the_title( $id );
			}
		}
		return $out;
	}

	/** صفحه مدیریت بودجه‌بندی */
	public static function render() {
		if ( ! is_user_logged_in() || ! HS_Roles::is_teacher_or_admin() ) {
			return '<div class="hs-alert hs-alert-warning">این بخش مخصوص معلمان و مدیران است.</div>';
		}
		$classes = self::teacher_classes();
		if ( ! $classes ) {
			return '<div class="hs-card"><p>ابتدا یک کلاس بسازید.</p><a class="hs-btn hs-btn-primary" href="' . esc_url( HS_Shell::url( 'classes' ) ) . '">ساخت کلاس</a></div>';
		}
		$cid = isset( $_GET['class_id'] ) ? absint( $_GET['class_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $classes[ $cid ] ) ) {
			$cid = (int) key( $classes );
		}
		$rows = self::get( $cid );
		if ( ! $rows ) {
			foreach ( self::default_subjects() as $s ) {
				$rows[] = array( 'subject' => $s, 'planned' => 0, 'done' => 0 );
			}
		}
		wp_enqueue_script( 'hs-budget', HS_CLASS_URL . 'assets/js/budget.js', array( 'jquery' ), HS_CLASS_VERSION, true );
		wp_localize_script( 'hs-budget', 'hsBudget', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hs_budget' ),
			'classId' => $cid,
			'defaults' => self::default_subjects(),
		) );

		ob_start();
		?>
		<div class="hs-panel-classes hs-budget-page">
			<div class="hs-panel-header">
				<div>
					<h2><?php echo esc_html( HS_Hubs::title( 'budget', '📐 بودجه‌بندی کلاس' ) ); ?></h2>
					<p class="hs-muted">برای هر درس، تعداد درس‌ها یا جلسات برنامه‌ریزی‌شده در سال و تعداد انجام‌شده را وارد کنید.</p>
				</div>
				<form method="get" class="hs-form-row">
					<select name="class_id" onchange="this.form.submit()" aria-label="انتخاب کلاس">
						<?php foreach ( $classes as $id => $title ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $cid, $id ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>
			<div class="hs-card">
				<div class="hs-table-wrap">
					<table class="hs-table hs-bg-table" id="hs-bg-table">
						<thead><tr><th>درس</th><th>برنامه‌ریزی‌شده</th><th>انجام‌شده</th><th>پیشرفت</th><th></th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $r ) : ?>
							<tr>
								<td><input type="text" class="hs-bg-subject" maxlength="40" value="<?php echo esc_attr( $r['subject'] ); ?>"></td>
								<td><input type="number" class="hs-bg-planned" min="0" max="999" value="<?php echo esc_attr( $r['planned'] ); ?>"></td>
								<td><input type="number" class="hs-bg-done" min="0" max="999" value="<?php echo esc_attr( $r['done'] ); ?>"></td>
								<td class="hs-bg-pct">—</td>
								<td><button type="button" class="hs-btn hs-btn-sm hs-btn-ghost hs-bg-del" aria-label="حذف ردیف">✕</button></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="hs-bg-actions">
					<button type="button" class="hs-btn hs-btn-ghost" id="hs-bg-add">+ افزودن درس</button>
					<button type="button" class="hs-btn hs-btn-primary" id="hs-bg-save">ذخیره بودجه‌بندی</button>
					<span class="hs-bg-sum" id="hs-bg-sum"></span>
					<span class="hs-form-message" id="hs-bg-msg"></span>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_save() {
		if ( ! check_ajax_referer( 'hs_budget', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ), 403 );
		}
		$cid = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		if ( ! $cid || 'hs_class' !== get_post_type( $cid ) || ! HS_Roles::can_manage_class( $cid ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی به این کلاس ندارید.' ), 403 );
		}
		$raw  = isset( $_POST['rows'] ) ? json_decode( wp_unslash( $_POST['rows'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$rows = array();
		foreach ( is_array( $raw ) ? array_slice( $raw, 0, 24 ) : array() as $r ) {
			$subject = isset( $r['subject'] ) ? mb_substr( sanitize_text_field( (string) $r['subject'] ), 0, 40 ) : '';
			if ( '' === $subject ) {
				continue;
			}
			$planned = min( 999, max( 0, (int) ( $r['planned'] ?? 0 ) ) );
			$done    = min( $planned, max( 0, (int) ( $r['done'] ?? 0 ) ) );
			$rows[]  = array( 'subject' => $subject, 'planned' => $planned, 'done' => $done );
		}
		update_post_meta( $cid, self::META, $rows );
		wp_send_json_success( array( 'message' => 'بودجه‌بندی ذخیره شد ✅' ) );
	}
}

HS_Budget::init();
