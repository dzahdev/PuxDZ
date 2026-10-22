<?php

namespace Pux\Tests\Fixtures;

// Test fixture, moved from src/Builder/ControllerRouteBuilderTest.php in Stage 0 without changes to its body.
class ChildController extends ParentController { 

    // we should override this action but use the parent annotations
    public function pageAction() {  }

    public function subpageAction() {  }
}
