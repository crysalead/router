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
