'use strict';

var app = angular.module('cardsApp', [
    'general.controllers',
    'general.services',
    'general.directives',
    'general.filters',
    'cardsApp.controllers',
    'cardsApp.directives',
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
            when('/card/list', {
                templateUrl: config.partialsUrl + 'route/cardList.twig',
                controller: 'cardsListCtrl'
            }).
            when('/card/edit/:id', {
                templateUrl: config.partialsUrl + 'route/cardEdit.twig',
                controller: 'cardEditCtrl'
            }).
            when('/card/view/:id', {
                templateUrl: config.partialsUrl + 'route/cardView.twig',
                controller: 'cardViewCtrl'
            }).
            when('/card/feedback', {
                templateUrl: config.partialsUrl + 'route/cardFeedbackList.twig',
                controller: 'cardFeedbackListCtrl'
            }).
            when('/card/feedback/:id', {
                templateUrl: config.partialsUrl + 'route/cardFeedback.twig',
                controller: 'cardFeedbackCtrl'
            }).
            otherwise({
                redirectTo: '/card/list'
            });
    }
]);
