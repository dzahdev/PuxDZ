<?php

declare(strict_types=1);

namespace Pux\Tests\Fixtures;

use Pux\Expandable;
use Pux\Mux;

/**
 * Minimal Expandable resource for RESTfulMuxBuilder: one route without a method constraint,
 * so the builder can be specified without RESTfulController (Stage 1) and without request
 * methods (defect L4).
 */
class PlainResource implements Expandable
{
    public function expand(array $options = [], $dynamic = true)
    {
        $mux = new Mux();
        $mux->any('/:id', [self::class, 'loadAction'], $options);

        return $mux;
    }

    public function loadAction($id)
    {
        return "load $id";
    }
}
