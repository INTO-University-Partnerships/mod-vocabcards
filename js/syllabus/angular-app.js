'use strict';

var app = angular.module('syllabusApp', [
    'general.controllers',
    'general.services',
    'general.directives',
    'general.filters',
    'syllabusApp.controllers',
    'syllabusApp.directives',
    'ngRoute',
    'ngSanitize'
]);

app.constant('CONFIG', window.CONFIG);
delete window.CONFIG;

app.config([
    '$routeProvider', '$httpProvider', 'CONFIG',
    function ($routeProvider, $httpProvider, config) {
        $httpProvider.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        $routeProvider.
            when('/', {
                templateUrl: config.partialsUrl + 'route/syllabus.twig',
                controller: 'syllabusCtrl'
            }).
            otherwise({
                redirectTo: '/'
            });
    }
]);
