<?php

declare(strict_types=1);

namespace Lead\Router;

/**
 * Router Interface
 */
interface RouterInterface
{
    /**
     * Adds a route.
     *
     * @param  string|array $pattern The route's pattern.
     * @param  callable|array $options An array of options or the callback handler.
     * @param  callable|null $handler The callback handler.
     * @return self
     */
    public function bind($pattern, $options = [], $handler = null): RouteInterface;
/**
     * Gets the base path
     *
     * @param  string $basePath The base path to set or none to get the setted one.
     * @return string
     */
    public function getBasePath(): string;
/**
     * Sets the base path
     *
     * @param  string $basePath Base Path
     * @return $this
     */
    public function setBasePath(string $basePath);
/**
     * Routes a Request.
     *
     * @param mixed $request The request to route.
     * @return \Lead\Router\RouteInterface A route matching the request or a "route not found" route.
     */
    public function route($request): RouteInterface;
/**
     * Returns a route's link.
     *
     * @param  string $name    A route name.
     * @param  array  $params  The route parameters.
     * @param  array  $options Options for generating the proper prefix. Accepted values are:
     *                         - `'absolute'` _boolean_: `true` or `false`. - `'scheme'`
     *                         _string_ : The scheme. - `'host'`     _string_ : The host
     *                         name. - `'basePath'` _string_ : The base path. - `'query'`
     *                         _string_ : The query string. - `'fragment'` _string_ : The
     *                         fragment string.
     * @return string The link.
     */
    public function link(string $name, array $params = [], array $options = []): string;
}
