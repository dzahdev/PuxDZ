<?php

use Pux\Controller\ExpandableController;

// Test fixture, moved from src/Dispatcher/PathDispatcherRESTfulTest.php in Stage 0 without changes to its body.
class ProductResource2Controller extends ExpandableController {


    /**
     * @Method("POST")
     * @Route("");
     */
    public function createAction() {

    }

    /**
     * @Route("/:id")
     * @Method("POST")
     */
    public function updateAction() {

    }

    /**
     * @Route("/:id")
     * @Method("GET")
     */
    public function getAction() {

    }

    /**
     * @Route("/:id");
     * @Method("DELETE")
     */
    public function deleteAction() {

    }

}
