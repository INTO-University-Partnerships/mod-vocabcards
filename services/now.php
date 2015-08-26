<?php

defined('MOODLE_INTERNAL') || die();

$app['now'] = $app->protect(function () {
    return time();
});
