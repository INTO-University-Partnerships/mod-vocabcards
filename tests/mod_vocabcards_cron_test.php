<?php

use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../lib.php';

class mod_vocabcards_cron_test extends advanced_testcase {

    /**
     * setUp
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    public function tearDown() {
        m::close();
    }

    /**
     * tests the cron on a Friday
     * (on Fridays, the 'words to assign' notifications are sent)
     * @global moodle_database $DB
     */
    public function test_cron_on_friday() {
        global $DB;

        // mock out vocabcards_notifier_model, mod_vocabcards_notification_sender, guzzler
        $container = new Pimple();
        $container['window_after_hour'] = 2;
        $container['window_before_hour'] = 5;
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
                ));
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
                ->times(6);
            return $mock;
        });
        $container['guzzler'] = $container->share(function () {
            $mock = m::mock('\GuzzleHttp\Client');
            return $mock;
        });

        // run the cron for each hour in a day
        foreach (range(0, 23) as $hour) {
            $container['now'] = mktime($hour, 15, 0, 8, 1, 2014); // a Friday
            $this->assertSame($hour, (integer)date('G', $container['now']));
            vocabcards_cron($container);
        }

        // check the rlastcron value gets stamped
        $expected = date(mod_vocabcards_cron_manager::DATE_FORMAT, mktime(2, 15, 0, 8, 1, 2014));
        $actual = $DB->get_field('config_plugins', 'value', array('name' => 'rlastcron', 'plugin' => 'mod_vocabcards'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * tests the cron not on a Friday
     * (on days other than Fridays, the 'words to assign' notifications are not sent)
     * @global moodle_database $DB
     */
    public function test_cron_not_on_friday() {
        global $DB;

        // mock out vocabcards_notifier_model, mod_vocabcards_notification_sender, guzzler
        $container = new Pimple();
        $container['window_after_hour'] = 2;
        $container['window_before_hour'] = 5;
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

        // run the cron for each hour in a day
        foreach (range(0, 23) as $hour) {
            $container['now'] = mktime($hour, 15, 0, 8, 7, 2014); // a Thursday
            $this->assertSame($hour, (integer)date('G', $container['now']));
            vocabcards_cron($container);
        }

        // check the rlastcron value gets stamped
        $expected = date(mod_vocabcards_cron_manager::DATE_FORMAT, mktime(2, 15, 0, 8, 7, 2014));
        $actual = $DB->get_field('config_plugins', 'value', array('name' => 'rlastcron', 'plugin' => 'mod_vocabcards'));
        $this->assertEquals($expected, $actual);
    }

}
