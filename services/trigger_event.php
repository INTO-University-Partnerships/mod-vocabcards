<?php

defined('MOODLE_INTERNAL') || die();

$app['trigger_event'] = $app->protect(function ($eventName, $params) use ($app) {
    // check params
    foreach (array('context', 'objectid', 'relateduserid') as $p) {
        if (empty($params[$p])) {
            throw new coding_exception("missing parameter '{$p}'");
        }
    }

    // check if event exists
    $eventClass = "\\mod_vocabcards\\event\\$eventName";
    if (!class_exists($eventClass)) {
        $classFile = __DIR__ . '/../classes/event/' . $eventName . '.php';
        if (!file_exists($classFile)) {
            throw new coding_exception("code file defining event '{$eventName}' does not exist");
        }
        require_once $classFile;
        if (!class_exists($eventClass)) {
            throw new coding_exception("code file defining event '{$eventName}' is missing event class by the same name");
        }
    }

    // create event, set guzzler, trigger it and return it
    $event = $eventClass::create($params);
    $event->set_guzzler($app['guzzler']);
    $event->trigger();
    return $event;
});
