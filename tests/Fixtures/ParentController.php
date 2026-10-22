<?php

namespace Pux\Tests\Fixtures;

use Pux\Controller\ExpandableController;

// Test fixture, moved from src/Builder/ControllerRouteBuilderTest.php in Stage 0 without changes to its body.
class ParentController extends ExpandableController {

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
