<?php

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../lib.php';

$controller = $app['controllers_factory'];

// manage the vocabulary syllabus
$controller->get('/{courseid}', function ($courseid) use ($app) {
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        throw new moodle_exception('invalidcourseid');
    }

    // require course login
    $app['require_course_login']($course);

    // capability check
    $app['require_capability']('mod/vocabcards:syllabus', context_course::instance($course->id));

    // prepare course sections
    $sections = vocabcards_prepare_course_sections($course);

    // serve AngularJS app
    return $app['twig']->render('syllabus.twig', array(
        'app' => 'syllabus',
        'course' => $course,
        'sections' => $sections,
    ));
})
->bind('syllabus')
->assert('courseid', '\d+');

// return the controller
return $controller;
