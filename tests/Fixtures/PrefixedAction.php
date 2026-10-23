<?php

namespace Pux\Tests\Fixtures;

/**
 * Invokable action object with state: the prefix given to the constructor shows that
 * RouteExecutor::execute calls the given object and does not create a new one.
 */
class PrefixedAction
{
    public function __construct(private string $prefix)
    {
    }

    public function __invoke($id)
    {
        return "$this->prefix invoked $id";
    }

    public function describe($id)
    {
        return "$this->prefix described $id";
    }
}
