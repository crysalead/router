<?php

declare(strict_types=1);

namespace Lead\Router\Exception;

/**
 * DispatchingException
 */
class DispatchingException extends \RuntimeException
{
    /**
     * The error code.
     *
     * @var integer
     */
    protected $code = 500;
}
