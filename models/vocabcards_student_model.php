<?php

defined('MOODLE_INTERNAL') || die();

class vocabcards_student_model {

    /**
     * c'tor
     */
    public function __construct() {
        // empty
    }

    /**
     * gets all the groups in the given course
     * @global moodle_database $DB
     * @param integer $courseid
     * @return array
     */
    public function get_groups_in_course($courseid) {
        global $DB;

        // query
        $sql = <<<SQL
            SELECT g.id, g.name
            FROM {groups} g WHERE g.courseid = :courseid
            ORDER BY g.name
SQL;

        // return records
        $results = $DB->get_records_sql($sql, array('courseid' => $courseid));
        $retval = [];
        foreach ($results as $result) {
            $retval[] = $this->_group_obj_to_array($result);
        }
        return $retval;
    }

    /**
     * gets the groups in the given course that the given user is a member of
     * @param integer $courseid
     * @param integer $userid
     * @return array
     */
    public function get_user_groups_in_course($courseid, $userid) {
        global $DB;

        // query
        $sql = <<<SQL
            SELECT g.id, g.name
            FROM {groups} g
            INNER JOIN {groups_members} gm ON gm.groupid = g.id AND gm.userid = :userid
            WHERE g.courseid = :courseid
            ORDER BY g.name
SQL;

        // return records
        $results = $DB->get_records_sql($sql, array('courseid' => $courseid, 'userid' => $userid));
        $retval = [];
        foreach ($results as $result) {
            $retval[] = $this->_group_obj_to_array($result);
        }
        return $retval;
    }

    /**
     * @global moodle_database $DB
     * @param integer $courseid
     * @param array $groups
     * @param integer $groupid
     * @return integer
     */
    public function get_student_total_by_courseid($courseid, array $groups = null, $groupid = 0) {
        global $DB;

        // group restriction
        $group_restrict_table = empty($groups) ? '' : 'INNER JOIN {groups_members} gm1 ON ra.userid = gm1.userid';
        $group_restrict_where = empty($groups) ? '' : 'WHERE gm1.groupid IN (' . implode(',', $groups) . ')';

        // group filter
        $group_filter = empty($groupid) ? '' : 'INNER JOIN {groups_members} gm2 ON ra.userid = gm2.userid AND gm2.groupid = ?';

        // query
        $sql = <<<SQL
            SELECT COUNT(u.id)
            FROM {role_assignments} ra
            INNER JOIN {user} u ON ra.userid = u.id AND u.deleted = 0
            INNER JOIN {role} r ON ra.roleid = r.id AND r.shortname = ?
            INNER JOIN {context} c ON ra.contextid = c.id AND c.contextlevel = ? AND c.instanceid = ?
            $group_restrict_table
            $group_filter
            $group_restrict_where
SQL;

        // parameters
        $params = array(
            'student',
            CONTEXT_COURSE,
            $courseid,
        );

        // group filter parameter
        if (!empty($group_filter)) {
            $params[] = $groupid;
        }

        // return count
        return (integer)$DB->count_records_sql($sql, $params) + 1;
    }

    /**
     * gets the users that have explicit students role assignments in the given course
     * @global moodle_database $DB
     * @param integer $courseid
     * @param integer $userid
     * @param array $groups
     * @param integer $groupid
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    public function get_students_in_course($courseid, $userid, array $groups = null, $groupid = 0, $limitfrom = 0, $limitnum = 0) {
        global $DB;

        // group restriction
        $group_restrict_table = empty($groups) ? '' : 'INNER JOIN {groups_members} gm1 ON ra.userid = gm1.userid';
        $group_restrict_where = empty($groups) ? '' : 'WHERE gm1.groupid IN (' . implode(',', $groups) . ')';

        // group filter
        $group_filter = empty($groupid) ? '' : 'INNER JOIN {groups_members} gm2 ON ra.userid = gm2.userid AND gm2.groupid = ?';

        // query
        $sql = <<<SQL
            SELECT u.id, u.firstname, u.lastname
            FROM {role_assignments} ra
            INNER JOIN {user} u ON ra.userid = u.id AND u.deleted = 0
            INNER JOIN {role} r ON ra.roleid = r.id AND r.shortname = ?
            INNER JOIN {context} c ON ra.contextid = c.id AND c.contextlevel = ? AND c.instanceid = ?
            $group_restrict_table
            $group_filter
            $group_restrict_where
            UNION
            SELECT u.id, u.firstname, u.lastname
            FROM {user} u WHERE u.id = ? AND u.deleted = 0
            ORDER BY 2, 3
SQL;

        // parameters
        $params = array(
            'student',
            CONTEXT_COURSE,
            $courseid,
        );

        // group filter parameter
        if (!empty($group_filter)) {
            $params[] = $groupid;
        }

        // specific user parameter
        $params[] = $userid;

        // return records
        $results = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        $retval = [];
        foreach ($results as $result) {
            $retval[] = $this->_student_obj_to_array($result, $userid);
        }
        return $retval;
    }

    /**
     * @param object $obj
     * @param integer $userid
     * @return array
     */
    protected function _student_obj_to_array($obj, $userid) {
        $retval = array(
            'id' => (integer)$obj->id,
            'studentfullname' => $obj->firstname . ' ' . $obj->lastname,
        );
        if ($obj->id == $userid) {
            $retval['studentfullname'] .= ' (' . get_string('you', 'mod_vocabcards') . ')';
        }
        return $retval;
    }

    /**
     * @param object $obj
     * @return array
     */
    protected function _group_obj_to_array($obj) {
        return array(
            'id' => (integer)$obj->id,
            'name' => $obj->name,
        );
    }

}
