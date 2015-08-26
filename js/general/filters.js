'use strict';

var app = angular.module('general.filters', []);

app.filter('substring', function () {
    return function (str, start, end) {
        return str.substring(start, end);
    };
});

app.filter('pipes', [
    '$sce',
    function ($sce) {
        return function (str) {
            if (!str) {
                return '';
            }
            return $sce.trustAsHtml(str.replace(/\s*,\s*/g, '&nbsp;<strong>|</strong>&nbsp;'));
        };
    }
]);
