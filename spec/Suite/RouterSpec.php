<?php
namespace Lead\Router\Spec\Suite;

use Lead\Router\RouterException;
use Lead\Router\Router;

describe("Router", function() {

    beforeEach(function() {

        $this->router = new Router();

    });

    describe("->dispatch()", function() {

        it("dispatches a simple url", function() {

            $r = $this->router;
            $r->route('foo/bar', function () { return 'hello'; });
            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe('hello');

        });

        it("supports route variables", function() {

            $r = $this->router;
            $r->get('foo/{param}', function() { return $this->params(); });
            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe(['param' => 'bar']);

        });

        it("supports constrained route variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', function() { return $this->params(); });
            $response = $r->dispatch('foo/25', 'GET');
            expect($response)->toBe(['var1' => '25']);

            $closure = function() use ($r) {
                $response = $r->dispatch('foo/bar', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/foo/bar`.", 404));
        });

        it("supports optional route variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1}]', function() { return $this->params(); });

            $response = $r->dispatch('foo', 'GET');
            expect($response)->toBe([]);

            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe(['var1' => 'bar']);

        });

        it("supports optional constrained route variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1:\d+}]', function() { return $this->params(); });

            $response = $r->dispatch('foo', 'GET');
            expect($response)->toBe([]);

            $response = $r->dispatch('foo/25', 'GET');
            expect($response)->toBe(['var1' => '25']);

            $closure = function() use ($r) {
                $response = $r->dispatch('foo/bar', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/foo/bar`.", 404));

        });

        it("supports routes with optional variables with multiple segments", function() {

            $r = $this->router;
            $r->get('[/{var1}[/{var2}]]', function() { return $this->params(); });

            $response = $r->dispatch('', 'GET');
            expect($response)->toBe([]);

            $response = $r->dispatch('foo', 'GET');
            expect($response)->toBe(['var1' => 'foo']);

            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe(['var1' => 'foo', 'var2' => 'bar']);

        });

        it("supports routes with optional variables with multiple segments and `'/'` as empty route", function() {

            $r = $this->router;
            $r->get('/[{var1}[/{var2}]]', function() { return $this->params(); });

            $response = $r->dispatch('', 'GET');
            expect($response)->toBe([]);

            $response = $r->dispatch('foo', 'GET');
            expect($response)->toBe(['var1' => 'foo']);

            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe(['var1' => 'foo', 'var2' => 'bar']);

        });

        it("supports domain variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['domain' => 'foo.{domain}.bar'], function() { return $this->params() + ['tld' => 'bar']; });
            $r->get('foo/{var1:\d+}', ['domain' => 'foo.{domain}.baz'], function() { return $this->params() + ['tld' => 'baz']; });

            $response = $r->dispatch('foo/25', 'GET', 'foo.biz.bar');
            expect($response)->toBe(['domain' => 'biz', 'var1' => '25', 'tld' => 'bar']);

            $response = $r->dispatch('foo/50', 'GET', 'foo.buz.baz');
            expect($response)->toBe(['domain' => 'buz', 'var1' => '50', 'tld' => 'baz']);

        });

        it("supports constrained domain variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['domain' => '{subdomain:foo}.{domain}.bar'], function() { return $this->params(); });
            $response = $r->dispatch('foo/25', 'GET', 'foo.biz.bar');
            expect($response)->toBe([ 'subdomain' => 'foo', 'domain' => 'biz', 'var1' => '25']);

            $closure = function() use ($r) {
                $response = $r->dispatch('foo/bar', 'GET', 'foo.biz.bar');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:foo.biz.bar:GET:/foo/bar`.", 404));

        });

        it("passes route variables to the handler function", function() {

            $r = $this->router;
            $r->get('foo/{var1}[/{var2}]',
                ['domain' => '{subdomain}.{domain}.bar'],
                function($subdomain, $domain, $var1, $var2 = 'default') {
                    return [$subdomain, $domain, $var1, $var2];
                }
            );
            $response = $r->dispatch('foo/25', 'GET', 'foo.biz.bar');
            expect($response)->toBe(['foo', 'biz', '25', 'default']);

            $response = $r->dispatch('foo/25/bar', 'GET', 'foo.biz.bar');
            expect($response)->toBe(['foo', 'biz', '25', 'bar']);

        });

        it("supports RESTful routes", function() {

            $r = $this->router;
            $r->get('foo/bar', function () { return 'GET'; });
            $r->head('foo/bar', function () { return 'HEAD'; });
            $r->post('foo/bar', function () { return 'POST'; });
            $r->put('foo/bar', function () { return 'PUT'; });
            $r->patch('foo/bar', function () { return 'PATCH'; });
            $r->delete('foo/bar', function () { return 'DELETE'; });
            $r->options('foo/bar', function () { return 'OPTIONS'; });

            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe('GET');

            $response = $r->dispatch('foo/bar', 'HEAD');
            expect($response)->toBe('HEAD');

            $response = $r->dispatch('foo/bar', 'POST');
            expect($response)->toBe('POST');

            $response = $r->dispatch('foo/bar', 'PUT');
            expect($response)->toBe('PUT');

            $response = $r->dispatch('foo/bar', 'PATCH');
            expect($response)->toBe('PATCH');

            $response = $r->dispatch('foo/bar', 'DELETE');
            expect($response)->toBe('DELETE');

            $response = $r->dispatch('foo/bar', 'OPTIONS');
            expect($response)->toBe('OPTIONS');

        });

        it("dispatches HEAD requests on matching GET routes if the HEAD routes are missing", function() {

            $r = $this->router;
            $r->get('foo/bar', function () { return 'GET'; });

            $response = $r->dispatch('foo/bar', 'HEAD');
            expect($response)->toBe('GET');

        });

        it("dispatches HEAD requests on matching GET routes if the HEAD routes are missing", function() {

            $r = $this->router;

            $r->head('foo/bar', function () { return 'HEAD'; });
            $r->get('foo/bar', function () { return 'GET'; });

            $response = $r->dispatch('foo/bar', 'HEAD');
            expect($response)->toBe('HEAD');

        });

        it("throws an exception when two routes conflicts together", function() {

            $closure = function() {
                $r = $this->router;
                $r->route('foo/bar', function() {});
                $r->get('foo/bar', function() {});
                $r->dispatch('foo/bar');
            };

            expect($closure)->toThrow(new RouterException("The route `*:*:GET:/foo/bar` conflicts with a previously defined one on `*:*:*:/foo/bar`."));

        });

    });

});