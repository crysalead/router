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