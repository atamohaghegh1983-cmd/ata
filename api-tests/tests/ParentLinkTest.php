<?php
/** اتصال ساده‌ی والد: کد اتصال والد، و تأیید پیشنهاد معلم */
class ParentLinkTest extends HS_Test_Case {

	public function test_join_with_parent_code_only() {
		$t   = $this->teacher();
		$c   = $this->klass( $t );
		$s   = $this->student_in( $c, 'سارا رضایی' );
		$p   = $this->user( 'parent' );
		$prof = $this->call( 'GET', '/classes/' . $c . '/students/' . $s, array(), $t )->get_data();
		$this->assertMatchesRegularExpression( '/^[A-Z0-9]{8}$/', $prof['parent_code'] );
		$res = $this->call( 'POST', '/children/join', array( 'code' => strtolower( $prof['parent_code'] ) ), $p );
		$this->assertStatus( 200, $res );
		$this->assertSame( 'linked', $res->get_data()['status'] );
		$this->assertTrue( HS_API_Access::is_parent_of( $p, $s ) );
		$this->assertStatus( 200, $this->call( 'GET', '/children/' . $s . '/today', array(), $p ) );
	}

	public function test_parent_code_not_visible_to_manager_or_parent() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c );
		$p = $this->parent_of( $s );
		$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $c . '/students/' . $s, array(), $p ) );
	}

	public function test_wrong_parent_code_is_limited() {
		$p = $this->user( 'parent' );
		for ( $i = 0; $i < 8; $i++ ) {
			$this->assertStatus( 404, $this->call( 'POST', '/children/join', array( 'code' => 'ZZZZZZZ' . $i ), $p ) );
		}
		$this->assertStatus( 429, $this->call( 'POST', '/children/join', array( 'code' => 'ZZZZZZZ9' ), $p ) );
	}

	public function test_teacher_entered_phone_becomes_proposal_parent_confirms() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$m = $this->mobile();
		$p = $this->user( 'parent', 'پدر', $m );
		wp_set_current_user( $t );
		$res = $this->call( 'POST', '/classes/' . $c . '/students', array( 'first_name' => 'علی', 'last_name' => 'کریمی', 'parent_phone' => $m ), $t );
		$this->assertStatus( 201, $res );
		$this->assertStringContainsString( $res->get_data()['parent_code'], $res->get_data()['share_text'] );
		$sid   = $res->get_data()['id'];
		$props = $this->call( 'GET', '/children/proposals', array(), $p )->get_data();
		$this->assertCount( 1, $props );
		$this->assertFalse( HS_API_Access::is_parent_of( $p, $sid ), 'بدون تأیید والد وصل نمی‌شود' );
		$other = $this->user( 'parent' );
		$this->assertStatus( 400, $this->call( 'POST', '/children/proposals/' . $props[0]['id'], array( 'accept' => true ), $other ), 'پیشنهاد کس دیگر' );
		$this->assertStatus( 200, $this->call( 'POST', '/children/proposals/' . $props[0]['id'], array( 'accept' => true ), $p ) );
		$this->assertTrue( HS_API_Access::is_parent_of( $p, $sid ) );
		$this->assertSame( array(), $this->call( 'GET', '/children/proposals', array(), $p )->get_data() );
	}
}
