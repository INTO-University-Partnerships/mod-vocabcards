<?php

// use the Client and Request classes
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/vocabcard_status.php';

class mod_vocabcards_web_test extends advanced_testcase {

    /**
     * @var Silex\Application
     */
    protected $_app;

    /**
     * setUp
     */
    public function setUp() {
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
    }

    /**
     * tearDown
     */
    public function tearDown() {
        $_GET = array();
        $_POST = array();
    }

    /**
     * tests a non-existent route
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function test_non_existent_route() {
        $client = new Client($this->_app);
        $client->request('GET', '/does_not_exist');
    }

    /**
     * tests the instances route that shows all activity instances (i.e. course modules) in a certain course
     * @global moodle_database $DB
     */
    public function test_instances_route() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // create a handful of modules within the course
        foreach (range(1, 5) as $i) {
            $module = $this->getDataGenerator()->create_module('vocabcards', array(
                'course' => $course->id,
            ));
        }

        // login the user
        $this->setUser($user);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/instances/' . $course->id);
        $this->assertTrue($client->getResponse()->isOk());
        // check the page content
        foreach (range(1, 5) as $i) {
            $this->assertContains('Vocabulary cards ' . $i, $client->getResponse()->getContent());
        }
        $this->assertNotContains('Vocabulary cards 6', $client->getResponse()->getContent());
    }

    /**
     * tests the 'byinstanceid' route that lets you view a vocabcards by instance id (as opposed to course module id)
     */
    public function test_byinstanceid_route() {
        global $CFG;
        $client = new Client($this->_app);
        $course = $this->getDataGenerator()->create_course();
        $vocabcards = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $course->id,
        ));
        $client->request('GET', '/instance/' . $vocabcards->id);
        $url = $CFG->wwwroot . SLUG . $this->_app['url_generator']->generate('view', array(
            'cmid' => $vocabcards->cmid,
        ));
        $this->assertTrue($client->getResponse()->isRedirect($url));
    }

    /**
     * tests the 'view' route that lets you view a vocabcards by course module id
     * @global moodle_database $DB
     */
    public function test_view_route() {
        global $DB;

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a course module
        $vocabcards = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $course->id,
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/' . $vocabcards->cmid);
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests the route that serves the vocabulary syllabus app for a given course requires a certain capability
     * @global moodle_database $DB
     * @expectedException required_capability_exception
     * @expectedExceptionMessage Sorry, but you do not currently have permissions to do that (Manage vocabulary syllabus)
     */
    public function test_syllabus_route_requires_capability() {
        global $DB;

        // create a non-admin user
        $user = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // login the user
        $this->setUser($user);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/syllabus/' . $course->id);
    }

    /**
     * tests the route that serves the vocabulary syllabus app raises an error if an invalid courseid is given
     * @expectedException moodle_exception
     * @expectedExceptionMessage You are trying to use an invalid course ID
     */
    public function test_syllabus_route_invalid_courseid() {
        // create a non-admin user
        $user = $this->getDataGenerator()->create_user(array(
            'email' => 'foobar@into.uk.com',
        ));

        // login the user
        $this->setUser($user);

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/syllabus/999');
    }

    /**
     * tests the syllabus route
     * @moodle_database $DB
     */
    public function test_syllabus_route() {
        global $DB;

        // create a non-admin user
        $user = $this->getDataGenerator()->create_user(array(
            'email' => 'foobar@into.uk.com',
        ));

        // login the user
        $this->setUser($user);

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 5,
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'editingteacher',
        )));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/syllabus/' . $course->id);
        $this->assertTrue($client->getResponse()->isOk());

        // ensure all the sections have been created
        $this->assertEquals(6, $DB->count_records('course_sections', array(
            'course' => $course->id,
        )));
    }

    /**
     * tests the repository route
     * @moodle_database $DB
     */
    public function test_repository_route() {
        global $DB;

        // create a non-admin user
        $user = $this->getDataGenerator()->create_user(array(
            'email' => 'foobar@into.uk.com',
        ));

        // login the user
        $this->setUser($user);

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/library/' . $course->id);
        $this->assertTrue($client->getResponse()->isOk());
    }

    /**
     * tests the export repository route when there are no cards in the library
     * @global moodle_database $DB
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function test_export_repository_route_returns_not_found_when_empty() {
        global $DB;

        // create a non-admin user
        $user = $this->getDataGenerator()->create_user(array(
            'email' => 'foobar@into.uk.com',
        ));

        // login the user
        $this->setUser($user);

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/library/' . $course->id .'/pdf');
        $this->assertTrue($client->getResponse()->isNotFound());
    }

    /**
     * tests the export route when there are cards in the library
     */
    public function test_export_repository_renders_pdf() {
        global $DB;

        // create a non-admin user
        $user = $this->getDataGenerator()->create_user(array(
            'email' => 'foobar@into.uk.com',
        ));

        // login the user
        $this->setUser($user);

        // create a teacher
        $teacher = $this->getDataGenerator()->create_user();

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'student',
        )));

        // some words and their phonemics
        $words = array('table', 'chair', 'dog', 'run', 'eat', 'good');
        $phonemics = array('teɪb(ə)l', 'tʃɛː', 'dɒg', 'rʌn', 'dɒg', 'gʊd');

        // add words to syllabus
        $vocabcards_syllabus = array_map(function ($id, $word) use ($course, $teacher) {
            return array($id, $course->id, 1, $word, $teacher->id, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013));
        }, range(1, count($words)), $words);
        array_unshift($vocabcards_syllabus, array('id', 'courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'));

        // create one card from each of the words
        $vocabcards_card = array_map(function ($wordid, $phonemic) use ($user, $teacher) {
            return array($wordid, $wordid, $user->id, $teacher->id, null, vocabcard_status::IN_REPOSITORY, $phonemic, 'definition goes here', mktime(9, 0, 0, 12, 1, 2013), mktime(9, 0, 0, 12, 1, 2013));
        }, range(1, count($words)), $phonemics);
        array_unshift($vocabcards_card, array('id', 'wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'));


        // seed the database
        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => $vocabcards_syllabus,
            'vocabcards_card' => $vocabcards_card,
        )));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/library/' . $course->id .'/pdf');

        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals('application/pdf', $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * tests the assignment route
     * @moodle_database $DB
     */
    public function test_assignment_route() {
        global $DB;

        // create a non-admin user
        $user = $this->getDataGenerator()->create_user(array(
            'email' => 'foobar@into.uk.com',
        ));

        // login the user
        $this->setUser($user);

        // create a course
        $course = $this->getDataGenerator()->create_course(array(
            'numsections' => 5,
        ));

        // enrol the user on the course
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $DB->get_field('role', 'id', array(
            'shortname' => 'teacher',
        )));

        // request the page
        $client = new Client($this->_app);
        $client->request('GET', '/assignment/' . $course->id);
        $this->assertTrue($client->getResponse()->isOk());

        // ensure all the sections have been created
        $this->assertEquals(6, $DB->count_records('course_sections', array(
            'course' => $course->id,
        )));
    }

    /**
     * tests serving up a partial that doesn't exist
     * @expectedException file_serving_exception
     * @expectedExceptionMessage Can not serve file - server configuration problem. (Non-existent partial)
     */
    public function test_partials_route_does_not_exist() {
        $client = new Client($this->_app);
        $client->request('GET', '/partials/route/does_not_exist.twig', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
    }

    /**
     * tests serving up a partial without an XMLHttpRequest
     * @expectedException file_serving_exception
     * @expectedExceptionMessage Can not serve file - server configuration problem. (AJAX requests only)
     */
    public function test_partials_route_non_xmlhttprequest() {
        $this->_app['debug'] = false;
        $client = new Client($this->_app);
        $client->request('GET', '/partials/route/syllabus.twig', array(), array(), array(
            // empty
        ));
    }

    /**
     * tests serving up a partial
     */
    public function test_partials_route() {
        $client = new Client($this->_app);
        $client->request('GET', '/partials/route/syllabus.twig', array(), array(), array(
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ));
        $this->assertTrue($client->getResponse()->isOk());
    }

}
