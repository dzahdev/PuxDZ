<?php

use Pux\Controller\RESTfulController;

// Test fixture, moved from tests/controllers.php in Stage 0 without changes to its body.
class ProductResourceController extends RESTfulController
{
    /**
     * @Method("POST")
     * @Route("");
     */
    public function createAction() { }

    /**
     * @Route("/:id")
     * @Method("POST")
     */
    public function updateAction($id) { }

    /**
     * @Route("/:id")
     * @Method("GET")
     */
    public function loadAction($id) { }

    /**
     * @Route("/:id");
     * @Method("DELETE")
     */
    public function deleteAction($id) { }

}
