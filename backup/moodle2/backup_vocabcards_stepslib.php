<?php

defined('MOODLE_INTERNAL') || die;

class backup_vocabcards_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        $vocabcards = new backup_nested_element('vocabcards', array('id'), array(
            'name',
            'startdate',
            'header',
            'footer',
        ));

        $vocabcards->set_source_table('vocabcards', array('id' => backup::VAR_ACTIVITYID));

        return $this->prepare_activity_structure($vocabcards);
    }

}

class backup_vocabcards_syllabus_structure_step extends backup_structure_step {

    protected function define_structure() {
        $syllabus = new backup_nested_element('vocabcards_syllabus');

        $words = new backup_nested_element('word', array('id'), array(
            'word',
            'sectionnum',
        ));

        $sql = <<<SQL
            SELECT vs.id AS id, vs.word AS word, cs.section AS sectionnum
            FROM {vocabcards_syllabus} vs
            INNER JOIN {course_sections} cs ON vs.sectionid = cs.id
            WHERE vs.courseid = ?
SQL;
        $words->set_source_sql($sql, array(backup::VAR_COURSEID));
        $syllabus->add_child($words);

        return $syllabus;
    }

}
