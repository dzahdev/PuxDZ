<?php
namespace Pux\Controller;
use Pux\Controller\ExpandableController;
use Pux\Mux;
use Pux\RequestMethod;

abstract class RESTfulController extends ExpandableController
{
    /**
     * @Route("");
     * @Method("GET")
     */
    public function collectionAction()
    {

    }

    /**
     * @Method("POST")
     * @Route("");
     */
    public function createAction()
    {

    }

    /**
     * @Route("/:id")
     * @Method("POST")
     */
    public function updateAction($id)
    {

    }

    /**
     * @Route("/:id")
     * @Method("GET")
     */
    public function loadAction($id)
    {

    }

    /**
     * @Route("/:id");
     * @Method("DELETE")
     */
    public function deleteAction($id)
    {

    }




    /**
     * Expand controller actions into Mux object
     *
     * @return Mux
     */
    public function expand(array $options = [], $dynamic = false)
    {
        $mux   = new Mux();
        $target = $dynamic ? $this : $this->getClass();
        $mux->add('/:id', [$target, 'updateAction'], array_merge($options, ['method' => RequestMethod::POST->value]));
        $mux->add('/:id', [$target, 'loadAction'],   array_merge($options, ['method' => RequestMethod::GET->value]));
        $mux->add('/:id', [$target, 'deleteAction'], array_merge($options, ['method' => RequestMethod::DELETE->value]));
        $mux->add('', [$target, 'createAction'],     array_merge($options, ['method' => RequestMethod::POST->value]));
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
    public function code($code)
    {
        http_response_code($code);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeOk()
    {
        $this->code(200);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeCreated()
    {
        $this->code(201);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeAccepted()
    {
        $this->code(202);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeNoContent()
    {
        $this->code(204);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeBadRequest()
    {
        $this->code(400);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeForbidden()
    {
        $this->code(403);
    }

    /**
     * @deprecated since 3.0, use the response array; removed in 4.0
     */
    public function codeNotFound()
    {
        $this->code(404);
    }

    public function getClass()
    {
        return static::class;
    }
}

