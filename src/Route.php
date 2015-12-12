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
     * Matching doamin.
     *
     * @var string
     */
    public $domain = '*';

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
            'domain'    => '*',
            'method'    => '*',
            'pattern'   => '',
            'name'      => '',
            'namespace' => '',
            'handler'   => null,
            'params'    => []
        ];
        $config += $defaults;

        $this->scheme($config['scheme']);
        $this->domain($config['domain']);
        $this->method($config['method']);
        $this->pattern($config['pattern']);
        $this->name($config['name']);
        $this->ns($config['namespace']);
        $this->params($config['params']);
        $this->handler($config['handler']);
    }

    /**
     * Gets/sets the route scheme.
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
     * Gets/sets the route domain.
     *
     * @param  array      $domain The domain to set or none to get the setted one.
     * @return array|self
     */
    public function domain($domain = null)
    {
        if (func_num_args() === 0) {
            return $this->_domain;
        }
        $this->_domain = $domain;
        return $this;
    }

    /**
     * Gets/sets the route method.
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
     * Gets/sets the route pattern.
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
     * Gets/sets the route name.
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
     * Gets/sets the route namespace.
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
     * Gets/sets the route params.
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
     * Gets/sets the route handler.
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
     * Dispatches the route.
     *
     * @return mixed
     */
    public function dispatch()
    {
        $handler = $this->_handler;
        return call_user_func_array($handler, $this->params());
    }
}
