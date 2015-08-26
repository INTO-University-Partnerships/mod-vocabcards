<?php

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// handle AngularJS deep links to specific cards
$controller->get('/{courseid}/card/{action}/{cardid}', function ($courseid, $action, $cardid) use ($app) {
    global $CFG, $SESSION;
    $SESSION->vocabcards_angularjs_route = '/card/' . $action . '/' . $cardid;
    $url = $CFG->wwwroot . SLUG . $app['url_generator']->generate('repository', array(
        'courseid' => $courseid,
    ));
    return $app->redirect($url);
})
->assert('cmid', '\d+')
->assert('action', 'edit|view|feedback')
->assert('cardid', '\d+');

// card repository for a given course
$controller->get('/{courseid}', function ($courseid) use ($app) {
    global $DB, $USER, $SESSION;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        throw new moodle_exception('invalidcourseid');
    }

    // require course login
    $app['require_course_login']($course);

    // capability check
    $app['require_capability']('mod/vocabcards:repository', context_course::instance($course->id));

    // get groups
    require_once __DIR__ . '/../models/vocabcards_student_model.php';
    $model = new vocabcards_student_model();
    if ($app['has_capability']('moodle/site:accessallgroups', context_course::instance($course->id))) {
        $groups = $model->get_groups_in_course($courseid);
    } else {
        $groups = $model->get_user_groups_in_course($courseid, $USER->id);
    }

    // see whether we need to go to a specific AngularJS route
    $angularjs_route = '';
    if (!empty($SESSION->vocabcards_angularjs_route)) {
        $angularjs_route = $SESSION->vocabcards_angularjs_route;
        unset($SESSION->vocabcards_angularjs_route);
    }

    // serve AngularJS app
    return $app['twig']->render('repository.twig', array(
        'app' => 'repository',
        'course' => $course,
        'groups' => $groups,
        'omniscience' => $app['has_capability']('mod/vocabcards:omniscience', context_course::instance($course->id)),
        'can_feedback' => $app['has_capability']('mod/vocabcards:feedback', context_course::instance($course->id)),
        'userid' => (integer)$USER->id,
        'angularjs_route' => $angularjs_route,
    ));
})
->bind('repository')
->assert('courseid', '\d+');

// pdf export of card repository of the given course
$controller->get('/{courseid}/pdf', function ($courseid) use ($app) {
    global $DB, $USER;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        throw new moodle_exception('invalidcourseid');
    }

    // require course login
    $app['require_course_login']($course);

    // capability check
    $app['require_capability']('mod/vocabcards:repository', context_course::instance($course->id));

    // get groups
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);

    // get cards
    require_once __DIR__ . '/../models/vocabcards_card_model.php';
    $model = new vocabcards_card_model();
    $cards = $model->get_cards_in_repository_in_course_long($courseid, $groups);

    if (empty($cards)) {
        throw new NotFoundHttpException(get_string('nocards', $app['plugin']));
    }

    // generate pdf from cards
    require_once __DIR__ . '/../src/pdf_generator.php';
    $pdf_generator = new pdf_generator();
    foreach ($cards as $card) {
        $pdf_generator->add_page();
        $pdf_generator->write($app['twig']->render('pdf.twig', array(
            'card' => $card
        )));
    }

    return new Response($pdf_generator->render(), 200, array(
        'Content-Type' => 'application/pdf',
    ));
})
->bind('exporttopdf')
->assert('courseid', '\d+');

// return the controller
return $controller;
