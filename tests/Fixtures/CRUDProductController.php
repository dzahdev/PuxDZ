<?php

declare(strict_types=1);

use Pux\Controller\ExpandableController;

// Test fixture, moved from src/Controller/ControllerTest.php in Stage 0 without changes to its body.
class CRUDProductController extends ExpandableController
{
    public function indexAction()
    {
    }

    public function addAction()
    {
    }

    public function delAction()
    {
    }

    public function jsonAction()
    {
        return $this->toJson(['foo' => 1]);
    }
}
