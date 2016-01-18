(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
'use strict';

var app = angular.module('general.controllers', []);

app.controller('cardEditCtrl', ['$scope', '$routeParams', '$timeout', '$location', 'cardsSrv', 'messageSrv', 'CONFIG', function ($scope, $routeParams, $timeout, $location, cardsSrv, messageSrv, config) {
    $scope.app = config.app;
    $scope.card = null;
    $scope.messages = messageSrv.collect();
    $scope.timeout = null;
    $scope.reverseCardStatus = config.reverseCardStatus;

    cardsSrv.getCard($routeParams.id).then(function (data) {
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
        cardsSrv.putCard($scope.card.id, $scope.card).then(function (data) {
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
        cardsSrv.putCard($scope.card.id, $scope.card).then(function (data) {
            $location.path('/card/view/' + data.id);
        }, function (error) {
            $scope.messages.error = error.errorMessage;
        });
    };

    $scope.$on('$destroy', function () {
        $timeout.cancel($scope.timeout);
    });
}]);

app.controller('cardViewCtrl', ['$scope', '$routeParams', '$timeout', '$location', 'cardsSrv', 'messageSrv', 'CONFIG', function ($scope, $routeParams, $timeout, $location, cardsSrv, messageSrv, config) {
    $scope.app = config.app;
    $scope.card = null;
    $scope.messages = messageSrv.collect();
    $scope.timeout = null;

    cardsSrv.getCard($routeParams.id).then(function (data) {
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
}]);

app.controller('cardFeedbackCtrl', ['$scope', '$routeParams', '$timeout', '$window', '$location', 'cardsSrv', 'feedbackSrv', 'messageSrv', 'CONFIG', function ($scope, $routeParams, $timeout, $window, $location, cardsSrv, feedbackSrv, messageSrv, config) {
    $scope.app = config.app;
    $scope.card = null;
    $scope.messages = messageSrv.collect();
    $scope.timeout = null;

    $scope.getCard = function () {
        cardsSrv.getCard($routeParams.id).then(function (data) {
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
        feedbackSrv.postFeedback(feedback, $scope.card.id).then(function (data) {
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
        cardsSrv.putCard($scope.card.id, $scope.card).then(function () {
            $location.path('/card/feedback');
        }, function (error) {
            $scope.messages.error = error.errorMessage;
        });
    };

    $scope.editFeedback = function (feedback) {
        feedbackSrv.putFeedback(feedback).then(function (data) {
            $scope.messages.success = data.successMessage;
        }, function (error) {
            $scope.messages.error = error.errorMessage;
        }).finally(function () {
            $scope.getCard();
        });
    };

    $scope.deleteFeedback = function (feedbackid) {
        $timeout(function () {
            if (!$window.confirm(config.messages.confirm_delete_feedback)) {
                return;
            }
            feedbackSrv.deleteFeedback(feedbackid).then(function () {
                $scope.messages.success = config.messages.feedback_deleted_successfully;
            }, function (error) {
                $scope.messages.error = error.errorMessage;
            }).finally(function () {
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
}]);

},{}],2:[function(require,module,exports){
'use strict';

var app = angular.module('general.directives', []);

app.directive('selectOnFocus', function () {
    return {
        restrict: 'A',
        link: function link(scope, element) {
            element.on('focus', function () {
                this.select();
            });
        }
    };
});

app.directive('alerts', ['CONFIG', function (config) {
    return {
        restrict: 'E',
        scope: {
            messages: '='
        },
        templateUrl: config.partialsUrl + 'directive/alerts.twig',
        link: function link(scope, element) {
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
}]);

app.directive('pagination', ['CONFIG', function (config) {
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
}]);

app.directive('feedback', ['$timeout', 'feedbackSrv', 'CONFIG', function ($timeout, feedbackSrv, config) {
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
                feedbackSrv.getPageOfFeedback(currentPage, $scope.perPage, $scope.card.id).then(function (data) {
                    $scope.feedback = data.feedback;
                    $scope.total = data.total;
                }, function (error) {
                    $scope.feedback = null;
                    $scope.total = 0;
                    $scope.messages.error = error.errorMessage;
                }).finally(function () {
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
}]);

app.directive('feedbackListItem', ['$timeout', 'CONFIG', function ($timeout, config) {
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
        link: function link(scope, element) {
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
}]);

},{}],3:[function(require,module,exports){
'use strict';

var app = angular.module('general.filters', []);

app.filter('substring', function () {
    return function (str, start, end) {
        return str.substring(start, end);
    };
});

app.filter('pipes', ['$sce', function ($sce) {
    return function (str) {
        if (!str) {
            return '';
        }
        return $sce.trustAsHtml(str.replace(/\s*,\s*/g, '&nbsp;<strong>|</strong>&nbsp;'));
    };
}]);

},{}],4:[function(require,module,exports){
'use strict';

require('./controllers');

require('./directives');

require('./services');

require('./filters');

},{"./controllers":1,"./directives":2,"./filters":3,"./services":5}],5:[function(require,module,exports){
'use strict';

var app = angular.module('general.services', []);

app.service('wordsSrv', ['$http', '$q', 'CONFIG', function ($http, $q, config) {
    var url = config.apiUrls.words;

    this.getPageOfWords = function (page, perPage, sectionid, q) {
        var deferred = $q.defer();
        $http.get(url + '?limitfrom=' + page * perPage + '&limitnum=' + perPage + '&sectionid=' + sectionid + '&q=' + encodeURIComponent(q)).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.getWord = function (wordid) {
        var deferred = $q.defer();
        $http.get(url + '/' + wordid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.postWord = function (word, section) {
        var deferred = $q.defer();
        var data = {
            word: word,
            sectionid: section
        };
        $http.post(url + '?sesskey=' + config.sesskey, data).success(function (responseData) {
            deferred.resolve(responseData);
        }).error(function (responseData) {
            deferred.reject(responseData);
        });
        return deferred.promise;
    };

    this.putWord = function (wordid, word, section) {
        var deferred = $q.defer();
        var data = {
            word: word,
            sectionid: section
        };
        $http.put(url + '/' + wordid + '?sesskey=' + config.sesskey, data).success(function (responseData) {
            deferred.resolve(responseData);
        }).error(function (responseData) {
            deferred.reject(responseData);
        });
        return deferred.promise;
    };

    this.deleteWord = function (wordid) {
        var deferred = $q.defer();
        $http.delete(url + '/' + wordid + '?sesskey=' + config.sesskey).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };
}]);

app.service('studentsSrv', ['$http', '$q', 'CONFIG', function ($http, $q, config) {
    var url = config.apiUrls.students;

    this.getPageOfStudents = function (page, perPage, groupid) {
        var deferred = $q.defer();
        $http.get(url + '?limitfrom=' + page * perPage + '&limitnum=' + perPage + '&groupid=' + groupid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };
}]);

app.service('cardsSrv', ['$http', '$q', 'CONFIG', function ($http, $q, config) {
    var url = config.apiUrls.cards;

    this.getPageOfStudentCardsInActivity = function (page, perPage, instanceid) {
        var deferred = $q.defer();
        $http.get(url + '?limitfrom=' + page * perPage + '&limitnum=' + perPage + '&instanceid=' + instanceid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.getPageOfCardsInReviewInActivity = function (page, perPage, instanceid) {
        var deferred = $q.defer();
        $http.get(url + '/review?limitfrom=' + page * perPage + '&limitnum=' + perPage + '&instanceid=' + instanceid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.getPageOfCardsInRepositoryInCourse = function (page, perPage, filters) {
        var deferred = $q.defer();
        $http.get(url + '/repository' + '?limitfrom=' + page * perPage + '&limitnum=' + perPage + '&groupid=' + filters.groupid + '&userid=' + filters.userid + '&q=' + encodeURIComponent(filters.q) + '&status=' + filters.status + '&sort=' + encodeURIComponent(filters.sort)).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.getCard = function (cardid) {
        var deferred = $q.defer();
        $http.get(url + '/' + cardid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.putCard = function (cardid, card) {
        var deferred = $q.defer();
        $http.put(url + '/' + cardid + '?sesskey=' + config.sesskey, card).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.createCardsFromWords = function (words, student, groupid) {
        var deferred = $q.defer(),
            wordids = [],
            data = {};
        angular.forEach(words, function (value) {
            wordids.push(value.id);
        });
        data.wordids = wordids;
        data.ownerid = student.id;
        data.groupid = groupid;
        $http.post(url + '/create?sesskey=' + config.sesskey, data).success(function (responseData) {
            deferred.resolve(responseData);
        }).error(function (responseData) {
            deferred.reject(responseData);
        });
        return deferred.promise;
    };

    this.deleteCard = function (cardid) {
        var deferred = $q.defer();
        $http.delete(url + '/' + cardid + '?sesskey=' + config.sesskey).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.displayWord = function (card) {
        if (!card) {
            return '';
        }
        return card.word.charAt(0).toUpperCase() + card.word.slice(1);
    };
}]);

app.service('feedbackSrv', ['$http', '$q', 'CONFIG', function ($http, $q, config) {
    var url = config.apiUrls.feedbacks;

    this.getPageOfFeedback = function (page, perPage, cardid) {
        var deferred = $q.defer();
        $http.get(url + '?limitfrom=' + page * perPage + '&limitnum=' + perPage + '&cardid=' + cardid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.postFeedback = function (feedback, cardid) {
        var deferred = $q.defer();
        var data = {
            feedback: feedback,
            cardid: cardid
        };
        var cmid = config.hasOwnProperty('cmid') ? '&cmid=' + config.cmid : '';
        $http.post(url + '?sesskey=' + config.sesskey + '&app=' + config.app + cmid, data).success(function (responseData) {
            deferred.resolve(responseData);
        }).error(function (responseData) {
            deferred.reject(responseData);
        });
        return deferred.promise;
    };

    this.putFeedback = function (feedback) {
        var deferred = $q.defer();
        var data = {
            feedback: feedback.feedback
        };
        $http.put(url + '/' + feedback.id + '?sesskey=' + config.sesskey + '&app=' + config.app, data).success(function (responseData) {
            deferred.resolve(responseData);
        }).error(function (responseData) {
            deferred.reject(responseData);
        });
        return deferred.promise;
    };

    this.deleteFeedback = function (feedbackid) {
        var deferred = $q.defer();
        $http.delete(url + '/' + feedbackid + '?sesskey=' + config.sesskey + '&app=' + config.app).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };
}]);

app.service('repoFilterSrv', ['CONFIG', function (config) {
    this.filters = {
        groupid: 0,
        userid: 0,
        q: '',
        status: config.reverseCardStatus.in_repository,
        sort: 'word'
    };
}]);

app.service('initialRouteSrv', ['$location', 'CONFIG', function ($location, config) {
    var routes = [];
    if (config.angularjs_route) {
        routes.push(config.angularjs_route);
    }
    this.checkStack = function () {
        var retval = null;
        if (routes.length) {
            retval = routes.pop();
            $location.path(retval);
        }
        return retval;
    };
}]);

app.service('messageSrv', function () {
    this.messages = {};
    this.collect = function () {
        var retval = {};
        angular.copy(this.messages, retval);
        this.messages = {};
        return retval;
    };
});

},{}],6:[function(require,module,exports){
'use strict';

var app = angular.module('repositoryApp', ['general.controllers', 'general.services', 'general.directives', 'general.filters', 'repositoryApp.controllers', 'repositoryApp.directives', 'ngRoute', 'ngSanitize']);

app.constant('CONFIG', window.CONFIG);
delete window.CONFIG;

app.config(['$routeProvider', '$httpProvider', 'CONFIG', function ($routeProvider, $httpProvider, config) {
    $httpProvider.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    $routeProvider.when('/', {
        templateUrl: config.partialsUrl + 'route/repository.twig',
        controller: 'repositoryCtrl'
    }).when('/card/edit/:id', {
        templateUrl: config.partialsUrl + 'route/cardEdit.twig',
        controller: 'cardEditCtrl'
    }).when('/card/view/:id', {
        templateUrl: config.partialsUrl + 'route/cardView.twig',
        controller: 'cardViewCtrl'
    }).when('/card/feedback/:id', {
        templateUrl: config.partialsUrl + 'route/cardFeedback.twig',
        controller: 'cardFeedbackCtrl'
    }).otherwise({
        redirectTo: '/'
    });
}]);

},{}],7:[function(require,module,exports){
'use strict';

require('./angular-app');

require('./controllers');

require('./directives');

require('../general/import');

},{"../general/import":4,"./angular-app":6,"./controllers":8,"./directives":9}],8:[function(require,module,exports){
'use strict';

var app = angular.module('repositoryApp.controllers', []);

app.controller('repositoryCtrl', ['$scope', '$timeout', 'initialRouteSrv', 'cardsSrv', 'repoFilterSrv', 'messageSrv', 'CONFIG', function ($scope, $timeout, initialRouteSrv, cardsSrv, repoFilterSrv, messageSrv, config) {
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
        cardsSrv.getPageOfCardsInRepositoryInCourse(currentPage, $scope.perPage, $scope.filters).then(function (data) {
            $scope.cards = data.cards;
            $scope.total = data.total;
        }, function (error) {
            $scope.cards = null;
            $scope.total = 0;
            $scope.messages.error = error.errorMessage;
        }).finally(function () {
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
}]);

},{}],9:[function(require,module,exports){
'use strict';

var app = angular.module('repositoryApp.directives', []);

app.directive('repoListItem', ['cardsSrv', 'CONFIG', function (cardsSrv, config) {
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
                cardsSrv.putCard(card.id, card).then(function (data) {
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
}]);

},{}]},{},[7]);
