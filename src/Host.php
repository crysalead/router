<?php
declare(strict_types=1);

namespace Lead\Router;

use Lead\Router\Exception\RouterException;

/**
 * Defines a Host Pattern to match
 */
class Host implements HostInterface
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

        $this->setScheme($config['scheme']);
        $this->setPattern($config['pattern']);
    }

    /**
     * Sets the scheme
     *
     * @param string $scheme Scheme to set.
     * @return $this
     */
    public function setScheme(string $scheme)
    {
        $this->_scheme = $scheme;

        return $this;
    }

    /**
     * Gets the scheme
     *
     * @return string|null
     */
    public function getScheme(): ?string
    {
        return $this->_scheme;
    }

    /**
     * Sets the hosts pattern
     *
     * @param string $pattern Pattern
     * @return $this
     */
    public function setPattern(string $pattern)
    {
        $this->_token = null;
        $this->_regex = null;
        $this->_variables = null;
        $this->_pattern = $pattern;

        return $this;
    }

    /**
     * Gets the hosts pattern
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->_pattern;
    }

    /**
     * Returns the route's token structures.
     *
     * @return array A collection route's token structure.
     */
    protected function getToken()
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
    public function getRegex(): string
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
    public function getVariables()
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
        if ($this->getPattern() === '*') {
            return;
        }
        $parser = $this->_classes['parser'];
        $rule = $parser::compile($this->getToken());
        $this->_regex = $rule[0];
        $this->_variables = $rule[1];
    }

    /**
     * Checks if a host matches a host pattern.
     *
     * @param  string $host          The host to check.
     * @param  string $hostVariables The matches host variables
     * @return boolean                Returns `true` on success, false otherwise.
     */
    public function match($request, &$hostVariables = null): bool
    {
        $defaults = [
            'host'   => '*',
            'scheme' => '*'
        ];
        $request += $defaults;
        $scheme = $request['scheme'];
        $host = $request['host'];

        $hostVariables = [];

        $anyHost = $this->getPattern() === '*' || $host === '*';
        $anyScheme = $this->getScheme() === '*' || $scheme === '*';


        if ($anyHost) {
            if ($this->getVariables()) {
                $hostVariables = array_fill_keys(array_keys($this->getVariables()), null);
            }
            return $anyScheme || $this->getScheme() === $scheme;
        }

        if (!$anyScheme && $this->getScheme() !== $scheme) {
            return false;
        }

        if (!preg_match('~^' . $this->getRegex() . '$~', $host, $matches)) {
            $hostVariables = null;
            return false;
        }
        $i = 0;

        foreach ($this->getVariables() as $name => $pattern) {
            $hostVariables[$name] = $matches[++$i];
        }
        return true;
    }

    /**
     * Returns the host's link.
     *
     * @param array $params  The host parameters.
     * @param array $options Options for generating the proper prefix. Accepted values are:
     *                       - `'scheme'`   _string_ : The scheme. - `'host'`     _string_
     *                       : The host name.
     *
     * @return string The link.
     */
    public function link($params = [], $options = []): string
    {
        $defaults = [
            'scheme'   => $this->getScheme()
        ];
        $options += $defaults;

        if (!isset($options['host'])) {
            $options['host'] = $this->_link($this->getToken(), $params);
        }

        $scheme = $options['scheme'] !== '*' ? $options['scheme'] . '://' : '//';
        return $scheme . $options['host'];
    }

    /**
     * Helper for `Host::link()`.
     *
     * @param  array $token  The token structure array.
     * @param  array $params The route parameters.
     * @return string The URL path representation of the token structure array.
     */
    protected function _link($token, $params): string
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
