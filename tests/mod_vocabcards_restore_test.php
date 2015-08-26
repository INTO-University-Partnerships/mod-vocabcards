<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/mod_vocabcards_restore_controller.php';

/**
 * @see http://docs.moodle.org/dev/Restore_2.0_for_developers
 */
class mod_vocabcards_restore_test extends advanced_testcase {

    /**
     * @var integer
     */
    protected $_categoryid;

    /**
     * @var integer
     */
    protected $_userid;

    /**
     * @var integer
     */
    protected $_courseid;

    /**
     * @var mod_vocabcards_restore_controller
     */
    protected $_cut;

    /**
     * @var moodle_transaction
     */
    protected $_transaction;

    /**
     * setUp
     * @global moodle_database $DB
     */
    protected function setUp() {
        global $CFG, $DB;

        // copy the 'restoreme' directory to dataroot
        $src = __DIR__ . '/restoreme/';
        check_dir_exists($CFG->dataroot . '/temp/backup/');
        $dest = $CFG->dataroot . '/temp/backup/';
        shell_exec("cp -r {$src} {$dest}");

        // set parameters, create a course to restore into
        $folder = 'restoreme';
        $this->_categoryid = 1;
        $this->_userid = 2;
        $this->_courseid = restore_dbops::create_new_course('Restored course fullname', 'Restored course shortname', $this->_categoryid);

        // create an instance of the class under test
        $this->_transaction = $DB->start_delegated_transaction();
        $this->_cut = new mod_vocabcards_restore_controller(
            $folder,
            $this->_courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $this->_userid,
            backup::TARGET_NEW_COURSE
        );

        $this->resetAfterTest(true);
    }

    /**
     * tests instantiation of a restore controller
     */
    public function test_restore_controller_instantiation() {
        $this->assertInstanceOf('restore_controller', $this->_cut);
    }

    /**
     * tests the plan has no missing modules
     */
    public function test_restore_plan_has_no_missing_modules() {
        $this->assertFalse($this->_cut->get_plan()->is_missing_modules());
    }

    /**
     * tests that the precheck returns true as expected
     */
    public function test_execute_precheck_returns_true() {
        $result = $this->_cut->execute_precheck();
        $this->assertTrue($result);
        $this->_cut->execute_plan();
        $this->_transaction->allow_commit();
    }

    /**
     * tests that executing the plan renames the destination course
     * @global moodle_database $DB
     */
    public function test_execute_plan_renames_destination_course() {
        global $DB;

        $before_courseid = (integer)$DB->get_field('course', 'id', array(
            'fullname' => 'Restored course fullname',
            'shortname' => 'Restored course shortname',
        ), MUST_EXIST);
        $this->assertGreaterThanOrEqual(1, $before_courseid);

        $this->_cut->execute_precheck();
        $this->_cut->execute_plan();
        $this->_transaction->allow_commit();

        $after_courseid = (integer)$DB->get_field('course', 'id', array(
            'fullname' => '001',
            'shortname' => '001',
        ), MUST_EXIST);
        $this->assertGreaterThanOrEqual(1, $after_courseid);

        $this->assertSame($this->_courseid, $before_courseid);
        $this->assertSame($before_courseid, $after_courseid);
    }

    /**
     * tests that executing the plan restores the module
     * @global moodle_database $DB
     */
    public function test_execute_plan_restores_module() {
        global $DB;

        $this->_cut->execute_precheck();
        $this->_cut->execute_plan();
        $this->_transaction->allow_commit();

        $this->assertEquals(1, $DB->count_records('vocabcards'));
        $data = (array)$DB->get_record('vocabcards', array(), '*', MUST_EXIST);
        $this->assertSame('Vocabulary cards 001', $data['name']);
        $this->assertEquals(1419984000, $data['startdate']);
        $this->assertContains('Course link in header', $data['header']);
        $this->assertContains('Course link in footer', $data['footer']);
        $this->assertContains('course/view.php?id=' . $this->_courseid, $data['header']);
        $this->assertContains('course/view.php?id=' . $this->_courseid, $data['footer']);
    }

    /**
     * tests that executing the plan restores the vocabulary syllabus
     * @global moodle_database $DB
     */
    public function test_execute_plan_restores_vocabulary_syllabus() {
        global $DB, $USER;

        $t0 = time();

        $this->_cut->execute_precheck();
        $this->_cut->execute_plan();
        $this->_transaction->allow_commit();

        // expected words (sorted alphabetically) and their section numbers (not section ids)
        $expected_words = array(
            'ankle' => 2,
            'arm' => 1,
            'chest' => 3,
            'ear' => 1,
            'eye' => 1,
            'finger' => 0,
            'head' => 0,
            'knee' => 2,
            'leg' => 0,
            'neck' => 2,
            'nose' => 0,
            'shoulder' => 2,
            'stomach' => 3,
            'thumb' => 1,
        );

        // count the number of vocabcards_syllabus records (i.e. the number of words)
        $this->assertEquals(count($expected_words), $DB->count_records('vocabcards_syllabus', array(
            'courseid' => $this->_courseid,
        )));

        // check the words
        $sql = <<<SQL
            SELECT word
            FROM {vocabcards_syllabus}
            WHERE courseid = ?
            ORDER BY word
SQL;
        $words = $DB->get_fieldset_sql($sql, array(
            'courseid' => $this->_courseid,
        ));
        $this->assertEquals(array_keys($expected_words), $words);

        // check the section numbers of the words
        $sql = <<<SQL
            SELECT cs.section
            FROM {vocabcards_syllabus} vs
            INNER JOIN {course_sections} cs ON cs.id = vs.sectionid
            WHERE vs.courseid = ?
            ORDER BY vs.word
SQL;
        $sectionnums = $DB->get_fieldset_sql($sql, array(
            'courseid' => $this->_courseid,
        ));
        $this->assertEquals(array_values($expected_words), $sectionnums);

        // get all words in vocabulary syllabus for the course
        $words = $DB->get_records('vocabcards_syllabus', array(
            'courseid' => $this->_courseid,
        ));

        // we have no logged in user, so ensure the creator got stamped as the admin
        $this->assertEquals(0, $USER->id);
        $admin = get_admin();
        array_map(function ($word) use ($admin) {
            $this->assertEquals($admin->id, $word->creatorid);
        }, $words);

        // check the timecreated and timemodified values
        array_map(function ($word) use ($t0) {
            $this->assertGreaterThanOrEqual($t0, $word->timecreated);
            $this->assertGreaterThanOrEqual($t0, $word->timemodified);
        }, $words);
    }

    /**
     * ensures that the currently logged in user is stamped as the creator for vocabulary syllabus words
     * @global moodle_database $DB
     */
    public function test_execute_plan_stamps_vocabulary_syllabus_with_logged_in_user() {
        global $DB;

        $manager = $this->getDataGenerator()->create_user();
        $this->setUser($manager);

        $this->_cut->execute_precheck();
        $this->_cut->execute_plan();
        $this->_transaction->allow_commit();

        // get all words in vocabulary syllabus for the course
        $words = $DB->get_records('vocabcards_syllabus', array(
            'courseid' => $this->_courseid,
        ));

        // ensure the creator got stamped as the logged in user
        $admin = get_admin();
        $this->assertGreaterThan($admin->id, $manager->id);
        array_map(function ($word) use ($manager) {
            $this->assertEquals($manager->id, $word->creatorid);
        }, $words);
    }

}
