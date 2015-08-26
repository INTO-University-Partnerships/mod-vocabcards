'use strict';

var app = angular.module('syllabusApp.controllers', []);

app.controller('syllabusCtrl', [
    '$scope', '$timeout', '$window', 'wordsSrv', 'CONFIG',
    function ($scope, $timeout, $window, wordsSrv, config) {
        $scope.words = null;
        $scope.total = 0;
        $scope.perPage = 10;
        $scope.currentPage = 0;
        $scope.newWord = '';
        $scope.timeoutPromise = null;
        $scope.messages = {};
        $scope.sections = config.sections;
        $scope.newSection = $scope.sections[0].id;
        $scope.sectionFilter = 0;

        $scope.getPageOfWords = function (currentPage) {
            $timeout.cancel($scope.timeoutPromise);
            wordsSrv.getPageOfWords(currentPage, $scope.perPage, $scope.sectionFilter, '').
                then(function (data) {
                    $scope.words = data.words;
                    $scope.total = data.total;
                }, function (error) {
                    $scope.words = null;
                    $scope.total = 0;
                    $scope.messages.error = error.errorMessage;
                }).
                finally(function () {
                    $scope.timeoutPromise = $timeout(function () {
                        $scope.getPageOfWords($scope.currentPage);
                    }, 10000);
                });
        };

        $scope.$watch('currentPage', function (newValue) {
            $scope.getPageOfWords(newValue);
        });

        $scope.addNewWordDisabled = function () {
            return $scope.newWord.length === 0;
        };

        $scope.addNewWord = function () {
            $timeout.cancel($scope.timeoutPromise);
            wordsSrv.postWord($scope.newWord, $scope.newSection).
                then(function (data) {
                    $scope.messages.success = data.successMessage;
                    $scope.currentPage = 0;
                    $scope.getPageOfWords(0);
                }, function (error) {
                    $scope.messages.error = error.errorMessage;
                    $scope.getPageOfWords($scope.currentPage);
                });
        };

        $scope.editWord = function (word) {
            $timeout.cancel($scope.timeoutPromise);
            wordsSrv.putWord(word.id, word.word, word.section).
                then(function (data) {
                    $scope.messages.success = data.successMessage;
                }, function (error) {
                    $scope.messages.error = error.errorMessage;
                }).
                finally(function () {
                    $scope.getPageOfWords($scope.currentPage);
                });
        };

        $scope.deleteWord = function (wordid) {
            $timeout.cancel($scope.timeoutPromise);
            $timeout(function () {
                if (!$window.confirm(config.messages.confirm_delete_word)) {
                    $scope.getPageOfWords($scope.currentPage);
                    return;
                }
                wordsSrv.deleteWord(wordid).
                    then(function () {
                        $scope.messages.success = config.messages.word_deleted_successfully;
                        $scope.currentPage = 0;
                        $scope.getPageOfWords(0);
                    }, function (error) {
                        $scope.messages.error = error.errorMessage;
                        $scope.getPageOfWords($scope.currentPage);
                    });
            }, 1);
        };

        $scope.sectionFilterChanged = function () {
            $scope.currentPage = 0;
            $scope.getPageOfWords(0);
        };

        $scope.stopAutoRefresh = function () {
            $timeout.cancel($scope.timeoutPromise);
        };

        $scope.startAutoRefresh = function () {
            $scope.getPageOfWords($scope.currentPage);
        };

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeoutPromise);
        });
    }
]);
