<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/mod_vocabcards_backup_controller.php';

/**
 * @see http://docs.moodle.org/dev/Backup_2.0_for_developers
 */
class mod_vocabcards_backup_test extends advanced_testcase {

    /**
     * @var integer
     */
    protected $_cmid;

    /**
     * @var integer
     */
    protected $_userid;

    /**
     * @var object
     */
    protected $_course;

    /**
     * @var object
     */
    protected $_course_module;

    /**
     * @var integer
     */
    protected $_t0;

    /**
     * @var mod_vocabcards_backup_controller
     */
    protected $_cut;

    /**
     * @var array
     */
    protected $_words;

    /**
     * @var array
     */
    protected $_words_to_sectionnums;

    /**
     * setUp
     * @global moodle_database $DB
     */
    protected function setUp() {
        global $CFG, $DB;
        $CFG->keeptempdirectoriesonbackup = true;

        // record initial time
        $this->_t0 = time();

        // create course and some course modules (of which we're testing the last)
        $course = $this->_course = $this->getDataGenerator()->create_course(array(
            'numsections' => 5,
        ), array (
            'createsections' => true,
        ));
        foreach (array('forum', 'forum', 'vocabcards', 'vocabcards') as $module) {
            $this->getDataGenerator()->create_module($module, array(
                'course' => $this->_course->id,
            ));
        }
        $this->_course_module = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $this->_course->id,
            'startdate' => mktime(9, 0, 0, 6, 4, 2014),
            'header' => array(
                'format' => FORMAT_HTML,
                'text' => '<p>My lovely header</p>'
            ),
            'footer' => array(
                'format' => FORMAT_HTML,
                'text' => '<p>My lovely footer</p>'
            ),
        ));

        // set the course module id and the user id
        $this->_cmid = $this->_course_module->cmid;
        $userid = $this->_userid = 2;

        // setup a vocabulary syllabus
        $words = $this->_words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $sectionids = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($this->_course->id));
        $word_sectionids = array($sectionids[0], $sectionids[0], $sectionids[0], $sectionids[1], $sectionids[1], $sectionids[0]);

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word, $sectionid) use ($course, $userid) {
            return array($course->id, $sectionid, $word, $userid, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words, $word_sectionids);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create map of words to section numbers (not section ids)
        $sectionnums = (array)$DB->get_fieldset_select('course_sections', 'section', 'course = ?', array($this->_course->id));
        $this->_words_to_sectionnums = array(
            'table' => $sectionnums[0],
            'chair' => $sectionnums[0],
            'dog' => $sectionnums[0],
            'run' => $sectionnums[1],
            'eat' => $sectionnums[1],
            'good' => $sectionnums[0],
        );

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // create an instance of the class under test
        $this->_cut = new mod_vocabcards_backup_controller(
            backup::TYPE_1ACTIVITY,
            $this->_cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $this->_userid
        );

        $this->resetAfterTest(true);
    }

    /**
     * tests instantiation of a backup controller
     */
    public function test_backup_controller_instantiation() {
        $this->assertInstanceOf('backup_controller', $this->_cut);
    }

    /**
     * tests executing a plan creates a single directory in dataroot in /temp/backup
     */
    public function test_execute_plan_creates_one_directory() {
        global $CFG;
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $this->assertCount(0, $child_directories);
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $this->assertCount(1, $child_directories);
    }

    /**
     * tests the backupid corresponds to a directory in dataroot in /temp/backup
     */
    public function test_get_backupid_matches_directory() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $this->assertCount(1, $child_directories);
        $this->assertEquals($child_directories[0], $this->_cut->get_backupid());
    }

    /**
     * tests executing a plan creates a single course module subdirectory in dataroot in /temp/backup/{backupid}/activities/vocabcards_{cmid}
     */
    public function test_execute_plan_creates_vocabcards_subdirectory() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $dir = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/vocabcards_' . $this->_course_module->cmid;
        $this->assertFileExists($dir);
    }

    /**
     * tests executing a plan for a vocabcards course module creates a module.xml file
     */
    public function test_execute_plan_creates_module_xml() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $file = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/vocabcards_' . $this->_course_module->cmid . '/module.xml';
        $this->assertFileExists($file);
    }

    /**
     * tests executing a plan for a vocabcards course module creates a vocabcards.xml file
     */
    public function test_execute_plan_creates_vocabcards_xml() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $file = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/vocabcards_' . $this->_course_module->cmid . '/vocabcards.xml';
        $this->assertFileExists($file);
    }

    /**
     * tests executing a plan for a vocabcards course module creates a vocabcards_syllabus.xml file
     * (this xml file will be repeated for every vocabcards course module in the course that's being backed up)
     */
    public function test_execute_plan_creates_vocabcards_syllabus_xml() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $file = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/vocabcards_' . $this->_course_module->cmid . '/vocabcards_syllabus.xml';
        $this->assertFileExists($file);
    }

    /**
     * tests executing a plan for a vocabcards course module creates a vocabcards.xml file with the expected content
     */
    public function test_execute_plan_creates_expected_vocabcards_xml_content() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $file = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/vocabcards_' . $this->_course_module->cmid . '/vocabcards.xml';
        $xml = simplexml_load_file($file);
        $this->assertEquals($this->_course_module->id, $xml['id']);
        $this->assertSame($this->_course_module->cmid, (integer)$xml['moduleid']);
        $this->assertEquals('vocabcards', $xml['modulename']);
        $this->assertEquals($this->_course_module->name, $xml->vocabcards->name);
        $this->assertEquals(mktime(9, 0, 0, 6, 4, 2014), (integer)$xml->vocabcards->startdate);
        $this->assertEquals('<p>My lovely header</p>', $xml->vocabcards->header);
        $this->assertEquals('<p>My lovely footer</p>', $xml->vocabcards->footer);
    }

    /**
     * tests executing a plan for a vocabcards course module creates a vocabcards_syllabus.xml file with the expected content
     */
    public function test_execute_plan_creates_expected_vocabcards_syllabus_xml_content() {
        global $CFG;
        $this->_cut->execute_plan();
        $child_directories = self::_get_child_directories($CFG->dataroot . '/temp/backup');
        $file = $CFG->dataroot . '/temp/backup/' . $child_directories[0] . '/activities/vocabcards_' . $this->_course_module->cmid . '/vocabcards_syllabus.xml';

        // check the number of words
        $xml = simplexml_load_file($file);
        $this->assertEquals(count($this->_words), $xml->children()->count());

        // check each word, and check it is associated with the expected section number
        $expected_words = $this->_words;
        foreach ($xml->children() as $child) {
            $word = (string)$child->word;
            $this->assertContains($word, $expected_words);
            $expected_words = array_filter($expected_words, function ($w) use ($word) {
                return $w != $word;
            });
            $this->assertTrue(isset($child->sectionnum));
            $this->assertEquals($this->_words_to_sectionnums[$word], (integer)$child->sectionnum);
        }
        $this->assertCount(0, $expected_words);
    }

    /**
     * tests encoding content links encodes the /mod/vocabcards/index.php URL
     */
    public function test_encode_content_links_encodes_mod_vocabcards_index_url() {
        global $CFG;
        $link = $CFG->wwwroot . '/mod/vocabcards/index.php?id=123';
        $content = '<p>hello</p><a href="' . $link . '">click here</a><p>world</p>';
        $result = backup_vocabcards_activity_task::encode_content_links($content);
        $encoded_link = '$@VOCABCARDSINDEX*123@$';
        $this->assertSame('<p>hello</p><a href="' . $encoded_link . '">click here</a><p>world</p>', $result);
    }

    /**
     * tests encoding content links encodes the /mod/vocabcards/view.php URL
     */
    public function test_encode_content_links_encodes_mod_vocabcards_view_url() {
        global $CFG;
        $link = $CFG->wwwroot . '/mod/vocabcards/view.php?id=123';
        $content = '<p>hello</p><a href="' . $link . '">click here</a><p>world</p>';
        $result = backup_vocabcards_activity_task::encode_content_links($content);
        $encoded_link = '$@VOCABCARDSVIEWBYID*123@$';
        $this->assertSame('<p>hello</p><a href="' . $encoded_link . '">click here</a><p>world</p>', $result);
    }

    /**
     * returns an array of directories within the given directory (not recursively)
     * @param string $dir
     * @return array
     */
    protected static function _get_child_directories($dir) {
        $retval = array();
        $ignore = array('.', '..');
        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if (is_dir($dir . '/' . $entry) && !in_array($entry, $ignore)) {
                    $retval[] = $entry;
                }
            }
            closedir($handle);
        }
        return $retval;
    }

}
