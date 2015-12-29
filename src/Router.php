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
     * Slash insensitive param name.
     *
     * @param array
     */
    protected $_matchAnything = 'args';

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
            'strategies'    => $this->_strategies(),
            'classes'       => [
                'parser'    => 'Lead\Router\Parser',
                'route'     => 'Lead\Router\Route',
                'routing'   => 'Lead\Router\Routing'
            ],
            'matchAnything' => 'args'
        ];
        $config += $defaults;
        $this->_classes = $config['classes'];
        $this->_scopes[] = $config['scope'] + [
            'name'      => '',
            'scheme'    => '*',
            'host'      => '*',
            'method'    => '*',
            'pattern'   => '/',
            'namespace' => ''
        ];
        $this->_basePath = $config['basePath'];
        $this->_chunkSize = $config['chunkSize'];
        $this->_strategies = $config['strategies'];
        $this->_matchAnything = $config['matchAnything'];
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
     * @param  string        $pattern The route pattern
     * @param  Closure|array $options An array of options or the handler callback.
     * @param  Closure|null  $handler The handler callback.
     * @return self
     */
    public function add($pattern, $options, $handler = null)
    {
        if ($options instanceof Closure) {
            $handler = $options;
            $options = [];
        }
        if (!$handler instanceof Closure) {
            throw new RouterException("The handler needs to be an instance of `Closure`.");
        }
        $scope = end($this->_scopes);

        if (strpos($pattern, '#')) {
            list($name, $pattern) = explode('#', $pattern, 2);
            $name = $options['name'] = $scope['name'] ?  $scope['name'] . '/' . $name : $name;
        }

        $options['pattern'] = $scope['pattern'] . (trim($pattern, '/'));

        if (isset($options['namespace'])) {
            $options['namespace'] = $scope['namespace'] . trim($options['namespace'], '\\') . '\\';
        }

        $options += $scope;
        $options['handler'] = $handler;
        $route = $this->_classes['route'];

        $instance = new $route($options);
        $this->_routes[$options['scheme']][$options['host']][$options['method']][] = $instance;
        if (isset($name)) {
            $this->_data[$name] = $instance;
        }
        return $instance;
    }

    /**
     * Groups some routes inside a scope
     *
     * @param string|array $scope
     * @param Closure      $callback
     */
    public function group($pattern, $options, $handler = null)
    {
        if ($options instanceof Closure) {
            $handler = $options;
            if (is_string($pattern)) {
                $options = [];
            } else {
                $options = $pattern;
                $pattern = '';
            }
        }
        if (!$handler instanceof Closure) {
            throw new RouterException("The handler needs to be an instance of `Closure`.");
        }
        $scope = end($this->_scopes);

        if (strpos($pattern, '#')) {
            list($name, $pattern) = explode('#', $pattern, 2);
            $options['name'] = $scope['name'] ?  $scope['name'] . '/' . $name : $name;
        }

        $options['pattern'] = $scope['pattern'] . trim($pattern, '/') . '/';

        if (isset($options['namespace'])) {
            $options['namespace'] = $scope['namespace'] . trim($options['namespace'], '\\') . '\\';
        }

        $this->_scopes[] = $options + $scope;

        $handler($this);

        array_pop($this->_scopes);
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
        $routing = $this->_classes['routing'];

        $error = $routing::FOUND;
        $message = 'OK';

        if ($route = $this->_route($rules, $r['path'])) {
            $route->request = is_object($request) ? $request : $r;
        } else {
            $rules = $this->_buildRules('*', $r['host'], $r['scheme']);
            if ($route = $this->_route($rules, $r['path'])) {
                $error = $routing::METHOD_NOT_ALLOWED;
                $message = "Method `{$r['method']}` Not Allowed for `{$r['scheme']}:{$r['host']}:{$r['path']}`.";
                $route = null;
            } else {
                $error = $routing::NOT_FOUND;
                $message = "No route found for `{$r['scheme']}:{$r['host']}:{$r['method']}:{$r['path']}`.";
            }
        }

        return new $routing(compact('error', 'message', 'route'));
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
        $matchAnything = $this->_matchAnything;

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
            if (isset($variables[$matchAnything])) {
                $args = explode('/', $variables[$matchAnything]);
                $route->args = array_merge(array_values(array_slice($variables, 0, -1)), $args);
            } else {
                $route->args = array_values($variables);
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
        if (!$handler instanceof Closure) {
            throw new RouterException("The handler needs to be an instance of `Closure`.");
        }
        $this->_strategies[$name] = $handler;
        return $this;
    }

    /**
     * Examples of routing strategy.
     *
     * @return array
     */
    protected function _strategies()
    {
        return [
            'controller' => function($path, $options, $controller = null) {
                if (!is_array($options)) {
                    $controller = $options;
                    $options = [];
                }
                $options += ['suffix' => 'Controller'];
                $this->add($path, $options, function() use ($controller, $options) {
                    if (!$controller) {
                        $controller  = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', strtolower($this->params['controller']))));
                        $controller .= $options['suffix'];
                    }
                    $controller = $this->namespace . $controller;
                    $instance = new $controller();
                    return $instance($this->args, $this->params, $this->request, $this->response);
                });
            }
        ];
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
            $strategy = $strategy->bindTo($this);
            return call_user_func_array($strategy, $params);
        }
        if (is_callable($params[1])) {
            $params[2] = $params[1];
            $params[1] = [];
        }
        $params[1]['method'] = $method;
        return call_user_func_array([$this, 'add'], $params);
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
            'scheme'    => '*',
            'host'      => '*',
            'method'    => '*',
            'pattern'   => '/',
            'namespace' => ''
        ]];
    }
}
