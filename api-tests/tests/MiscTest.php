<?php
/** تکمیل ثبت‌نام، مجله، حریم خصوصی و رفع باگ‌های افزونه‌های هسته */
class MiscTest extends HS_Test_Case {

	public function test_onboarding_parent_without_code() {
		$uid = $this->user( 'subscriber', '' );
		$res = $this->call( 'GET', '/onboarding', array(), $uid );
		$this->assertStatus( 200, $res );
		$this->assertTrue( $res->get_data()['needs'] );
		$res = $this->call( 'POST', '/onboarding', array(
			'role' => 'parent', 'relation' => 'father', 'father_first' => 'رضا', 'father_last' => 'محمدی',
			'child_first' => 'سارا', 'child_last' => 'محمدی', 'child_city' => 'تهران',
		), $uid );
		$this->assertStatus( 200, $res );
		$this->assertSame( 'parent', $res->get_data()['role'] );
		$this->assertFalse( $res->get_data()['user']['needs_onboarding'] );
		$this->assertContains( 'parent', get_userdata( $uid )->roles );
		$this->assertSame( 'رضا محمدی', get_userdata( $uid )->display_name );
	}

	public function test_onboarding_validation_error() {
		$uid = $this->user( 'subscriber', '' );
		$res = $this->call( 'POST', '/onboarding', array( 'role' => 'administrator' ), $uid );
		$this->assertStatus( 400, $res );
		$this->assertNotContains( 'administrator', get_userdata( $uid )->roles );
	}

	public function test_onboarding_school_admin_needs_approval_and_cannot_invite_staff() {
		$uid   = $this->user( 'subscriber', '' );
		$staff = $this->user( 'parent', 'یک والد' );
		$res   = $this->call( 'POST', '/onboarding', array(
			'role' => 'school_admin', 'first_name' => 'مریم', 'last_name' => 'کاظمی', 'personnel_code' => '۱۲۳۴۵۶۷',
			'school_name_m' => 'دبستان امید', 'province_m' => HS_Util::provinces()[0], 'city_m' => 'شهر', 'school_address_m' => 'خیابان اول، کوچه دوم',
			'postal_code_m' => '1234567890', 'school_code_m' => '1', 'school_code' => '98765', 'school_type' => '',
			'staff' => array( array( 'position' => 'teacher', 'first' => 'یک', 'last' => 'والد', 'mobile' => get_user_meta( $staff, '_hs_mobile', true ) ) ),
		), $uid );
		$this->assertStatus( 200, $res );
		$this->assertNotContains( 'school_admin', get_userdata( $uid )->roles, 'نقش مدیر فقط بعد از تأیید' );
		$this->assertNotContains( 'teacher', get_userdata( $staff )->roles, 'متقاضیِ تأییدنشده نمی‌تواند به کسی نقش معلم بدهد' );
		$this->assertNotEmpty( get_user_meta( $uid, '_hsum_staff_pending', true ) );
		// تأیید مدیر کل ← کادر دعوت می‌شود
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( HSUM_Membership::set_status( $uid, 'active' ) );
		$this->assertTrue( HS_SO_Store::manager_approved( $uid ) );
		$this->assertEmpty( get_user_meta( $uid, '_hsum_staff_pending', true ) );
		$this->assertContains( 'teacher', get_userdata( $staff )->roles );
	}

	public function test_articles_gate() {
		$open   = self::factory()->post->create( array( 'post_title' => 'برای همه', 'post_content' => str_repeat( 'متن عمومی ', 50 ) ) );
		$locked = self::factory()->post->create( array( 'post_title' => 'ویژه معلمان', 'post_content' => 'SECRET-TEACHER-CONTENT ' . str_repeat( 'کلمه ', 80 ) ) );
		wp_set_object_terms( $open, 'general', HSAR_Access::TAX_AUD );
		wp_set_object_terms( $locked, 'teachers', HSAR_Access::TAX_AUD );
		$parent = $this->user( 'parent' );
		$one    = $this->call( 'GET', '/articles/' . $locked, array(), $parent );
		$this->assertStatus( 200, $one );
		$this->assertTrue( $one->get_data()['locked'] );
		$this->assertSame( '', $one->get_data()['content'] );
		$this->assertStringNotContainsString( 'SECRET', wp_json_encode( $one->get_data() ) );
		$ok = $this->call( 'GET', '/articles/' . $open, array(), $parent );
		$this->assertFalse( $ok->get_data()['locked'] );
		$this->assertStringContainsString( 'متن عمومی', $ok->get_data()['content'] );
		$list = $this->call( 'GET', '/articles', array(), $parent );
		$this->assertStatus( 200, $list );
		$this->assertStringNotContainsString( 'SECRET', wp_json_encode( $list->get_data() ) );
		$teacher = $this->teacher();
		$this->assertFalse( $this->call( 'GET', '/articles/' . $locked, array(), $teacher )->get_data()['locked'] );
	}

	public function test_wp_users_endpoint_hidden() {
		$this->teacher();
		$req = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$this->assertSame( 401, rest_get_server()->dispatch( $req )->get_status() );
		wp_set_current_user( $this->user( 'parent' ) );
		$this->assertSame( 403, rest_get_server()->dispatch( $req )->get_status() );
		$me = new WP_REST_Request( 'GET', '/wp/v2/users/me' );
		$this->assertSame( 200, rest_get_server()->dispatch( $me )->get_status() );
		$this->assertArrayNotHasKey( '/wp/v2/hs_class', rest_get_server()->get_routes() );
	}

	public function test_gravatar_hash_not_exposed() {
		$uid = $this->user( 'parent' );
		wp_update_user( array( 'ID' => $uid, 'user_email' => get_userdata( $uid )->user_login . '@hamshagerdi.local' ) );
		$this->assertStringNotContainsString( 'gravatar', get_avatar_url( $uid ) );
		$this->assertStringNotContainsString( md5( get_userdata( $uid )->user_email ), get_avatar_url( $uid ) );
	}

	public function test_can_view_checks_given_parent_not_current_user() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c );
		$p = $this->user( 'parent' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( HS_Parent_Link::can_view( $p, $s ), 'مدیر بودنِ کاربر فعلی نباید به والد دیگر دسترسی بدهد' );
		HS_Parent_Link::link( $p, $s );
		$this->assertTrue( HS_Parent_Link::can_view( $p, $s ) );
	}
}
