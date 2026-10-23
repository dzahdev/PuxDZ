<?php

declare(strict_types=1);

namespace Pux\Controller;

use Pux\Mux;
use Pux\RequestMethod;

/**
 * A resource controller: expand() routes the five actions below by request method. A
 * subclass overrides the actions it serves.
 *
 * The actions declare their return type in the docblock only, so that overrides written for
 * 2.x without a return type stay compatible.
 */
abstract class RESTfulController extends ExpandableController
{
    /**
     * @Route("");
     * @Method("GET")
     *
     * @return mixed
     */
    public function collectionAction()
    {
        return null;
    }

    /**
     * @Method("POST")
     * @Route("");
     *
     * @return mixed
     */
    public function createAction()
    {
        return null;
    }

    /**
     * @Route("/:id")
     * @Method("POST")
     *
     * @return mixed
     */
    public function updateAction(mixed $id)
    {
        return null;
    }

    /**
     * @Route("/:id")
     * @Method("GET")
     *
     * @return mixed
     */
    public function loadAction(mixed $id)
    {
        return null;
    }

    /**
     * @Route("/:id");
     * @Method("DELETE")
     *
     * @return mixed
     */
    public function deleteAction(mixed $id)
    {
        return null;
    }

    /**
     * Expand controller actions into Mux object
     *
     * @param array<string, mixed> $options route options merged into every route
     */
    public function expand(array $options = [], bool $dynamic = false): Mux
    {
        $mux = new Mux();
        $target = $dynamic ? $this : $this->getClass();
        $mux->add('/:id', [$target, 'updateAction'], array_merge($options, ['method' => RequestMethod::POST->value]));
        $mux->add('/:id', [$target, 'loadAction'], array_merge($options, ['method' => RequestMethod::GET->value]));
        $mux->add('/:id', [$target, 'deleteAction'], array_merge($options, ['method' => RequestMethod::DELETE->value]));
        $mux->add('', [$target, 'createAction'], array_merge($options, ['method' => RequestMethod::POST->value]));
        $mux->add('', [$target, 'collectionAction'], array_merge($options, ['method' => RequestMethod::GET->value]));

        return $mux;
    }

    /**
     * Sets the HTTP response status code through http_response_code().
     *
     * @deprecated since 3.0, return the response array [code, headers, body] from the action
     *             instead; removed in 4.0
     *
     * @param int $code HTTP status code
     */
    public function code(int $code): void
    {
        http_response_code($code);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeOk(): void
    {
        $this->code(200);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeCreated(): void
    {
        $this->code(201);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeAccepted(): void
    {
        $this->code(202);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeNoContent(): void
    {
        $this->code(204);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeBadRequest(): void
    {
        $this->code(400);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeForbidden(): void
    {
        $this->code(403);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeNotFound(): void
    {
        $this->code(404);
    }

    /**
     * @return class-string<static>
     */
    public function getClass(): string
    {
        return static::class;
    }
}
