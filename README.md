# Mod vocabcards

A Moodle activity plugin that allows tutors to assign a syllabus of words and mark vocabulary.

Commands are relative to the directory in which Moodle is installed.

## Dependencies

Moodle 2.9

The following packages must be added to `composer.json`:

    "require": {
        "silex/silex": "1.3.*",
        "twig/twig": "1.18.*",
        "symfony/browser-kit": "2.5.*",
        "symfony/config": "2.5.*",
        "symfony/css-selector": "2.5.*",
        "symfony/debug": "2.5.*",
        "symfony/dom-crawler": "2.5.*",
        "symfony/event-dispatcher": "2.5.*",
        "symfony/filesystem": "2.5.*",
        "symfony/form": "2.5.*",
        "symfony/http-foundation": "2.5.*",
        "symfony/http-kernel": "2.5.*",
        "symfony/intl": "2.5.*",
        "symfony/locale": "2.5.*",
        "symfony/options-resolver": "2.5.*",
        "symfony/property-access": "2.5.*",
        "symfony/routing": "2.5.*",
        "symfony/security-core": "2.5.*",
        "symfony/security-csrf": "2.5.*",
        "symfony/translation": "2.5.*",
        "symfony/twig-bridge": "2.5.*",
        "symfony/validator": "2.5.*",
        "symfony/yaml": "2.5.*",
        "guzzlehttp/guzzle": "4.2.*"
    },
    "require-dev": {
        "mockery/mockery": "0.9.4"
    }

# Installation

Install [Composer](https://getcomposer.org/download/) if it isn't already.

    ./composer.phar self-update
    ./composer.phar update
    cd mod
    git clone https://github.com/INTO-University-Partnerships/mod-vocabcards vocabcards
    cd ..
    php admin/cli/upgrade.php

## Apache rewrite rule

Add the following Apache rewrite rule:

    RewriteRule ^(/vocabcards) /mod/vocabcards/bootstrap.php?slug=$1 [QSA,L]

## Bower components

    cd mod/vocabcards
    bower install

## Tests

### PHPUnit

Comment-out line `173` of `lib/phpunit/bootstrap.php`, then:

    php admin/tool/phpunit/cli/util.php --buildcomponentconfigs
    vendor/bin/phpunit -c mod/vocabcards

## Gulp

There are four [Gulp](http://gulpjs.com/) tasks:

* `gulp clean` deletes the build directory `static/js/build`
* `gulp build --app X` compiles minified JavaScript app *X* to the build directory `static/js/build`
* `gulp watch --app X` compiles unminified JavaScript app *X* to the build directory `static/js/build` (and recompiles when necessary)
* `gulp lint` lints the JavaScript app with [ESLint](http://eslint.org/)

The four JavaScripts apps are `assignment`, `cards`, `repository` and `syllabus`.
