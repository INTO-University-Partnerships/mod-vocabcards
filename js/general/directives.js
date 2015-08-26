'use strict';

var app = angular.module('general.directives', []);

app.directive('selectOnFocus', function () {
    return {
        restrict: 'A',
        link: function (scope, element) {
            element.on('focus', function () {
                this.select();
            });
        }
    };
});

app.directive('alerts', [
    'CONFIG',
    function (config) {
        return {
            restrict: 'E',
            scope: {
                messages: '='
            },
            templateUrl: config.partialsUrl + 'directive/alerts.twig',
            link: function (scope, element) {
                element.find('button').bind('click', function () {
                    scope.$apply(function () {
                        scope.class = '';
                        scope.msg = '';
                    });
                });
            },
            controller: ['$scope', function ($scope) {
                $scope.class = '';

                $scope.setMessage = function (status) {
                    if ($scope.messages[status]) {
                        $scope.msg = $scope.messages[status];
                        $scope.class = 'alert-' + status;
                        $scope.messages = {};
                    }
                };

                angular.forEach(['success', 'error', 'warning', 'info'], function (status) {
                    $scope.$watch('messages.' + status, function () {
                        $scope.setMessage(status);
                    }, true);
                });
            }]
        };
    }
]);

app.directive('pagination', [
    'CONFIG',
    function (config) {
        return {
            restrict: 'E',
            scope: {
                perPage: '@',
                currentPage: '=',
                total: '=',
                fetchPage: '&'
            },
            templateUrl: config.partialsUrl + 'directive/pagination.twig',
            controller: ['$scope', function ($scope) {
                $scope.currentPage = 0;
                $scope.pageCount = 0;
                $scope.pages = [];

                $scope.calculatePageCount = function () {
                    if ($scope.total === 0) {
                        $scope.pageCount = 1;
                    } else {
                        $scope.pageCount = Math.ceil($scope.total / $scope.perPage);
                    }
                };

                $scope.calculatePages = function () {
                    var from, to, i;
                    from = 1;
                    to = $scope.pageCount;
                    $scope.pages = [];
                    for (i = from; i <= to; ++i) {
                        $scope.pages.push(i);
                    }
                };

                $scope.$watch('currentPage', function () {
                    $scope.calculatePages();
                });

                $scope.$watch('total', function () {
                    $scope.calculatePageCount();
                    $scope.calculatePages();
                });

                $scope.prevPage = function () {
                    if ($scope.currentPage > 0) {
                        --$scope.currentPage;
                    }
                };

                $scope.prevPageDisabled = function () {
                    var disabled = $scope.currentPage === 0 ? 'disabled' : '';
                    return disabled;
                };

                $scope.nextPage = function () {
                    if ($scope.currentPage < $scope.pageCount - 1) {
                        $scope.currentPage++;
                    }
                };

                $scope.nextPageDisabled = function () {
                    var disabled = $scope.currentPage === $scope.pageCount - 1 ? 'disabled' : '';
                    return disabled;
                };

                $scope.pageDisabled = function (n) {
                    var disabled = $scope.currentPage === n;
                    return disabled;
                };

                $scope.gotoPage = function (n) {
                    $scope.currentPage = n;
                };
            }]
        };
    }
]);

app.directive('feedback', [
    '$timeout', 'feedbackSrv', 'CONFIG',
    function ($timeout, feedbackSrv, config) {
        return {
            restrict: 'E',
            scope: {
                card: '=',
                messages: '=',
                editF: '&',
                deleteF: '&'
            },
            templateUrl: config.partialsUrl + 'directive/feedback.twig',
            controller: ['$scope', function ($scope) {
                $scope.perPage = 5;
                $scope.feedback = null;
                $scope.total = 0;
                $scope.currentPage = 0;
                $scope.timeoutPromise = null;

                $scope.getPageOfFeedback = function (currentPage) {
                    if (!$scope.card) {
                        return;
                    }
                    $timeout.cancel($scope.timeoutPromise);
                    feedbackSrv.getPageOfFeedback(currentPage, $scope.perPage, $scope.card.id).
                        then(function (data) {
                            $scope.feedback = data.feedback;
                            $scope.total = data.total;
                        }, function (error) {
                            $scope.feedback = null;
                            $scope.total = 0;
                            $scope.messages.error = error.errorMessage;
                        }).
                        finally(function () {
                            $scope.timeoutPromise = $timeout(function () {
                                $scope.getPageOfFeedback($scope.currentPage);
                            }, 10000);
                        });
                };

                $scope.$watch('currentPage', function (newValue) {
                    $scope.getPageOfFeedback(newValue);
                });

                $scope.$watch('card', function () {
                    $scope.currentPage = 0;
                    $scope.getPageOfFeedback($scope.currentPage);
                }, true);

                $scope.editFeedback = function (feedback) {
                    $scope.editF({
                        feedback: feedback
                    });
                };

                $scope.deleteFeedback = function (feedbackid) {
                    $scope.deleteF({
                        feedbackid: feedbackid
                    });
                };

                $scope.stopAutoRefresh = function () {
                    $timeout.cancel($scope.timeoutPromise);
                };

                $scope.startAutoRefresh = function () {
                    $scope.getPageOfFeedback($scope.currentPage);
                };

                $scope.$on('$destroy', function () {
                    $timeout.cancel($scope.timeoutPromise);
                });
            }]
        };
    }
]);

app.directive('feedbackListItem', [
    '$timeout', 'CONFIG',
    function ($timeout, config) {
        return {
            restrict: 'A',
            scope: {
                feedback: '=',
                editFeedback: '&',
                deleteFeedback: '&',
                stopAutoRefresh: '&',
                startAutoRefresh: '&'
            },
            templateUrl: config.partialsUrl + 'directive/feedbackListItem.twig',
            controller: ['$scope', function ($scope) {
                $scope.userid = config.userid;
                $scope.editing = false;
                $scope.canFeedback = config.canFeedback;

                $scope.enableEditing = function () {
                    $scope.editing = true;
                    $scope.stopAutoRefresh();
                    $scope.oldFeedback = $scope.feedback.feedback;
                    $timeout(function () {
                        $scope.elem.focus();
                    }, 1);
                };

                $scope.disableEditing = function () {
                    if ($scope.oldFeedback !== $scope.feedback.feedback) {
                        $scope.editFeedback({
                            feedback: $scope.feedback
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
                            scope.feedback.feedback = scope.oldFeedback;
                            scope.startAutoRefresh();
                        }
                    });
                });
            }
        };
    }
]);
