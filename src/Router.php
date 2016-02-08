<?php
namespace Lead\Router;

use Closure;
use Psr\Http\Message\RequestInterface;
use Lead\Router\RouterException;

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
     * Named routes.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Scopes definition.
     *
     * @var array
     */
    protected $_scopes = [];

    /**
     * Chunk size.
     *
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
                'request'   => 'Lead\Net\Http\Cgi\Request'
            ]
        ];
        $config += $defaults;
        $this->_classes = $config['classes'];
        $this->_scopes[] = $config['scope'] + [
            'name'       => '',
            'scheme'     => '*',
            'host'       => '*',
            'method'     => '*',
            'prefix'     => '/',
            'namespace'  => '',
            'persist'    => [],
            'middleware' => []
        ];
        $this->_basePath = $config['basePath'];
        $this->_chunkSize = $config['chunkSize'];
        $this->_strategies = $config['strategies'];
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
     * @param  Closure|array $options An array of options or the handler callback.
     * @param  Closure|null  $handler The handler callback.
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

        $options = $this->_scopify($options);
        $options['patterns'] = (array) $pattern;
        $options['handler'] = $handler;
        $route = $this->_classes['route'];

        $instance = new $route($options);
        $this->_routes[$options['scheme']][$options['host']][$options['method']][] = $instance;

        if (isset($options['name'])) {
            $this->_data[$options['name']] = $instance;
        }
        return $instance;
    }

    /**
     * Groups some routes inside a scope
     *
     * @param string|array $scope
     * @param Closure      $callback
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

        $options['prefix'] = $prefix;
        $this->_scopes[] = $this->_scopify($options);

        $handler($this);

        array_pop($this->_scopes);

        return $this;
    }

    /**
     * Scopify some route options.
     *
     * @param  string $pattern The pattern to scopify.
     * @param  array  $options The options to scopify.
     * @return array           The scopified options.
     */
    protected function _scopify($options)
    {
        $scope = end($this->_scopes);

        if (!empty($options['name'])) {
            $options['name'] = $scope['name'] ? $scope['name'] . '.' . $options['name'] : $options['name'];
        }

        if (!empty($options['prefix'])) {
            $options['prefix'] = $scope['prefix'] . trim($options['prefix'], '/') . '/';
        }

        if (isset($options['persist'])) {
            $options['persist'] = ((array) $options['persist']) + $scope['persist'];
        }

        if (isset($options['namespace'])) {
            $options['namespace'] = $scope['namespace'] . trim($options['namespace'], '\\') . '\\';
        }

        $options['middleware'] = $scope['middleware'];

        return $options + $scope;
    }

    /**
     * Routes a Request.
     *
     * @param  mixed  $request The request to route.
     * @return object          A route matching the request
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
            $route->request = is_object($request) ? $request : $this->_request($r);
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
     * Creates an instance from a request array.
     *
     * @param  mixed  $r A request.
     * @return object    A request instance.
     */
    protected function _request($r)
    {
        $request = $this->_classes['request'];
        if ($r['scheme'] === '*') {
            unset($r['scheme']);
        }
        if ($r['host'] === '*') {
            unset($r['host']);
        }
        return new $request($r);
    }

    /**
     * Normalize the request
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
     * Builds all route rules available for a request.
     *
     * @param  string $httpMethod The HTTP method constraint.
     * @param  string $host       The host constraint.
     * @param  string $scheme     The scheme constraint.
     * @return array              The available rules.
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
            $variables = [];
            $i = 0;
            foreach ($varNames as $varName) {
                $variables[$varName] = $matches[++$i];
            }
            $route->params = $hostVariables + $variables;
            return $route;
        }
    }

    /**
     * Combines a bunch of rules together.
     * This is an optimization to avoid matching the regular expressions one by one.
     *
     * @see http://nikic.github.io/2014/02/18/Fast-request-routing-using-regular-expressions.html
     *
     * @param  array $rules
     * @return array        A bunch of combined rules
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
                $numGroups = max($numGroups, $numVariables);
                $regexes[] = $regex . str_repeat('()', $numGroups - $numVariables);
                $ruleMap[++$numGroups] = $rule;
            }
            $regex = '~^(?|' . implode('|', $regexes) . ')$~';
            $combinedRules[] = ['regex' => $regex, 'map' => $ruleMap];
        }
        return $combinedRules;
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
        if ($pattern === '*') {
            $rules = [['.*', []]];
        } else {
            $parser = $this->_classes['parser'];
            $rules = $parser::rules($parser::parse($pattern, '[^.]+'));
        }
        foreach ($rules as $rule) {
            if (preg_match('~^(?|' . $rule[0] . ')$~', $host, $matches)) {
                $i = 0;
                foreach ($rule[1] as $name) {
                    $variables[$name] = $matches[++$i];
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Applies a middleware.
     *
     * @param object|Closure A middleware instance of closure.
     */
    public function apply($middleware)
    {
        $this->_scopes['middleware'][] = $middleware;
        return $this;
    }

    /**
     * Gets/sets router methods.
     *
     * @param  string       $name    A router method name
     * @param  Closure|null $handler The method handler or `null` to get the setted one.
     * @return mixed                 A method handler, `null` if not found or self on set.
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
     * Adds custom route.
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
     * Generates an URL to a named route.
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
     * Clears routes.
     */
    public function clear()
    {
        $this->_routes = [];
        $this->_scopes = [[
            'name'       => '',
            'scheme'     => '*',
            'host'       => '*',
            'method'     => '*',
            'prefix'     => '/',
            'namespace'  => '',
            'persist'    => [],
            'middleware' => []
        ]];
    }
}
