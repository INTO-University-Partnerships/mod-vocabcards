'use strict';

var app = angular.module('assignmentApp', [
    'general.controllers',
    'general.services',
    'general.directives',
    'general.filters',
    'assignmentApp.controllers',
    'assignmentApp.directives',
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
                templateUrl: config.partialsUrl + 'route/assignment.twig',
                controller: 'assignmentCtrl'
            }).
            otherwise({
                redirectTo: '/'
            });
    }
]);
