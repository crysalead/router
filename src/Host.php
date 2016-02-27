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
    protected $_scheme = '*';

    /**
     * The matching host.
     *
     * @var string
     */
    protected $_pattern = '*';

    /**
     * The tokens structure extracted from host's pattern.
     *
     * @see Parser::tokenize()
     * @var array
     */
    protected $_token = null;

    /**
     * The host's regular expression pattern.
     *
     * @see Parser::compile()
     * @var string
     */
    protected $_regex = null;

    /**
     * The host's variables.
     *
     * @see Parser::compile()
     * @var array
     */
    protected $_variables = null;

    /**
     * Constructs a route
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'scheme'     => '*',
            'pattern'     => '*',
            'classes'    => [
                'parser' => 'Lead\Router\Parser'
            ]
        ];
        $config += $defaults;

        $this->_classes = $config['classes'];

        $this->scheme($config['scheme']);
        $this->pattern($config['pattern']);
    }

    /**
     * Get/sets the host's scheme.
     *
     * @param  string      $scheme The scheme on set or none to get the setted one.
     * @return string|self         The scheme on get or `$this` on set.
     */
    public function scheme($scheme = null)
    {
        if (!func_num_args()) {
            return $this->_scheme;
        }
        $this->_scheme = $scheme;
        return $this;
    }

    /**
     * Get/sets the host's pattern.
     *
     * @param  string      $pattern The pattern on set or none to get the setted one.
     * @return string|self          The pattern on get or `$this` on set.
     */
    public function pattern($pattern = null)
    {
        if (!func_num_args()) {
            return $this->_pattern;
        }
        $this->_token = null;
        $this->_regex = null;
        $this->_variables = null;
        $this->_pattern = $pattern;
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
            $this->_token = $parser::tokenize($this->_pattern, '.');
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
     * Compiles the host's patten.
     */
    protected function _compile()
    {
        if ($this->pattern() === '*') {
            return;
        }
        $parser = $this->_classes['parser'];
        $rule = $parser::compile($this->token());
        $this->_regex = $rule[0];
        $this->_variables = $rule[1];
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

        $anyHost = $this->pattern() === '*' || $host === '*';
        $anyScheme = $this->scheme() === '*' || $scheme === '*';


        if ($anyHost) {
            if ($this->variables()) {
                $hostVariables = array_fill_keys(array_keys($this->variables()), null);
            }
            return $anyScheme || $this->scheme() === $scheme;
        }

        if (!$anyScheme && $this->scheme() !== $scheme) {
            return false;
        }

        if (!preg_match('~^' . $this->regex() . '$~', $host, $matches)) {
            $hostVariables = null;
            return false;
        }
        $i = 0;

        foreach ($this->variables() as $name => $pattern) {
            $hostVariables[$name] = $matches[++$i];
        }
        return true;
    }

    /**
     * Returns the host's link.
     *
     * @param  array  $params  The host parameters.
     * @param  array  $options Options for generating the proper prefix. Accepted values are:
     *                         - `'scheme'`   _string_ : The scheme.
     *                         - `'host'`     _string_ : The host name.
     * @return string          The link.
     */
    public function link($params = [], $options = [])
    {
        $defaults = [
            'scheme'   => $this->scheme()
        ];
        $options += $defaults;

        if (!isset($options['host'])) {
            $options['host'] = $this->_link($this->token(), $params);
        }

        $scheme = $options['scheme'] !== '*' ? $options['scheme'] . '://' : '//';
        return $scheme . $options['host'];
    }

    /**
     * Helper for `Host::link()`.
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
                    throw new RouterException("Missing parameters `'{$child['name']}'` for host: `'{$this->_pattern}'`.");
                }
                return '';
            }
            $link .= $params[$child['name']];
        }
        return $link;
    }
}