<?php

defined('MOODLE_INTERNAL') || die();

$app['has_capability'] = $app->protect(function ($capability, $context, $user = null) {
    return has_capability($capability, $context, $user);
});
