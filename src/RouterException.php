<?php
declare(strict_types=1);

namespace Lead\Router;

class RouterException extends \RuntimeException
{
    /**
     * The error code.
     *
     * @var integer
     */
    protected $code = 500;
}
