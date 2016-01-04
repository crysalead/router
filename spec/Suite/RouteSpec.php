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

            $routing = $r->route('foo/bar/action');
            $actual = $routing->route()->dispatch($response);

            expect($actual)->toBe(['foo' => 'foo', 'bar' => 'bar']);

            $routing = $r->route('foo/bar/baz');
            $actual = $routing->route()->dispatch($response);

            expect($actual)->toBe(['foz' => 'foo', 'baz' => 'bar', 'quz' => 'baz']);

        });

    });

    describe("->prepend()", function() {

        it("prepends a new pattern to an existing route", function() {

            $r = $this->router;
            $route = $r->get('{foo}/{bar}/action', function($route) { return $route->params; });
            $route->prepend('{foz}/{baz}[/{quz}]');

            $response = new stdClass();

            $routing = $r->route('foo/bar/action');
            $actual = $routing->route()->dispatch($response);

            expect($actual)->toBe(['foz' => 'foo', 'baz' => 'bar', 'quz' => 'action']);

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

            $routing = $r->route('foo/25', 'GET', 'foo.biz.bar');
            $actual = $routing->route()->dispatch($response);
            expect($actual)->toBe([$response, 'foo', 'biz', '25']);

            $routing = $r->route('foo/25/bar', 'GET', 'foo.biz.bar');
            $actual = $routing->route()->dispatch($response);
            expect($actual)->toBe([$response, 'foo', 'biz', '25', 'bar']);

        });

    });

});