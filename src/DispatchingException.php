<?php
namespace Lead\Router;

class DispatchingException extends \RuntimeException
{
    protected $code = 500;
}
