<?php
/** نسخه‌ی ۲.۴: اطلاعیه، اتصال خودکار، کد کلاس، اکسل، پورتفولیو، مدرسه، اعلان، حذف حساب */
class MoreTest extends HS_Test_Case {

	private function approved_manager() {
		$m = $this->user( 'school_admin', 'مدیر' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		HSUM_Membership::set_status( $m, 'active' );
		wp_set_current_user( 0 );
		return $m;
	}

	public function test_classes_mine_excludes_children_classes() {
		$t  = $this->teacher();
		$c1 = $this->klass( $t, 'کلاس خودم' );
		$c2 = $this->klass( $this->teacher(), 'کلاس دیگری' );
		HS_Parent_Link::link( $t, $this->student_in( $c2 ) ); // معلم، والدِ یک دانش‌آموز در کلاس دیگر هم هست
		$ids = wp_list_pluck( $this->call( 'GET', '/classes', array( 'mine' => 1 ), $t )->get_data(), 'id' );
		$this->assertSame( array( $c1 ), $ids );
	}

	public function test_announcement_edit_delete_permissions() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c );
		$p = $this->parent_of( $s );
		$id = HS_Comms_DB::add_announcement( $c, 'عنوان', 'متن', $t );
		$this->assertStatus( 403, $this->call( 'POST', '/announcements/' . $id, array( 'title' => 'x', 'body' => 'y' ), $p ) );
		$this->assertStatus( 403, $this->call( 'DELETE', '/announcements/' . $id, array(), $this->teacher() ) );
		$this->assertStatus( 200, $this->call( 'POST', '/announcements/' . $id, array( 'title' => 'عنوان تازه', 'body' => 'متن تازه' ), $t ) );
		$this->assertSame( 'عنوان تازه', HS_Comms_DB::get_announcements( $c, 1 )[0]['title'] );
		// مدیر مدرسه‌ی همان کلاس
		$m   = $this->approved_manager();
		$sid = self::factory()->post->create( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'post_title' => 'دبستان' ) );
		update_post_meta( $sid, '_hs_manager_id', $m );
		HS_SO_Store::add_staff( $sid, $t, 'teacher' );
		$this->assertStatus( 200, $this->call( 'DELETE', '/announcements/' . $id, array(), $m ) );
		$this->assertSame( array(), HS_Comms_DB::get_announcements( $c, 5 ) );
	}

	public function test_parent_registered_by_teacher_is_linked_on_first_login() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$m = $this->mobile();
		wp_set_current_user( $t );
		$st = $this->call( 'POST', '/classes/' . $c . '/students', array( 'first_name' => 'سارا', 'last_name' => 'رضایی', 'parent_name' => 'مریم رضایی', 'parent_phone' => $m ), $t )->get_data();
		wp_set_current_user( 0 );
		$this->assertStatus( 200, $this->call( 'POST', '/auth/otp/request', array( 'mobile' => $m ) ) );
		$res = $this->call( 'POST', '/auth/otp/verify', array( 'mobile' => $m, 'code' => $this->last_otp( $m ) ) );
		$this->assertStatus( 200, $res );
		$d = $res->get_data();
		$this->assertFalse( $d['needs_onboarding'], 'والدِ ثبت‌شده فرم ثبت‌نام نمی‌خواهد' );
		$uid = $d['user']['id'];
		$this->assertTrue( HS_API_Access::is_parent_of( $uid, $st['id'] ), 'بدون دکمه‌ی تأیید وصل است' );
		$this->assertSame( 'مریم رضایی', get_userdata( $uid )->display_name );
	}

	public function test_new_number_gets_neutral_role_even_if_site_default_is_student() {
		update_option( 'default_role', 'student' );
		$m = $this->mobile();
		$this->call( 'POST', '/auth/otp/request', array( 'mobile' => $m ) );
		$d = $this->call( 'POST', '/auth/otp/verify', array( 'mobile' => $m, 'code' => $this->last_otp( $m ) ) )->get_data();
		$this->assertTrue( $d['needs_onboarding'] );
		$this->assertNotContains( 'student', get_userdata( $d['user']['id'] )->roles );
		update_option( 'default_role', 'subscriber' );
	}

	public function test_join_class_pick_child_match_or_request() {
		$t   = $this->teacher();
		$c   = $this->klass( $t );
		$inv = HSUM_Invites::class_invite( $c, true );
		$s1  = $this->student_in( $c, 'سارا رضایی' );
		$s2  = $this->student_in( $c, 'علی کریمی' );
		$m   = $this->mobile();
		update_user_meta( $s1, '_hs_parent_phone', $m );
		$p1 = $this->user( 'parent', 'والد سارا', $m );
		$list = $this->call( 'POST', '/join/students', array( 'code' => $inv['code'] ), $p1 )->get_data();
		$this->assertCount( 2, $list['students'] );
		$this->assertSame( 'linked', $this->call( 'POST', '/children/join-class', array( 'code' => $inv['code'], 'student_id' => $s1 ), $p1 )->get_data()['status'] );
		$p2 = $this->user( 'parent', 'ناشناس' );
		$this->assertSame( 'request', $this->call( 'POST', '/children/join-class', array( 'code' => $inv['code'], 'student_id' => $s2 ), $p2 )->get_data()['status'] );
		$this->assertFalse( HS_API_Access::is_parent_of( $p2, $s2 ) );
		// معلم درخواست را در اپ می‌بیند و تأیید می‌کند
		$reqs = $this->call( 'GET', '/requests', array(), $t )->get_data();
		$this->assertCount( 1, $reqs );
		$this->assertStatus( 400, $this->call( 'POST', '/requests/' . $reqs[0]['id'], array( 'approve' => true, 'student_id' => $s2 ), $this->teacher() ) );
		$this->assertStatus( 200, $this->call( 'POST', '/requests/' . $reqs[0]['id'], array( 'approve' => true, 'student_id' => $s2 ), $t ) );
		$this->assertTrue( HS_API_Access::is_parent_of( $p2, $s2 ) );
	}

	public function test_excel_import() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$res = $this->call( 'POST', '/classes/' . $c . '/students/import', array( 'rows' => array(
			array( 'first_name' => 'سارا', 'last_name' => 'رضایی', 'parent_phone' => '۰۹۱۲۱۱۱۲۲۳۳' ),
			array( 'first_name' => 'علی', 'last_name' => 'کریمی' ),
			array( 'first_name' => '', 'last_name' => '' ),
			array( 'first_name' => 'سارا', 'last_name' => 'رضایی', 'parent_phone' => '09121112233' ),
		) ), $t );
		$this->assertStatus( 200, $res );
		$this->assertSame( 2, $res->get_data()['added'] );
		$this->assertSame( 1, $res->get_data()['skipped'] );
		$this->assertCount( 1, $res->get_data()['errors'], 'تکراری' );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $c . '/students/import', array( 'rows' => array( array( 'first_name' => 'a', 'last_name' => 'b' ) ) ), $this->teacher() ) );
	}

	public function test_portfolio_privacy_and_daily_limit() {
		$t  = $this->teacher();
		$c  = $this->klass( $t );
		$s  = $this->student_in( $c, 'سارا' );
		$p  = $this->parent_of( $s );
		$s2 = $this->student_in( $c, 'علی' );
		$p2 = $this->parent_of( $s2 );
		$this->assertStatus( 201, $this->call( 'POST', '/portfolio/student/' . $s, array( 'title' => 'نقاشی من', 'text' => 'یک گل' ), $p ) );
		$this->assertStatus( 201, $this->call( 'POST', '/portfolio/student/' . $s, array( 'title' => 'کاردستی' ), $s ) );
		$this->assertStatus( 429, $this->call( 'POST', '/portfolio/student/' . $s, array( 'title' => 'سومی' ), $p ), 'روزی حداکثر ۲ پست' );
		$this->assertStatus( 403, $this->call( 'POST', '/portfolio/student/' . $s, array( 'title' => 'x' ), $p2 ), 'والد دیگر نمی‌تواند' );
		$this->assertStatus( 403, $this->call( 'GET', '/portfolio/student/' . $s, array(), $p2 ), 'پست خصوصی برای والد دیگر' );
		$this->assertSame( array(), $this->call( 'GET', '/classes/' . $c . '/wall', array(), $p2 )->get_data() );
		$posts = $this->call( 'GET', '/classes/' . $c . '/portfolio', array(), $t )->get_data();
		$this->assertCount( 2, $posts );
		$this->assertStatus( 403, $this->call( 'POST', '/portfolio/' . $posts[0]['id'] . '/wall', array( 'on' => true ), $p ), 'فقط معلم روی دیوار می‌گذارد' );
		$this->assertStatus( 200, $this->call( 'POST', '/portfolio/' . $posts[0]['id'] . '/wall', array( 'on' => true ), $t ) );
		$wall = $this->call( 'GET', '/classes/' . $c . '/wall', array(), $p2 )->get_data();
		$this->assertCount( 1, $wall );
		$this->assertTrue( $this->call( 'POST', '/portfolio/' . $wall[0]['id'] . '/cheer', array(), $p2 )->get_data()['cheered'] );
		$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $c . '/wall', array(), $this->user( 'parent' ) ) );
	}

	public function test_school_profile_seven_digit_code_and_staff() {
		$m = $this->approved_manager();
		$base = array( 'name' => 'دبستان امید', 'province' => HS_Util::provinces()[0], 'city' => 'شهر', 'address' => 'خیابان اول کوچه دوم', 'postal' => '1234567890' );
		$this->assertStatus( 400, $this->call( 'POST', '/school/profile', $base + array( 'code' => '12345' ), $m ) );
		$this->assertStatus( 400, $this->call( 'POST', '/school/profile', $base + array( 'code' => '12345678' ), $m ) );
		$this->assertStatus( 200, $this->call( 'POST', '/school/profile', $base + array( 'code' => '۱۲۳۴۵۶۷' ), $m ) );
		$this->assertStatus( 403, $this->call( 'POST', '/school/profile', $base + array( 'code' => '7654321' ), $this->teacher() ) );
		$t = $this->teacher();
		$res = $this->call( 'POST', '/school/staff', array( 'first' => 'زهرا', 'last' => 'احمدی', 'mobile' => get_user_meta( $t, '_hs_mobile', true ), 'position' => 'teacher' ), $m );
		$this->assertStatus( 200, $res );
		$this->assertNotNull( HS_SO_Store::staff_row_by_user( $t ), 'معلمِ عضو مستقیم به کادر اضافه می‌شود' );
		// معلم دیگر با کد مدرسه درخواست اتصال می‌دهد ← مدیر تأیید می‌کند
		$t2 = $this->teacher();
		$c2 = $this->klass( $t2 );
		$this->assertStatus( 200, $this->call( 'POST', '/me/school', array( 'school_code' => '1234567' ), $t2 ) );
		$claims = $this->call( 'GET', '/school/claims', array(), $m )->get_data();
		$this->assertSame( array( $c2 ), wp_list_pluck( $claims, 'id' ) );
		$this->assertStatus( 200, $this->call( 'POST', '/school/claims/' . $c2, array(), $m ) );
		$this->assertContains( $c2, HS_API_Access::supervised_class_ids( $m ) );
	}

	public function test_notifications_follow_permissions() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c, 'سارا' );
		$p = $this->parent_of( $s );
		$this->call( 'POST', '/classes/' . $c . '/attendance', array( 'date' => current_time( 'Y-m-d' ), 'records' => array( array( 'student_id' => $s, 'status' => 'absent' ) ) ), $t );
		$n = $this->call( 'GET', '/notifications', array(), $p )->get_data();
		$this->assertSame( 1, $n['unread'] );
		$this->assertSame( 'attendance', $n['items'][0]['type'] );
		$this->deny( $s, 'attendance' );
		$this->call( 'POST', '/classes/' . $c . '/attendance', array( 'date' => current_time( 'Y-m-d' ), 'records' => array( array( 'student_id' => $s, 'status' => 'late' ) ) ), $t );
		$this->assertSame( 1, $this->call( 'GET', '/notifications', array(), $p )->get_data()['unread'], 'بخش بسته ← اعلان نمی‌آید' );
		$this->call( 'POST', '/notifications/read', array(), $p );
		$this->assertSame( 0, $this->call( 'GET', '/notifications', array(), $p )->get_data()['unread'] );
	}

	public function test_onboarding_as_student_with_class_code() {
		$t   = $this->teacher();
		$c   = $this->klass( $t );
		update_post_meta( $c, '_hs_invite_code', 'ABC234' );
		$uid = $this->user( 'subscriber', '' );
		$res = $this->call( 'POST', '/onboarding', array( 'role' => 'student', 'first_name' => 'آرین', 'last_name' => 'نوری', 'code' => 'abc234' ), $uid );
		$this->assertStatus( 200, $res );
		$this->assertContains( 'student', get_userdata( $uid )->roles );
		$this->assertContains( $uid, HS_API_Access::class_members( $c ) );
	}

	public function test_broadcast_and_delete_account() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$p = $this->parent_of( $this->student_in( $c ) );
		$this->parent_of( $this->student_in( $c ) );
		$this->assertSame( 2, $this->call( 'POST', '/classes/' . $c . '/broadcast', array( 'body' => 'سلام به همه' ), $t )->get_data()['sent'] );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $c . '/broadcast', array( 'body' => 'x' ), $p ) );
		$tok = HS_API_Auth::issue( get_userdata( $p ) )['access_token'];
		$this->assertStatus( 200, $this->call( 'POST', '/me/delete', array( 'reason' => 'آزمایش' ), $p ) );
		$this->assertSame( array(), HS_API_Access::children_ids( $p ) );
		$this->assertSame( 0, $this->user_from_token( $tok ) );
	}
}
