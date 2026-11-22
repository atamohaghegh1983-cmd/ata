<?php
/**
 * تعریف سمت‌ها، حوزه‌های وظیفه و وضعیت‌ها
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_SO_Structure {

	/** سمت‌ها: کلید => عنوان، رنگ پالت پنل، والد در چارت، شرح وظایف پیش‌فرض */
	public static function positions() {
		return array(
			'deputy_edu'  => array(
				'title'  => 'معاون آموزشی',
				'color'  => 'blue',
				'parent' => 'manager',
				'duties' => "برنامه‌ریزی درسی و بودجه‌بندی سالانه با آموزگاران\nنظارت بر اجرای برنامه هفتگی و کیفیت تدریس\nپیگیری ارزشیابی توصیفی و ثبت نمرات\nهماهنگی تکالیف، کاربرگ‌ها و فعالیت‌های آموزشی\nگزارش پیشرفت تحصیلی به مدیر",
			),
			'deputy_exec' => array(
				'title'  => 'معاون اجرایی',
				'color'  => 'teal',
				'parent' => 'manager',
				'duties' => "نظارت بر حضور و غیاب دانش‌آموزان و کادر\nانضباط، نظم و آراستگی مدرسه\nپشتیبانی اداری، تجهیزات و فضای مدرسه\nهماهنگی با اولیا برای امور اجرایی\nتنظیم برنامه نوبت‌های نگهبانی و زنگ‌ها",
			),
			'deputy_cult' => array(
				'title'  => 'معاون پرورشی',
				'color'  => 'violet',
				'parent' => 'manager',
				'duties' => "برنامه‌ریزی مناسبت‌ها، جشن‌ها و اردوها\nراه‌اندازی انجمن اولیا و شورای دانش‌آموزی\nنظارت بر فعالیت‌های پرورشی و فوق‌برنامه\nهماهنگی مراقبت و سلامت و تربیت بدنی",
			),
			'health'      => array(
				'title'  => 'مسئول مراقبت و سلامت',
				'color'  => 'pink',
				'parent' => 'deputy_cult',
				'duties' => "پرونده سلامت و ایمنی دانش‌آموزان\nمراقبت‌های بهداشتی و واکسیناسیون\nآموزش بهداشت و گزارش موارد اورژانسی",
			),
			'pe'          => array(
				'title'  => 'مربی تربیت بدنی',
				'color'  => 'orange',
				'parent' => 'deputy_cult',
				'duties' => "برنامه زنگ ورزش و حرکات اصلاحی\nمسابقات و فعالیت‌های بدنی\nایمنی وسایل و فضای ورزشی",
			),
			'teacher'     => array(
				'title'  => 'آموزگار',
				'color'  => 'green',
				'parent' => 'deputy_edu',
				'duties' => "تدریس و برنامه هفتگی کلاس\nثبت حضور و غیاب، تکلیف و ارزیابی\nارتباط منظم با اولیا\nثبت پیشرفت و تشویق دانش‌آموزان",
			),
			'other'       => array(
				'title'  => 'سایر کادر',
				'color'  => 'green',
				'parent' => 'manager',
				'duties' => '',
			),
		);
	}

	public static function position_title( $key ) {
		$p = self::positions();
		return isset( $p[ $key ] ) ? $p[ $key ]['title'] : 'کادر';
	}

	public static function is_deputy( $key ) {
		return 0 === strpos( (string) $key, 'deputy_' );
	}

	/** حوزهٔ وظیفه (برچسب برای دسته‌بندی وظایف) */
	public static function domains() {
		return array(
			''             => '— بدون دسته —',
			'weekly_plan'  => 'برنامه‌ریزی و بودجه‌بندی هفتگی',
			'occasions'    => 'تقویم مناسبتی',
			'associations' => 'انجمن‌ها و شوراها',
			'attendance'   => 'حضور و غیاب',
			'health'       => 'مراقبت و سلامت',
			'sports'       => 'تربیت بدنی و ورزش',
			'assessment'   => 'ارزیابی و نمرات',
			'homework'     => 'تکالیف و کاربرگ',
			'parent_comms' => 'ارتباط با والدین',
			'reports'      => 'گزارش‌ها و آمار',
			'other'        => 'سایر',
		);
	}

	public static function statuses() {
		return array(
			'todo'  => 'انجام نشده',
			'doing' => 'در حال انجام',
			'done'  => 'انجام شد',
		);
	}

	public static function priorities() {
		return array(
			'normal' => 'عادی',
			'high'   => 'فوری',
		);
	}
}
