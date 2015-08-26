<?php

use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/vocabcard_status.php';

class trigger_event_service_test extends advanced_testcase {

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
        if (!defined('SILEX_WEB_TEST')) {
            define('SILEX_WEB_TEST', true);
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
     * test to see if the trigger event service works.
     */
    public function test_trigger_event_service_successfully_triggers_event() {
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

        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // login as admin
        $this->setAdminUser();

        // create an instance of the vocabulary cards activity
        $module = $this->getDataGenerator()->create_module('vocabcards', array(
            'course' => $course->id,
        ));

        $this->loadDataSet($this->createArrayDataSet(array(
            'vocabcards_syllabus' => array(
                array('courseid', 'sectionid', 'word', 'creatorid', 'timecreated', 'timemodified'),
                array($course->id, 1, 'table', 1, mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
            'vocabcards_card' => array(
                array('wordid', 'ownerid', 'assignerid', 'groupid', 'status', 'phonemic', 'definition', 'timecreated', 'timemodified'),
                array(1, $user->id, 1, 1, vocabcard_status::IN_PROGRESS, '', '', mktime(9, 0, 0, 11, 5, 2013), mktime(9, 0, 0, 11, 5, 2013)),
            ),
        )));

        // params to be send to the event
        $params = array(
            'context' => context_course::instance($course->id),
            'objectid' => 1,
            'relateduserid' => $user->id,
            'other' => array(
                'wordid' => 1,
                'cmid' => $module->cmid,
            )
        );

        $event = $this->_app['trigger_event']('vocabcard_assigned', $params);
        $this->assertTrue($event->is_triggered());
    }

    /**
     * test to see a coding exception gets throws when not all necessary parameters are set
     * @expectedException coding_exception
     * @expectedExceptionMessage missing parameter 'context'
     */
    public function test_incorrect_parameters_throws_exception() {
        $this->_app['trigger_event']('vocabcard_assigned', array());
    }

    /**
     * test trigger unknown_event throws an exception
     * @expectedException coding_exception
     * @expectedExceptionMessage code file defining event 'unknown_event' does not exist
     */
    public function test_trigger_unknown_event_throws_exception() {
        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // params to be send to the event
        $params = array(
            'context' => context_course::instance($course->id),
            'objectid' => 1,
            'relateduserid' => $user->id
        );

        $this->_app['trigger_event']('unknown_event', $params);
    }

    /**
     * test trigger malformed_event throws an exception
     * @expectedException coding_exception
     * @expectedExceptionMessage code file defining event 'malformed_event' is missing event class by the same name
     */
    public function test_trigger_badly_written_code_file_throws_exception() {
        // create a course
        $course = $this->getDataGenerator()->create_course();

        // create a user
        $user = $this->getDataGenerator()->create_user();

        // params to be send to the event
        $params = array(
            'context' => context_course::instance($course->id),
            'objectid' => 1,
            'relateduserid' => $user->id
        );

        $this->_app['trigger_event']('malformed_event', $params);
    }

}
