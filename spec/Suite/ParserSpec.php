<?php
namespace Lead\Router\Spec\Suite;

use Lead\Router\RouterException;
use Lead\Router\Parser;

describe("Parser", function() {

    describe("::parse()", function() {

        it("parses an empty url", function() {

            $result = Parser::parse('');
            expect($result)->toBe([['', []]]);

        });

        it("parses a static url", function() {

            $result = Parser::parse('/test');
            expect($result)->toBe([['/test', []]]);

        });

        it("parses an url with a variable", function() {

            $result = Parser::parse('/test/{param}');
            expect($result)->toBe([['/test/([^/]+)', ['param' => 'param']]]);

        });

        it("parses an url with several variables", function() {

            $result = Parser::parse('/test/{param1}/test2/{param2}');
            expect($result)->toBe([['/test/([^/]+)/test2/([^/]+)', ['param1' => 'param1', 'param2' => 'param2']]]);

        });

        it("parses an url with a variable with a custom regex", function() {

            $result = Parser::parse('/test/{param:\d+}');
            expect($result)->toBe([['/test/(\d+)', ['param' => 'param']]]);

            $result = Parser::parse('/test/{ param : \d{1,9} }');
            expect($result)->toBe([['/test/(\d{1,9})', ['param' => 'param']]]);

        });

        it("parses an url with an optional segment", function() {

            $result = Parser::parse('/test[opt]');
            expect($result)->toBe([
                ['/test', []],
                ['/testopt', []]
            ]);

        });

        it("parses an optional segment", function() {

            $result = Parser::parse('[test]');
            expect($result)->toBe([
                ['', []],
                ['test', []]
            ]);

        });

        it("parses an url with an optional variable", function() {

            $result = Parser::parse('/test[/{param}]');
            expect($result)->toBe([
                ['/test', []],
                ['/test/([^/]+)', ['param' => 'param']]
            ]);

        });

        it("parses an url with a variable and an optional segment", function() {

            $result = Parser::parse('/{param}[opt]');
            expect($result)->toBe([
                ['/([^/]+)', ['param' => 'param']],
                ['/([^/]+)opt', ['param' => 'param']]
            ]);

        });

        it("parses an complex url", function() {

            $result = Parser::parse('/test[/{name}[/{id:[0-9]+}]]');
            expect($result)->toBe([
                ['/test', []],
                ['/test/([^/]+)', ['name' => 'name']],
                ['/test/([^/]+)/([0-9]+)', ['name' => 'name', 'id' => 'id']]
            ]);

        });

        it("throws an exception when there's a missing square bracket", function() {

            $closure = function() {
                Parser::parse('/test[opt');
            };

            expect($closure)->toThrow(new RouterException("Number of opening '[' and closing ']' does not match."));

            $closure = function() {
                Parser::parse('/test[opt[opt2]');
            };

            expect($closure)->toThrow(new RouterException("Number of opening '[' and closing ']' does not match."));

            $closure = function() {
                Parser::parse('/testopt]');
            };

            expect($closure)->toThrow(new RouterException("Number of opening '[' and closing ']' does not match."));

        });

        it("throws an exception on empty optional segments", function() {

            $closure = function() {
                Parser::parse('/test[]');
            };

            expect($closure)->toThrow(new RouterException("Empty optional part."));

            $closure = function() {
                Parser::parse('/test[[opt]]');
            };

            expect($closure)->toThrow(new RouterException("Empty optional part."));

            $closure = function() {
                Parser::parse('[[test]]');
            };

            expect($closure)->toThrow(new RouterException("Empty optional part."));

        });

        it("throws an exception when optional segments are present inside a route definition", function() {

            $closure = function() {
                Parser::parse('/test[/opt]/required');
            };

            expect($closure)->toThrow(new RouterException("Optional segments can only occur at the end of a route."));

        });


    });

});