<?php

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../lib.php';

$container = new Pimple(array(
    'window_after_hour' => -1,
    'window_before_hour' => 25,
    'assign_notify_day_of_week' => date('N'),
));

vocabcards_cron($container);
