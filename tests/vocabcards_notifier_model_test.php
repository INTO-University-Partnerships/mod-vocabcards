<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../models/vocabcards_notifier_model.php';

class vocabcards_notifier_model_test extends advanced_testcase {

    /**
     * @var array
     */
    protected $_courses;

    /**
     * @var array
     */
    protected $_sids;

    /**
     * @var array
     */
    protected $_groups;

    /**
     * @var array
     */
    protected $_modules;

    /**
     * @var array
     */
    protected $_students;

    /**
     * @var array
     */
    protected $_tutors;

    /**
     * @var integer
     */
    protected $_tutor_role_id;

    /**
     * @var integer
     */
    protected $_assigner_role_id;

    /**
     * @var integer
     */
    protected $_now;

    /**
     * @var vocabcards_notifier_model
     */
    protected $_cut;

    /**
     * setUp
     * @global moodle_database $DB
     */
    protected function setUp() {
        global $DB;

        $this->_now = time();
        $this->_cut = new vocabcards_notifier_model();

        // create two courses (the first using groups, the second not using groups)
        $this->_courses = array_map(function ($i) use ($DB) {
            return $this->getDataGenerator()->create_course(array(
                'numsections' => 2,
            ), array(
                'createsections' => true,
            ));
        }, range(1, 2));

        // section ids
        $this->_sids = array();
        $this->_sids[0] = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($this->_courses[0]->id));
        $this->_sids[1] = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($this->_courses[1]->id));

        // create two groups in the first and second courses
        $g1 = array_map(function ($i) use ($DB) {
            return $this->getDataGenerator()->create_group(array(
                'courseid' => $this->_courses[0]->id,
            ));
        }, range(1, 2));

        // create two groups in the second course
        $g2 = array_map(function ($i) use ($DB) {
            return $this->getDataGenerator()->create_group(array(
                'courseid' => $this->_courses[1]->id,
            ));
        }, range(1, 2));
        $this->_groups = array_merge($g1, $g2);

        // create two course modules each course
        $this->_modules = array();
        $this->_modules[] = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_courses[0]->id,
            'startdate' => mktime(0, 0, 0, 8, 4, 2014),
        ), array(
            'section' => $DB->get_field('course_sections', 'section', array('id' => $this->_sids[0][0]), MUST_EXIST),
        ));
        $this->_modules[] = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_courses[0]->id,
            'startdate' => mktime(0, 0, 0, 8, 11, 2014),
        ), array(
            'section' => $DB->get_field('course_sections', 'section', array('id' => $this->_sids[0][1]), MUST_EXIST),
        ));
        $this->_modules[] = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_courses[1]->id,
            'startdate' => mktime(0, 0, 0, 8, 18, 2014),
        ), array(
            'section' => $DB->get_field('course_sections', 'section', array('id' => $this->_sids[1][0]), MUST_EXIST),
        ));
        $this->_modules[] = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_courses[1]->id,
            'startdate' => mktime(0, 0, 0, 8, 25, 2014),
        ), array(
            'section' => $DB->get_field('course_sections', 'section', array('id' => $this->_sids[1][1]), MUST_EXIST),
        ));

        // create students in both courses
        $this->_students = array_map(function ($i) use ($DB) {
            // create a student
            $student = $this->getDataGenerator()->create_user();

            // enrol student on both courses
            foreach (range(0, 1) as $i) {
                $this->getDataGenerator()->enrol_user($student->id, $this->_courses[$i]->id, $DB->get_field('role', 'id', array(
                    'shortname' => 'student',
                )));
            }

            // put students into groups in the first course
            $this->getDataGenerator()->create_group_member(array(
                'groupid' => $i < 5 ? $this->_groups[0]->id : $this->_groups[1]->id,
                'userid' => $student->id,
            ));

            // return the student
            return $student;
        }, range(1, 10));

        // create tutor role
        $this->_tutor_role_id = create_role('Tutor', 'tutor', 'Tutor description goes here');

        // create assigner role
        $this->_assigner_role_id = create_role('Assigner', 'assigner', 'Assigner description goes here');

        // create tutors in both courses
        $this->_tutors = array_map(function ($i) use ($DB) {
            $tutor = $this->getDataGenerator()->create_user();

            // enrol tutor on both courses
            foreach (range(0, 1) as $j) {
                $this->getDataGenerator()->enrol_user($tutor->id, $this->_courses[$j]->id, $DB->get_field('role', 'id', array(
                    'shortname' => 'tutor',
                )));
            }

            // enrol tutor on one of the two groups in each course
            $this->getDataGenerator()->create_group_member(array(
                'groupid' => $this->_groups[$i]->id,
                'userid' => $tutor->id,
            ));
            $this->getDataGenerator()->create_group_member(array(
                'groupid' => $this->_groups[2 + $i]->id,
                'userid' => $tutor->id,
            ));

            return $tutor;
        }, range(0, 1));

        $this->resetAfterTest(true);
    }

    /**
     * tests instantiation
     */
    public function test_vocabcards_notifier_model_instantiation() {
        $this->assertInstanceOf('vocabcards_notifier_model', $this->_cut);
    }

    /**
     * tests getting tutors that have cards to review
     * 1. when there are no cards in review, there's nothing to review
     */
    public function test_get_tutors_that_have_cards_to_review_1() {
        // syllabus
        $vocabcards_syllabus = array(
            array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
            array(1, $this->_courses[0]->id, $this->_sids[0][0],   'dog', 2, $this->_now, $this->_now),
            array(2, $this->_courses[0]->id, $this->_sids[0][0],   'cat', 2, $this->_now, $this->_now),
            array(3, $this->_courses[0]->id, $this->_sids[0][0],  'lion', 2, $this->_now, $this->_now),
            array(4, $this->_courses[1]->id, $this->_sids[1][0], 'mouse', 2, $this->_now, $this->_now),
            array(5, $this->_courses[1]->id, $this->_sids[1][0],  'fish', 2, $this->_now, $this->_now),
            array(6, $this->_courses[1]->id, $this->_sids[1][0],   'ant', 2, $this->_now, $this->_now),
        );

        // cards
        $vocabcards_card = array(
            array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'),
            array(1, 1, $this->_students[0]->id, 2, null, vocabcard_status::NOT_STARTED, $this->_now, $this->_now),
            array(2, 2, $this->_students[1]->id, 2, null, vocabcard_status::IN_PROGRESS, $this->_now, $this->_now),
            array(3, 3, $this->_students[2]->id, 2, null, vocabcard_status::IN_REPOSITORY, $this->_now, $this->_now),
            array(4, 4, $this->_students[0]->id, 2, null, vocabcard_status::NOT_STARTED, $this->_now, $this->_now),
            array(5, 5, $this->_students[1]->id, 2, null, vocabcard_status::IN_PROGRESS, $this->_now, $this->_now),
            array(6, 6, $this->_students[2]->id, 2, null, vocabcard_status::IN_REPOSITORY, $this->_now, $this->_now),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_card' => $vocabcards_card,
            'vocabcards_syllabus' => $vocabcards_syllabus
        )));

        // no cards in review means nothing to review
        $results = $this->_cut->get_tutors_that_have_cards_to_review();
        $this->assertEmpty($results);
    }

    /**
     * tests getting tutors that have cards to review
     * 2. when there are 2 cards in review in course 1 and 3 cards in review in course 2
     */
    public function test_get_tutors_that_have_cards_to_review_2() {
        // syllabus
        $vocabcards_syllabus = array(
            array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
            array(1, $this->_courses[0]->id, $this->_sids[0][0],    'dog', 2, $this->_now, $this->_now),
            array(2, $this->_courses[0]->id, $this->_sids[0][0],    'cat', 2, $this->_now, $this->_now),
            array(3, $this->_courses[0]->id, $this->_sids[0][0],   'lion', 2, $this->_now, $this->_now),
            array(4, $this->_courses[0]->id, $this->_sids[0][1],    'pig', 2, $this->_now, $this->_now),
            array(5, $this->_courses[1]->id, $this->_sids[1][0],  'mouse', 2, $this->_now, $this->_now),
            array(6, $this->_courses[1]->id, $this->_sids[1][0],   'fish', 2, $this->_now, $this->_now),
            array(7, $this->_courses[1]->id, $this->_sids[1][0],    'ant', 2, $this->_now, $this->_now),
            array(8, $this->_courses[1]->id, $this->_sids[1][0], 'spider', 2, $this->_now, $this->_now),
            array(9, $this->_courses[1]->id, $this->_sids[1][1],   'wolf', 2, $this->_now, $this->_now),
        );

        // cards
        $vocabcards_card = array(
            array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'),
            array(1, 1, $this->_students[0]->id, 2, null, vocabcard_status::IN_PROGRESS, $this->_now, $this->_now),
            array(2, 2, $this->_students[1]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(3, 3, $this->_students[2]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(4, 4, $this->_students[3]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(5, 5, $this->_students[0]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(6, 6, $this->_students[1]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(7, 7, $this->_students[2]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(8, 8, $this->_students[3]->id, 2, null, vocabcard_status::IN_REPOSITORY, $this->_now, $this->_now),
            array(9, 9, $this->_students[4]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_card' => $vocabcards_card,
            'vocabcards_syllabus' => $vocabcards_syllabus
        )));

        // check the count of results
        $results = $this->_cut->get_tutors_that_have_cards_to_review();
        $tutors_in_first_course = 2;
        $modules_in_first_course = 2;
        $tutors_in_second_course = 2;
        $modules_in_second_course = 2;
        $this->assertCount($tutors_in_first_course * $modules_in_first_course + $tutors_in_second_course * $modules_in_second_course, $results);

        // check each count of cards in the first module (which is in the first section of the first course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[0]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[0]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->card_count);

        // check each count of cards in the second module (which is in the second section of the first course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[1]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[1]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);

        // check each count of cards in the third module (which is in the first section of the second course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[2]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(3, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[2]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(3, $results[$key]->card_count);

        // check each count of cards in the fourth module (which is in the second section of the second course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[3]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[3]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);
    }

    /**
     * tests getting tutors that have cards to review
     * 3. when some cards have been assigned to groups but others have not
     */
    public function test_get_tutors_that_have_cards_to_review_3() {
        // syllabus
        $vocabcards_syllabus = array(
            array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
            array( 1, $this->_courses[0]->id, $this->_sids[0][0],    'dog', 2, $this->_now, $this->_now),
            array( 2, $this->_courses[0]->id, $this->_sids[0][0],    'cat', 2, $this->_now, $this->_now),
            array( 3, $this->_courses[0]->id, $this->_sids[0][0],   'lion', 2, $this->_now, $this->_now),
            array( 4, $this->_courses[0]->id, $this->_sids[0][0],  'horse', 2, $this->_now, $this->_now),
            array( 5, $this->_courses[0]->id, $this->_sids[0][1],    'bat', 2, $this->_now, $this->_now),
            array( 6, $this->_courses[1]->id, $this->_sids[1][0],  'mouse', 2, $this->_now, $this->_now),
            array( 7, $this->_courses[1]->id, $this->_sids[1][0],   'fish', 2, $this->_now, $this->_now),
            array( 8, $this->_courses[1]->id, $this->_sids[1][0],    'ant', 2, $this->_now, $this->_now),
            array( 9, $this->_courses[1]->id, $this->_sids[1][0], 'spider', 2, $this->_now, $this->_now),
            array(10, $this->_courses[1]->id, $this->_sids[1][1], 'monkey', 2, $this->_now, $this->_now),
        );

        // cards
        $vocabcards_card = array(
            array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'),
            array( 1,  1, $this->_students[0]->id, 2, $this->_groups[0]->id, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array( 2,  2, $this->_students[1]->id, 2, $this->_groups[0]->id, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array( 3,  3, $this->_students[2]->id, 2, $this->_groups[1]->id, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array( 4,  4, $this->_students[3]->id, 2,                  null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array( 5,  5, $this->_students[4]->id, 2,                  null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array( 6,  6, $this->_students[0]->id, 2, $this->_groups[2]->id, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array( 7,  8, $this->_students[1]->id, 2, $this->_groups[2]->id, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array( 8,  8, $this->_students[2]->id, 2, $this->_groups[2]->id, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array( 9,  9, $this->_students[3]->id, 2, $this->_groups[3]->id, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(10, 10, $this->_students[4]->id, 2, $this->_groups[2]->id, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_card' => $vocabcards_card,
            'vocabcards_syllabus' => $vocabcards_syllabus
        )));

        // get results
        $results = $this->_cut->get_tutors_that_have_cards_to_review();

        // check each count of cards in the first module (which is in the first section of the first course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[0]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(3, $results[$key]->card_count); // two cards in group 0 and one card in no group
        $key = $this->_tutors[1]->id . '_' . $this->_modules[0]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->card_count); // one card in group 1 and one card in no group

        // check each count of cards in the second module (which is in the second section of the first course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[1]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[1]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);

        // check each count of cards in the third module (which is in the first section of the second course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[2]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(3, $results[$key]->card_count); // three cards in group 2
        $key = $this->_tutors[1]->id . '_' . $this->_modules[2]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count); // one card in group 3

        // check each count of cards in the fourth module (which is in the second section of the second course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[3]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count); // one card in group 2
    }

    /**
     * tests getting tutors that have cards to review
     * 4. when more than one course module has been added to the same section
     * @global moodle_database $DB
     */
    public function test_get_tutors_that_have_cards_to_review_4() {
        global $DB;

        // create another course module in each of the two sections in the first course
        $this->_modules[] = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_courses[0]->id,
        ), array(
            'section' => $DB->get_field('course_sections', 'section', array('id' => $this->_sids[0][0]), MUST_EXIST),
        ));
        $this->_modules[] = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_courses[0]->id,
        ), array(
            'section' => $DB->get_field('course_sections', 'section', array('id' => $this->_sids[0][1]), MUST_EXIST),
        ));

        // syllabus
        $vocabcards_syllabus = array(
            array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
            array(1, $this->_courses[0]->id, $this->_sids[0][0],  'dog', 2, $this->_now, $this->_now),
            array(2, $this->_courses[0]->id, $this->_sids[0][0],  'cat', 2, $this->_now, $this->_now),
            array(3, $this->_courses[0]->id, $this->_sids[0][0], 'lion', 2, $this->_now, $this->_now),
            array(4, $this->_courses[0]->id, $this->_sids[0][1],  'pig', 2, $this->_now, $this->_now),
        );

        // cards
        $vocabcards_card = array(
            array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'),
            array(1, 1, $this->_students[0]->id, 2, null, vocabcard_status::IN_PROGRESS, $this->_now, $this->_now),
            array(2, 2, $this->_students[1]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(3, 3, $this->_students[2]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
            array(4, 4, $this->_students[3]->id, 2, null, vocabcard_status::IN_REVIEW, $this->_now, $this->_now),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_card' => $vocabcards_card,
            'vocabcards_syllabus' => $vocabcards_syllabus
        )));

        // check the count of results
        $results = $this->_cut->get_tutors_that_have_cards_to_review();
        $tutors_in_first_course = 2;
        $modules_in_first_course = 4;
        $this->assertCount($tutors_in_first_course * $modules_in_first_course, $results);

        // check each count of cards in the first module (which is in the first section of the first course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[0]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[0]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->card_count);

        // check each count of cards in the second module (which is in the second section of the first course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[1]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[1]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);

        // check each count of cards in the first additional module (which is in the first section of the first course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[4]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[4]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->card_count);

        // check each count of cards in the second additonal module (which is in the second section of the first course)
        $key = $this->_tutors[0]->id . '_' . $this->_modules[5]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);
        $key = $this->_tutors[1]->id . '_' . $this->_modules[5]->cmid;
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->card_count);
    }

    /**
     * test getting tutors that have words to assign
     * 1. when no cards exist, all words in the syllabus need to be assigned
     */
    public function test_get_tutors_that_have_words_to_assign_1() {
        // syllabus
        $vocabcards_syllabus = array(
            array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
            array( 1, $this->_courses[0]->id, $this->_sids[0][0],    'dog', 2, $this->_now, $this->_now),
            array( 2, $this->_courses[0]->id, $this->_sids[0][0],    'cat', 2, $this->_now, $this->_now),
            array( 3, $this->_courses[0]->id, $this->_sids[0][0],   'lion', 2, $this->_now, $this->_now),
            array( 4, $this->_courses[0]->id, $this->_sids[0][0],  'mouse', 2, $this->_now, $this->_now),
            array( 5, $this->_courses[0]->id, $this->_sids[0][0],   'fish', 2, $this->_now, $this->_now),
            array( 6, $this->_courses[0]->id, $this->_sids[0][1],    'ant', 2, $this->_now, $this->_now),
            array( 7, $this->_courses[0]->id, $this->_sids[0][1], 'spider', 2, $this->_now, $this->_now),
            array( 8, $this->_courses[1]->id, $this->_sids[1][0],    'bat', 2, $this->_now, $this->_now),
            array( 9, $this->_courses[1]->id, $this->_sids[1][1],  'snail', 2, $this->_now, $this->_now),
            array(10, $this->_courses[1]->id, $this->_sids[1][1],  'horse', 2, $this->_now, $this->_now),
            array(11, $this->_courses[1]->id, $this->_sids[1][1],  'whale', 2, $this->_now, $this->_now),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus
        )));

        // check the count of results
        $t0 = 0;
        $t1 = mktime(0, 0, 0, 1, 1, 2100);
        $results = $this->_cut->get_tutors_that_have_words_to_assign($t0, $t1);
        $tutors_in_first_course = 2;
        $sections_in_first_course = 2;
        $tutors_in_second_course = 2;
        $sections_in_second_course = 2;
        $this->assertCount($tutors_in_first_course * $sections_in_first_course + $tutors_in_second_course * $sections_in_second_course, $results);

        // check each count of words in the first section of the first course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[0][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(5, $results[$key]->word_count);
        $key = $this->_tutors[1]->id . '_' . $this->_sids[0][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(5, $results[$key]->word_count);

        // check each count of words in the second section of the first course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[0][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->word_count);
        $key = $this->_tutors[1]->id . '_' . $this->_sids[0][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->word_count);

        // check each count of words in the first section of the second course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[1][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->word_count);
        $key = $this->_tutors[1]->id . '_' . $this->_sids[1][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1, $results[$key]->word_count);

        // check each count of words in the second section of the second course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[1][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(3, $results[$key]->word_count);
        $key = $this->_tutors[1]->id . '_' . $this->_sids[1][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(3, $results[$key]->word_count);
    }

    /**
     * test getting tutors that have words to assign
     * 2. when some cards exist, not all words in the syllabus need to be assigned
     */
    public function test_get_tutors_that_have_words_to_assign_2() {
        // syllabus
        $vocabcards_syllabus = array(
            array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
            array( 1, $this->_courses[0]->id, $this->_sids[0][0],    'dog', 2, $this->_now, $this->_now),
            array( 2, $this->_courses[0]->id, $this->_sids[0][0],    'cat', 2, $this->_now, $this->_now),
            array( 3, $this->_courses[0]->id, $this->_sids[0][0],   'lion', 2, $this->_now, $this->_now),
            array( 4, $this->_courses[0]->id, $this->_sids[0][0],  'mouse', 2, $this->_now, $this->_now),
            array( 5, $this->_courses[0]->id, $this->_sids[0][0],   'fish', 2, $this->_now, $this->_now),
            array( 6, $this->_courses[0]->id, $this->_sids[0][1],    'ant', 2, $this->_now, $this->_now),
            array( 7, $this->_courses[0]->id, $this->_sids[0][1], 'spider', 2, $this->_now, $this->_now),
            array( 8, $this->_courses[1]->id, $this->_sids[1][0],    'bat', 2, $this->_now, $this->_now),
            array( 9, $this->_courses[1]->id, $this->_sids[1][1],  'snail', 2, $this->_now, $this->_now),
            array(10, $this->_courses[1]->id, $this->_sids[1][1],  'horse', 2, $this->_now, $this->_now),
            array(11, $this->_courses[1]->id, $this->_sids[1][1],  'whale', 2, $this->_now, $this->_now),
        );

        // cards
        $vocabcards_card = array(
            array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'),
            array( 1,  1, $this->_students[0]->id, 2, null, vocabcard_status::NOT_STARTED, $this->_now, $this->_now),
            array( 2,  2, $this->_students[1]->id, 2, null, vocabcard_status::NOT_STARTED, $this->_now, $this->_now),
            array( 3,  7, $this->_students[2]->id, 2, null, vocabcard_status::NOT_STARTED, $this->_now, $this->_now),
            array( 4, 10, $this->_students[3]->id, 2, null, vocabcard_status::NOT_STARTED, $this->_now, $this->_now),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_card' => $vocabcards_card,
            'vocabcards_syllabus' => $vocabcards_syllabus
        )));

        // check the count of results
        $t0 = 0;
        $t1 = mktime(0, 0, 0, 1, 1, 2100);
        $results = $this->_cut->get_tutors_that_have_words_to_assign($t0, $t1);
        $tutors_in_first_course = 2;
        $sections_in_first_course = 2;
        $tutors_in_second_course = 2;
        $sections_in_second_course = 2;
        $this->assertCount($tutors_in_first_course * $sections_in_first_course + $tutors_in_second_course * $sections_in_second_course, $results);

        // check each count of words in the first section of the first course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[0][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(5 - 2, $results[$key]->word_count); // 2 cards exist
        $key = $this->_tutors[1]->id . '_' . $this->_sids[0][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(5 - 2, $results[$key]->word_count); // 2 cards exist

        // check each count of words in the second section of the second course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[0][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2 - 1, $results[$key]->word_count); // 1 card exists
        $key = $this->_tutors[1]->id . '_' . $this->_sids[0][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2 - 1, $results[$key]->word_count); // 1 card exists

        // check each count of words in the first section of the second course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[1][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1 - 0, $results[$key]->word_count); // no cards exist
        $key = $this->_tutors[1]->id . '_' . $this->_sids[1][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(1 - 0, $results[$key]->word_count); // no cards exist

        // check each count of words in the second section of the second course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[1][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(3 - 1, $results[$key]->word_count); // 1 card exists
        $key = $this->_tutors[1]->id . '_' . $this->_sids[1][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(3 - 1, $results[$key]->word_count); // 1 card exists
    }

    /**
     * test getting tutors that have words to assign
     * 3. when more than one course module has been added to the same section
     * @global moodle_database $DB
     */
    public function test_get_tutors_that_have_words_to_assign_3() {
        global $DB;

        // create another course module in each of the two sections in the first course
        $this->_modules[] = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_courses[0]->id,
        ), array(
            'section' => $DB->get_field('course_sections', 'section', array('id' => $this->_sids[0][0]), MUST_EXIST),
        ));
        $this->_modules[] = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_courses[0]->id,
        ), array(
            'section' => $DB->get_field('course_sections', 'section', array('id' => $this->_sids[0][1]), MUST_EXIST),
        ));

        // syllabus
        $vocabcards_syllabus = array(
            array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
            array( 1, $this->_courses[0]->id, $this->_sids[0][0],    'dog', 2, $this->_now, $this->_now),
            array( 2, $this->_courses[0]->id, $this->_sids[0][0],    'cat', 2, $this->_now, $this->_now),
            array( 3, $this->_courses[0]->id, $this->_sids[0][0],   'lion', 2, $this->_now, $this->_now),
            array( 4, $this->_courses[0]->id, $this->_sids[0][0],  'mouse', 2, $this->_now, $this->_now),
            array( 5, $this->_courses[0]->id, $this->_sids[0][0],   'fish', 2, $this->_now, $this->_now),
            array( 6, $this->_courses[0]->id, $this->_sids[0][1],    'ant', 2, $this->_now, $this->_now),
            array( 7, $this->_courses[0]->id, $this->_sids[0][1], 'spider', 2, $this->_now, $this->_now),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus
        )));

        // check the count of results
        $t0 = 0;
        $t1 = mktime(0, 0, 0, 1, 1, 2100);
        $results = $this->_cut->get_tutors_that_have_words_to_assign($t0, $t1);
        $tutors_in_first_course = 2;
        $sections_in_first_course = 2;
        $this->assertCount($tutors_in_first_course * $sections_in_first_course, $results);

        // check each count of words in the first section of the first course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[0][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(5, $results[$key]->word_count);
        $key = $this->_tutors[1]->id . '_' . $this->_sids[0][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(5, $results[$key]->word_count);

        // check each count of words in the second section of the first course
        $key = $this->_tutors[0]->id . '_' . $this->_sids[0][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->word_count);
        $key = $this->_tutors[1]->id . '_' . $this->_sids[0][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->word_count);
    }

    /**
     * test getting tutors that have words to assign
     * 4. when a (finite) date range is given, corresponding to vocabulary cards activity start dates
     * @global moodle_database $DB
     */
    public function test_get_tutors_that_have_words_to_assign_4() {
        // syllabus
        $vocabcards_syllabus = array(
            array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
            array( 1, $this->_courses[0]->id, $this->_sids[0][0],    'dog', 2, $this->_now, $this->_now),
            array( 2, $this->_courses[0]->id, $this->_sids[0][0],    'cat', 2, $this->_now, $this->_now),
            array( 3, $this->_courses[0]->id, $this->_sids[0][0],   'lion', 2, $this->_now, $this->_now),
            array( 4, $this->_courses[0]->id, $this->_sids[0][0],  'mouse', 2, $this->_now, $this->_now),
            array( 5, $this->_courses[0]->id, $this->_sids[0][0],   'fish', 2, $this->_now, $this->_now),
            array( 6, $this->_courses[0]->id, $this->_sids[0][1],    'ant', 2, $this->_now, $this->_now),
            array( 7, $this->_courses[0]->id, $this->_sids[0][1], 'spider', 2, $this->_now, $this->_now),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus
        )));

        // date range for the first module in the first course
        $t0 = mktime(0, 0, 0, 8, 3, 2014);
        $t1 = mktime(0, 0, 0, 8, 5, 2014);
        $results = $this->_cut->get_tutors_that_have_words_to_assign($t0, $t1);
        $this->assertCount(2, $results);
        $key = $this->_tutors[0]->id . '_' . $this->_sids[0][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(5, $results[$key]->word_count);
        $key = $this->_tutors[1]->id . '_' . $this->_sids[0][0];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(5, $results[$key]->word_count);

        // date range for no modules
        $t0 = mktime(0, 0, 0, 8, 6, 2014);
        $t1 = mktime(0, 0, 0, 8, 7, 2014);
        $results = $this->_cut->get_tutors_that_have_words_to_assign($t0, $t1);
        $this->assertEmpty($results);

        // date range for the second module in the first course
        $t0 = mktime(0, 0, 0, 8, 10, 2014);
        $t1 = mktime(0, 0, 0, 8, 12, 2014);
        $results = $this->_cut->get_tutors_that_have_words_to_assign($t0, $t1);
        $this->assertCount(2, $results);
        $key = $this->_tutors[0]->id . '_' . $this->_sids[0][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->word_count);
        $key = $this->_tutors[1]->id . '_' . $this->_sids[0][1];
        $this->assertArrayHasKey($key, $results);
        $this->assertEquals(2, $results[$key]->word_count);

        // date range for both modules in the first course
        $t0 = mktime(0, 0, 0, 8, 3, 2014);
        $t1 = mktime(0, 0, 0, 8, 12, 2014);
        $results = $this->_cut->get_tutors_that_have_words_to_assign($t0, $t1);
        $this->assertCount(4, $results);
    }

}
