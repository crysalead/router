<?php
namespace Lead\Router;

use Closure;
use Psr\Http\Message\RequestInterface;
use Lead\Router\ParseException;
use Lead\Router\RouterException;

/**
 * The Router class.
 */
class Router extends \Lead\Collection\Collection
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

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
     * Chunk size. (optimization parameter)
     *
     * @see http://nikic.github.io/2014/02/18/Fast-request-routing-using-regular-expressions.html
     * @param integer
     */
    protected $_chunkSize = null;

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
     * Constructor
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $defaults = [
            'basePath'      => '',
            'scope'         => [],
            'chunkSize'     => 10,
            'strategies'    => [],
            'classes'       => [
                'parser'    => 'Lead\Router\Parser',
                'route'     => 'Lead\Router\Route',
                'scope'     => 'Lead\Router\Scope'
            ]
        ];
        $config += $defaults;
        $this->_classes = $config['classes'];
        $this->_basePath = $config['basePath'];
        $this->_chunkSize = $config['chunkSize'];
        $this->_strategies = $config['strategies'];

        $scope = $this->_classes['scope'];
        $this->_scopes[] = new $scope(['router' => $this]);
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
     * @return object The poped scope instance.
     */
    public function popScope()
    {
        return array_pop($this->_scopes);
    }

    /**
     * Gets/sets the base path of the router.
     *
     * @param  string      $basePath The base path to set or none to get the setted one.
     * @return string|self
     */
    public function basePath($basePath = null)
    {
        if (!func_num_args()) {
            return $this->_basePath;
        }
        $this->_basePath = $basePath && $basePath !== '/' ? '/' . trim($basePath, '/') : '';
        return $this;
    }

    /**
     * Adds a route.
     *
     * @param  string|array  $pattern The route's pattern or patterns.
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
        if (!$handler instanceof Closure && !method_exists($handler, '__invoke')) {
            throw new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method.");
        }

        $scope = end($this->_scopes);
        $options = $scope->scopify($options);
        $options['patterns'] = (array) $pattern;
        $options['handler'] = $handler;
        $options['scope'] = $scope;
        $route = $this->_classes['route'];

        $instance = new $route($options);
        $this->_routes[$options['scheme']][$options['host']][$options['method']][] = $instance;

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
     * Routes a Request.
     *
     * @param  mixed  $request The request to route.
     * @return object          A route matching the request or a "route not found" route.
     */
    public function route($request)
    {
        $defaults = [
            'path'   => '/',
            'method' => 'GET',
            'host'   => '*',
            'scheme' => '*'
        ];

        $this->_defaults = [];

        if ($request instanceof RequestInterface) {
            $uri = $request->getUri();
            $r = [
                'scheme' => $uri->getScheme(),
                'host'   => $uri->getHost(),
                'method' => $request->getMethod(),
                'path'   => $uri->getPath()
            ];
            if (method_exists($request, 'basePath')) {
                $this->basePath($request->basePath());
            }
        } elseif (!is_array($request)) {
            $r = array_combine(array_keys($defaults), func_get_args() + array_values($defaults));
        } else {
            $r = $request + $defaults;
        }
        $r = $this->_normalizeRequest($r);

        $rules = $this->_buildRules($r['method'], $r['host'], $r['scheme']);

        if ($route = $this->_route($rules, $r['path'])) {
            $route->request = is_object($request) ? $request : $r;
            foreach ($route->persist as $key) {
                if (isset($route->params[$key])) {
                    $this->_defaults[$key] = $route->params[$key];
                }
            }
        } else {
            $route = $this->_classes['route'];

            $rules = $this->_buildRules('*', $r['host'], $r['scheme']);
            if ($this->_route($rules, $r['path'])) {
                $error = $route::METHOD_NOT_ALLOWED;
                $message = "Method `{$r['method']}` Not Allowed for `{$r['scheme']}:{$r['host']}:{$r['path']}`.";
            } else {
                $error = $route::NOT_FOUND;
                $message = "No route found for `{$r['scheme']}:{$r['host']}:{$r['method']}:{$r['path']}`.";
            }

            $route = new $route(compact('error', 'message'));
        }

        return $route;
    }

    /**
     * Normalizes a request.
     *
     * @param  array $request The request to normalize.
     * @return array          The normalized request.
     */
    protected function _normalizeRequest($request)
    {
        if (preg_match('~^(?:[a-z]+:)?//~i', $request['path'])) {
            $parsed = array_intersect_key(parse_url($request['path']), $request);
            $request = $parsed + $request;
        }
        $request['path'] = '/' . (ltrim(strtok($request['path'], '?'), '/'));
        return $request;
    }

    /**
     * Returns all potentially matchable route rules.
     *
     * @param  string $httpMethod The HTTP method constraint.
     * @param  string $host       The host constraint.
     * @param  string $scheme     The scheme constraint.
     * @return array              The potentially matchable route rules.
     */
    protected function _buildRules($httpMethod, $host = '*', $scheme = '*')
    {
        $allowedSchemes = array_unique([$scheme => $scheme, '*' => '*']);
        $allowedMethods = array_unique([$httpMethod => $httpMethod, '*' => '*']);

        $rulesMap = [];

        if ($httpMethod === 'HEAD') {
            $allowedMethods += ['GET' => 'GET'];
            $rulesMap['HEAD'] = [];
        }

        // Only routes which match the schema, host and HTTP method are compiled.
        // This explain this code nesting.
        foreach ($this->_routes as $scheme => $hostBasedRoutes) {
            if (!isset($allowedSchemes[$scheme])) {
                continue;
            }
            foreach ($hostBasedRoutes as $routeDomain => $methodBasedRoutes) {
                $hostVariables = [];
                if (!$this->_matchDomain($host, $routeDomain, $hostVariables)) {
                    continue;
                }
                foreach ($methodBasedRoutes as $method => $routes) {
                    if (!isset($allowedMethods[$method]) && $httpMethod !== '*') {
                        continue;
                    }
                    foreach ($routes as $route) {
                        $rules = $route->rules();
                        foreach ($rules as $rule) {
                            list($pattern, $varNames) = $rule;
                            if (isset($rulesMap['*'][$pattern])) {
                                $old = $rulesMap['*'][$pattern][0];
                            } elseif (isset($rulesMap[$method][$pattern])) {
                                $old = $rulesMap[$method][$pattern][0];
                            } else {
                                $rulesMap[$method][$pattern] = [$route, $varNames, $hostVariables];
                                continue;
                            }
                            $error  = "The route `{$scheme}:{$routeDomain}:{$method}:{$pattern}` conflicts with a previously ";
                            $error .= "defined one on `{$old->scheme}:{$old->host}:{$old->method}:{$pattern}`.";
                            throw new RouterException($error);
                        }
                    }
                }
            }
        }
        $rules = [];

        foreach ($rulesMap as $method => $value) {
            $rules += $value;
        }
        return $rules;
    }

    /**
     * Routes an url pattern on a bunch of route rules.
     *
     * @param array  $rules The rules to match on.
     * @param string $path  The URL path to dispatch.
     * @param array         The result array.
     */
    protected function _route($rules, $path)
    {
        $combinedRules = $this->_combineRules($rules, $this->_chunkSize);
        foreach ($combinedRules as $combinedRule) {
            if (!preg_match($combinedRule['regex'], $path, $matches)) {
                continue;
            }
            list($route, $varNames, $hostVariables) = $combinedRule['map'][count($matches)];

            $variables = $this->_buildVariables($varNames, $matches);
            $route->params = $hostVariables + $variables;
            return $route;
        }
    }

    /**
     * Combines rules' regexs together by chunks.
     * This is an optimization to avoid matching the regular expressions one by one.
     *
     * @see http://nikic.github.io/2014/02/18/Fast-request-routing-using-regular-expressions.html
     *
     * @param  array $rules     The rules to combine.
     * @param  array $chunkSize The chunk size.
     * @return array            A collection of regex chunk.
     */
    protected function _combineRules($rules, $chunkSize)
    {
        $combinedRules = [];
        $count = count($rules);
        $chunks = array_chunk($rules, $chunkSize, true);
        foreach ($chunks as $chunk) {
            $ruleMap = [];
            $regexes = [];
            $numGroups = 0;
            foreach ($chunk as $regex => $rule) {
                $numVariables = count($rule[1]);
                $numGroups = max($numGroups, $numVariables) + 1;
                $regexes[] = $regex . str_repeat('()', $numGroups - $numVariables);
                $ruleMap[++$numGroups] = $rule;
            }
            $regex = '~^(?|' . implode('|', $regexes) . ')$~';
            $combinedRules[] = ['regex' => $regex, 'map' => $ruleMap];
        }
        return $combinedRules;
    }

    /**
     * Combines route's variables names with the regex matched route's values.
     *
     * @param  array $varNames The variable names array with their corresponding pattern segment when applicable.
     * @param  array $values   The matched values.
     * @return array           The route's variables.
     */
    protected function _buildVariables($varNames, $values)
    {
        $variables = [];
        $parser = $this->_classes['parser'];

        $values = $this->_cleanMatches($values);

        foreach ($values as $value) {
            list($name, $pattern) = each($varNames);
            if (!$pattern) {
                $variables[$name] = $value;
            } else {
                $parsed = $parser::tokenize($pattern, '/');
                $rule = $parser::compile($parsed);
                if (preg_match_all('~' . $rule[0] . '~', $value, $parts)) {
                    $variables[$name] = $parts[1];
                }
            }
        }
        return $variables;
    }

    /**
     * Filters out all empty values of not found groups.
     *
     * @param  array $matches Some regex matched values.
     * @return array          The real matched values.
     */
    protected function _cleanMatches($matches)
    {
        $result = [];
        $len = count($matches);
        while ($len > 1 && !$matches[$len - 1]) {
            $len--;
        }
        for ($i = 1; $i < $len; $i++)
        {
            $result[] = $matches[$i];
        }
        return $result;
    }

    /**
     * Checks if a host matches a host pattern.
     *
     * @param  string  $host    The host to check.
     * @param  string  $pattern The pattern to use for checking.
     * @return boolean          Returns `true` on success, false otherwise.
     */
    protected function _matchDomain($host, $pattern, &$variables)
    {
        if ($host === '*' || $pattern === '*') {
            return true;
        }
        $parser = $this->_classes['parser'];
        $token = $parser::tokenize($pattern, '.');
        $rule = $parser::compile($token);
        if (preg_match('~^' . $rule[0] . '$~', $host, $matches)) {
            $i = 0;
            foreach ($rule[1] as $name => $pattern) {
                $variables[$name] = $matches[++$i];
            }
            return true;
        }
        return false;
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
     * @param object|Closure A callable middleware.
     */
    public function apply($middleware)
    {
        foreach (func_get_args() as $mw) {
            $this->_scopes[0]->apply($mw);
        }
        return $this;
    }

    /**
     * Gets/sets router's strategies.
     *
     * @param  string $name    A routing strategy name.
     * @param  mixed  $handler The strategy handler or none to get the setted one.
     * @return mixed           The strategy handler (or `null` if not found) on get or `$this` on set.
     */
    public function strategy($name, $handler = null)
    {
        if (func_num_args() === 1) {
            if (!isset($this->_strategies[$name])) {
                return;
            }
            return $this->_strategies[$name];
        }
        if ($handler === false) {
            unset($this->_strategies[$name]);
            return;
        }
        if (!$handler instanceof Closure && !method_exists($handler, '__invoke')) {
            throw new RouterException("The handler needs to be an instance of `Closure` or implements the `__invoke()` magic method.");
        }
        $this->_strategies[$name] = $handler;
        return $this;
    }

    /**
     * Adds a route based on a custom HTTP verb.
     *
     * @param  string $name   The HTTP verb to define a route on.
     * @param  array  $params The route's parameters.
     */
    public function __call($name, $params)
    {
        $method = strtoupper($name);
        if ($strategy = $this->strategy($name)) {
            array_unshift($params, $this);
            return call_user_func_array($strategy, $params);
        }
        if (is_callable($params[1])) {
            $params[2] = $params[1];
            $params[1] = [];
        }
        $params[1]['method'] = $method;
        return call_user_func_array([$this, 'bind'], $params);
    }

    /**
     * Returns a route's link.
     *
     * @param  string $name    A route name.
     * @param  array  $params  The route parameters.
     * @param  array  $options Options for generating the proper prefix. Accepted values are:
     *                         - `'absolute'` _boolean_: `true` or `false`.
     *                         - `'scheme'`   _string_ : The scheme.
     *                         - `'host'`     _string_ : The host name.
     *                         - `'basePath'` _string_ : The base path.
     *                         - `'query'`    _string_ : The query string.
     *                         - `'fragment'` _string_ : The fragment string.
     * @return string          The link.
     */
    public function link($name, $params = [], $options = [])
    {
        $defaults = [
            'basePath' => $this->basePath()
        ];
        $options += $defaults;

        $params += $this->_defaults;

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
}
