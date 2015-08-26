<?php

$app['guzzler'] = $app->share(function ($app) {
    $client = new \GuzzleHttp\Client();
    return $client;
});
