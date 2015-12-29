<?php
namespace Lead\Router;

class Routing
{
    const FOUND = 0;

    const NOT_FOUND = 404;

    const METHOD_NOT_ALLOWED = 405;

    /**
     * The routing error number.
     *
     * @var integer
     */
    protected $_error = 0;

    /**
     * The routing message.
     *
     * @var string
     */
    protected $_message = 'OK';

    /**
     * The attached route.
     *
     * @var object|null
     */
    protected $_route = null;

    /**
     * Constructor
     *
     * @param array $config The config array
     */
    public function __construct($config = [])
    {
        $defaults = [
            'error'    => static::FOUND,
            'message'  => 'OK',
            'route'    => null
        ];
        $config += $defaults;
        $this->_error = $config['error'];
        $this->_message = $config['message'];
        $this->_route = $config['route'];
    }

    /**
     * Gets the routing error number.
     *
     * @return integer The routing error.
     */
    public function error()
    {
        return $this->_error;
    }

    /**
     * Gets the routing message.
     *
     * @return string The routing message.
     */
    public function message()
    {
        return $this->_message;
    }

    /**
     * Gets the attached route.
     *
     * @return object|null A route instance or `null` when routing failed.
     */
    public function route()
    {
        return $this->_route;
    }
}
