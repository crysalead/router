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

// Bind to all methods
$router->bind('foo/bar', function() {
    return "Hello World!";
});

// Bind to POST and PUT at dev.example.com only
$router->bind('foo/bar/edit', ['methods' => ['POST',' PUT'], 'host' => 'dev.example.com'], function() {
    return "Hello World!!";
});

// The Router class makes no assumption of the ingoing request, so you have to pass
// uri, methods, host, and protocol into `->route()` or use a PSR-7 Compatible Request.
// Do not rely on $_SERVER, you must check or sanitize it!
$route = $router->route(
    $_SERVER['REQUEST_URI'], // foo/bar
    $_SERVER['REQUEST_METHOD'], // get, post, put...etc
    $_SERVER['HTTP_HOST'], // www.example.com
    $_SERVER['SERVER_PROTOCOL'] // http or https
);

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
    return $response;
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
    return $response;
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
