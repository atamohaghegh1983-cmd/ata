<?php
/** داشبورد نقش‌ها و ورود دانش‌آموز با کارت کلاس */
class DashboardKidTest extends HS_Test_Case {

	public function test_teacher_dashboard() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s1 = $this->student_in( $c );
		$s2 = $this->student_in( $c );
		HS_Attendance_DB::save( $c, $s1, current_time( 'Y-m-d' ), 'absent', '', $t );
		HS_Attendance_DB::save( $c, $s2, current_time( 'Y-m-d' ), 'present', '', $t );
		$this->parent_of( $s1 );
		$d = $this->call( 'GET', '/dashboard', array(), $t )->get_data();
		$this->assertSame( 'teacher', $d['role'] );
		$this->assertSame( 1, $d['teacher']['totals']['classes'] );
		$this->assertSame( 2, $d['teacher']['totals']['students'] );
		$this->assertSame( 1, $d['teacher']['totals']['absent_today'] );
		$this->assertSame( 0, $d['teacher']['totals']['not_recorded'] );
		$this->assertSame( 1, $d['teacher']['totals']['unlinked_parents'] );
	}

	public function test_parent_dashboard_respects_permissions() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c );
		$p = $this->parent_of( $s );
		HS_Attendance_DB::save( $c, $s, current_time( 'Y-m-d' ), 'late', '', $t );
		$d = $this->call( 'GET', '/dashboard', array(), $p )->get_data();
		$this->assertSame( 'late', $d['parent']['children'][0]['attendance_today'] );
		$this->deny( $s, 'attendance' );
		$d = $this->call( 'GET', '/dashboard', array(), $p )->get_data();
		$this->assertNull( $d['parent']['children'][0]['attendance_today'] );
		$this->assertContains( 'attendance', $d['parent']['children'][0]['locked'] );
		// والد دیگر فرزند را نمی‌بیند
		$this->assertArrayNotHasKey( 'parent', $this->call( 'GET', '/dashboard', array(), $this->user( 'subscriber' ) )->get_data() );
	}

	public function test_manager_dashboard_only_when_approved() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$this->student_in( $c );
		$m = $this->user( 'school_admin', 'مدیر' );
		$sid = self::factory()->post->create( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'post_title' => 'دبستان' ) );
		update_post_meta( $sid, '_hs_manager_id', $m );
		HS_SO_Store::add_staff( $sid, $t, 'teacher' );
		$d = $this->call( 'GET', '/dashboard', array(), $m )->get_data();
		$this->assertArrayNotHasKey( 'manager', $d, 'مدیر تأییدنشده داشبورد مدرسه ندارد' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		HSUM_Membership::set_status( $m, 'active' );
		$d = $this->call( 'GET', '/dashboard', array(), $m )->get_data();
		$this->assertSame( 1, $d['manager']['totals']['classes'] );
		$this->assertSame( 1, $d['manager']['totals']['teachers'] );
		$this->assertSame( 0, $d['manager']['totals']['recorded_today'] );
	}

	public function test_kid_login_with_card_and_pin() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c, 'سارا رضایی' );
		$o = $this->student_in( $c, 'بدون اجازه' );
		HSSP_Login::set_pin( $s, '4826' ); // والد اجازه داده
		$code = HSSP_Login::class_code( $c );
		$res  = $this->call( 'POST', '/auth/kid/class', array( 'code' => 'https://example.org/class-login/?c=' . $code ) );
		$this->assertStatus( 200, $res );
		$this->assertSame( array( $s ), wp_list_pluck( $res->get_data()['students'], 'id' ), 'فقط دانش‌آموزی که ورودش باز است' );
		$this->assertStatus( 403, $this->call( 'POST', '/auth/kid/login', array( 'code' => $code, 'student_id' => $o, 'pin' => '4826' ) ) );
		$this->assertStatus( 401, $this->call( 'POST', '/auth/kid/login', array( 'code' => $code, 'student_id' => $s, 'pin' => '1111' ) ) );
		$ok = $this->call( 'POST', '/auth/kid/login', array( 'code' => $code, 'student_id' => $s, 'pin' => '۴۸۲۶' ) );
		$this->assertStatus( 200, $ok );
		$this->assertSame( $s, $this->user_from_token( $ok->get_data()['access_token'] ) );
		$sum = $this->call( 'GET', '/student/summary', array(), $s );
		$this->assertStatus( 200, $sum );
		$this->assertStatus( 403, $this->call( 'GET', '/student/summary', array(), $t ) );
	}

	public function test_kid_pin_bruteforce_locks() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c );
		HSSP_Login::set_pin( $s, '4826' );
		$code = HSSP_Login::class_code( $c );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertStatus( 401, $this->call( 'POST', '/auth/kid/login', array( 'code' => $code, 'student_id' => $s, 'pin' => '000' . $i ) ) );
		}
		$this->assertStatus( 429, $this->call( 'POST', '/auth/kid/login', array( 'code' => $code, 'student_id' => $s, 'pin' => '4826' ) ), 'حتی رمز درست بعد از قفل' );
	}
}
