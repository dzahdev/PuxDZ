<?php

namespace Pux\Tests\Fixtures;

// Test fixture for ControllerRouteBuilder::build. Stage 2: no longer extends
// Pux\Controller\ExpandableController; build() reads only reflection and docblocks, and the
// parent class cannot be loaded until Stage 1.
class ParentController {

    /**
     *
     * @Route("/page")
     * @Method("GET")
     */
    public function pageAction() {  }


    /**
     * @Route("/post")
     * @Method("POST")
     */
    public function postAction() { }
}
