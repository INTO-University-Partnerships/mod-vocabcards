<?php

namespace mod_vocabcards\event;

defined('MOODLE_INTERNAL') || die();

class cron_finished extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     * @return string
     */
    public static function get_name() {
        return get_string('cron_finish_short', 'mod_vocabcards');
    }

    /**
     * Returns description of what happened.
     * @return string
     */
    public function get_description() {
        $a = array(
            'okays' => $this->other['okays'],
            'fails' => $this->other['fails']
        );
        return get_string('cron_finish', 'mod_vocabcards', $a);
    }

}
