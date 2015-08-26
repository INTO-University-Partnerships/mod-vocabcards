<?php

require_once __DIR__ . '/../../config.php';

$cmid = required_param('id', PARAM_INT);

redirect($CFG->wwwroot . '/vocabcards/' . $cmid);
