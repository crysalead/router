<?php
namespace Lead\Router\Spec\Suite;

use Lead\Router\Exception\RouteNotFoundException;
use Lead\Router\Scope;
use stdClass;
use Lead\Router\Exception\RouterException;
use Lead\Router\Router;
use Lead\Router\Route;
use Lead\Net\Http\Cgi\Request;

describe("Route", function() {

    describe("->pattern()", function() {

        it("gets/sets the pattern", function() {

            $route = new Route();
            expect($route->setPattern('/foo/bar/{id}[/{paths}]*'))->toBe($route);
            expect($route->getPattern())->toBe('/foo/bar/{id}[/{paths}]*');
            expect($route->getRegex())->toBe('/foo/bar/([^/]+)((?:/[^/]+)*)');
            expect($route->getVariables())->toBe([
                'id'    => false,
                'paths' => '/{paths}'
            ]);

        });

        it("updates the regex", function() {

            $route = new Route();
            expect($route->setPattern('/foo/bar/{id}[/{paths}]*'))->toBe($route);
            expect($route->getRegex())->toBe('/foo/bar/([^/]+)((?:/[^/]+)*)');

            expect($route->setPattern('/foo/baz/{id}[/{paths}]*'))->toBe($route);
            expect($route->getRegex())->toBe('/foo/baz/([^/]+)((?:/[^/]+)*)');

        });

        it("updates the variables", function() {

            $route = new Route();
            expect($route->setPattern('/foo/bar/{id}[/{paths}]*'))->toBe($route);
            expect($route->getVariables())->toBe([
                'id'    => false,
                'paths' => '/{paths}'
            ]);

            expect($route->setPattern('/foo/bar/{baz}[/{paths}]'))->toBe($route);
            expect($route->getVariables())->toBe([
                'baz'   => false,
                'paths' => false
            ]);

        });

    });

    describe("->scope()", function() {

        it("gets/sets route scope", function() {

            $scope = new Scope();
            $route = new Route();
            expect($route->setScope($scope))->toBe($route);
            expect($route->getScope())->toBe($scope);

        });

    });

    describe("->methods()", function() {

        it("gets/sets route methods", function() {

            $route = new Route();
            expect($route->setMethods(['POST', 'PUT']))->toBe($route);
            expect($route->getMethods())->toBe(['POST', 'PUT']);

        });

        it("formats method names", function() {

            $route = new Route();
            expect($route->setMethods(['post', 'put']))->toBe($route);
            expect($route->getMethods())->toBe(['POST', 'PUT']);

        });

    });

    describe("->allow()", function() {

        it("adds some extra allowed methods", function() {

            $route = new Route(['methods' => []]);
            expect($route->allow(['POST', 'PUT']))->toBe($route);
            expect($route->getMethods())->toBe(['POST', 'PUT']);

        });

        it("formats newly allowed method names", function() {

            $route = new Route(['methods' => []]);
            expect($route->allow(['post', 'put']))->toBe($route);
            expect($route->getMethods())->toBe(['POST', 'PUT']);

        });

    });

    describe("->apply()", function() {

        it("applies middlewares", function() {

            $r = new Router();
            $route = $r->bind('foo/bar', function($route) {
                return 'A';
            })->apply(function($request, $response, $next) {
                return '1' . $next() . '1';
            })->apply(function($request, $response, $next) {
                return '2' . $next() . '2';
            });

            $route = $r->route('foo/bar');
            $actual = $route->dispatch();

            expect($actual)->toBe('21A12');

        });

    });

    describe("->link()", function() {

        it("creates relative links", function() {

            $route = new Route(['pattern' => '/foo/{bar}']);

            $link = $route->link(['bar' => 'baz']);
            expect($link)->toBe('/foo/baz');

        });

        it("supports optionnal parameters", function() {

            $route = new Route(['pattern' => '/foo[/{bar}]']);

            $link = $route->link();
            expect($link)->toBe('/foo');

            $link = $route->link(['bar' => 'baz']);
            expect($link)->toBe('/foo/baz');

        });

        it("supports multiple optionnal parameters", function() {

            $route = new Route(['pattern' => '/file[/{paths}]*']);

            $link = $route->link();
            expect($link)->toBe('/file');

            $link = $route->link(['paths' => ['some', 'file', 'path']]);
            expect($link)->toBe('/file/some/file/path');

        });

        it("merges default params", function() {

            $route = new Route(['pattern' => '/foo/{bar}', 'params' => ['bar' => 'baz']]);

            $link = $route->link();
            expect($link)->toBe('/foo/baz');

        });

        it("creates absolute links with custom base path", function() {

            $route = new Route([
                'pattern' => 'foo/{bar}',
                'host' => 'www.{domain}.com',
                'scheme' => 'https'
            ]);

            $link = $route->link([
                'bar'    => 'baz',
                'domain' => 'example'
            ], [
                'basePath' => 'app',
                'absolute' => true
            ]);
            expect($link)->toBe('https://www.example.com/app/foo/baz');

        });

        it("allows host and scheme overriding", function() {

            $route = new Route([
                'pattern' => 'foo/{bar}',
                'host' => 'www.{domain}.com',
                'scheme' => 'https'
            ]);

            $link =$route->link([
                'bar'    => 'baz',
                'domain' => 'example'
            ], [
                'scheme'   => 'http',
                'host'     => 'www.overrided.com',
                'basePath' => 'app',
                'absolute' => true
            ]);
            expect($link)->toBe('http://www.overrided.com/app/foo/baz');

        });

        it("supports repeatable parameter placeholders as an array", function() {

            $route = new Route(['pattern' => 'post[/{id}]*']);
            expect($route->link(['id' => '123']))->toBe('/post/123');
            expect($route->link(['id' => ['123']]))->toBe('/post/123');
            expect($route->link(['id' => ['123', '456', '789']]))->toBe('/post/123/456/789');
            expect($route->link([]))->toBe('/post');

            $route = new Route(['pattern' => '/post[/{id}]+']);
            expect($route->link(['id' => ['123']]))->toBe('/post/123');
            expect($route->link(['id' => ['123', '456', '789']]))->toBe('/post/123/456/789');

        });

        it("supports route with multiple optional segments", function() {

            $route = new Route(['pattern' => '[{relation}/{rid:[^/:][^/]*}/]post[/{id:[^/:][^/]*}][/:{action}]']);

            $link = $route->link();
            expect($link)->toBe('/post');

            $link = $route->link(['action' => 'add']);
            expect($link)->toBe('/post/:add');

            $link = $route->link(['action' => 'edit', 'id' => 12]);
            expect($link)->toBe('/post/12/:edit');

            $link = $route->link(['relation' => 'user', 'rid' => 5]);
            expect($link)->toBe('/user/5/post');

            $link = $route->link(['relation' => 'user', 'rid' => 5, 'action' => 'edit', 'id' => 12]);
            expect($link)->toBe('/user/5/post/12/:edit');

        });

        it("supports route with complex repeatable optional segments", function() {

            $route = new Route(['pattern' => '[{relations:[^/]+/[^/:][^/]*}/]*post[/{id:[^/:][^/]*}][/:{action}]']);

            $link = $route->link();
            expect($link)->toBe('/post');

            $link = $route->link(['action' => 'add']);
            expect($link)->toBe('/post/:add');

            $link = $route->link(['action' => 'edit', 'id' => 12]);
            expect($link)->toBe('/post/12/:edit');

            $link = $route->link(['relations' => [['user', 5]]]);
            expect($link)->toBe('/user/5/post');

            $link = $route->link(['relations' => [['user', 5]], 'action' => 'edit', 'id' => 12]);
            expect($link)->toBe('/user/5/post/12/:edit');

        });

        it("throws an exception for missing variables", function() {

            $closure = function() {
                $route = new Route(['pattern' => 'post[/{id}]+']);
                echo $route->link([]);
            };
            expect($closure)->toThrow(new RouterException("Missing parameters `'id'` for route: `'#/post[/{id}]+'`."));

        });

        it("throws an exception when a variable doesn't match its capture pattern", function() {

            $closure = function() {
                $route = new Route(['pattern' => 'post/{id:[0-9]{3}}']);
                $route->link(['id' => '1234']);
            };
            expect($closure)->toThrow(new RouterException("Expected `'id'` to match `'[0-9]{3}'`, but received `'1234'`."));

        });

        it("throws an exception when a an array is provided for a non repeatable parameter placeholder", function() {

            $closure = function() {
                $route = new Route(['pattern' => 'post/{id}']);
                $route->link(['id' => ['123', '456']]);
            };
            expect($closure)->toThrow(new RouterException("Expected `'id'` to match `'[^/]+'`, but received `'123/456'`."));

        });

        it("throws an exception when one element of an array doesn't match the capture pattern", function() {

            $closure = function() {
                $route = new Route(['pattern' => 'post[/{id:[0-9]{3}}]+']);
                $route->link(['id' => ['123', '456', '78']]);
            };
            expect($closure)->toThrow(new RouterException("Expected `'id'` to match `'[0-9]{3}'`, but received `'78'`."));

        });

    });

    describe("->dispatch()", function() {

        it("passes route as argument of the handler function", function() {

            $r = new Router();
            $r->get('foo/{var1}[/{var2}]',
                ['host' => '{subdomain}.{domain}.bar'],
                function($route, $response) {
                    return array_merge([$response], array_values($route->params));
                }
            );

            $response = new stdClass();

            $route = $r->route('foo/25', 'GET', 'foo.biz.bar');
            $actual = $route->dispatch($response);
            expect($actual)->toBe([$response, 'foo', 'biz', '25', null]);

            $route = $r->route('foo/25/bar', 'GET', 'foo.biz.bar');
            $actual = $route->dispatch($response);
            expect($actual)->toBe([$response, 'foo', 'biz', '25', 'bar']);

        });

        it("throws an exception on non valid routes", function() {

            $closure = function() {
                $r = new Router();
                $r->get('foo', function() {});
                $route = $r->route('bar');
                $route->dispatch();
            };

            expect($closure)->toThrow(new RouteNotFoundException("No route found for `*:*:GET:/bar`.", 404));
        });

    });

});
