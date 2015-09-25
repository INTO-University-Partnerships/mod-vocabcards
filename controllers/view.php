<?php

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// handle AngularJS deep links to specific cards
$controller->get('/{cmid}/card/{action}/{cardid}', function ($cmid, $action, $cardid) use ($app) {
    global $CFG, $SESSION;
    $SESSION->vocabcards_angularjs_route = '/card/' . $action . '/' . $cardid;
    $url = $CFG->wwwroot . SLUG . $app['url_generator']->generate('view', array(
        'cmid' => $cmid,
    ));
    return $app->redirect($url);
})
->assert('cmid', '\d+')
->assert('action', 'edit|view|feedback')
->assert('cardid', '\d+');

// handle AngularJS deep link to feedback
$controller->get('/{cmid}/card/feedback', function ($cmid) use ($app) {
    global $CFG, $SESSION;
    $SESSION->vocabcards_angularjs_route = '/card/feedback';
    $url = $CFG->wwwroot . SLUG . $app['url_generator']->generate('view', array(
        'cmid' => $cmid,
    ));
    return $app->redirect($url);
})
->assert('cmid', '\d+');

// view the given activity
$controller->get('/{cmid}', function ($cmid) use ($app) {
    global $DB, $USER, $PAGE, $SESSION;

    // get course module id
    $cm = $DB->get_record('course_modules', array(
        'id' => $cmid,
    ), '*', MUST_EXIST);

    // get instance
    $instance = $DB->get_record($app['module_table'], array(
        'id' => $cm->instance,
    ), '*', MUST_EXIST);

    // get course
    $course = $DB->get_record('course', array(
        'id' => $cm->course,
    ), '*', MUST_EXIST);

    // require course login
    $app['require_course_login']($course, $cm);

    // capability check
    $context = context_module::instance($cmid);
    $app['require_capability']('mod/vocabcards:view', $context);

    // log it
    $event = \mod_vocabcards\event\course_module_viewed::create(array(
        'objectid' => $cm->instance,
        'context' => $PAGE->context,
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot($app['module_table'], $instance);
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    // mark viewed
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // see whether we need to go to a specific AngularJS route
    $angularjs_route = '';
    if (!empty($SESSION->vocabcards_angularjs_route)) {
        $angularjs_route = $SESSION->vocabcards_angularjs_route;
        unset($SESSION->vocabcards_angularjs_route);
    }

    // set heading and title
    $app['heading_and_title']($course->fullname, $instance->name);

    // serve AngularJS app
    return $app['twig']->render('view.twig', array(
        'app' => 'cards',
        'cm' => $cm,
        'instance' => $instance,
        'course' => $course,
        'can_feedback' => $app['has_capability']('mod/vocabcards:feedback', $context),
        'userid' => (integer)$USER->id,
        'angularjs_route' => $angularjs_route,
    ));
})
->bind('view')
->assert('cmid', '\d+');

// view the given activity
$controller->get('/instance/{id}', function ($id) use ($app) {
    global $CFG, $DB;

    // get module id from modules table
    $moduleid = (integer)$DB->get_field('modules', 'id', array(
        'name' => $app['module_table'],
    ), MUST_EXIST);

    // get course module id
    $cmid = (integer)$DB->get_field('course_modules', 'id', array(
        'module' => $moduleid,
        'instance' => $id,
    ), MUST_EXIST);

    // redirect
    return $app->redirect($CFG->wwwroot . SLUG . $app['url_generator']->generate('view', array(
        'cmid' => $cmid,
    )));
})
->bind('byinstanceid')
->assert('id', '\d+');

// return the controller
return $controller;
