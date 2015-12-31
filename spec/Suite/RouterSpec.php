<?php
namespace Lead\Router\Spec\Suite;

use Lead\Router\RouterException;
use Lead\Router\Router;
use Lead\Router\Routing;
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

            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method."));

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

            $routing = $r->route('foo/bar', 'GET');
            $route = $routing->route();
            expect($route->request)->toBe([
                'path'   => '/foo/bar',
                'method' => 'GET',
                'host'   => '*',
                'scheme' => '*'
            ]);

            $routing = $r->route('foo/baz', 'GET');
            expect($routing->error())->toBe(Routing::NOT_FOUND);
            expect($routing->message())->toBe("No route found for `*:*:GET:/foo/baz`.");
            expect($routing->route())->toBe(null);

        });

        it("routes on a named route", function() {

            $r = $this->router;
            $r->add('foo#foo/bar', function () {});

            $routing = $r->route('foo/bar', 'GET');
            $route = $routing->route();
            expect($route->name)->toBe('foo');

            $routing = $r->route('foo/baz', 'GET');
            expect($routing->error())->toBe(Routing::NOT_FOUND);
            expect($routing->message())->toBe("No route found for `*:*:GET:/foo/baz`.");
            expect($routing->route())->toBe(null);

        });

        it("supports route variables", function() {

            $r = $this->router;
            $r->get('foo/{param}', function() {});

            $routing = $r->route('foo/bar', 'GET');
            $route = $routing->route();
            expect($route->params)->toBe(['param' => 'bar']);

            $routing = $r->route('bar/foo', 'GET');
            expect($routing->error())->toBe(Routing::NOT_FOUND);
            expect($routing->message())->toBe("No route found for `*:*:GET:/bar/foo`.");
            expect($routing->route())->toBe(null);

        });

        it("supports constrained route variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', function() {});

            $routing = $r->route('foo/25', 'GET');
            $route = $routing->route();
            expect($route->params)->toBe(['var1' => '25']);

            $routing = $r->route('foo/bar', 'GET');
            expect($routing->error())->toBe(Routing::NOT_FOUND);
            expect($routing->message())->toBe("No route found for `*:*:GET:/foo/bar`.");
            expect($routing->route())->toBe(null);

        });

        it("supports optional route variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1}]', function() {});

            $routing = $r->route('foo', 'GET');
            $route = $routing->route();
            expect($route->params)->toBe([]);

            $routing = $r->route('foo/bar', 'GET');
            $route = $routing->route();
            expect($route->params)->toBe(['var1' => 'bar']);

        });

        it("supports optional constrained route variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1:\d+}]', function() {});

            $routing = $r->route('foo', 'GET');
            $route = $routing->route();
            expect($route->params)->toBe([]);

            $routing = $r->route('foo/25', 'GET');
            $route = $routing->route();
            expect($route->params)->toBe(['var1' => '25']);

            $routing = $r->route('foo/baz', 'GET');
            expect($routing->error())->toBe(Routing::NOT_FOUND);
            expect($routing->message())->toBe("No route found for `*:*:GET:/foo/baz`.");
            expect($routing->route())->toBe(null);

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

                $routing = $r->route('', 'GET');
                $route = $routing->route();
                expect($route->params)->toBe([]);

                $routing = $r->route('foo', 'GET');
                $route = $routing->route();
                expect($route->params)->toBe(['var1' => 'foo']);

                $routing = $r->route('foo/bar', 'GET');
                $route = $routing->route();
                expect($route->params)->toBe(['var1' => 'foo', 'var2' => 'bar']);

                $r->clear();

            };

        });

        it("supports host variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function() {});
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function() {});

            $routing = $r->route('foo/25', 'GET', 'foo.biz.bar');
            $route = $routing->route();
            expect($route->host)->toBe('foo.{domain}.bar');
            expect($route->params)->toBe(['domain' => 'biz', 'var1' => '25']);

            $routing = $r->route('foo/50', 'GET', 'foo.buz.baz');
            $route = $routing->route();
            expect($route->host)->toBe('foo.{domain}.baz');
            expect($route->params)->toBe(['domain' => 'buz', 'var1' => '50']);

        });

        it("supports constrained host variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => '{subdomain:foo}.{domain}.bar'], function() {});

            $routing = $r->route('foo/25', 'GET', 'foo.biz.bar');
            $route = $routing->route();
            expect($route->params)->toBe([ 'subdomain' => 'foo', 'domain' => 'biz', 'var1' => '25']);

            $routing = $r->route('foo/bar', 'GET', 'foo.biz.bar');
            expect($routing->error())->toBe(Routing::NOT_FOUND);
            expect($routing->message())->toBe("No route found for `*:foo.biz.bar:GET:/foo/bar`.");
            expect($routing->route())->toBe(null);

        });

        it("supports absolute URL", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function() {});
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function() {});

            $routing = $r->route('http://foo.biz.bar/foo/25', 'GET');
            $route = $routing->route();
            expect($route->host)->toBe('foo.{domain}.bar');
            expect($route->params)->toBe(['domain' => 'biz', 'var1' => '25']);

            $routing = $r->route('http://foo.buz.baz/foo/50', 'GET');
            $route = $routing->route();
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

            $routing = $r->route('foo/bar', 'GET');
            $route = $routing->route();
            expect($route->method)->toBe('GET');

            $routing = $r->route('foo/bar', 'HEAD');
            $route = $routing->route();
            expect($route->method)->toBe('HEAD');

            $routing = $r->route('foo/bar', 'POST');
            $route = $routing->route();
            expect($route->method)->toBe('POST');

            $routing = $r->route('foo/bar', 'PUT');
            $route = $routing->route();
            expect($route->method)->toBe('PUT');

            $routing = $r->route('foo/bar', 'PATCH');
            $route = $routing->route();
            expect($route->method)->toBe('PATCH');

            $routing = $r->route('foo/bar', 'DELETE');
            $route = $routing->route();
            expect($route->method)->toBe('DELETE');

            $routing = $r->route('foo/bar', 'OPTIONS');
            $route = $routing->route();
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

            $routing = $r->route('foo/bar', 'HEAD');
            $route = $routing->route();
            expect($route->method)->toBe('GET');

        });

        it("dispatches HEAD requests on matching GET routes if the HEAD routes are missing", function() {

            $r = $this->router;

            $r->head('foo/bar', function () { return 'HEAD'; });
            $r->get('foo/bar', function () { return 'GET'; });

            $routing = $r->route('foo/bar', 'HEAD');
            $route = $routing->route();
            expect($route->method)->toBe('HEAD');

        });

        it("supports requests as a list of arguments", function() {

            $r = $this->router;
            $r->add('foo/bar', function () {});

            $routing = $r->route('foo/bar', 'GET');
            $route = $routing->route();
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

            $routing = $r->route($request, 'GET');
            $route = $routing->route();
            expect($route->request)->toBe($request);

        });

        it("supports requests as an array", function() {

            $r = $this->router;
            $r->add('foo/bar', function () {});

            $routing = $r->route(['path' =>'foo/bar'], 'GET');
            $route = $routing->route();
            expect($route->request)->toEqual([
                'scheme' => "*",
                'host'   => "*",
                'method' => "GET",
                'path'   => "/foo/bar"
            ]);

        });

        it("routes on a named route", function() {

            $r = $this->router;
            $r->post('foo/bar', function () {});

            $routing = $r->route('foo/bar', 'GET');
            expect($routing->error())->toBe(Routing::METHOD_NOT_ALLOWED);
            expect($routing->message())->toBe("Method `GET` Not Allowed for `*:*:/foo/bar`.");
            expect($routing->route())->toBe(null);

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
                $routing = $r->route('foo/bar');
                $route = $routing->route();
                expect($route->params)->toBe(['var1'   => 'bar']);

            });

            it("bails out when the prefix doesn't match", function() {

                $r = $this->router;
                $routing = $r->route('bar/foo', 'GET');
                expect($routing->error())->toBe(Routing::NOT_FOUND);
                expect($routing->message())->toBe("No route found for `*:*:GET:/bar/foo`.");
                expect($routing->route())->toBe(null);

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
                $routing = $r->route('http://foo.hello.bar/foo/bar/baz', 'GET');
                $route = $routing->route();
                expect($route->params)->toBe([
                    'domain' => 'hello',
                    'var1'   => 'baz'
                ]);

            });

            it("bails out when the host doesn't match", function() {

                $r = $this->router;
                $routing = $r->route('http://bar.hello.foo/foo/bar/baz', 'GET');
                expect($routing->error())->toBe(Routing::NOT_FOUND);
                expect($routing->message())->toBe("No route found for `http:bar.hello.foo:GET:/foo/bar/baz`.");
                expect($routing->route())->toBe(null);

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
                $routing = $r->route('http://domain.com/foo/bar/baz', 'GET');
                $route = $routing->route();
                expect($route->params)->toBe([
                    'var1'   => 'baz'
                ]);

            });

            it("bails out when the scheme doesn't match", function() {

                $r = $this->router;
                $routing = $r->route('https://domain.com/foo/bar/baz', 'GET');
                expect($routing->error())->toBe(Routing::NOT_FOUND);
                expect($routing->message())->toBe("No route found for `https:domain.com:GET:/foo/bar/baz`.");
                expect($routing->route())->toBe(null);

            });

        });

        it("concats namespace values", function() {

            $r = $this->router;
            $r->group('foo', ['namespace' => 'My'], function($r) {
                $r->group('bar', ['namespace' => 'Name'], function($r) {
                    $r->add('{var1}', ['namespace' => 'Space'], function () {});
                });
            });

            $routing = $r->route('foo/bar/baz', 'GET');
            $route = $routing->route();
            expect($route->namespace)->toBe('My\Name\Space\\');

        });

        it("throws an exception when the handler is not a closure", function() {

            $closure = function() {
                $r = $this->router;
                $r->group('foo', 'substr');
            };

            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method."));

        });

    });

    describe("->strategy()", function() {

        it("sets a strategy", function() {

            $r = $this->router;

            $mystrategy = function($router) {
                $router->add('foo/bar', function() {
                    return 'Hello World!';
                });
            };

            $r->strategy('mystrategy', $mystrategy);
            expect($r->strategy('mystrategy'))->toBe($mystrategy);

            $r->mystrategy();
            $routing = $r->route('foo/bar');
            $route = $routing->route();
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

            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method."));

        });

    });

});