# Router - HTTP Request Router

[![Build Status](https://travis-ci.org/crysalead/router.svg?branch=master)](https://travis-ci.org/crysalead/router)
[![Code Coverage](https://scrutinizer-ci.com/g/crysalead/router/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/crysalead/router/)

This library extends the [FastRoute](https://github.com/nikic/FastRoute) implementation with some additional features:

 * Supports named routes and reverse routing
 * Supports sub-domain and/or prefix routing
 * Allows to set a custom dispatching strategy

## Installation

```bash
composer require crysalead/router
```

## API

### Defining routes

Example of routes definition:

```php
use Lead\Router\Router;

$router = new Router();

$router->add($route, $handler);           # route matching any request method
$router->add($route, $options, $handler); # alternative syntax with some options.
$router->add($route, [
    'method' => 'get'
], $handler);                             # route matching only get requests

// Alternative syntax
$router->get($route, $handler);    # route matching only get requests
$router->post($route, $handler);   # route matching only post requests
$router->delete($route, $handler); # route matching only delete requests
```

In the above example `$router` is a collection of routes. A route is registered using the `add()` method and takes as parametters a route pattern, an optionnal options array and an handler.

A route pattern is a string representing an URL path. Placeholders can be specified using brackets (e.g `{foo}`) and matches the `[^/]+` regexp by defaults. You can however specify a custom pattern using the following syntax `{foo:[0-9]+}`. A particular case is the placeholder `{args}` which match `.*` (i.e anything).

Furthermore you can use square brackets (i.e `[]`) to make parts of the pattern optional. For example `/foo[/bar]` will match both `/foo` and `/foobar`. Optional parts are only supported in a trailing position (i.e. not allowed in the middle of a route). You can also nest optional parts with the following syntax `/{controller}[/{action}[/{args}]]`.

The second parameter is an `$options`. Possible values are:

* `'scheme'`: the scheme constraint (default: `'*'`)
* `'host'`: the host constraint (default: `'*'`)
* `'method'`: the method constraint (default: `'*'`)
* `'name'`: the name of the route (optional)
* `'namespace'`: the namespace to attach to a route (optional)

The last parameter is the `$handler` which contain the dispatching logic. The `$handler` is dynamically binded to the founded route so `$this` will stands for the route instance. The available data in the handler will be the following:

```php
$router->add('foo/bar', function() {
    $this->scheme;     // The scheme contraint
    $this->host;       // The host contraint
    $this->method;     // The method contraint
    $this->pattern;    // The pattern contraint
    $this->args;       // The matched args
    $this->params;     // The matched params
    $this->namespace;  // The namespace
    $this->name;       // The route's name
    $this->request;    // The routed request
    $this->resposne;   // The response (can be `null`)
    $this->handlers(); // The route's handler
});
```

### Named Routes And Reverse Routing

To be able to do some reverse routing, you must name your route first using the following syntax:

```php
$route = $router->add('foo#foo/{bar}', function () { return 'hello'; });

$router['foo'] === $route; // true
```

Then the reverse routing is done through the `link()` method:

```php
$link = $router->link('foo', ['bar' => 'baz']);
echo $link; // /foo/baz
```

### Grouping Routes

It's possible to apply contraints to a bunch of routes all together by grouping them into a dedicated of decicated scope using the router `->group()` method.

```php
$router->group('admin', ['namespace' => 'App\Admin\Controller'], function($r) {
    $router->add('{controller}[/{action}]', function () {
        $controller = $this->namespace . $this->params['controller'];
        $instance = new $controller($this->args, $this->params, $this->request, $this->response);
        $action = isset($this->params['action']) ? $this->params['action'] : 'index';
        $instance->{$action}();
        return $this->response;
    });
});
```

### Sub-Domain And/Or Prefix Routing

To supports some sub-domains routing, the easiest way is to group routes related to a specific sub-domain using the `group()` method like in the following:

```php
$router->group(['host' => 'foo.{domain}.bar'], function($r) {
    $router->group('admin', function($r) {
        $router->add('{controller}[/{action}]', function () {});
    });
});
```

### Dispatching

Dispatching is the outermost layer of the framework, responsible for both receiving the initial HTTP request and sending back a response at the end of the request's life cycle.

This step has the responsibility to loads and instantiates the correct controller, resource or class to build a response. Since all this logic depends on the application architecture, the dispatching has been splitted in two steps for being as flexible as possible.

#### Dispatching A Request

The URL dispatching is done in two steps. First the `route()` method is called on the router instance to find a route matching the URL. The route accepts as arguments:

* An instance of `Psr\Http\Message\RequestInterface`
* An url or path string
* An array containing at least a path entry
* A list of parameters with the following order: path, method, host and scheme

Then if the `route()` method returns a matching route, the `dispatch()` method is called on it to execute the dispatching logic contained in the route handler.

```php
use Lead\Router\Router;

$router = new Router();

$router->add('foo/bar', function() { return "Hello World!"; });

$routing = $router->route('foo/bar', 'GET', 'www.domain.com', 'https');

if (!$routing->error()) {
    $route = $routing->route();
    echo $route->dispatch();
} else {
    throw new Exception($routing->message(), $routing->error());
}
```

#### Dispatching A Request Using Some PSR-7 Compatible Request/Response

It also possible to use compatible Request/Response instance for the dispatching.

```php
use Lead\Router\Router;
use Lead\Net\Http\Cgi\Request;
use Lead\Net\Http\Response;

$request = Request::ingoing();
$response = new Response();

$router = new Router();
$router->add('foo/bar', function() { $this->response->write("Hello World!"); });

$routing = $router->route($request);

if (!$routing->error()) {
    $route = $routing->route();
    echo $route->dispatch($response);
} else {
    throw new Exception($routing->message(), $routing->error());
}
```

### Setting up a custom dispatching strategy.

By default only the controller strategy is available and can by used like the following:

```php
$router->controller('{controller}/{action}[/{args}]', ['namespace' => 'App\Controller']);

$routing = $router->route('home/index');
$routing->route()->dispatch(); // instantiate the `App\Controller\HomeController` class
```

The controller strategy creates a controller instance from the URL controller parameter then runs the `__invoke()` method with parameters extracted from the route instance (i.e. the URL arguments and named parameters as well as the request and the response).

To define your own strategy you need to create if first using the router `strategy()` method.

Bellow an example of RESTful strategy:

```php
use Lead\Router\Router;

Router::strategy('resource', function($resource, $options = []) {
    $dispatch = function($route, $action) use ($resource) {
        $resource = $route->namespace . $resource . 'Resource';
        $instance = new $resource();
        return $instance($route->args, $route->params, $route->request, $route->response);
    };

    $path = strtolower(strtr(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $resource), '-', '_'));

    $this->get($path, $options, function() use ($dispatch) {
        return $dispatch($this, 'index');
    });
    $this->get($path . '/{id:[0-9a-f]{24}|[0-9]+}', $options, function() use ($dispatch) {
        return $dispatch($this, 'show');
    });
    $this->get($path . '/add', $options, function() use ($dispatch) {
        return $dispatch($this, 'add');
    });
    $this->post($path, $options, function() use ($dispatch) {
        return $dispatch($this, 'create');
    });
    $this->get($path . '/{id:[0-9a-f]{24}|[0-9]+}' .'/edit', $options, function() use ($dispatch) {
        return $dispatch($this, 'edit');
    });
    $this->patch($path . '/{id:[0-9a-f]{24}|[0-9]+}', $options, function() use ($dispatch) {
        return $dispatch($this, 'update');
    });
    $this->delete($path . '/{id:[0-9a-f]{24}|[0-9]+}', $options, function() use ($dispatch) {
        return $dispatch($this, 'delete');
    });
});

$router = new Router();
$router->resource('Home', ['namespace' => 'App\Resource']);

// Now all the following URL can be routed
$router->route('home');
$router->route('home/123');
$router->route('home/add');
$router->route('home', 'POST');
$router->route('home/123/edit');
$router->route('home/123', 'PATCH');
$router->route('home/123', 'DELETE');
```

### Acknowledgements

- [Li3](https://github.com/UnionOfRAD/lithium)
- [FastRoute](https://github.com/nikic/FastRoute)
