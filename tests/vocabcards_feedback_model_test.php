<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../models/vocabcards_feedback_model.php';
require_once __DIR__ . '/../src/vocabcard_status.php';

class vocabcards_feedback_model_test extends advanced_testcase {

    /**
     * @var object
     */
    protected $_teacher;

    /**
     * @var vocabcards_feedback_model
     */
    protected $_cut;

    /**
     * setUp
     * @global moodle_database $DB
     */
    protected function setUp() {
        // create a teacher
        $this->_teacher = $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array(1, $course->id, 1, 'table', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
                array(2, $course->id, 1, 'chair', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
                array(3, $course->id, 1, 'banana', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
                array(2, 2, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
                array(3, 3, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('id', 'cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, 1, $teacher->id, 'Feedback 1', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
                array(2, 1, $teacher->id, 'Feedback 2', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
                array(3, 1, $teacher->id, 'Feedback 3', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
                array(4, 2, $teacher->id, 'Feedback 4', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
            ),
        )));

        // instantiate the class under test
        $this->_cut = new vocabcards_feedback_model();

        // reset the database after each test
        $this->resetAfterTest(true);
    }

    /**
     * tests instantiation
     */
    public function test_vocabcards_feedback_model_instantiation() {
        $this->assertInstanceOf('vocabcards_feedback_model', $this->_cut);
    }

    /**
     * tests delete
     * @global moodle_database $DB
     */
    public function test_delete() {
        global $DB;

        $this->_cut->delete(1);
        $this->assertEquals(3, $DB->count_records('vocabcards_feedback'));

        $this->_cut->delete(2);
        $this->assertEquals(2, $DB->count_records('vocabcards_feedback'));

        $this->_cut->delete(3);
        $this->assertEquals(1, $DB->count_records('vocabcards_feedback'));

        $this->_cut->delete(4);
        $this->assertEquals(0, $DB->count_records('vocabcards_feedback'));
    }

    /**
     * @expectedException dml_missing_record_exception
     */
    public function test_delete_non_existing() {
        $this->_cut->delete(999);
    }

    /**
     * tests saving a new piece of feedback
     * @global moodle_database $DB
     */
    public function test_save_new() {
        global $DB;
        $data = array(
            'cardid' => 2,
            'userid' => $this->_teacher->id,
            'feedback' => 'Feedback 5',
        );
        $now = time();
        $data = $this->_cut->save($data, $now);
        $this->assertEquals(array(
            'id' => $data['id'],
            'cardid' => 2,
            'userid' => $this->_teacher->id,
            'feedback' => 'Feedback 5',
            'timecreated' => $now,
            'timemodified' => $now,
        ), (array)$DB->get_record('vocabcards_feedback', array('id' => $data['id'])));
    }

    /**
     * tests saving an existing piece of feedback
     * @global moodle_database $DB
     */
    public function test_save_existing() {
        global $DB;
        $data = array(
            'id' => 1,
            'feedback' => 'Feedback 1a',
        );
        $this->_cut->save($data, time());
        $this->assertEquals('Feedback 1a', $DB->get_field('vocabcards_feedback', 'feedback', array(
            'id' => 1,
        ), MUST_EXIST));
    }

    /**
     * tests getting all feedback by card
     */
    public function test_get_all_by_cardid() {
        $feedback = $this->_cut->get_all_by_cardid(1);
        $this->assertCount(3, $feedback);

        $feedback = $this->_cut->get_all_by_cardid(2);
        $this->assertCount(1, $feedback);

        $feedback = $this->_cut->get_all_by_cardid(3);
        $this->assertCount(0, $feedback);
    }

}
