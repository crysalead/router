<?php

declare(strict_types=1);

namespace Lead\Router;

/**
 * HostInterface
 */
interface HostInterface
{
    /**
     * Checks if a host matches a host pattern.
     *
     * @param string $request The request to check.
     * @param string $hostVariables The matches host variables
     * @return bool Returns `true` on success, false otherwise.
     */
    public function match($request, &$hostVariables = null): bool;
/**
     * Returns the host's link.
     *
     * @param array $params  The host parameters.
     * @param array $options Options for generating the proper prefix. Accepted values are:
     *  - `'scheme'` _string_ : The scheme.
     *  - `'host'`   _string_ : The host name.
     *
     * @return string The link.
     */
    public function link($params = [], $options = []): string;
}
