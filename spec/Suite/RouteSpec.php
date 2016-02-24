<?php
namespace Lead\Router\Spec\Suite;

use stdClass;
use Lead\Router\RouterException;
use Lead\Router\Router;
use Lead\Router\Route;
use Lead\Net\Http\Cgi\Request;

describe("Route", function() {

    describe("->pattern()", function() {

        it("gets/sets the pattern", function() {

            $route = new Route();
            expect($route->pattern('foo/bar/{id}[/{paths}]*'))->toBe($route);
            expect($route->pattern())->toBe('foo/bar/{id}[/{paths}]*');
            expect($route->regex())->toBe('foo/bar/([^/]+)((?:/[^/]+)*)');
            expect($route->variables())->toBe([
                'id'    => false,
                'paths' => '/{paths}'
            ]);

        });

        it("updates the regex", function() {

            $route = new Route();
            expect($route->pattern('foo/bar/{id}[/{paths}]*'))->toBe($route);
            expect($route->regex())->toBe('foo/bar/([^/]+)((?:/[^/]+)*)');

            expect($route->pattern('foo/baz/{id}[/{paths}]*'))->toBe($route);
            expect($route->regex())->toBe('foo/baz/([^/]+)((?:/[^/]+)*)');

        });

        it("updates the variables", function() {

            $route = new Route();
            expect($route->pattern('foo/bar/{id}[/{paths}]*'))->toBe($route);
            expect($route->variables())->toBe([
                'id'    => false,
                'paths' => '/{paths}'
            ]);

            expect($route->pattern('foo/bar/{baz}[/{paths}]'))->toBe($route);
            expect($route->variables())->toBe([
                'baz'   => false,
                'paths' => false
            ]);

        });

    });

    describe("->scope()", function() {

        it("gets/sets route scope", function() {

            $scope = new stdClass();
            $route = new Route();
            expect($route->scope($scope))->toBe($route);
            expect($route->scope())->toBe($scope);

        });

    });

    describe("->apply()", function() {

        it("applies middlewares", function() {

            $r = new Router();
            $route = $r->bind('/foo/bar', function($route) {
                return 'A';
            })->apply(function($request, $response, $next) {
                return '1' . $next() . '1';
            })->apply(function($request, $response, $next) {
                return '2' . $next() . '2';
            });

            $route = $r->route('foo/bar');
            $actual = $route->dispatch();

            expect($actual)->toBe('21A12');

        });

    });

    describe("->dispatch()", function() {

        it("passes route as argument of the handler function", function() {

            $r = new Router();
            $r->get('foo/{var1}[/{var2}]',
                ['host' => '{subdomain}.{domain}.bar'],
                function($route, $response) {
                    return array_merge([$response], array_values($route->params));
                }
            );

            $response = new stdClass();

            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            $actual = $route->dispatch($response);
            expect($actual)->toBe([$response, 'foo', 'biz', '25', null]);

            $route = $r->route('foo/25/bar', 'GET', 'foo.biz.bar');
            $actual = $route->dispatch($response);
            expect($actual)->toBe([$response, 'foo', 'biz', '25', 'bar']);

        });

        it("throws an exception on non valid routes", function() {

            $closure = function() {
                $r = new Router();
                $r->get('foo', function() {});
                $route = $r->route('bar');
                $route->dispatch();
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/bar`.", 404));

        });

    });

});