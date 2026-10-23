<?php

declare(strict_types=1);

namespace Pux;

/**
 * An object that mount() turns into routes through expand().
 *
 * The return type is declared in the docblock only, so that implementations written for 2.x
 * without a return type stay compatible with the interface.
 */
interface Expandable
{
    /**
     * Returns a Mux with the routes of this object.
     *
     * @param array<string, mixed> $options route options merged into every route (mount()
     *                                      passes its options here, 'mount_path' included)
     * @param bool                 $dynamic true: the route callbacks hold this object
     *                                      ([$this, action]); false (the default, as in
     *                                      ExpandableController and RESTfulController): they
     *                                      hold the class name ([class, action]), so
     *                                      RouteExecutor creates a new instance per call and
     *                                      the routes can be exported
     *
     * @return Mux
     */
    public function expand(array $options = [], bool $dynamic = false);
}
