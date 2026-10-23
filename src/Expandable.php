<?php
namespace Pux;

interface Expandable
{

    /**
     * Returns a Mux with the routes of this object.
     *
     * @param array   $options route options merged into every route (mount() passes its
     *                         options here, 'mount_path' included)
     * @param boolean $dynamic true: the route callbacks hold this object ([$this, action]);
     *                         false (the default, as in ExpandableController and
     *                         RESTfulController): they hold the class name ([class, action]),
     *                         so RouteExecutor creates a new instance per call and the routes
     *                         can be exported
     *
     * @return Mux
     */
    public function expand(array $options = [], $dynamic = false);

}
