'use strict';

var app = angular.module('general.controllers', []);

app.controller('cardEditCtrl', [
    '$scope', '$routeParams', '$timeout', '$location', 'cardsSrv', 'messageSrv', 'CONFIG',
    function ($scope, $routeParams, $timeout, $location, cardsSrv, messageSrv, config) {
        $scope.app = config.app;
        $scope.card = null;
        $scope.messages = messageSrv.collect();
        $scope.timeout = null;
        $scope.reverseCardStatus = config.reverseCardStatus;

        cardsSrv.getCard($routeParams.id).
            then(function (data) {
                // if the card is not owned by the logged in user, or it's not in review, then cannot edit the card
                if (data.ownerid !== config.userid) {
                    messageSrv.messages.warning = config.messages.cannot_edit_card_as_not_owner;
                    $location.path('/card/view/' + $routeParams.id);
                } else if (data.status === config.reverseCardStatus.in_review) {
                    messageSrv.messages.warning = config.messages.cannot_edit_card_as_in_review;
                    $location.path('/card/view/' + $routeParams.id);
                } else {
                    $scope.card = data;
                }
            }, function (error) {
                $scope.messages.error = error.errorMessage;
                $scope.timeout = $timeout(function () {
                    $location.path('/');
                }, 5000);
            });

        $scope.displayWord = function () {
            return cardsSrv.displayWord($scope.card);
        };

        $scope.saveChanges = function () {
            if ($scope.card.status === config.reverseCardStatus.not_started) {
                $scope.card.status = config.reverseCardStatus.in_progress;
            }
            cardsSrv.putCard($scope.card.id, $scope.card).
                then(function (data) {
                    var successMessage = data.successMessage;
                    delete data.successMessage;
                    $scope.card = data;
                    $scope.messages.success = successMessage;
                }, function (error) {
                    $scope.messages.error = error.errorMessage;
                });
        };

        $scope.sendToTutor = function () {
            $scope.card.status = config.reverseCardStatus.in_review;
            cardsSrv.putCard($scope.card.id, $scope.card).
                then(function (data) {
                    $location.path('/card/view/' + data.id);
                }, function (error) {
                    $scope.messages.error = error.errorMessage;
                });
        };

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeout);
        });
    }
]);

app.controller('cardViewCtrl', [
    '$scope', '$routeParams', '$timeout', '$location', 'cardsSrv', 'messageSrv', 'CONFIG',
    function ($scope, $routeParams, $timeout, $location, cardsSrv, messageSrv, config) {
        $scope.app = config.app;
        $scope.card = null;
        $scope.messages = messageSrv.collect();
        $scope.timeout = null;

        cardsSrv.getCard($routeParams.id).
            then(function (data) {
                if (config.app === 'repository' && !config.omniscience && data.status !== config.reverseCardStatus.in_repository) {
                    messageSrv.messages.warning = config.messages.cannot_view_card_as_not_in_repository;
                    $location.path('/');
                } else {
                    $scope.card = data;
                }
            }, function (error) {
                $scope.messages.error = error.errorMessage;
                $scope.timeout = $timeout(function () {
                    $location.path('/');
                }, 5000);
            });

        $scope.displayWord = function () {
            return cardsSrv.displayWord($scope.card);
        };

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeout);
        });
    }
]);

app.controller('cardFeedbackCtrl', [
    '$scope', '$routeParams', '$timeout', '$window', '$location', 'cardsSrv', 'feedbackSrv', 'messageSrv', 'CONFIG',
    function ($scope, $routeParams, $timeout, $window, $location, cardsSrv, feedbackSrv, messageSrv, config) {
        $scope.app = config.app;
        $scope.card = null;
        $scope.messages = messageSrv.collect();
        $scope.timeout = null;

        $scope.getCard = function () {
            cardsSrv.getCard($routeParams.id).
                then(function (data) {
                    if (config.app === 'cards' && data.status !== config.reverseCardStatus.in_review) {
                        messageSrv.messages.warning = config.messages.cannot_review_card_as_not_in_review;
                        $location.path('/card/view/' + $routeParams.id);
                    } else if (config.app === 'repository' && data.status !== config.reverseCardStatus.in_repository) {
                        messageSrv.messages.warning = config.messages.cannot_review_card_as_not_in_repository;
                        $location.path('/card/view/' + $routeParams.id);
                    } else if (config.app === 'repository' && data.ownerid === config.userid) {
                        messageSrv.messages.warning = config.messages.cannot_review_own_card;
                        $location.path('/card/view/' + $routeParams.id);
                    } else {
                        $scope.card = data;
                    }
                }, function (error) {
                    $scope.messages.error = error.errorMessage;
                    $scope.timeout = $timeout(function () {
                        $location.path('/');
                    }, 5000);
                });
        };

        $scope.addFeedback = function () {
            var feedback = $scope.feedback;
            $scope.feedback = '';
            feedbackSrv.postFeedback(feedback, $scope.card.id).
                then(function (data) {
                    $scope.messages.success = data.successMessage;
                    $scope.getCard();
                }, function (error) {
                    $scope.messages.error = error.errorMessage;
                });
        };

        $scope.sendToStudent = function () {
            $scope.setCardStatusTo('in_progress');
        };

        $scope.sendToRepository = function () {
            $scope.setCardStatusTo('in_repository');
        };

        $scope.setCardStatusTo = function (status) {
            $scope.card.status = config.reverseCardStatus[status];
            cardsSrv.putCard($scope.card.id, $scope.card).
                then(function () {
                    $location.path('/card/feedback');
                }, function (error) {
                    $scope.messages.error = error.errorMessage;
                });
        };

        $scope.editFeedback = function (feedback) {
            feedbackSrv.putFeedback(feedback).
                then(function (data) {
                    $scope.messages.success = data.successMessage;
                }, function (error) {
                    $scope.messages.error = error.errorMessage;
                }).
                finally(function () {
                    $scope.getCard();
                });
        };

        $scope.deleteFeedback = function (feedbackid) {
            $timeout(function () {
                if (!$window.confirm(config.messages.confirm_delete_feedback)) {
                    return;
                }
                feedbackSrv.deleteFeedback(feedbackid).
                    then(function () {
                        $scope.messages.success = config.messages.feedback_deleted_successfully;
                    }, function (error) {
                        $scope.messages.error = error.errorMessage;
                    }).
                    finally(function () {
                        $scope.getCard();
                    });
            }, 1);
        };

        $scope.displayWord = function () {
            return cardsSrv.displayWord($scope.card);
        };

        $scope.$on('$destroy', function () {
            $timeout.cancel($scope.timeout);
        });

        if (config.app === 'cards' && !config.canFeedback) {
            messageSrv.messages.warning = config.messages.cannot_review;
            $location.path('/');
        } else {
            $scope.getCard();
        }
    }
]);
