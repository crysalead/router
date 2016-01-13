<?php
namespace Lead\Router\Spec\Suite;

use stdClass;
use Lead\Router\RouterException;
use Lead\Router\Router;
use Lead\Net\Http\Cgi\Request;

describe("Route", function() {

    beforeEach(function() {

        $this->router = new Router();

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

        it("applies middlewares from top to bottom", function() {

            $r = $this->router;
            $route = $r->add('/foo/bar', function($route) {
                return 'C';
            })->apply(function($request, $response, $next) {
                return 'A' . $next();
            })->apply(function($request, $response, $next) {
                return 'B' . $next();
            });

            $route = $r->route('foo/bar');
            $actual = $route->dispatch();

            expect($actual)->toBe('ABC');

        });

        it("applies middlewares from bottom to top", function() {

            $r = $this->router;
            $route = $r->add('/foo/bar', function($route) {
                return 'C';
            })->apply(function($request, $response, $next) {
                return $next() . 'A';
            })->apply(function($request, $response, $next) {
                return $next() . 'B';
            });

            $route = $r->route('foo/bar');
            $actual = $route->dispatch();

            expect($actual)->toBe('CBA');

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

    });

});