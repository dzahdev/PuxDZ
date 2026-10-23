<?php

declare(strict_types=1);

namespace Pux\Controller;

use Pux\Builder\ControllerRouteBuilder;
use Pux\Expandable;
use Pux\Mux;

/**
 * A controller whose *Action methods become routes through expand() (see
 * ControllerRouteBuilder).
 */
class ExpandableController extends Controller implements Expandable
{
    /**
     * The Mux built by the last expand(); null before it.
     */
    protected ?Mux $mux = null;

    public function getMux(): ?Mux
    {
        return $this->mux;
    }

    /**
     * Expand controller actions to Mux object.
     *
     * @param array<string, mixed> $options route options merged into every route
     */
    public function expand(array $options = [], bool $dynamic = false): Mux
    {
        $this->mux = new Mux();
        $mux = $this->mux;
        foreach (ControllerRouteBuilder::build($this) as [$path, $action, $routeOptions]) {
            $mux->add($path, [$dynamic ? $this : static::class, $action], array_merge($options, $routeOptions));
        }

        $mux->sort();

        return $mux;
    }
}
