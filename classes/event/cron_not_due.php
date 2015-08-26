<?php

namespace mod_vocabcards\event;

defined('MOODLE_INTERNAL') || die();

class cron_not_due extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['action'] = 'not_due';
        $this->data['target'] = 'cron';
    }

    /**
     * Return localised event name.
     * @return string
     */
    public static function get_name() {
        return get_string('cron_not_due_short', 'mod_vocabcards');
    }

    /**
     * Returns description of what happened.
     * @return string
     */
    public function get_description() {
        $a = array(
            'hour' => $this->other['hour'],
            'window_after_hour' => $this->other['window_after_hour'],
            'window_before_hour' => $this->other['window_before_hour'],
        );
        return get_string('cron_not_due', 'mod_vocabcards', $a);
    }

}
