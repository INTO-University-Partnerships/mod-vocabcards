<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../src/vocabcard_status.php';

class vocabcards_notifier_model {

    /**
     * c'tor
     */
    public function __construct() {
        // empty
    }

    /**
     * get users with the tutor role for which cards exist that are in review
     * one record per tutor, per vocabulary cards activity
     * @global moodle_database $DB
     * @return array
     */
    public function get_tutors_that_have_cards_to_review() {
        global $DB;
        $uniqueid = $DB->sql_concat('u.id', "'_'", 'cm.id');
        $sql = <<<SQL
            SELECT $uniqueid AS uniqueid, u.id AS uid, cm.id AS cmid, v.name AS activity_name, COUNT(vc.id) AS card_count
            FROM {course_modules} cm
            INNER JOIN {course} c
                ON c.id = cm.course
            INNER JOIN {modules} m
                ON m.id = cm.module
                AND m.name = :module_name
            INNER JOIN {vocabcards_syllabus} vs
                ON vs.courseid = c.id
                AND vs.sectionid = cm.section
            INNER JOIN {vocabcards_card} vc
                ON vc.wordid = vs.id
                AND vc.status = :in_review
            INNER JOIN {vocabcards} v
                ON v.id = cm.instance
            INNER JOIN {context} ctx
                ON ctx.instanceid = c.id
                AND ctx.contextlevel = :context_course
            INNER JOIN {role_assignments} ra
                ON ra.contextid = ctx.id
            INNER JOIN {role} r
                ON r.id = ra.roleid
                AND r.shortname = :role_shortname
            INNER JOIN {user} u
                ON u.id = ra.userid
                AND u.deleted = 0
            INNER JOIN {user} o
                ON o.id = vc.ownerid
                AND o.deleted = 0
            WHERE (vc.groupid IN (
                SELECT g.id
                FROM {groups} g
                INNER JOIN {groups_members} gm
                    ON gm.groupid = g.id
                WHERE g.courseid = c.id
                    AND gm.userid = u.id
            ) OR vc.groupid IS NULL)
            GROUP BY uniqueid, uid, cmid, activity_name
            ORDER BY uid, cmid
SQL;
        $params = array(
            'module_name' => 'vocabcards',
            'in_review' => vocabcard_status::IN_REVIEW,
            'context_course' => CONTEXT_COURSE,
            'role_shortname' => 'tutor',
        );
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * get users with the tutor role for which words exist that don't have corresponding vocabulary cards
     * one record per tutor, per course section
     * @global moodle_database $DB
     * @param integer $datefrom
     * @param integer $dateto
     * @return array
     */
    public function get_tutors_that_have_words_to_assign($datefrom, $dateto) {
        global $DB;
        $uniqueid = $DB->sql_concat('u.id', "'_'", 'cs.id');
        $sql = <<<SQL
            SELECT $uniqueid AS uniqueid, u.id AS uid, cs.id AS csid, c.id AS cid, c.fullname AS course_fullname, COUNT(DISTINCT vs.id) AS word_count
            FROM {course_modules} cm
            INNER JOIN {course} c
                ON c.id = cm.course
            INNER JOIN {modules} m
                ON m.id = cm.module
                AND m.name = :module_name
            INNER JOIN {vocabcards_syllabus} vs
                ON vs.courseid = c.id
                AND vs.sectionid = cm.section
            INNER JOIN {course_sections} cs
                ON cs.id = vs.sectionid
                AND cs.course = c.id
            INNER JOIN {vocabcards} v
                ON v.id = cm.instance
            INNER JOIN {context} ctx
                ON ctx.instanceid = c.id
                AND ctx.contextlevel = :context_course
            INNER JOIN {role_assignments} ra
                ON ra.contextid = ctx.id
            INNER JOIN {role} r
                ON r.id = ra.roleid
                AND r.shortname = :role_shortname
            INNER JOIN {user} u
                ON u.id = ra.userid
                AND u.deleted = 0
            WHERE NOT EXISTS (
                SELECT vc.id
                FROM {vocabcards_card} vc
                WHERE vc.wordid = vs.id
            ) AND v.startdate BETWEEN :date_from AND :date_to
            GROUP BY uniqueid, uid, csid, cid, course_fullname
            ORDER BY uid, csid
SQL;
        $params = array (
            'module_name' => 'vocabcards',
            'context_course' => CONTEXT_COURSE,
            'role_shortname' => 'tutor',
            'date_from' => $datefrom,
            'date_to' => $dateto,
        );
        return $DB->get_records_sql($sql, $params);
    }

}
