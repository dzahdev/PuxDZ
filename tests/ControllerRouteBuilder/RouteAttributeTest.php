<?php

declare(strict_types=1);

namespace Pux\Tests\ControllerRouteBuilder;

use PHPUnit\Framework\TestCase;
use Pux\Builder\ControllerRouteBuilder;
use Pux\Tests\Fixtures\AttributeRoutesChildController;
use Pux\Tests\Fixtures\AttributeRoutesController;

/**
 * ControllerRouteBuilder::build reads #[Pux\Attribute\Route(path, method:)] on an action. The
 * attribute replaces the deprecated docblock annotations @Route and @Method of the same
 * method. Method constraints are integers: 1 = GET, 2 = POST, 3 = PUT.
 */
final class RouteAttributeTest extends TestCase
{
    public function test_build_uses_route_attribute_path_and_method(): void
    {
        self::assertContains(
            ['/items', 'listAction', ['method' => 1]],
            ControllerRouteBuilder::build(new AttributeRoutesController()),
        );
    }

    public function test_build_with_route_attribute_without_method_adds_no_method_option(): void
    {
        self::assertContains(
            ['/anything', 'anyAction', []],
            ControllerRouteBuilder::build(new AttributeRoutesController()),
        );
    }

    public function test_build_with_route_attribute_and_docblock_annotations_uses_attribute(): void
    {
        self::assertContains(
            ['/attribute', 'bothAction', ['method' => 3]],
            ControllerRouteBuilder::build(new AttributeRoutesController()),
        );
    }

    public function test_build_with_route_attribute_ignores_docblock_method_annotation(): void
    {
        self::assertContains(
            ['/no-method', 'attributeWithoutMethodAction', []],
            ControllerRouteBuilder::build(new AttributeRoutesController()),
        );
    }

    public function test_build_of_class_name_reads_route_attributes(): void
    {
        self::assertSame(
            [
                ['/items', 'listAction', ['method' => 1]],
                ['/anything', 'anyAction', []],
                ['/attribute', 'bothAction', ['method' => 3]],
                ['/no-method', 'attributeWithoutMethodAction', []],
                ['/plain', 'plainAction', []],
            ],
            ControllerRouteBuilder::build(AttributeRoutesController::class),
        );
    }

    public function test_build_child_override_without_attribute_keeps_parent_route_attribute(): void
    {
        self::assertContains(
            ['/items', 'listAction', ['method' => 1]],
            ControllerRouteBuilder::build(new AttributeRoutesChildController()),
        );
    }
}
