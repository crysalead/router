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
            'scheme' => '*',
            'domain' => '*',
            'method' => '*',
            'pattern' => ''
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
        $options += [
            'scheme'  => '*',
            'domain'  => '*',
            'method'  => '*'
        ];
        if (!is_callable($handler)) {
            throw new RouterException("The handler needs to be callable.");
        }
        $scope = end($this->_scopes);
        $options['pattern'] = $scope['pattern'] . '/' . (trim($pattern, '/'));
        $options += $scope;
        $options['handler'] = $handler;
        $route = $this->_classes['route'];
        $instance = new $route($options);
        $this->_routes[$options['scheme']][$options['domain']][$options['method']][] = $instance;
        return $instance;
    }

    /**
     * Groups some routes inside a scope
     *
     * @param string|array  $scope
     * @param Closure       $callback
     */
    public function group($pattern, $options, $handler = null)
    {
        if (is_callable($options)) {
            $handler = $options;
            $options = [];
        }
        if (!is_callable($handler)) {
            throw new RouterException("The handler needs to be callable.");
        }
        $parent = end($this->_scopes);

        $pattern = isset($options['pattern']) ? $options['pattern'] : '';
        $options['pattern'] = trim(trim($parent['pattern'], '/') . '/' . $pattern, '/');

        if ($parent['scheme'] !== '*' && $parent['scheme'] !== $scope['scheme']) {
            throw new Exception("Parent's scope requires `'{$parent['scheme']}'` as scheme, but current is `'{$scope['scheme']}'`.");
        }

        if ($parent['domain'] !== '*' && $parent['domain'] !== $scope['domain']) {
            throw new Exception("Parent's scope requires `'{$parent['domain']}'` as domain, but current is `'{$scope['domain']}'`.");
        }

        $this->_scopes[] = $scope;

        $handler($this);

        array_pop($this->_scopes);
    }

    /**
     * @return mixed
     */
    public function dispatch($request)
    {
        $parser = $this->_classes['parser'];

        if (!is_array($request)) {
            $request = array_combine(['path', 'method', 'domain', 'scheme'], func_get_args() + ['/', 'GET', '*', '*']);
        }
        $request['path'] = '/' . (ltrim($request['path'], '/'));

        $schemes = array_unique([$request['scheme'] => $request['scheme']]);
        $methods = array_unique([$request['method'] => $request['method'], '*' => '*']);
        $rules = [];

        if ($request['method'] === 'HEAD') {
            $methods += ['GET' => 'GET'];
        }

        foreach ($this->_routes as $scheme => $domainBasedRoutes) {
            if (!isset($schemes[$scheme])) {
                continue;
            }
            foreach ($domainBasedRoutes as $domain => $methodBasedRoutes) {
                $domainVariables = [];
                if (!$this->_matchDomain($request['domain'], $domain, $domainVariables)) {
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
                            if (isset($rules['*'][$pattern])) {
                                $old = $rules['*'][$pattern][0];
                            } elseif (isset($rules[$method][$pattern])) {
                                $old = $rules[$method][$pattern][0];
                            } else {
                                $rules[$method][$pattern] = [$route, $varNames, $domainVariables];
                                continue;
                            }
                            $error  = "The route `{$scheme}:{$domain}:{$method}:{$pattern}` conflicts with a previously ";
                            $error .= "defined one on `{$old->scheme()}:{$old->domain()}:{$old->method()}:{$pattern}`.";
                            throw new RouterException($error);
                        }
                    }
                }
            }
        }

        $all = [];

        foreach ($methods as $method) {
            $all += (isset($rules[$method]) ? $rules[$method] : []);
        }

        if ($route = $this->_dispatch($all, $request['path'])) {
            return $route->dispatch();
        }
        throw new RouterException("No route found for `{$request['scheme']}:{$request['domain']}:{$request['method']}:{$request['path']}`.", 404);
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
            list($route, $varNames, $domainVariables) = $combinedRule['map'][count($matches)];
            $variables = [];
            $i = 0;
            foreach ($varNames as $varName) {
                $variables[$varName] = $matches[++$i];
            }
            $route->params($domainVariables + $variables);
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
     * Checks if a domain matches a domain pattern.
     *
     * @param  string  $domain  The domain to check.
     * @param  string  $pattern The pattern to use for checking.
     * @return boolean          Returns `true` on success, false otherwise.
     */
    protected function _matchDomain($domain, $pattern, &$variables)
    {
        if ($pattern === '*') {
            $rules = [['.*', []]];
        } else {
            $parser = $this->_classes['parser'];
            $rules = $parser::parse($pattern, '[^.]+');
        }
        foreach ($rules as $rule) {
            if (preg_match('~^(?|' . $rule[0] . ')$~', $domain, $matches)) {
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
