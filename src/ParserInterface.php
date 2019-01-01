<?php
declare(strict_types=1);

namespace Lead\Router;

/**
 * ParserInterface
 */
interface ParserInterface
{
    /**
     * Tokenizes a route pattern. Optional segments are identified by square brackets.
     *
     * @param string $pattern A route pattern
     * @param string $delimiter The path delimiter.
     * @param array The tokens structure root node.
     * @return array
     */
    public static function tokenize(string $pattern, string $delimiter = '/'): array;

    /**
     * Splits a pattern in segments and patterns.
     * segments will be represented by string value and patterns by an array containing
     * the string pattern as first value and the greedy value as second value.
     * example:
     * `/user[/{id}]*` will gives `['/user', ['id', '*']]`
     * Unfortunately recursive regex matcher can't help here so this function is required.
     *
     * @param  string $pattern A route pattern.
     * @param  array The split pattern.
     * @return array
     */
    public static function split(string $pattern): array;

    /**
     * Builds a regex from a tokens structure array.
     *
     * @param  array $token A tokens structure root node.
     * @return array An array containing the regex pattern and its associated variable names.
     */
    public static function compile($token): array;
}
