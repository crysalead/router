<?php
namespace Lead\Router;

/**
 * The Route class.
 */
class Host
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The matching scheme.
     *
     * @var string
     */
    public $scheme = '*';

    /**
     * The matching host.
     *
     * @var string
     */
    public $host = '*';

    /**
     * Rules extracted from host's tokens structures.
     *
     * @see Parser::compile()
     * @var array
     */
    protected $_rule = null;

    /**
     * Constructs a route
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'scheme'     => '*',
            'host'       => '*',
            'classes'    => [
                'parser' => 'Lead\Router\Parser'
            ]
        ];
        $config += $defaults;

        $this->_classes = $config['classes'];

        $this->scheme = $config['scheme'];
        $this->host = $config['host'];
    }

    /**
     * Returns the compiled route.
     *
     * @return array A collection of route regex and their associated variable names.
     */
    public function rule()
    {
        if ($this->_rule !== null) {
            return $this->_rule;
        }

        if ($this->host !== '*') {
            $parser = $this->_classes['parser'];
            $token = $parser::tokenize($this->host, '.');
            $this->_rule = $parser::compile($token);
        } else {
            $this->_rule = [];
        }
        return $this->_rule;
    }

    /**
     * Checks if a host matches a host pattern.
     *
     * @param  string  $host          The host to check.
     * @param  string  $hostVariables The matches host variables
     * @return boolean                Returns `true` on success, false otherwise.
     */
    public function match($request, &$hostVariables = null)
    {
        $defaults = [
            'host'   => '*',
            'scheme' => '*'
        ];
        $request += $defaults;
        $scheme = $request['scheme'];
        $host = $request['host'];

        $hostVariables = [];

        if ($this->host === '*' || $host === '*') {
            return true;
        }
        if (!$rule = $this->rule()) {
            return true;
        }
        if (!preg_match('~^' . $rule[0] . '$~', $host, $matches)) {
            $hostVariables = null;
            return false;
        }
        $i = 0;

        foreach ($rule[1] as $name => $pattern) {
            $hostVariables[$name] = $matches[++$i];
        }
        return true;
    }
}