<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once __DIR__ . '/../../../backup/util/includes/restore_includes.php';

class mod_vocabcards_restore_controller extends restore_controller {

    /**
     * prevents the PHP time limit from being set by the base class (which, in turn, causes PHPUnits to fail)
     */
    public function execute_plan() {
        parent::execute_plan();
        set_time_limit(0);
    }

}
