<?php
namespace Lead\Router;

use Closure;

/**
 * The Route class.
 */
class Route
{
    const FOUND = 0;

    const NOT_FOUND = 404;

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The route's error.
     *
     * @var integer
     */
    protected $_error = 0;

    /**
     * The route's message.
     *
     * @var string
     */
    protected $_message = 'OK';

    /**
     * Route's name.
     *
     * @var string
     */
    public $name = '';

    /**
     * Named parameter.
     *
     * @var array
     */
    public $params = [];

    /**
     * List of parameters that should persist during dispatching.
     *
     * @var array
     */
    public $persist = [];

    /**
     * The attached namespace.
     *
     * @var string
     */
    public $namespace = '';

    /**
     * The attached request.
     *
     * @var mixed
     */
    public $request = null;

    /**
     * The attached response.
     *
     * @var mixed
     */
    public $response = null;

    /**
     * The dispatched instance (custom).
     *
     * @var object
     */
    public $dispatched = null;

    /**
     * The route scope.
     *
     * @var array
     */
    protected $_scope = null;

    /**
     * The route's host.
     *
     * @var object
     */
    protected $_host = null;

    /**
     * Route's allowed methods.
     *
     * @var array
     */
    protected $_methods = [];

    /**
     * Route's prefix.
     *
     * @var array
     */
    protected $_prefix = '';

    /**
     * Route's pattern.
     *
     * @var string
     */
    protected $_pattern = '';

    /**
     * The tokens structure extracted from route's pattern.
     *
     * @see Parser::tokenize()
     * @var array
     */
    protected $_token = null;

    /**
     * The route's regular expression pattern.
     *
     * @see Parser::compile()
     * @var string
     */
    protected $_regex = null;

    /**
     * The route's variables.
     *
     * @see Parser::compile()
     * @var array
     */
    protected $_variables = null;

    /**
     * The route's handler to execute when a request match.
     *
     * @var Closure
     */
    protected $_handler = null;

    /**
     * The middlewares.
     *
     * @var array
     */
    protected $_middleware = [];

    /**
     * Constructs a route
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'error'          => static::FOUND,
            'message'        => 'OK',
            'scheme'         => '*',
            'host'           => null,
            'methods'        => '*',
            'prefix'         => '',
            'pattern'        => '',
            'name'           => '',
            'namespace'      => '',
            'handler'        => null,
            'params'         => [],
            'persist'        => [],
            'scope'          => null,
            'middleware'     => [],
            'classes'        => [
                'parser' => 'Lead\Router\Parser',
                'host'   => 'Lead\Router\Host'
            ]
        ];
        $config += $defaults;

        $this->name = $config['name'];
        $this->namespace = $config['namespace'];
        $this->params = $config['params'];
        $this->persist = $config['persist'];
        $this->handler($config['handler']);

        $this->_classes = $config['classes'];

        $this->_prefix = $config['prefix'];

        $this->host($config['host'], $config['scheme']);
        $this->methods($config['methods']);

        $this->_scope = $config['scope'];
        $this->_middleware = (array) $config['middleware'];
        $this->_error = $config['error'];
        $this->_message = $config['message'];

        $this->pattern($config['pattern']);
    }

    /**
     * Gets/sets the route host.
     *
     * @param  object      $host The host instance to set or none to get the setted one.
     * @return object|self       The current host on get or `$this` on set.
     */
    public function host($host = null, $scheme = '*')
    {
        if (!func_num_args()) {
            return $this->_host;
        }
        if (!is_string($host)) {
            $this->_host = $host;
            return $this;
        }
        if ($host !== '*' || $scheme !== '*') {
            $class = $this->_classes['host'];
            $this->_host = new $class(['scheme' => $scheme, 'pattern' => $host]);
        }
        return $this;
    }

    /**
     * Gets/sets the allowed methods.
     *
     * @param  string|array $allowedMethods The allowed methods set or none to get the setted one.
     * @return array|self                   The allowed methods on get or `$this` on set.
     */
    public function methods($methods = null)
    {
        if (!func_num_args()) {
            return array_keys($this->_methods);
        }
        $methods = $methods ? (array) $methods : [];
        $this->_methods = array_fill_keys($methods, true);
        return $this;
    }

    /**
     * Allows additionnal methods.
     *
     * @param  string|array $methods The methods to allow.
     * @return self
     */
    public function allow($methods = [])
    {
        $methods = $methods ? (array) $methods : [];
        $this->_methods = array_fill_keys($methods, true) + $this->_methods;
         return $this;
    }

    /**
     * Gets/sets the route scope.
     *
     * @param  object      $scope The scope instance to set or none to get the setted one.
     * @return object|self        The current scope on get or `$this` on set.
     */
    public function scope($scope = null)
    {
        if (!func_num_args()) {
            return $this->_scope;
        }
        $this->_scope = $scope;
        return $this;
    }

    /**
     * Gets the routing error number.
     *
     * @return integer The routing error number.
     */
    public function error()
    {
        return $this->_error;
    }

    /**
     * Gets the routing error message.
     *
     * @return string The routing error message.
     */
    public function message()
    {
        return $this->_message;
    }

    /**
     * Gets the route's pattern.
     *
     * @return array The route's pattern.
     */
    public function pattern($pattern = null)
    {
        if (!func_num_args()) {
            return $this->_pattern;
        }
        $this->_token = null;
        $this->_regex = null;
        $this->_variables = null;
        $this->_pattern = $this->_prefix . ltrim($pattern, '/');
        return $this;
    }

    /**
     * Returns the route's token structures.
     *
     * @return array A collection route's token structure.
     */
    public function token()
    {
        if ($this->_token === null) {
            $parser = $this->_classes['parser'];
            $this->_token = [];
            $this->_regex = null;
            $this->_variables = null;
            $this->_token = $parser::tokenize($this->_pattern, '/');
        }
        return $this->_token;
    }

    /**
     * Gets the route's regular expression pattern.
     *
     * @return string the route's regular expression pattern.
     */
    public function regex()
    {
        if ($this->_regex !== null) {
            return $this->_regex;
        }
        $this->_compile();
        return $this->_regex;
    }

    /**
     * Gets the route's variables and their associated pattern in case of array variables.
     *
     * @return array The route's variables and their associated pattern.
     */
    public function variables()
    {
        if ($this->_variables !== null) {
            return $this->_variables;
        }
        $this->_compile();
        return $this->_variables;
    }

    /**
     * Compiles the route's patten.
     */
    protected function _compile()
    {
        $parser = $this->_classes['parser'];
        $rule = $parser::compile($this->token());
        $this->_regex = $rule[0];
        $this->_variables = $rule[1];
    }

    /**
     * Gets/sets the route's handler.
     *
     * @param  array      $handler The route handler.
     * @return array|self
     */
    public function handler($handler = null)
    {
        if (func_num_args() === 0) {
            return $this->_handler;
        }
        $this->_handler = $handler;
        return $this;
    }

    /**
     * Checks if the route instance matches a request.
     *
     * @param  array   $request a request.
     * @return boolean
     */
    public function match($request, &$variables = null, &$hostVariables = null)
    {
        $hostVariables = [];

        if (($host = $this->host()) && !$host->match($request, $hostVariables)) {
            return false;
        }

        $path = isset($request['path']) ? $request['path'] : '';
        $method = isset($request['method']) ? $request['method'] : '*';

        if (!isset($this->_methods['*']) && $method !== '*' && !isset($this->_methods[$method])) {
            if ($method !== 'HEAD' && !isset($this->_methods['GET'])) {
                return false;
            }
        }

        if (!preg_match('~^' . $this->regex() . '$~', $path, $matches)) {
            return false;
        }
        $variables = $this->_buildVariables($matches);
        $this->params = $hostVariables + $variables;
        return true;
    }

    /**
     * Combines route's variables names with the regex matched route's values.
     *
     * @param  array $varNames The variable names array with their corresponding pattern segment when applicable.
     * @param  array $values   The matched values.
     * @return array           The route's variables.
     */
    protected function _buildVariables($values)
    {
        $variables = [];
        $parser = $this->_classes['parser'];

        $i = 1;
        foreach ($this->variables() as $name => $pattern) {
            if (!isset($values[$i])) {
                $variables[$name] = !$pattern ? null : [];
                continue;
            }
            if (!$pattern) {
                $variables[$name] = $values[$i] ?: null;
            } else {
                $token = $parser::tokenize($pattern, '/');
                $rule = $parser::compile($token);
                if (preg_match_all('~' . $rule[0] . '~', $values[$i], $parts)) {
                    $variables[$name] = $parts[1];
                } else {
                    $variables[$name] = [];
                }
            }
            $i++;
        }
        return $variables;
    }

    /**
     * Dispatches the route.
     *
     * @param  mixed $response The outgoing response.
     * @return mixed           The handler return value.
     */
    public function dispatch($response = null)
    {
        if ($error = $this->error()) {
            throw new RouterException($this->message(), $error);
        }
        $this->response = $response;
        $request = $this->request;

        $generator = $this->middleware();

        $next = function() use ($request, $response, $generator, &$next) {
            $handler = $generator->current();
            $generator->next();
            return $handler($request, $response, $next);
        };
        return $next();
    }

    /**
     * Middleware generator.
     *
     * @return callable
     */
    public function middleware()
    {
        foreach ($this->_middleware as $middleware) {
            yield $middleware;
        }

        if ($scope = $this->scope()) {
            foreach ($scope->middleware() as $middleware) {
                yield $middleware;
            }
        }

        yield function() {
            $handler = $this->handler();
            return $handler($this, $this->response);
        };
    }

    /**
     * Adds a middleware to the list of middleware.
     *
     * @param object|Closure A callable middleware.
     */
    public function apply($middleware)
    {
        foreach (func_get_args() as $mw) {
            array_unshift($this->_middleware, $mw);
        }
        return $this;
    }

    /**
     * Returns the route's link.
     *
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
    public function link($params = [], $options = [])
    {
        $defaults = [
            'absolute' => false,
            'basePath' => '',
            'query'    => '',
            'fragment' => ''
        ];

        $options = array_filter($options, function($value) { return $value !== '*'; });
        $options += $defaults;

        $params = $params + $this->params;

        $link = $this->_link($this->token(), $params);

        $basePath = trim($options['basePath'], '/');
        if ($basePath) {
            $basePath = '/' . $basePath;
        }
        $link = isset($link) ? ltrim($link, '/') : '';
        $link = $basePath . ($link ? '/' . $link : $link);
        $query = $options['query'] ? '?' . $options['query'] : '';
        $fragment = $options['fragment'] ? '#' . $options['fragment'] : '';

        if ($options['absolute']) {
            if ($host = $this->host()) {
                $link = $host->link($params, $options) . "{$link}";
            } else {
                $scheme = !empty($options['scheme']) ? $options['scheme'] . '://' : '//';
                $host = isset($options['host']) ? $options['host'] : 'localhost';
                $link = "{$scheme}{$host}{$link}";
            }
        }

        return $link . $query . $fragment;
    }

    /**
     * Helper for `Route::link()`.
     *
     * @param  array  $token    The token structure array.
     * @param  array  $params   The route parameters.
     * @return string           The URL path representation of the token structure array.
     */
    protected function _link($token, $params)
    {
        $link = '';
        foreach ($token['tokens'] as $child) {
            if (is_string($child)) {
                $link .= $child;
                continue;
            }
            if (isset($child['tokens'])) {
                if ($child['repeat']) {
                    $name = $child['repeat'];
                    $values = isset($params[$name]) && $params[$name] !== null ? (array) $params[$name] : [];
                    if (!$values && !$child['optional']) {
                        throw new RouterException("Missing parameters `'{$name}'` for route: `'{$this->name}#/{$this->_pattern}'`.");
                    }
                    foreach ($values as $value) {
                        $link .= $this->_link($child, [$name => $value] + $params);
                    }
                } else {
                    $link .= $this->_link($child, $params);
                }
                continue;
            }

            if (!array_key_exists($child['name'], $params)) {
                if (!$token['optional']) {
                    throw new RouterException("Missing parameters `'{$child['name']}'` for route: `'{$this->name}#/{$this->_pattern}'`.");
                }
                return '';
            }
            if (is_array($params[$child['name']])) {
                throw new RouterException("Expected `'" . $child['name'] . "'` to not repeat, but received `[" . join(',', $params[$child['name']]) . "]`.");
            }
            $value = rawurlencode($params[$child['name']]);
            if (!preg_match('~^' . $child['pattern'] . '$~', $value)) {
                throw new RouterException("Expected `'" . $child['name'] . "'` to match `'" . $child['pattern'] . "'`, but received `'" . $value . "'`.");
            }
            $link .= $params[$child['name']];
        }
        return $link;
    }
}
