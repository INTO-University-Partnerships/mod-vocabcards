'use strict';

var app = angular.module('assignmentApp.controllers', []);

app.controller('assignmentCtrl', [
    '$scope',
    function ($scope) {
        $scope.unavailableWords = {
            ids: []
        };

        $scope.timers = {
            on: true
        };

        $scope.messages = {};

        $scope.selections = {
            words: [],
            student: null,
            filterGroup: 0
        };

        $scope.getIndexOfWordId = function (wordid) {
            var index = -1;
            angular.forEach($scope.selections.words, function (value, key) {
                if (value.id === wordid) {
                    index = key;
                    return false;
                }
            });
            return index;
        };

        $scope.getIndexOfWord = function (word) {
            return $scope.getIndexOfWordId(word.id);
        };

        $scope.setSelectedStudentWords = function () {
            $scope.unavailableWords.ids = [];
            if (!$scope.selections.student || $scope.selections.student.cards.length === 0) {
                return;
            }
            angular.forEach($scope.selections.student.cards, function (value) {
                var index;
                $scope.unavailableWords.ids.push(value.wordid);
                index = $scope.getIndexOfWordId(value.wordid);
                if (index !== -1) {
                    $scope.selections.words.splice(index, 1);
                }
            });
        };

        $scope.$watch('selections.student', function () {
            $scope.setSelectedStudentWords();
        });

        $scope.getIndexOfUnavailableWordId = function (wordid) {
            var index = -1;
            angular.forEach($scope.unavailableWords.ids, function (value, key) {
                if (value === wordid) {
                    index = key;
                    return false;
                }
            });
            return index;
        };
    }
]);

app.controller('wordsCtrl', [
    '$scope', '$timeout', 'wordsSrv', 'CONFIG',
    function ($scope, $timeout, wordsSrv, config) {
        $scope.perPageWords = 12;
        $scope.words = null;
        $scope.totalWords = 0;
        $scope.currentPageWords = 0;
        $scope.timeoutPromiseWords = null;
        $scope.sections = config.sections;
        $scope.filterSection = 0;
        $scope.filterQ = $scope.prevFilterQ = '';

        $scope.getPageOfWords = function (currentPage) {
            $timeout.cancel($scope.timeoutPromiseWords);
            wordsSrv.getPageOfWords(currentPage, $scope.perPageWords, $scope.filterSection, $scope.filterQ).
                then(function (data) {
                    $scope.words = data.words;
                    $scope.totalWords = data.total;
                }, function (error) {
                    $scope.words = null;
                    $scope.totalWords = 0;
                    $scope.messages.error = error.errorMessage;
                }).
                finally(function () {
                    $scope.timeoutPromiseWords = $timeout(function () {
                        $scope.getPageOfWords($scope.currentPageWords);
                    }, 10000);
                });
        };

        $scope.$watch('currentPageWords', function (newValue) {
            $scope.getPageOfWords(newValue);
        });

        $scope.$watch('timers.on', function (newValue, oldValue) {
            if (newValue === true && oldValue === false) {
                $scope.getPageOfWords($scope.currentPageWords);
            } else if (newValue === false && oldValue === true) {
                $timeout.cancel($scope.timeoutPromiseWords);
            }
        });

        $scope.filterSectionChanged = function () {
            $scope.currentPageWords = 0;
            $scope.getPageOfWords(0);
        };

        $scope.filterQChanged = function () {
            $timeout.cancel($scope.timeoutPromiseWords);
            $scope.timeoutPromiseWords = $timeout(function () {
                if ($scope.prevFilterQ !== $scope.filterQ) {
                    $scope.prevFilterQ = $scope.filterQ;
                    $scope.currentPageWords = 0;
                    $scope.getPageOfWords(0);
                } else {
                    $scope.timeoutPromiseWords = $timeout(function () {
                        $scope.getPageOfWords($scope.currentPageWords);
                    }, 10000);
                }
            }, 1000);
        };

        $scope.toggleSelectedWord = function (word) {
            $scope.$apply(function () {
                var index = $scope.getIndexOfUnavailableWordId(word.id);
                if (index !== -1) {
                    return;
                }
                index = $scope.getIndexOfWord(word);
                if (index === -1) {
                    $scope.selections.words.push(word);
                } else {
                    $scope.selections.words.splice(index, 1);
                }
            });
        };

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeoutPromiseWords);
        });
    }
]);

app.controller('studentsCtrl', [
    '$scope', '$timeout', '$window', 'studentsSrv', 'cardsSrv', 'CONFIG',
    function ($scope, $timeout, $window, studentsSrv, cardsSrv, config) {
        $scope.perPageStudents = 8;
        $scope.students = null;
        $scope.totalStudents = 0;
        $scope.currentPageStudents = 0;
        $scope.timeoutPromiseStudents = null;
        $scope.groups = config.groups;

        $scope.getPageOfStudents = function (currentPage) {
            $timeout.cancel($scope.timeoutPromiseStudents);
            studentsSrv.getPageOfStudents(currentPage, $scope.perPageStudents, $scope.selections.filterGroup).
                then(function (data) {
                    $scope.students = data.students;
                    $scope.totalStudents = data.total;
                }, function (error) {
                    $scope.students = null;
                    $scope.totalStudents = 0;
                    $scope.messages.error = error.errorMessage;
                }).
                finally(function () {
                    if ($scope.selections.student) {
                        angular.forEach($scope.students, function (value) {
                            if (value.id === $scope.selections.student.id) {
                                $scope.selections.student.cards = value.cards;
                            }
                        });
                    }
                    $scope.setSelectedStudentWords();
                    $scope.timeoutPromiseStudents = $timeout(function () {
                        $scope.getPageOfStudents($scope.currentPageStudents);
                    }, 10000);
                });
        };

        $scope.$watch('currentPageStudents', function (newValue) {
            $scope.getPageOfStudents(newValue);
        });

        $scope.$watch('timers.on', function (newValue, oldValue) {
            if (newValue === true && oldValue === false) {
                $scope.getPageOfStudents($scope.currentPageStudents);
            } else if (newValue === false && oldValue === true) {
                $timeout.cancel($scope.timeoutPromiseStudents);
            }
        });

        $scope.filterGroupChanged = function () {
            $scope.selections.student = null;
            $scope.currentPageStudents = 0;
            $scope.getPageOfStudents(0);
        };

        $scope.setSelectedStudent = function (student) {
            $scope.$apply(function () {
                if ($scope.selections.student && $scope.selections.student.id === student.id) {
                    $scope.selections.student = null;
                } else {
                    $scope.selections.student = student;
                }
            });
        };

        $scope.deleteCard = function (cardid) {
            $scope.timers.on = false;
            $timeout(function () {
                if (!$window.confirm(config.messages.confirm_delete_card)) {
                    $scope.timers.on = true;
                    return;
                }
                cardsSrv.deleteCard(cardid).
                    then(function () {
                        $scope.messages.success = config.messages.card_deleted_successfully;
                    }, function (error) {
                        $scope.messages.error = error.errorMessage;
                    }).
                    finally(function () {
                        $scope.timers.on = true;
                    });
            }, 1);
        };

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeoutPromiseStudents);
        });
    }
]);

app.controller('confirmationCtrl', [
    '$scope', '$timeout', '$window', 'cardsSrv', 'CONFIG',
    function ($scope, $timeout, $window, cardsSrv, config) {
        $scope.createCardsFromWords = function () {
            var words = $scope.selections.words,
                student = $scope.selections.student;
            $scope.timers.on = false;
            $timeout(function () {
                if ($scope.selections.filterGroup === 0) {
                    if (!$window.confirm(config.messages.confirm_assign_cards_without_group)) {
                        $scope.timers.on = true;
                        return;
                    }
                }
                $scope.selections.words = [];
                $scope.selections.student = null;
                cardsSrv.createCardsFromWords(words, student, $scope.selections.filterGroup).
                    then(function (data) {
                        $scope.messages.success = data.successMessage;
                    }, function (error) {
                        $scope.messages.error = error.errorMessage;
                    }).
                    finally(function () {
                        $scope.timers.on = true;
                    });
            }, 1);
        };

        $scope.removeWord = function (word) {
            var index = $scope.getIndexOfWord(word);
            if (index !== -1) {
                $scope.selections.words.splice(index, 1);
            }
        };

        $scope.removeStudent = function () {
            $scope.selections.student = null;
        };
    }
]);
