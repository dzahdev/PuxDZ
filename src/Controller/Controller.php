<?php
namespace Pux\Controller;
use ReflectionClass;
use ReflectionObject;
use Pux\ArgumentResolver;
use Pux\Mux;
use Pux\Handler;
use Pux\RouteRequest;
use Pux\Exception\InvalidCallback;
use Pux\Exception\MissingArgument;

class Controller implements Handler
{
    /**
     * @var array environment array: server parameters plus the keys pux.controller_action and pux.route
     */
    protected $environment = [];


    /**
     * @var array Response array in [ code, headers, content ]
     */
    protected $response = [];

    /**
     * @var RouteRequest|null request built from $environment by getRequest()
     */
    protected $_request;


    /**
     * @var array The matched route array
     */
    protected $matchedRoute = [];

    public function call(array & $environment, array $response)
    {
        $this->context($environment, $response);

        $action = $environment['pux.controller_action'];
        [$pcre, $pattern, $callbackArg, $options] = $this->matchedRoute;

        $reflectionClass = new ReflectionClass($this);

        $rps = $reflectionClass->getMethod($action)->getParameters();

        [$arguments, $unbound] = ArgumentResolver::bind($rps, $options['vars'] ?? [], $options['default'] ?? []);
        foreach ($unbound as $rp) {
            if (!$rp->isOptional()) {
                throw MissingArgument::forParameter($rp->getName(), static::class . '::' . $action);
            }
        }

        return call_user_func_array([$this, $action], $arguments);
    }

    protected function context(array & $environment, array $response)
    {
        $this->environment  = $environment;
        $this->response     = $response;
        $this->matchedRoute = $environment['pux.route'];
    }

    public function hasMatchedRoute()
    {
        return $this->matchedRoute ? true : false;
    }


    /**
     * @return array The standard route structure
     */
    public function getMatchedRoute()
    {
        return $this->matchedRoute;
    }

    /**
     * Create and return the RouteRequest of the environment given to call(): a flat
     * environment of the shape of $_SERVER or one with the server array under _SERVER
     * (see RouteRequest::createFromEnv).
     *
     * @param boolean $recreate
     * @return RouteRequest
     */
    protected function getRequest($recreate = false)
    {
        if (!$recreate && $this->_request) {
            return $this->_request;
        }

        return $this->_request = RouteRequest::createFromEnv($this->environment);
    }

    protected function toJson($data, $encodeFlags = null)
    {
        if ($encodeFlags === null) {
            $encodeFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        }

        return [200, ['Content-Type: application/json'], json_encode($data, $encodeFlags)];
    }


    /**
     * Run controller action
     *
     * @param string $action Action name, the action name should not include "Action" as its suffix.
     * @param array $vars    Action method parameters, which will be applied to the method parameters by their names.
     * @return string        Return execution result in string format.
     */
    public function runAction($action, array $vars = [])
    {
        $method = sprintf('%sAction', $action);
        if (! method_exists($this,$method)) {
            throw InvalidCallback::methodNotFound(static::class, $method);
        }

        $reflectionObject = new ReflectionObject( $this );
        $reflectionMethod = $reflectionObject->getMethod($method);

        // Map vars to function arguments by name; a parameter without a var is left out.
        [$arguments] = ArgumentResolver::bind($reflectionMethod->getParameters(), $vars, []);

        return call_user_func_array( [$this, $method] , $arguments );
    }

    /**
     * Forward to another controller action
     *
     * @param string|Controller $controller A controller class name or a controller instance.
     * @param string            $actionName The action name
     * @param array             $parameters Parameters for the action method
     *
     *
     *  return $this->forward('\OAuthPlugin\Controller\AuthenticationErrorPage','index',array(
     *      'vars' => array(
     *          'message' => $e->lastResponse
     *      )
     *  ));
     */
    public function forward($controller, $actionName = 'index' , $parameters = [])
    {
        if (is_string($controller)) {
            $controller = new $controller;
        }

        return $controller->runAction($actionName, $parameters);
    }

    /**
     * Check if the controller action exists
     *
     * @param  string  $action action name
     * @return boolean
     */
    public function hasAction($action)
    {
        $method = sprintf('%sAction', $action);
        if (method_exists($this, $method)) {
            return $method;
        }

        return false;
    }
}
