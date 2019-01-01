<?php
declare(strict_types=1);

namespace Lead\Router;

use ArrayAccess;
use Closure;
use Countable;
use Iterator;
use Lead\Router\Exception\ParserException;
use Lead\Router\Exception\RouteNotFoundException;
use Lead\Router\Exception\RouterException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * The Router class.
 */
class Router implements ArrayAccess, Iterator, Countable, RouterInterface
{
    /**
     * @var bool
     */
    protected $_skipNext;

    /**
     * @var array
     */
    protected $_data = [];

    /**
     * @var array
     */
    protected $_pattern = [];

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Hosts.
     *
     * @var array
     */
    protected $_hosts = [];

    /**
     * Routes.
     *
     * @var array
     */
    protected $_routes = [];

    /**
     * Scopes stack.
     *
     * @var array
     */
    protected $_scopes = [];

    /**
     * Base path.
     *
     * @param string
     */
    protected $_basePath = '';

    /**
     * Dispatching strategies.
     *
     * @param array
     */
    protected $_strategies = [];

    /**
     * Defaults parameters to use when generating URLs in a dispatching context.
     *
     * @var array
     */
    protected $_defaults = [];

    /**
     * Default handler
     *
     * @var callable|null
     */
    protected $defaultHandler = null;

    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $defaults = [
            'basePath'       => '',
            'scope'          => [],
            'strategies'     => [],
            'defaultHandler' => null,
            'classes'        => [
                'parser'     => 'Lead\Router\Parser',
                'host'       => 'Lead\Router\Host',
                'route'      => 'Lead\Router\Route',
                'scope'      => 'Lead\Router\Scope'
            ]
        ];

        $config += $defaults;
        $this->_classes = $config['classes'];
        $this->_strategies = $config['strategies'];
        $this->setDefaultHandler($config['defaultHandler']);
        $this->setBasePath($config['basePath']);

        $scope = $this->_classes['scope'];
        $this->_scopes[] = new $scope(['router' => $this]);
    }

    /**
     * Sets the default handler for routes
     *
     * @param mixed $handler
     * @return $this
     */
    public function setDefaultHandler($handler): self
    {
        $this->_defaultHandler = $handler;

        return $this;
    }

    /**
     * Returns the current router scope.
     *
     * @return object The current scope instance.
     */
    public function scope()
    {
        return end($this->_scopes);
    }

    /**
     * Pushes a new router scope context.
     *
     * @param  object $scope A scope instance.
     * @return self
     */
    public function pushScope($scope)
    {
        $this->_scopes[] = $scope;

        return $this;
    }

    /**
     * Pops the current router scope context.
     *
     * @return object The popped scope instance.
     */
    public function popScope()
    {
        return array_pop($this->_scopes);
    }

    /**
     * Gets the base path
     *
     * @param  string $basePath The base path to set or none to get the setted one.
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->_basePath;
    }

    /**
     * Sets the base path
     *
     * @param  string $basePath Base Path
     * @return $this
     */
    public function setBasePath(string $basePath): self
    {
        $basePath = trim($basePath, '/');
        $this->_basePath = $basePath ? '/' . $basePath : '';

        return $this;
    }

    /**
     * Gets/sets the base path of the router.
     *
     * @deprecated Use setBasePath() and getBasePath() instead
     * @param      string|null $basePath The base path to set or none to get the setted one.
     * @return     string|self
     */
    public function basePath(?string $basePath = null)
    {
        if ($basePath === null) {
            return $this->_basePath;
        }

        return $this->setBasePath($basePath);
    }

    /**
     * Adds a route.
     *
     * @param  string|array  $pattern The route's pattern.
     * @param  Closure|array $options An array of options or the callback handler.
     * @param  Closure|null  $handler The callback handler.
     * @return self
     */
    public function bind($pattern, $options = [], $handler = null)
    {
        if (!is_array($options)) {
            $handler = $options;
            $options = [];
        }
        if (empty($handler) && !empty($this->_defaultHandler)) {
            $handler = $this->_defaultHandler;
        }
        if (!$handler instanceof Closure && !method_exists($handler, '__invoke')) {
            throw new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method.");
        }

        if (isset($options['method'])) {
            throw new RouterException("Use the `'methods'` option to limit HTTP verbs on a route binding definition.");
        }

        $scope = end($this->_scopes);
        $options = $scope->scopify($options);
        $options['pattern'] = $pattern;
        $options['handler'] = $handler;
        $options['scope'] = $scope;

        $scheme = $options['scheme'];
        $host = $options['host'];

        if (isset($this->_hosts[$scheme][$host])) {
            $options['host'] = $this->_hosts[$scheme][$host];
        }

        if (isset($this->_pattern[$scheme][$host][$pattern])) {
            $instance = $this->_pattern[$scheme][$host][$pattern];
        } else {
            $route = $this->_classes['route'];
            $instance = new $route($options);
            $this->_hosts[$scheme][$host] = $instance->getHost();
        }

        if (!isset($this->_pattern[$scheme][$host][$pattern])) {
            $this->_pattern[$scheme][$host][$pattern] = $instance;
        }

        $methods = $options['methods'] ? (array)$options['methods'] : [];

        $instance->allow($methods);

        foreach ($methods as $method) {
            $this->_routes[$scheme][$host][strtoupper($method)][] = $instance;
        }

        if (isset($options['name'])) {
            $this->_data[$options['name']] = $instance;
        }

        return $instance;
    }

    /**
     * Groups some routes inside a new scope.
     *
     * @param  string|array  $prefix  The group's prefix pattern or the options array.
     * @param  Closure|array $options An array of options or the callback handler.
     * @param  Closure|null  $handler The callback handler.
     * @return object                 The newly created scope instance.
     */
    public function group($prefix, $options, $handler = null)
    {
        if (!is_array($options)) {
            $handler = $options;
            if (is_string($prefix)) {
                $options = [];
            } else {
                $options = $prefix;
                $prefix = '';
            }
        }
        if (!$handler instanceof Closure && !method_exists($handler, '__invoke')) {
            throw new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method.");
        }

        $options['prefix'] = isset($options['prefix']) ? $options['prefix'] : $prefix;

        $scope = $this->scope();

        $this->pushScope($scope->seed($options));

        $handler($this);

        return $this->popScope();
    }

    /**
     *
     */
    protected function _getRequestInformation(ServerRequestInterface $request): array
    {
        $uri = $request->getUri();

        if (method_exists($request, 'basePath')) {
            $this->setBasePath($request->basePath());
        }

        return [
            'scheme' => $uri->getScheme(),
            'host' => $uri->getHost(),
            'method' => $request->getMethod(),
            'path' => $uri->getPath()
        ];
    }

    /**
     * Routes a Request.
     *
     * @todo   Remove none PSR7 requests
     * @param  mixed $request The request to route.
     * @return object A route matching the request or a "route not found" route.
     */
    public function route($request): Route
    {
        $defaults = [
            'path' => '',
            'method' => 'GET',
            'host' => '*',
            'scheme' => '*'
        ];

        $this->_defaults = [];

        if ($request instanceof ServerRequestInterface) {
            $r = $this->_getRequestInformation($request);
        } elseif (!is_array($request)) {
            $r = array_combine(array_keys($defaults), func_get_args() + array_values($defaults));
        } else {
            $r = $request + $defaults;
        }

        $r = $this->_normalizeRequest($r);

        $route = $this->_route($r);
        if ($route instanceof RouteInterface) {
            $route->request = is_object($request) ? $request : $r;
            foreach ($route->getPersistentParams() as $key) {
                if (isset($route->params[$key])) {
                    $this->_defaults[$key] = $route->params[$key];
                }
            }

            return $route;
        }

        $message = "No route found for `{$r['scheme']}:{$r['host']}:{$r['method']}:/{$r['path']}`.";
        throw new RouteNotFoundException($message);
    }

    /**
     * Normalizes a request.
     *
     * @param  array $request The request to normalize.
     * @return array          The normalized request.
     */
    protected function _normalizeRequest(array $request): array
    {
        if (preg_match('~^(?:[a-z]+:)?//~i', $request['path'])) {
            $parsed = array_intersect_key(parse_url($request['path']), $request);
            $request = $parsed + $request;
        }
        $request['path'] = (ltrim((string)strtok($request['path'], '?'), '/'));
        $request['method'] = strtoupper($request['method']);

        return $request;
    }

    /**
     * Routes a request.
     *
     * @param array $request The request to route.
     * @return null|\Lead\Router\RouteInterface
     */
    protected function _route($request): ?RouteInterface
    {
        $path = $request['path'];
        $httpMethod = $request['method'];
        $host = $request['host'];
        $scheme = $request['scheme'];

        $allowedSchemes = array_unique([$scheme => $scheme, '*' => '*']);
        $allowedMethods = array_unique([$httpMethod => $httpMethod, '*' => '*']);

        if ($httpMethod === 'HEAD') {
            $allowedMethods += ['GET' => 'GET'];
        }

        foreach ($this->_routes as $scheme => $hostBasedRoutes) {
            if (!isset($allowedSchemes[$scheme])) {
                continue;
            }
            foreach ($hostBasedRoutes as $routeHost => $methodBasedRoutes) {
                foreach ($methodBasedRoutes as $method => $routes) {
                    if (!isset($allowedMethods[$method]) && $httpMethod !== '*') {
                        continue;
                    }
                    foreach ($routes as $route) {
                        if (!$route->match($request, $variables, $hostVariables)) {
                            if ($hostVariables === null) {
                                continue 3;
                            }
                            continue;
                        }

                        return $route;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Middleware generator.
     *
     * @return callable
     */
    public function middleware()
    {
        foreach ($this->_scopes[0]->middleware() as $middleware) {
            yield $middleware;
        }
    }

    /**
     * Adds a middleware to the list of middleware.
     *
     * @param  object|Closure A callable middleware.
     * @return $this
     */
    public function apply($middleware)
    {
        foreach (func_get_args() as $mw) {
            $this->_scopes[0]->apply($mw);
        }

        return $this;
    }

    /**
     * Sets a dispatcher strategy
     *
     * @param  string   $name    Name
     * @param  callable $handler Handler
     * @return $this
     */
    public function setStrategy(string $name, callable $handler)
    {
        $this->_strategies[$name] = $handler;

        return $this;
    }

    /**
     * Get a strategy
     *
     * @return callable
     */
    public function getStrategy(string $name): callable
    {
        if (isset($this->_strategies[$name])) {
            return $this->_strategies[$name];
        }

        throw new RuntimeException(sprintf('Strategy `%s` not found.', $name));
    }

    /**
     * Unsets a strategy
     *
     * @param  string $name
     * @return $this
     */
    public function unsetStrategy(string $name)
    {
        if (isset($this->_strategies[$name])) {
            unset($this->_strategies[$name]);

            return $this;
        }

        throw new RuntimeException(sprintf('Strategy `%s` not found.', $name));
    }

    /**
     * Gets/sets router's strategies.
     *
     * @deprecated Use setStrategy(), unsetStrategy() and getStrategy()
     * @param      string $name    A routing strategy name.
     * @param      mixed  $handler The strategy handler or none to get the setted one.
     * @return     mixed           The strategy handler (or `null` if not found) on get or `$this` on set.
     */
    public function strategy($name, $handler = null)
    {
        if (func_num_args() === 1) {
            try {
                return $this->getStrategy($name);
            } catch (RuntimeException $e) {
                return null;
            }
        }

        if ($handler === false) {
            try {
                return $this->unsetStrategy($name);
            } catch (RuntimeException $e) {
                return null;
            }
        }

        if (!$handler instanceof Closure && !method_exists($handler, '__invoke')) {
            throw new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method.");
        }

        return $this->setStrategy($name, $handler);
    }

    /**
     * Adds a route based on a custom HTTP verb.
     *
     * @param string $name   The HTTP verb to define a route on.
     * @param array  $params The route's parameters.
     * @return mixed
     */
    public function __call($name, $params)
    {
        if ($strategy = $this->strategy($name)) {
            array_unshift($params, $this);

            return call_user_func_array($strategy, $params);
        }

        if (is_callable($params[1])) {
            $params[2] = $params[1];
            $params[1] = [];
        }

        $params[1]['methods'] = [$name];

        return call_user_func_array([$this, 'bind'], $params);
    }

    /**
     * Returns a route's link.
     *
     * @param string $name    A route name.
     * @param array  $params  The route parameters.
     * @param array  $options Options for generating the proper prefix. Accepted values are:
     *                        - `'absolute'` _boolean_: `true` or `false`. - `'scheme'`  
     *                        _string_ : The scheme. - `'host'`     _string_ : The host
     *                        name. - `'basePath'` _string_ : The base path. - `'query'`   
     *                        _string_ : The query string. - `'fragment'` _string_ : The
     *                        fragment string.
     *
     * @return string          The link.
     */
    public function link(string $name, array $params = [], array $options = []): string
    {
        $defaults = [
            'basePath' => $this->getBasePath()
        ];
        $options += $defaults;

        $params += $this->_defaults;

        if (!isset($this[$name])) {
            throw new RouterException("No binded route defined for `'{$name}'`, bind it first with `bind()`.");
        }
        $route = $this[$name];

        return $route->link($params, $options);
    }

    /**
     * Clears the router.
     */
    public function clear()
    {
        $this->_basePath = '';
        $this->_strategies = [];
        $this->_defaults = [];
        $this->_routes = [];
        $scope = $this->_classes['scope'];
        $this->_scopes = [new $scope(['router' => $this])];
    }

    /**
     * Return the current element
     *
     * @link   https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since  5.0.0
     */
    public function current()
    {
        return current($this->_data);
    }

    /**
     * Move forward to next element
     *
     * @link   https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since  5.0.0
     */
    public function next()
    {
        $value = $this->_skipNext ? current($this->_data) : next($this->_data);
        $this->_skipNext = false;

        key($this->_data) !== null ? $value : null;
    }

    /**
     * Return the key of the current element
     *
     * @link   https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since  5.0.0
     */
    public function key()
    {
        return array_keys($this->_data);
    }

    /**
     * Checks if current position is valid
     *
     * @link   https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since  5.0.0
     */
    public function valid()
    {
        return key($this->_data) !== null;
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @link   https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since  5.0.0
     */
    public function rewind()
    {
        $this->_skipNext = false;

        reset($this->_data);
    }

    /**
     * Whether a offset exists
     *
     * @link   https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param  mixed $offset <p>
     *                       An offset to check for.
     *                       </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since  5.0.0
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_data);
    }

    /**
     * Offset to retrieve
     *
     * @link   https://php.net/manual/en/arrayaccess.offsetget.php
     * @param  mixed $offset <p>
     *                       The offset to retrieve.
     *                       </p>
     * @return mixed Can return all value types.
     * @since  5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->_data[$offset];
    }

    /**
     * Offset to set
     *
     * @link  https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The
     *                      value
     *                      to
     *                      set.
     *                      </p>
     *
     * @return void
     * @since  5.0.0
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->_data[] = $value;
            return;
        }

        $this->_data[$offset] = $value;
    }

    /**
     * Offset to unset
     *
     * @link   https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param  mixed $offset <p>
     *                       The offset to unset.
     *                       </p>
     * @return void
     * @since  5.0.0
     */
    public function offsetUnset($offset)
    {
        $this->_skipNext = $offset === key($this->_data);
        unset($this->_data[$offset]);
    }

    /**
     * Count elements of an object
     *
     * @link   https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since  5.1.0
     */
    /**
     * Counts the items of the object.
     *
     * @return integer Returns the number of items in the collection.
     */
    public function count()
    {
        return count($this->_data);
    }
}
