<?php

defined('MOODLE_INTERNAL') || die();

class vocabcards_syllabus_model {

    /**
     * c'tor
     */
    public function __construct() {
        // empty
    }

    /**
     * @global moodle_database $DB
     * @param integer $courseid
     * @param integer $sectionid
     * @param string $q
     * @return integer
     */
    public function get_total_by_courseid($courseid, $sectionid = 0, $q = '') {
        global $DB;
        $section_filter = empty($sectionid) ? '' : 'AND vs.sectionid = ?';
        $q_filter = $this->_get_q_query_string($q);
        $sql = <<<SQL
            SELECT COUNT(vs.id)
            FROM {vocabcards_syllabus} vs
            INNER JOIN {user} u ON vs.creatorid = u.id AND u.deleted = 0
            INNER JOIN {course_sections} cs ON vs.sectionid = cs.id
            WHERE vs.courseid = ? $section_filter $q_filter[0]
SQL;
        $params = array(
            $courseid,
        );
        if (!empty($section_filter)) {
            $params[] = $sectionid;
        }
        if (!empty($q_filter)) {
            $params[] = $q_filter[1];
        }
        return (integer)$DB->count_records_sql($sql, $params);
    }

    /**
     * @global moodle_database $DB
     * @param integer $courseid
     * @param integer $sectionid
     * @param string $q
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    public function get_all_by_courseid($courseid, $sectionid = 0, $q = '', $limitfrom = 0, $limitnum = 0) {
        global $DB;
        $section_filter = empty($sectionid) ? '' : 'AND vs.sectionid = ?';
        $q_filter = $this->_get_q_query_string($q);
        $retval = array();
        $sql = <<<SQL
            SELECT vs.*, u.firstname, u.lastname, cs.section AS sectionnum, COUNT(vc.id) AS cardcount
            FROM {vocabcards_syllabus} vs
            INNER JOIN {user} u ON vs.creatorid = u.id AND u.deleted = 0
            INNER JOIN {course_sections} cs ON vs.sectionid = cs.id
            LEFT JOIN {vocabcards_card} vc ON vc.wordid = vs.id
            WHERE vs.courseid = ? $section_filter $q_filter[0]
            GROUP BY vs.id, u.firstname, u.lastname, cs.section
            ORDER BY vs.word
SQL;
        $params = array(
            $courseid,
        );
        if (!empty($section_filter)) {
            $params[] = $sectionid;
        }
        if (!empty($q_filter)) {
            $params[] = $q_filter[1];
        }
        $results = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        if (empty($results)) {
            return $retval;
        }
        foreach ($results as $result) {
            $retval[] = $this->_obj_to_array($result);
        }
        return $retval;
    }

    /**
     * @global moodle_database $DB
     * @param integer $courseid
     * @param integer $id
     * @return array
     */
    public function get($courseid, $id) {
        global $DB;
        $sql = <<<SQL
            SELECT vs.*, u.firstname, u.lastname, cs.section AS sectionnum, COUNT(vc.id) AS cardcount
            FROM {vocabcards_syllabus} vs
            INNER JOIN {user} u ON vs.creatorid = u.id AND u.deleted = 0
            INNER JOIN {course_sections} cs ON vs.sectionid = cs.id
            LEFT JOIN {vocabcards_card} vc ON vc.wordid = vs.id
            WHERE vs.courseid = :courseid
            AND vs.id = :id
            GROUP BY vs.id, u.firstname, u.lastname, cs.section
            ORDER BY vs.word
SQL;
        $result = $DB->get_record_sql($sql, array(
            'courseid' => $courseid,
            'id' => $id,
        ), MUST_EXIST);
        return $this->_obj_to_array($result);
    }

    /**
     * @global moodle_database $DB
     * @throws invalid_parameter_exception
     * @param array $data
     * @param integer $now
     * @return array
     */
    public function save(array $data, $now) {
        global $DB;
        $data['timemodified'] = $now;
        $data['word'] = trim(strtolower($data['word']));
        if (array_key_exists('id', $data)) {
            if ($DB->record_exists_sql('SELECT * FROM {vocabcards_syllabus} WHERE courseid = ? AND word = ? AND id != ?', array(
                $data['courseid'],
                $data['word'],
                $data['id'],
            ))) {
                throw new invalid_parameter_exception(get_string('exception:word_already_exists', 'mod_vocabcards', $data['word']));
            }
            $DB->update_record('vocabcards_syllabus', (object)$data);
        } else {
            if ($DB->record_exists('vocabcards_syllabus', array(
                'courseid' => $data['courseid'],
                'word' => $data['word'],
            ))) {
                throw new invalid_parameter_exception(get_string('exception:word_already_exists', 'mod_vocabcards', $data['word']));
            }
            $data['timecreated'] = $data['timemodified'];
            $data['id'] = (integer)$DB->insert_record('vocabcards_syllabus', (object)$data);
        }
        return $this->get($data['courseid'], $data['id']);
    }

    /**
     * @global moodle_database $DB
     * @throws invalid_parameter_exception
     * @param integer $courseid
     * @param integer $id
     */
    public function delete($courseid, $id) {
        global $DB;
        $word = $DB->get_field('vocabcards_syllabus', 'word', array(
            'courseid' => $courseid,
            'id' => $id,
        ), MUST_EXIST);
        if ((integer)$DB->count_records('vocabcards_card', array('wordid' => $id)) > 0) {
            throw new invalid_parameter_exception(get_string('exception:word_already_assigned', 'mod_vocabcards', $word));
        }
        $DB->delete_records('vocabcards_syllabus', array(
            'courseid' => $courseid,
            'id' => $id,
        ));
    }

    /**
     * @global moodle_database $DB
     * @param string $q
     * @return array
     */
    protected function _get_q_query_string($q) {
        global $DB;
        $q = trim(preg_replace('/\s{2,}/', ' ', $q));
        if (empty($q)) {
            return null;
        }
        $word_like = $DB->sql_like('vs.word', '?', false);
        return array(" AND {$word_like}", "%$q%");
    }

    /**
     * @param object $obj
     * @return array
     */
    protected function _obj_to_array($obj) {
        return array(
            'id' => (integer)$obj->id,
            'courseid' => (integer)$obj->courseid,
            'sectionid' => (integer)$obj->sectionid,
            'sectionnum' => (integer)$obj->sectionnum,
            'word' => $obj->word,
            'creatorid' => (integer)$obj->creatorid,
            'creatorfullname' => $obj->firstname . ' ' . $obj->lastname,
            'cardcount' => (integer)$obj->cardcount,
            'timecreated' => userdate($obj->timecreated),
            'timemodified' => userdate($obj->timemodified),
        );
    }

}
