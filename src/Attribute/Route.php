<?php

declare(strict_types=1);

namespace Pux\Attribute;

use Attribute;
use Pux\RequestMethod;

/**
 * Route of a controller action, read by Pux\Builder\ControllerRouteBuilder::build():
 *
 *     #[Route('/items/:id', method: RequestMethod::GET)]
 *     public function itemAction($id) { ... }
 *
 * $path replaces the path derived from the action name; $method, when given, becomes the
 * 'method' option of the route. The attribute replaces the deprecated docblock annotations
 * @Route and @Method of the same method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Route
{
    public function __construct(
        public readonly string $path,
        public readonly ?RequestMethod $method = null,
    ) {
    }
}
