<?php

namespace Pux\Tests\Fixtures;

use LogicException;

/**
 * Controller whose constructor throws: a caller that only inspects the class (callback
 * validation in MuxCompiler::merge) must not instantiate it.
 */
class ConstructorSideEffectController
{
    public function __construct()
    {
        throw new LogicException('ConstructorSideEffectController was instantiated.');
    }

    public function indexAction()
    {
        return 'index';
    }
}
