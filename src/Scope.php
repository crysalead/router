<?php
declare(strict_types=1);

namespace Lead\Router;

/**
 * Scope
 */
class Scope implements ScopeInterface
{
    /**
     * The router instance.
     *
     * @var object
     */
    protected $router = null;

    /**
     * The parent instance.
     *
     * @var object
     */
    protected $parent = null;

    /**
     * The middleware array.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * The scope data.
     *
     * @var array
     */
    protected $scope = [];

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

        $this->router = $config['router'];
        $this->parent = $config['parent'];
        $this->middleware = $config['middleware'];
        $this->scope = $config['scope'] + [
            'name'           => '',
            'scheme'         => '*',
            'host'           => '*',
            'methods'        => '*',
            'prefix'         => '',
            'namespace'      => '',
            'persist'        => []
        ];
    }

    /**
     * Creates a new sub scope based on the instance scope.
     *
     * @param  array $options The route options to scopify.
     * @return $this          The new sub scope.
     */
    public function seed(array $options): ScopeInterface
    {
        return new static(
            [
            'router' => $this->router,
            'parent' => $this,
            'scope'  => $this->scopify($options)
            ]
        );
    }

    /**
     * Scopes an options array according to the instance scope data.
     *
     * @param  array $options The options to scope.
     * @return array          The scoped options.
     */
    public function scopify(array $options): array
    {
        $scope = $this->scope;

        if (!empty($options['name'])) {
            $options['name'] = $scope['name'] ? $scope['name'] . '.' . $options['name'] : $options['name'];
        }

        if (!empty($options['prefix'])) {
            $options['prefix'] = $scope['prefix'] . trim($options['prefix'], '/');
            $options['prefix'] = $options['prefix'] ? $options['prefix'] . '/' : '';
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
        foreach ($this->middleware as $middleware) {
            yield $middleware;
        }
        if ($this->parent) {
            foreach ($this->parent->middleware() as $middleware) {
                yield $middleware;
            }
        }
    }

    /**
     * Adds a middleware to the list of middleware
     *
     * @param object|\Closure A callable middleware
     * @return \Lead\Router\ScopeInterface
     */
    public function apply($middleware): ScopeInterface
    {
        foreach (func_get_args() as $mw) {
            array_unshift($this->middleware, $mw);
        }

        return $this;
    }

    /**
     * Delegates calls to the router instance
     *
     * @param string $name The method name
     * @param array $params The parameters
     * @return mixed
     */
    public function __call(string $name, array $params)
    {
        $this->router->pushScope($this);
        $result = call_user_func_array([$this->router, $name], $params);
        $this->router->popScope();

        return $result;
    }
}
