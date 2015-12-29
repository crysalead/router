<?php
namespace Lead\Router\Spec\Suite;

use Lead\Router\RouterException;
use Lead\Router\Router;
use Lead\Net\Http\Cgi\Request;

describe("Route", function() {

    beforeEach(function() {

        $this->router = new Router();

    });

    describe("->dispatch()", function() {

        it("passes route variables as arguments of the handler function", function() {

            $r = $this->router;
            $r->get('foo/{var1}[/{var2}]',
                ['host' => '{subdomain}.{domain}.bar'],
                function($subdomain, $domain, $var1, $var2 = 'default') {
                    return [$subdomain, $domain, $var1, $var2];
                }
            );

            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            $response = $route->dispatch();
            expect($response)->toBe(['foo', 'biz', '25', 'default']);

            $route = $r->route('foo/25/bar', 'GET', 'foo.biz.bar');
            $response = $route->dispatch();
            expect($response)->toBe(['foo', 'biz', '25', 'bar']);

        });

    });

});