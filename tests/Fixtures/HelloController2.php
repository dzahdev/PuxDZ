<?php

use Pux\Controller\Controller;

// Test fixture, moved from tests/controllers.php in Stage 0 without changes to its body.
class HelloController2 extends Controller
{
    public function helloAction($name)
    {
        return "hello $name";
    }
}
