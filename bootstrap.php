<?php

define('SLUG', $_GET['slug']);
if (substr($_SERVER['REQUEST_URI'], 0, strlen(SLUG)) === SLUG) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen(SLUG));
}

require_once __DIR__ . '/app.php';
$app->run();
