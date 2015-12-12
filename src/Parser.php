<?php
namespace Lead\Router;

use Lead\Router\RouterException;

/**
 * Parses routes of the following form:
 *
 * "/user/{name}[/{id:[0-9]+}]"
 */
class Parser {

    /**
     * Variable capturing block regex.
     */
    const VARIABLE_REGEX = <<<'REGEX'
\{
    \s* ([a-zA-Z][a-zA-Z0-9_]*) \s*
    (?:
        : \s* ([^{}]*(?:\{(?-1)\}[^{}]*)*)
    )?
\}
REGEX;

    /**
     * Parses a route pattern of the following form:
     *
     * "/user/{name}[/{id:[0-9]+}]"
     *
     * Optional segments are identified by square brackets.
     *
     * @param string $pattern      A route pattern
     * @param string $segmentRegex The regular expression for variable segment.
     * @param array                Returns a collection of route patterns and their associated variable names.
     */
    public static function parse($pattern, $segmentRegex = '[^/]+')
    {
        $patternWithoutClosingOptionals = rtrim($pattern, ']');
        $numOptionals = strlen($pattern) - strlen($patternWithoutClosingOptionals);

        $segments = preg_split('~' . static::VARIABLE_REGEX . '(*SKIP)(*F) | \[~x', $patternWithoutClosingOptionals);

        if ($numOptionals !== count($segments) - 1) {
            if (preg_match('~' . static::VARIABLE_REGEX . '(*SKIP)(*F) | \]~x', $patternWithoutClosingOptionals)) {
                throw new RouterException("Optional segments can only occur at the end of a route.");
            }
            throw new RouterException("Number of opening '[' and closing ']' does not match.");
        }

        $parses = [];
        $currentPattern = '';

        foreach ($segments as $n => $part) {
            if (!$part) {
                if ($n !== 0) {
                    throw new RouterException("Empty optional part.");
                }
            } else {
                $currentPattern = $part[0] === '/' ? rtrim($currentPattern, '/') . $part : $currentPattern . $part;
            }
            $parses[] = static::_parse($currentPattern, $segmentRegex);
        }
        return $parses;
    }

    /**
     * Parses a route pattern that does not contain optional segments.
     *
     * @param string $pattern      A route pattern
     * @param string $segmentRegex The regular expression for variable segment.
     * @param array                Returns a collection of route patterns and their associated variable names.
     */
    protected static function _parse($pattern, $segmentRegex)
    {
        if (!preg_match_all('~' . static::VARIABLE_REGEX . '~x', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return static::_buildPattern([$pattern]);
        }
        $offset = 0;
        $patternData = [];
        foreach ($matches as $set) {
            if ($set[0][1] > $offset) {
                $patternData[] = substr($pattern, $offset, $set[0][1] - $offset);
            }
            $patternData[] = [
                $set[1][0],
                isset($set[2]) ? trim($set[2][0]) : $segmentRegex
            ];
            $offset = $set[0][1] + strlen($set[0][0]);
        }
        if ($offset != strlen($pattern)) {
            $patternData[] = substr($pattern, $offset);
        }
        return static::_buildPattern($patternData);
    }

    /**
     * Build a regex pattern from a pattern data.
     *
     * @param  array $patternData A pattern data
     * @return array              An array containing a regex pattern and its associated variable names.
     */
    protected static function _buildPattern($patternData)
    {
        $regex = '';
        $variables = [];
        foreach ($patternData as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }
            list($varName, $regexPart) = $part;
            if (isset($variables[$varName])) {
                throw new RouterException("Cannot use the same placeholder `{$varName}` twice.");
            }
            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }
        return [$regex, $variables];
    }
}
