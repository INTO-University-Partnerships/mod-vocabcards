<?php

defined('MOODLE_INTERNAL') || die();

class vocabcards_feedback_model {

    /**
     * c'tor
     */
    public function __construct() {
        // empty
    }

    /**
     * @global moodle_database $DB
     * @param integer $cardid
     * @param array $groups
     * @return integer
     */
    public function get_total_by_cardid($cardid, array $groups = null) {
        global $DB;
        $group_restrict_where = $this->_group_restrict_where($groups);
        $sql = <<<SQL
            SELECT COUNT(vf.id)
            FROM {vocabcards_feedback} vf
            INNER JOIN {vocabcards_card} vc ON vf.cardid = vc.id
            INNER JOIN {user} u ON vf.userid = u.id AND u.deleted = 0
            WHERE vf.cardid = :cardid
            $group_restrict_where
SQL;
        $params = array(
            'cardid' => $cardid,
        );
        return (integer)$DB->count_records_sql($sql, $params);
    }

    /**
     * @global moodle_database $DB
     * @param integer $cardid
     * @param array $groups
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    public function get_all_by_cardid($cardid, array $groups = null, $limitfrom = 0, $limitnum = 0) {
        global $DB;
        $group_restrict_where = $this->_group_restrict_where($groups);
        $retval = array();
        $sql = <<<SQL
            SELECT vf.*, u.firstname, u.lastname
            FROM {vocabcards_feedback} vf
            INNER JOIN {vocabcards_card} vc ON vf.cardid = vc.id
            INNER JOIN {user} u ON vf.userid = u.id AND u.deleted = 0
            WHERE vf.cardid = :cardid
            $group_restrict_where
            ORDER BY vf.timecreated DESC
SQL;
        $params = array(
            'cardid' => $cardid,
        );
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
     * @param integer $id
     * @param array $groups
     * @return array
     */
    public function get($id, array $groups = null) {
        global $DB;
        $group_restrict_where = $this->_group_restrict_where($groups);
        $sql = <<<SQL
            SELECT vf.*, u.firstname, u.lastname
            FROM {vocabcards_feedback} vf
            INNER JOIN {vocabcards_card} vc ON vf.cardid = vc.id
            INNER JOIN {user} u ON vf.userid = u.id AND u.deleted = 0
            WHERE vf.id = :id
            $group_restrict_where
SQL;
        $result = $DB->get_record_sql($sql, array(
            'id' => $id,
        ), MUST_EXIST);
        return $this->_obj_to_array($result);
    }

    /**
     * @global moodle_database $DB
     * @param array $data
     * @param integer $now
     * @return array
     */
    public function save(array $data, $now) {
        global $DB;
        $data['timemodified'] = $now;
        if (array_key_exists('id', $data)) {
            $DB->update_record('vocabcards_feedback', (object)$data);
        } else {
            $data['timecreated'] = $data['timemodified'];
            $data['id'] = (integer)$DB->insert_record('vocabcards_feedback', (object)$data);
        }
        return $this->get($data['id']);
    }

    /**
     * @global moodle_database $DB
     * @param integer $id
     */
    public function delete($id) {
        global $DB;
        $DB->get_field('vocabcards_feedback', 'id', array(
            'id' => $id,
        ), MUST_EXIST);
        $DB->delete_records('vocabcards_feedback', array(
            'id' => $id,
        ));
    }

    /**
     * @param array $groups
     * @return string
     */
    protected function _group_restrict_where(array $groups = null)  {
        return empty($groups) ? '' : 'AND (vc.groupid IN (' . implode(',', $groups) . ') OR vc.groupid IS NULL)';
    }

    /**
     * @param object $obj
     * @return array
     */
    protected function _obj_to_array($obj) {
        return array(
            'id' => (integer)$obj->id,
            'cardid' => (integer)$obj->cardid,
            'userid' => (integer)$obj->userid,
            'userfullname' => $obj->firstname . ' ' . $obj->lastname,
            'feedback' => $obj->feedback,
            'timecreated' => userdate($obj->timecreated),
        );
    }

}
