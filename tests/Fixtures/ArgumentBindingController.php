<?php

declare(strict_types=1);

namespace Pux\Tests\Fixtures;

/**
 * Actions whose parameter lists exercise argument binding in RouteExecutor::execute.
 */
class ArgumentBindingController
{
    public function item($id, $fmt = 'html')
    {
        return "item=$id fmt=$fmt";
    }

    public function two($a, $b)
    {
        return "a=$a b=$b";
    }

    public function named($param1, $param2)
    {
        return "p1=$param1 p2=$param2";
    }

    public function nameAndPosition($a, $param2)
    {
        return "a=$a param2=$param2";
    }

    public function nullable(?string $value)
    {
        return 'value=' . var_export($value, true);
    }
}
