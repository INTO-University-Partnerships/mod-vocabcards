<?php

defined('MOODLE_INTERNAL') || die();

class vocabcard_status {

    /**
     * when a vocab card is first assigned to a student
     * before the vocab card begins to be worked on
     */
    const NOT_STARTED = 0;

    /**
     * when a vocab card is being worked on by a student
     * when a teacher/tutor has given feedback
     */
    const IN_PROGRESS = 1;

    /**
     * when a vocab card is placed in review (for a teacher/tutor to give feedback on)
     */
    const IN_REVIEW = 2;

    /**
     * when a vocab card is placed in the repository (when a teacher/tutor has no further feedback)
     */
    const IN_REPOSITORY = 3;

    /**
     * private c'tor
     */
    private function __construct() {
        // empty
    }

}
