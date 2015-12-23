<?php
namespace Lead\Router\Spec\Suite;

use Lead\Router\RouterException;
use Lead\Router\Router;
use Lead\Net\Http\Cgi\Request;

describe("Router", function() {

    beforeEach(function() {

        $this->router = new Router();

    });

    describe("->add()", function() {

        it("adds a named route", function() {

            $r = $this->router;
            $route = $r->add('foo#foo/bar', function () { return 'hello'; });
            expect(isset($r['foo']))->toBe(true);
            expect($r['foo'])->toBe($route);

        });

        it("throws an exception when the handler is not a closure", function() {

            $closure = function() {
                $r = $this->router;
                $r->add('foo', 'substr');
            };

            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure`."));

        });

    });

    describe("->link()", function() {

        it("generates a relative named route link", function() {

            $r = $this->router;
            $r->add('foo#foo/{bar}', function () {});

            $link = $r->link('foo', ['bar' => 'baz']);
            expect($link)->toBe('/foo/baz');

        });

        it("generates a relative named route link with missing optionnal parameters", function() {

            $r = $this->router;
            $r->add('foo#foo[/{bar}]', function () {});

            $link = $r->link('foo');
            expect($link)->toBe('/foo');

            $link = $r->link('foo', ['bar' => 'baz']);
            expect($link)->toBe('/foo/baz');

        });

        it("generates a absolute named route link", function() {

            $r = $this->router;
            $r->basePath('app');

            $r->group(['host' => 'www.example.com'], function($r) {
                $r->add('foo#foo/{bar}', function () {});
            });

            $link = $r->link('foo', ['bar' => 'baz'], ['absolute' => true]);
            expect($link)->toBe('http://www.example.com/app/foo/baz');

        });

        it("generates a nested named route relative link", function() {

            $r = $this->router;
            $r->group('foz#foo', function($r) {
                $r->group('baz#bar', function($r) {
                    $r->add('quz#{var1}', function () {});
                });
            });

            $link = $r->link('foz/baz/quz', ['var1' => 'hello']);
            expect($link)->toBe('/foo/bar/hello');

        });

        it("throws an exception when some required parameters are missing", function() {

            $closure = function() {
                $r = $this->router;
                $r->add('foo#foo/{bar}', function () {});

                var_dump($r->link('foo'));
            };

            expect($closure)->toThrow(new RouterException("Missing parameters `'bar'` for route: `'#/foo/{bar}'`."));

        });

    });

    describe("->dispatch()", function() {

        it("dispatches a simple route", function() {

            $r = $this->router;
            $r->add('foo/bar', function () { return 'hello'; });
            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe('hello');

            $closure = function() use ($r) {
                $r->dispatch('bar/foo', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/bar/foo`.", 404));

        });

        it("dispatches a named route", function() {

            $r = $this->router;
            $r->add('foo#foo/bar', function () { return 'hello'; });
            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe('hello');

            $closure = function() use ($r) {
                $r->dispatch('bar/foo', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/bar/foo`.", 404));

        });

        it("supports route variables", function() {

            $r = $this->router;
            $r->get('foo/{param}', function() { return $this->params; });
            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe(['param' => 'bar']);

            $closure = function() use ($r) {
                $r->dispatch('bar/foo', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/bar/foo`.", 404));

        });

        it("supports constrained route variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', function() { return $this->params; });
            $response = $r->dispatch('foo/25', 'GET');
            expect($response)->toBe(['var1' => '25']);

            $closure = function() use ($r) {
                $r->dispatch('foo/bar', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/foo/bar`.", 404));
        });

        it("supports optional route variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1}]', function() { return $this->params; });

            $response = $r->dispatch('foo', 'GET');
            expect($response)->toBe([]);

            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toBe(['var1' => 'bar']);

        });

        it("supports optional constrained route variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1:\d+}]', function() { return $this->params; });

            $response = $r->dispatch('foo', 'GET');
            expect($response)->toBe([]);

            $response = $r->dispatch('foo/25', 'GET');
            expect($response)->toBe(['var1' => '25']);

            $closure = function() use ($r) {
                $r->dispatch('foo/bar', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/foo/bar`.", 404));

        });

        it("supports routes with optional variables with multiple segments", function() {

            $patterns = [
                '[{var1}[/{var2}]]',
                '/[{var1}[/{var2}]]',
                '[/{var1}[/{var2}]]'
            ];

            $r = $this->router;

            foreach ($patterns as $pattern) {
                $r->get($pattern, function() { return $this->params; });

                $response = $r->dispatch('', 'GET');
                expect($response)->toBe([]);

                $response = $r->dispatch('foo', 'GET');
                expect($response)->toBe(['var1' => 'foo']);

                $response = $r->dispatch('foo/bar', 'GET');
                expect($response)->toBe(['var1' => 'foo', 'var2' => 'bar']);

                $r->clear();

            };

        });

        it("supports host variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function() { return $this->params + ['tld' => 'bar']; });
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function() { return $this->params + ['tld' => 'baz']; });

            $response = $r->dispatch('foo/25', 'GET', 'foo.biz.bar');
            expect($response)->toBe(['domain' => 'biz', 'var1' => '25', 'tld' => 'bar']);

            $response = $r->dispatch('foo/50', 'GET', 'foo.buz.baz');
            expect($response)->toBe(['domain' => 'buz', 'var1' => '50', 'tld' => 'baz']);

        });

        it("supports constrained host variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => '{subdomain:foo}.{domain}.bar'], function() { return $this->params; });
            $response = $r->dispatch('foo/25', 'GET', 'foo.biz.bar');
            expect($response)->toBe([ 'subdomain' => 'foo', 'domain' => 'biz', 'var1' => '25']);

            $closure = function() use ($r) {
                $r->dispatch('foo/bar', 'GET', 'foo.biz.bar');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:foo.biz.bar:GET:/foo/bar`.", 404));

        });

        it("passes route variables to the handler function", function() {

            $r = $this->router;
            $r->get('foo/{var1}[/{var2}]',
                ['host' => '{subdomain}.{domain}.bar'],
                function($subdomain, $domain, $var1, $var2 = 'default') {
                    return [$subdomain, $domain, $var1, $var2];
                }
            );
            $response = $r->dispatch('foo/25', 'GET', 'foo.biz.bar');
            expect($response)->toBe(['foo', 'biz', '25', 'default']);

            $response = $r->dispatch('foo/25/bar', 'GET', 'foo.biz.bar');
            expect($response)->toBe(['foo', 'biz', '25', 'bar']);

        });

        it("supports absolute URL", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function() { return $this->params + ['tld' => 'bar']; });
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function() { return $this->params + ['tld' => 'baz']; });

            $response = $r->dispatch('http://foo.biz.bar/foo/25', 'GET');
            expect($response)->toBe(['domain' => 'biz', 'var1' => '25', 'tld' => 'bar']);

            $response = $r->dispatch('http://foo.buz.baz/foo/50', 'GET');
            expect($response)->toBe(['domain' => 'buz', 'var1' => '50', 'tld' => 'baz']);

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

        it("throws an exception when two routes conflicts together", function() {

            $closure = function() {
                $r = $this->router;
                $r->add('foo/bar', function() {});
                $r->get('foo/bar', function() {});
                $r->dispatch('foo/bar');
            };

            expect($closure)->toThrow(new RouterException("The route `*:*:GET:/foo/bar` conflicts with a previously defined one on `*:*:*:/foo/bar`."));

        });

        it("throws an exception when two routes conflicts together", function() {

            $closure = function() {
                $r = $this->router;
                $r->get('foo/bar', function() {});
                $r->get('foo/bar', function() {});
                $r->dispatch('foo/bar');
            };

            expect($closure)->toThrow(new RouterException("The route `*:*:GET:/foo/bar` conflicts with a previously defined one on `*:*:GET:/foo/bar`."));

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

        it("dispatches a request as an object", function() {

            $r = $this->router;
            $r->add('foo/bar', function () { return $this->request; });
            $request = new Request(['path' =>'foo/bar']);
            $response = $r->dispatch($request, 'GET');
            expect($response)->toBe($request);

        });

        it("dispatches a request as an array", function() {

            $r = $this->router;
            $r->add('foo/bar', function () { return $this->request; });
            $response = $r->dispatch(['path' =>'foo/bar'], 'GET');
            expect($response)->toEqual([
                'scheme' => "*",
                'host'   => "*",
                'method' => "GET",
                'path'   => "/foo/bar"
            ]);

        });

        it("dispatches a request as arguments", function() {

            $r = $this->router;
            $r->add('foo/bar', function () { return $this->request; });
            $response = $r->dispatch('foo/bar', 'GET');
            expect($response)->toEqual([
                'scheme' => "*",
                'host'   => "*",
                'method' => "GET",
                'path'   => "/foo/bar"
            ]);

        });

    });

    describe("->group()", function() {

        it("support nested named route", function() {

            $r = $this->router;
            $r->group('foz#foo', function($r) use (&$route) {
                $r->group('baz#bar', function($r) use (&$route) {
                    $route = $r->add('quz#{var1}', function () {});
                });
            });

            expect(isset($r['foz/baz/quz']))->toBe(true);
            expect($r['foz/baz/quz'])->toBe($route);

        });

        context("with a prefix contraint", function() {

            beforeEach(function() {

                $r = $this->router;
                $r->group('foo', function($r) {
                    $r->add('{var1}', function () { return $this->params; });
                });

            });

            it("dispatches urls matching route's prefix", function() {

                $r = $this->router;
                $response = $r->dispatch('foo/bar', 'GET');
                expect($response)->toBe(['var1'   => 'bar']);

            });

            it("throws an exception when the prefix doesn't match", function() {

                $closure = function() {
                    $r = $this->router;
                    $r->dispatch('bar/foo', 'GET');
                };

                expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/bar/foo`.", 404));

            });

        });

        context("with a host constraint", function() {

            beforeEach(function() {

                $r = $this->router;
                $r->group('foo', ['host' => 'foo.{domain}.bar'], function($r) {
                    $r->group('bar', function($r) {
                        $r->add('{var1}', function () { return $this->params; });
                    });
                });

            });

            it("dispatches urls matching route's host", function() {

                $r = $this->router;
                $response = $r->dispatch('http://foo.hello.bar/foo/bar/baz', 'GET');
                expect($response)->toBe([
                    'domain' => 'hello',
                    'var1'   => 'baz'
                ]);

            });

            it("throws an exception when the host doesn't match", function() {

                $closure = function() {
                    $r = $this->router;
                    $r->dispatch('http://bar.hello.foo/foo/bar/baz', 'GET');
                };

                expect($closure)->toThrow(new RouterException("No route found for `http:bar.hello.foo:GET:/foo/bar/baz`.", 404));

            });

        });

        context("with a scheme constraint", function() {

            beforeEach(function() {

                $r = $this->router;
                $r->group('foo', ['scheme' => 'http'], function($r) {
                    $r->group('bar', function($r) {
                        $r->add('{var1}', function () { return $this->params; });
                    });
                });

            });

            it("dispatches urls matching route's scheme", function() {

                $r = $this->router;
                $response = $r->dispatch('http://domain.com/foo/bar/baz', 'GET');
                expect($response)->toBe([
                    'var1'   => 'baz'
                ]);

            });

            it("throws an exception when the scheme doesn't match", function() {

                $closure = function() {
                    $r = $this->router;
                    $response = $r->dispatch('https://domain.com/foo/bar/baz', 'GET');
                };

                expect($closure)->toThrow(new RouterException("No route found for `https:domain.com:GET:/foo/bar/baz`.", 404));

            });

        });

        it("concats namespace values", function() {

            $r = $this->router;
            $r->group('foo', ['namespace' => 'My'], function($r) {
                $r->group('bar', ['namespace' => 'Name'], function($r) {
                    $r->add('{var1}', ['namespace' => 'Space'], function () { return $this->namespace; });
                });
            });

            $response = $r->dispatch('foo/bar/baz', 'GET');
            expect($response)->toBe('My\Name\Space\\');

        });

        it("throws an exception when the handler is not a closure", function() {

            $closure = function() {
                $r = $this->router;
                $r->group('foo', 'substr');
            };

            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure`."));

        });

    });

    describe("->controller()", function() {

        it("dispatches on controllers", function() {

            $r = $this->router;
            $r->controller('{controller}/{action}[/{args}]', ['namespace' => 'Lead\Router\Spec\Mock']);

            $response = $r->dispatch('test/index');
            expect($response->params)->toBe([
                'controller' => 'test',
                'action'     => 'index'
            ]);
            expect($response->args)->toBe(['test', 'index']);

            $response = $r->dispatch('test/hello/willy');
            expect($response->params)->toBe([
                'controller' => 'test',
                'action'     => 'hello',
                'args'       => 'willy'
            ]);
            expect($response->args)->toBe(['test', 'hello', 'willy']);

            $response = $r->dispatch('test/hello/willy/boy');
            expect($response->params)->toBe([
                'controller' => 'test',
                'action'     => 'hello',
                'args'       => 'willy/boy'
            ]);
            expect($response->args)->toBe(['test', 'hello', 'willy', 'boy']);

        });

    });

});