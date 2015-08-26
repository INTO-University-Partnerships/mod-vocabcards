<?php

defined('MOODLE_INTERNAL') || die();

$app['get_user_groups_in_course'] = $app->protect(function ($courseid, $userid) use ($app) {
    require_once __DIR__ . '/../models/vocabcards_student_model.php';
    $model = new vocabcards_student_model();
    $groups = null;
    if (!$app['has_capability']('moodle/site:accessallgroups', context_course::instance($courseid))) {
        $groups = $model->get_user_groups_in_course($courseid, $userid);
        // note that empty(array(0)) === false
        $groups = empty($groups) ? array(0) : array_map(function ($group) {
            return $group['id'];
        }, $groups);
    }
    return $groups;
});
