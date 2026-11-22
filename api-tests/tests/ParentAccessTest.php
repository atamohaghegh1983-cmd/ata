<?php
/** والد فقط فرزند خودش، و فقط بخش‌هایی که مدرسه باز گذاشته */
class ParentAccessTest extends HS_Test_Case {

	private $t, $c, $s, $p;

	public function set_up() {
		parent::set_up();
		$this->t = $this->teacher();
		$this->c = $this->klass( $this->t );
		$this->s = $this->student_in( $this->c, 'سارا' );
		$this->p = $this->parent_of( $this->s );
		HS_Attendance_DB::save( $this->c, $this->s, current_time( 'Y-m-d' ), 'absent', '', $this->t );
	}

	public function test_parent_sees_own_child() {
		$res = $this->call( 'GET', '/children', array(), $this->p );
		$this->assertStatus( 200, $res );
		$this->assertSame( array( $this->s ), wp_list_pluck( $res->get_data(), 'id' ) );
		$res = $this->call( 'GET', '/children/' . $this->s, array(), $this->p );
		$this->assertStatus( 200, $res );
		$this->assertArrayHasKey( 'attendance_month', $res->get_data()['classes'][0] );
		$today = $this->call( 'GET', '/children/' . $this->s . '/today', array(), $this->p );
		$this->assertStatus( 200, $today );
		$this->assertSame( 'absent', $today->get_data()['classes'][0]['attendance']['status'] );
	}

	public function test_other_parent_cannot_see_child() {
		$other = $this->user( 'parent' );
		foreach ( array( '', '/today', '/week', '/pin' ) as $suffix ) {
			$this->assertStatus( 403, $this->call( 'GET', '/children/' . $this->s . $suffix, array(), $other ), $suffix );
		}
		$this->assertSame( array(), $this->call( 'GET', '/children', array(), $other )->get_data() );
	}

	public function test_teacher_is_not_treated_as_parent() {
		// قبلاً HS_Parent_Link::can_view معلم کلاس را هم «والد» حساب می‌کرد
		$this->assertStatus( 403, $this->call( 'GET', '/children/' . $this->s, array(), $this->t ) );
		$this->assertStatus( 403, $this->call( 'POST', '/children/' . $this->s . '/pin', array( 'pin' => '4826' ), $this->t ) );
	}

	public function test_closed_features_are_locked() {
		$this->deny( $this->s, 'attendance' );
		$this->deny( $this->s, 'points' );
		$this->deny( $this->s, 'homework' );
		$d = $this->call( 'GET', '/children/' . $this->s, array(), $this->p )->get_data();
		$row = $d['classes'][0];
		$this->assertArrayNotHasKey( 'attendance_month', $row );
		$this->assertArrayNotHasKey( 'points', $row );
		$this->assertArrayNotHasKey( 'homework', $row );
		$this->assertEqualsCanonicalizing( array( 'attendance', 'points', 'homework' ), $row['locked'] );
		$t = $this->call( 'GET', '/children/' . $this->s . '/today', array(), $this->p )->get_data()['classes'][0];
		$this->assertArrayNotHasKey( 'attendance', $t );
		$this->assertArrayNotHasKey( 'points_today', $t );
		$res = $this->call( 'GET', '/classes/' . $this->c . '/homework', array(), $this->p );
		$this->assertStatus( 403, $res );
		$this->assertSame( 'hs_feature_locked', $res->get_data()['code'] );
	}

	public function test_announcements_feature() {
		$this->assertStatus( 200, $this->call( 'GET', '/classes/' . $this->c . '/announcements', array(), $this->p ) );
		$this->deny( $this->s, 'announce' );
		$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $this->c . '/announcements', array(), $this->p ) );
	}

	public function test_parent_cannot_reach_staff_routes() {
		foreach ( array( '/students', '/attendance', '/points', '/invite' ) as $r ) {
			$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $this->c . $r, array(), $this->p ), $r );
		}
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $this->c . '/attendance', array( 'date' => current_time( 'Y-m-d' ), 'records' => array( array( 'student_id' => $this->s, 'status' => 'present' ) ) ), $this->p ) );
	}

	public function test_messages_respect_permission() {
		$this->assertStatus( 201, $this->call( 'POST', '/messages', array( 'recipient_id' => $this->t, 'body' => 'سلام' ), $this->p ) );
		$this->deny( $this->s, 'messages' );
		$this->assertStatus( 403, $this->call( 'POST', '/messages', array( 'recipient_id' => $this->t, 'body' => 'سلام دوباره' ), $this->p ) );
		// معلم همچنان می‌تواند به والد پیام بدهد و والد آن را بخواند
		$this->assertStatus( 201, $this->call( 'POST', '/messages', array( 'recipient_id' => $this->p, 'body' => 'جواب' ), $this->t ) );
		$th = $this->call( 'GET', '/messages/' . $this->t, array(), $this->p );
		$this->assertStatus( 200, $th );
		$this->assertFalse( $th->get_data()['can_reply'] );
	}

	public function test_unlinked_parent_loses_thread() {
		$this->call( 'POST', '/messages', array( 'recipient_id' => $this->t, 'body' => 'سلام' ), $this->p );
		HS_Parent_Link::unlink( $this->p, $this->s );
		$this->assertStatus( 403, $this->call( 'GET', '/messages/' . $this->t, array(), $this->p ) );
		$this->assertSame( array(), $this->call( 'GET', '/messages', array(), $this->p )->get_data() );
	}

	public function test_cannot_message_strangers() {
		$stranger = $this->teacher();
		$this->assertStatus( 403, $this->call( 'POST', '/messages', array( 'recipient_id' => $stranger, 'body' => 'x' ), $this->p ) );
		$this->assertStatus( 403, $this->call( 'GET', '/messages/' . $stranger, array(), $this->p ) );
	}

	public function test_pin() {
		$this->assertStatus( 400, $this->call( 'POST', '/children/' . $this->s . '/pin', array( 'pin' => '1234' ), $this->p ), 'رمز ساده' );
		$this->assertStatus( 400, $this->call( 'POST', '/children/' . $this->s . '/pin', array( 'pin' => '12a4' ), $this->p ) );
		$this->assertStatus( 200, $this->call( 'POST', '/children/' . $this->s . '/pin', array( 'pin' => '۴۸۲۶' ), $this->p ) );
		$this->assertTrue( HSSP_Login::check_pin( $this->s, '4826' ) );
		$g = $this->call( 'GET', '/children/' . $this->s . '/pin', array(), $this->p )->get_data();
		$this->assertTrue( $g['enabled'] );
		$this->assertArrayNotHasKey( 'pin', $g, 'خود رمز هرگز برنمی‌گردد' );
		$this->assertStatus( 200, $this->call( 'DELETE', '/children/' . $this->s . '/pin', array(), $this->p ) );
		$this->assertFalse( HSSP_Login::parent_ok( $this->s ) );
	}

	public function test_join_with_code_links_when_phone_matches() {
		$m   = $this->mobile();
		$s2  = $this->student_in( $this->c, 'علی رضایی' );
		update_user_meta( $s2, '_hs_parent_phone', $m );
		$p2  = $this->user( 'parent', 'پدر علی', $m );
		$inv = HSUM_Invites::class_invite( $this->c, true );
		$res = $this->call( 'POST', '/children/join', array( 'code' => 'https://example.org/join/' . $inv['code'] . '/', 'child_name' => 'علی رضایی' ), $p2 );
		$this->assertStatus( 200, $res );
		$this->assertSame( 'linked', $res->get_data()['status'] );
		$this->assertContains( $s2, HS_Parent_Link::get_children( $p2 ) );
	}

	public function test_join_with_unknown_phone_creates_request_only() {
		$p2  = $this->user( 'parent', 'ناشناس' );
		$inv = HSUM_Invites::class_invite( $this->c, true );
		$res = $this->call( 'POST', '/children/join', array( 'code' => $inv['code'], 'child_name' => 'سارا' ), $p2 );
		$this->assertStatus( 200, $res );
		$this->assertSame( 'request', $res->get_data()['status'] );
		$this->assertSame( array(), HS_Parent_Link::get_children( $p2 ), 'بدون تأیید معلم وصل نمی‌شود' );
	}

	public function test_join_bruteforce_is_limited() {
		$p2 = $this->user( 'parent' );
		for ( $i = 0; $i < 8; $i++ ) {
			$this->assertStatus( 404, $this->call( 'POST', '/children/join', array( 'code' => 'K-ZZZZ' . $i . 'Q', 'child_name' => 'سارا' ), $p2 ) );
		}
		$this->assertStatus( 429, $this->call( 'POST', '/children/join', array( 'code' => 'K-ZZZZ9Q', 'child_name' => 'سارا' ), $p2 ) );
	}

	public function test_teacher_who_is_also_parent_sees_children() {
		$t2 = $this->teacher();
		HS_Parent_Link::link( $t2, $this->s );
		$this->assertSame( array( $this->s ), wp_list_pluck( $this->call( 'GET', '/children', array(), $t2 )->get_data(), 'id' ) );
	}
}
