<?php

defined('MOODLE_INTERNAL') || die;

// wrapper around setting up a Moodle page
$app['twig']->addFunction(new Twig_SimpleFunction('page', function ($type, $id, $url) {
    global $PAGE;
    $PAGE->set_pagelayout('incourse');
    switch ($type) {
        case 'course':
            $PAGE->set_context(context_course::instance($id));
            break;
        case 'module':
            $PAGE->set_context(context_module::instance($id));
            break;
    }
    $PAGE->set_url($url);
}));

// wrapper around adding an item to the Moodle nav bar
$app['twig']->addFunction(new Twig_SimpleFunction('navbaradd', function ($str) {
    global $PAGE;
    $PAGE->navbar->add($str);
}));

// wrapper around initializing an admin page
$app['twig']->addFunction(new Twig_SimpleFunction('adminpage', function ($section) {
    global $CFG;
    require_once($CFG->libdir . '/adminlib.php');
    admin_externalpage_setup($section);
}));

// wrapper around Moodle's get_string() function
$app['twig']->addFunction(new Twig_SimpleFunction('trans', function ($identifier, $component = '', $a = null) {
    return get_string($identifier, $component, $a);
}));

// wrapper around printing a Moodle header
$app['twig']->addFunction(new Twig_SimpleFunction('header', function () {
    global $OUTPUT;
    return $OUTPUT->header();
}));

// wrapper around printing a Moodle footer
$app['twig']->addFunction(new Twig_SimpleFunction('footer', function (Twig_Markup $footer_script = null) {
    /** @var core_renderer $OUTPUT */
    global $OUTPUT;

    // no scripts, or (for some weird reason) the final closing body tag can't be found
    $footer = $OUTPUT->footer();
    if (empty($footer_script)) {
        return $footer;
    }
    $index = strrpos($footer, '</body>');
    if ($index === false) {
        return $footer;
    }

    // if debugging, load unminified scripts
    if (debugging()) {
        $footer_script = str_replace('.min.js', '.js', $footer_script);
    }

    // insert footer script immediately before the final closing body tag
    return substr($footer, 0, $index - 1) . $footer_script . substr($footer, $index);
}));

// wrapper around displaying the user's session key
$app['twig']->addFunction(new Twig_SimpleFunction('sesskey', function () {
    global $USER;
    sesskey();
    return $USER->sesskey;
}));

// wrapper around displaying a moodle form
$app['twig']->addFunction(new Twig_SimpleFunction('form', function (moodleform $form) {
    ob_start();
    $form->display();
    return ob_get_clean();
}));

// wrapper around displaying a moodle table
$app['twig']->addFunction(new Twig_SimpleFunction('table', function (html_table $table) {
    return html_writer::table($table);
}));

// wrapper around Moodle's isloggedin() function
$app['twig']->addFunction(new Twig_SimpleFunction('isloggedin', function () {
    return isloggedin();
}));

// wrapper around requiring css
$app['twig']->addFunction(new Twig_SimpleFunction('css', function ($path) {
    global $PAGE;
    $PAGE->requires->css($path);
}));

// wrapper around var_dump
$app['twig']->addFunction(new Twig_SimpleFunction('var_dump', function ($expr) {
    var_dump($expr);
}));

// wrapper around outputting a pix icon
$app['twig']->addFunction(new Twig_SimpleFunction('pix_icon', function ($pix, $alt = '', $component = 'moodle', array $attributes = null) {
    /** @var core_renderer */
    global $OUTPUT;
    return $OUTPUT->pix_icon($pix, $alt, $component, $attributes);
}));
