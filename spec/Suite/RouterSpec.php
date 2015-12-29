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
                $r->link('foo');
            };

            expect($closure)->toThrow(new RouterException("Missing parameters `'bar'` for route: `'foo#/foo/{bar}'`."));

        });

    });

    describe("->route()", function() {

        it("routes on a simple route", function() {

            $r = $this->router;
            $r->add('foo/bar', function () {});

            $route = $r->route('foo/bar', 'GET');
            expect($route->request)->toBe([
                'path'   => '/foo/bar',
                'method' => 'GET',
                'host'   => '*',
                'scheme' => '*'
            ]);

            $closure = function() use ($r) {
                $r->route('foo/baz', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/foo/baz`.", 404));

        });

        it("routes on a named route", function() {

            $r = $this->router;
            $r->add('foo#foo/bar', function () {});

            $route = $r->route('foo/bar', 'GET');
            expect($route->name)->toBe('foo');

            $closure = function() use ($r) {
                $r->route('foo/baz', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/foo/baz`.", 404));

        });

        it("supports route variables", function() {

            $r = $this->router;
            $r->get('foo/{param}', function() {});

            $route = $r->route('foo/bar', 'GET');
            expect($route->params)->toBe(['param' => 'bar']);

            $closure = function() use ($r) {
                $r->route('bar/foo', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/bar/foo`.", 404));

        });

        it("supports constrained route variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', function() {});

            $route = $r->route('foo/25', 'GET');
            expect($route->params)->toBe(['var1' => '25']);

            $closure = function() use ($r) {
                 $r->route('foo/bar', 'GET');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/foo/bar`.", 404));
        });

        it("supports optional route variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1}]', function() {});

            $route = $r->route('foo', 'GET');
            expect($route->params)->toBe([]);

            $route = $r->route('foo/bar', 'GET');
            expect($route->params)->toBe(['var1' => 'bar']);

        });

        it("supports optional constrained route variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1:\d+}]', function() {});

            $route = $r->route('foo', 'GET');
            expect($route->params)->toBe([]);

            $route = $r->route('foo/25', 'GET');
            expect($route->params)->toBe(['var1' => '25']);

            $closure = function() use ($r) {
                $r->route('foo/bar', 'GET');
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
                $r->get($pattern, function() {});

                $route = $r->route('', 'GET');
                expect($route->params)->toBe([]);

                $route = $r->route('foo', 'GET');
                expect($route->params)->toBe(['var1' => 'foo']);

                $route = $r->route('foo/bar', 'GET');
                expect($route->params)->toBe(['var1' => 'foo', 'var2' => 'bar']);

                $r->clear();

            };

        });

        it("supports host variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function() {});
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function() {});

            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            expect($route->host)->toBe('foo.{domain}.bar');
            expect($route->params)->toBe(['domain' => 'biz', 'var1' => '25']);

            $route = $r->route('foo/50', 'GET', 'foo.buz.baz');
            expect($route->host)->toBe('foo.{domain}.baz');
            expect($route->params)->toBe(['domain' => 'buz', 'var1' => '50']);

        });

        it("supports constrained host variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => '{subdomain:foo}.{domain}.bar'], function() {});

            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            expect($route->params)->toBe([ 'subdomain' => 'foo', 'domain' => 'biz', 'var1' => '25']);

            $closure = function() use ($r) {
                $r->route('foo/bar', 'GET', 'foo.biz.bar');
            };

            expect($closure)->toThrow(new RouterException("No route found for `*:foo.biz.bar:GET:/foo/bar`.", 404));

        });

        it("supports absolute URL", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function() {});
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function() {});

            $route = $r->route('http://foo.biz.bar/foo/25', 'GET');
            expect($route->host)->toBe('foo.{domain}.bar');
            expect($route->params)->toBe(['domain' => 'biz', 'var1' => '25']);

            $route = $r->route('http://foo.buz.baz/foo/50', 'GET');
            expect($route->host)->toBe('foo.{domain}.baz');
            expect($route->params)->toBe(['domain' => 'buz', 'var1' => '50']);

        });

        it("supports RESTful routes", function() {

            $r = $this->router;
            $r->get('foo/bar', function () {});
            $r->head('foo/bar', function () {});
            $r->post('foo/bar', function () {});
            $r->put('foo/bar', function () {});
            $r->patch('foo/bar', function () {});
            $r->delete('foo/bar', function () {});
            $r->options('foo/bar', function () {});

            $route = $r->route('foo/bar', 'GET');
            expect($route->method)->toBe('GET');

            $route = $r->route('foo/bar', 'HEAD');
            expect($route->method)->toBe('HEAD');

            $route = $r->route('foo/bar', 'POST');
            expect($route->method)->toBe('POST');

            $route = $r->route('foo/bar', 'PUT');
            expect($route->method)->toBe('PUT');

            $route = $r->route('foo/bar', 'PATCH');
            expect($route->method)->toBe('PATCH');

            $route = $r->route('foo/bar', 'DELETE');
            expect($route->method)->toBe('DELETE');

            $route = $r->route('foo/bar', 'OPTIONS');
            expect($route->method)->toBe('OPTIONS');

        });

        it("throws an exception when two routes conflicts together", function() {

            $closure = function() {
                $r = $this->router;
                $r->add('foo/bar', function() {});
                $r->get('foo/bar', function() {});
                $r->route('foo/bar');
            };

            expect($closure)->toThrow(new RouterException("The route `*:*:GET:/foo/bar` conflicts with a previously defined one on `*:*:*:/foo/bar`."));

        });

        it("throws an exception when two routes conflicts together", function() {

            $closure = function() {
                $r = $this->router;
                $r->get('foo/bar', function() {});
                $r->get('foo/bar', function() {});
                $r->route('foo/bar');
            };

            expect($closure)->toThrow(new RouterException("The route `*:*:GET:/foo/bar` conflicts with a previously defined one on `*:*:GET:/foo/bar`."));

        });

        it("dispatches HEAD requests on matching GET routes if the HEAD routes are missing", function() {

            $r = $this->router;
            $r->get('foo/bar', function () { return 'GET'; });

            $route = $r->route('foo/bar', 'HEAD');
            expect($route->method)->toBe('GET');

        });

        it("dispatches HEAD requests on matching GET routes if the HEAD routes are missing", function() {

            $r = $this->router;

            $r->head('foo/bar', function () { return 'HEAD'; });
            $r->get('foo/bar', function () { return 'GET'; });

            $route = $r->route('foo/bar', 'HEAD');
            expect($route->method)->toBe('HEAD');

        });

        it("supports requests as a list of arguments", function() {

            $r = $this->router;
            $r->add('foo/bar', function () {});

            $route = $r->route('foo/bar', 'GET');
            expect($route->request)->toEqual([
                'scheme' => "*",
                'host'   => "*",
                'method' => "GET",
                'path'   => "/foo/bar"
            ]);

        });

        it("supports requests as an object", function() {

            $r = $this->router;
            $r->add('foo/bar', function () {});
            $request = new Request(['path' =>'foo/bar']);

            $route = $r->route($request, 'GET');
            expect($route->request)->toBe($request);

        });

        it("supports requests as an array", function() {

            $r = $this->router;
            $r->add('foo/bar', function () {});

            $route = $r->route(['path' =>'foo/bar'], 'GET');
            expect($route->request)->toEqual([
                'scheme' => "*",
                'host'   => "*",
                'method' => "GET",
                'path'   => "/foo/bar"
            ]);

        });

    });

    describe("->group()", function() {

        it("supports nested named route", function() {

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
                    $r->add('{var1}', function () {});
                });

            });

            it("respects url's prefix constraint", function() {

                $r = $this->router;
                $route = $r->route('foo/bar');
                expect($route->params)->toBe(['var1'   => 'bar']);

            });

            it("throws an exception when the prefix doesn't match", function() {

                $closure = function() {
                    $r = $this->router;
                    $route = $r->route('bar/foo', 'GET');
                };

                expect($closure)->toThrow(new RouterException("No route found for `*:*:GET:/bar/foo`.", 404));

            });

        });

        context("with a host constraint", function() {

            beforeEach(function() {

                $r = $this->router;
                $r->group('foo', ['host' => 'foo.{domain}.bar'], function($r) {
                    $r->group('bar', function($r) {
                        $r->add('{var1}', function () {});
                    });
                });

            });

            it("respects url's host constraint", function() {

                $r = $this->router;
                $route = $r->route('http://foo.hello.bar/foo/bar/baz', 'GET');
                expect($route->params)->toBe([
                    'domain' => 'hello',
                    'var1'   => 'baz'
                ]);

            });

            it("throws an exception when the host doesn't match", function() {

                $closure = function() {
                    $r = $this->router;
                    $route = $r->route('http://bar.hello.foo/foo/bar/baz', 'GET');
                };

                expect($closure)->toThrow(new RouterException("No route found for `http:bar.hello.foo:GET:/foo/bar/baz`.", 404));

            });

        });

        context("with a scheme constraint", function() {

            beforeEach(function() {

                $r = $this->router;
                $r->group('foo', ['scheme' => 'http'], function($r) {
                    $r->group('bar', function($r) {
                        $r->add('{var1}', function () {});
                    });
                });

            });

            it("respects url's scheme constraint", function() {

                $r = $this->router;
                $route = $r->route('http://domain.com/foo/bar/baz', 'GET');
                expect($route->params)->toBe([
                    'var1'   => 'baz'
                ]);

            });

            it("throws an exception when the scheme doesn't match", function() {

                $closure = function() {
                    $r = $this->router;
                    $route = $r->route('https://domain.com/foo/bar/baz', 'GET');
                };

                expect($closure)->toThrow(new RouterException("No route found for `https:domain.com:GET:/foo/bar/baz`.", 404));

            });

        });

        it("concats namespace values", function() {

            $r = $this->router;
            $r->group('foo', ['namespace' => 'My'], function($r) {
                $r->group('bar', ['namespace' => 'Name'], function($r) {
                    $r->add('{var1}', ['namespace' => 'Space'], function () {});
                });
            });

            $route = $r->route('foo/bar/baz', 'GET');
            expect($route->namespace)->toBe('My\Name\Space\\');

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

        it("builds controller's routes", function() {

            $r = $this->router;
            $r->controller('{controller}/{action}[/{args}]', ['namespace' => 'Lead\Router\Spec\Mock']);

            $route = $r->route('test/index');
            expect($route->params)->toBe([
                'controller' => 'test',
                'action'     => 'index'
            ]);
            expect($route->args)->toBe(['test', 'index']);

            $route = $r->route('test/hello/willy');
            expect($route->params)->toBe([
                'controller' => 'test',
                'action'     => 'hello',
                'args'       => 'willy'
            ]);
            expect($route->args)->toBe(['test', 'hello', 'willy']);

            $route = $r->route('test/hello/willy/boy');
            expect($route->params)->toBe([
                'controller' => 'test',
                'action'     => 'hello',
                'args'       => 'willy/boy'
            ]);
            expect($route->args)->toBe(['test', 'hello', 'willy', 'boy']);

        });

    });

    describe("->strategy()", function() {

        it("sets a strategy", function() {

            $r = $this->router;

            $mystrategy = function() {
                $this->add('foo/bar', function() {
                    return 'Hello World!';
                });
            };

            $r->strategy('mystrategy', $mystrategy);
            expect($r->strategy('mystrategy'))->toBe($mystrategy);

            $r->mystrategy();
            $route = $r->route('foo/bar');
            expect($route->pattern)->toBe('/foo/bar');

        });

        it("unsets a strategy", function() {

            $r = $this->router;

            $mystrategy = function() {};

            $r->strategy('mystrategy', $mystrategy);
            expect($r->strategy('mystrategy'))->toBe($mystrategy);

            $r->strategy('mystrategy', false);
            expect($r->strategy('mystrategy'))->toBe(null);

        });

        it("throws an exception when the handler is not a closure", function() {

            $closure = function() {
                $r = $this->router;
                $r->strategy('mystrategy', "substr");
            };

            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure`."));

        });

    });

});