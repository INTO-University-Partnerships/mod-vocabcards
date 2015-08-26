'use strict';

var app = angular.module('assignmentApp.directives', []);

app.directive('wordListItemShort', [
    'CONFIG',
    function (config) {
        return {
            restrict: 'A',
            scope: {
                word: '=',
                toggleSelectedWord: '&'
            },
            templateUrl: config.partialsUrl + 'directive/wordListItemShort.twig',
            link: function (scope, element) {
                element.bind('click', function () {
                    scope.toggleSelectedWord({
                        word: scope.word
                    });
                });
            }
        };
    }
]);

app.directive('studentListItem', [
    'CONFIG',
    function (config) {
        return {
            restrict: 'A',
            scope: {
                student: '=',
                setSelectedStudent: '&',
                deleteCard: '&'
            },
            templateUrl: config.partialsUrl + 'directive/studentListItem.twig',
            link: function (scope, element) {
                element.bind('click', function () {
                    scope.setSelectedStudent({
                        student: scope.student
                    });
                });
            }
        };
    }
]);
