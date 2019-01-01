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
    public function setName(string $name): self;

    /**
     * Checks if the route instance matches a request.
     *
     * @param  array $request a request.
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
