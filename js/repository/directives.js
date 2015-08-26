'use strict';

var app = angular.module('repositoryApp.directives', []);

app.directive('repoListItem', [
    'cardsSrv', 'CONFIG',
    function (cardsSrv, config) {
        return {
            restrict: 'A',
            scope: {
                card: '=',
                messages: '=',
                filterChanged: '&'
            },
            templateUrl: config.partialsUrl + 'directive/repoListItem.twig',
            controller: ['$scope', function ($scope) {
                $scope.cardStatus = config.cardStatus;
                $scope.omniscience = config.omniscience;

                $scope.cardHref = function (card) {
                    var action = 'view';
                    if (card.ownerid === config.userid && card.status !== config.reverseCardStatus.in_review) {
                        action = 'edit';
                    } else if (card.status === config.reverseCardStatus.in_repository) {
                        action = 'feedback';
                    }
                    return '#/card/' + action + '/' + card.id;
                };

                $scope.statusChanged = function (card) {
                    cardsSrv.putCard(card.id, card).
                        then(function (data) {
                            var successMessage = data.successMessage;
                            delete data.successMessage;
                            $scope.card = data;
                            $scope.messages.success = successMessage;
                            $scope.filterChanged();
                        }, function (error) {
                            $scope.messages.error = error.errorMessage;
                        });
                };
            }]
        };
    }
]);
