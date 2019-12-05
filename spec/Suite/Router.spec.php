<?php

declare(strict_types=1);

namespace Lead\Router\Spec\Suite;

use Kahlan\Plugin\Double;
use Lead\Router\Exception\RouteNotFoundException;
use Lead\Router\Exception\RouterException;
use Lead\Router\Router;
use Lead\Router\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

describe("Router", function () {


    beforeEach(function () {


        $this->router = new Router();
        $this->export = function ($request) {

            return array_intersect_key($request, array_fill_keys(['path', 'method', 'host', 'scheme'], true));
        };
    });
    describe("->__construct()", function () {


        it("formats the basePath", function () {


            $router = new Router(['basePath' => '/']);
            expect($router->getBasePath())->toBe('');
        });
    });
    describe("->getBasePath()", function () {


        it("sets an empty basePath", function () {


            expect($this->router->setBasePath('/'))->toBe($this->router);
            expect($this->router->getBasePath())->toBe('');
            expect($this->router->setBasePath(''))->toBe($this->router);
            expect($this->router->getBasePath())->toBe('');
        });
        it("adds an leading slash for non empty basePath", function () {


            expect($this->router->setBasePath('app'))->toBe($this->router);
            expect($this->router->getBasePath())->toBe('/app');
            expect($this->router->setBasePath('/base'))->toBe($this->router);
            expect($this->router->getBasePath())->toBe('/base');
        });
    });
    describe("->bind()", function () {


        it("binds a named route", function () {


            $r = $this->router;
            $route = $r->bind('foo/bar', ['name' => 'foo'], function () {
                return 'hello';
            });
            expect(isset($r['foo']))->toBe(true);
            expect($r['foo'])->toBe($route);
        });
        it("matches on methods", function () {


            $r = $this->router;
            $r->bind('foo/bar', ['methods' => ['POST', 'PUT']], function () {
            });
            $route = $r->route('foo/bar', 'POST');
            expect($route->getMethods())->toBe(['POST', 'PUT']);
            $route = $r->route('foo/bar', 'PUT');
            expect($route->getMethods())->toBe(['POST', 'PUT']);
            try {
                $route = $r->route('bar/foo', 'GET');
            } catch (RouteNotFoundException $e) {
                expect($e->getMessage())->toBe("No route found for `*:*:GET:/bar/foo`.");
            }
        });
        it("supports lowercase method names", function () {


            $r = $this->router;
            $r->bind('foo/bar', ['methods' => ['POST', 'PUT']], function () {
            });
            $route = $r->route('foo/bar', 'post');
            expect($route->getMethods())->toBe(['POST', 'PUT']);
            $route = $r->route('foo/bar', 'put');
            expect($route->getMethods())->toBe(['POST', 'PUT']);
            try {
                $route = $r->route('bar/foo', 'GET');
            } catch (RouteNotFoundException $e) {
                expect($e->getMessage())->toBe("No route found for `*:*:GET:/bar/foo`.");
            }
        });
        it("matches on same path different methods", function () {

            $r = $this->router;
            $r->bind('foo/bar', ['name' => 'foo', 'methods' => ['POST']], function () {
            });
            $r->bind('foo/bar', ['name' => 'bar', 'methods' => ['PUT']], function () {
            });
            $route = $r->route('foo/bar', 'POST');
            expect($route->name)->toBe('foo');
            $route = $r->route('foo/bar', 'PUT');
            expect($route->name)->toBe('bar');
        });
        it("throws an exception when the handler is not a closure", function () {


            $closure = function () {

                $r = $this->router;
                $r->bind('foo', 'substr');
            };
            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method."));
        });
        it("throws an exception when trying to use the `'method'` option", function () {


            $closure = function () {

                $r = $this->router;
                $r->bind('foo', ['method' => 'GET'], function () {
                });
            };
            expect($closure)->toThrow(new RouterException("Use the `'methods'` option to limit HTTP verbs on a route binding definition."));
        });
    });
    describe("->link()", function () {


        it("forwards router base path", function () {


            $r = $this->router;
            $r->basePath('app');

            $r->group(['host' => 'www.{domain}.com', 'scheme' => 'https'], function ($r) {

                $r->bind('foo/{bar}', ['name' => 'foo'], function () {
                });
            });
            $link = $r->link('foo', [
                'bar'    => 'baz',
                'domain' => 'example'
            ], ['absolute' => true]);
            expect($link)->toBe('https://www.example.com/app/foo/baz');
        });
        it("maintains prefixes in nested routes", function () {


            $r = $this->router;
            $r->group('foo', ['name' => 'foz'], function ($r) {

                $r->group('bar', ['name' => 'baz'], function ($r) {

                    $r->bind('{var1}', ['name' => 'quz'], function () {
                    });
                });
            });
            $link = $r->link('foz.baz.quz', ['var1' => 'hello']);
            expect($link)->toBe('/foo/bar/hello');
        });
        it("persists persisted parameters in a dispatching context", function () {


            $r = $this->router;
            $r->group('{locale:en|fr}', ['persist' => 'locale'], function ($r) {

                $r->bind('{controller}/{action}[/{id}]', ['name' => 'controller'], function () {
                });
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
        it("overrides persisted parameters in a dispatching context", function () {


            $r = $this->router;
            $r->group('{locale:en|fr}', ['persist' => 'locale'], function ($r) {

                $r->bind('{controller}/{action}[/{id}]', ['name' => 'controller'], function () {
                });
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
        it("throws an exception when no route is found for a specified name", function () {


            $closure = function () {

                $this->router->link('not.binded.yet', []);
            };
            expect($closure)->toThrow(new RouterException("No binded route defined for `'not.binded.yet'`, bind it first with `bind()`."));
        });
    });
    describe("->route()", function () {


        it("routes on a simple route", function () {


            $r = $this->router;
            $r->bind('foo/bar', function () {
            });
            $route = $r->route('foo/bar', 'GET');
            expect($this->export($route->request))->toEqual([
                'host'   => '*',
                'scheme' => '*',
                'method' => 'GET',
                'path'   => 'foo/bar'
            ]);
            try {
                $route = $r->route('bar/foo', 'GET');
            } catch (RouteNotFoundException $e) {
                expect($e->getMessage())->toBe("No route found for `*:*:GET:/bar/foo`.");
            }
        });
        it("routes on a named route", function () {


            $r = $this->router;
            $r->bind('foo/bar', ['name' => 'foo'], function () {
            });
            $route = $r->route('foo/bar', 'GET');
            expect($route->name)->toBe('foo');
            try {
                $route = $r->route('bar/foo', 'GET');
            } catch (RouteNotFoundException $e) {
                expect($e->getMessage())->toBe("No route found for `*:*:GET:/bar/foo`.");
            }
        });
        it("supports empty as index route", function () {


            $r = $this->router;
            $r->bind('', function () {
            });
            $route = $r->route('', 'GET');
            expect($this->export($route->request))->toEqual([
                'host'   => '*',
                'scheme' => '*',
                'method' => 'GET',
                'path'   => ''
            ]);
        });
        it("supports a slash as indes route", function () {


            $r = $this->router;
            $r->bind('/', function () {
            });
            $route = $r->route('', 'GET');
            expect($this->export($route->request))->toEqual([
                'host'   => '*',
                'scheme' => '*',
                'method' => 'GET',
                'path'   => ''
            ]);
        });
        it("supports route variables", function () {


            $r = $this->router;
            $r->get('foo/{param}', function () {
            });
            $route = $r->route('foo/bar', 'GET');
            expect($route->params)->toBe(['param' => 'bar']);
            try {
                $route = $r->route('bar/foo', 'GET');
            } catch (RouteNotFoundException $e) {
                expect($e->getMessage())->toBe("No route found for `*:*:GET:/bar/foo`.");
            }
        });
        it("supports constrained route variables", function () {


            $r = $this->router;
            $r->get('foo/{var1:\d+}', function () {
            });
            $route = $r->route('foo/25', 'GET');
            expect($route->params)->toBe(['var1' => '25']);
            try {
                $route = $r->route('foo/bar', 'GET');
            } catch (RouteNotFoundException $e) {
                expect($e->getMessage())->toBe("No route found for `*:*:GET:/foo/bar`.");
            }
        });
        it("supports optional segments with variables", function () {


            $r = $this->router;
            $r->get('foo[/{var1}]', function () {
            });
            $route = $r->route('foo', 'GET');
            expect($route->params)->toBe(['var1' => null]);
            $route = $r->route('foo/bar', 'GET');
            expect($route->params)->toBe(['var1' => 'bar']);
        });
        it("supports repeatable segments", function () {


            $r = $this->router;
            $r->get('foo[/:{var1}]*[/bar[/:{var2}]*]', function () {
            });
            $route = $r->route('foo', 'GET');
            expect($route->params)->toBe([
                'var1' => [],
                'var2' => []
            ]);
            $route = $r->route('foo/:bar', 'GET');
            expect($route->params)->toBe([
                'var1' => ['bar'],
                'var2' => []
            ]);
            $route = $r->route('foo/:bar/:baz/bar/:fuz', 'GET');
            expect($route->params)->toBe([
                'var1' => ['bar', 'baz'],
                'var2' => ['fuz']
            ]);
        });
        it("supports optional segments with custom variable regex", function () {


            $r = $this->router;
            $r->get('foo[/{var1:\d+}]', function () {
            });
            $route = $r->route('foo', 'GET');
            expect($route->params)->toBe(['var1' => null]);
            $route = $r->route('foo/25', 'GET');
            expect($route->params)->toBe(['var1' => '25']);
            try {
                $route = $r->route('foo/baz', 'GET');
            } catch (RouteNotFoundException $e) {
                expect($e->getMessage())->toBe("No route found for `*:*:GET:/foo/baz`.");
            }
        });
        it("supports multiple optional segments", function () {


            $patterns = [
                '/[{var1}[/{var2}]]'
            ];
            $r = $this->router;

            foreach ($patterns as $pattern) {
                $r->get($pattern, function () {
                });
                $route = $r->route('foo', 'GET');
                expect($route->params)->toBe(['var1' => 'foo', 'var2' => null]);
                $route = $r->route('foo/bar', 'GET');
                expect($route->params)->toBe(['var1' => 'foo', 'var2' => 'bar']);
                $r->clear();
            };
        });
        it("supports host variables", function () {


            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function () {
            });
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function () {
            });
            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            expect($route->getHost()->getPattern())->toBe('foo.{domain}.bar');
            expect($route->params)->toBe(['domain' => 'biz', 'var1' => '25']);
            $route = $r->route('foo/50', 'GET', 'foo.buz.baz');
            expect($route->getHost()->getPattern())->toBe('foo.{domain}.baz');
            expect($route->params)->toBe(['domain' => 'buz', 'var1' => '50']);
        });
        it("supports constrained host variables", function () {


            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => '{subdomain:foo}.{domain}.bar'], function () {
            });
            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            expect($route->params)->toBe([ 'subdomain' => 'foo', 'domain' => 'biz', 'var1' => '25']);
            try {
                $route = $r->route('foo/bar', 'GET', 'foo.biz.bar');
            } catch (RouteNotFoundException $e) {
                expect($e->getMessage())->toBe("No route found for `*:foo.biz.bar:GET:/foo/bar`.");
            }
        });
        it("supports absolute URL", function () {


            $r = $this->router;
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.bar'], function () {
            });
            $r->get('foo/{var1:\d+}', ['host' => 'foo.{domain}.baz'], function () {
            });
            $route = $r->route('http://foo.biz.bar/foo/25', 'GET');
            expect($route->getHost()->getPattern())->toBe('foo.{domain}.bar');
            expect($route->params)->toBe(['domain' => 'biz', 'var1' => '25']);
            $route = $r->route('http://foo.buz.baz/foo/50', 'GET');
            expect($route->getHost()->getPattern())->toBe('foo.{domain}.baz');
            expect($route->params)->toBe(['domain' => 'buz', 'var1' => '50']);
        });
        it("supports RESTful routes", function () {


            $r = $this->router;
            $r->get('foo/bar', function () {
            });
            $r->head('foo/bar', function () {
            });
            $r->post('foo/bar', function () {
            });
            $r->put('foo/bar', function () {
            });
            $r->patch('foo/bar', function () {
            });
            $r->delete('foo/bar', function () {
            });
            $r->options('foo/bar', function () {
            });
            $methods = ['OPTIONS', 'DELETE', 'PATCH', 'PUT', 'POST', 'HEAD', 'GET'];
            $route = $r->route('foo/bar', 'GET');
            expect($route->getMethods())->toBe($methods);
            $route = $r->route('foo/bar', 'HEAD');
            expect($route->getMethods())->toBe($methods);
            $route = $r->route('foo/bar', 'POST');
            expect($route->getMethods())->toBe($methods);
            $route = $r->route('foo/bar', 'PUT');
            expect($route->getMethods())->toBe($methods);
            $route = $r->route('foo/bar', 'PATCH');
            expect($route->getMethods())->toBe($methods);
            $route = $r->route('foo/bar', 'DELETE');
            expect($route->getMethods())->toBe($methods);
            $route = $r->route('foo/bar', 'OPTIONS');
            expect($route->getMethods())->toBe($methods);
        });
        it("matches relationships based routes", function () {


            $r = $this->router;
            $r->get('[/{relations:[^/]+/[^/:][^/]*}]*/comment[/{id:[^/:][^/]*}][/:{action}]', function () {
            });
            $route = $r->route('blog/1/post/22/comment/:show', 'GET');
            expect($route->params)->toBe([
                'relations' => [
                    ['blog', '1'],
                    ['post', '22']
                ],
                'id' => null,
                'action' => 'show'
            ]);
        });
        it("dispatches HEAD requests on matching GET routes if the HEAD routes are missing", function () {


            $r = $this->router;
            $r->get('foo/bar', function () {
                return 'GET';
            });
            $route = $r->route('foo/bar', 'HEAD');
            expect($route->getMethods())->toBe(['GET']);
        });
        it("dispatches HEAD requests on matching HEAD routes", function () {


            $r = $this->router;

            $r->head('foo/bar', function () {
                return 'HEAD';
            });
            $r->get('foo/bar', function () {
                return 'GET';
            });
            $route = $r->route('foo/bar', 'HEAD');
            expect($route->getMethods())->toBe(['GET', 'HEAD']);
        });
        it("supports requests as a list of arguments", function () {


            $r = $this->router;
            $r->bind('foo/bar', function () {
            });
            $route = $r->route('foo/bar', 'GET');
            expect($this->export($route->request))->toEqual([
                'scheme' => '*',
                'host'   => '*',
                'method' => 'GET',
                'path'   => 'foo/bar'
            ]);
        });
        it("supports requests as an object", function () {

            $r = $this->router;
            $r->bind('foo/bar', function () {
            });
            $request = Double::instance(['implements' => ServerRequestInterface::class]);
            $uri = Double::instance(['implements' => UriInterface::class]);
            allow($request)->toReceive('basePath')->andReturn('/');
            allow($uri)->toReceive('getScheme')->andReturn('http');
            allow($uri)->toReceive('getHost')->andReturn('');
            allow($uri)->toReceive('getPath')->andReturn('foo/bar');
            allow($request)->toReceive('getMethod')->andReturn('GET');
            allow($request)->toReceive('getUri')->andReturn($uri);
            $route = $r->route($request, 'GET');
            expect($route->request)->toBe($request);
        });
        it("supports requests as an array", function () {


            $r = $this->router;
            $r->bind('foo/bar', function () {
            });
            $route = $r->route(['path' => 'foo/bar'], 'GET');
            expect($this->export($route->request))->toEqual([
                'scheme' => '*',
                'host'   => '*',
                'method' => 'GET',
                'path'   => 'foo/bar'
            ]);
        });
    });
    describe("->group()", function () {


        it("supports nested named route", function () {


            $r = $this->router;
            $r->group('foo', ['name' => 'foz'], function ($r) use (&$route) {

                $r->group('bar', ['name' => 'baz'], function ($r) use (&$route) {

                    $route = $r->bind('{var1}', ['name' => 'quz'], function () {
                    });
                });
            });
            expect(isset($r['foz.baz.quz']))->toBe(true);
            expect($r['foz.baz.quz'])->toBe($route);
        });
        it("returns a working scope", function () {


            $r = $this->router;
            $route = $r->group('foo', ['name' => 'foz'], function () {
            })->group('bar', ['name' => 'baz'], function () {
            })->bind('{var1}', ['name' => 'quz'], function () {
            });
            expect(isset($r['foz.baz.quz']))->toBe(true);
            expect($r['foz.baz.quz'])->toBe($route);
        });
        context("with a prefix contraint", function () {


            beforeEach(function () {


                $r = $this->router;
                $r->group('foo', function ($r) {

                    $r->bind('{var1}', function () {
                    });
                });
            });
            it("respects url's prefix constraint", function () {


                $r = $this->router;
                $route = $r->route('foo/bar');
                expect($route->params)->toBe(['var1'   => 'bar']);
            });
            it("throws an exception when the prefix does not match", function () {


                $closure = function () {

                    $r = $this->router;
                    $route = $r->route('bar/foo', 'GET');
                };
                expect($closure)->toThrow(new RouteNotFoundException("No route found for `*:*:GET:/bar/foo`."));
            });
        });
        context("with a prefix contraint and an optional parameter", function () {


            beforeEach(function () {


                $r = $this->router;
                $r->group('foo', function ($r) {

                    $r->bind('[{var1}]', function () {
                    });
                });
            });
            it("respects url's prefix constraint", function () {


                $r = $this->router;
                $route = $r->route('foo');
                expect($route->params)->toBe(['var1' => null]);
            });
            it("throws an exception when the prefix does not match", function () {


                $closure = function () {

                    $r = $this->router;
                    $route = $r->route('bar/foo', 'GET');
                };
                expect($closure)->toThrow(new RouteNotFoundException("No route found for `*:*:GET:/bar/foo`."));
            });
        });
        context("with a host constraint", function () {


            beforeEach(function () {


                $r = $this->router;
                $r->group('foo', ['host' => 'foo.{domain}.bar'], function ($r) {

                    $r->group('bar', function ($r) {

                        $r->bind('{var1}', function () {
                        });
                    });
                });
            });
            it("respects url's host constraint", function () {


                $r = $this->router;
                $route = $r->route('http://foo.hello.bar/foo/bar/baz', 'GET');
                expect($route->params)->toBe([
                    'domain' => 'hello',
                    'var1'   => 'baz'
                ]);
            });
            it("throws an exception when the host does not match", function () {


                $closure = function () {

                    $r = $this->router;
                    $route = $r->route('http://bar.hello.foo/foo/bar/baz', 'GET');
                };
                expect($closure)->toThrow(new RouteNotFoundException("No route found for `http:bar.hello.foo:GET:/foo/bar/baz`."));
            });
        });
        context("with a scheme constraint", function () {


            beforeEach(function () {


                $r = $this->router;
                $r->group('foo', ['scheme' => 'http'], function ($r) {

                    $r->group('bar', function ($r) {

                        $r->bind('{var1}', function () {
                        });
                    });
                });
            });
            it("respects url's scheme constraint", function () {


                $r = $this->router;
                $route = $r->route('http://domain.com/foo/bar/baz', 'GET');
                expect($route->params)->toBe([
                    'var1'   => 'baz'
                ]);
            });
            it("throws an exception when route is not found", function () {


                $closure = function () {

                    $r = $this->router;
                    $route = $r->route('https://domain.com/foo/bar/baz', 'GET');
                };
                expect($closure)->toThrow(new RouteNotFoundException("No route found for `https:domain.com:GET:/foo/bar/baz`."));
            });
        });
        it("concats namespace values", function () {


            $r = $this->router;
            $r->group('foo', ['namespace' => 'My'], function ($r) {

                $r->group('bar', ['namespace' => 'Name'], function ($r) {

                    $r->bind('{var1}', ['namespace' => 'Space'], function () {
                    });
                });
            });
            $route = $r->route('foo/bar/baz', 'GET');
            expect($route->namespace)->toBe('My\Name\Space\\');
        });
        it("throws an exception when the handler is not a closure", function () {


            $closure = function () {

                $r = $this->router;
                $r->group('foo', 'substr');
            };
            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method."));
        });
    });
    describe("->middleware()", function () {


        it("returns global middleware", function () {


            $r = $this->router;
            $mw1 = function ($request, $response, $next) {

                return '1' . $next() . '1';
            };
            $mw2 = function ($request, $response, $next) {

                return '2' . $next() . '2';
            };
            $r->apply($mw1, $mw2);

            $next = function () {
                return '';
            };
            $generator = $r->middleware();
            $actual2 = $generator->current();
            $generator->next();
            $actual1 = $generator->current();
            expect($actual2(null, null, $next))->toBe('22');
            expect($actual1(null, null, $next))->toBe('11');
        });
    });
    describe("->apply()", function () {


        it("applies middlewares globally", function () {


            $r = $this->router;
            $r->apply(function ($request, $response, $next) {

                return '1' . $next() . '1';
            })->apply(function ($request, $response, $next) {

                return '2' . $next() . '2';
            });
            $route = $r->bind('/foo/bar', function ($route) {

                return 'A';
            });
            $route = $r->route('foo/bar');
            $actual = $route->dispatch();
            expect($actual)->toBe('21A12');
        });
        it("applies middlewares globally and per groups", function () {


            $r = $this->router;
            $r->apply(function ($request, $response, $next) {

                return '1' . $next() . '1';
            });
            $r->bind('foo/{foo}', ['name' => 'foo'], function () {

                return 'A';
            });
            $r->group('bar', function ($r) {

                $r->bind('{bar}', ['name' => 'bar'], function () {

                    return 'A';
                });
            })->apply(function ($request, $response, $next) {

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
    describe("->strategy()", function () {


        it("sets a strategy", function () {


            $r = $this->router;
            $mystrategy = function ($router) {

                $router->bind('foo/bar', function () {

                    return 'Hello World!';
                });
            };
            $r->strategy('mystrategy', $mystrategy);
            expect($r->strategy('mystrategy'))->toBe($mystrategy);
            $r->mystrategy();
            $route = $r->route('foo/bar');
            expect($route->getPattern())->toBe('/foo/bar');
        });
        it("unsets a strategy", function () {


            $r = $this->router;

            $mystrategy = function () {
            };
            $r->strategy('mystrategy', $mystrategy);
            expect($r->strategy('mystrategy'))->toBe($mystrategy);
            $r->strategy('mystrategy', false);
            expect($r->strategy('mystrategy'))->toBe(null);
        });
        it("throws an exception when the handler is not a closure", function () {


            $closure = function () {

                $r = $this->router;
                $r->strategy('mystrategy', "substr");
            };
            expect($closure)->toThrow(new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method."));
        });
    });
});
