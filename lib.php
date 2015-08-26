<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/src/mod_vocabcards_cron_manager.php';

/**
 * @global moodle_database $DB
 * @param object $obj
 * @param mod_vocabcards_mod_form $mform
 * @return integer
 */
function vocabcards_add_instance($obj, mod_vocabcards_mod_form $mform = null) {
    global $DB;
    $obj->timecreated = $obj->timemodified = time();
    $obj->header = (isset($obj->header) && array_key_exists('text', $obj->header)) ? $obj->header['text'] : null;
    $obj->footer = (isset($obj->footer) && array_key_exists('text', $obj->footer)) ? $obj->footer['text'] : null;
    $obj->id = $DB->insert_record('vocabcards', $obj);
    return $obj->id;
}

/**
 * @global moodle_database $DB
 * @param object $obj
 * @param mod_vocabcards_mod_form $mform
 * @return boolean
 */
function vocabcards_update_instance($obj, mod_vocabcards_mod_form $mform) {
    global $DB;
    $obj->id = $obj->instance;
    $obj->timemodified = time();
    $obj->header = (isset($obj->header) && array_key_exists('text', $obj->header)) ? $obj->header['text'] : null;
    $obj->footer = (isset($obj->footer) && array_key_exists('text', $obj->footer)) ? $obj->footer['text'] : null;
    $success = $DB->update_record('vocabcards', $obj);
    return $success;
}

/**
 * @global moodle_database $DB
 * @param integer $id
 * @return boolean
 */
function vocabcards_delete_instance($id) {
    global $DB;
    $success = $DB->delete_records('vocabcards', array('id' => $id));
    return $success;
}

/**
* @param string $feature
* @return boolean
*/
function vocabcards_supports($feature) {
    $support = array(
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_ADVANCED_GRADING => false,
        FEATURE_CONTROLS_GRADE_VISIBILITY => false,
        FEATURE_PLAGIARISM => false,
        FEATURE_COMPLETION_HAS_RULES => false,
        FEATURE_NO_VIEW_LINK => false,
        FEATURE_IDNUMBER => false,
        FEATURE_GROUPS => false, // although FEATURE_GROUPS itself isn't supported, the group mode is effectively hardcoded to SEPARATEGROUPS
        FEATURE_GROUPINGS => false,
        FEATURE_MOD_ARCHETYPE => false,
        FEATURE_MOD_INTRO => false,
        FEATURE_MODEDIT_DEFAULT_COMPLETION => false,
        FEATURE_COMMENT => false,
        FEATURE_RATE => false,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_SHOW_DESCRIPTION => false,
    );
    if (!array_key_exists($feature, $support)) {
        return null;
    }
    return $support[$feature];
}

/**
 * ensures the course has all its sections created
 * returns those sections along with their names
 * @global moodle_database $DB
 * @param object $course
 * @return array
 */
function vocabcards_prepare_course_sections($course) {
    global $DB;

    // get number of sections in given course
    $format = course_get_format($course);
    $courseformatoptions = $format->get_format_options();
    $sectionCount = (integer)$courseformatoptions['numsections'];

    // make sure all sections are created
    course_create_sections_if_missing($course, range(0, $sectionCount));

    // get the sections
    $sql = <<<SQL
        SELECT id, section
        FROM {course_sections}
        WHERE course = :courseid
        AND section <= :numsections
        ORDER BY section
SQL;
    $sections = (array)$DB->get_records_sql($sql, array(
        'courseid' => $course->id,
        'numsections' => $sectionCount,
    ));
    foreach ($sections as $key => $section) {
        $sections[$key]->name = $format->get_section_name($section);
    }

    return $sections;
}

/**
 * Returns all other caps used in module
 * @return array
 */
function vocabcards_get_extra_capabilities() {
    return array(
        'moodle/site:accessallgroups',
    );
}

/**
 * @param callable $f
 * @param array $a
 * @return boolean
 */
function _all(callable $f, array $a) {
    foreach ($a as $v) {
        if (!$f($v)) {
            return false;
        }
    }
    return true;
}

/**
 * @param callable $f
 * @param array $a
 * @return boolean
 */
function _any(callable $f, array $a) {
    foreach ($a as $v) {
        if ($f($v)) {
            return true;
        }
    }
    return false;
}

/**
 * @param array $a
 * @return boolean
 */
function _and(array $a) {
    if (empty($a)) {
        return true;
    }
    foreach ($a as $v) {
        if (!$v) {
            return false;
        }
    }
    return true;
}

/**
 * @param array $a
 * @return boolean
 */
function _or(array $a) {
    foreach ($a as $v) {
        if ($v) {
            return true;
        }
    }
    return false;
}

/**
 * @param Pimple $container
 */
function vocabcards_cron(Pimple $container = null) {
    if (empty($container)) {
        $container = new Pimple();
    }
    if (!$container->offsetExists('mod_vocabcards_cron_manager')) {
        $container['mod_vocabcards_cron_manager'] = $container->share(function ($container) {
            return new mod_vocabcards_cron_manager($container);
        });
    }

    // get the cron manager
    $mod_vocabcards_cron_manager = $container['mod_vocabcards_cron_manager'];

    // if the current hour isn't within the given window, then don't do anything
    if (!$mod_vocabcards_cron_manager->is_due()) {
        return;
    }

    // start cron
    $mod_vocabcards_cron_manager->start();
    $mod_vocabcards_cron_manager->send_notification_to_tutors_that_have_cards_to_review();
    $mod_vocabcards_cron_manager->send_notification_to_tutors_that_have_words_to_assign();
    $mod_vocabcards_cron_manager->finish();
}

/**
 * @param settings_navigation $sn
 * @param navigation_node $nn
 */
function vocabcards_extend_settings_navigation(settings_navigation $sn, navigation_node $nn) {
    global $PAGE;

    // get the course context
    $courseid = $PAGE->course->id;
    $course_context = context_course::instance($courseid);

    // if can manage syllabus, add a link
    if (has_capability('mod/vocabcards:syllabus', $course_context)) {
        $url = new moodle_url('/vocabcards/syllabus/' . $courseid);
        $nn->add(get_string('vocabcards:syllabus', 'vocabcards'), $url, navigation_node::TYPE_CUSTOM);
    }

    // if can assign words, add a link
    if (has_capability('mod/vocabcards:assignment', $course_context)) {
        $url = new moodle_url('/vocabcards/assignment/' . $courseid);
        $nn->add(get_string('vocabcards:assignment', 'vocabcards'), $url, navigation_node::TYPE_CUSTOM);
    }

    // if can view repository, add a link
    if (has_capability('mod/vocabcards:repository', $course_context)) {
        $url = new moodle_url('/vocabcards/library/' . $courseid);
        $nn->add(get_string('vocabcards:repository', 'vocabcards'), $url, navigation_node::TYPE_CUSTOM);
    }
}
