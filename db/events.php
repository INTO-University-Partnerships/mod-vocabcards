<?php

defined('MOODLE_INTERNAL') || die();

$observers = array(

    array(
        'eventname' => '\core\event\course_deleted',
        'callback' => 'mod_vocabcards_observer::course_deleted',
    ),

    array(
        'eventname' => '\mod_vocabcards\event\vocabcard_assigned',
        'callback' => 'mod_vocabcards_observer::vocabcard_assigned'
    ),

    array(
        'eventname' => '\mod_vocabcards\event\feedback_given_by_tutor',
        'callback' => 'mod_vocabcards_observer::feedback_given_by_tutor'
    ),

    array(
        'eventname' => '\mod_vocabcards\event\feedback_given_in_repository',
        'callback' => 'mod_vocabcards_observer::feedback_given_in_repository'
    ),

);
