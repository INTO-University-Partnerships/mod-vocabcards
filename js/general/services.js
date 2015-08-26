'use strict';

var app = angular.module('general.services', []);

app.service('wordsSrv', [
    '$http', '$q', 'CONFIG',
    function ($http, $q, config) {
        var url = config.apiUrls.words;

        this.getPageOfWords = function (page, perPage, sectionid, q) {
            var deferred = $q.defer();
            $http.get(url + '?limitfrom=' + (page * perPage) + '&limitnum=' + perPage + '&sectionid=' + sectionid + '&q=' + encodeURIComponent(q)).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };

        this.getWord = function (wordid) {
            var deferred = $q.defer();
            $http.get(url + '/' + wordid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
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
            $http.post(url + '?sesskey=' + config.sesskey, data).
                success(function (responseData) {
                    deferred.resolve(responseData);
                }).
                error(function (responseData) {
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
            $http.put(url + '/' + wordid + '?sesskey=' + config.sesskey, data).
                success(function (responseData) {
                    deferred.resolve(responseData);
                }).
                error(function (responseData) {
                    deferred.reject(responseData);
                });
            return deferred.promise;
        };

        this.deleteWord = function (wordid) {
            var deferred = $q.defer();
            $http.delete(url + '/' + wordid + '?sesskey=' + config.sesskey).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };
    }
]);

app.service('studentsSrv', [
    '$http', '$q', 'CONFIG',
    function ($http, $q, config) {
        var url = config.apiUrls.students;

        this.getPageOfStudents = function (page, perPage, groupid) {
            var deferred = $q.defer();
            $http.get(url + '?limitfrom=' + (page * perPage) + '&limitnum=' + perPage + '&groupid=' + groupid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };
    }
]);

app.service('cardsSrv', [
    '$http', '$q', 'CONFIG',
    function ($http, $q, config) {
        var url = config.apiUrls.cards;

        this.getPageOfStudentCardsInActivity = function (page, perPage, instanceid) {
            var deferred = $q.defer();
            $http.get(url + '?limitfrom=' + (page * perPage) + '&limitnum=' + perPage + '&instanceid=' + instanceid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };

        this.getPageOfCardsInReviewInActivity = function (page, perPage, instanceid) {
            var deferred = $q.defer();
            $http.get(url + '/review?limitfrom=' + (page * perPage) + '&limitnum=' + perPage + '&instanceid=' + instanceid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };

        this.getPageOfCardsInRepositoryInCourse = function (page, perPage, filters) {
            var deferred = $q.defer();
            $http.get(url + '/repository' +
                    '?limitfrom=' + (page * perPage) +
                    '&limitnum=' + perPage +
                    '&groupid=' + filters.groupid +
                    '&userid=' + filters.userid +
                    '&q=' + encodeURIComponent(filters.q) +
                    '&status=' + filters.status +
                    '&sort=' + encodeURIComponent(filters.sort)
                ).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };

        this.getCard = function (cardid) {
            var deferred = $q.defer();
            $http.get(url + '/' + cardid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };

        this.putCard = function (cardid, card) {
            var deferred = $q.defer();
            $http.put(url + '/' + cardid + '?sesskey=' + config.sesskey, card).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
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
            $http.post(url + '/create?sesskey=' + config.sesskey, data).
                success(function (responseData) {
                    deferred.resolve(responseData);
                }).
                error(function (responseData) {
                    deferred.reject(responseData);
                });
            return deferred.promise;
        };

        this.deleteCard = function (cardid) {
            var deferred = $q.defer();
            $http.delete(url + '/' + cardid + '?sesskey=' + config.sesskey).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
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
    }
]);

app.service('feedbackSrv', [
    '$http', '$q', 'CONFIG',
    function ($http, $q, config) {
        var url = config.apiUrls.feedbacks;

        this.getPageOfFeedback = function (page, perPage, cardid) {
            var deferred = $q.defer();
            $http.get(url + '?limitfrom=' + (page * perPage) + '&limitnum=' + perPage + '&cardid=' + cardid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
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
            var cmid = config.hasOwnProperty('cmid') ? ('&cmid=' + config.cmid) : '';
            $http.post(url + '?sesskey=' + config.sesskey + '&app=' + config.app + cmid, data).
                success(function (responseData) {
                    deferred.resolve(responseData);
                }).
                error(function (responseData) {
                    deferred.reject(responseData);
                });
            return deferred.promise;
        };

        this.putFeedback = function (feedback) {
            var deferred = $q.defer();
            var data = {
                feedback: feedback.feedback
            };
            $http.put(url + '/' + feedback.id + '?sesskey=' + config.sesskey + '&app=' + config.app, data).
                success(function (responseData) {
                    deferred.resolve(responseData);
                }).
                error(function (responseData) {
                    deferred.reject(responseData);
                });
            return deferred.promise;
        };

        this.deleteFeedback = function (feedbackid) {
            var deferred = $q.defer();
            $http.delete(url + '/' + feedbackid + '?sesskey=' + config.sesskey + '&app=' + config.app).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };
    }
]);

app.service('repoFilterSrv', [
    'CONFIG',
    function (config) {
        this.filters = {
            groupid: 0,
            userid: 0,
            q: '',
            status: config.reverseCardStatus.in_repository,
            sort: 'word'
        };
    }
]);

app.service('initialRouteSrv', [
    '$location', 'CONFIG',
    function ($location, config) {
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
    }
]);

app.service('messageSrv', function () {
    this.messages = {};
    this.collect = function () {
        var retval = {};
        angular.copy(this.messages, retval);
        this.messages = {};
        return retval;
    };
});
