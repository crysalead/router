<?php
namespace Lead\Router\Spec\Suite;

use stdClass;
use Lead\Router\RouterException;
use Lead\Router\Router;
use Lead\Router\Route;
use Lead\Net\Http\Cgi\Request;

describe("Route", function() {

    beforeEach(function() {

        $this->router = new Router();

    });

    describe("->scope()", function() {

        it("gets/sets route scope", function() {

            $scope = new stdClass();
            $route = new Route();
            expect($route->scope($scope))->toBe($route);
            expect($route->scope())->toBe($scope);

        });

    });

    describe("->append()", function() {

        it("appends a new pattern to an existing route", function() {

            $r = $this->router;
            $route = $r->get('{foo}/{bar}/action', function($route) { return $route->params; });
            $route->append('{foz}/{baz}[/{quz}]');

            $response = new stdClass();

            $route = $r->route('foo/bar/action');
            $actual = $route->dispatch($response);

            expect($actual)->toBe(['foo' => 'foo', 'bar' => 'bar']);

            $route = $r->route('foo/bar/baz');
            $actual = $route->dispatch($response);

            expect($actual)->toBe(['foz' => 'foo', 'baz' => 'bar', 'quz' => 'baz']);

        });

    });

    describe("->prepend()", function() {

        it("prepends a new pattern to an existing route", function() {

            $r = $this->router;
            $route = $r->get('{foo}/{bar}/action', function($route) { return $route->params; });
            $route->prepend('{foz}/{baz}[/{quz}]');

            $response = new stdClass();

            $route = $r->route('foo/bar/action');
            $actual = $route->dispatch($response);

            expect($actual)->toBe(['foz' => 'foo', 'baz' => 'bar', 'quz' => 'action']);

        });

    });

    describe("->apply()", function() {

        it("applies middlewares", function() {

            $r = $this->router;
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

            $r = $this->router;
            $r->get('foo/{var1}[/{var2}]',
                ['host' => '{subdomain}.{domain}.bar'],
                function($route, $response) {
                    return array_merge([$response], array_values($route->params));
                }
            );

            $response = new stdClass();

            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            $actual = $route->dispatch($response);
            expect($actual)->toBe([$response, 'foo', 'biz', '25']);

            $route = $r->route('foo/25/bar', 'GET', 'foo.biz.bar');
            $actual = $route->dispatch($response);
            expect($actual)->toBe([$response, 'foo', 'biz', '25', 'bar']);

        });

        it("throws an exception on non valid routes", function() {

            $closure = function() {
                $r = $this->router;
                $r->get('foo', function() {});
                $route = $r->route('bar');
                $route->dispatch();
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/bar`.", 404));

        });

    });

});