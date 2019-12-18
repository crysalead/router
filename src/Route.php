<?php

declare(strict_types=1);

namespace Lead\Router;

use Closure;
use Generator;
use InvalidArgumentException;
use Lead\Router\Exception\RouterException;
use RuntimeException;

/**
 * The Route class.
 */
class Route implements RouteInterface
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $classes = [];

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
     * @var \Lead\Router\ScopeInterface|null
     */
    protected $scope = null;

    /**
     * The route's host.
     *
     * @var \Lead\Router\HostInterface|null
     */
    protected $host = null;

    /**
     * Route's allowed methods.
     *
     * @var array
     */
    protected $methods = [];

    /**
     * Route's prefix.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Route's pattern.
     *
     * @var string
     */
    protected $pattern = '';

    /**
     * The tokens structure extracted from route's pattern.
     *
     * @see Parser::tokenize()
     * @var array|null
     */
    protected $token = null;

    /**
     * The route's regular expression pattern.
     *
     * @see Parser::compile()
     * @var string|null
     */
    protected $regex = null;

    /**
     * The route's variables.
     *
     * @see Parser::compile()
     * @var array|null
     */
    protected $variables = null;

    /**
     * The route's handler to execute when a request match.
     *
     * @var \Closure|null
     */
    protected $handler = null;

    /**
     * The middlewares.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * Attributes
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Constructs a route
     *
     * @param array $config The config array.
     */
    public function __construct(array $config = [])
    {
        $config = $this->defaultConfig($config);

        $this->classes = $config['classes'];
        $this->setNamespace($config['namespace']);
        $this->setName($config['name']);
        $this->setParams($config['params']);
        $this->setPersistentParams($config['persist']);
        $this->setHandler($config['handler']);
        $this->setPrefix($config['prefix']);
        $this->setHost($config['host'], $config['scheme']);
        $this->setMethods($config['methods']);
        $this->setScope($config['scope']);
        $this->setPattern($config['pattern']);
        $this->setMiddleware((array)$config['middleware']);
    }

    /**
     * Sets the middlewares
     *
     * @param array $middleware Middlewares
     * @return \Lead\Router\Route
     */
    public function setMiddleware(array $middleware)
    {
        $this->middleware = (array)$middleware;

        return $this;
    }

    /**
     * Gets the default config
     *
     * @param array $config Values to merge
     * @return array
     */
    protected function defaultConfig($config = []): array
    {
        $defaults = [
            'scheme' => '*',
            'host' => null,
            'methods' => '*',
            'prefix' => '',
            'pattern' => '',
            'name' => '',
            'namespace' => '',
            'handler' => null,
            'params' => [],
            'persist' => [],
            'scope' => null,
            'middleware' => [],
            'classes' => [
                'parser' => 'Lead\Router\Parser',
                'host' => 'Lead\Router\Host'
            ]
        ];
        $config += $defaults;

        return $config;
    }

    /**
     * Sets a route attribute
     *
     * This method can be used to set arbitrary date attributes to a route.
     *
     * @param string $name Name
     * @param mixed $value Value
     * @return \Lead\Router\RouteInterface
     */
    public function setAttribute($name, $value): RouteInterface
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Gets a route attribute
     *
     * @param string $name Attribute name
     * @return mixed
     */
    public function attribute(string $name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return null;
    }

    /**
     * Sets namespace
     *
     * @param string $namespace Namespace
     * @return self
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get namespace
     *
     * @return string
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * Sets params
     *
     * @param array $params Params
     * @return self
     */
    public function setParams(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Get parameters
     *
     * @return array
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Sets persistent params
     *
     * @param array $params Params
     * @return self
     */
    public function setPersistentParams(array $params): self
    {
        $this->persist = $params;

        return $this;
    }

    /**
     * Get persistent parameters
     *
     * @return array
     */
    public function persistentParams(): array
    {
        return $this->persist;
    }

    /**
     * Gets the routes name
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Sets the routes name
     *
     * @param string $name Name
     * @return self
     */
    public function setName(string $name): RouteInterface
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the prefix
     *
     * @return string
     */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Sets the routes prefix
     *
     * @param string $prefix Prefix
     * @return self
     */
    public function setPrefix(string $prefix): RouteInterface
    {
        $this->prefix = trim($prefix, '/');
        if ($this->prefix) {
            $this->prefix = '/' . $this->prefix;
        }

        return $this;
    }

    /**
     * Gets the host
     *
     * @return mixed
     */
    public function host(): ?HostInterface
    {
        return $this->host;
    }

    /**
     * Sets the route host.
     *
     * @param string|\Lead\Router\HostInterface $host The host instance to set or none to get the set one
     * @param string $scheme HTTP Scheme
     * @return $this The current host on get or `$this` on set
     */
    public function setHost($host = null, string $scheme = '*'): RouteInterface
    {
        if (!is_string($host) && $host instanceof Host && $host !== null) {
            throw new InvalidArgumentException();
        }

        if ($host instanceof HostInterface || $host === null) {
            $this->host = $host;

            return $this;
        }

        if ($host !== '*' || $scheme !== '*') {
            $class = $this->classes['host'];
            $host = new $class(['scheme' => $scheme, 'pattern' => $host]);
            if (!$host instanceof HostInterface) {
                throw new RuntimeException('Must be an instance of HostInterface');
            }
            $this->host = $host;

            return $this;
        }

        $this->host = null;

        return $this;
    }

    /**
     * Gets allowed methods
     *
     * @return array
     */
    public function methods(): array
    {
        return array_keys($this->methods);
    }

    /**
     * Sets methods
     *
     * @param  string|array $methods
     * @return self
     */
    public function setMethods($methods): self
    {
        $methods = $methods ? (array)$methods : [];
        $methods = array_map('strtoupper', $methods);
        $methods = array_fill_keys($methods, true);

        foreach ($methods as $method) {
            if (is_string($method) && !in_array($method, self::VALID_METHODS, true)) {
                throw new InvalidArgumentException(sprintf('`%s` is not an allowed HTTP method', $method));
            }
        }

        $this->methods = $methods;

        return $this;
    }

    /**
     * Allows additional methods.
     *
     * @param  string|array $methods The methods to allow.
     * @return self
     */
    public function allow($methods = [])
    {
        $methods = $methods ? (array)$methods : [];
        $methods = array_map('strtoupper', $methods);
        $methods = array_fill_keys($methods, true) + $this->methods;

        foreach ($methods as $method) {
            if (!in_array($method, self::VALID_METHODS)) {
                throw new InvalidArgumentException(sprintf('`%s` is not an allowed HTTP method', $method));
            }
        }

        $this->methods = $methods;

        return $this;
    }

    /**
     * Gets the routes Scope
     *
     * @return \Lead\Router\Scope
     */
    public function scope(): ?ScopeInterface
    {
        return $this->scope;
    }

    /**
     * Sets a routes scope
     *
     * @param  \Lead\Router\Scope|null $scope Scope
     * @return $this;
     */
    public function setScope(?Scope $scope): RouteInterface
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Gets the routes pattern
     *
     * @return string
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Sets the routes pattern
     *
     * @return $this
     */
    public function setPattern(string $pattern): RouteInterface
    {
        $this->token = null;
        $this->regex = null;
        $this->variables = null;

        if (!$pattern || $pattern[0] !== '[') {
            $pattern = '/' . trim($pattern, '/');
        }

        $this->pattern = $this->prefix . $pattern;

        return $this;
    }

    /**
     * Returns the route's token structures.
     *
     * @return array A collection route's token structure.
     */
    public function token(): array
    {
        if ($this->token === null) {
            $parser = $this->classes['parser'];
            $this->token = [];
            $this->regex = null;
            $this->variables = null;
            $this->token = $parser::tokenize($this->pattern, '/');
        }

        return $this->token;
    }

    /**
     * Gets the route's regular expression pattern.
     *
     * @return string the route's regular expression pattern.
     */
    public function regex(): string
    {
        if ($this->regex !== null) {
            return $this->regex;
        }
        $this->compile();

        return $this->regex;
    }

    /**
     * Gets the route's variables and their associated pattern in case of array variables.
     *
     * @return array The route's variables and their associated pattern.
     */
    public function variables(): array
    {
        if ($this->variables !== null) {
            return $this->variables;
        }
        $this->compile();

        return $this->variables;
    }

    /**
     * Compiles the route's patten.
     */
    protected function compile(): void
    {
        $parser = $this->classes['parser'];
        $rule = $parser::compile($this->token());
        $this->regex = $rule[0];
        $this->variables = $rule[1];
    }

    /**
     * Gets the routes handler
     *
     * @return mixed
     */
    public function handler()
    {
        return $this->handler;
    }

    /**
     * Gets/sets the route's handler.
     *
     * @param mixed $handler The route handler.
     * @return self
     */
    public function setHandler($handler): RouteInterface
    {
        if (!is_callable($handler) && !is_string($handler) && $handler !== null) {
            throw new InvalidArgumentException('Handler must be a callable, string or null');
        }

        $this->handler = $handler;

        return $this;
    }

    /**
     * Checks if the route instance matches a request.
     *
     * @param  array $request a request.
     * @return bool
     */
    public function match($request, &$variables = null, &$hostVariables = null): bool
    {
        $hostVariables = [];

        if (($host = $this->host()) && !$host->match($request, $hostVariables)) {
            return false;
        }

        $path = isset($request['path']) ? $request['path'] : '';
        $method = isset($request['method']) ? $request['method'] : '*';

        if (!isset($this->methods['*']) && $method !== '*' && !isset($this->methods[$method])) {
            if ($method !== 'HEAD' && !isset($this->methods['GET'])) {
                return false;
            }
        }

        $path = '/' . trim($path, '/');

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
    protected function _buildVariables(array $values): array
    {
        $variables = [];
        $parser = $this->classes['parser'];

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
                    foreach ($parts[1] as $value) {
                        if (strpos($value, '/') !== false) {
                            $variables[$name][] = explode('/', $value);
                        } else {
                            $variables[$name][] = $value;
                        }
                    }
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
     * @return mixed The handler return value.
     */
    public function dispatch($response = null)
    {
        $this->response = $response;
        $request = $this->request;

        $generator = $this->middleware();

        $next = function () use ($request, $response, $generator, &$next) {
            $handler = $generator->current();
            $generator->next();

            return $handler($request, $response, $next);
        };

        return $next();
    }

    /**
     * Middleware generator.
     *
     * @return \Generator
     */
    public function middleware(): Generator
    {
        foreach ($this->middleware as $middleware) {
            yield $middleware;
        }

        $scope = $this->scope();
        if ($scope !== null) {
            foreach ($scope->middleware() as $middleware) {
                yield $middleware;
            }
        }

        yield function () {
            $handler = $this->handler();
            if ($handler === null) {
                return null;
            }

            return $handler($this, $this->response);
        };
    }

    /**
     * Adds a middleware to the list of middleware.
     *
     * @param object|Closure A callable middleware.
     * @return $this
     */
    public function apply($middleware)
    {
        foreach (func_get_args() as $mw) {
            array_unshift($this->middleware, $mw);
        }

        return $this;
    }

    /**
     * Returns the route's link.
     *
     * @param  array $params  The route parameters.
     * @param  array $options Options for generating the proper prefix. Accepted values are:
     *                        - `'absolute'` _boolean_: `true` or `false`. - `'scheme'`
     *                        _string_ : The scheme. - `'host'`     _string_ : The host
     *                        name. - `'basePath'` _string_ : The base path. - `'query'`
     *                        _string_ : The query string. - `'fragment'` _string_ : The
     *                        fragment string.
     * @return string          The link.
     */
    public function link(array $params = [], array $options = []): string
    {
        $defaults = [
            'absolute' => false,
            'basePath' => '',
            'query' => '',
            'fragment' => ''
        ];

        $options = array_filter(
            $options,
            function ($value) {
                return $value !== '*';
            }
        );
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
            if ($this->host !== null) {
                $link = $this->host->link($params, $options) . "{$link}";
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
     * @param  array $token  The token structure array.
     * @param  array $params The route parameters.
     * @return string The URL path representation of the token structure array.
     */
    protected function _link(array $token, array $params): string
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
                    $values = isset($params[$name]) && $params[$name] !== null ? (array)$params[$name] : [];
                    if (!$values && !$child['optional']) {
                        throw new RouterException("Missing parameters `'{$name}'` for route: `'{$this->name}#{$this->pattern}'`.");
                    }
                    foreach ($values as $value) {
                        $link .= $this->_link($child, [$name => $value] + $params);
                    }
                } else {
                    $link .= $this->_link($child, $params);
                }
                continue;
            }

            if (!isset($params[$child['name']])) {
                if (!$token['optional']) {
                    throw new RouterException("Missing parameters `'{$child['name']}'` for route: `'{$this->name}#{$this->pattern}'`.");
                }

                return '';
            }

            if ($data = $params[$child['name']]) {
                $parts = is_array($data) ? $data : [$data];
            } else {
                $parts = [];
            }
            foreach ($parts as $key => $value) {
                $parts[$key] = rawurlencode((string)$value);
            }
            $value = join('/', $parts);

            if (!preg_match('~^' . $child['pattern'] . '$~', $value)) {
                throw new RouterException("Expected `'" . $child['name'] . "'` to match `'" . $child['pattern'] . "'`, but received `'" . $value . "'`.");
            }
            $link .= $value;
        }

        return $link;
    }
}
