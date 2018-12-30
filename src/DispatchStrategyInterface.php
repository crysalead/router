<?php
declare(strict_types=1);

namespace Lead\Router;

interface DispatchStrategyInterface
{

    public function __invoke($router, $resource, $options = []);

    public function _dispatch($route, $resource, $action);

}
