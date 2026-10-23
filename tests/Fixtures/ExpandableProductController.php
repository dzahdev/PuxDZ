<?php

declare(strict_types=1);

use Pux\Controller\ExpandableController;

// Test fixture, moved from tests/controllers.php in Stage 0 without changes to its body.
class ExpandableProductController extends ExpandableController
{
    public function indexAction()
    {
    }


    /**
     * @Method("GET")
     */
    public function itemAction()
    {
    }


    /**
     * @Route("/add")
     * @Method("POST")
     */
    public function addAction()
    {
    }

    /**
     * @Route("/update")
     * @Method("PUT")
     */
    public function updateAction()
    {
    }

    /**
     * @Route("/delete")
     * @Method("DELETE")
     */
    public function delAction()
    {
    }


    public function fooBarAction()
    {
    }
}
