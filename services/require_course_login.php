<?php

defined('MOODLE_INTERNAL') || die();

$app['require_course_login'] = $app->protect(function ($course, $cm = null, $setwantsurltome = true) {
    require_course_login($course, true, $cm, $setwantsurltome);
});
