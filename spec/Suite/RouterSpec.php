<?php
namespace Lead\Router\Spec\Suite;

use Lead\Router\RouterException;
use Lead\Router\Router;
use Lead\Router\Route;
use Lead\Net\Http\Cgi\Request;

describe("Router", function() {

    beforeEach(function() {

        $this->router = new Router();
        $this->export = function($request) {
            return array_intersect_key($request, array_fill_keys(['path', 'method', 'host', 'scheme'], true));
        };

    });

    describe("->bind()", function() {

        it("binds a named route", function() {

            $r = $this->router;
            $route = $r->bind('foo/bar', ['name' => 'foo'], function() { return 'hello'; });
            expect(isset($r['foo']))->toBe(true);
            expect($r['foo'])->toBe($route);

        });

        it("throws an exception when the handler is not a closure", function() {

            $closure = function() {
                $r = $this->router;
                $r->bind('foo', 'substr');
            };

            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method."));

        });

    });

    describe("->link()", function() {

        it("creates relative links", function() {

            $r = $this->router;
            $r->bind('foo/{bar}', ['name' => 'foo'], function () {});

            $link = $r->link('foo', ['bar' => 'baz']);
            expect($link)->toBe('/foo/baz');

        });

        it("supports optionnal parameters", function() {

            $r = $this->router;
            $r->bind('foo[/{bar}]', ['name' => 'foo'], function () {});

            $link = $r->link('foo');
            expect($link)->toBe('/foo');

            $link = $r->link('foo', ['bar' => 'baz']);
            expect($link)->toBe('/foo/baz');

        });

        it("merges default params", function() {

            $r = $this->router;
            $r->bind('foo/{bar}', [
                'name'   => 'foo',
                'params' => ['bar' => 'baz']
            ], function () {});

            $link = $r->link('foo');
            expect($link)->toBe('/foo/baz');

        });

        it("creates absolute links", function() {

            $r = $this->router;
            $r->basePath('app');

            $r->group(['host' => 'www.example.com'], function($r) {
                $r->bind('foo/{bar}', ['name' => 'foo'], function () {});
            });

            $link = $r->link('foo', ['bar' => 'baz'], ['absolute' => true]);
            expect($link)->toBe('http://www.example.com/app/foo/baz');

        });

        it("support nested routes", function() {

            $r = $this->router;
            $r->group('foo', ['name' => 'foz'], function($r) {
                $r->group('bar', ['name' => 'baz'], function($r) {
                    $r->bind('{var1}', ['name' => 'quz'], function () {});
                });
            });

            $link = $r->link('foz.baz.quz', ['var1' => 'hello']);
            expect($link)->toBe('/foo/bar/hello');

        });

        it("persists persisted parameters in a dispatching context", function() {

            $r = $this->router;
            $r->group('{locale:en|fr}', ['persist' => 'locale'], function($r) {
                $r->bind('{controller}/{action}[/{id}]', ['name' => 'controller'], function () {});
            });

            $r->route('fr/post/index');
            $link = $r->link('controller', [
                'controller' => 'post',
                'action'     => 'view',
                'id'         => 5
            ]);
            expect($link)->toBe('/fr/post/view/5');

            $r->route('en/post/index');
            $link = $r->link('controller', [
                'controller' => 'post',
                'action'     => 'view',
                'id'         => 5
            ]);
            expect($link)->toBe('/en/post/view/5');

        });

        it("overrides persisted parameters in a dispatching context", function() {

            $r = $this->router;
            $r->group('{locale:en|fr}', ['persist' => 'locale'], function($r) {
                $r->bind('{controller}/{action}[/{id}]', ['name' => 'controller'], function () {});
            });

            $r->route('fr/post/index');

            $link = $r->link('controller', [
                'locale'     => 'en',
                'controller' => 'post',
                'action'     => 'view',
                'id'         => 5
            ]);
            expect($link)->toBe('/en/post/view/5');

        });

        it("supports route with multiple patterns", function() {

            $r = $this->router;

            $patterns = [
                '{relation}/{rid:[^/:][^/]*}/post/{id:[^/:][^/]*}[/:{action}]',
                '{relation}/{rid:[^/:][^/]*}/post[/:{action}]',
                'post/{id:[^/:][^/]*}[/:{action}]',
                'post[/:{action}]'
            ];

            $route = $r->bind($patterns, ['name' => 'post'], function () {});

            $link = $r->link('post');
            expect($link)->toBe('/post');

            $link = $r->link('post', ['action' => 'add']);
            expect($link)->toBe('/post/:add');

            $link = $r->link('post', ['action' => 'edit', 'id' => 12]);
            expect($link)->toBe('/post/12/:edit');

            $link = $r->link('post', ['relation' => 'user', 'rid' => 5]);
            expect($link)->toBe('/user/5/post');

            $link = $r->link('post', ['relation' => 'user', 'rid' => 5, 'action' => 'edit', 'id' => 12]);
            expect($link)->toBe('/user/5/post/12/:edit');

        });

        it("throws an exception when some required parameters are missing", function() {

            $closure = function() {
                $r = $this->router;
                $r->bind('foo/{bar}', ['name' => 'foo'], function () {});
                $r->link('foo');
            };

            expect($closure)->toThrow(new RouterException("Missing parameters `'bar'` for route: `'foo#/foo/{bar}'`."));

        });

    });

    describe("->route()", function() {

        it("routes on a simple route", function() {

            $r = $this->router;
            $r->bind('foo/bar', function () {});

            $route = $r->route('foo/bar', 'GET');
            expect($this->export($route->request))->toEqual([
                'host'   => '*',
                'scheme' => '*',
                'method' => 'GET',
                'path'   => '/foo/bar'
            ]);

            $route = $r->route('foo/baz', 'GET');
            expect($route->error())->toBe(Route::NOT_FOUND);
            expect($route->message())->toBe("No route found for `*:*:GET:/foo/baz`.");

        });

        it("routes on a named route", function() {

            $r = $this->router;
            $r->bind('foo/bar', ['name' => 'foo'], function () {});

            $route = $r->route('foo/bar', 'GET');
            expect($route->name)->toBe('foo');

            $route = $r->route('foo/baz', 'GET');
            expect($route->error())->toBe(Route::NOT_FOUND);
            expect($route->message())->toBe("No route found for `*:*:GET:/foo/baz`.");

        });

        it("supports route variables", function() {

            $r = $this->router;
            $r->get('foo/{param}', function() {});

            $route = $r->route('foo/bar', 'GET');
            expect($route->params)->toBe(['param' => 'bar']);

            $route = $r->route('bar/foo', 'GET');
            expect($route->error())->toBe(Route::NOT_FOUND);
            expect($route->message())->toBe("No route found for `*:*:GET:/bar/foo`.");

        });

        it("supports constrained route variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', function() {});

            $route = $r->route('foo/25', 'GET');
            expect($route->params)->toBe(['var1' => '25']);

            $route = $r->route('foo/bar', 'GET');
            expect($route->error())->toBe(Route::NOT_FOUND);
            expect($route->message())->toBe("No route found for `*:*:GET:/foo/bar`.");

        });

        it("supports optional segments with variables", function() {

            $r = $this->router;
            $r->get('foo[/{var1}]', function() {});

            $route = $r->route('foo', 'GET');
            expect($route->params)->toBe([]);

            $route = $r->route('foo/bar', 'GET');
            expect($route->params)->toBe(['var1' => 'bar']);

        });

        it("supports repeatable segments", function() {

            $r = $this->router;
            $r->get('foo[/:{var1}]*[/bar[/:{var2}]*]', function() {});

            $route = $r->route('foo', 'GET');
            expect($route->params)->toBe([]);

            $route = $r->route('foo/:bar', 'GET');
            expect($route->params)->toBe([
                'var1' => ['bar']
            ]);

            $route = $r->route('foo/:bar/:baz/bar/:fuz', 'GET');
            expect($route->params)->toBe([
                'var1' => ['bar', 'baz'],
                'var2' => ['fuz']
            ]);

        });

        it("supports optional segments with custom variable regex", function() {

            $r = $this->router;
            $r->get('foo[/{var1:\d+}]', function() {});

            $route = $r->route('foo', 'GET');
            expect($route->params)->toBe([]);

            $route = $r->route('foo/25', 'GET');
            expect($route->params)->toBe(['var1' => '25']);

            $route = $r->route('foo/baz', 'GET');
            expect($route->error())->toBe(Route::NOT_FOUND);
            expect($route->message())->toBe("No route found for `*:*:GET:/foo/baz`.");

        });

        it("supports multiple optional segments", function() {

            $patterns = [
                '/[{var1}[/{var2}]]',
                '[/{var1}[/{var2}]]'
            ];

            $r = $this->router;

            foreach ($patterns as $pattern) {
                $r->get($pattern, function() {});

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
            expect($route->host()->host)->toBe('foo.{domain}.bar');
            expect($route->params)->toBe(['domain' => 'biz', 'var1' => '25']);

            $route = $r->route('foo/50', 'GET', 'foo.buz.baz');
            expect($route->host()->host)->toBe('foo.{domain}.baz');
            expect($route->params)->toBe(['domain' => 'buz', 'var1' => '50']);

        });

        it("supports constrained host variables", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => '{subdomain:foo}.{domain}.bar'], function() {});

            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            expect($route->params)->toBe([ 'subdomain' => 'foo', 'domain' => 'biz', 'var1' => '25']);

            $route = $r->route('foo/bar', 'GET', 'foo.biz.bar');
            expect($route->error())->toBe(Route::NOT_FOUND);
            expect($route->message())->toBe("No route found for `*:foo.biz.bar:GET:/foo/bar`.");

        });

        it("supports absolute URL", function() {

            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function() {});
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function() {});

            $route = $r->route('http://foo.biz.bar/foo/25', 'GET');
            expect($route->host()->host)->toBe('foo.{domain}.bar');
            expect($route->params)->toBe(['domain' => 'biz', 'var1' => '25']);

            $route = $r->route('http://foo.buz.baz/foo/50', 'GET');
            expect($route->host()->host)->toBe('foo.{domain}.baz');
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
            $r->bind('foo/bar', function () {});

            $route = $r->route('foo/bar', 'GET');
            expect($this->export($route->request))->toEqual([
                'scheme' => '*',
                'host'   => '*',
                'method' => 'GET',
                'path'   => '/foo/bar'
            ]);

        });

        it("supports requests as an object", function() {

            $r = $this->router;
            $r->bind('foo/bar', function () {});
            $request = new Request(['path' =>'foo/bar']);

            $route = $r->route($request, 'GET');
            expect($route->request)->toBe($request);

        });

        it("supports requests as an array", function() {

            $r = $this->router;
            $r->bind('foo/bar', function () {});

            $route = $r->route(['path' =>'foo/bar'], 'GET');
            expect($this->export($route->request))->toEqual([
                'scheme' => '*',
                'host'   => '*',
                'method' => 'GET',
                'path'   => '/foo/bar'
            ]);

        });

    });

    describe("->group()", function() {

        it("supports nested named route", function() {

            $r = $this->router;
            $r->group('foo', ['name' => 'foz'], function($r) use (&$route) {
                $r->group('bar', ['name' => 'baz'], function($r) use (&$route) {
                    $route = $r->bind('{var1}', ['name' => 'quz'], function() {});
                });
            });

            expect(isset($r['foz.baz.quz']))->toBe(true);
            expect($r['foz.baz.quz'])->toBe($route);

        });

        it("returns a working scope", function() {

            $r = $this->router;
            $route = $r->group('foo', ['name' => 'foz'], function() {
            })->group('bar', ['name' => 'baz'], function() {
            })->bind('{var1}', ['name' => 'quz'], function() {});

            expect(isset($r['foz.baz.quz']))->toBe(true);
            expect($r['foz.baz.quz'])->toBe($route);

        });

        context("with a prefix contraint", function() {

            beforeEach(function() {

                $r = $this->router;
                $r->group('foo', function($r) {
                    $r->bind('{var1}', function () {});
                });

            });

            it("respects url's prefix constraint", function() {

                $r = $this->router;
                $route = $r->route('foo/bar');
                expect($route->params)->toBe(['var1'   => 'bar']);

            });

            it("bails out when the prefix doesn't match", function() {

                $r = $this->router;
                $route = $r->route('bar/foo', 'GET');
                expect($route->error())->toBe(Route::NOT_FOUND);
                expect($route->message())->toBe("No route found for `*:*:GET:/bar/foo`.");

            });

        });

        context("with a host constraint", function() {

            beforeEach(function() {

                $r = $this->router;
                $r->group('foo', ['host' => 'foo.{domain}.bar'], function($r) {
                    $r->group('bar', function($r) {
                        $r->bind('{var1}', function () {});
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

            it("bails out when the host doesn't match", function() {

                $r = $this->router;
                $route = $r->route('http://bar.hello.foo/foo/bar/baz', 'GET');
                expect($route->error())->toBe(Route::NOT_FOUND);
                expect($route->message())->toBe("No route found for `http:bar.hello.foo:GET:/foo/bar/baz`.");

            });

        });

        context("with a scheme constraint", function() {

            beforeEach(function() {

                $r = $this->router;
                $r->group('foo', ['scheme' => 'http'], function($r) {
                    $r->group('bar', function($r) {
                        $r->bind('{var1}', function () {});
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

            it("bails out when the scheme doesn't match", function() {

                $r = $this->router;
                $route = $r->route('https://domain.com/foo/bar/baz', 'GET');
                expect($route->error())->toBe(Route::NOT_FOUND);
                expect($route->message())->toBe("No route found for `https:domain.com:GET:/foo/bar/baz`.");

            });

        });

        it("concats namespace values", function() {

            $r = $this->router;
            $r->group('foo', ['namespace' => 'My'], function($r) {
                $r->group('bar', ['namespace' => 'Name'], function($r) {
                    $r->bind('{var1}', ['namespace' => 'Space'], function () {});
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

            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method."));

        });

    });

    describe("->middleware()", function() {

        it("returns global middleware", function() {

            $r = $this->router;

            $mw1 = function($request, $response, $next) {
                return '1' . $next() . '1';
            };
            $mw2 = function($request, $response, $next) {
                return '2' . $next() . '2';
            };

            $r->apply($mw1, $mw2);

            $next = function() { return ''; };

            $generator = $r->middleware();

            $actual2 = $generator->current();
            $generator->next();
            $actual1 = $generator->current();

            expect($actual2(null, null, $next))->toBe('22');
            expect($actual1(null, null, $next))->toBe('11');

        });

    });

    describe("->apply()", function() {

        it("applies middlewares globally", function() {

            $r = $this->router;

            $r->apply(function($request, $response, $next) {
                return '1' . $next() . '1';
            })->apply(function($request, $response, $next) {
                return '2' . $next() . '2';
            });

            $route = $r->bind('/foo/bar', function($route) {
                return 'A';
            });

            $route = $r->route('foo/bar');
            $actual = $route->dispatch();

            expect($actual)->toBe('21A12');

        });

        it("applies middlewares globally and per groups", function() {

            $r = $this->router;

            $r->apply(function($request, $response, $next) {
                return '1' . $next() . '1';
            });

            $r->bind('foo/{foo}', ['name' => 'foo'], function () {
                return 'A';
            });

            $r->group('bar', function($r) {
                $r->bind('{bar}', ['name' => 'bar'], function () {
                    return 'A';
                });
            })->apply(function($request, $response, $next) {
                return '2' . $next() . '2';
            });

            $route = $r->route('foo/foo');
            $actual = $route->dispatch();

            expect($actual)->toBe('1A1');

            $route = $r->route('bar/bar');
            $actual = $route->dispatch();

            expect($actual)->toBe('21A12');

        });

    });

    describe("->strategy()", function() {

        it("sets a strategy", function() {

            $r = $this->router;

            $mystrategy = function($router) {
                $router->bind('foo/bar', function() {
                    return 'Hello World!';
                });
            };

            $r->strategy('mystrategy', $mystrategy);
            expect($r->strategy('mystrategy'))->toBe($mystrategy);

            $r->mystrategy();
            $route = $r->route('foo/bar');
            expect($route->patterns())->toBe(['/foo/bar']);

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