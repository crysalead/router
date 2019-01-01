<?php
declare(strict_types=1);

namespace Lead\Router;

/**
 * RouteInterface
 */
interface RouteInterface
{
    /**
     * Gets the routes name
     *
     * @return string
     */
    public function getName(): string;

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
     * @return mixed
     */
    public function getHost(): ?Host;

    /**
     * Sets the route host.
     *
     * @param  object $host The host instance to set or none to get the set one.
     * @param  string $scheme HTTP Scheme
     * @return object|self       The current host on get or `$this` on set.
     */
    public function setHost($host = null, string $scheme = '*'): RouteInterface;

    /**
     * Gets the routes Scope
     *
     * @return \Lead\Router\Scope
     */
    public function getScope(): ?Scope;

    /**
     * Sets a routes scope
     *
     * @param  \Lead\Router\Scope|null $scope Scope
     * @return $this;
     */
    public function setScope(?Scope $scope): RouteInterface;

    /**
     * Checks if the route instance matches a request.
     *
     * @param  array $request a request.
     * @param array|null $variables Variables
     * @param array|null $hostVariables Host variables
     * @return boolean
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
}
