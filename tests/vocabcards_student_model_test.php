<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../models/vocabcards_student_model.php';

class vocabcards_student_model_test extends advanced_testcase {

    /**
     * @var integer
     */
    protected $_now;

    /**
     * @var vocabcards_student_model
     */
    protected $_cut;

    /**
     * setUp
     * @global moodle_database $DB
     */
    protected function setUp() {
        $this->_now = time();
        $this->_cut = new vocabcards_student_model();
        $this->resetAfterTest(true);
    }

    /**
     * tests instantiation
     */
    public function test_vocabcards_student_model_instantiation() {
        $this->assertInstanceOf('vocabcards_student_model', $this->_cut);
    }

    /**
     * tests getting students from a course with no students
     */
    public function test_get_students_in_course_with_no_enrolled_students() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a teacher (to ensure the teacher is included)
        $teacher = $this->getDataGenerator()->create_user();

        // enrol the teacher
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // check there were no students (only one teacher)
        $records = $this->_cut->get_students_in_course($course->id, $teacher->id);
        $this->assertCount(1, $records);
    }

    /**
     * tests getting a total of students in course with various group filters
     * @global moodle_database $DB
     */
    public function test_get_student_total_by_courseid() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create two groups
        $group1 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));
        $group2 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // create ten students
        $students = array_map(function ($i) use ($DB, $course, $group1, $group2) {
            // create a student
            $student = $this->getDataGenerator()->create_user();

            // enrol the student
            $this->getDataGenerator()->enrol_user($student->id, $course->id, $DB->get_field('role', 'id', array(
                'shortname' => 'student',
            )));

            // put the first 4 students in one group and the next 6 in another
            $this->getDataGenerator()->create_group_member(array(
                'groupid' => $i < 5 ? $group1->id : $group2->id,
                'userid' => $student->id,
            ));

            // return the student
            return $student;
        }, range(1, 10));

        // create a teacher (to ensure the teacher is included)
        $teacher = $this->getDataGenerator()->create_user();

        // enrol the teacher
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // check all students
        $total = $this->_cut->get_student_total_by_courseid($course->id);
        $this->assertEquals(count($students) + 1, $total);
        $total = $this->_cut->get_student_total_by_courseid($course->id, array($group1->id, $group2->id));
        $this->assertEquals(count($students) + 1, $total);

        // check students in group 1
        $total = $this->_cut->get_student_total_by_courseid($course->id, null, $group1->id);
        $this->assertEquals(4 + 1, $total);
        $total = $this->_cut->get_student_total_by_courseid($course->id, array($group1->id));
        $this->assertEquals(4 + 1, $total);

        // check students in group 2
        $total = $this->_cut->get_student_total_by_courseid($course->id, null, $group2->id);
        $this->assertEquals(6 + 1, $total);
        $total = $this->_cut->get_student_total_by_courseid($course->id, array($group2->id));
        $this->assertEquals(6 + 1, $total);
    }

    /**
     * tests getting students in course with various group filters
     * @global moodle_database $DB
     */
    public function test_get_students_in_course() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create two groups
        $group1 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));
        $group2 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // create ten students
        $students = array_map(function ($i) use ($DB, $course, $group1, $group2) {
            // create a student
            $student = $this->getDataGenerator()->create_user();

            // enrol the student
            $this->getDataGenerator()->enrol_user($student->id, $course->id, $DB->get_field('role', 'id', array(
                'shortname' => 'student',
            )));

            // put the first 4 students in one group and the next 6 in another
            $this->getDataGenerator()->create_group_member(array(
                'groupid' => $i < 5 ? $group1->id : $group2->id,
                'userid' => $student->id,
            ));

            // return the student
            return $student;
        }, range(1, 10));

        // create a teacher (to ensure the teacher is included)
        $teacher = $this->getDataGenerator()->create_user();
        $students[] = $teacher;

        // enrol the teacher
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // check all students
        $records = $this->_cut->get_students_in_course($course->id, $teacher->id);
        $this->assertCount(count($students), $records);
        $this->_assert_same_ids($records, $students);
        $records = $this->_cut->get_students_in_course($course->id, $teacher->id, array($group1->id, $group2->id));
        $this->assertCount(count($students), $records);
        $this->_assert_same_ids($records, $students);

        // check students in group 1
        $records = $this->_cut->get_students_in_course($course->id, $teacher->id, null, $group1->id);
        $expected_students = array_slice($students, 0, 4 + 1);
        $this->_assert_same_ids($records, $expected_students);
        $records = $this->_cut->get_students_in_course($course->id, $teacher->id, array($group1->id));
        $expected_students = array_slice($students, 0, 4 + 1);
        $this->_assert_same_ids($records, $expected_students);

        // check students in group 2
        $records = $this->_cut->get_students_in_course($course->id, $teacher->id, null, $group2->id);
        $expected_students = array_slice($students, 4, 6 + 1);
        $this->_assert_same_ids($records, $expected_students);
        $records = $this->_cut->get_students_in_course($course->id, $teacher->id, array($group2->id));
        $expected_students = array_slice($students, 4, 6 + 1);
        $this->_assert_same_ids($records, $expected_students);
    }

    /**
     * tests getting groups in course
     */
    public function test_get_groups_in_course() {
        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create some groups
        $groups = array_map(function ($i) use ($course) {
            $group = $this->getDataGenerator()->create_group(array(
                'courseid' => $course->id,
            ));
            return $group;
        }, range(1, 5));

        // get groups
        $records = $this->_cut->get_groups_in_course($course->id);
        $this->assertCount(count($groups), $records);
        $this->_assert_same_ids($records, $groups);
    }

    /**
     * tests getting user groups in course
     * @global moodle_database $DB
     */
    public function test_get_user_groups_in_course() {
        global $DB;

        // create a user
        $teacher = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user (for this test, it doesn't really matter which role, as long as the user is enrolled on the course)
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create some groups
        $groups = array_map(function ($i) use ($course) {
            $group = $this->getDataGenerator()->create_group(array(
                'courseid' => $course->id,
            ));
            return $group;
        }, range(1, 5));

        // put the user in some of the groups
        $member_groups = array($groups[0], $groups[1]);
        array_map(function ($group) use ($teacher) {
            return groups_add_member($group, $teacher->id);
        }, $member_groups);

        // get groups
        $records = $this->_cut->get_user_groups_in_course($course->id, $teacher->id);
        $this->assertCount(count($member_groups), $records);
        $this->_assert_same_ids($records, $member_groups);
    }

    /**
     * @param array $records
     * @param array $expected
     */
    protected function _assert_same_ids(array $records, array $expected) {
        $this->assertCount(count($expected), $records);
        $expected_ids = array_map(function ($expected_thing) {
            return $expected_thing->id;
        }, $expected);
        $actual_ids = array_map(function ($actual_thing) {
            return $actual_thing['id'];
        }, $records);
        $actual_ids = array_values($actual_ids);
        $this->assertEquals(sort($expected_ids), sort($actual_ids));
    }

}
