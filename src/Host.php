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
    protected $classes = [];

    /**
     * The matching scheme.
     *
     * @var string
     */
    protected $scheme = '*';

    /**
     * The matching host.
     *
     * @var string
     */
    protected $pattern = '*';

    /**
     * The tokens structure extracted from host's pattern.
     *
     * @see Parser::tokenize()
     * @var array|null
     */
    protected $token = null;

    /**
     * The host's regular expression pattern.
     *
     * @see Parser::compile()
     * @var string|null
     */
    protected $regex = null;

    /**
     * The host's variables.
     *
     * @see Parser::compile()
     * @var array|null
     */
    protected $variables = null;

    /**
     * Constructs a route
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'scheme'     => '*',
            'pattern'    => '*',
            'classes'    => [
                'parser' => 'Lead\Router\Parser'
            ]
        ];
        $config += $defaults;

        $this->classes = $config['classes'];
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
        $this->scheme = $scheme;

        return $this;
    }

    /**
     * Gets the scheme
     *
     * @return string|null
     */
    public function scheme(): ?string
    {
        return $this->scheme;
    }

    /**
     * Sets the hosts pattern
     *
     * @param string $pattern Pattern
     * @return $this
     */
    public function setPattern(string $pattern)
    {
        $this->token = null;
        $this->regex = null;
        $this->variables = null;
        $this->pattern = $pattern;

        return $this;
    }

    /**
     * Gets the hosts pattern
     *
     * @return string
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Returns the route's token structures.
     *
     * @return array A collection route's token structure.
     */
    protected function token()
    {
        if ($this->token === null) {
            $parser = $this->classes['parser'];
            $this->token = [];
            $this->regex = null;
            $this->variables = null;
            $this->token = $parser::tokenize($this->pattern, '.');
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
    public function variables()
    {
        if ($this->variables !== null) {
            return $this->variables;
        }

        $this->compile();

        return $this->variables;
    }

    /**
     * Compiles the host's patten.
     */
    protected function compile(): void
    {
        if ($this->pattern() === '*') {
            return;
        }

        $parser = $this->classes['parser'];
        $rule = $parser::compile($this->token());
        $this->regex = $rule[0];
        $this->variables = $rule[1];
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function link($params = [], $options = []): string
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
                    throw new RouterException("Missing parameters `'{$child['name']}'` for host: `'{$this->pattern}'`.");
                }
                return '';
            }
            $link .= $params[$child['name']];
        }
        return $link;
    }
}
