<?php

defined('MOODLE_INTERNAL') || die();

$app['heading_and_title'] = $app->protect(function ($heading, $title) {
    global $PAGE;

    // set title that will be rendered in the page heading
    $PAGE->set_heading($heading);

    // set title that will be rendered in the browser title tag
    $PAGE->set_title($title);
});
