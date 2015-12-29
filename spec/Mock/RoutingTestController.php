<?php
namespace Lead\Router\Spec\Mock;

use Exception;

class RoutingTestController
{
    public $args = [];

    public $params = [];

    public $request = null;

    public $response = null;

    public function __invoke($args = [], $params = [], $request = null, $response = null)
    {
        $this->args = $args;
        $this->params = $params;
        $this->request = $request;
        $this->response = $response;
        return $this;
    }
}