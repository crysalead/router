<?php
namespace Lead\Router;

/**
 * Parses route pattern.
 *
 * The parser can produce a tokens structure from route pattern using `Parser::tokenize()`.
 * A tokens structure root node is of the following form:
 *
 * ```php
 * $token = Parser::tokenize('/test/{param}');
 * ```
 *
 * The returned `$token` looks like the following:
 * ```
 * [
 *     'optional' => false,
 *     'greedy'   => '',
 *     'repeat'   => false,
 *     'pattern'  => '/test/{param}',
 *     'tokens'   => [
 *         '/test/',
 *         [
 *             'name'      => 'param',
 *             'pattern'   => '[^/]+'
 *         ]
 *     ]
 * ]
 * ```
 *
 * Then tokens structures can be compiled to get the regex representation with associated variable.
 *
 * ```php
 * $rule = Parser::compile($token);
 * ```
 *
 * `$rule` looks like the following:
 *
 * ```
 * [
 *     '/test/([^/]+)',
 *     ['param' => false]
 * ]
 * ```
 */
class Parser {

    /**
     * Variable capturing block regex.
     */
    const PLACEHOLDER_REGEX = <<<EOD
\{
    (
        [a-zA-Z][a-zA-Z0-9_]*
    )
    (?:
        :(
            [^{}]*
            (?:
                \{(?-1)\}[^{}]*
            )*
        )
    )?
\}
EOD;

    /**
     * Tokenizes a route pattern. Optional segments are identified by square brackets.
     *
     * @param string $pattern   A route pattern
     * @param string $delimiter The path delimiter.
     * @param array             The tokens structure root node.
     */
    public static function tokenize($pattern, $delimiter = '/')
    {
        // Checks if the pattern has some optional segments.
        if (preg_match('~^(?:[^\[\]{}]*(?:' . static::PLACEHOLDER_REGEX . ')?)*\[~x', $pattern, $matches)) {
            $tokens = static::_tokenizePattern($pattern, $delimiter);
        } else {
            $tokens = static::_tokenizeSegment($pattern, $delimiter);
        }
        return [
            'optional' => false,
            'greedy'   => '',
            'repeat'   => false,
            'pattern'  => $pattern,
            'tokens'   => $tokens
        ];
    }

    /**
     * Tokenizes patterns.
     *
     * @param string $pattern   A route pattern
     * @param string $delimiter The path delimiter.
     * @param array             An array of tokens structure.
     */
    protected static function _tokenizePattern($pattern, $delimiter)
    {
        $tokens = [];
        $index = 0;
        $path = '';
        $parts = static::split($pattern);

        foreach ($parts as $part) {
            if (is_string($part)) {
                $tokens = array_merge($tokens, static::_tokenizeSegment($part, $delimiter));
                continue;
            }

            $greedy = $part[1];
            $repeat = $greedy === '+' || $greedy === '*';
            $optional = $greedy === '?' || $greedy === '*';

            $tokens[] = [
                'optional' => $optional,
                'greedy'   => $greedy ?: '?',
                'repeat'   => $repeat,
                'pattern'  => $part[0],
                'tokens'   => static::_tokenizePattern($part[0], $delimiter)
            ];

        }
        return $tokens;
    }

    /**
     * Tokenizes segments which are patterns with optional segments filtered out.
     * Only classic placeholder are supported.
     *
     * @param string $pattern   A route pattern with no optional segments.
     * @param string $delimiter The path delimiter.
     * @param array             An array of tokens structure.
     */
    protected static function _tokenizeSegment($pattern, $delimiter)
    {
        $tokens = [];
        $index = 0;
        $path = '';

        if (preg_match_all('~' . static::PLACEHOLDER_REGEX . '()~x', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $offset = $match[0][1];

                $path .= substr($pattern, $index, $offset - $index);
                $index = $offset + strlen($match[0][0]);

                if ($path) {
                    $tokens[] = $path;
                    $path = '';
                }

                $name = $match[1][0];
                $capture = $match[2][0] ?: '[^' . $delimiter . ']+';

                $tokens[] = [
                    'name'      => $name,
                    'pattern'   => $capture
                ];
            }
        }

        if ($index < strlen($pattern)) {
            $path .= substr($pattern, $index);
            if ($path) {
                $tokens[] = $path;
            }
        }
        return $tokens;
    }

    /**
     * Splits a pattern in segments and patterns.
     * segments will be represented by string value and patterns by an array containing
     * the string pattern as first value and the greedy value as second value.
     *
     * example:
     * `/user[/{id}]*` will gives `['/user', ['id', '*']]`
     *
     * Unfortunately recursive regex matcher can't help here so this function is required.
     *
     * @param string $pattern A route pattern.
     * @param array           The splitted pattern.
     */
    public static function split($pattern)
    {
        $segments = [];
        $len = strlen($pattern);
        $buffer = '';
        $opened = 0;
        for ($i = 0; $i < $len; $i++) {
            if ($pattern[$i] === '{') {
                do {
                    $buffer .= $pattern[$i++];
                    if ($pattern[$i] === '}') {
                        $buffer .= $pattern[$i];
                        break;
                    }
                } while ($i < $len);
            } elseif ($pattern[$i] === '[') {
                $opened++;
                if ($opened === 1) {
                    $segments[] = $buffer;
                    $buffer = '';
                } else {
                    $buffer .= $pattern[$i];
                }
            } elseif ($pattern[$i] === ']') {
                $opened--;
                if ($opened === 0) {
                    $greedy = '?';
                    if ($i < $len -1) {
                        if ($pattern[$i + 1] === '*' || $pattern[$i + 1] === '?') {
                            $greedy = $pattern[$i + 1];
                            $i++;
                        }
                    }
                    $segments[] = [$buffer, $greedy];
                    $buffer = '';
                } else {
                    $buffer .= $pattern[$i];
                }
            } else {
                $buffer .= $pattern[$i];
            }
        }
        if ($buffer) {
            $segments[] = $buffer;
        }
        if ($opened) {
            throw ParserException::squareBracketMismatch();
        }
        return $segments;
    }

    /**
     * Builds a regex from a tokens structure array.
     *
     * @param  array $token A tokens structure root node.
     * @return array        An array containing the regex pattern and its associated variable names.
     */
    public static function compile($token)
    {
        $variables = [];
        $regex = '';
        foreach ($token['tokens'] as $child) {
            if (is_string($child)) {
                $regex .= preg_quote($child, '~');
            } elseif (isset($child['tokens'])) {
                $rule = static::compile($child);
                if ($child['repeat']) {
                    if (count($rule[1]) > 1) {
                        throw ParserException::placeholderExceeded();
                    }
                    $regex .= '((?:' . $rule[0] . ')' . $child['greedy'] . ')';
                } elseif ($child['optional']) {
                    $regex .= '(?:' . $rule[0] . ')?';
                }
                foreach ($rule[1] as $name => $pattern) {
                    if (isset($variables[$name])) {
                        throw ParserException::duplicatePlaceholder($name);
                    }
                    $variables[$name] = $pattern;
                }
            } else {
                $name = $child['name'];
                if (isset($variables[$name])) {
                    throw ParserException::duplicatePlaceholder($name);
                }
                if ($token['repeat']) {
                    $variables[$name] = $token['pattern'];
                    $regex .= $child['pattern'];
                } else {
                    $variables[$name] = false;
                    $regex .= '(' . $child['pattern'] . ')';
                }
            }
        }
        return [$regex, $variables];
    }
}
