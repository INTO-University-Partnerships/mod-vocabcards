<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../models/vocabcards_card_model.php';

class vocabcards_card_model_test extends advanced_testcase {

    /**
     * @var object
     */
    protected $_course;

    /**
     * @var array
     */
    protected $_groups;

    /**
     * @var array
     */
    protected $_users;

    /**
     * @var integer
     */
    protected $_now;

    /**
     * @var vocabcards_card_model
     */
    protected $_cut;

    /**
     * setUp
     * @global moodle_database $DB
     */
    protected function setUp() {
        $this->_now = time();
        $this->_cut = new vocabcards_card_model();
        $this->resetAfterTest(true);
    }

    /**
     * seeds the database with a repository of cards
     * @global moodle_database $DB
     */
    protected function _seed_db_for_repository() {
        global $DB;

        // create a course
        $this->_course = $this->getDataGenerator()->create_course();
        $courseid = $this->_course->id;

        // create 3 groups
        $this->_groups = $groups = array_map(function ($i) use ($courseid) {
            return $this->getDataGenerator()->create_group(array(
                'courseid' => $courseid,
            ));
        }, range(1, 3));

        // create 12 users, putting 6 in group 1 and the other 6 in group 2
        $this->_users = $users = array_map(function ($i) use ($DB, $groups) {
            $student = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($student->id, $this->_course->id, $DB->get_field('role', 'id', array(
                'shortname' => 'student',
            )));
            $this->getDataGenerator()->create_group_member(array(
                'groupid' => $groups[$i % 2],
                'userid' => $student->id,
            ));
            return $student;
        }, range(1, 12));

        // set an arbitrary time
        $t = mktime(9, 0, 0, 7, 1, 2014);

        // words
        $words = array('rat', 'ox', 'tiger', 'rabbit', 'dragon', 'snake', 'horse', 'sheep', 'monkey', 'rooster', 'dog', 'pig');
        $vocabcards_syllabus = array_map(function ($id, $w) use ($courseid, $t) {
            return array($id, $courseid, 1, $w, 2, $t, $t);
        }, range(1, count($words)), $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // cards
        $vocabcards_card = array(
            array('wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'tags', 'timecreated', 'timemodified'),
            array( 1, $users[ 0]->id, 2, $groups[1]->id, vocabcard_status::IN_REPOSITORY, 'teeth, plague', $t, $t),
            array( 2, $users[ 2]->id, 2, $groups[1]->id, vocabcard_status::IN_REPOSITORY, 'strong, herbivor', $t, $t),
            array( 3, $users[ 4]->id, 2, $groups[1]->id, vocabcard_status::IN_REPOSITORY, 'stripes, endangered, predator', $t, $t),
            array( 4, $users[ 6]->id, 2, $groups[1]->id, vocabcard_status::IN_REPOSITORY, 'teeth', $t, $t),
            array( 5, $users[ 8]->id, 2, $groups[1]->id, vocabcard_status::IN_REPOSITORY, 'fantastical, predator', $t, $t),
            array( 6, $users[10]->id, 2, $groups[1]->id, vocabcard_status::IN_REPOSITORY, 'reptile, predator', $t, $t),
            array( 7, $users[ 1]->id, 2, $groups[0]->id, vocabcard_status::IN_REPOSITORY, 'teeth', $t, $t),
            array( 8, $users[ 3]->id, 2, $groups[0]->id, vocabcard_status::IN_REPOSITORY, 'meek', $t, $t),
            array( 9, $users[ 5]->id, 2, $groups[0]->id, vocabcard_status::IN_REPOSITORY, 'clever', $t, $t),
            array(10, $users[ 7]->id, 2, $groups[0]->id, vocabcard_status::IN_REPOSITORY, 'noisy', $t, $t),
            array(11, $users[ 9]->id, 2, $groups[0]->id, vocabcard_status::IN_REPOSITORY, 'loyal', $t, $t),
            array(12, $users[11]->id, 2, $groups[0]->id, vocabcard_status::IN_REPOSITORY, 'tasty', $t, $t),
        );

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));
    }

    /**
     * tests instantiation
     */
    public function test_vocabcards_card_model_instantiation() {
        $this->assertInstanceOf('vocabcards_card_model', $this->_cut);
    }

    /**
     * tests creating a vocabulary card from a given word
     * @global moodle_database $DB
     */
    public function test_create_from_word() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 2,
        ), array (
            'createsections' => true,
        ));

        // create a group in the course
        $group = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // some words and corresponding section ids
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $wordids = range(1, count($words));
        $sectionids = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($course->id));
        $word_sectionids = array($sectionids[0], $sectionids[0], $sectionids[0], $sectionids[1], $sectionids[1], $sectionids[0]);

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($wordid, $word, $sectionid) use ($course, $user) {
            return array($wordid, $course->id, $sectionid, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $wordids, $words, $word_sectionids);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // create one card from each of the words
        $cardids = array_map(function ($wordid) use ($DB, $user, $group) {
            $card = $this->_cut->create_from_word($wordid, $user->id, $group->id, 2, $this->_now);
            $this->assertGreaterThan(0, $card['id']);
            return $card['id'];
        }, $wordids);

        // make sure the card count is as expected
        $counts = array_map(function ($cardid, $wordid) use ($DB, $user, $group) {
            $count = (integer)$DB->count_records('vocabcards_card', array(
                'id' => $cardid,
                'wordid' => $wordid,
                'ownerid' => $user->id,
                'assignerid' => 2,
                'groupid' => $group->id,
                'status' => vocabcard_status::NOT_STARTED,
                'timecreated' => $this->_now,
                'timemodified' => $this->_now,
            ));
            return $count;
        }, $cardids, $wordids);
        $this->assertEquals(array_fill(0, count($words), 1), $counts);
    }

    /**
     * tests that trying to create a card with the same word for the same owner does nothing
     */
    public function test_create_from_word_duplicate_word_per_owner() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a card owner
        $owner = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 2,
        ), array (
            'createsections' => true,
        ));

        // some words and corresponding section ids
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $wordids = range(1, count($words));
        $sectionids = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($course->id));
        $word_sectionids = array($sectionids[0], $sectionids[0], $sectionids[0], $sectionids[1], $sectionids[1], $sectionids[0]);

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($wordid, $word, $sectionid) use ($course, $user) {
            return array($wordid, $course->id, $sectionid, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $wordids, $words, $word_sectionids);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid) use ($owner, $user) {
            return array($wordid, $wordid, $owner->id, $user->id, null, vocabcard_status::NOT_STARTED, mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, $wordids);
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // sanity check there's now six cards in the database
        $this->assertEquals(count($words), $DB->count_records('vocabcards_card'));

        // try to create another card with the same word for a particular user
        $card = $this->_cut->create_from_word($wordids[0], $owner->id, null, 2, $this->_now);

        // check the returned card is one that already exists
        $this->assertEquals(1, $card['id']);
        $this->assertEquals($words[0], $card['word']);

        // there should still be the same number of cards in the database
        $this->assertEquals(count($words), $DB->count_records('vocabcards_card'));
    }

    /**
     * @global moodle_database $DB
     */
    public function test_get_student_cards_in_group() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a card owner
        $owner = $this->getDataGenerator()->create_user();

        // create a card owner that has no cards
        $poor_owner = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 2,
        ), array (
            'createsections' => true,
        ));

        // create a group in the course
        $group = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // some words and corresponding section ids
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $wordids = range(1, count($words));
        $sectionids = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($course->id));
        $word_sectionids = array($sectionids[0], $sectionids[0], $sectionids[0], $sectionids[1], $sectionids[1], $sectionids[0]);

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($wordid, $word, $sectionid) use ($course, $user) {
            return array($wordid, $course->id, $sectionid, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $wordids, $words, $word_sectionids);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid) use ($owner, $user, $group) {
            return array($wordid, $wordid, $owner->id, $user->id, $group->id, vocabcard_status::NOT_STARTED, mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, $wordids);
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // get the cards for the owner
        $cards = $this->_cut->get_student_cards_in_group($group->id, $owner->id);
        $this->assertCount(count($words), $cards);
        $actual_words = array_map(function ($card) {
            return $card['word'];
        }, $cards);
        $expected_words = $words;
        sort($expected_words);
        $this->assertEquals($expected_words, $actual_words);

        // get the cards for the poor owner that has none
        $cards = $this->_cut->get_student_cards_in_group($group->id, $poor_owner->id);
        $this->assertCount(0, $cards);
        $this->assertEquals(array(), $cards);
    }

    /**
     * @global moodle_database $DB
     */
    public function test_get_student_cards_in_activity() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a card owner
        $owner = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 3,
        ), array (
            'createsections' => true,
        ));

        // some words and corresponding section ids
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $wordids = range(1, count($words));
        $sectionids = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($course->id));
        $word_sectionids = array($sectionids[0], $sectionids[0], $sectionids[0], $sectionids[1], $sectionids[1], $sectionids[0]);

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($wordid, $word, $sectionid) use ($course, $user) {
            return array($wordid, $course->id, $sectionid, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $wordids, $words, $word_sectionids);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid) use ($owner, $user) {
            return array($wordid, $wordid, $owner->id, $user->id, null, vocabcard_status::NOT_STARTED, mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, $wordids);
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'));

        // create instances of the vocabulary cards activity in each of the sections
        $modules = array_map(function ($sectionid) use ($DB, $course) {
            $module = $this->getDataGenerator()->create_module('vocabcards', array(
                'course' => $course->id,
            ), array(
                'section' => $DB->get_field('course_sections', 'section', array('id' => $sectionid), MUST_EXIST),
            ));
            return $module;
        }, $sectionids);

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // get cards from the database
        $cards = array_map(function ($i) use ($modules, $owner) {
            $card = $this->_cut->get_student_cards_in_activity($modules[$i]->id, $owner->id);
            return $card;
        }, range(0, 2));

        // check the count of cards in the first activity (which is in section zero)
        $this->assertCount(4, $cards[0]);
        $actual_words = array_map(function ($card) {
            return $card['word'];
        }, $cards[0]);
        $expected_words = array('table', 'chair', 'dog', 'good');
        sort($expected_words);
        $this->assertEquals($expected_words, $actual_words);
        $this->assertEquals(4, $this->_cut->get_total_student_cards_in_activity($modules[0]->id, $owner->id));

        // check the count of cards in the second activity (which is in a different section)
        $this->assertCount(2, $cards[1]);
        $actual_words = array_map(function ($card) {
            return $card['word'];
        }, $cards[1]);
        $expected_words = array('run', 'eat');
        sort($expected_words);
        $this->assertEquals($expected_words, $actual_words);
        $this->assertEquals(2, $this->_cut->get_total_student_cards_in_activity($modules[1]->id, $owner->id));

        // check the count of cards in the third activity (which is in a different section that has no words)
        $this->assertCount(0, $cards[2]);
        $this->assertEquals(0, $this->_cut->get_total_student_cards_in_activity($modules[2]->id, $owner->id));
    }

    /**
     * @global moodle_database $DB
     */
    public function test_get_cards_in_review_in_activity_no_groups() {
        global $DB;

        // create a user
        $tutor = $this->getDataGenerator()->create_user();

        // create a card owner
        $owner = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 3,
        ), array (
            'createsections' => true,
        ));

        // some words and corresponding section ids
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $wordids = range(1, count($words));
        $sectionids = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($course->id));
        $word_sectionids = array($sectionids[0], $sectionids[0], $sectionids[0], $sectionids[1], $sectionids[1], $sectionids[0]);

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($wordid, $word, $sectionid) use ($course, $tutor) {
            return array($wordid, $course->id, $sectionid, $word, $tutor->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $wordids, $words, $word_sectionids);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card (in review) from each of the words
        $vocabcards_card = array_map(function ($wordid) use ($owner, $tutor) {
            return array($wordid, $wordid, $owner->id, $tutor->id, null, vocabcard_status::IN_REVIEW, mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, $wordids);
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'));

        // create instances of the vocabulary cards activity in each of the sections
        $modules = array_map(function ($sectionid) use ($DB, $course) {
            $module = $this->getDataGenerator()->create_module('vocabcards', array(
                'course' => $course->id,
            ), array(
                'section' => $DB->get_field('course_sections', 'section', array('id' => $sectionid), MUST_EXIST),
            ));
            return $module;
        }, $sectionids);

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // get cards from the database
        $cards = array_map(function ($i) use ($modules, $owner) {
            $card = $this->_cut->get_cards_in_review_in_activity($modules[$i]->id);
            return $card;
        }, range(0, 2));

        // check the count of cards in the first activity (which is in section zero)
        $this->assertCount(4, $cards[0]);
        $actual_words = array_map(function ($card) {
            return $card['word'];
        }, $cards[0]);
        $expected_words = array('table', 'chair', 'dog', 'good');
        sort($expected_words);
        $this->assertEquals($expected_words, $actual_words);
        $this->assertEquals(4, $this->_cut->get_total_cards_in_review_in_activity($modules[0]->id));

        // check the count of cards in the second activity (which is in a different section)
        $this->assertCount(2, $cards[1]);
        $actual_words = array_map(function ($card) {
            return $card['word'];
        }, $cards[1]);
        $expected_words = array('run', 'eat');
        sort($expected_words);
        $this->assertEquals($expected_words, $actual_words);
        $this->assertEquals(2, $this->_cut->get_total_cards_in_review_in_activity($modules[1]->id));

        // check the count of cards in the third activity (which is in a different section that has no words)
        $this->assertCount(0, $cards[2]);
        $this->assertEquals(0, $this->_cut->get_total_cards_in_review_in_activity($modules[2]->id));
    }

    /**
     * @global moodle_database $DB
     */
    public function test_get_cards_in_review_in_activity_with_groups() {
        global $DB;

        // create a user
        $tutor = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // create three groups
        $group1 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));
        $group2 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));
        $group3 = $this->getDataGenerator()->create_group(array(
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

        // some words and corresponding section ids
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $wordids = range(1, count($words));

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($wordid, $word) use ($course, $tutor, $section_id) {
            return array($wordid, $course->id, $section_id, $word, $tutor->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $wordids, $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // assign first 3 words to each student in group 1 (3 x 4 = 12 cards in total)
        $vocabcards_card = array();
        foreach (range(0, 3) as $i) {
            foreach (range(0, 2) as $j) {
                $vocabcards_card[] = array($wordids[$j], $students[$i]->id, $tutor->id, $group1->id, vocabcard_status::IN_REVIEW, mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
            }
        }

        // assign next 3 words to each student in group 2 (3 x 6 = 18 cards in total)
        foreach (range(4, 9) as $i) {
            foreach (range(3, 5) as $j) {
                $vocabcards_card[] = array($wordids[$j], $students[$i]->id, $tutor->id, $group2->id, vocabcard_status::IN_REVIEW, mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
            }
        }
        array_unshift($vocabcards_card, array('wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'));

        // create an instance of the vocabulary cards activity
        $module = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $course->id,
        ));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // check the count of cards in the activity (from the point of view of someone in group 1)
        $cards = $this->_cut->get_cards_in_review_in_activity($module->id, array($group1->id));
        $this->assertCount(12, $cards);
        $this->assertEquals(12, $this->_cut->get_total_cards_in_review_in_activity($module->id, array($group1->id)));

        // check the count of cards in the activity (from the point of view of someone in group 2)
        $cards = $this->_cut->get_cards_in_review_in_activity($module->id, array($group2->id));
        $this->assertCount(18, $cards);
        $this->assertEquals(18, $this->_cut->get_total_cards_in_review_in_activity($module->id, array($group2->id)));

        // check the count of cards in the activity (from the point of view of someone in group 3)
        $cards = $this->_cut->get_cards_in_review_in_activity($module->id, array($group3->id));
        $this->assertCount(0, $cards);
        $this->assertEquals(0, $this->_cut->get_total_cards_in_review_in_activity($module->id, array($group3->id)));
    }

    /**
     * tests getting cards from the card repository (for a particular course)
     */
    public function test_get_cards_in_repository_in_course() {
        // seed the database
        $this->_seed_db_for_repository();

        // get all cards from the point of view of someone in the second group
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id));
        $this->assertCount(6, $cards);
        $this->assertEquals(6, $this->_cut->get_total_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id)));

        // text search by word matching 'ra': 'rat', 'rabbit', 'dragon'
        $q = 'ra';
        $expected = array('dragon', 'rabbit', 'rat');
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id), 0, 0, $q);
        $this->assertCount(count($expected), $cards);
        $this->assertEquals(count($expected), $this->_cut->get_total_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id), 0, 0, $q));
        $words = array_map(function ($card) {
            return $card['word'];
        }, $cards);
        sort($words);
        $this->assertEquals($expected, $words);

        // text search by word matching 'monkey'
        $q = 'monkey';
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id), 0, 0, $q);
        $this->assertCount(0, $cards);
        $this->assertEquals(0, $this->_cut->get_total_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id), 0, 0, $q));

        // text search by tag 'predator'
        $q = 'predator';
        $expected = array('dragon', 'snake', 'tiger');
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id), 0, 0, $q);
        $this->assertCount(count($expected), $cards);
        $this->assertEquals(count($expected), $this->_cut->get_total_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id), 0, 0, $q));
        $words = array_map(function ($card) {
            return $card['word'];
        }, $cards);
        sort($words);
        $this->assertEquals($expected, $words);

        // no group restriction
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id);
        $expected = array('rat', 'ox', 'tiger', 'rabbit', 'dragon', 'snake', 'horse', 'sheep', 'monkey', 'rooster', 'dog', 'pig');
        sort($expected);
        $this->assertCount(count($expected), $cards);
        $this->assertEquals(count($expected), $this->_cut->get_total_cards_in_repository_in_course($this->_course->id));
        $words = array_map(function ($card) {
            return $card['word'];
        }, $cards);
        sort($words);
        $this->assertEquals($expected, $words);

        // group filter by the second group
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id, null, $this->_groups[1]->id);
        $expected = array('rat', 'ox', 'tiger', 'rabbit', 'dragon', 'snake');
        sort($expected);
        $this->assertCount(count($expected), $cards);
        $this->assertEquals(count($expected), $this->_cut->get_total_cards_in_repository_in_course($this->_course->id, null, $this->_groups[1]->id));
        $words = array_map(function ($card) {
            return $card['word'];
        }, $cards);
        sort($words);
        $this->assertEquals($expected, $words);

        // group filter by the first group
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id, null, $this->_groups[0]->id);
        $expected = array('horse', 'sheep', 'monkey', 'rooster', 'dog', 'pig');
        sort($expected);
        $this->assertCount(count($expected), $cards);
        $this->assertEquals(count($expected), $this->_cut->get_total_cards_in_repository_in_course($this->_course->id, null, $this->_groups[0]->id));
        $words = array_map(function ($card) {
            return $card['word'];
        }, $cards);
        sort($words);
        $this->assertEquals($expected, $words);

        // user filter by someone in the second group
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id), 0, $this->_users[0]->id);
        $this->assertCount(1, $cards);
        $this->assertEquals(1, $this->_cut->get_total_cards_in_repository_in_course($this->_course->id, array($this->_groups[1]->id), 0, $this->_users[0]->id));
        $this->assertEquals('rat', $cards[0]['word']);

        // user filter by someone in the first group
        $cards = $this->_cut->get_cards_in_repository_in_course($this->_course->id, array($this->_groups[0]->id), 0, $this->_users[1]->id);
        $this->assertCount(1, $cards);
        $this->assertEquals(1, $this->_cut->get_total_cards_in_repository_in_course($this->_course->id, array($this->_groups[0]->id), 0, $this->_users[1]->id));
        $this->assertEquals('horse', $cards[0]['word']);
    }

    /**
     * test getting cards for repository export with full information
     */
    public function test_get_cards_in_repository_in_course_long() {
        // seed the database
        $this->_seed_db_for_repository();

        // get all cards from the point of view of someone in the second group
        $expected = array('dragon', 'ox', 'rabbit', 'rat', 'snake', 'tiger');
        $cards = $this->_cut->get_cards_in_repository_in_course_long($this->_course->id, array($this->_groups[1]->id));
        $this->assertCount(count($expected), $cards);
        $words = array_map(function ($card) {
            return $card['word'];
        }, $cards);
        $this->assertEquals($expected, $words);

        // get all cards without group restriction
        $expected = array('dog', 'dragon', 'horse', 'monkey', 'ox', 'pig', 'rabbit', 'rat', 'rooster', 'sheep', 'snake', 'tiger');
        $cards = $this->_cut->get_cards_in_repository_in_course_long($this->_course->id);
        $this->assertCount(count($expected), $cards);
        $words = array_map(function ($card) {
            return $card['word'];
        }, $cards);
        $this->assertEquals($expected, $words);
    }

    /**
     * tests deleting a card deletes the card along with its feedback
     * @global moodle_database $DB
     */
    public function test_delete() {
        global $DB;

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

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
            ),
            'vocabcards_card' => array(
                array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, $teacher->id, null, vocabcard_status::NOT_STARTED, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
                array(2, 2, $user->id, $teacher->id, null, vocabcard_status::NOT_STARTED, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('id', 'cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, 1, $teacher->id, 'Feedback 1', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
                array(2, 1, $teacher->id, 'Feedback 2', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
                array(3, 1, $teacher->id, 'Feedback 3', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
                array(4, 2, $teacher->id, 'Feedback 4', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
            ),
        )));

        // delete a card
        $this->_cut->delete(1);

        // ensure the card no longer exists
        $this->assertFalse($DB->record_exists('vocabcards_card', array('id' => 1)));

        // ensure the other card still exists
        $this->assertTrue($DB->record_exists('vocabcards_card', array('id' => 2)));

        // ensure there's one feedback left in total
        $this->assertEquals(1, $DB->count_records('vocabcards_feedback'));

        // ensure there's one feedback against card 2
        $this->assertEquals(1, $DB->count_records('vocabcards_feedback', array(
            'cardid' => 2,
        )));

        // ensure there's no feedback against card 1
        $this->assertEquals(0, $DB->count_records('vocabcards_feedback', array(
            'cardid' => 1,
        )));
    }

}
