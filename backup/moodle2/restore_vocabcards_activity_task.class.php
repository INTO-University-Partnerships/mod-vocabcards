<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/vocabcards/backup/moodle2/restore_vocabcards_stepslib.php';

class restore_vocabcards_activity_task extends restore_activity_task {

    protected function define_my_settings() {
        // empty
    }

    protected function define_my_steps() {
        $this->add_step(new restore_vocabcards_syllabus_structure_step('vocabcards_syllabus_structure', 'vocabcards_syllabus.xml'));
        $this->add_step(new restore_vocabcards_activity_structure_step('vocabcards_activity_structure', 'vocabcards.xml'));
    }

    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('vocabcards', array('header', 'footer'), 'vocabcards');

        return $contents;
    }

    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('VOCABCARDSVIEWBYID', '/mod/vocabcards/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('VOCABCARDSINDEX', '/mod/vocabcards/index.php?id=$1', 'course');

        return $rules;
    }

}
