<?php
/**
 * پایه‌ی تست‌ها: ساخت معلم، کلاس، دانش‌آموز، والد و فراخوانی مسیرهای REST.
 */
abstract class HS_Test_Case extends WP_UnitTestCase {

	protected static $mobile_seq = 1000;

	public function set_up() {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
		$_SERVER['REMOTE_ADDR'] = '10.0.0.' . wp_rand( 1, 250 );
		wp_set_current_user( 0 );
	}

	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;
		unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REQUEST_URI'] );
		parent::tear_down();
	}

	protected function mobile() {
		return '0912' . str_pad( (string) ( ++self::$mobile_seq ), 7, '0', STR_PAD_LEFT );
	}

	protected function user( $role, $name = 'کاربر آزمایشی', $mobile = '' ) {
		$m   = $mobile ? $mobile : $this->mobile();
		$uid = self::factory()->user->create( array( 'role' => $role, 'user_login' => $m, 'display_name' => $name, 'first_name' => $name, 'last_name' => 'آزمایشی' ) );
		update_user_meta( $uid, '_hs_mobile', $m );
		return $uid;
	}

	protected function teacher() { return $this->user( 'teacher', 'معلم' ); }

	protected function student_in( $class_id, $name = 'دانش‌آموز' ) {
		$sid = self::factory()->user->create( array( 'role' => 'student', 'user_login' => 'stu' . wp_rand( 10000, 99999 ) . wp_rand( 100, 999 ), 'display_name' => $name ) );
		HS_Class_Students::add_student( $class_id, $sid );
		return $sid;
	}

	protected function klass( $teacher_id, $title = 'کلاس سوم' ) {
		$cid = self::factory()->post->create( array( 'post_type' => 'hs_class', 'post_status' => 'publish', 'post_title' => $title, 'post_author' => $teacher_id ) );
		update_post_meta( $cid, '_hs_teacher_id', $teacher_id );
		update_post_meta( $cid, '_hs_grade', 'سوم' );
		return $cid;
	}

	protected function parent_of( $sid ) {
		$pid = $this->user( 'parent', 'والد' );
		HS_Parent_Link::link( $pid, $sid );
		return $pid;
	}

	/** بستن یک بخش برای یک دانش‌آموز (با دسترسی مدیر سایت، مثل پیشخوان) */
	protected function deny( $sid, $feature ) {
		$prev  = get_current_user_id();
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$r = HSPP_Perms::set( 'student', $sid, $feature, 0 );
		$this->assertTrue( $r );
		wp_set_current_user( $prev );
	}

	/** @return WP_REST_Response */
	protected function call( $method, $route, $params = array(), $as = null ) {
		if ( null !== $as ) {
			wp_set_current_user( $as );
		}
		$req = new WP_REST_Request( $method, '/hamshagerdi/v1' . $route );
		if ( $params ) {
			if ( 'GET' === $method ) {
				$req->set_query_params( $params );
			} else {
				$req->set_header( 'content-type', 'application/json' );
				$req->set_body( wp_json_encode( $params ) );
			}
		}
		return rest_get_server()->dispatch( $req );
	}

	protected function assertStatus( $code, WP_REST_Response $res, $msg = '' ) {
		$this->assertSame( $code, $res->get_status(), $msg . ' ← ' . wp_json_encode( $res->get_data(), JSON_UNESCAPED_UNICODE ) );
	}

	/** آخرین کد پیامکی در حالت آزمایشی افزونه‌ی ورود */
	protected function last_otp( $mobile ) {
		foreach ( (array) get_option( 'hsma_test_log', array() ) as $row ) {
			if ( $row['m'] === $mobile ) { return (string) $row['c']; }
		}
		return '';
	}

	/** شبیه‌سازی درخواست HTTP با توکن (برای تست determine_current_user) */
	protected function user_from_token( $token, $uri = '/wp-json/hamshagerdi/v1/me' ) {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		$_SERVER['REQUEST_URI']        = $uri;
		$ref = new ReflectionProperty( 'HS_API_Auth', 'error' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );
		$uid = HS_API_Auth::determine_user( 0 );
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		return (int) $uid;
	}
}
