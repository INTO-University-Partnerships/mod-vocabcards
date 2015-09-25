<?php

use Symfony\Component\HttpFoundation\Request;

// bootstrap Moodle
require_once __DIR__ . '/../../config.php';
global $CFG, $FULLME;

// fix $FULLME
$FULLME = str_replace($CFG->wwwroot, $CFG->wwwroot . SLUG, $FULLME);

// create Silex app
require_once __DIR__ . '/../../vendor/autoload.php';
$app = new Silex\Application();
$app['debug'] = debugging('', DEBUG_MINIMAL);

// enable Twig service provider
$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__ . '/templates',
    'twig.options' => [
        'cache' => empty($CFG->disable_twig_cache) ? "{$CFG->dataroot}/twig_cache" : false,
        'auto_reload' => debugging('', DEBUG_MINIMAL),
    ],
]);

// enable URL generator service provider
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// set Twig constants
$app['twig']->addGlobal('plugin', 'mod_vocabcards');
$app['twig']->addGlobal('wwwroot', $CFG->wwwroot);
$app['twig']->addGlobal('slug', SLUG);
$app['twig']->addGlobal('STATIC_URL', '/mod/vocabcards/static/');
$app['twig']->addGlobal('bower_url', isset($CFG->bower_url) ? $CFG->bower_url : $CFG->wwwroot . '/mod/vocabcards/static/js/components/');

// require Twig library functions
require __DIR__ . '/twiglib.php';

// module settings
$app['plugin'] = 'mod_vocabcards';
$app['module_table'] = 'vocabcards';

// require the services
foreach ([
    'get_user_groups_in_course',
    'guzzler',
    'has_capability',
    'heading_and_title',
    'now',
    'require_capability',
    'require_course_login',
    'trigger_event',
] as $service) {
    require __DIR__ . '/services/' . $service . '.php';
}

// define middleware
$app['middleware'] = [
    'ajax_request' => function (Request $request) use ($app) {
        if (!$app['debug'] && !$request->isXmlHttpRequest()) {
            throw new file_serving_exception(get_string('exception:ajax_only', $app['plugin']));
        }
    },
    'ajax_sesskey' => function (Request $request) use ($app) {
        if (!confirm_sesskey($request->get('sesskey'))) {
            return $app->json(['errorMessage' => get_string('accessdenied', 'admin')], 403);
        }
    }
];

// mount the controllers
foreach ([
    'assignment' => 'assignment',
    'instances' => 'instances',
    'partials' => 'partials',
    'repository' => 'library',
    'syllabus' => 'syllabus',
    'v1_api' => 'api/v1',
    'view' => '',
] as $controller => $mount_point) {
    $app->mount('/' . $mount_point, require __DIR__ . '/controllers/' . $controller . '.php');
}

// return the app
return $app;
