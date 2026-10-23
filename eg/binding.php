<?php

declare(strict_types=1);

/*
 * Argument binding: RouteExecutor::execute() binds route variables to the action parameters by
 * name, then by position, then from the route option 'default', then from the parameter
 * default. A value for an int, float, string or bool parameter is converted when nothing is
 * lost ("7" to 7, "off" to false); otherwise PHP throws a TypeError.
 *
 *     php eg/binding.php
 */

namespace Pux\Examples;

use Pux\Mux;
use Pux\RouteExecutor;

require __DIR__ . '/../vendor/autoload.php';

final class ReportController
{
    public function byNameAction(string $month, int $year): string
    {
        return "by name: year=$year month=$month";
    }

    public function byPositionAction(string $first, string $second): string
    {
        return "by position: first=$first second=$second";
    }

    public function draftsAction(int $page, string $format = 'html', bool $draft = true): string
    {
        $draft = var_export($draft, true);

        return "defaults: page=$page format=$format draft=$draft";
    }
}

$mux = new Mux();
// The parameter names match the variable names, in another order.
$mux->any('/by-name/:year/:month', [ReportController::class, 'byNameAction']);
// No parameter name matches: the variables are bound in pattern order.
$mux->any('/by-position/:a/:b', [ReportController::class, 'byPositionAction']);
// page comes from 'default' when the group is omitted ("0" is bound too); format keeps its
// own default; draft is converted from "off" to false.
$mux->any('/reports/:draft(/:page)', [ReportController::class, 'draftsAction'], [
    'default' => ['page' => '0'],
]);

foreach (['/by-name/2026/10', '/by-position/x/y', '/reports/off', '/reports/yes/3'] as $path) {
    $route = $mux->dispatch($path) ?? throw new \UnexpectedValueException("No route for $path.");
    echo RouteExecutor::execute($route), "\n";
}
