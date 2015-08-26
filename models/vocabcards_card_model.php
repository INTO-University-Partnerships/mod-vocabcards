<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/vocabcard_status.php';

class vocabcards_card_model {

    /**
     * @var array
     */
    protected $_sql_snippets;

    /**
     * c'tor
     */
    public function __construct() {
        $base = <<<SQL
            FROM {vocabcards_card} vc
            INNER JOIN {vocabcards_syllabus} vs ON vc.wordid = vs.id
            INNER JOIN {course_modules} cm ON cm.course = vs.courseid AND cm.instance = :instanceid AND cm.section = vs.sectionid
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = 'vocabcards'
            INNER JOIN {user} u ON vc.ownerid = u.id AND u.deleted = 0
SQL;
        $get_student_cards_in_activity = <<<SQL
            $base
            WHERE vc.ownerid = :ownerid
SQL;
        $get_cards_in_review_in_activity = <<<SQL
            $base
            WHERE vc.status = :status
SQL;
        $get_cards_in_repository_in_course = <<<SQL
            FROM {vocabcards_card} vc
            INNER JOIN {vocabcards_syllabus} vs ON vc.wordid = vs.id AND vs.courseid = ?
            INNER JOIN {user} u ON vc.ownerid = u.id AND u.deleted = 0
SQL;
        $this->_sql_snippets = array(
            'get_student_cards_in_activity' => $get_student_cards_in_activity,
            'get_cards_in_review_in_activity' => $get_cards_in_review_in_activity,
            'get_cards_in_repository_in_course' => $get_cards_in_repository_in_course,
        );
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
            SELECT vc.*, vs.word, u.firstname, u.lastname
            FROM {vocabcards_card} vc
            INNER JOIN {vocabcards_syllabus} vs ON vc.wordid = vs.id
            INNER JOIN {user} u ON vc.ownerid = u.id AND u.deleted = 0
            WHERE vc.id = :id
            $group_restrict_where
SQL;
        $result = $DB->get_record_sql($sql, array(
            'id' => $id,
        ), MUST_EXIST);
        return $this->_obj_to_long_array($result);
    }

    /**
     * gets a course module id (cmid) that 'contains' the given card
     * there may be zero, one or many of these!
     * @global moodle_database $DB
     * @param integer $cardid
     * @return integer
     */
    public function get_cmid_from_cardid($cardid) {
        global $DB;
        $sql = <<<SQL
            SELECT cm.id
            FROM {course_modules} cm
            INNER JOIN {modules} m ON m.name = 'vocabcards' AND m.id = cm.module
            INNER JOIN {vocabcards_syllabus} vs ON vs.courseid = cm.course AND vs.sectionid = cm.section
            INNER JOIN {vocabcards_card} vc ON vc.wordid = vs.id AND vc.id = :cardid
            ORDER BY cm.id LIMIT 1
SQL;
        $retval = (integer)$DB->get_field_sql($sql, array(
            'cardid' => $cardid,
        ));
        return $retval;
    }

    /**
     * gets all the cards in the given group owned by the given student
     * @global moodle_database $DB
     * @param integer $groupid
     * @param integer $studentid
     * @return array
     */
    public function get_student_cards_in_group($groupid, $studentid) {
        global $DB;
        $group_filter = empty($groupid) ? 'AND groupid IS NULL' : 'AND groupid = :groupid';
        $sql = <<<SQL
            SELECT vc.*, vs.word, u.firstname, u.lastname
            FROM {vocabcards_card} vc
            INNER JOIN {vocabcards_syllabus} vs ON vc.wordid = vs.id
            INNER JOIN {user} u ON vc.ownerid = u.id AND u.deleted = 0
            WHERE vc.ownerid = :ownerid
            $group_filter
            ORDER BY vs.word
SQL;
        $params = array(
            'ownerid' => $studentid,
        );
        if (!empty($groupid)) {
            $params['groupid'] = $groupid;
        }
        $results = $DB->get_records_sql($sql, $params);
        $retval = array();
        foreach ($results as $result) {
            $retval[] = $this->_obj_to_short_array($result);
        }
        return $retval;
    }

    /**
     * @global moodle_database $DB
     * @param integer $instanceid
     * @param integer $studentid
     * @return integer
     */
    public function get_total_student_cards_in_activity($instanceid, $studentid) {
        global $DB;
        $get_student_cards_in_activity = $this->_sql_snippets['get_student_cards_in_activity'];
        $sql = <<<SQL
            SELECT COUNT(vc.id)
            $get_student_cards_in_activity
SQL;
        $params = array(
            'instanceid' => $instanceid,
            'ownerid' => $studentid,
        );
        return (integer)$DB->count_records_sql($sql, $params);
    }

    /**
     * gets the cards owned by the given student that should appear in the given activity
     * the cards that should appear in the given activity are those with words in the same section as the activity
     * @global moodle_database $DB
     * @param integer $instanceid
     * @param integer $studentid
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    public function get_student_cards_in_activity($instanceid, $studentid, $limitfrom = 0, $limitnum = 0) {
        global $DB;
        $get_student_cards_in_activity = $this->_sql_snippets['get_student_cards_in_activity'];
        $sql = <<<SQL
            SELECT vc.*, vs.word, u.firstname, u.lastname
            $get_student_cards_in_activity
            ORDER BY vs.word
SQL;
        $params = array(
            'instanceid' => $instanceid,
            'ownerid' => $studentid,
        );
        $results = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        $retval = array();
        foreach ($results as $result) {
            $retval[] = $this->_obj_to_short_array($result);
        }
        return $retval;
    }

    /**
     * @global moodle_database $DB
     * @param integer $instanceid
     * @param array $groups
     * @return int
     */
    public function get_total_cards_in_review_in_activity($instanceid, array $groups = null) {
        global $DB;
        $get_cards_in_review_in_activity = $this->_sql_snippets['get_cards_in_review_in_activity'];
        $group_restrict_where = $this->_group_restrict_where($groups);
        $sql = <<<SQL
            SELECT COUNT(vc.id)
            $get_cards_in_review_in_activity
            $group_restrict_where
SQL;
        $params = array(
            'instanceid' => $instanceid,
            'status' => vocabcard_status::IN_REVIEW,
        );
        return (integer)$DB->count_records_sql($sql, $params);
    }

    /**
     * gets all the cards with status IN_REVIEW whose word is assigned to a section that the activity is in
     * if the given collection of groups is non-empty, only cards that are visible to these groups are returned
     * @global moodle_database $DB
     * @param integer $instanceid
     * @param array $groups
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    public function get_cards_in_review_in_activity($instanceid, array $groups = null, $limitfrom = 0, $limitnum = 0) {
        global $DB;
        $get_cards_in_review_in_activity = $this->_sql_snippets['get_cards_in_review_in_activity'];
        $group_restrict_where = $this->_group_restrict_where($groups);
        $sql = <<<SQL
            SELECT vc.*, vs.word, u.firstname, u.lastname
            $get_cards_in_review_in_activity
            $group_restrict_where
            ORDER BY vs.word
SQL;
        $params = array(
            'instanceid' => $instanceid,
            'status' => vocabcard_status::IN_REVIEW,
        );
        $results = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        $retval = array();
        foreach ($results as $result) {
            $retval[] = $this->_obj_to_short_array($result);
        }
        return $retval;
    }

    /**
     * @global moodle_database $DB
     * @param integer $courseid
     * @param array $groups
     * @param integer $groupid
     * @param integer $userid
     * @param string $q
     * @param integer $status
     * @return int
     */
    public function get_total_cards_in_repository_in_course($courseid, array $groups = null, $groupid = 0, $userid = 0, $q = '', $status = vocabcard_status::IN_REPOSITORY) {
        global $DB;
        $get_cards_in_repository_in_course = $this->_sql_snippets['get_cards_in_repository_in_course'];
        $group_restrict_where = $this->_group_restrict_where($groups);
        $group_filter = empty($groupid) ? '' : 'AND vc.groupid = ?';
        $user_filter = empty($userid) ? '' : 'AND vc.ownerid = ?';
        $q_filter = $this->_get_q_query_string($q);
        $status_filter = $status == -1 ? '' : 'AND vc.status = ?';
        $sql = <<<SQL
            SELECT COUNT(vc.id)
            $get_cards_in_repository_in_course
            WHERE 1=1 $group_restrict_where $group_filter $user_filter $q_filter[0] $status_filter
SQL;
        $params = array($courseid);
        if (!empty($group_filter)) {
            $params[] = $groupid;
        }
        if (!empty($user_filter)) {
            $params[] = $userid;
        }
        if (!empty($q_filter)) {
            $params[] = $q_filter[1];
            $params[] = $q_filter[1];
        }
        if (!empty($status_filter)) {
            $params[] = $status;
        }
        return (integer)$DB->count_records_sql($sql, $params);
    }

    /**
     * gets cards with status IN_REPOSITORY
     * if the given collection of groups is non-empty, only cards that are visible to these groups are returned
     * @param integer $courseid
     * @param array $groups
     * @param integer $groupid
     * @param integer $userid
     * @param string $q
     * @param integer $status
     * @param string $sort
     * @param int $limitfrom
     * @param int $limitnum
     * @return array
     */
    public function get_cards_in_repository_in_course($courseid, array $groups = null, $groupid = 0, $userid = 0, $q = '', $status = vocabcard_status::IN_REPOSITORY, $sort = null, $limitfrom = 0, $limitnum = 0) {
        global $DB;
        $get_cards_in_repository_in_course = $this->_sql_snippets['get_cards_in_repository_in_course'];
        $group_restrict_where = $this->_group_restrict_where($groups);
        $group_filter = empty($groupid) ? '' : 'AND vc.groupid = ?';
        $user_filter = empty($userid) ? '' : 'AND vc.ownerid = ?';
        $q_filter = $this->_get_q_query_string($q);
        $status_filter = $status == -1 ? '' : 'AND vc.status = ?';
        $sortings = array(
            'word' => 'vs.word, u.firstname, u.lastname',
            'owner' => 'u.firstname, u.lastname, vs.word',
            'added' => 'vc.timeaddedtorepo DESC',
            'status' => 'vc.status, vs.word',
        );
        $sorting = $sortings[($sort == null || !array_key_exists($sort, $sortings)) ? 'word' : $sort];
        $sql = <<<SQL
            SELECT vc.*, vs.word, u.firstname, u.lastname
            $get_cards_in_repository_in_course
            WHERE 1=1 $group_restrict_where $group_filter $user_filter $q_filter[0] $status_filter
            ORDER BY $sorting
SQL;
        $params = array($courseid);
        if (!empty($group_filter)) {
            $params[] = $groupid;
        }
        if (!empty($user_filter)) {
            $params[] = $userid;
        }
        if (!empty($q_filter)) {
            $params[] = $q_filter[1];
            $params[] = $q_filter[1];
        }
        if (!empty($status_filter)) {
            $params[] = $status;
        }
        $results = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        $retval = array();
        foreach ($results as $result) {
            $retval[] = $this->_obj_to_short_array($result);
        }
        return $retval;
    }

    /**
     * gets cards with status IN_REPOSITORY with all information for repository export
     * if the given collection of groups is non-empty, only cards that are visible to these groups are returned
     * @param integer $courseid
     * @param array $groups
     * @param integer $status
     * @return array
     */
    public function get_cards_in_repository_in_course_long($courseid, array $groups = null, $status = vocabcard_status::IN_REPOSITORY) {
        global $DB;
        $get_cards_in_repository_in_course = $this->_sql_snippets['get_cards_in_repository_in_course'];
        $group_restrict_where = $this->_group_restrict_where($groups);
        $status_filter = 'AND vc.status = ?';
        $sql = <<<SQL
            SELECT vc.*, vs.word, u.firstname, u.lastname
            $get_cards_in_repository_in_course
            WHERE 1=1 $group_restrict_where $status_filter
            ORDER BY vs.word, u.firstname, u.lastname
SQL;
        $results = $DB->get_records_sql($sql, array($courseid, $status));
        $retval = array();
        foreach ($results as $result) {
            $retval[] = $this->_obj_to_long_array($result);
        }
        return $retval;
    }

    /**
     * creates a vocabulary card from a given word and stamps it as being owned by the given owner
     * @global moodle_database $DB
     * @param integer $wordid
     * @param integer $ownerid
     * @param integer $groupid
     * @param integer $assignerid
     * @param integer $now
     * @return array
     */
    public function create_from_word($wordid, $ownerid, $groupid, $assignerid, $now) {
        global $DB;
        $id = $DB->get_field('vocabcards_card', 'id', array(
            'wordid' => $wordid,
            'ownerid' => $ownerid,
            'groupid' => empty($groupid) ? null : $groupid,
        ));
        if (!empty($id)) {
            return $this->get($id);
        }
        $data = array(
            'wordid' => $wordid,
            'ownerid' => $ownerid,
            'groupid' => empty($groupid) ? null : $groupid,
            'assignerid' => $assignerid,
            'status' => vocabcard_status::NOT_STARTED,
            'timecreated' => $now,
            'timemodified' => $now,
        );
        $id = $DB->insert_record('vocabcards_card', (object)$data);
        return $this->get($id);
    }

    /**
     * @global moodle_database $DB
     * @param array $data
     * @param integer $now
     * @return array
     */
    public function save(array $data, $now) {
        global $DB;
        $obj = $DB->get_record('vocabcards_card', array('id' => $data['id']), '*', MUST_EXIST);
        $old_status = $obj->status;
        $properties = array(
            'status',
            'phonemic',
            'definition',
            'noun',
            'verb',
            'adverb',
            'adjective',
            'synonym_',
            'antonym_',
            'prefixes',
            'suffixes',
            'collocations',
            'tags',
            'sentences',
        );
        foreach ($properties as $property) {
            if (array_key_exists($property, $data)) {
                $trimmed = trim(preg_replace('/\s{2,}/', ' ', $data[$property]));
                $obj->{$property} = ($trimmed === '') ? null : $trimmed;
            }
        }
        $obj->timemodified = $now;
        if ($old_status != vocabcard_status::IN_REPOSITORY && $obj->status == vocabcard_status::IN_REPOSITORY) {
            $obj->timeaddedtorepo = $now;
        } else if ($obj->status != vocabcard_status::IN_REPOSITORY) {
            $obj->timeaddedtorepo = null;
        }
        $DB->update_record('vocabcards_card', $obj);
        return $this->get($data['id']);
    }

    /**
     * @global moodle_database $DB
     * @param integer $id
     */
    public function delete($id) {
        global $DB;
        $DB->get_field('vocabcards_card', 'id', array(
            'id' => $id,
        ), MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('vocabcards_feedback', array(
            'cardid' => $id,
        ));
        $DB->delete_records('vocabcards_card', array(
            'id' => $id,
        ));
        $transaction->allow_commit();
    }

    /**
     * @global moodle_database $DB
     * @param integer $id
     * @return integer
     */
    public function get_feedback_modified($id) {
        global $DB;
        $sql = <<<SQL
            SELECT MAX(vf.timemodified)
            FROM {vocabcards_feedback} vf
            WHERE vf.cardid = :cardid
SQL;
        $params = array(
            'cardid' => $id,
        );
        return (integer)$DB->get_field_sql($sql, $params);
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
        $tag_like = $DB->sql_like('vc.tags', '?', false);
        return array(" AND ({$word_like} OR {$tag_like})", "%$q%");
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
    protected function _obj_to_short_array($obj) {
        return array(
            'id' => (integer)$obj->id,
            'word' => $obj->word,
            'wordid' => (integer)$obj->wordid,
            'ownerid' => (integer)$obj->ownerid,
            'ownerfullname' => $obj->firstname . ' ' . $obj->lastname,
            'status' => (integer)$obj->status,
            'tags' => $obj->tags,
            'timeaddedtorepo' => empty($obj->timeaddedtorepo) ? '' : userdate($obj->timeaddedtorepo),
            'timecreated' => userdate($obj->timecreated),
            'timemodified' => userdate($obj->timemodified),
        );
    }

    /**
     * @param object $obj
     * @return array
     */
    protected function _obj_to_long_array($obj) {
        $a = $this->_obj_to_short_array($obj);
        foreach (array(
            'phonemic',
            'definition',
            'noun',
            'verb',
            'adverb',
            'adjective',
            'synonym_',
            'antonym_',
            'prefixes',
            'suffixes',
            'collocations',
            'sentences',
        ) as $field) {
            $a[$field] = empty($obj->{$field}) ? '' : $obj->{$field};
        }
        return $a;
    }

}
