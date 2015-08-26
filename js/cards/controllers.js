'use strict';

var app = angular.module('cardsApp.controllers', []);

app.controller('cardsListCtrl', [
    '$scope', '$timeout', 'initialRouteSrv', 'cardsSrv', 'messageSrv', 'CONFIG',
    function ($scope, $timeout, initialRouteSrv, cardsSrv, messageSrv, config) {
        $scope.perPage = 10;
        $scope.cards = null;
        $scope.total = 0;
        $scope.currentPage = 0;
        $scope.timeoutPromise = null;
        $scope.messages = messageSrv.collect();

        if (initialRouteSrv.checkStack()) {
            return;
        }

        $scope.getPageOfStudentCardsInActivity = function (currentPage) {
            $timeout.cancel($scope.timeoutPromise);
            cardsSrv.getPageOfStudentCardsInActivity(currentPage, $scope.perPage, config.instanceid).
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
                        $scope.getPageOfStudentCardsInActivity($scope.currentPage);
                    }, 10000);
                });
        };

        $scope.$watch('currentPage', function (newValue) {
            $scope.getPageOfStudentCardsInActivity(newValue);
        });

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeoutPromise);
        });
    }
]);

app.controller('cardFeedbackListCtrl', [
    '$scope', '$timeout', 'cardsSrv', 'messageSrv', 'CONFIG',
    function ($scope, $timeout, cardsSrv, messageSrv, config) {
        $scope.perPage = 10;
        $scope.cards = null;
        $scope.total = 0;
        $scope.currentPage = 0;
        $scope.timeoutPromise = null;
        $scope.messages = messageSrv.collect();

        $scope.getPageOfCardsInReviewInActivity = function (currentPage) {
            $timeout.cancel($scope.timeoutPromise);
            cardsSrv.getPageOfCardsInReviewInActivity(currentPage, $scope.perPage, config.instanceid).
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
                        $scope.getPageOfCardsInReviewInActivity($scope.currentPage);
                    }, 10000);
                });
        };

        $scope.$watch('currentPage', function (newValue) {
            $scope.getPageOfCardsInReviewInActivity(newValue);
        });

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeoutPromise);
        });
    }
]);
