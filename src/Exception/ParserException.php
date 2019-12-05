<?php

declare(strict_types=1);

namespace Lead\Router\Exception;

use RuntimeException;

/**
 * ParserException
 */
class ParserException extends RuntimeException
{
    public const SQUARE_BRACKET_MISMATCH = 1;
    public const DUPLICATE_PLACEHOLDER = 2;
    public const PLACEHOLDER_EXCEEDED = 3;

    /**
     * The missing placeholder name.
     *
     * @var string
     */
    public $placeholder = '';

    /**
     * The error code.
     *
     * @var integer
     */
    protected $code = 500;

    /**
     * Creates a square bracket mismatch exception.
     *
     * @return $this
     */
    public static function squareBracketMismatch()
    {
        return new static("Number of opening '[' and closing ']' does not match.", static::SQUARE_BRACKET_MISMATCH);
    }

    /**
     * Creates a duplicate placeholder exception.
     *
     * @return $this
     */
    public static function duplicatePlaceholder($placeholder = '')
    {
        $exception = new static("Cannot use the same placeholder `{$placeholder}` twice.", static::DUPLICATE_PLACEHOLDER);
        $exception->placeholder = $placeholder;
        return $exception;
    }

    /**
     * Creates a placeholder exceeded exception.
     *
     * @return $this
     */
    public static function placeholderExceeded()
    {
        return new static("Only a single placeholder is allowed in repeatable segments.", static::PLACEHOLDER_EXCEEDED);
    }
}
