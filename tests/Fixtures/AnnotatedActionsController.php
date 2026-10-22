<?php

namespace Pux\Tests\Fixtures;

/**
 * Same actions and annotations as ExpandableProductController, without a parent class, so
 * that ControllerRouteBuilder::build can be specified before Stage 1 replaces
 * Pux\Controller\Controller.
 */
class AnnotatedActionsController
{
    public function indexAction() { }

    /**
     * @Method("GET")
     */
    public function itemAction() { }

    /**
     * @Route("/add")
     * @Method("POST")
     */
    public function addAction() { }

    /**
     * @Route("/update")
     * @Method("PUT")
     */
    public function updateAction() { }

    /**
     * @Route("/delete")
     * @Method("DELETE")
     */
    public function delAction() { }

    public function fooBarAction() { }

    public function helper() { }
}
