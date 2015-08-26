<?php

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// list instances of the activity in the given course
$controller->get('/{courseid}', function ($courseid) use ($app) {
    global $CFG, $DB;

    // get the course
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        throw new moodle_exception('invalidcourseid');
    }

    // require course login
    $app['require_course_login']($course);

    // log it
    $event = \mod_vocabcards\event\course_module_instance_list_viewed::create(array(
        'context' => context_course::instance($course->id),
    ));
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    // get instances
    if (!$instances = get_all_instances_in_course('vocabcards', $course)) {
        redirect($CFG->wwwroot . '/course/view.php?id=' . $course->id, get_string('thereareno', 'moodle', get_string('modulenameplural', $app['plugin'])));
    }

    // populate the instance data
    $data = array();
    if (!empty($instances)) {
        foreach ($instances as $instance) {
            $link = $CFG->wwwroot . SLUG . $app['url_generator']->generate('byinstanceid', array(
                'id' => $instance->id,
            ));
            $data[] = (object)array(
                'link' => '<a href="' . $link . '">' . $instance->name . '</a>',
                'timecreated' => userdate($instance->timecreated),
                'timemodified' => userdate($instance->timemodified),
            );
        }
    }

    // create the table
    $tbl = new html_table();
    $tbl->head = array(
        get_string('name'),
        get_string('created', $app['plugin']),
        get_string('modified', $app['plugin']),
    );
    $tbl->data = $data;

    // render
    return $app['twig']->render('instances.twig', array(
        'course' => $course,
        'table' => $tbl,
    ));
})
->bind('instances')
->assert('courseid', '\d+');

// return the controller
return $controller;
