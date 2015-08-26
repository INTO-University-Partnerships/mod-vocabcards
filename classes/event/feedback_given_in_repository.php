<?php

namespace mod_vocabcards\event;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/mod_vocabcards_base_event.php';

class feedback_given_in_repository extends mod_vocabcards_base_event {

    protected function init() {
        $this->data['objecttable'] = 'vocabcards_feedback';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public function get_description() {
        return "The user with id '$this->userid' has left feedback on vocabcard with id '{$this->objectid}' to the user with id '$this->relateduserid'".
        " in the Vocabcard activity with course module id '$this->contextinstanceid'.";
    }

}
