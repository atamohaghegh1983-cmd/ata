<?php
/** نسخه‌ی ۲.۶: برنامه‌ی هفتگی، رسید و ویرایش پیام، سنجاق دیوار، ترتیب دانش‌آموزان، منابع درسی، نسخه‌ی اپ */
class V26Test extends HS_Test_Case {

	private function school_with_manager( $teachers ) {
		$m = $this->user( 'school_admin', 'مدیر' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		HSUM_Membership::set_status( $m, 'active' );
		wp_set_current_user( 0 );
		$sid = self::factory()->post->create( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'post_title' => 'دبستان' ) );
		update_post_meta( $sid, '_hs_manager_id', $m );
		foreach ( (array) $teachers as $t ) { HS_SO_Store::add_staff( $sid, $t, 'teacher' ); }
		return array( $m, $sid );
	}

	public function test_timetable_save_view_and_teacher_conflict() {
		$t1 = $this->teacher();
		$t2 = $this->teacher();
		$c1 = $this->klass( $t1, 'سوم الف' );
		$c2 = $this->klass( $t2, 'سوم ب' );
		list( $m, $sid ) = $this->school_with_manager( array( $t1, $t2 ) );
		update_post_meta( $c1, '_hs_school_id', $sid );
		update_post_meta( $c2, '_hs_school_id', $sid );
		$s = $this->student_in( $c1 );
		$p = $this->parent_of( $s );

		// معلم کلاس خودش را می‌چیند
		$res = $this->call( 'POST', '/classes/' . $c1 . '/timetable', array( 'cells' => array(
			array( 'day' => 0, 'period' => 1, 'subject' => 'ریاضی' ),
			array( 'day' => 0, 'period' => 2, 'subject' => 'فارسی' ),
			array( 'day' => 9, 'period' => 1, 'subject' => 'بیرون از هفته' ),
		) ), $t1 );
		$this->assertStatus( 200, $res );
		$this->assertCount( 2, $res->get_data()['cells'] );
		$this->assertNotNull( $res->get_data()['score'] );
		// معلم کلاس دیگر نمی‌تواند
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $c1 . '/timetable', array( 'cells' => array() ), $t2 ) );
		// مدیر برای کلاس دوم، معلم اول را در همان زنگ می‌گذارد ← تداخل و هیچ چیز ذخیره نمی‌شود
		$bad = $this->call( 'POST', '/classes/' . $c2 . '/timetable', array( 'cells' => array(
			array( 'day' => 0, 'period' => 1, 'subject' => 'زبان', 'teacher_id' => $t1 ),
			array( 'day' => 0, 'period' => 3, 'subject' => 'علوم' ),
		) ), $m );
		$this->assertStatus( 409, $bad );
		$this->assertStringContainsString( 'سوم الف', $bad->as_error()->get_error_message() );
		$this->assertSame( array(), HS_Timetable::cells( $c2 ) );
		// زنگ دیگر بدون تداخل
		$this->assertStatus( 200, $this->call( 'POST', '/classes/' . $c2 . '/timetable', array( 'cells' => array( array( 'day' => 0, 'period' => 3, 'subject' => 'زبان', 'teacher_id' => $t1 ) ) ), $m ) );
		// والد می‌بیند ولی ویرایش نه
		$v = $this->call( 'GET', '/me/timetable', array( 'child' => $s ), $p )->get_data();
		$this->assertSame( 'class', $v['mode'] );
		$this->assertFalse( $v['can_edit'] );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $c1 . '/timetable', array( 'cells' => array() ), $p ) );
		// برنامه‌ی من برای معلم اول: هر دو کلاس
		$mine = $this->call( 'GET', '/me/timetable', array(), $t1 )->get_data();
		$this->assertSame( 'teacher', $mine['mode'] );
		$this->assertCount( 3, $mine['cells'] );
	}

	public function test_bells_school_default_and_class_override() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		list( $m, $sid ) = $this->school_with_manager( array( $t ) );
		update_post_meta( $c, '_hs_school_id', $sid );
		$this->assertSame( 'default', $this->call( 'GET', '/classes/' . $c . '/timetable', array(), $t )->get_data()['bells']['source'] );
		$slots = array( array( 'type' => 'class', 'start' => '08:00', 'end' => '08:45' ), array( 'type' => 'break', 'start' => '08:45', 'end' => '09:00' ), array( 'type' => 'class', 'start' => '09:00', 'end' => '09:45' ) );
		$this->assertStatus( 403, $this->call( 'POST', '/school/bells', array( 'days' => 5, 'slots' => $slots ), $t ) );
		$this->assertStatus( 200, $this->call( 'POST', '/school/bells', array( 'days' => 6, 'slots' => $slots ), $m ) );
		$b = $this->call( 'GET', '/classes/' . $c . '/timetable', array(), $t )->get_data()['bells'];
		$this->assertSame( 'school', $b['source'] );
		$this->assertSame( 2, $b['periods'] );
		$this->assertSame( 6, $b['days'] );
		// هم‌پوشانی رد می‌شود
		$this->assertStatus( 400, $this->call( 'POST', '/classes/' . $c . '/bells', array( 'days' => 5, 'slots' => array( array( 'type' => 'class', 'start' => '08:00', 'end' => '09:00' ), array( 'type' => 'class', 'start' => '08:30', 'end' => '09:10' ) ) ), $t ) );
		$this->assertSame( 'class', $this->call( 'POST', '/classes/' . $c . '/bells', array( 'days' => 5, 'slots' => array( array( 'type' => 'class', 'start' => '07:30', 'end' => '08:15' ) ) ), $t )->get_data()['bells']['source'] );
		$this->assertSame( 'school', $this->call( 'POST', '/classes/' . $c . '/bells', array( 'reset' => true ), $t )->get_data()['bells']['source'] );
	}

	public function test_message_receipts_and_edit() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c );
		$p = $this->parent_of( $s );
		$this->call( 'POST', '/messages', array( 'recipient_id' => $t, 'body' => 'سلام' ), $p );
		$th = $this->call( 'GET', '/messages/' . $t, array(), $p )->get_data();
		$this->assertSame( 'sent', $th['messages'][0]['receipt'] );
		$this->assertTrue( $th['messages'][0]['can_edit'] );
		$id = $th['messages'][0]['id'];
		// معلم صندوق را باز کرد ← رسید
		$this->call( 'GET', '/messages', array(), $t );
		$this->assertSame( 'delivered', $this->call( 'GET', '/messages/' . $t, array(), $p )->get_data()['messages'][0]['receipt'] );
		// معلم گفت‌وگو را باز کرد ← خوانده شد
		$this->call( 'GET', '/messages/' . $p, array(), $t );
		$m = $this->call( 'GET', '/messages/' . $t, array(), $p )->get_data()['messages'][0];
		$this->assertSame( 'read', $m['receipt'] );
		$this->assertNotEmpty( $m['read_at'] );
		// ویرایش فقط فرستنده
		$this->assertStatus( 403, $this->call( 'POST', '/messages/item/' . $id, array( 'body' => 'x' ), $t ) );
		$this->assertStatus( 200, $this->call( 'POST', '/messages/item/' . $id, array( 'body' => 'سلام خانم معلم' ), $p ) );
		$m = $this->call( 'GET', '/messages/' . $p, array(), $t )->get_data()['messages'][0];
		$this->assertSame( 'سلام خانم معلم', $m['body'] );
		$this->assertTrue( $m['edited'] );
		$this->assertFalse( $m['can_edit'] );
		// بعد از ۲۴ ساعت نه
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . HS_Comms_DB::messages_table() . ' SET created_at = DATE_SUB(NOW(), INTERVAL 2 DAY) WHERE id = %d', $id ) ); // phpcs:ignore
		$this->assertStatus( 400, $this->call( 'POST', '/messages/item/' . $id, array( 'body' => 'دیر' ), $p ) );
	}

	public function test_wall_pin_and_feed() {
		$t  = $this->teacher();
		$c  = $this->klass( $t );
		$s  = $this->student_in( $c );
		$a  = HS_Portfolio_DB::add( array( 'class_id' => $c, 'student_id' => $s, 'title' => 'اول', 'status' => 'approved' ) );
		$b  = HS_Portfolio_DB::add( array( 'class_id' => $c, 'student_id' => $s, 'title' => 'دوم', 'status' => 'pending' ) );
		$this->assertStatus( 403, $this->call( 'POST', '/portfolio/' . $a . '/pin', array( 'on' => true ), $this->parent_of( $s ) ) );
		$this->assertStatus( 200, $this->call( 'POST', '/portfolio/' . $a . '/pin', array( 'on' => true ), $t ) );
		$wall = $this->call( 'GET', '/classes/' . $c . '/wall', array(), $t )->get_data();
		$this->assertSame( 'اول', $wall[0]['title'] );
		$this->assertTrue( $wall[0]['pinned'] );
		// سنجاق پست منتظر، آن را روی دیوار هم می‌برد
		$this->call( 'POST', '/portfolio/' . $b . '/pin', array( 'on' => true ), $t );
		$this->assertSame( 'approved', HS_Portfolio_DB::get( $b )['status'] );
		$feed = $this->call( 'GET', '/teacher/feed', array(), $t )->get_data();
		$this->assertCount( 2, $feed['wall'] );
		$this->assertArrayHasKey( 'announcements', $feed );
	}

	public function test_students_order_modes() {
		$t  = $this->teacher();
		$c  = $this->klass( $t );
		$s1 = $this->student_in( $c, 'سارا رضایی' );
		$s2 = $this->student_in( $c, 'علی احمدی' );
		$this->assertStatus( 200, $this->call( 'POST', '/classes/' . $c . '/students/order', array( 'mode' => 'last' ), $t ) );
		$this->assertSame( array( $s2, $s1 ), wp_list_pluck( $this->call( 'GET', '/classes/' . $c . '/students', array(), $t )->get_data(), 'id' ) );
		$this->call( 'POST', '/classes/' . $c . '/students/order', array( 'mode' => 'custom', 'order' => array( $s1, $s2, 99999 ) ), $t );
		$this->assertSame( array( $s1, $s2 ), wp_list_pluck( $this->call( 'GET', '/classes/' . $c . '/students', array(), $t )->get_data(), 'id' ) );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $c . '/students/order', array( 'mode' => 'first' ), $this->teacher() ) );
	}

	public function test_budget_suggestions_by_grade_and_sources() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		update_post_meta( $c, '_hs_grade', 'هشتم' );
		$d = $this->call( 'GET', '/classes/' . $c . '/budget', array(), $t )->get_data();
		$this->assertContains( 'عربی', $d['subjects'] );
		$this->assertNotContains( 'هدیه‌های آسمان', $d['subjects'] );
		$this->call( 'POST', '/me/budget-sources', array( 'sources' => array( array( 'name' => 'کتاب کار ریاضی', 'unit' => 'page', 'total' => 120 ), array( 'name' => '' ) ) ), $t );
		$d = $this->call( 'GET', '/classes/' . $c . '/budget', array(), $t )->get_data();
		$this->assertSame( 'کتاب کار ریاضی', $d['mine'][0]['name'] );
		$this->assertSame( 'page', $d['mine'][0]['unit'] );
		// مدیر فهرست مدرسه را تعیین می‌کند
		list( $m, $sid ) = $this->school_with_manager( array( $t ) );
		update_post_meta( $c, '_hs_school_id', $sid );
		$this->assertStatus( 200, $this->call( 'POST', '/school/budget-subjects', array( 'grade' => 8, 'subjects' => array( array( 'name' => 'ریاضی هشتم (مدرسه)' ) ) ), $m ) );
		$d = $this->call( 'GET', '/classes/' . $c . '/budget', array(), $t )->get_data();
		$this->assertSame( array( 'ریاضی هشتم (مدرسه)' ), $d['subjects'] );
		$this->assertSame( 'school', $d['suggest_source'] );
	}

	public function test_app_latest_public() {
		$this->assertFalse( $this->call( 'GET', '/app/latest' )->get_data()['available'] );
		update_option( HS_API_Routes_V26::APP_OPT, array( 'url' => 'https://example.com/a.apk', 'version' => '0.6.0', 'build' => 11, 'size' => 3, 'min_build' => 11 ) );
		$d = $this->call( 'GET', '/app/latest' )->get_data();
		$this->assertTrue( $d['available'] );
		$this->assertSame( 11, $d['min_build'] );
		$this->assertStringContainsString( 'example.com', do_shortcode( '[hamshagerdi_app_download]' ) );
	}

	public function test_teacher_calendar_tasks() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$d = gmdate( 'Y-m-d', strtotime( '+3 days' ) );
		HS_Homework_DB::create( $c, 'تمرین', '', '', $d );
		$x = $this->call( 'GET', '/calendar', array( 'tasks' => 1 ), $t )->get_data();
		$this->assertSame( 'hw', $x['tasks'][0]['kind'] );
	}
}
