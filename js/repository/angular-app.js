'use strict';

var app = angular.module('repositoryApp', [
    'general.controllers',
    'general.services',
    'general.directives',
    'general.filters',
    'repositoryApp.controllers',
    'repositoryApp.directives',
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
                templateUrl: config.partialsUrl + 'route/repository.twig',
                controller: 'repositoryCtrl'
            }).
            when('/card/edit/:id', {
                templateUrl: config.partialsUrl + 'route/cardEdit.twig',
                controller: 'cardEditCtrl'
            }).
            when('/card/view/:id', {
                templateUrl: config.partialsUrl + 'route/cardView.twig',
                controller: 'cardViewCtrl'
            }).
            when('/card/feedback/:id', {
                templateUrl: config.partialsUrl + 'route/cardFeedback.twig',
                controller: 'cardFeedbackCtrl'
            }).
            otherwise({
                redirectTo: '/'
            });
    }
]);
