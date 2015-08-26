<?php

defined('MOODLE_INTERNAL') || die;

require_once $CFG->dirroot . '/mod/vocabcards/backup/moodle2/backup_vocabcards_stepslib.php';

class backup_vocabcards_activity_task extends backup_activity_task {

    protected function define_my_settings() {
        // empty
    }

    protected function define_my_steps() {
        $this->add_step(new backup_vocabcards_syllabus_structure_step('vocabcards_syllabus_structure', 'vocabcards_syllabus.xml'));
        $this->add_step(new backup_vocabcards_activity_structure_step('vocabcards_activity_structure', 'vocabcards.xml'));
    }

    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // link to the list of pages
        $search = "/(" . $base . "\/mod\/vocabcards\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@VOCABCARDSINDEX*$2@$', $content);

        // link to page view by moduleid
        $search = "/(" . $base . "\/mod\/vocabcards\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@VOCABCARDSVIEWBYID*$2@$', $content);

        return $content;
    }

}
