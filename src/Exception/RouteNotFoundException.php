<?php

declare(strict_types=1);

namespace Lead\Router\Exception;

use RuntimeException;

/**
 * RouteNotFoundException
 */
class RouteNotFoundException extends RouterException
{
    /**
     * The error code.
     *
     * @var integer
     */
    protected $code = 404;
}
