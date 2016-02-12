<?php
namespace Lead\Router;

class DispatchingException extends \RuntimeException
{
    /**
     * The error code.
     *
     * @var integer
     */
    protected $code = 500;
}
