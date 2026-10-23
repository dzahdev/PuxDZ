<?php

declare(strict_types=1);

namespace Pux\Tests\Fixtures;

use Pux\Attribute\Route;
use Pux\RequestMethod;

/**
 * Actions routed with the #[Route] attribute, alone and together with the deprecated
 * docblock annotations.
 */
class AttributeRoutesController
{
    #[Route('/items', method: RequestMethod::GET)]
    public function listAction()
    {
    }

    #[Route('/anything')]
    public function anyAction()
    {
    }

    /**
     * @Route("/docblock")
     * @Method("POST")
     */
    #[Route('/attribute', method: RequestMethod::PUT)]
    public function bothAction()
    {
    }

    /**
     * @Method("POST")
     */
    #[Route('/no-method')]
    public function attributeWithoutMethodAction()
    {
    }

    public function plainAction()
    {
    }
}
