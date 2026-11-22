<?php
/** معلم فقط کلاس خودش؛ مدیر فقط‌خواندنی و فقط اگر تأییدشده؛ دانش‌آموز فقط خودش */
class ClassAccessTest extends HS_Test_Case {

	private $t, $c, $s;

	public function set_up() {
		parent::set_up();
		$this->t = $this->teacher();
		$this->c = $this->klass( $this->t );
		$this->s = $this->student_in( $this->c, 'سارا' );
	}

	public function test_teacher_records_attendance_for_own_class_only() {
		$other_class = $this->klass( $this->teacher(), 'کلاس دیگر' );
		$stranger    = $this->student_in( $other_class );
		$res = $this->call( 'POST', '/classes/' . $this->c . '/attendance', array(
			'date'    => current_time( 'Y-m-d' ),
			'records' => array( array( 'student_id' => $this->s, 'status' => 'late', 'note' => '<b>x</b>' ), array( 'student_id' => $stranger, 'status' => 'absent' ) ),
		), $this->t );
		$this->assertStatus( 200, $res );
		$this->assertSame( 1, $res->get_data()['skipped'], 'دانش‌آموز کلاس دیگر نادیده گرفته می‌شود' );
		$rec = HS_Attendance_DB::get_by_class_date( $this->c, current_time( 'Y-m-d' ) );
		$this->assertSame( 'late', $rec[ $this->s ]['status'] );
		$this->assertStringNotContainsString( '<b>', $rec[ $this->s ]['note'] );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $other_class . '/attendance', array( 'date' => current_time( 'Y-m-d' ), 'records' => array( array( 'student_id' => $stranger ) ) ), $this->t ) );
		$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $other_class . '/students', array(), $this->t ) );
	}

	public function test_attendance_date_validation() {
		$recs = array( array( 'student_id' => $this->s, 'status' => 'present' ) );
		$this->assertStatus( 400, $this->call( 'POST', '/classes/' . $this->c . '/attendance', array( 'date' => '2026-02-30', 'records' => $recs ), $this->t ) );
		$this->assertStatus( 400, $this->call( 'POST', '/classes/' . $this->c . '/attendance', array( 'date' => gmdate( 'Y-m-d', strtotime( '+10 days' ) ), 'records' => $recs ), $this->t ) );
	}

	public function test_points_only_for_class_members() {
		$beh = HS_Points_DB::get_behaviors( $this->c );
		$this->assertNotEmpty( $beh );
		$other = $this->student_in( $this->klass( $this->teacher() ) );
		$res = $this->call( 'POST', '/classes/' . $this->c . '/points', array( 'behavior_id' => (int) $beh[0]['id'], 'student_ids' => array( $this->s, $other ) ), $this->t );
		$this->assertStatus( 200, $res );
		$this->assertSame( 1, $res->get_data()['awarded'] );
		$this->assertSame( 0, (int) HS_Points_DB::get_student_total( $this->c, $other ) );
	}

	public function test_student_profile_and_invite_for_teacher() {
		$res = $this->call( 'GET', '/classes/' . $this->c . '/students/' . $this->s, array(), $this->t );
		$this->assertStatus( 200, $res );
		$this->assertSame( $this->s, $res->get_data()['id'] );
		$this->assertStatus( 404, $this->call( 'GET', '/classes/' . $this->c . '/students/' . $this->t, array(), $this->t ) );
		$inv = $this->call( 'GET', '/classes/' . $this->c . '/invite', array(), $this->t );
		$this->assertStatus( 200, $inv );
		$this->assertNotEmpty( $inv->get_data()['parent']['code'] );
		$this->assertNotEmpty( $inv->get_data()['student_card']['code'] );
	}

	public function test_student_sees_only_own_class_and_no_staff_data() {
		$this->assertSame( array( $this->c ), wp_list_pluck( $this->call( 'GET', '/classes', array(), $this->s )->get_data(), 'id' ) );
		$this->assertStatus( 200, $this->call( 'GET', '/classes/' . $this->c . '/homework', array(), $this->s ) );
		foreach ( array( '/students', '/attendance', '/points', '/invite' ) as $r ) {
			$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $this->c . $r, array(), $this->s ), $r );
		}
		$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $this->klass( $this->teacher() ) . '/homework', array(), $this->s ) );
	}

	/** مدیر مدرسه‌ی تأییدشده (مثل تأیید مدیر کل در پیشخوان) */
	private function approved_manager() {
		$m = $this->user( 'school_admin', 'مدیر' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( HSUM_Membership::set_status( $m, 'active' ) );
		wp_set_current_user( 0 );
		return $m;
	}

	private function school( $manager ) {
		$sid = self::factory()->post->create( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'post_title' => 'دبستان نمونه' ) );
		update_post_meta( $sid, '_hs_manager_id', $manager );
		HS_SO_Store::add_staff( $sid, $this->t, 'teacher' );
		return $sid;
	}

	public function test_approved_manager_is_read_only() {
		$m = $this->approved_manager();
		$sid = $this->school( $m );
		$this->assertTrue( HS_SO_Store::manager_approved( $m ) );
		$this->assertStatus( 200, $this->call( 'GET', '/classes/' . $this->c . '/attendance', array(), $m ) );
		$this->assertStatus( 200, $this->call( 'GET', '/classes/' . $this->c . '/students', array(), $m ) );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $this->c . '/attendance', array( 'date' => current_time( 'Y-m-d' ), 'records' => array( array( 'student_id' => $this->s ) ) ), $m ) );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $this->c . '/homework', array( 'title' => 'x' ), $m ) );
		$this->assertStatus( 403, $this->call( 'POST', '/classes/' . $this->c . '/announcements', array( 'title' => 'x', 'body' => 'y' ), $m ) );
		$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $this->c . '/invite', array(), $m ) );
		$sch = $this->call( 'GET', '/school', array(), $m );
		$this->assertStatus( 200, $sch );
		$this->assertTrue( $sch->get_data()['is_manager'] );
	}

	public function test_unapproved_manager_has_no_power() {
		// ثبت‌نام «مدیر مدرسه» بدون تأیید مدیر کل ← افزونه‌ی عضویت آن را «در انتظار» می‌گذارد
		$m = $this->user( 'school_admin', 'متقاضی' );
		$this->assertSame( 'pending', HSUM_Membership::status( $m ) );
		$sid = $this->school( $m );
		$this->assertFalse( HS_SO_Store::manager_approved( $m ) );
		$this->assertFalse( HS_SO_Store::is_manager( $sid, $m ) );
		$this->assertSame( 0, HS_SO_Store::manager_school_id( $m ) );
		$this->assertSame( $sid, HS_SO_Store::owned_school_id( $m ) );
		$this->assertStatus( 403, $this->call( 'GET', '/classes/' . $this->c . '/students', array(), $m ) );
		$this->assertSame( array(), $this->call( 'GET', '/classes', array(), $m )->get_data() );
		$this->assertFalse( HSPP_Perms::can_manage( 'school', $sid, $m ), 'مجوز والدین را هم نمی‌تواند تغییر دهد' );
	}

	public function test_staff_without_view_permission_gets_school_name_only() {
		$m   = $this->approved_manager();
		$sid = $this->school( $m );
		$res = $this->call( 'GET', '/school', array(), $this->t );
		$this->assertStatus( 200, $res );
		$this->assertSame( array( 'id', 'name', 'can_supervise', 'is_manager' ), array_keys( $res->get_data() ) );
		$this->assertSame( $sid, $res->get_data()['id'] );
	}

	public function test_homework_status_needs_teacher() {
		$hid = HS_Homework_DB::create( $this->c, 'تمرین', '', '', null );
		$other_t = $this->teacher();
		$this->assertStatus( 403, $this->call( 'POST', '/homework/' . $hid . '/statuses', array( 'records' => array( array( 'student_id' => $this->s, 'status' => 'done' ) ) ), $other_t ) );
		$this->assertStatus( 200, $this->call( 'POST', '/homework/' . $hid . '/statuses', array( 'records' => array( array( 'student_id' => $this->s, 'status' => 'done' ) ) ), $this->t ) );
	}
}
