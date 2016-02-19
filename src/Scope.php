<?php
namespace Lead\Router;

class Scope
{
    /**
     * The router instance.
     *
     * @var object
     */
    protected $_router = null;

    /**
     * The parent instance.
     *
     * @var object
     */
    protected $_parent = null;

    /**
     * The middleware array.
     *
     * @var array
     */
    protected $_middleware = [];

    /**
     * The scope data.
     *
     * @var array
     */
    protected $_scope = [];

    /**
     * The constructor.
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'router' => null,
            'parent' => null,
            'middleware' => [],
            'scope'  => []
        ];
        $config += $defaults;

        $this->_router = $config['router'];
        $this->_parent = $config['parent'];
        $this->_middleware = $config['middleware'];
        $this->_scope = $config['scope'] + [
            'name'           => '',
            'scheme'         => '*',
            'host'           => '*',
            'methods'        => '*',
            'prefix'         => '/',
            'namespace'      => '',
            'persist'        => []
        ];
    }

    /**
     * Creates a new sub scope based on the instance scope.
     *
     * @param  array  $options The route options to scopify.
     * @return object          The new sub scope.
     */
    public function seed($options)
    {
        return new static([
            'router' => $this->_router,
            'parent' => $this,
            'scope'  => $this->scopify($options)
        ]);
    }

    /**
     * Scopes an options array according to the instance scope data.
     *
     * @param  array $options The options to scope.
     * @return array          The scoped options.
     */
    public function scopify($options)
    {
        $scope = $this->_scope;

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

        return $options + $scope;
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
        if ($this->_parent) {
            foreach ($this->_parent->middleware() as $middleware) {
                yield $middleware;
            }
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
            array_unshift($this->_middleware, $mw);
        }
        return $this;
    }

    /**
     * Delegates calls to the router instance.
     *
     * @param  string $name   The method name.
     * @param  array  $params The parameters.
     */
    public function __call($name, $params)
    {
        $this->_router->pushScope($this);
        $result = call_user_func_array([$this->_router, $name], $params);
        $this->_router->popScope();
        return $result;
    }
}