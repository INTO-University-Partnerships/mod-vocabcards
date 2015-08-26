<?php

require_once __DIR__ . '/../../config.php';

$id = required_param('id', PARAM_INT);

redirect($CFG->wwwroot . '/vocabcards/instances/' . $id);
