<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/mod_vocabcards_notification_sender.php';

class mod_vocabcards_observer {

    /**
     * @global moodle_database $DB
     * @param \core\event\course_deleted $event
     * @return boolean
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;
        $DB->delete_records('vocabcards_syllabus', array(
            'courseid' => $event->objectid,
        ));
        return true;
    }

    /**
     * triggered when a user has been assigned a vocabulary card
     * @throws moodle_exception
     * @param \mod_vocabcards\event\vocabcard_assigned $event
     */
    public static function vocabcard_assigned(\mod_vocabcards\event\vocabcard_assigned $event) {
        global $CFG, $DB;

        if (empty($event->other['wordid'])) {
            throw new coding_exception('missing parameters');
        }

        // if there's no course module id (cmid) then just link to the course as a whole
        $cmid = array_key_exists('cmid', $event->other) ? $event->other['cmid'] : 0;
        $url = $CFG->wwwroot . '/course/view.php?id=' . $event->contextinstanceid;
        if (!empty($cmid)) {
            $url = $CFG->wwwroot . SLUG . '/' . $cmid . '/card/edit/' . $event->objectid;
        }

        $body = get_string('notify:' . __FUNCTION__, 'mod_vocabcards', array(
            'word' => $DB->get_field('vocabcards_syllabus', 'word', array('id' => $event->other['wordid'])),
            'url' => $url,
        ));
        $userid = (integer)$event->relateduserid;

        self::_send_notification($event, $body, $userid, $url);
    }

    /**
     * triggered when a user leaves feedback on a vocabulary card that's in review
     * @param \mod_vocabcards\event\feedback_given_by_tutor $event
     */
    public static function feedback_given_by_tutor(\mod_vocabcards\event\feedback_given_by_tutor $event) {
        global $CFG;

        $url = $CFG->wwwroot . SLUG . '/' . $event->other['cmid'] . '/card/edit/' . $event->objectid;
        $body = get_string('notify:' . __FUNCTION__, 'mod_vocabcards', array(
            'url' => $url,
        ));
        $userid = (integer)$event->relateduserid;

        self::_send_notification($event, $body, $userid, $url);
    }

    /**
     * triggered when a user leaves feedback on a vocabulary card that's in the repository
     * @param \mod_vocabcards\event\feedback_given_in_repository $event
     */
    public static function feedback_given_in_repository(\mod_vocabcards\event\feedback_given_in_repository $event) {
        global $CFG;

        $url = $CFG->wwwroot . SLUG . '/library/' . $event->contextinstanceid . '/card/edit/' . $event->objectid;
        $body = get_string('notify:' . __FUNCTION__, 'mod_vocabcards', array(
            'url' => $url,
        ));
        $userid = (integer)$event->relateduserid;

        self::_send_notification($event, $body, $userid, $url);
    }

    /**
     * @param \mod_vocabcards\event\mod_vocabcards_base_event $event
     * @param string $body
     * @param integer $userid
     * @param string $url
     */
    protected static function _send_notification(\mod_vocabcards\event\mod_vocabcards_base_event $event, $body, $userid, $url) {
        try {
            $sender = new mod_vocabcards_notification_sender();
            $sender->send_notification($event->get_guzzler(), $body, array($userid), $url);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

}
