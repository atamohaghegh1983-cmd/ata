<?php
/** ورود با پیامک، JWT، رفرش، ابطال و سیاست حساب‌ها */
class AuthTest extends HS_Test_Case {

	private function otp_login( $mobile, $device = 'A15' ) {
		$res = $this->call( 'POST', '/auth/otp/request', array( 'mobile' => $mobile ) );
		$this->assertStatus( 200, $res );
		$code = $this->last_otp( $mobile );
		$this->assertNotSame( '', $code );
		return $this->call( 'POST', '/auth/otp/verify', array( 'mobile' => $mobile, 'code' => $code, 'device' => $device ) );
	}

	public function test_new_number_creates_account_and_needs_onboarding() {
		$m   = $this->mobile();
		$res = $this->otp_login( $m );
		$this->assertStatus( 200, $res );
		$d = $res->get_data();
		$this->assertTrue( $d['is_new'] );
		$this->assertTrue( $d['needs_onboarding'] );
		$this->assertNotEmpty( $d['access_token'] );
		$u = get_user_by( 'login', $m );
		$this->assertInstanceOf( WP_User::class, $u );
		$this->assertSame( $m, get_user_meta( $u->ID, '_hs_mobile', true ) );
		$this->assertSame( $u->ID, $this->user_from_token( $d['access_token'] ) );
	}

	public function test_persian_digits_and_existing_user() {
		$m   = $this->mobile();
		$uid = $this->user( 'parent', 'والد', $m );
		update_user_meta( $uid, '_hsum_onboarded', time() );
		$fa  = strtr( $m, array( '0' => '۰', '1' => '۱', '2' => '۲', '9' => '۹', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸' ) );
		$this->assertStatus( 200, $this->call( 'POST', '/auth/otp/request', array( 'mobile' => $fa ) ) );
		$res = $this->call( 'POST', '/auth/otp/verify', array( 'mobile' => $m, 'code' => $this->last_otp( $m ) ) );
		$this->assertStatus( 200, $res );
		$this->assertFalse( $res->get_data()['is_new'] );
		$this->assertSame( $uid, $res->get_data()['user']['id'] );
	}

	public function test_wrong_code_and_resend_cooldown() {
		$m = $this->mobile();
		$this->assertStatus( 200, $this->call( 'POST', '/auth/otp/request', array( 'mobile' => $m ) ) );
		$this->assertStatus( 429, $this->call( 'POST', '/auth/otp/request', array( 'mobile' => $m ) ), 'ارسال دوباره زیر ۶۰ ثانیه' );
		$good = $this->last_otp( $m );
		$bad  = '00000' === $good ? '11111' : '00000';
		for ( $i = 0; $i < 4; $i++ ) {
			$this->assertStatus( 400, $this->call( 'POST', '/auth/otp/verify', array( 'mobile' => $m, 'code' => $bad ) ) );
		}
		$this->assertStatus( 429, $this->call( 'POST', '/auth/otp/verify', array( 'mobile' => $m, 'code' => $bad ) ), 'پنجمین اشتباه کد را باطل می‌کند' );
		$this->assertStatus( 400, $this->call( 'POST', '/auth/otp/verify', array( 'mobile' => $m, 'code' => $good ) ), 'کد باطل‌شده دیگر کار نمی‌کند' );
	}

	public function test_bad_mobile() {
		$this->assertStatus( 400, $this->call( 'POST', '/auth/otp/request', array( 'mobile' => '12345' ) ) );
	}

	public function test_admin_cannot_use_app() {
		$m = $this->mobile();
		$a = self::factory()->user->create( array( 'role' => 'administrator', 'user_login' => $m ) );
		$res = $this->otp_login( $m );
		$this->assertStatus( 403, $res );
		$this->assertSame( 'hs_admin_account', $res->get_data()['code'] );
		// توکن ساختگی برای مدیر هم پذیرفته نمی‌شود
		$tok = HS_API_Auth::issue( get_userdata( $a ) )['access_token'];
		$this->assertSame( 0, $this->user_from_token( $tok ) );
	}

	public function test_student_number_is_rejected() {
		$m = $this->mobile();
		self::factory()->user->create( array( 'role' => 'student', 'user_login' => $m ) );
		$res = $this->otp_login( $m );
		$this->assertStatus( 409, $res );
	}

	public function test_suspended_user_is_blocked_and_tokens_revoked() {
		$uid = $this->user( 'parent' );
		$tok = HS_API_Auth::issue( get_userdata( $uid ) );
		$this->assertSame( $uid, $this->user_from_token( $tok['access_token'] ) );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->assertTrue( HSUM_Membership::set_status( $uid, 'suspended' ) );
		wp_set_current_user( 0 );
		$this->assertSame( 0, $this->user_from_token( $tok['access_token'] ), 'توکن دسترسی باطل' );
		$res = $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $tok['refresh_token'] ) );
		$this->assertStatus( 401, $res, 'رفرش باطل' );
	}

	public function test_password_change_revokes_tokens() {
		$uid = $this->user( 'teacher' );
		$tok = HS_API_Auth::issue( get_userdata( $uid ) );
		wp_update_user( array( 'ID' => $uid, 'user_pass' => 'New-Pass-123!' ) );
		$this->assertSame( 0, $this->user_from_token( $tok['access_token'] ) );
		$this->assertStatus( 401, $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $tok['refresh_token'] ) ) );
	}

	public function test_refresh_rotates() {
		$uid = $this->user( 'parent' );
		$tok = HS_API_Auth::issue( get_userdata( $uid ) );
		$r1  = $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $tok['refresh_token'] ) );
		$this->assertStatus( 200, $r1 );
		$this->assertStatus( 401, $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $tok['refresh_token'] ) ), 'توکن رفرش قدیمی بعد از چرخش باطل است' );
		$this->assertStatus( 200, $this->call( 'POST', '/auth/refresh', array( 'refresh_token' => $r1->get_data()['refresh_token'] ) ) );
	}

	public function test_token_only_for_our_namespace() {
		$uid = $this->user( 'parent' );
		$tok = HS_API_Auth::issue( get_userdata( $uid ) )['access_token'];
		$this->assertSame( $uid, $this->user_from_token( $tok, '/wp-json/hamshagerdi/v1/children' ) );
		$this->assertSame( 0, $this->user_from_token( $tok, '/wp-json/wp/v2/users' ) );
		$this->assertSame( 0, $this->user_from_token( $tok, '/wp-json/wp/v2/users?x=hamshagerdi/v1/' ), 'ترفند رشته‌ی مسیر در کوئری' );
	}

	public function test_tampered_token_rejected() {
		$uid = $this->user( 'parent' );
		$tok = HS_API_Auth::issue( get_userdata( $uid ) )['access_token'];
		$p   = explode( '.', $tok );
		$c   = json_decode( HS_API_JWT::unb64( $p[1] ), true );
		$c['sub'] = 1;
		$forged = $p[0] . '.' . HS_API_JWT::b64( wp_json_encode( $c ) ) . '.' . $p[2];
		$this->assertSame( 0, $this->user_from_token( $forged ) );
		$none = HS_API_JWT::b64( '{"typ":"JWT","alg":"none"}' ) . '.' . $p[1] . '.';
		$this->assertSame( 0, $this->user_from_token( $none ) );
	}

	public function test_password_login_throttled_per_account_across_ips() {
		$m   = $this->mobile();
		$this->user( 'teacher', 'معلم', $m );
		for ( $i = 0; $i < 5; $i++ ) {
			$_SERVER['REMOTE_ADDR'] = '172.16.0.' . ( $i + 1 ); // هر بار IP تازه
			$this->assertStatus( 401, $this->call( 'POST', '/auth/login', array( 'username' => $m, 'password' => 'wrong' . $i ) ) );
		}
		$_SERVER['REMOTE_ADDR'] = '172.16.9.9';
		$this->assertStatus( 429, $this->call( 'POST', '/auth/login', array( 'username' => $m, 'password' => 'whatever' ) ) );
	}

	public function test_logout_all() {
		$uid = $this->user( 'parent' );
		$tok = HS_API_Auth::issue( get_userdata( $uid ) );
		$this->assertStatus( 200, $this->call( 'POST', '/auth/logout', array( 'all' => true ), $uid ) );
		$this->assertSame( 0, $this->user_from_token( $tok['access_token'] ) );
	}

	public function test_devices_belong_to_one_account() {
		$a   = $this->user( 'parent' );
		$b   = $this->user( 'parent' );
		$dev = str_repeat( 'abcDEF123_-', 5 );
		$this->assertStatus( 200, $this->call( 'POST', '/devices', array( 'token' => $dev, 'platform' => 'android' ), $a ) );
		$this->assertSame( array( $dev ), HS_API_Devices::tokens( $a ) );
		$this->assertStatus( 200, $this->call( 'POST', '/devices', array( 'token' => $dev ), $b ) );
		$this->assertSame( array(), HS_API_Devices::tokens( $a ), 'گوشی به حساب دیگر رفت؛ اعلان حساب قبلی نیاید' );
		$this->assertStatus( 400, $this->call( 'POST', '/devices', array( 'token' => '<script>' ), $b ) );
		$this->assertStatus( 200, $this->call( 'DELETE', '/devices', array( 'token' => $dev ), $b ) );
		$this->assertSame( array(), HS_API_Devices::tokens( $b ) );
	}

	public function test_unauthenticated_routes_return_401() {
		foreach ( array( '/me', '/classes', '/children', '/messages', '/articles', '/onboarding', '/school' ) as $r ) {
			$this->assertStatus( 401, $this->call( 'GET', $r ), $r );
		}
	}
}
