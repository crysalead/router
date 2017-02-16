# Router - HTTP Request Router

[![Build Status](https://travis-ci.org/crysalead/router.svg?branch=master)](https://travis-ci.org/crysalead/router)
[![Code Coverage](https://scrutinizer-ci.com/g/crysalead/router/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/crysalead/router/)

[Complete benchmark results can be found here.](https://github.com/jails/php-router-benchmark)

 * Compatible with PSR-7
 * Named routes
 * Reverses routing
 * Sub-domain
 * Nested routes
 * Custom dispatching strategy
 * Advanced route pattern syntax

## Installation

```bash
composer require crysalead/router
```

## API

### Route patterns

Route pattern are path string with curly brace placeholders. Possible placeholder format are:

* `'{name}'`       - placeholder
* `'{name:regex}'` - placeholder with regex definition.
* `'[{name}]'`     - optionnal placeholder
* `'[{name}]+'`    - recurring placeholder
* `'[{name}]*'`    - optionnal recurring placeholder

Variable placeholders may contain only word characters (latin letters, digits, and underscore) and must be unique within the pattern. For placeholders without an explicit regex, a variable placeholder matches any number of characters other than '/' (i.e `[^/]+`).

You can use square brackets (i.e `[]`) to make parts of the pattern optional. For example `/foo[bar]` will match both `/foo` and `/foobar`. Optional parts can be nested and repeatable using the `[]*` or `[]+` syntax. Example: `/{controller}[/{action}[/{args}]*]`.

Examples:
- `'/foo/'`            - Matches only if the path is exactly '/foo/'. There is no special treatment for trailing slashes, and patterns have to match the entire path, not just a prefix.
- `'/user/{id}'`       - Matches '/user/bob' or '/user/1234!!!' or even '/user/bob/details' but not '/user/' or '/user'.
- `'/user/{id:[^/]+}'` - Same as the previous example.
- `'/user[/{id}]'`     - Same as the previous example, but also match '/user'.
- `'/user[/[{id}]]'`   - Same as the previous example, but also match '/user/'.
- `'/user[/{id}]*'`    - Match '/user' as well as 'user/12/34/56'.
- `'/user/{id:[0-9a-fA-F]{1,8}}'` - Only matches if the id parameter consists of 1 to 8 hex digits.
- `'/files/{path:.*}'`            - Matches any URL starting with '/files/' and captures the rest of the path into the parameter 'path'.

Note: the difference between `/{controller}[/{action}[/{args}]*]` and `/{controller}[/{action}[/{args:.*}]]` for example is `args` will be an array using `[/{args}]*` while a unique "slashed" string using `[/{args:.*}]`.

### The Router

The `Router` instance can be nstantiate so:

```php
use Lead\Router\Router;

$router = new Router();
```

Optionally, if your project lives in a sub-folder of your web root you'll need to set a base path using `basePath()`. This base path will be ignored so your routes won't need to be prefixed with it to matches the request path.

```php
$router->basePath('/my/sub/dir');
```

Note: If you are using the [crysalead/net](https://github.com/crysalead/net) library you can pass `Request::ingoing()->basePath();` directly so you won't need to set it manually.

#### The Router Public Methods

```php
$router->basePath();   // Gets/sets the router base path
$router->group();      // To create some scoped routes
$router->bind();       // To create a route
$router->route();      // To route a request
$router->link();       // To generate a route's link
$router->apply();      // To add a global middleware
$router->middleware(); // The router's middleware generator
$router->strategy();   // Gets/sets a routing strategy
```

### Route definition

Example of routes definition:

```php
use Lead\Router\Router;

$router = new Router();

$router->bind($pattern, $handler);                                 // route matching any request method
$router->bind($pattern, $options, $handler);                       // alternative syntax with some options.
$router->bind($pattern, ['methods' => 'GET'], $handler);           // route matching on only GET requests
$router->bind($pattern, ['methods' => ['POST', 'PUT']], $handler); // route matching on POST and PUT requests

// Alternative syntax
$router->get($pattern, $handler);    // route matching only get requests
$router->post($pattern, $handler);   // route matching only post requests
$router->delete($pattern, $handler); // route matching only delete requests
```

In the above example a route is registered using the `->bind()` method and takes as parametters a route pattern, an optionnal options array and the callback handler.

The second parameter is an `$options` array where possible values are:

* `'scheme'`: the scheme constraint (default: `'*'`)
* `'host'`: the host constraint (default: `'*'`)
* `'method'`: the method constraint (default: `'*'`)
* `'name'`: the name of the route (optional)
* `'namespace'`: the namespace to attach to a route (optional)

The last parameter is the callback handler which contain the dispatching logic to execute when a route matches the request. The callback handler is the called with the matched route as first parameter and the response object as second parameter:

```php
$router->bind('foo/bar', function($route, $response) {
});
```

#### The Route Public Attributes

```php
$route->method;       // The method contraint
$route->params;       // The matched params
$route->persist;      // The persisted params
$route->namespace;    // The namespace
$route->name;         // The route's name
$route->request;      // The routed request
$route->response;     // The response (same as 2nd argument, can be `null`)
$route->dispatched;   // To store the dispated instance if applicable.
```

#### The Route Mublic Methods

```php
$route->host();       // The route's host instance
$route->pattern();    // The pattern
$route->regex();      // The regex
$route->variables();  // The variables
$route->token();      // The route's pattern token structure
$route->scope();      // The route's scope
$route->error();      // The route's error number
$route->message();    // The route's error message
$route->link();       // The route's link
$route->apply();      // To add a new middleware
$route->middleware(); // The route's middleware generator
$route->handler();    // The route's handler
$route->dispatch();   // To dispatch the route (i.e execute the route's handler)
```

### Named Routes And Reverse Routing

To be able to do some reverse routing, route must be named using the following syntax first:

```php
$route = $router->bind('foo/{bar}', ['name' => 'foo'], function() { return 'hello'; });
```

Named routes can be retrieved using the array syntax on the router instance:
```php
$router['foo']; // Returns the `'foo'` route.
```

Once named, the reverse routing can be done using the `->link()` method:

```php
echo $router->link('foo', ['bar' => 'baz']); // /foo/baz
```

The `->link()` method takes as first parameter the name of a route and as second parameter the route's arguments.

### Grouping Routes

It's possible to apply a scope to a set of routes all together by grouping them into a dedicated group using the `->group()` method.

```php
$router->group('admin', ['namespace' => 'App\Admin\Controller'], function($router) {
    $router->bind('{controller}[/{action}]', function($route, $response) {
        $controller = $route->namespace . $route->params['controller'];
        $instance = new $controller($route->params, $route->request, $route->response);
        $action = isset($route->params['action']) ? $route->params['action'] : 'index';
        $instance->{$action}();
        return $route->response;
    });
});
```

The above example will be able to route `/admin/user/edit` on `App\Admin\Controller\User::edit()`.

### Sub-Domain And/Or Prefix Routing

To supports some sub-domains routing, the easiest way is to group routes using the `->group()` method and setting up the host constraint like so:

```php
$router->group(['host' => 'foo.{domain}.bar'], function($router) {
    $router->group('admin', function($router) {
        $router->bind('{controller}[/{action}]', function() {});
    });
});
```

The above example will be able to route `http://foo.hello.bar/admin/user/edit` for example.

### Middleware

Middleware functions are functions that have access to the request object, the response object, and the next middleware function in the application’s request-response cycle. Middleware functions provide the same level of control as aspects in [AOP](https://en.wikipedia.org/wiki/Aspect-oriented_programming). It allows to:

* Execute any code.
* Make changes to the request and the response objects.
* End the request-response cycle.
* Call the next middleware function in the stack.

And it's also possible to apply middleware functions globally on a single route or on a group of them. Adding a middleware to a Route is done using the `->apply()` method:

```php
$mw = function ($request, $response, $next) {
    return 'BEFORE' . $next($request, $response) . 'AFTER';
};


$router->get('foo', function($route) {
    return '-FOO-';
})

echo $router->route('foo')->dispatch($response); //BEFORE-FOO-AFTER
```

You can also attach middlewares on groups.

```php
$mw1 = function ($request, $response, $next) {
    return '1' . $next($request, $response) . '1';
};
$mw2 = function ($request, $response, $next) {
    return '2' . $next($request, $response) . '2';
};
$mw3 = function ($request, $response, $next) {
    return '3' . $next($request, $response) . '3';
};
$router->apply($mw1); // Global

$router->group('foo', function($router) {
    $router->get('bar', function($route) {
        return '-BAR-';
    })->apply($mw3);  // Local
})->apply($mw2);      // Group

echo $router->route('foo/bar')->dispatch($response); //321-BAR-123
```

### Dispatching

Dispatching is the outermost layer of the framework, responsible for both receiving the initial HTTP request and sending back a response at the end of the request's life cycle.

This step has the responsibility to loads and instantiates the correct controller, resource or class to build a response. Since all this logic depends on the application architecture, the dispatching has been splitted in two steps for being as flexible as possible.

#### Dispatching A Request

The URL dispatching is done in two steps. First the `->route()` method is called on the router instance to find a route matching the URL. The route accepts as arguments:

* An instance of `Psr\Http\Message\RequestInterface`
* An url or path string
* An array containing at least a path entry
* A list of parameters with the following order: path, method, host and scheme

The `->route()` method returns a route (or a "not found" route), then the `->dispatch()` method will execute the dispatching logic contained in the route handler (or throwing an exception for non valid routes).

```php
use Lead\Router\Router;

$router = new Router();

$router->bind('foo/bar', function() {
    return "Hello World!";
});

$route = $router->route('foo/bar', 'GET', 'www.domain.com', 'https');

echo $route->dispatch(); // Can throw an exception if the route is not valid.
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
$router->bind('foo/bar', function($route, $response) {
    $response->body("Hello World!");
});

$route = $router->route($request);

echo $route->dispatch($response); // Can throw an exception if the route is not valid.
```

#### Handling dispatching failures

```php
use Lead\Router\RouterException;
use Lead\Router\Router;
use Lead\Net\Http\Cgi\Request;
use Lead\Net\Http\Response;

$request = Request::ingoing();
$response = new Response();

$router = new Router();
$router->bind('foo/bar', function($route, $response) {
    $response->body("Hello World!");
});

$route = $router->route($request);

try {
    echo $route->dispatch($response);
} catch (RouterException $e) {
    http_response_code($e->getCode());
    // Or you can use Whoops or whatever to render something
}
```

### Setting up a custom dispatching strategy.

To use your own strategy you need to create it using the `->strategy()` method.

Bellow an example of a RESTful strategy:

```php
use Lead\Router\Router;
use My\Custom\Namespace\ResourceStrategy;

Router::strategy('resource', new ResourceStrategy());

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

The strategy:

```php
namespace use My\Custom\Namespace;

class ResourceStrategy {

    public function __invoke($router, $resource, $options = [])
    {
        $path = strtolower(strtr(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $resource), '-', '_'));

        $router->get($path, $options, function($route) {
            return $this->_dispatch($route, $resource, 'index');
        });
        $router->get($path . '/{id:[0-9a-f]{24}|[0-9]+}', $options, function($route) {
            return $this->_dispatch($route, $resource, 'show');
        });
        $router->get($path . '/add', $options, function($route) {
            return $this->_dispatch($route, $resource, 'add');
        });
        $router->post($path, $options, function($route) {
            return $this->_dispatch($route, $resource, 'create');
        });
        $router->get($path . '/{id:[0-9a-f]{24}|[0-9]+}' .'/edit', $options, function($route) {
            return $this->_dispatch($route, $resource, 'edit');
        });
        $router->patch($path . '/{id:[0-9a-f]{24}|[0-9]+}', $options, function($route) {
            return $this->_dispatch($route, $resource, 'update');
        });
        $router->delete($path . '/{id:[0-9a-f]{24}|[0-9]+}', $options, function($route) {
            return $this->_dispatch($route, $resource, 'delete');
        });
    }

    protected function _dispatch($route, $resource, $action)
    {
        $resource = $route->namespace . $resource . 'Resource';
        $instance = new $resource();
        return $instance($route->params, $route->request, $route->response);
    }

}
```

### Acknowledgements

- [Li3](https://github.com/UnionOfRAD/lithium)
- [FastRoute](https://github.com/nikic/FastRoute)
