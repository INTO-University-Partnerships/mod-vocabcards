<?php

use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/mod_vocabcards_cron_manager.php';

class mod_vocabcards_cron_manager_test extends advanced_testcase {

    /**
     * @var mod_vocabcards_cron_manager
     */
    protected $_cut;

    /**
     * setUp
     */
    protected function setUp() {
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    public function tearDown() {
        m::close();
    }

    /**
     * tests instantiation
     */
    public function test_instantiation() {
        $this->_cut = new mod_vocabcards_cron_manager();
        $this->assertInstanceOf('mod_vocabcards_cron_manager', $this->_cut);
    }

    /**
     * tests construction
     */
    public function test_construction() {
        $this->_cut = new mod_vocabcards_cron_manager();
        $now = time();
        $container = $this->_cut->get_container();
        $this->assertInstanceOf('Pimple', $container);
        $this->assertGreaterThanOrEqual($now, $container['now']);
        $this->assertTrue($container->offsetExists('window_after_hour'));
        $this->assertTrue($container->offsetExists('window_before_hour'));
        $this->assertGreaterThan($container['window_after_hour'], $container['window_before_hour']);
    }

    /**
     * tests whether the cron job is ready to run
     */
    public function test_is_due_returns_false_when_too_soon() {
        $container = new Pimple(array(
            'now' => mktime(0, 0, 0, 7, 23, 2014),
            'window_after_hour' => 1,
            'window_before_hour' => 7,
        ));
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->assertFalse($this->_cut->is_due());
    }

    /**
     * tests whether the cron job is ready to run
     */
    public function test_is_due_returns_true_when_within_window_and_lastcron_empty() {
        $container = new Pimple(array(
            'now' => mktime(3, 0, 0, 7, 23, 2014),
            'window_after_hour' => 1,
            'window_before_hour' => 7,
        ));
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->assertTrue($this->_cut->is_due());
    }

    /**
     * tests whether the cron job is ready to run
     * @global moodle_database $DB
     */
    public function test_is_due_returns_true_when_within_window_and_rlastcron_yesterday() {
        global $DB;
        $DB = m::mock('moodle_database')->shouldIgnoreMissing();
        $DB->shouldReceive('get_field')
            ->once()
            ->with('config_plugins', 'value', array(
                'plugin' => 'mod_vocabcards',
                'name' => 'rlastcron',
            ))
            ->andReturn(date('d/m/Y', mktime(23, 55, 0, 7, 22, 2014))); // yesterday
        $container = new Pimple(array(
            'now' => mktime(3, 0, 0, 7, 23, 2014),
            'window_after_hour' => 1,
            'window_before_hour' => 7,
        ));
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->assertTrue($this->_cut->is_due());
    }

    /**
     * tests whether the cron job is ready to run
     * @global moodle_database $DB
     */
    public function test_is_due_returns_false_when_within_window_and_rlastcron_today() {
        global $DB;
        $DB = m::mock('moodle_database')->shouldIgnoreMissing();
        $DB->shouldReceive('get_field')
            ->once()
            ->with('config_plugins', 'value', array(
                'plugin' => 'mod_vocabcards',
                'name' => 'rlastcron',
            ))
            ->andReturn(date('d/m/Y', mktime(1, 5, 0, 7, 23, 2014))); // before 9 am
        $container = new Pimple(array(
            'now' => mktime(5, 0, 0, 7, 23, 2014),
            'window_after_hour' => 1,
            'window_before_hour' => 7,
        ));
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->assertFalse($this->_cut->is_due());
    }

    /**
     * tests whether the cron job is ready to run
     */
    public function test_is_due_returns_false_when_too_late() {
        $container = new Pimple(array(
            'now' => mktime(8, 0, 0, 7, 23, 2014),
            'window_after_hour' => 1,
            'window_before_hour' => 7,
        ));
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->assertFalse($this->_cut->is_due());
    }

    /**
     * tests the manager stamps the rlastcron ('real' lastcron) value to today's date
     * @global moodle_database $DB
     */
    public function test_finish_updates_rlastcron() {
        global $DB;
        $container = new Pimple(array(
            'now' => mktime(5, 0, 0, 10, 1, 2013),
        ));
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->_cut->finish();
        $expected = date('d/m/Y', $container['now']);
        $actual = $DB->get_field('config_plugins', 'value', array('plugin' => 'mod_vocabcards', 'name' => 'rlastcron'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * test sending tutors the notification when there are cards to review
     */
    public function test_send_notification_to_tutors_that_have_cards_to_review() {
        $container = new Pimple();
        $container['vocabcards_notifier_model'] = $container->share(function () {
            $mock = m::mock('vocabcards_notifier_model');
            $mock->shouldReceive('get_tutors_that_have_cards_to_review')
                ->once()
                ->andReturn(array(
                    (object)array(
                        'uid' => 3,
                        'card_count' => 2,
                        'cmid' => 1,
                        'activity_name' => '001',
                    ),
                    (object)array(
                        'uid' => 3,
                        'card_count' => 1,
                        'cmid' => 2,
                        'activity_name' => '002',
                    ),
                    (object)array(
                        'uid' => 3,
                        'card_count' => 5,
                        'cmid' => 3,
                        'activity_name' => '003',
                    ),
                    (object)array(
                        'uid' => 3,
                        'card_count' => 2,
                        'cmid' => 4,
                        'activity_name' => '004',
                    ),
                ));
            return $mock;
        });
        $container['mod_vocabcards_notification_sender'] = $container->share(function () {
            $mock = m::mock('mod_vocabcards_notification_sender');
            $mock->shouldReceive('send_notification')
                ->times(4);
            return $mock;
        });
        $container['guzzler'] = $container->share(function () {
            $mock = m::mock('\GuzzleHttp\Client');
            return $mock;
        });
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->_cut->send_notification_to_tutors_that_have_cards_to_review();
        $this->assertEquals(4, $this->_cut->get_success_count());
        $this->assertEquals(-1, $this->_cut->get_failure_count());
    }

    /**
     * test sending tutors the notification when there are cards to review
     * tests that notifications are sent in batches (i.e. the same notification to multiple users)
     */
    public function test_send_notification_to_tutors_that_have_cards_to_review_batching() {
        $container = new Pimple();
        $container['vocabcards_notifier_model'] = $container->share(function () {
            $mock = m::mock('vocabcards_notifier_model');
            $mock->shouldReceive('get_tutors_that_have_cards_to_review')
                ->once()
                ->andReturn(array(
                    (object)array(
                        'uid' => 3,
                        'card_count' => 2,
                        'cmid' => 1,
                        'activity_name' => '001',
                    ),
                    (object)array(
                        'uid' => 4,
                        'card_count' => 2,
                        'cmid' => 1,
                        'activity_name' => '001',
                    ),
                    (object)array(
                        'uid' => 5,
                        'card_count' => 5,
                        'cmid' => 3,
                        'activity_name' => '003',
                    ),
                    (object)array(
                        'uid' => 6,
                        'card_count' => 5,
                        'cmid' => 3,
                        'activity_name' => '003',
                    ),
                ));
            return $mock;
        });
        $container['mod_vocabcards_notification_sender'] = $container->share(function () {
            global $CFG;
            $mock = m::mock('mod_vocabcards_notification_sender');

            // first batch
            $url = $CFG->wwwroot . '/vocabcards/1/card/feedback';
            $body = get_string('notify:vocabcards_to_review_many', 'mod_vocabcards', array(
                'name' => '001',
                'count' => 2,
                'url' => $url,
            ));
            $mock->shouldReceive('send_notification')
                ->once()
                ->with(m::any(), $body, array(3, 4), $url);

            // second batch
            $url = $CFG->wwwroot . '/vocabcards/3/card/feedback';
            $body = get_string('notify:vocabcards_to_review_many', 'mod_vocabcards', array(
                'name' => '003',
                'count' => 5,
                'url' => $url,
            ));
            $mock->shouldReceive('send_notification')
                ->once()
                ->with(m::any(), $body, array(5, 6), $url);

            return $mock;
        });
        $container['guzzler'] = $container->share(function () {
            $mock = m::mock('\GuzzleHttp\Client');
            return $mock;
        });
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->_cut->send_notification_to_tutors_that_have_cards_to_review();
        $this->assertEquals(2, $this->_cut->get_success_count());
        $this->assertEquals(-1, $this->_cut->get_failure_count());
    }

    /**
     * test sending tutors the notification when there are cards to review
     * when send_notification throws an exception
     */
    public function test_send_notification_to_tutors_that_have_cards_to_review_exception() {
        $container = new Pimple();
        $container['vocabcards_notifier_model'] = $container->share(function () {
            $mock = m::mock('vocabcards_notifier_model');
            $mock->shouldReceive('get_tutors_that_have_cards_to_review')
                ->once()
                ->andReturn(array(
                    (object)array(
                        'uid' => 3,
                        'card_count' => 2,
                        'cmid' => 1,
                        'activity_name' => '001',
                    ),
                    (object)array(
                        'uid' => 3,
                        'card_count' => 1,
                        'cmid' => 2,
                        'activity_name' => '002',
                    ),
                    (object)array(
                        'uid' => 3,
                        'card_count' => 1,
                        'cmid' => 3,
                        'activity_name' => '003',
                    ),
                ));
            return $mock;
        });
        $container['mod_vocabcards_notification_sender'] = $container->share(function () {
            $mock = m::mock('mod_vocabcards_notification_sender');
            $mock->shouldReceive('send_notification')
                ->once();
            $mock->shouldReceive('send_notification')
                ->twice()
                ->andThrow('Exception');
            return $mock;
        });
        $container['guzzler'] = $container->share(function () {
            $mock = m::mock('\GuzzleHttp\Client');
            return $mock;
        });
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->_cut->send_notification_to_tutors_that_have_cards_to_review();
        $this->assertEquals(1, $this->_cut->get_success_count());
        $this->assertEquals(2, $this->_cut->get_failure_count());
    }

    /**
     * test sending tutors the notification when there are words to assign
     */
    public function test_send_notification_to_tutors_that_have_words_to_assign() {
        $container = new Pimple(array(
            'now' => mktime(9, 0, 0, 8, 1, 2014), // Friday
        ));
        $container['vocabcards_notifier_model'] = $container->share(function () {
            $mock = m::mock('vocabcards_notifier_model');
            $mock->shouldReceive('get_tutors_that_have_words_to_assign')
                ->once()
                ->andReturn(array(
                    (object)array(
                        'uid' => 3,
                        'word_count' => 2,
                        'csid' => 1,
                        'cid' => 1,
                        'course_fullname' => '001',
                    ),
                    (object)array(
                        'uid' => 3,
                        'word_count' => 1,
                        'csid' => 2,
                        'cid' => 2,
                        'course_fullname' => '002',
                    ),
                    (object)array(
                        'uid' => 3,
                        'word_count' => 5,
                        'csid' => 3,
                        'cid' => 3,
                        'course_fullname' => '003',
                    ),
                ));
            return $mock;
        });
        $container['mod_vocabcards_notification_sender'] = $container->share(function () {
            $mock = m::mock('mod_vocabcards_notification_sender');
            $mock->shouldReceive('send_notification')
                ->times(3);
            return $mock;
        });
        $container['guzzler'] = $container->share(function () {
            $mock = m::mock('\GuzzleHttp\Client');
            return $mock;
        });
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->_cut->send_notification_to_tutors_that_have_words_to_assign();
        $this->assertEquals(3, $this->_cut->get_success_count());
        $this->assertEquals(-1, $this->_cut->get_failure_count());
    }

    /**
     * test sending tutors the notification when there are words to assign
     * tests that notifications are sent in batches (i.e. the same notification to multiple users)
     */
    public function test_send_notification_to_tutors_that_have_words_to_assign_batching() {
        $container = new Pimple(array(
            'now' => mktime(9, 0, 0, 8, 1, 2014), // Friday
        ));
        $container['vocabcards_notifier_model'] = $container->share(function () {
            $mock = m::mock('vocabcards_notifier_model');
            $mock->shouldReceive('get_tutors_that_have_words_to_assign')
                ->once()
                ->andReturn(array(
                    (object)array(
                        'uid' => 3,
                        'word_count' => 2,
                        'csid' => 1,
                        'cid' => 1,
                        'course_fullname' => '001',
                    ),
                    (object)array(
                        'uid' => 4,
                        'word_count' => 2,
                        'csid' => 1,
                        'cid' => 1,
                        'course_fullname' => '001',
                    ),
                    (object)array(
                        'uid' => 3,
                        'word_count' => 1,
                        'csid' => 2,
                        'cid' => 2,
                        'course_fullname' => '002',
                    ),
                    (object)array(
                        'uid' => 4,
                        'word_count' => 1,
                        'csid' => 2,
                        'cid' => 2,
                        'course_fullname' => '002',
                    ),
                    (object)array(
                        'uid' => 5,
                        'word_count' => 1,
                        'csid' => 2,
                        'cid' => 2,
                        'course_fullname' => '002',
                    ),
                    (object)array(
                        'uid' => 3,
                        'word_count' => 1,
                        'csid' => 3,
                        'cid' => 3,
                        'course_fullname' => '003',
                    ),
                ));
            return $mock;
        });
        $container['mod_vocabcards_notification_sender'] = $container->share(function () {
            global $CFG;
            $mock = m::mock('mod_vocabcards_notification_sender');

            // first batch
            $url = $CFG->wwwroot . '/vocabcards/assignment/1';
            $body = get_string('notify:vocabcards_to_assign_many', 'mod_vocabcards', array(
                'name' => '001',
                'count' => 2,
                'url' => $url,
            ));
            $mock->shouldReceive('send_notification')
                ->once()
                ->with(m::any(), $body, array(3, 4), $url);

            // second batch
            $url = $CFG->wwwroot . '/vocabcards/assignment/2';
            $body = get_string('notify:vocabcards_to_assign_one', 'mod_vocabcards', array(
                'name' => '002',
                'count' => 1,
                'url' => $url,
            ));
            $mock->shouldReceive('send_notification')
                ->once()
                ->with(m::any(), $body, array(3, 4, 5), $url);

            // third batch
            $url = $CFG->wwwroot . '/vocabcards/assignment/3';
            $body = get_string('notify:vocabcards_to_assign_one', 'mod_vocabcards', array(
                'name' => '003',
                'count' => 1,
                'url' => $url,
            ));
            $mock->shouldReceive('send_notification')
                ->once()
                ->with(m::any(), $body, array(3), $url);

            return $mock;
        });
        $container['guzzler'] = $container->share(function () {
            $mock = m::mock('\GuzzleHttp\Client');
            return $mock;
        });
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->_cut->send_notification_to_tutors_that_have_words_to_assign();
        $this->assertEquals(3, $this->_cut->get_success_count());
        $this->assertEquals(-1, $this->_cut->get_failure_count());
    }

    /**
     * test sending tutors the notification when there are words to assign
     * when send_notification throws an exception
     */
    public function test_send_notification_to_tutors_that_have_words_to_assign_exception() {
        $container = new Pimple(array(
            'now' => mktime(9, 0, 0, 8, 1, 2014), // Friday
        ));
        $container['vocabcards_notifier_model'] = $container->share(function () {
            $mock = m::mock('vocabcards_notifier_model');
            $mock->shouldReceive('get_tutors_that_have_words_to_assign')
                ->once()
                ->andReturn(array(
                    (object)array(
                        'uid' => 3,
                        'word_count' => 2,
                        'csid' => 1,
                        'cid' => 1,
                        'course_fullname' => '001',
                    ),
                    (object)array(
                        'uid' => 3,
                        'word_count' => 1,
                        'csid' => 2,
                        'cid' => 2,
                        'course_fullname' => '002',
                    ),
                    (object)array(
                        'uid' => 3,
                        'word_count' => 5,
                        'csid' => 3,
                        'cid' => 3,
                        'course_fullname' => '003',
                    ),
                ));
            return $mock;
        });
        $container['mod_vocabcards_notification_sender'] = $container->share(function () {
            $mock = m::mock('mod_vocabcards_notification_sender');
            $mock->shouldReceive('send_notification')
                ->once();
            $mock->shouldReceive('send_notification')
                ->twice()
                ->andThrow('Exception');
            return $mock;
        });
        $container['guzzler'] = $container->share(function () {
            $mock = m::mock('\GuzzleHttp\Client');
            return $mock;
        });
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->_cut->send_notification_to_tutors_that_have_words_to_assign();
        $this->assertEquals(1, $this->_cut->get_success_count());
        $this->assertEquals(2, $this->_cut->get_failure_count());
    }

    /**
     * test sending notification tutors that have words to assign when today is not the day to send out notifications
     */
    public function test_send_notification_to_tutors_when_today_is_not_assign_notify_day() {
        $container = new Pimple(array(
            'now' => mktime(9, 0, 0, 8, 2, 2014), // Saturday
        ));
        $this->_cut = new mod_vocabcards_cron_manager($container);
        $this->_cut->send_notification_to_tutors_that_have_words_to_assign();
        $this->assertEquals(-1, $this->_cut->get_success_count());
        $this->assertEquals(-1, $this->_cut->get_failure_count());
    }

}
