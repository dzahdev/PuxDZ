<?php

// Test fixture. Stage 2: no longer extends Pux\Controller\Controller; the tests that use it
// exercise RouteExecutor only, and the parent class cannot be loaded until Stage 1.
class HelloController
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
