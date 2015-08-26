'use strict';

var app = angular.module('repositoryApp.controllers', []);

app.controller('repositoryCtrl', [
    '$scope', '$timeout', 'initialRouteSrv', 'cardsSrv', 'repoFilterSrv', 'messageSrv', 'CONFIG',
    function ($scope, $timeout, initialRouteSrv, cardsSrv, repoFilterSrv, messageSrv, config) {
        $scope.cards = null;
        $scope.total = 0;
        $scope.perPage = 10;
        $scope.currentPage = 0;
        $scope.timeoutPromise = null;
        $scope.messages = messageSrv.collect();
        $scope.userid = config.userid;
        $scope.groups = config.groups;
        $scope.omniscience = config.omniscience;
        $scope.cardStatus = config.cardStatus;
        $scope.filters = repoFilterSrv.filters;
        $scope.prevFilterQ = repoFilterSrv.filters.q;
        $scope.exportUrl = config.exportUrl;

        if (initialRouteSrv.checkStack()) {
            return;
        }

        if ($scope.omniscience) {
            $scope.messages.info = config.messages.omniscience;
        }

        $scope.getPageOfCards = function (currentPage) {
            $timeout.cancel($scope.timeoutPromise);
            cardsSrv.getPageOfCardsInRepositoryInCourse(currentPage, $scope.perPage, $scope.filters).
                then(function (data) {
                    $scope.cards = data.cards;
                    $scope.total = data.total;
                }, function (error) {
                    $scope.cards = null;
                    $scope.total = 0;
                    $scope.messages.error = error.errorMessage;
                }).
                finally(function () {
                    $scope.timeoutPromise = $timeout(function () {
                        $scope.getPageOfCards($scope.currentPage);
                    }, 10000);
                });
        };

        $scope.$watch('currentPage', function (newValue) {
            $scope.getPageOfCards(newValue);
        });

        $scope.filterChanged = function () {
            $scope.currentPage = 0;
            $scope.getPageOfCards($scope.currentPage);
        };

        $scope.sortChanged = function () {
            $scope.getPageOfCards($scope.currentPage);
        };

        $scope.filterQChanged = function () {
            $timeout.cancel($scope.timeoutPromise);
            $scope.timeoutPromise = $timeout(function () {
                if ($scope.prevFilterQ !== $scope.filters.q) {
                    $scope.prevFilterQ = $scope.filters.q;
                    $scope.filterChanged();
                } else {
                    $scope.timeoutPromise = $timeout(function () {
                        $scope.getPageOfCards($scope.currentPage);
                    }, 10000);
                }
            }, 1000);
        };

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeoutPromise);
        });
    }
]);
