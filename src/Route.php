<?php
namespace Lead\Router;

use Closure;

class Route
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Route's name.
     *
     * @var string
     */
    public $name = '';

    /**
     * Maching scheme.
     *
     * @var string
     */
    public $scheme = '*';

    /**
     * Matching host.
     *
     * @var string
     */
    public $host = '*';

    /**
     * Matching HTTP method.
     *
     * @var string
     */
    public $method = '*';

    /**
     * Pattern definition.
     *
     * @var string
     */
    public $pattern = '';

    /**
     * Route's arguments.
     *
     * @var array
     */
    public $args = [];

    /**
     * Named parameter.
     *
     * @var array
     */
    public $params = [];

    /**
     * Namespace.
     *
     * @var string
     */
    public $namespace = '';

    /**
     * Request.
     *
     * @var mixed
     */
    public $request = null;

    /**
     * Response.
     *
     * @var mixed
     */
    public $response = null;

    /**
     * Data extracted from route's pattern.
     *
     * @var string
     */
    protected $_data = null;

    /**
     * Rules extracted from route's data.
     *
     * @var string
     */
    protected $_rules = null;

    /**
     * Handler.
     *
     * @var Closure
     */
    protected $_handler = null;

    /**
     * Constructs a route
     *
     * @param array $config The config array.
     */
    public function __construct($config = []) {
        $defaults = [
            'scheme'    => '*',
            'host'      => '*',
            'method'    => '*',
            'pattern'   => '',
            'name'      => '',
            'namespace' => '',
            'handler'   => null,
            'params'    => [],
            'classes'   => [
                'parser' => 'Lead\Router\Parser'
            ]
        ];
        $config += $defaults;

        $this->_classes = $config['classes'];
        $this->scheme = $config['scheme'];
        $this->host = $config['host'];
        $this->method = $config['method'];
        $this->pattern = $config['pattern'];
        $this->name = $config['name'];
        $this->namespace = $config['namespace'];
        $this->params = $config['params'];
        $this->handler($config['handler']);
    }

    /**
     * Returns the route's data.
     *
     * @return array A collection of routes splited in segments.
     */
    public function data($matchAnything = 'args')
    {
        if ($this->_data === null) {
            $parser = $this->_classes['parser'];
            $this->_rules = null;
            $this->_data = $parser::parse($this->pattern, '[^/]+', $matchAnything);
        }
        return $this->_data;
    }

    /**
     * Returns the route's rules.
     *
     * @return array A collection of route patterns and their associated variable names.
     */
    public function rules()
    {
        if ($this->_data === null) {
            $parser = $this->_classes['parser'];
            $this->_rules = $parser::rules($this->data());
        }
        return $this->_rules;
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
     * Dispatches the route.
     *
     * @param  mixed $response The outgoing response.
     * @return mixed
     */
    public function dispatch($response = null)
    {
        $this->response = $response;
        $handler = $this->handler();
        return $handler($this, $response);
    }

    /**
     * Returns the route link
     *
     * @param  array  $params  The route parameters.
     * @param  array  $options Options for generating the proper prefix. Accepted values are:
     *                         - `'absolute'` _boolean_: `true` or `false`
     *                         - `'scheme'`   _string_ : The scheme
     *                         - `'host'`     _string_ : The host name
     *                         - `'basePath'` _string_ : The base path
     * @return string          The prefixed path, depending on the passed options.
     */
    public function link($params = [], $options = [])
    {
        $defaults = [
            'absolute' => false,
            'scheme'   => 'http',
            'host'     => 'localhost',
            'basePath' => '',
            'query'    => '',
            'fragment' => ''
        ];
        $options += [
            'scheme' => $this->scheme,
            'host'   => $this->host
        ];

        $options = array_filter($options, function($value) { return $value !== '*'; });

        $options += $defaults;

        $data = array_reverse($this->data());

        foreach ($data as $segments) {
            $link = '';
            $missing = null;

            foreach ($segments as $segment) {
                if (is_string($segment)) {
                    $link .= $segment;
                    continue;
                }
                if (!array_key_exists($segment[0], $params)) {
                    $missing = $segment[0];
                    break;
                }
                $link .= $params[$segment[0]];
            }
            if (!$missing) {
                break;
            }
        }
        if (!empty($missing)) {
            throw new RouterException("Missing parameters `'{$segment[0]}'` for route: `'{$this->name}#{$this->pattern}'`.");
        }
        $basePath = trim($options['basePath'], '/');
        if ($options['basePath']) {
            $basePath = '/' . $basePath;
        }
        $link = isset($link) ? ltrim($link, '/') : '';
        $link = $basePath . ($link ? '/' . $link : $link);
        $query = $options['query'] ? '?' . $options['query'] : '';
        $fragment = $options['fragment'] ? '#' . $options['fragment'] : '';

        if ($options['absolute']) {
            $scheme = $options['scheme'] ? $options['scheme'] . '://' : '//';
            $link = "{$scheme}{$options['host']}{$link}";
        }

        return $link . $query . $fragment;
    }

}
