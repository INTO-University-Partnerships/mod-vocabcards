'use strict';

var app = angular.module('syllabusApp.directives', []);

app.directive('wordListItemLong', [
    '$timeout', 'CONFIG',
    function ($timeout, config) {
        return {
            restrict: 'A',
            scope: {
                word: '=',
                editWord: '&',
                deleteWord: '&',
                stopAutoRefresh: '&',
                startAutoRefresh: '&'
            },
            templateUrl: config.partialsUrl + 'directive/wordListItemLong.twig',
            controller: ['$scope', function ($scope) {
                $scope.editing = false;

                $scope.enableEditing = function () {
                    $scope.editing = true;
                    $scope.stopAutoRefresh();
                    $scope.oldword = $scope.word.word;
                    $timeout(function () {
                        $scope.elem.focus();
                    }, 1);
                };

                $scope.disableEditing = function () {
                    if ($scope.oldword !== $scope.word.word) {
                        $scope.editWord({
                            word: $scope.word
                        });
                    } else {
                        $scope.startAutoRefresh();
                    }
                };
            }],
            link: function (scope, element) {
                scope.elem = element.find('input')[0];
                element.bind('keyup', function (event) {
                    if (!scope.editing || !(event.which === 13 || event.which === 27)) {
                        return;
                    }
                    scope.$apply(function () {
                        scope.editing = false;
                        if (event.which === 13) {
                            scope.disableEditing();
                        } else if (event.which === 27) {
                            scope.word.word = scope.oldword;
                            scope.startAutoRefresh();
                        }
                    });
                });
            }
        };
    }
]);
