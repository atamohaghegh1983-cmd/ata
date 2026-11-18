<?php
/** حذف‌ها: والد فرزند را جدا می‌کند، معلم دانش‌آموز را از کلاس، مدیر کادر را از مدرسه */
class RemoveTest extends HS_Test_Case {

	public function test_parent_unlinks_child_only_for_self() {
		$t  = $this->teacher();
		$c  = $this->klass( $t );
		$s  = $this->student_in( $c );
		$p1 = $this->parent_of( $s );
		$p2 = $this->user( 'parent' );
		HS_Parent_Link::link( $p2, $s );
		$this->assertStatus( 403, $this->call( 'DELETE', '/children/' . $s, array(), $this->user( 'parent' ) ), 'والد غریبه' );
		$this->assertStatus( 403, $this->call( 'DELETE', '/children/' . $s, array(), $t ), 'معلم از این مسیر نه' );
		$this->assertStatus( 200, $this->call( 'DELETE', '/children/' . $s, array(), $p1 ) );
		$this->assertFalse( HS_API_Access::is_parent_of( $p1, $s ) );
		$this->assertTrue( HS_API_Access::is_parent_of( $p2, $s ), 'والد دیگر وصل می‌ماند' );
		$this->assertContains( $s, HS_API_Access::class_members( $c ), 'دانش‌آموز در کلاس می‌ماند' );
	}

	public function test_teacher_removes_student_from_own_class() {
		$t = $this->teacher();
		$c = $this->klass( $t );
		$s = $this->student_in( $c );
		$p = $this->parent_of( $s );
		$other = $this->teacher();
		$this->assertStatus( 403, $this->call( 'DELETE', '/classes/' . $c . '/students/' . $s, array(), $other ) );
		$this->assertStatus( 403, $this->call( 'DELETE', '/classes/' . $c . '/students/' . $s, array(), $p ) );
		$this->assertStatus( 200, $this->call( 'DELETE', '/classes/' . $c . '/students/' . $s, array(), $t ) );
		$this->assertNotContains( $s, HS_API_Access::class_members( $c ) );
		$this->assertInstanceOf( WP_User::class, get_userdata( $s ), 'حساب دانش‌آموز پاک نمی‌شود' );
		$this->assertStatus( 404, $this->call( 'DELETE', '/classes/' . $c . '/students/' . $s, array(), $t ) );
	}

	public function test_manager_removes_staff() {
		$m = $this->user( 'school_admin', 'مدیر' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		HSUM_Membership::set_status( $m, 'active' );
		$sid = self::factory()->post->create( array( 'post_type' => HS_SO_Store::CPT, 'post_status' => 'publish', 'post_title' => 'دبستان' ) );
		update_post_meta( $sid, '_hs_manager_id', $m );
		$t   = $this->teacher();
		$c   = $this->klass( $t );
		$row = HS_SO_Store::add_staff( $sid, $t, 'teacher' );
		$this->assertContains( $c, HS_SO_Monitor::class_ids( $sid ) );
		$staff_id = (int) HS_SO_Store::staff_row_by_user( $t )['id'];
		// معلمی که عضو کادر است نمی‌تواند کادر را حذف کند
		$this->assertStatus( 403, $this->call( 'DELETE', '/school/staff/' . $staff_id, array(), $t ) );
		$this->assertStatus( 200, $this->call( 'DELETE', '/school/staff/' . $staff_id, array(), $m ) );
		$this->assertNull( HS_SO_Store::staff_row_by_user( $t ) );
		$this->assertNotContains( $c, HS_SO_Monitor::class_ids( $sid ), 'کلاس‌هایش دیگر زیر نظر مدرسه نیست' );
		$this->assertSame( 'publish', get_post_status( $c ), 'کلاس معلم دست‌نخورده می‌ماند' );
		// مدیر مدرسه‌ی دیگر
		$m2 = $this->user( 'school_admin', 'مدیر دیگر' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		HSUM_Membership::set_status( $m2, 'active' );
		$t2 = $this->teacher();
		HS_SO_Store::add_staff( $sid, $t2, 'deputy_edu' );
		$this->assertStatus( 403, $this->call( 'DELETE', '/school/staff/' . (int) HS_SO_Store::staff_row_by_user( $t2 )['id'], array(), $m2 ) );
	}
}
