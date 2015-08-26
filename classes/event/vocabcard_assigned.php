<?php

namespace mod_vocabcards\event;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/mod_vocabcards_base_event.php';

class vocabcard_assigned extends mod_vocabcards_base_event {

    protected function init() {
        $this->data['objecttable'] = 'vocabcards_card';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public function get_description() {
        return "The user with id '$this->userid' has assigned a Vocabcard with wordid '{$this->other['wordid']}' to the user with id '$this->relateduserid'".
        " in the Vocabcard activity with course module id '$this->contextinstanceid'.";
    }

}
