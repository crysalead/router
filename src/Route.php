<?php
namespace Lead\Router;

use Closure;

class Route
{
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
     * Attached namespace.
     *
     * @var string
     */
    public $namespace = '';

    /**
     * Route's name.
     *
     * @var string
     */
    public $name = '';

    /**
     * Handler callable.
     *
     * @var mixed
     */
    public $handler = null;

    /**
     * Params.
     *
     * @var array
     */
    public $params = [];

    /**
     * Constructs a route
     *
     * @param string $httpMethod
     * @param mixed  $handler
     * @param string $regex
     * @param array  $variables
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
            'params'    => []
        ];
        $config += $defaults;

        $this->scheme($config['scheme']);
        $this->host($config['host']);
        $this->method($config['method']);
        $this->pattern($config['pattern']);
        $this->name($config['name']);
        $this->ns($config['namespace']);
        $this->params($config['params']);
        $this->handler($config['handler']);
    }

    /**
     * Gets/sets the route's scheme.
     *
     * @param  array      $scheme The scheme to set or none to get the setted one.
     * @return array|self
     */
    public function scheme($scheme = null)
    {
        if (func_num_args() === 0) {
            return $this->_scheme;
        }
        $this->_scheme = $scheme;
        return $this;
    }

    /**
     * Gets/sets the route's host.
     *
     * @param  array      $host The host to set or none to get the setted one.
     * @return array|self
     */
    public function host($host = null)
    {
        if (func_num_args() === 0) {
            return $this->_host;
        }
        $this->_host = $host;
        return $this;
    }

    /**
     * Gets/sets the route's method.
     *
     * @param  array      $method The method to set or none to get the setted one.
     * @return array|self
     */
    public function method($method = null)
    {
        if (func_num_args() === 0) {
            return $this->_method;
        }
        $this->_method = $method;
        return $this;
    }

    /**
     * Gets/sets the route's pattern.
     *
     * @param  array      $pattern The pattern to set or none to get the setted one.
     * @return array|self
     */
    public function pattern($pattern = null)
    {
        if (func_num_args() === 0) {
            return $this->_pattern;
        }
        $this->_pattern = $pattern;
        return $this;
    }

    /**
     * Gets/sets the route's name.
     *
     * @param  array      $name The name to set or none to get the setted one.
     * @return array|self
     */
    public function name($name = null)
    {
        if (func_num_args() === 0) {
            return $this->_name;
        }
        $this->_name = $name;
        return $this;
    }

    /**
     * Gets/sets the route's namespace.
     *
     * @param  array      $namespace The namespace to set or none to get the setted one.
     * @return array|self
     */
    public function ns($namespace = null)
    {
        if (func_num_args() === 0) {
            return $this->_namespace;
        }
        $this->_namespace = $namespace;
        return $this;
    }

    /**
     * Gets/sets the route's params.
     *
     * @param  array      $params The params to set or none to get the setted one.
     * @return array|self
     */
    public function params($params = null)
    {
        if (func_num_args() === 0) {
            return $this->_params;
        }
        $this->_params = $params;
        return $this;
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
        if ($handler instanceof Closure) {
            $handler = $handler->bindTo($this);
        }
        $this->_handler = $handler;
        return $this;
    }

    /**
     * Gets/sets the route's request.
     *
     * @param  array      $request The request to set or none to get the setted one.
     * @return array|self
     */
    public function request($request = null)
    {
        if (func_num_args() === 0) {
            return $this->_request;
        }
        $this->_request = $request;
        return $this;
    }

    /**
     * Dispatches the route.
     *
     * @param mixed  $request The dispatched request.
     * @return mixed
     */
    public function dispatch($request)
    {
        $handler = $this->handler();
        $this->request($request);
        return call_user_func_array($handler, $this->params());
    }
}
