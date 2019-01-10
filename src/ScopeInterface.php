<?php
declare(strict_types=1);

namespace Lead\Router;

/**
 * ScopeInterface
 */
interface ScopeInterface
{
    /**
     * Creates a new sub scope based on the instance scope.
     *
     * @param  array $options The route options to scopify.
     * @return $this          The new sub scope.
     */
    public function seed(array $options): ScopeInterface;

    /**
     * Scopes an options array according to the instance scope data.
     *
     * @param  array $options The options to scope.
     * @return array The scoped options.
     */
    public function scopify(array $options): array;
}
