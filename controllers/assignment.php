<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../lib.php';

$controller = $app['controllers_factory'];

// manage assigning words to users (thereby creating cards)
$controller->get('/{courseid}', function ($courseid) use ($app) {
    global $DB, $USER;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        throw new moodle_exception('invalidcourseid');
    }

    // require course login
    $app['require_course_login']($course);

    // capability check
    $app['require_capability']('mod/vocabcards:assignment', context_course::instance($course->id));

    // prepare course sections
    $sections = vocabcards_prepare_course_sections($course);

    // get groups
    require_once __DIR__ . '/../models/vocabcards_student_model.php';
    $model = new vocabcards_student_model();
    if ($app['has_capability']('moodle/site:accessallgroups', context_course::instance($course->id))) {
        $groups = $model->get_groups_in_course($courseid);
    } else {
        $groups = $model->get_user_groups_in_course($courseid, $USER->id);
    }

    // serve AngularJS app
    return $app['twig']->render('assignment.twig', array(
        'app' => 'assignment',
        'course' => $course,
        'sections' => $sections,
        'groups' => $groups,
    ));
})
->bind('assignment')
->assert('courseid', '\d+');

// return the controller
return $controller;
