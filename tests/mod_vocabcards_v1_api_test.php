<?php

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;
use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/vocabcard_status.php';

class mod_vocabcards_v1_api_test extends advanced_testcase {

    /**
     * @var Silex\Application
     */
    protected $_app;

    /**
     * setUp
     */
    public function setUp() {
        global $CFG;

        if (!defined('SLUG')) {
            define('SLUG', '');
        }

        // create Silex app
        $this->_app = require __DIR__ . '/../app.php';
        $this->_app['debug'] = true;
        $this->_app['exception_handler']->disable();

        // add middleware to work around Moodle expecting non-empty $_GET or $_POST
        $this->_app->before(function (Request $request) {
            if (empty($_GET) && 'GET' == $request->getMethod()) {
                $_GET = $request->query->all();
            }
            if (empty($_POST) && 'POST' == $request->getMethod()) {
                $_POST = $request->request->all();
            }
        });

        // reset the database after each test
        $this->resetAfterTest();

        // Django settings removed by Moodle's PHPUnit integration
        $CFG->djangowwwroot = 'http://localhost:8000';
        $CFG->django_notification_basic_auth = array('notification', 'Wibble123!');
        $CFG->django_urls = array(
            'send_notification' => '/messaging_core/send/notification/',
        );
    }

    /**
     * tearDown
     */
    public function tearDown() {
        $_GET = array();
        $_POST = array();
        m::close();
    }

    /**
     * tests trying to get syllabus words from an invalid courseid
     */
    public function test_syllabus_words_invalid_courseid() {
        // create a user
        $user = $this->getDataGenerator()->create_user();

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/999/words', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 404 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('invalidcourseid', 'error'), $content->errorMessage);
    }

    /**
     * tests getting all syllabus words when none exist
     */
    public function test_syllabus_words_route_no_words() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // add words to syllabus
        $vocabcards_syllabus = array();
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(0, $content->total);
        $this->assertCount(0, $content->words);
    }

    /**
     * tests getting filtered syllabus words (filtered by section)
     * @global moodle_database $DB
     */
    public function test_syllabus_words_route_filtered_words_by_section_no_pagination() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 5,
        ), array (
            'createsections' => true,
        ));

        // some words and corresponding section ids ('run' and 'eat' are in their own section)
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $words_subset = array('eat', 'run');
        $sectionids = (array)$DB->get_fieldset_select('course_sections', 'id', 'course = ?', array($course->id));
        $word_sectionids = array($sectionids[0], $sectionids[0], $sectionids[0], $sectionids[1], $sectionids[1], $sectionids[0]);

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word, $sectionid) use ($course, $user) {
            return array($course->id, $sectionid, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words, $word_sectionids);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words', array(
            'sectionid' => $sectionids[1],
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(count($words_subset), $content->total);
        $this->assertCount(count($words_subset), $content->words);

        // verify which words were served (given the words are sorted alphabetically)
        array_map(function ($x, $y) {
            $this->assertEquals($x, $y->word);
            return $x == $y->word;
        }, $words_subset, $content->words);
    }

    /**
     * tests getting filtered syllabus words (filtered with a search query)
     * @global moodle_database $DB
     */
    public function test_syllabus_words_route_filtered_words_by_query_no_pagination() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // some words, two of which start with 'cha'
        $words = array('table', 'chair', 'chapel', 'chip', 'eat', 'good');
        $words_subset = array('chair', 'chapel');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user, $section_id) {
            return array($course->id, $section_id, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words', array(
            'q' => 'cha',
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(count($words_subset), $content->total);
        $this->assertCount(count($words_subset), $content->words);

        // verify which words were served (given the words are sorted alphabetically)
        array_map(function ($x, $y) {
            $this->assertEquals($x, $y->word);
            return $x == $y->word;
        }, $words_subset, $content->words);
    }

    /**
     * tests getting all syllabus words
     * @global moodle_database $DB
     */
    public function test_syllabus_words_route_all_words_no_pagination() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user, $section_id) {
            return array($course->id, $section_id, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(count($words), $content->total);
        $this->assertCount(count($words), $content->words);
    }

    /**
     * tests getting all syllabus words without the relevant capability
     * @global moodle_database $DB
     */
    public function test_syllabus_words_route_all_words_no_capability() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user) {
            return array($course->id, 1, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course as a student (i.e. without sufficient capabilities)
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isForbidden());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 403 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('accessdenied', 'admin'), $content->errorMessage);
    }

    /**
     * tests getting some syllabus words
     * @global moodle_database $DB
     */
    public function test_syllabus_words_route_pagination() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good', 'blue', 'cup');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user, $section_id) {
            return array($course->id, $section_id, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // pagination parameters
        $limitfrom = 3;
        $limitnum = 3;

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words', array(
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(count($words), $content->total);
        $this->assertCount($limitnum, $content->words);

        // verify which words were served (given the words are sorted alphabetically)
        array_map(function ($x, $y) {
            $this->assertEquals($x, $y->word);
            return $x == $y->word;
        }, array('dog', 'eat', 'good'), $content->words);
    }

    /**
     * tests trying to get a syllabus word with an invalid courseid
     */
    public function test_get_syllabus_word_invalid_courseid() {
        // create a user
        $user = $this->getDataGenerator()->create_user();

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/999/words/999', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 404 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('invalidcourseid', 'error'), $content->errorMessage);
    }

    /**
     * tests trying to get a syllabus word with an invalid wordid
     * @global moodle_database $DB
     */
    public function test_get_syllabus_word_invalid_wordid() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words/999', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 404 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('invalidwordid', $this->_app['plugin']), $content->errorMessage);
    }

    /**
     * tests getting a syllabus word
     * @global moodle_database $DB
     */
    public function test_get_syllabus_word() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 7,
        ), array(
            'createsections' => true,
        ));

        // retrieve a section id for the course
        $sections = array_keys($DB->get_records('course_sections', array('course' => $course->id), 'section', 'id'));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user, $sections) {
            return array($course->id, $sections[4], $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // get a wordid to request
        $word = 'eat';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words/' . $wordid, array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        unset($content->timecreated);
        unset($content->timemodified);
        $this->assertEquals(array(
            'id' => $wordid,
            'courseid' => $course->id,
            'sectionid' => $sections[4],
            'sectionnum' => 4,
            'word' => $word,
            'cardcount' => 0,
            'creatorid' => $user->id,
            'creatorfullname' => $user->firstname . ' ' . $user->lastname,
        ), (array)$content);
    }

    /**
     * tests getting a syllabus word without the relevant capability
     * @global moodle_database $DB
     */
    public function test_get_syllabus_word_no_capability() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 7,
        ), array(
            'createsections' => true,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user) {
            return array($course->id, 5, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course as a student (i.e. without sufficient capabilities)
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // get a wordid to request
        $word = 'eat';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/words/' . $wordid, array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isForbidden());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 403 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('accessdenied', 'admin'), $content->errorMessage);
    }

    /**
     * tests posting a syllabus word that already exists raises an error
     * @global moodle_database $DB
     */
    public function test_post_syllabus_word_already_exists() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course section
        $section = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 3,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user) {
            return array($course->id, 0, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // create a word to post (that already exists)
        $content = json_encode(array(
            'word' => $words[0],
            'sectionid' => $section->id,
        ));

        // request the route
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $course->id . '/words', array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('exception:word_already_exists', $this->_app['plugin'], $words[0]), $content->errorMessage);
    }

    /**
     * tests posting a syllabus word
     * @global moodle_database $DB
     */
    public function test_post_syllabus_word() {
        global $CFG, $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course section
        $section = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 3,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user) {
            return array($course->id, 0, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // create a word to post
        $content = json_encode(array(
            'word' => 'museum',
            'sectionid' => $section->id,
        ));

        // request the route
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $course->id . '/words', array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check there's a new word in the database
        $word = $DB->get_record('vocabcards_syllabus', array(
            'word' => 'museum',
            'courseid' => $course->id,
            'sectionid' => $section->id,
            'creatorid' => $user->id,
            'timecreated' => $this->_app['now'](),
            'timemodified' => $this->_app['now'](),
        ), '*', MUST_EXIST);

        // check location header
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('getword', array(
            'courseid' => $course->id,
            'wordid' => $word->id,
        ));
        $this->assertEquals($url, $client->getResponse()->headers->get('Location'));

        // check body of response
        $content = json_decode($client->getResponse()->getContent());
        array_map(function ($prop) use ($word, $content) {
            $this->assertEquals($word->{$prop}, $content->{$prop});
            return $word->{$prop} == $content->{$prop};
        }, array('id', 'courseid', 'sectionid', 'word', 'creatorid'));

        // check success message
        $this->assertEquals(get_string('wordaddedsuccessfully', $this->_app['plugin'], 'museum'), $content->successMessage);
    }

    /**
     * tests posting a syllabus word without the relevant capability
     * @global moodle_database $DB
     */
    public function test_post_syllabus_word_no_capability() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course section
        $section = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 3,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user) {
            return array($course->id, 0, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course as a student (i.e. without sufficient capabilities)
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // create a word to post
        $content = json_encode(array(
            'word' => 'museum',
            'sectionid' => $section->id,
        ));

        // request the route
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $course->id . '/words', array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isForbidden());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 403 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('accessdenied', 'admin'), $content->errorMessage);
    }

    /**
     * tests putting a syllabus word that already exists raises an error
     * @global moodle_database $DB
     */
    public function test_put_syllabus_word_already_exists() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a couple of course sections
        $section3 = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 3,
        ));
        $section5 = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 5,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $section3, $user) {
            return array($course->id, $section3->id, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // update a word to put
        $content = json_encode(array(
            'word' => $words[1],
            'sectionid' => $section5->id,
        ));

        // get a wordid to update
        $word = 'dog';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $course->id . '/words/' . $wordid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('exception:word_already_exists', $this->_app['plugin'], $words[1]), $content->errorMessage);
    }

    /**
     * tests putting a syllabus word
     * @global moodle_database $DB
     */
    public function test_put_syllabus_word() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a couple of course sections
        $section3 = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 3,
        ));
        $section5 = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 5,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $section3, $user) {
            return array($course->id, $section3->id, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // update a word to put
        $content = json_encode(array(
            'word' => 'doggy',
            'sectionid' => $section5->id,
        ));

        // get a wordid to update
        $word = 'dog';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $course->id . '/words/' . $wordid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check the word updated successfully in the database
        $word = $DB->get_record('vocabcards_syllabus', array(
            'word' => 'doggy',
            'courseid' => $course->id,
            'sectionid' => $section5->id,
            'creatorid' => $user->id,
            'timecreated' => mktime(9, 0, 0, 11, 5, 2013),
            'timemodified' => $this->_app['now'](),
        ), '*', MUST_EXIST);

        // check body of response
        $content = json_decode($client->getResponse()->getContent());
        array_map(function ($prop) use ($word, $content) {
            $this->assertEquals($word->{$prop}, $content->{$prop});
            return $word->{$prop} == $content->{$prop};
        }, array('id', 'courseid', 'sectionid', 'word', 'creatorid'));

        // check success message
        $this->assertEquals(get_string('wordupdatedsuccessfully', $this->_app['plugin'], 'doggy'), $content->successMessage);
    }

    /**
     * tests putting a syllabus word without the relevant capability
     * @global moodle_database $DB
     */
    public function test_put_syllabus_word_no_capability() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a couple of course sections
        $section3 = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 3,
        ));
        $section5 = $this->getDataGenerator()->create_course_section(array(
            'course' => $course->id,
            'section' => 5,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $section3, $user) {
            return array($course->id, $section3->id, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course as a student (i.e. without sufficient capabilities)
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // update a word to put
        $content = json_encode(array(
            'word' => 'doggy',
            'sectionid' => $section5->id,
        ));

        // get a wordid to update
        $word = 'dog';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $course->id . '/words/' . $wordid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isForbidden());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 403 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('accessdenied', 'admin'), $content->errorMessage);
    }

    /**
     * tests deleting a syllabus word from a course that it doesn't belong to
     * @global moodle_database $DB
     */
    public function test_delete_syllabus_word_from_other_course() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create two courses
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus of course 1
        $vocabcards_syllabus = array_map(function ($word) use ($course1, $user) {
            return array($course1->id, 0, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on both courses
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));
        $this->getDataGenerator()->enrol_user($user->id, $course2->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // get a wordid to delete
        $word = 'chair';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route for the wrong course (course2 rather than course1)
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $course2->id . '/words/' . $wordid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests deleting a syllabus word that's already been used to create at least one card raises an error
     * @global moodle_database $DB
     */
    public function test_delete_syllabus_word_already_assigned() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a student
        $student = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $wordids = range(1, count($words));

        // add words to syllabus of course 1
        $vocabcards_syllabus = array_map(function ($word, $wordid) use ($course, $user) {
            return array($wordid, $course->id, 0, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words, $wordids);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // seed the database with one card
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_card' => array(
                array('wordid', 'ownerid', 'assignerid', 'status', 'timecreated', 'timemodified'),
                array($wordids[1], $student->id, 0, 0, mktime(10, 0, 0, 11, 5, 2013), mktime(10, 0, 0, 11, 5, 2013)),
            )
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // enrol the student on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // get a wordid to delete
        $word = 'chair';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $course->id . '/words/' . $wordid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isClientError());
        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('exception:word_already_assigned', $this->_app['plugin'], 'chair'), $content->errorMessage);
    }

    /**
     * tests deleting a syllabus word
     * @global moodle_database $DB
     */
    public function test_delete_syllabus_word() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user) {
            return array($course->id, 0, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the user
        $this->setUser($user);

        // get a wordid to delete
        $word = 'chair';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $course->id . '/words/' . $wordid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isEmpty());
        $this->assertEquals(204, $client->getResponse()->getStatusCode());
    }

    /**
     * tests trying to delete a syllabus word without the relevant capability
     */
    public function test_delete_syllabus_word_no_capability() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $user) {
            return array($course->id, 0, $word, $user->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course as a student (i.e. without sufficient capabilities)
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // get a wordid to delete
        $word = 'chair';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));

        // request the route
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $course->id . '/words/' . $wordid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isForbidden());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 403 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('accessdenied', 'admin'), $content->errorMessage);
    }

    /**
     * tests getting all students
     * @global moodle_database $DB
     */
    public function test_students_route_all_students_no_pagination() {
        global $DB;

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create two groups in the course
        $group1 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));
        $group2 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // create ten students enrolled on the course
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

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // login the teacher
        $this->setUser($teacher);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/students', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(count($students) + 1, $content->total);
        $this->assertCount(count($students) + 1, $content->students);
    }

    /**
     * tests getting students in groups (i.e. from a tutor's perspective)
     * @global moodle_database $DB
     */
    public function test_students_route_students_in_groups() {
        global $DB;

        // create four tutors
        $tutors = array_map(function ($i) {
            return $this->getDataGenerator()->create_user();
        }, range(1, 4));

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create two groups in the course
        $group1 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));
        $group2 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // create ten students enrolled on the course
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

        // create tutor role and give the role the capability to do vocabulary assignments
        $tutor_roleid = create_role('Tutor', 'tutor', 'Tutor description goes here');
        assign_capability('mod/vocabcards:assignment', CAP_ALLOW, $tutor_roleid, context_system::instance());

        // enrol all four tutors on the course
        $tutors = array_map(function ($tutor) use ($course, $DB) {
            $this->getDataGenerator()->enrol_user($tutor->id, $course->id, $DB->get_field('role', 'id', array(
                'shortname' => 'tutor',
            )));
            return $tutor;
        }, $tutors);

        // put the zeroth tutor in no groups, first in the first group, the second tutor in the second group, the 3rd tutor in both groups
        groups_add_member($group1, $tutors[1]->id);
        groups_add_member($group2, $tutors[2]->id);
        groups_add_member($group1, $tutors[3]->id);
        groups_add_member($group2, $tutors[3]->id);

        // login the zeroth tutor
        $this->setUser($tutors[0]);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/students', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(1, $content->total);
        $this->assertCount(1, $content->students);

        // login the first tutor
        $this->setUser($tutors[1]);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/students', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(4 + 1, $content->total);
        $this->assertCount(4 + 1, $content->students);

        // login the second tutor
        $this->setUser($tutors[2]);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/students', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(6 + 1, $content->total);
        $this->assertCount(6 + 1, $content->students);

        // login the third tutor
        $this->setUser($tutors[3]);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/students', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // get the JSON content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(count($students) + 1, $content->total);
        $this->assertCount(count($students) + 1, $content->students);
    }

    /**
     * tests creating some cards
     */
    public function test_create_cards_route() {
        global $DB;

        // mock out GuzzleHttp\Client (don't want real requests sent anywhere)
        $request = m::mock('\GuzzleHttp\Message\Request');
        $request->shouldIgnoreMissing();
        $response = m::mock('\GuzzleHttp\Response');
        $response->shouldIgnoreMissing();
        $client = m::mock('\GuzzleHttp\Client');
        $client->shouldReceive('createRequest')
            ->times(3) // i.e. once per word/card
            ->andReturn($request);
        $client->shouldReceive('send')
            ->times(3) // i.e. once per word/card
            ->with($request)
            ->andReturn($response);
        $this->_app['guzzler'] = function () use ($client) {
            return $client;
        };

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create module
        $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $course->id,
            'startdate' => mktime(9, 0, 0, 6, 4, 2014),
        ));

        // create a group in the course
        $group = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($word) use ($course, $teacher) {
            return array($course->id, 1, $word, $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, $words);
        array_unshift($vocabcards_syllabus, array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // seed the database with words
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the teacher
        $this->setUser($teacher);

        // get wordids
        $wordids = array_map(function ($word) use ($DB) {
            return $DB->get_field('vocabcards_syllabus', 'id', array(
                'word' => $word,
            ), MUST_EXIST);
        }, array('chair', 'run', 'good'));

        // create data to post
        $content = json_encode(array(
            'wordids' => $wordids,
            'ownerid' => $user->id,
            'groupid' => $group->id,
        ));

        // request the route
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $course->id . '/cards/create', array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check number of cards in the database
        $this->assertEquals(count($wordids), $DB->count_records('vocabcards_card', array(
            'ownerid' => $user->id,
            'status' => vocabcard_status::NOT_STARTED,
        )));

        // check success message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('cardscreatedsuccessfully', $this->_app['plugin'], count($wordids)), $content->successMessage);

        // check cards
        array_map(function ($card, $wordid) use ($user) {
            $this->assertEquals($card->wordid, $wordid);
            $this->assertEquals($card->ownerid, $user->id);
            return true;
        }, $content->cards, $wordids);
    }

    /**
     * tests it's not possible to get a card that's not visible according to groups
     * @global moodle_database $DB
     */
    public function test_get_card_not_in_group() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create another student
        $other_student = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create two groups in the course, putting the student in group 1 and the other student in group 2
        $group1 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));
        $this->getDataGenerator()->create_group_member(array(
            'groupid' => $group1->id,
            'userid' => $user->id,
        ));
        $group2 = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));
        $this->getDataGenerator()->create_group_member(array(
            'groupid' => $group2->id,
            'userid' => $other_student->id,
        ));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array($course->id, 1, 'table', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, $user->id, $teacher->id, $group1->id, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, $teacher->id, 'Better luck next time', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
            ),
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // enrol the other student on the course
        $this->getDataGenerator()->enrol_user($other_student->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the other student
        $this->setUser($other_student);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/cards/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isNotFound());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 404 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('invalidcardid', $this->_app['plugin']), $content->errorMessage);
    }

    /**
     * tests it is possible to get a card that's visible according to groups
     * @global moodle_database $DB
     */
    public function test_get_card_same_group() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create another student
        $other_student = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // create a groups in the course, putting both students in the same group
        $group = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array(1, $course->id, $section_id, 'table', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, $teacher->id, $group->id, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, $teacher->id, 'Better luck next time', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
            ),
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // enrol the other student on the course
        $this->getDataGenerator()->enrol_user($other_student->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // add both students to the single group (this must be done after enrolment)
        array_map(function ($userid) use ($group) {
            $this->getDataGenerator()->create_group_member(array(
                'groupid' => $group->id,
                'userid' => $userid,
            ));
        }, array($user->id, $other_student->id));

        // login the other student
        $this->setUser($other_student);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/cards/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check the content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals($user->id, $content->ownerid);
        $this->assertEquals($user->firstname . ' ' . $user->lastname, $content->ownerfullname);
        $this->assertEquals('table', $content->word);
    }

    /**
     * tests getting a card
     * @global moodle_database $DB
     */
    public function test_get_card() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // some words and their phonemics
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $phonemics = array('teɪb(ə)l', 'tʃɛː', 'dɒg', 'rʌn', 'dɒg', 'gʊd');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($id, $word) use ($course, $teacher, $section_id) {
            return array($id, $course->id, $section_id, $word, $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, range(1, count($words)), $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid, $phonemic) use ($user, $teacher) {
            return array($wordid, $wordid, $user->id, $teacher->id, null, vocabcard_status::NOT_STARTED, $phonemic, 'definition goes here', mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, range(1, count($words)), $phonemics);
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the teacher
        $this->setUser($teacher);

        // fetch each card
        foreach ($words as $word) {
            $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));
            $cardid = $DB->get_field('vocabcards_card', 'id', array('wordid' => $wordid));
            $phonemic = $DB->get_field('vocabcards_card', 'phonemic', array('wordid' => $wordid));

            // request the route
            $client = new Client($this->_app);
            $client->request('GET', '/api/v1/' . $course->id . '/cards/' . $cardid, array(), array(), array(
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ));
            $this->assertTrue($client->getResponse()->isOk());
            $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

            // get the JSON content
            $content = json_decode($client->getResponse()->getContent());
            unset($content->timeaddedtorepo);
            unset($content->timecreated);
            unset($content->timemodified);
            $this->assertEquals(array(
                'id' => $cardid,
                'word' => $word,
                'wordid' => $wordid,
                'ownerid' => $user->id,
                'ownerfullname' => $user->firstname . ' ' . $user->lastname,
                'status' => vocabcard_status::NOT_STARTED,
                'phonemic' => $phonemic,
                'definition' => 'definition goes here',
                'noun' => '',
                'verb' => '',
                'adverb' => '',
                'adjective' => '',
                'synonym_' => '',
                'antonym_' => '',
                'prefixes' => '',
                'suffixes' => '',
                'collocations' => '',
                'tags' => '',
                'sentences' => '',
                'feedback_count' => 0,
                'feedback_modified' => 0,
            ), (array)$content);
        }
    }

    /**
     * tests trying to put a card that's not owned by the requesting user
     * @global moodle_database $DB
     */
    public function test_put_card_not_owner() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create another student
        $other_student = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array(1, $course->id, $section_id, 'table', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('id', 'cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, 1, $teacher->id, 'Better luck next time', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
            ),
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // enrol the other student on the course
        $this->getDataGenerator()->enrol_user($other_student->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the other student
        $this->setUser($other_student);

        // create card data to put
        $obj = $DB->get_record('vocabcards_card', array('id' => 1));
        $obj->status = vocabcard_status::IN_REVIEW;
        $obj->definition = 'The quick brown fox';
        $obj->noun = $noun = 'Some noun';
        $obj->tags = $tags = 'tag1, tag2, tag3';
        $content = json_encode((array)$obj);

        // request the route
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $course->id . '/cards/1', array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isForbidden());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check 403 message
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(get_string('accessdenied', 'admin'), $content->errorMessage);
    }

    /**
     * tests putting a card
     * @global moodle_database $DB
     */
    public function test_put_card() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($id, $word) use ($course, $teacher, $section_id) {
            return array($id, $course->id, $section_id, $word, $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, range(1, count($words)), $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid) use ($user, $teacher) {
            return array($wordid, $wordid, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, range(1, count($words)));
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // get a cardid to put
        $word = 'chair';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));
        $cardid = $DB->get_field('vocabcards_card', 'id', array('wordid' => $wordid));

        // create card data to put
        $obj = $DB->get_record('vocabcards_card', array('id' => $cardid));
        $obj->status = vocabcard_status::IN_REVIEW;
        $obj->definition = $definition = 'The quick brown fox';
        $obj->noun = $noun = 'Some noun';
        $obj->tags = $tags = 'tag1, tag2, tag3';
        $content = json_encode((array)$obj);

        // request the route
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $course->id . '/cards/' . $cardid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check the card updated successfully in the database
        $card = $DB->get_record('vocabcards_card', array(
            'status' => vocabcard_status::IN_REVIEW,
            // 'definition' => $definition,
            'noun' => $noun,
            'tags' => $tags,
        ), '*', MUST_EXIST);

        // check body of response
        $content = json_decode($client->getResponse()->getContent());
        array_map(function ($prop) use ($card, $content) {
            $this->assertEquals($card->{$prop}, $content->{$prop});
            return $card->{$prop} == $content->{$prop};
        }, array('id', 'status', 'definition', 'noun', 'tags'));

        // check success message
        $this->assertEquals(get_string('cardupdatedsuccessfully', $this->_app['plugin']), $content->successMessage);
    }

    /**
     * tests deleting a card
     */
    public function test_delete_card() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a group in the course
        $group = $this->getDataGenerator()->create_group(array(
            'courseid' => $course->id,
        ));

        // some words
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($id, $word) use ($course, $teacher) {
            return array($id, $course->id, 0, $word, $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, range(1, count($words)), $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid) use ($user, $teacher) {
            return array($wordid, $wordid, $user->id, $teacher->id, null, vocabcard_status::NOT_STARTED, mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, range(1, count($words)));
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'timecreated', 'timemodified'));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // login the teacher
        $this->setUser($teacher);

        // get a cardid to delete
        $word = 'chair';
        $wordid = $DB->get_field('vocabcards_syllabus', 'id', array('word' => $word));
        $cardid = $DB->get_field('vocabcards_card', 'id', array('wordid' => $wordid));

        // check the number of cards in the database is equal to the number of words
        $this->assertEquals(count($words), $DB->count_records('vocabcards_card'));

        // request the route
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $course->id . '/cards/' . $cardid, array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isEmpty());
        $this->assertEquals(204, $client->getResponse()->getStatusCode());

        // check the number of cards in the database is one less than previously
        $this->assertEquals(count($words) - 1, $DB->count_records('vocabcards_card'));
    }

    /**
     * tests getting a collection of cards for a particular student
     * @global moodle_database $DB
     */
    public function test_get_cards() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // some words and their phonemics
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $phonemics = array('teɪb(ə)l', 'tʃɛː', 'dɒg', 'rʌn', 'dɒg', 'gʊd');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($id, $word) use ($course, $teacher, $section_id) {
            return array($id, $course->id, $section_id, $word, $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, range(1, count($words)), $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid, $phonemic) use ($user, $teacher) {
            return array($wordid, $wordid, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, $phonemic, 'definition goes here', mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, range(1, count($words)), $phonemics);
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'));

        // create an instance of the vocabulary cards activity
        $module = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $course->id,
        ));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/cards', array(
            'instanceid' => $module->id,
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(count($words), $content->total);
        array_map(function ($a) use ($user, $words) {
            $this->assertEquals($user->firstname . ' ' . $user->lastname, $a->ownerfullname);
            $this->assertTrue(in_array($a->word, $words, true));
            $this->assertEquals(vocabcard_status::IN_PROGRESS, $a->status);
            return true;
        }, $content->cards);
    }

    /**
     * tests getting a collection of cards that are in review
     * @global moodle_database $DB
     */
    public function test_get_cards_in_review() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // some words and their phonemics
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $phonemics = array('teɪb(ə)l', 'tʃɛː', 'dɒg', 'rʌn', 'dɒg', 'gʊd');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($id, $word) use ($course, $teacher, $section_id) {
            return array($id, $course->id, $section_id, $word, $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, range(1, count($words)), $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid, $phonemic) use ($user, $teacher) {
            return array($wordid, $wordid, $user->id, $teacher->id, null, vocabcard_status::IN_REVIEW, $phonemic, 'definition goes here', mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, range(1, count($words)), $phonemics);
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'));

        // create an instance of the vocabulary cards activity
        $module = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $course->id,
        ));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // login the teacher
        $this->setUser($teacher);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/cards/review', array(
            'instanceid' => $module->id,
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals(count($words), $content->total);
        array_map(function ($a) use ($user, $words) {
            $this->assertEquals($user->firstname . ' ' . $user->lastname, $a->ownerfullname);
            $this->assertTrue(in_array($a->word, $words, true));
            $this->assertEquals(vocabcard_status::IN_REVIEW, $a->status);
            return true;
        }, $content->cards);
    }

    /**
     * tests getting a collection of cards that are in the repository
     * @global moodle_database $DB
     */
    public function test_get_cards_in_repository() {
        global $DB;

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/cards/repository', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests getting a collection of feedback for a card
     * @global moodle_database $DB
     */
    public function test_get_feedbacks() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create another student
        $other_student = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // some words and their phonemics
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($id, $word) use ($course, $teacher, $section_id) {
            return array($id, $course->id, $section_id, $word, $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, range(1, count($words)), $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid) use ($user, $teacher) {
            return array($wordid, $wordid, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', 'definition goes here', mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, range(1, count($words)));
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'));

        // create feedback on the first card, first five from the tutor, second five from the other student
        $feedback_count = 10;
        $vocabcards_feedback = array_map(function ($i) use ($teacher, $other_student, $vocabcards_card) {
            return array($i, 1, $i < 6 ? $teacher->id : $other_student->id, 'Feedback ' . $i, mktime(9, $i, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, range(1, $feedback_count));
        array_unshift($vocabcards_feedback, array('id', 'cardid', 'userid', 'feedback', 'timecreated', 'timemodified'));

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
            'vocabcards_feedback' => $vocabcards_feedback,
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // enrol the other student on the course
        $this->getDataGenerator()->enrol_user($other_student->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/feedback', array(
            'cardid' => 1,
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check content
        $content = json_decode($client->getResponse()->getContent());
        $this->assertEquals($feedback_count, $content->total);
        $this->assertCount($feedback_count, $content->feedback);

        // the feedback is returned in reverse chronological order, therefore the ids should be [10..1]
        $this->assertEquals(range(10, 1), array_map(function ($feedback) {
            return $feedback->id;
        }, $content->feedback));

        // check five feedback (from tutor)
        $tutor_feedback = array_slice($content->feedback, 5, 5);
        $this->assertTrue(_all(function ($f) {
            return $f->is_tutor;
        }, $tutor_feedback));
        $this->assertTrue(_all(function ($f) use ($teacher) {
            return $f->userfullname = $teacher->firstname . ' ' . $teacher->lastname;
        }, $tutor_feedback));

        // check five feedback (from other student)
        $other_student_feedback = array_slice($content->feedback, 0, 5);
        $this->assertFalse(_any(function ($f) {
            return $f->is_tutor;
        }, $other_student_feedback));
        $this->assertTrue(_all(function ($f) use ($other_student) {
            return $f->userfullname = $other_student->firstname . ' ' . $other_student->lastname;
        }, $other_student_feedback));
    }

    /**
     * tests getting a (piece of) feedback for a card
     * @global moodle_database $DB
     */
    public function test_get_feedback() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // the feedback string
        $feedback = 'Your work is reasonable, but your example sentences are generally awful.';

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array(1, $course->id, $section_id, 'table', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('id', 'cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, 1, $teacher->id, $feedback, mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
            ),
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the route
        $client = new Client($this->_app);
        $client->request('GET', '/api/v1/' . $course->id . '/feedback/1', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check content
        $content = (array)json_decode($client->getResponse()->getContent());
        $this->assertEquals(array(
            'id' => 1,
            'cardid' => 1,
            'userid' => $teacher->id,
            'userfullname' => $teacher->firstname . ' ' . $teacher->lastname,
            'feedback' => $feedback,
            'timecreated' => userdate(mktime(9, 0, 0, 1, 1, 2014)),
        ), $content);
    }

    /**
     * tests posting a (piece of) feedback
     * @global moodle_database $DB
     */
    public function test_post_feedback() {
        global $CFG, $DB;

        // mock out GuzzleHttp\Client (don't want real requests sent anywhere)
        $request = m::mock('\GuzzleHttp\Message\Request');
        $request->shouldIgnoreMissing();
        $response = m::mock('\GuzzleHttp\Response');
        $response->shouldIgnoreMissing();
        $client = m::mock('\GuzzleHttp\Client');
        $client->shouldReceive('createRequest')
            ->once()
            ->andReturn($request);
        $client->shouldReceive('send')
            ->once()
            ->with($request)
            ->andReturn($response);
        $this->_app['guzzler'] = function () use ($client) {
            return $client;
        };

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // create module
        $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $course->id,
            'startdate' => mktime(9, 0, 0, 6, 4, 2014),
        ));

        // the feedback string
        $feedback = 'Your work is reasonable, but your example sentences are generally awful.';

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array(1, $course->id, $section_id, 'table', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('id', 'cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, 1, $teacher->id, 'Better luck next time', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
            ),
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the teacher
        $this->setUser($teacher);

        // create a (piece of) feedback to post
        $content = json_encode(array(
            'cardid' => 1,
            'feedback' => $feedback,
        ));

        // request the route
        $client = new Client($this->_app);
        $client->request('POST', '/api/v1/' . $course->id . '/feedback', array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check there's a new (piece of) feedback in the database
        $feedback = $DB->get_record('vocabcards_feedback', array(
            'timecreated' => $this->_app['now'](),
            'timemodified' => $this->_app['now'](),
        ), '*', MUST_EXIST);

        // check location header
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('getfeedback', array(
            'courseid' => $course->id,
            'feedbackid' => $feedback->id,
        ));
        $this->assertEquals($url, $client->getResponse()->headers->get('Location'));

        // check body of response
        $content = json_decode($client->getResponse()->getContent());
        array_map(function ($prop) use ($feedback, $content) {
            $this->assertEquals($feedback->{$prop}, $content->{$prop});
            return $feedback->{$prop} == $content->{$prop};
        }, array('id', 'cardid', 'userid', 'feedback'));

        // check success message
        $this->assertEquals(get_string('feedbackaddedsuccessfully', $this->_app['plugin']), $content->successMessage);
    }

    /**
     * tests posting a (piece of) feedback
     * @global moodle_database $DB
     */
    public function test_put_feedback() {
        global $DB;

         // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // retrieve a section id for the course
        $section_id = $DB->get_field_sql('SELECT MIN(id) FROM {course_sections} WHERE course = :id', array('id' => $course->id));

        // the feedback string
        $feedback = 'Your work is reasonable, but your example sentences are generally awful.';

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array(1, $course->id, $section_id, 'table', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('id', 'cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, 1, $teacher->id, 'Better luck next time', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
                array(2, 1, $teacher->id, 'Not bad, but could do better', mktime(9, 5, 0, 1, 1, 2014), mktime(9, 5, 0, 1, 1, 2014)),
            ),
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the teacher
        $this->setUser($teacher);

        // create a (piece of) feedback to put
        $content = json_encode(array(
            'feedbackid' => 2,
            'feedback' => $feedback,
        ));

        // request the route
        $client = new Client($this->_app);
        $client->request('PUT', '/api/v1/' . $course->id . '/feedback/2', array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ), $content);
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/json', $client->getResponse()->headers->get('Content-Type'));

        // check the feedback updated successfully in the database
        $record = $DB->get_record('vocabcards_feedback', array(
            'id' => 2,
        ), '*', MUST_EXIST);
        $this->assertEquals($feedback, $record->feedback);

        // check body of response
        $content = json_decode($client->getResponse()->getContent());
        array_map(function ($prop) use ($record, $content) {
            $this->assertEquals($record->{$prop}, $content->{$prop});
            return $record->{$prop} == $content->{$prop};
        }, array('id', 'cardid', 'userid', 'feedback'));

        // check success message
        $this->assertEquals(get_string('feedbackupdatedsuccessfully', $this->_app['plugin']), $content->successMessage);
    }

    /**
     * tests deleting a (piece of) feedback
     * @global moodle_database $DB
     */
    public function test_delete_feedback() {
        global $DB;

        // define now
        $this->_app['now'] = $this->_app->protect(function () {
            return mktime(15, 0, 0, 5, 2, 2014);
        });

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a student to assign some words to
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // the feedback string
        $feedback = 'Your work is reasonable, but your example sentences are generally awful.';

        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array(1, $course->id, 1, 'table', $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, 1, $user->id, $teacher->id, null, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_feedback' => array(
                array('id', 'cardid', 'userid', 'feedback', 'timecreated', 'timemodified'),
                array(1, 1, $teacher->id, 'Better luck next time', mktime(9, 0, 0, 1, 1, 2014), mktime(9, 0, 0, 1, 1, 2014)),
                array(2, 1, $teacher->id, 'Not bad, but could do better', mktime(9, 5, 0, 1, 1, 2014), mktime(9, 5, 0, 1, 1, 2014)),
            ),
        )));

        // enrol the teacher on the course
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the teacher
        $this->setUser($teacher);

        // request the route
        $client = new Client($this->_app);
        $client->request('DELETE', '/api/v1/' . $course->id . '/feedback/2', array(
            'sesskey' => sesskey(),
        ), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals(204, $client->getResponse()->getStatusCode());

        // check the number of feedback records in the database is one less than previously
        $this->assertEquals(1, $DB->count_records('vocabcards_feedback'));

        // check that feedback 1 still exists
        $this->assertTrue($DB->record_exists('vocabcards_feedback', array('id' => 1)));
    }

}
