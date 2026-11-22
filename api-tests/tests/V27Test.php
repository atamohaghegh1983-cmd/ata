<?php
/** نسخه‌ی ۲.۷: درس چندزنگه، تداخل با ساعت واقعی، بستن کلاس برای عضو جدید */
class V27Test extends HS_Test_Case {

	private function school( $teachers ) {
		$m = $this->user( 'school_admin', 'مدیر' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		HSUM_Membership::set_status( $m, 'active' );
		wp_set_current_user( 0 );
		$sid = self::factory()->post->create( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'post_title' => 'دبیرستان' ) );
		update_post_meta( $sid, '_hs_manager_id', $m );
		foreach ( $teachers as $t ) { HS_SO_Store::add_staff( $sid, $t, 'teacher' ); }
		return array( $m, $sid );
	}

	public function test_double_period_and_break_rule() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		// پیش‌فرض: زنگ ۱ و ۲ پشت سر هم، بعد تفریح، بعد ۳ و ۴
		$res = $this->call( 'POST', '/classes/' . $c . '/timetable', array( 'cells' => array(
			array( 'day' => 0, 'period' => 1, 'subject' => 'ریاضی', 'span' => 2 ),
			array( 'day' => 0, 'period' => 2, 'subject' => 'زیر دوزنگه' ),
		) ), $t );
		$this->assertStatus( 200, $res );
		$cells = $res->get_data()['cells'];
		$this->assertCount( 1, $cells );
		$this->assertSame( 2, $cells[0]['span'] );
		// درس دوزنگه می‌تواند از روی زنگ تفریح رد شود (تفریح سر جایش می‌ماند)
		$this->assertStatus( 200, $this->call( 'POST', '/classes/' . $c . '/timetable', array( 'cells' => array( array( 'day' => 0, 'period' => 2, 'subject' => 'علوم', 'span' => 2 ) ) ), $t ) );
		// ولی از آخرین زنگ روز بیرون نمی‌زند (پیش‌فرض ۵ زنگ)
		$this->assertStatus( 409, $this->call( 'POST', '/classes/' . $c . '/timetable', array( 'cells' => array( array( 'day' => 0, 'period' => 4, 'subject' => 'علوم', 'span' => 3 ) ) ), $t ) );
	}

	public function test_conflict_uses_real_clock_across_different_bells() {
		$t1 = $this->teacher();
		$t2 = $this->teacher();
		$c1 = $this->klass( $t1, 'دهم الف' );
		$c2 = $this->klass( $t2, 'دهم ب' );
		list( $m, $sid ) = $this->school( array( $t1, $t2 ) );
		update_post_meta( $c1, '_hs_school_id', $sid );
		update_post_meta( $c2, '_hs_school_id', $sid );
		// کلاس اول: ۸:۰۰-۹:۳۰ دوزنگه (۸-۸:۴۵ و ۸:۴۵-۹:۳۰)
		$this->call( 'POST', '/classes/' . $c1 . '/bells', array( 'days' => 5, 'slots' => array( array( 'type' => 'class', 'start' => '08:00', 'end' => '08:45' ), array( 'type' => 'class', 'start' => '08:45', 'end' => '09:30' ) ) ), $t1 );
		$this->assertStatus( 200, $this->call( 'POST', '/classes/' . $c1 . '/timetable', array( 'cells' => array( array( 'day' => 1, 'period' => 1, 'subject' => 'فیزیک', 'span' => 2 ) ) ), $t1 ) );
		// کلاس دوم زنگ‌بندی دیگری دارد: زنگ ۱ = ۹:۰۰-۹:۵۰ ← با ۸:۰۰-۹:۳۰ هم‌پوشانی دارد
		$this->call( 'POST', '/classes/' . $c2 . '/bells', array( 'days' => 5, 'slots' => array( array( 'type' => 'class', 'start' => '09:00', 'end' => '09:50' ), array( 'type' => 'class', 'start' => '10:00', 'end' => '10:50' ) ) ), $t2 );
		$bad = $this->call( 'POST', '/classes/' . $c2 . '/timetable', array( 'cells' => array( array( 'day' => 1, 'period' => 1, 'subject' => 'فیزیک', 'teacher_id' => $t1 ) ) ), $m );
		$this->assertStatus( 409, $bad );
		$this->assertStringContainsString( 'دهم الف', $bad->as_error()->get_error_message() );
		// زنگ ۲ (۱۰:۰۰) آزاد است
		$this->assertStatus( 200, $this->call( 'POST', '/classes/' . $c2 . '/timetable', array( 'cells' => array( array( 'day' => 1, 'period' => 2, 'subject' => 'فیزیک', 'teacher_id' => $t1 ) ) ), $m ) );
	}

	public function test_closed_class_blocks_code_join_but_not_registered_parent() {
		$t   = $this->teacher();
		$c   = $this->klass( $t );
		$inv = HSUM_Invites::class_invite( $c, true );
		$s   = $this->student_in( $c, 'سارا رضایی' );
		$p   = $this->user( 'parent', 'ناشناس' );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $c . '/join', array( 'open' => false ), $p ) );
		$this->assertFalse( $this->call( 'POST', '/classes/' . $c . '/join', array( 'open' => false ), $t )->get_data()['join_open'] );
		$this->assertFalse( $this->call( 'GET', '/classes/' . $c . '/invite', array(), $t )->get_data()['join_open'] );
		$this->assertStatus( 403, $this->call( 'POST', '/join/students', array( 'code' => $inv['code'] ), $p ) );
		$this->assertStatus( 403, $this->call( 'POST', '/children/join-class', array( 'code' => $inv['code'], 'student_id' => $s ), $p ) );
		$this->assertStatus( 403, $this->call( 'POST', '/children/join', array( 'code' => $inv['code'], 'child_name' => 'سارا رضایی' ), $p ) );
		$this->assertTrue( is_wp_error( HSUM_Invites::parent_join( $inv['code'], $p, 'سارا رضایی' ) ), 'مسیر سایت هم بسته است' );
		// والدی که معلم شماره‌اش را ثبت کرده با ورود پیامکی همچنان وصل می‌شود
		$m = $this->mobile();
		update_user_meta( $s, '_hs_parent_phone', $m );
		$this->call( 'POST', '/auth/otp/request', array( 'mobile' => $m ) );
		$d = $this->call( 'POST', '/auth/otp/verify', array( 'mobile' => $m, 'code' => $this->last_otp( $m ) ) )->get_data();
		$this->assertTrue( HS_API_Access::is_parent_of( $d['user']['id'], $s ) );
		// باز کردن دوباره
		$this->call( 'POST', '/classes/' . $c . '/join', array( 'open' => true ), $t );
		$this->assertStatus( 200, $this->call( 'POST', '/join/students', array( 'code' => $inv['code'] ), $p ) );
	}

	public function test_onboarding_cannot_change_role_or_name_of_existing_account() {
		$t = $this->teacher();
		$before = get_userdata( $t )->display_name;
		$res = $this->call( 'POST', '/onboarding', array( 'role' => 'parent', 'father_first' => 'نام', 'father_last' => 'دیگر', 'child_first' => 'الف', 'child_last' => 'ب' ), $t );
		$this->assertStatus( 409, $res );
		$this->assertSame( $before, get_userdata( $t )->display_name );
		$this->assertSame( 'teacher', HS_API_Access::view( $t ) );
	}

	public function test_me_name_edit() {
		$p = $this->user( 'parent', 'قدیمی' );
		$this->assertStatus( 400, $this->call( 'POST', '/me/name', array( 'first_name' => 'a', 'last_name' => '' ), $p ) );
		$this->assertStatus( 200, $this->call( 'POST', '/me/name', array( 'first_name' => 'مریم', 'last_name' => 'رضایی' ), $p ) );
		$this->assertSame( 'مریم رضایی', get_userdata( $p )->display_name );
	}

	public function test_release_per_app() {
		update_option( HS_API_Routes_V26::APP_OPT . '_teacher', array( 'url' => 'https://example.com/t.apk', 'version' => '0.8.0', 'build' => 20 ) );
		$t = $this->call( 'GET', '/app/latest', array( 'app' => 'teacher' ) )->get_data();
		$this->assertTrue( $t['available'] );
		$this->assertSame( 'teacher', $t['app'] );
		$f = $this->call( 'GET', '/app/latest', array( 'app' => 'family' ) )->get_data();
		$this->assertSame( 'https://example.com/t.apk', $f['other_url'] );
		$this->assertStringContainsString( 'همشاگردی معلم', do_shortcode( '[hamshagerdi_app_download app="teacher"]' ) );
	}
}
