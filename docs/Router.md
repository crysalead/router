### The Router

The `Router` instance can be instantiated so:

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
* `'methods'`: the method constraint (default: `'*'`)
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

#### The Route Public Methods

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
        $controller = $route->namespace . ucfirst($route->params['controller']);
        $instance = new $controller($route->params, $route->request, $route->response);
        $action = isset($route->params['action']) ? $route->params['action'] : 'index';
        $instance->{$action}();
        return $route->response;
    });
});
```

The above example will be able to route `/admin/user/edit` on `App\Admin\Controller\User::edit()`. The fully-namespaced class name of the controller is built using the `{controller}` variable and it's then instanciated to process the request by running the `{action}` method.

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
