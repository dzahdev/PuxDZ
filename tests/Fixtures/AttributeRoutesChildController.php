<?php

namespace Pux\Tests\Fixtures;

/**
 * Overrides an attribute-routed action without an attribute of its own.
 */
class AttributeRoutesChildController extends AttributeRoutesController
{
    public function listAction() { }
}
