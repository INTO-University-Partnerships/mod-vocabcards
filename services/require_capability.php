<?php

defined('MOODLE_INTERNAL') || die();

$app['require_capability'] = $app->protect(function ($capability, $context) {
    require_capability($capability, $context);
});
