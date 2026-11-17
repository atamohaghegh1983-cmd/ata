<?php
/** ساخت کلاس از اپ */
class ClassCreateTest extends HS_Test_Case {

	private function payload( $extra = array() ) {
		return array_merge( array( 'title' => 'سوم الف', 'grade' => HS_Class_Meta::grades()[3], 'school' => 'دبستان امید', 'province' => HS_Util::provinces()[0], 'city' => 'شهر' ), $extra );
	}

	public function test_teacher_creates_class_and_it_is_listed() {
		$t   = $this->teacher();
		$opt = $this->call( 'GET', '/classes/options', array(), $t );
		$this->assertTrue( $opt->get_data()['can_create'] );
		$this->assertNotEmpty( $opt->get_data()['grades'] );
		$res = $this->call( 'POST', '/classes', $this->payload(), $t );
		$this->assertStatus( 201, $res );
		$cid = $res->get_data()['class']['id'];
		$this->assertSame( 'write', $res->get_data()['class']['access'] );
		$this->assertSame( $t, (int) get_post_meta( $cid, '_hs_teacher_id', true ) );
		$this->assertNotEmpty( get_post_meta( $cid, '_hs_invite_code', true ) );
		$this->assertContains( $cid, wp_list_pluck( $this->call( 'GET', '/classes', array(), $t )->get_data(), 'id' ) );
		$this->assertStatus( 200, $this->call( 'GET', '/classes/' . $cid . '/invite', array(), $t ) );
	}

	public function test_validation() {
		$t = $this->teacher();
		$this->assertStatus( 400, $this->call( 'POST', '/classes', $this->payload( array( 'title' => '' ) ), $t ) );
		$this->assertStatus( 400, $this->call( 'POST', '/classes', $this->payload( array( 'city' => '' ) ), $t ) );
		$this->assertStatus( 400, $this->call( 'POST', '/classes', $this->payload( array( 'grade' => 'xyz' ) ), $t ) );
	}

	public function test_parent_and_student_cannot_create() {
		$this->assertStatus( 403, $this->call( 'POST', '/classes', $this->payload(), $this->user( 'parent' ) ) );
		$this->assertStatus( 403, $this->call( 'POST', '/classes', $this->payload(), $this->user( 'student' ) ) );
		$this->assertStatus( 401, $this->call( 'POST', '/classes', $this->payload(), 0 ) );
	}
}
