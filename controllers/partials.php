<?php

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// serve partials
$controller->get('/{file}', function ($file) use ($app) {
    global $PAGE;
    $path = __DIR__ . '/../templates/partials/';
    if (!file_exists($path . $file)) {
        throw new file_serving_exception(get_string('exception:non_existent_partial', $app['plugin']));
    }
    $PAGE->set_context(context_system::instance());
    return $app['twig']->render('partials/' . $file);
})
->before($app['middleware']['ajax_request'])
->assert('file', '[A-Za-z_/]+\.twig');

// return the controller
return $controller;
