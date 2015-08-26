'use strict';

var app = angular.module('cardsApp.directives', []);

app.directive('cardListItem', [
    'CONFIG',
    function (config) {
        return {
            restrict: 'A',
            scope: {
                card: '='
            },
            templateUrl: config.partialsUrl + 'directive/cardListItem.twig',
            controller: ['$scope', function ($scope) {
                $scope.cardStatus = config.cardStatus;
                $scope.reverseCardStatus = config.reverseCardStatus;
                $scope.repositoryUrl = config.repositoryUrl;

                $scope.cardHref = function (card) {
                    var verb = card.status === config.reverseCardStatus.in_review ? 'view' : 'edit';
                    return '#/card/' + verb + '/' + card.id;
                };
            }]
        };
    }
]);

app.directive('cardFeedbackListItem', [
    'CONFIG',
    function (config) {
        return {
            restrict: 'A',
            scope: {
                card: '='
            },
            templateUrl: config.partialsUrl + 'directive/cardFeedbackListItem.twig',
            controller: ['$scope', function ($scope) {
                $scope.cardHref = function (card) {
                    return '#/card/feedback/' + card.id;
                };
            }]
        };
    }
]);

app.directive('cardMenu', [
    '$location', 'CONFIG',
    function ($location, config) {
        var titles = [
            'edit_cards',
            'review_cards'
        ];
        var routes = [
            '/card/list',
            '/card/feedback'
        ];
        return {
            restrict: 'E',
            scope: {},
            templateUrl: config.partialsUrl + 'directive/cardMenu.twig',
            controller: ['$scope', function ($scope) {
                $scope.canFeedback = config.canFeedback;
                var i, count;
                $scope.menu = [];
                for (i = 0, count = routes.length; i < count; ++i) {
                    $scope.menu.push({
                        route: '#' + routes[i],
                        title: config.messages['menu_' + titles[i]],
                        active: $location.path() === routes[i]
                    });
                }
            }]
        };
    }
]);
