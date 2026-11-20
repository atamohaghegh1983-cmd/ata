<?php
/** نسخه‌ی ۲.۵: بازه‌ی نمایش اطلاعیه، پیوست تکلیف، ارزیابی، بودجه‌بندی، تقویم، حذف پیام، گروه‌بندی پیام‌ها */
class TeachTest extends HS_Test_Case {

	private function school_with_manager( $teacher ) {
		$m = $this->user( 'school_admin', 'مدیر' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		HSUM_Membership::set_status( $m, 'active' );
		wp_set_current_user( 0 );
		$sid = self::factory()->post->create( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'post_title' => 'دبستان' ) );
		update_post_meta( $sid, '_hs_manager_id', $m );
		HS_SO_Store::add_staff( $sid, $teacher, 'teacher' );
		return array( $m, $sid );
	}

	public function test_announcement_window_hides_expired_for_parents_but_teacher_sees_archive() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$p = $this->parent_of( $this->student_in( $c ) );
		$past   = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$future = gmdate( 'Y-m-d', strtotime( '+3 days' ) );
		$this->assertStatus( 201, $this->call( 'POST', '/classes/' . $c . '/announcements', array( 'title' => 'جلسه اولیا', 'body' => 'متن', 'show_until' => $past ), $t ) );
		$this->assertStatus( 201, $this->call( 'POST', '/classes/' . $c . '/announcements', array( 'title' => 'سه روزه', 'body' => 'متن', 'show_until' => $future ), $t ) );
		$this->assertStatus( 201, $this->call( 'POST', '/classes/' . $c . '/announcements', array( 'title' => 'آینده', 'body' => 'متن', 'show_from' => $future ), $t ) );
		$this->assertStatus( 400, $this->call( 'POST', '/classes/' . $c . '/announcements', array( 'title' => 'غلط', 'body' => 'متن', 'show_from' => $future, 'show_until' => $past ), $t ) );
		$pv = wp_list_pluck( $this->call( 'GET', '/classes/' . $c . '/announcements', array(), $p )->get_data(), 'title' );
		$this->assertSame( array( 'سه روزه' ), $pv );
		$tv = $this->call( 'GET', '/classes/' . $c . '/announcements', array(), $t )->get_data();
		$states = array_combine( wp_list_pluck( $tv, 'title' ), wp_list_pluck( $tv, 'state' ) );
		$this->assertSame( 'archived', $states['جلسه اولیا'] );
		$this->assertSame( 'scheduled', $states['آینده'] );
		$this->assertSame( 'active', $states['سه روزه'] );
	}

	public function test_homework_attachments_visible_to_parent() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c );
		$p = $this->parent_of( $s );
		$res = $this->call( 'POST', '/classes/' . $c . '/homework', array( 'title' => 'تمرین', 'due_date' => gmdate( 'Y-m-d', strtotime( '+2 days' ) ), 'attachments' => array( array( 'url' => 'https://example.com/a.pdf', 'name' => 'برگه', 'type' => 'pdf' ), array( 'url' => 'javascript:alert(1)', 'name' => 'بد' ) ) ), $t );
		$this->assertStatus( 201, $res );
		$list = $this->call( 'GET', '/classes/' . $c . '/homework', array(), $p )->get_data();
		$this->assertCount( 1, $list[0]['attachments'], 'لینک ناامن رد می‌شود' );
		$this->assertSame( 'برگه', $list[0]['attachments'][0]['name'] );
	}

	public function test_assessment_flow_and_permissions() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c, 'سارا رضایی' );
		$p = $this->parent_of( $s );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $c . '/assessments', array( 'title' => 'x' ), $p ) );
		$id = $this->call( 'POST', '/classes/' . $c . '/assessments', array( 'title' => 'املا', 'subject' => 'فارسی', 'scale' => 'descriptive' ), $t )->get_data()['id'];
		$this->assertStatus( 200, $this->call( 'POST', '/assessments/' . $id . '/results', array( 'results' => array( $s => array( 'level' => 'excellent', 'comment' => 'آفرین' ) ) ), $t ) );
		$a = $this->call( 'GET', '/assessments/' . $id, array(), $t )->get_data();
		$this->assertSame( 'excellent', $a['students'][0]['level'] );
		$this->assertStatus( 403, $this->call( 'GET', '/assessments/' . $id, array(), $this->teacher() ) );
		$list = $this->call( 'GET', '/classes/' . $c . '/assessments', array(), $t )->get_data();
		$this->assertSame( 1, $list['items'][0]['recorded'] );
		$n  = $this->call( 'POST', '/classes/' . $c . '/assessments', array( 'title' => 'ریاضی', 'scale' => 'numeric' ), $t )->get_data()['id'];
		$this->assertStatus( 400, $this->call( 'POST', '/assessments/' . $n . '/results', array( 'results' => array( $s => array( 'score' => 150 ) ) ), $t ) );
		$this->assertStatus( 200, $this->call( 'DELETE', '/assessments/' . $n, array(), $t ) );
	}

	public function test_budget_save_and_clamp() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$d = $this->call( 'POST', '/classes/' . $c . '/budget', array( 'rows' => array( array( 'subject' => 'ریاضی', 'planned' => 20, 'done' => 25 ), array( 'subject' => '', 'planned' => 5 ) ) ), $t )->get_data();
		$this->assertCount( 1, $d['rows'] );
		$this->assertSame( 20, $d['rows'][0]['done'], 'انجام‌شده بیشتر از برنامه نمی‌شود' );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $c . '/budget', array( 'rows' => array() ), $this->teacher() ) );
	}

	public function test_calendar_class_and_school_events_visibility() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		update_post_meta( $c, '_hs_grade', 'سوم' );
		list( $m, $sid ) = $this->school_with_manager( $t );
		update_post_meta( $c, '_hs_school_id', $sid );
		$s = $this->student_in( $c );
		$p = $this->parent_of( $s );
		$d = gmdate( 'Y-m-d', strtotime( '+5 days' ) );
		// معلم رویداد مدرسه نمی‌سازد؛ رویداد کلاس خودش را می‌سازد
		$this->assertStatus( 403, $this->call( 'POST', '/calendar', array( 'title' => 'x', 'date' => $d ), $t ) );
		$this->assertStatus( 201, $this->call( 'POST', '/calendar', array( 'title' => 'اردو', 'date' => $d, 'class_id' => $c ), $t ) );
		$this->assertStatus( 403, $this->call( 'POST', '/calendar', array( 'title' => 'x', 'date' => $d, 'class_id' => $this->klass( $this->teacher() ) ), $t ) );
		// مدیر: تقویم آموزشی ابتدایی و متوسطه
		$this->assertStatus( 201, $this->call( 'POST', '/calendar', array( 'title' => 'امتحانات ابتدایی', 'date' => $d, 'type' => 'academic', 'level' => 'primary', 'audience' => 'all' ), $m ) );
		$this->assertStatus( 201, $this->call( 'POST', '/calendar', array( 'title' => 'امتحانات متوسطه', 'date' => $d, 'type' => 'academic', 'level' => 'middle', 'audience' => 'all' ), $m ) );
		$this->assertStatus( 201, $this->call( 'POST', '/calendar', array( 'title' => 'فقط کادر', 'date' => $d, 'type' => 'meeting', 'audience' => 'teachers' ), $m ) );
		$tv = wp_list_pluck( $this->call( 'GET', '/calendar', array(), $t )->get_data()['events'], 'title' );
		sort( $tv );
		$this->assertSame( array( 'اردو', 'امتحانات ابتدایی', 'فقط کادر' ), $tv );
		$pv = wp_list_pluck( $this->call( 'GET', '/children/' . $s . '/calendar', array(), $p )->get_data()['events'], 'title' );
		$this->assertContains( 'امتحانات ابتدایی', $pv );
		$this->assertNotContains( 'فقط کادر', $pv );
		$this->assertNotContains( 'امتحانات متوسطه', $pv );
		// حذف: معلم دیگر نه، مدیر بله
		$ev = $this->call( 'GET', '/calendar', array(), $m )->get_data()['events'];
		$id = $ev[ array_search( 'امتحانات متوسطه', wp_list_pluck( $ev, 'title' ), true ) ]['id'];
		$this->assertStatus( 403, $this->call( 'DELETE', '/calendar/' . $id, array(), $t ) );
		$this->assertStatus( 200, $this->call( 'DELETE', '/calendar/' . $id, array(), $m ) );
	}

	public function test_message_delete_permissions() {
		$t  = $this->teacher();
		$c  = $this->klass( $t );
		$s  = $this->student_in( $c );
		$p  = $this->parent_of( $s );
		$p2 = $this->parent_of( $this->student_in( $c ) );
		$id = HS_Comms_DB::send_message( $p, $t, 'سلام', $c, $s );
		$this->assertStatus( 403, $this->call( 'DELETE', '/messages/item/' . $id, array(), $p2 ), 'والد دیگر نمی‌تواند' );
		$this->assertStatus( 403, $this->call( 'DELETE', '/messages/item/' . $id, array(), $this->teacher() ) );
		$this->assertStatus( 200, $this->call( 'DELETE', '/messages/item/' . $id, array(), $t ), 'معلم کلاس می‌تواند' );
		$id2 = HS_Comms_DB::send_message( $p, $t, 'دوباره', $c, $s );
		$this->assertStatus( 200, $this->call( 'DELETE', '/messages/item/' . $id2, array(), $p ), 'فرستنده می‌تواند' );
		$this->assertNull( HS_Comms_DB::get_message( $id2 ) );
	}

	public function test_inbox_groups_unread_and_labels_partner() {
		$t = $this->teacher( 'زهرا احمدی' );
		$c = $this->klass( $t, 'کلاس سوم الف' );
		$s = $this->student_in( $c, 'سارا رضایی' );
		$p = $this->parent_of( $s );
		foreach ( range( 1, 5 ) as $i ) { HS_Comms_DB::send_message( $p, $t, 'پیام ' . $i, $c, $s ); }
		$in = $this->call( 'GET', '/messages', array(), $t )->get_data();
		$this->assertCount( 1, $in );
		$this->assertSame( 5, $in[0]['unread_count'] );
		$this->assertSame( 'والد سارا رضایی', $in[0]['label'] );
		$th = $this->call( 'GET', '/messages/' . $t, array(), $p )->get_data();
		$this->assertSame( 'معلم کلاس سوم الف', $th['partner']['label'] );
		// اعلان: یک ردیف با شمار
		$n = HS_API_Notify::list_for( $t );
		$msgs = array_values( array_filter( $n, function ( $x ) { return 'message' === $x['type']; } ) );
		$this->assertCount( 1, $msgs );
		$this->assertStringContainsString( '۵ پیام جدید', $msgs[0]['body'] );
		// باز کردن گفت‌وگو اعلان را خوانده می‌کند
		$this->call( 'GET', '/messages/' . $p, array(), $t );
		$this->assertSame( 0, HS_API_Notify::unread( $t ) );
	}

	public function test_teacher_dashboard_has_site_cards() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$this->student_in( $c );
		$d = $this->call( 'GET', '/dashboard', array(), $t )->get_data()['teacher'];
		foreach ( array( 'month', 'top', 'upcoming', 'events' ) as $k ) { $this->assertArrayHasKey( $k, $d ); }
		$this->assertArrayHasKey( 'budget', $d['classes'][0] );
	}

	public function test_worksheet_assign_creates_homework_with_attachment() {
		$t  = $this->teacher();
		$c  = $this->klass( $t );
		$ws = self::factory()->post->create( array( 'post_type' => HS_Worksheet_CPT::POST_TYPE, 'post_status' => 'publish', 'post_title' => 'کاربرگ جمع' ) );
		update_post_meta( $ws, '_hs_ws_pdf', 'https://example.com/ws.pdf' );
		$list = $this->call( 'GET', '/worksheets', array(), $t )->get_data();
		$this->assertContains( 'کاربرگ جمع', wp_list_pluck( $list['items'], 'title' ) );
		$this->assertStatus( 403, $this->call( 'POST', '/worksheets/' . $ws . '/assign', array( 'class_id' => $this->klass( $this->teacher() ) ), $t ) );
		$this->assertStatus( 201, $this->call( 'POST', '/worksheets/' . $ws . '/assign', array( 'class_id' => $c ), $t ) );
		$hw = $this->call( 'GET', '/classes/' . $c . '/homework', array(), $t )->get_data();
		$this->assertSame( 'https://example.com/ws.pdf', $hw[0]['attachments'][0]['url'] );
	}
}
