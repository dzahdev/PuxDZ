<?php

// Test fixture, moved from tests/controllers.php in Stage 0 without changes to its body.
class ProductController
{
    public function indexAction() { return 'index'; }
    public function fooAction() { return 'foo'; }
    public function barAction() { return 'bar'; }

    public function itemAction($id)  { 
        return "product item $id";
    }
}
