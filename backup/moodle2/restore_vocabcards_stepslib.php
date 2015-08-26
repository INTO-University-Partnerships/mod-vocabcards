<?php

defined('MOODLE_INTERNAL') || die;

class restore_vocabcards_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('vocabcards', '/activity/vocabcards');
        return $this->prepare_activity_structure($paths);
    }

    protected function process_vocabcards($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $data->timecreated = $data->timemodified = time();

        $newitemid = $DB->insert_record('vocabcards', $data);
        $this->apply_activity_instance($newitemid);
    }

}

class restore_vocabcards_syllabus_structure_step extends restore_structure_step {

    /**
     * @var array
     */
    protected $_words = array();

    /**
     * @return array
     */
    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('word', '/vocabcards_syllabus/word');
        return $paths;
    }

    /**
     * @param array $data
     */
    protected function process_word($data) {
        $this->_words[] = $data;
    }

    /**
     * populates the database with words
     * @global moodle_database $DB
     */
    protected function after_restore() {
        global $DB;

        // get the courseid
        $courseid = $this->get_courseid();

        // get sections from the course
        $sql = <<<SQL
            SELECT id, section
            FROM {course_sections}
            WHERE course = ?
SQL;
        $sections = $DB->get_records_sql($sql, array(
            'course' => $courseid,
        ));

        // map of section numbers to section ids
        $sectionids = array();
        foreach ($sections as $section) {
            $sectionids[$section->section] = $section->id;
        }

        // insert each word
        foreach ($this->_words as $word) {
            $this->_insert_word($word, $sectionids[$word['sectionnum']]);
        }
    }

    /**
     * @param array $word
     * @param integer $sectionid
     * @global moodle_database $DB
     */
    private function _insert_word(array $word, $sectionid) {
        global $DB, $USER;

        // get the courseid
        $courseid = $this->get_courseid();

        // see whether the word already exists (and if so, do nothing)
        if ($DB->record_exists('vocabcards_syllabus', array(
            'courseid' => $courseid,
            'word' => $word['word'],
        ))) {
            return;
        }

        // determine the creator - either the logged in user, or the admin user if there's no logged in user
        $admin = get_admin();
        $creatorid = empty($USER->id) ? $admin->id : $USER->id;

        // otherwise, insert the word into the syllabus
        $now = time();
        $obj = (object)array(
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'word' => $word['word'],
            'creatorid' => $creatorid,
            'timecreated' => $now,
            'timemodified' => $now,
        );
        $DB->insert_record('vocabcards_syllabus', $obj);
    }

}
