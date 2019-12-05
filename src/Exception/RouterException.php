<?php

declare(strict_types=1);

namespace Lead\Router\Exception;

use RuntimeException;

/**
 * RouterException
 */
class RouterException extends RuntimeException
{
    /**
     * The error code.
     *
     * @var integer
     */
    protected $code = 500;
}
