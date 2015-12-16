<?php
namespace Lead\Router;

use Closure;
use Lead\Router\RouterException;

class Router
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * @var array
     */
    protected $_routes = [];

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
     * Dispatching strategies.
     */
    protected $_strategies = [];

    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes'   => [
                'parser' => 'Lead\Router\Parser',
                'route'  => 'Lead\Router\Route'
            ],
            'scope'     => [],
            'chunkSize' => 10
        ];
        $config += $defaults;
        $this->_classes = $config['classes'];
        $this->_scopes[] = $config['scope'] + [
            'scheme'  => '*',
            'host'    => '*',
            'method'  => '*',
            'pattern' => '/'
        ];
        $this->_chunkSize = $config['chunkSize'];
    }

    /**
     * Adds a route.
     *
     * @param  string        $pattern The route pattern
     * @param  Closure|array $options An array of options or the handler callback.
     * @param  Closure|null  $handler The handler callback.
     * @return self
     */
    public function route($pattern, $options, $handler = null)
    {
        if (is_callable($options)) {
            $handler = $options;
            $options = [];
        }
        if (!is_callable($handler)) {
            throw new RouterException("The handler needs to be callable.");
        }
        $scope = end($this->_scopes);

        $options['pattern'] = $scope['pattern'] . (trim($pattern, '/'));
        $options += $scope + [
            'scheme'  => '*',
            'host'    => '*',
            'method'  => '*'
        ];
        $options['handler'] = $handler;
        $route = $this->_classes['route'];

        $instance = new $route($options);
        $this->_routes[$options['scheme']][$options['host']][$options['method']][] = $instance;
        return $instance;
    }

    /**
     * Groups some routes inside a scope
     *
     * @param string|array  $scope
     * @param Closure       $callback
     */
    public function mount($pattern, $options, $handler = null)
    {
        if (is_callable($options)) {
            $handler = $options;
            $options = $pattern;
        }
        if (!is_callable($handler)) {
            throw new RouterException("The handler needs to be callable.");
        }
        $scope = end($this->_scopes);

        if (is_string($options)) {
            $pattern = $options;
            $options = [];
        }

        $options['pattern'] = $scope['pattern'] . trim($pattern, '/') . '/';

        if ($scope['scheme'] !== '*' && $scope['scheme'] !== $options['scheme']) {
            throw new Exception("Parent's scope requires `'{$scope['scheme']}'` as scheme, but current is `'{$options['scheme']}'`.");
        }

        if ($scope['host'] !== '*' && $scope['host'] !== $options['host']) {
            throw new Exception("Parent's scope requires `'{$scope['host']}'` as host, but current is `'{$options['host']}'`.");
        }

        $this->_scopes[] = $options + $scope;

        $handler($this);

        array_pop($this->_scopes);
    }

    /**
     * Dispatches a Request.
     *
     * @return mixed
     */
    public function dispatch($request)
    {
        if (is_object($request)) {
            $r = [
                'scheme' => $request->getScheme(),
                'host'   => $request->getHost(),
                'method' => $request->getMethod(),
                'path'   => $request->getRequestTarget(),
            ];
        } elseif (!is_array($request)) {
            $r = array_combine(['path', 'method', 'host', 'scheme'], func_get_args() + ['/', 'GET', '*', '*']);
        } else {
            $r = $request;
        }
        $r = $this->_normalizeRequest($r);

        $rules = $this->_buildRules($r['method'], $r['host'], $r['scheme']);

        if ($route = $this->_dispatch($rules, $r['path'])) {
            return $route->dispatch(is_object($request) ? $request : $r);
        }
        throw new RouterException("No route found for `{$r['scheme']}:{$r['host']}:{$r['method']}:{$r['path']}`.", 404);
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
     * @param  string $method The HTTP method constraint.
     * @param  string $host   The host constraint.
     * @param  string $scheme The scheme constraint.
     * @return array          The available rules.
     */
    protected function _buildRules($method, $host = '*', $scheme = '*')
    {
        $parser = $this->_classes['parser'];
        $schemes = array_unique([$scheme => $scheme, '*' => '*']);
        $methods = array_unique([$method => $method, '*' => '*']);

        if ($method === 'HEAD') {
            $methods += ['GET' => 'GET'];
        }

        $rulesMap = [];

        foreach ($this->_routes as $scheme => $hostBasedRoutes) {
            if (!isset($schemes[$scheme])) {
                continue;
            }
            foreach ($hostBasedRoutes as $routeDomain => $methodBasedRoutes) {
                $hostVariables = [];
                if (!$this->_matchDomain($host, $routeDomain, $hostVariables)) {
                    continue;
                }
                foreach ($methodBasedRoutes as $method => $routes) {
                    if (!isset($methods[$method])) {
                        continue;
                    }
                    foreach ($routes as $route) {
                        $parses = $parser::parse($route->pattern());
                        foreach ($parses as $parse) {
                            list($pattern, $varNames) = $parse;
                            if (isset($rulesMap['*'][$pattern])) {
                                $old = $rulesMap['*'][$pattern][0];
                            } elseif (isset($rules[$method][$pattern])) {
                                $old = $rulesMap[$method][$pattern][0];
                            } else {
                                $rulesMap[$method][$pattern] = [$route, $varNames, $hostVariables];
                                continue;
                            }
                            $error  = "The route `{$scheme}:{$routeDomain}:{$method}:{$pattern}` conflicts with a previously ";
                            $error .= "defined one on `{$old->scheme()}:{$old->host()}:{$old->method()}:{$pattern}`.";
                            throw new RouterException($error);
                        }
                    }
                }
            }
        }

        $rules = [];

        foreach ($methods as $method) {
            $rules += (isset($rulesMap[$method]) ? $rulesMap[$method] : []);
        }
        return $rules;
    }

    /**
     * Dispatches an url pattern.
     *
     * @param array  $rules The rules to match on.
     * @param string $path  The URL path to dispatch.
     * @param array         The result array.
     */
    protected function _dispatch($rules, $path)
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
            $route->params($hostVariables + $variables);
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
            $rules = $parser::parse($pattern, '[^.]+');
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
     * Adds a route.
     *
     * @param  string $name   The HTTP verb to route on.
     * @param  array  $params The parameters to pass to the route.
     */
    public function __call($name, $params)
    {
        if (func_num_args() < 2) {
            throw new RouterException("Adding a route require at least 2 parameters.");
        }
        $method = strtoupper($name);
        if (is_callable($params[1])) {
            $params[2] = $params[1];
            $params[1] = [];
        }
        $params[1]['method'] = $method;
        return call_user_func_array([$this, 'route'], $params);
    }
}
