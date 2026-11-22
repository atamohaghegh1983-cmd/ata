<?php
/** نسخه‌ی ۲.۹: ورود با رمز (موبایل/ایمیل/نام کاربری)، شمارش پیام‌های دیدنی */
class V29Test extends HS_Test_Case {

	public function test_set_password_then_login_by_mobile_email_username() {
		$m   = $this->mobile();
		$uid = self::factory()->user->create( array( 'role' => 'parent', 'user_login' => 'maryam_r', 'user_email' => $m . '@hamshagerdi.local', 'display_name' => 'مریم' ) );
		update_user_meta( $uid, '_hs_mobile', $m );
		$this->assertSame( '', $this->call( 'GET', '/me', array(), $uid )->get_data()['email'] );
		$this->assertFalse( $this->call( 'GET', '/me', array(), $uid )->get_data()['has_password'] );
		// رمز ضعیف
		$this->assertStatus( 400, $this->call( 'POST', '/me/password', array( 'password' => '1234' ), $uid ) );
		$ok = $this->call( 'POST', '/me/password', array( 'password' => 'Sara2024x', 'email' => 'maryam@example.com' ), $uid );
		$this->assertStatus( 200, $ok );
		$this->assertNotEmpty( $ok->get_data()['access_token'] );
		$this->assertTrue( $this->call( 'GET', '/me', array(), $uid )->get_data()['has_password'] );
		wp_set_current_user( 0 );
		foreach ( array( 'maryam_r', 'maryam@example.com', $m ) as $login ) {
			$r = $this->call( 'POST', '/auth/login', array( 'username' => $login, 'password' => 'Sara2024x' ) );
			$this->assertStatus( 200, $r, $login );
			$this->assertNotEmpty( $r->get_data()['access_token'] );
		}
		$this->assertStatus( 401, $this->call( 'POST', '/auth/login', array( 'username' => 'maryam_r', 'password' => 'wrong-pass1' ) ) );
		// تغییر دوباره بدون رمز فعلی رد می‌شود
		$this->assertStatus( 403, $this->call( 'POST', '/me/password', array( 'password' => 'NewPass99' ), $uid ) );
		$this->assertStatus( 200, $this->call( 'POST', '/me/password', array( 'password' => 'NewPass99', 'current' => 'Sara2024x' ), $uid ) );
		// بعد از ورود پیامکی (۱۵ دقیقه) رمز قبلی لازم نیست
		update_user_meta( $uid, '_hs_otp_at', time() );
		$this->assertStatus( 200, $this->call( 'POST', '/me/password', array( 'password' => 'Again123x' ), $uid ) );
	}

	public function test_email_taken() {
		$a = $this->user( 'parent', 'الف' );
		$b = $this->user( 'parent', 'ب' );
		$this->call( 'POST', '/me/password', array( 'password' => 'Abcdefg12', 'email' => 'x@example.com' ), $a );
		$this->assertStatus( 409, $this->call( 'POST', '/me/password', array( 'password' => 'Abcdefg12', 'email' => 'x@example.com' ), $b ) );
	}

	public function test_unread_counts_only_visible_threads() {
		$p = $this->user( 'parent', 'والد' );
		$x = $this->user( 'parent', 'غریبه' );
		HS_Comms_DB::send_message( $x, $p, 'سلام' );
		$this->assertSame( 0, $this->call( 'GET', '/me', array(), $p )->get_data()['unread_messages'] );
		$this->assertSame( array(), $this->call( 'GET', '/messages', array(), $p )->get_data() );
	}

	public function test_free_bells_with_named_breaks() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$slots = array(
			array( 'type' => 'break', 'start' => '07:30', 'end' => '07:45', 'title' => 'صبحگاه' ),
			array( 'type' => 'class', 'start' => '07:45', 'end' => '08:35' ),
			array( 'type' => 'break', 'start' => '08:35', 'end' => '08:45' ),
			array( 'type' => 'class', 'start' => '08:45', 'end' => '09:35' ),
			array( 'type' => 'break', 'start' => '09:35', 'end' => '10:00', 'title' => 'نماز' ),
			array( 'type' => 'class', 'start' => '10:00', 'end' => '10:50' ),
		);
		$this->assertStatus( 200, $this->call( 'POST', '/classes/' . $c . '/bells', array( 'days' => 6, 'slots' => $slots ), $t ) );
		$b = $this->call( 'GET', '/classes/' . $c . '/timetable', array(), $t )->get_data()['bells'];
		$this->assertSame( 3, $b['periods'] );
		$this->assertSame( 'صبحگاه', $b['slots'][0]['title'] );
		$this->assertSame( 'زنگ تفریح', $b['slots'][2]['title'] );
		// درس سه‌زنگه از روی دو تفریح
		$res = $this->call( 'POST', '/classes/' . $c . '/timetable', array( 'cells' => array( array( 'day' => 5, 'period' => 1, 'subject' => 'کارگاه', 'span' => 3 ) ) ), $t );
		$this->assertStatus( 200, $res );
		$this->assertSame( 3, $res->get_data()['cells'][0]['span'] );
		$this->assertSame( array( 7 * 60 + 45, 10 * 60 + 50 ), HS_Timetable::time_range( $c, 1, 3 ) );
		// جدول سایت: درس سه‌زنگه ۵ ردیف (با دو تفریح وسط) را می‌گیرد؛ هر ردیف ۷ خانه دارد
		wp_set_current_user( $t );
		$_GET['class_id'] = $c;
		$html = HS_Timetable::render();
		unset( $_GET['class_id'] );
		$this->assertStringContainsString( 'rowspan="5"', $html );
		$this->assertStringContainsString( 'صبحگاه', $html );
		preg_match_all( '#<tr[^>]*>(.*?)</tr>#s', explode( '<tbody>', $html )[1], $rows );
		$this->assertSame( array( 7, 7, 6, 6, 6, 6 ), array_map( function ( $r ) { return substr_count( $r, '<td' ); }, $rows[1] ) );
	}
}
