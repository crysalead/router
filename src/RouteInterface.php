<?php

declare(strict_types=1);

namespace Lead\Router;

/**
 * RouteInterface
 */
interface RouteInterface
{
    /**
     * Valid HTTP methods.
     *
     * @var array
     */
    public const VALID_METHODS = [
        'GET', 'PUT', 'POST', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'
    ];

    /**
     * HTTP verb constants
     */
    public const GET = 'GET';
    public const PUT = 'PUT';
    public const POST = 'POST';
    public const PATCH = 'PATCH';
    public const DELETE = 'DELETE';
    public const OPTIONS = 'OPTIONS';
    public const HEAD = 'HEAD';

    /**
     * Gets the routes name
     *
     * @return string
     */
    public function name(): string;

    /**
     * Sets the routes name
     *
     * @param string $name Name
     * @return self
     */
    public function setName(string $name): RouteInterface;

    /**
     * Gets the host
     *
     * @return null|\Lead\Router\HostInterface
     */
    public function host(): ?HostInterface;

    /**
     * Sets the route host.
     *
     * @param  object $host The host instance to set or none to get the set one.
     * @param  string $scheme HTTP Scheme
     * @return \Lead\Router\RouteInterface       The current host on get or `$this` on set.
     */
    public function setHost($host = null, string $scheme = '*'): RouteInterface;

    /**
     * Gets the routes Scope
     *
     * @return \Lead\Router\Scope
     */
    public function Scope(): ?ScopeInterface;

    /**
     * Sets a routes scope
     *
     * @param  \Lead\Router\Scope|null $scope Scope
     * @return $this;
     */
    public function setScope(?Scope $scope): RouteInterface;

    /**
     * Gets the routes handler
     *
     * This can be almost anything. It really depends on your application if you
     * want to construct a handler object from a string for example or if you
     * prefer to work with closures or something totally different.
     *
     * @return mixed
     */
    public function handler();

    /**
     * Gets/sets the route's handler.
     *
     * @param mixed $handler The route handler.
     * @return self
     */
    public function setHandler($handler): RouteInterface;

    /**
     * Checks if the route instance matches a request.
     *
     * @param  array $request a request.
     * @param array|null $variables Variables
     * @param array|null $hostVariables Host variables
     * @return bool
     */
    public function match($request, &$variables = null, &$hostVariables = null): bool;

    /**
      * Returns the route's link.
      *
      * @param  array $params  The route parameters.
      * @param  array $options Options for generating the proper prefix. Accepted values are:
      *                        - `'absolute'` _boolean_: `true` or `false`. - `'scheme'`
      *                        _string_ : The scheme. - `'host'`     _string_ : The host
      *                        name. - `'basePath'` _string_ : The base path. - `'query'`
      *                        _string_ : The query string. - `'fragment'` _string_ : The
      *                        fragment string.
      * @return string          The link.
      */
    public function link(array $params = [], array $options = []): string;

    /**
     * Gets the routes pattern
     *
     * @return string
     */
    public function pattern(): string;

    /**
     * Get persistent parameters
     *
     * @return array
     */
    public function persistentParams(): array;

    /**
     * Get parameters
     *
     * @return array
     */
    public function params(): array;
}
