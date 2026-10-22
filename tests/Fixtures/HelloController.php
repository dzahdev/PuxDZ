<?php

use Pux\Controller\Controller;

// Test fixture, moved from tests/controllers.php in Stage 0 without changes to its body.
class HelloController extends Controller
{
    public function index($name)
    {
        return "Hello $name";
    }

    public function show()
    {
        return "response";
    }
}
