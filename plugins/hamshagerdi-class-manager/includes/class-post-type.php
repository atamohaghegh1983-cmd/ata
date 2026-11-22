<?php
/**
 * ثبت Custom Post Type برای کلاس
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HS_Class_Post_Type {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'manage_hs_class_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_hs_class_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register() {
		$labels = array(
			'name'               => 'کلاس‌ها',
			'singular_name'      => 'کلاس',
			'menu_name'          => 'کلاس‌های همشاگردی',
			'add_new'            => 'افزودن کلاس',
			'add_new_item'       => 'افزودن کلاس جدید',
			'edit_item'          => 'ویرایش کلاس',
			'new_item'           => 'کلاس جدید',
			'view_item'          => 'مشاهده کلاس',
			'search_items'       => 'جستجوی کلاس',
			'not_found'          => 'کلاسی یافت نشد',
			'not_found_in_trash' => 'کلاسی در زباله‌دان نیست',
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'query_var'           => true,
			'rewrite'             => array( 'slug' => 'class' ),
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'author' ),
			'show_in_rest'        => true,
		);

		register_post_type( 'hs_class', $args );
	}

	public static function columns( $columns ) {
		$new = array();
		$new['cb']        = $columns['cb'];
		$new['title']     = 'نام کلاس';
		$new['grade']     = 'پایه';
		$new['year']      = 'سال تحصیلی';
		$new['teacher']   = 'معلم';
		$new['students']  = 'تعداد دانش‌آموز';
		$new['invite']    = 'کد دعوت';
		$new['date']      = 'تاریخ';
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'grade':
				echo esc_html( get_post_meta( $post_id, '_hs_grade', true ) ?: '—' );
				break;
			case 'year':
				echo esc_html( get_post_meta( $post_id, '_hs_year', true ) ?: '—' );
				break;
			case 'teacher':
				$tid = (int) get_post_meta( $post_id, '_hs_teacher_id', true );
				if ( $tid ) {
					$user = get_userdata( $tid );
					echo $user ? esc_html( $user->display_name ) : '—';
				} else {
					echo '—';
				}
				break;
			case 'students':
				$students = get_post_meta( $post_id, '_hs_students', true );
				$count = is_array( $students ) ? count( $students ) : 0;
				echo (int) $count;
				break;
			case 'invite':
				$code = get_post_meta( $post_id, '_hs_invite_code', true );
				echo $code ? '<code>' . esc_html( $code ) . '</code>' : '—';
				break;
		}
	}
}

HS_Class_Post_Type::init();
