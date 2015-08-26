<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../models/vocabcards_notifier_model.php';
require_once __DIR__ . '/../src/mod_vocabcards_notification_sender.php';

class mod_vocabcards_cron_manager {

    const DATE_FORMAT = 'd/m/Y';

    /**
     * @var Pimple $container
     */
    protected $_container;

    /**
     * @var array
     */
    protected $_failures;

    /**
     * @var integer
     */
    protected $_success_count;

    /**
     * c'tor
     * @param Pimple $container
     */
    public function __construct(Pimple $container = null) {
        if (empty($container)) {
            $container = new Pimple();
        }
        if (!$container->offsetExists('now')) {
            $container['now'] = time();
        }
        if (!$container->offsetExists('window_after_hour')) {
            $container['window_after_hour'] = 1; // 1 a.m.
        }
        if (!$container->offsetExists('window_before_hour')) {
            $container['window_before_hour'] = 7; // 7 a.m.
        }
        if (!$container->offsetExists('assign_notify_day_of_week')) {
            $container['assign_notify_day_of_week'] = 5; // Friday (date('N', $this->_container['now']))
        }
        if (!$container->offsetExists('vocabcards_notifier_model')) {
            $container['vocabcards_notifier_model'] = $container->share(function ($container) {
                return new vocabcards_notifier_model();
            });
        }
        if (!$container->offsetExists('mod_vocabcards_notification_sender')) {
            $container['mod_vocabcards_notification_sender'] = $container->share(function ($container) {
                return new mod_vocabcards_notification_sender();
            });
        }
        if (!$container->offsetExists('guzzler')) {
            $container['guzzler'] = $container->share(function ($container) {
                $client = new \GuzzleHttp\Client();
                return $client;
            });
        }
        $this->_container = $container;
    }

    /**
     * accessor
     * @return Pimple
     */
    public function get_container() {
        return $this->_container;
    }

    /**
     * @return bool
     */
    public function is_due() {
        global $DB;

        // if the current hour isn't within the given window, then don't do anything
        $hour = (integer)date('G', (integer)$this->_container['now']);
        if ($hour < (integer)$this->_container['window_after_hour'] || $hour > (integer)$this->_container['window_before_hour']) {
            // log it
            $event = \mod_vocabcards\event\cron_not_due::create(array(
                'context' => context_system::instance(),
                'other' => array(
                    'hour' => (integer)$hour,
                    'window_after_hour' => (integer)$this->_container['window_after_hour'],
                    'window_before_hour' => (integer)$this->_container['window_before_hour'],
                )
            ));
            $event->trigger();
            return false;
        }

        // get rlastcron, a date string in 'd/m/Y' format
        $rlastcron = $DB->get_field('config_plugins', 'value', array(
            'plugin' => 'mod_vocabcards',
            'name' => 'rlastcron',
        ));

        // if rlastcron is empty, then it definitely hasn't been ran today
        if (empty($rlastcron)) {
            return true;
        }

        // if the date strings differ, then the cron is due to run
        return $rlastcron != date(self::DATE_FORMAT, $this->_container['now']);
    }

    /**
     * start cron process
     */
    public function start() {
        // log it
        $event = \mod_vocabcards\event\cron_started::create(array(
            'context' => context_system::instance(),
        ));
        $event->trigger();
    }

    /**
     * get tutors that have cards to review and send them a notification
     */
    public function send_notification_to_tutors_that_have_cards_to_review() {
        global $CFG;

        $vocabcards_notifier_model = $this->_container['vocabcards_notifier_model'];
        $sender = $this->_container['mod_vocabcards_notification_sender'];
        $guzzler = $this->_container['guzzler'];

        // batch up results
        $batches = array();
        $results = $vocabcards_notifier_model->get_tutors_that_have_cards_to_review();
        foreach ($results as $result) {
            $key = $result->cmid . '::' . $result->card_count;
            if (!array_key_exists($key, $batches)) {
                $batches[$key] = (object)array(
                    'cmid' => $result->cmid,
                    'activity_name' => $result->activity_name,
                    'card_count' => $result->card_count,
                    'userids' => array($result->uid),
                );
            } else {
                $batches[$key]->userids[] = $result->uid;
            }
        }

        // send notifications
        foreach ($batches as $key => $item) {
            $url = $CFG->wwwroot . '/vocabcards/' . $item->cmid . '/card/feedback';
            $body = get_string('notify:vocabcards_to_review_' . ($item->card_count == 1 ? 'one' : 'many'), 'mod_vocabcards', array(
                'name' => $item->activity_name,
                'count' => $item->card_count,
                'url' => $url,
            ));

            try {
                $sender->send_notification($guzzler, $body, $item->userids, $url);
                ++$this->_success_count;
            } catch (Exception $e) {
                $this->_failures[] = (array)$item;
            }
        }
    }

    /**
     * get tutors that have words to assign and send them a notification
     */
    public function send_notification_to_tutors_that_have_words_to_assign() {
        global $CFG;

        // if the current day is not the day of the week to notify about assigning words, then do nothing
        $day = (integer)date('N', $this->_container['now']);
        if ($day != $this->_container['assign_notify_day_of_week']) {
            return;
        }

        // start and end date
        // https://jira.into.uk.com:8443/browse/GO-237
        $datefrom = strtotime('next Monday', $this->_container['now']);
        $dateto = strtotime('+1 week', $datefrom);

        $vocabcards_notifier_model = $this->_container['vocabcards_notifier_model'];
        $sender = $this->_container['mod_vocabcards_notification_sender'];
        $guzzler = $this->_container['guzzler'];

        // batch up results
        $batches = array();
        $results = $vocabcards_notifier_model->get_tutors_that_have_words_to_assign($datefrom, $dateto);
        foreach ($results as $result) {
            $key = $result->cid . '::' . $result->word_count;
            if (!array_key_exists($key, $batches)) {
                $batches[$key] = (object)array(
                    'cid' => $result->cid,
                    'course_fullname' => $result->course_fullname,
                    'word_count' => $result->word_count,
                    'userids' => array($result->uid),
                );
            } else {
                $batches[$key]->userids[] = $result->uid;
            }
        }

        // send notifications
        foreach ($batches as $key => $item) {
            $url = $CFG->wwwroot . '/vocabcards/assignment/' . $item->cid;
            $body = get_string('notify:vocabcards_to_assign_' . ($item->word_count == 1 ? 'one' : 'many'), 'mod_vocabcards', array(
                'name' => $item->course_fullname,
                'count' => $item->word_count,
                'url' => $url,
            ));

            try {
                $sender->send_notification($guzzler, $body, $item->userids, $url);
                ++$this->_success_count;
            } catch (Exception $e) {
                $this->_failures[] = (array)$item;
            }
        }
    }

    /**
     * returns the number of failures
     * @return integer
     */
    public function get_failure_count() {
        return isset($this->_failures) ? count($this->_failures) : -1;
    }

    /**
     * returns the number of successes
     * @return integer
     */
    public function get_success_count() {
        return isset($this->_success_count) ? $this->_success_count : -1;
    }

    /**
     * gets the failures
     * @return array
     */
    public function get_failures() {
        return isset($this->_failures) ? $this->_failures : array();
    }

    /**
     * finish cron process
     */
    public function finish(array $failures = null) {
        if (!isset($failures)) {
            $failures = $this->get_failures();
        }

        $other = array(
            'okays' => isset($this->_success_count) ? $this->_success_count : 0,
            'fails' => count($failures),
        );

        // log it
        $event = \mod_vocabcards\event\cron_finished::create(array(
            'context' => context_system::instance(),
            'other' => $other,
        ));
        $event->trigger();

        // update rlastcron ('real' lastcron)
        set_config('rlastcron', date(self::DATE_FORMAT, $this->_container['now']), 'mod_vocabcards');
    }

}
