<?php

declare(strict_types=1);

namespace Pux\Controller;

use Pux\ArgumentResolver;
use Pux\Exception\InvalidCallback;
use Pux\Exception\MissingArgument;
use Pux\Handler;
use Pux\RouteRequest;
use ReflectionMethod;

/**
 * Base controller: call() runs the action named by the environment with the variables of the
 * matched route, runAction() and forward() run an action by name.
 *
 * Actions are called directly under strict_types, so their scalar parameters receive the values
 * converted by ArgumentResolver.
 */
class Controller implements Handler
{
    /**
     * @var array<array-key, mixed> environment array: server parameters plus the keys pux.controller_action and pux.route
     */
    protected array $environment = [];

    /**
     * @var array<array-key, mixed> Response array in [ code, headers, content ]
     */
    protected array $response = [];

    /**
     * request built from $environment by getRequest()
     */
    protected ?RouteRequest $_request = null;

    /**
     * @var array<array-key, mixed> The matched route array [pcre, pattern, callback, options]; empty before call()
     */
    protected array $matchedRoute = [];

    /**
     * Runs the action named by $environment['pux.controller_action'] with the variables and
     * defaults of the route $environment['pux.route'].
     *
     * @param array<array-key, mixed> $environment
     * @param array<array-key, mixed> $response
     *
     * @throws \InvalidArgumentException when pux.controller_action is not a string or pux.route is not a route array
     * @throws InvalidCallback when the controller has no such action method
     * @throws MissingArgument when a required parameter of the action gets no value
     */
    public function call(array &$environment, array $response): mixed
    {
        $this->context($environment, $response);

        $action = $environment['pux.controller_action'] ?? null;
        if (!is_string($action)) {
            throw new \InvalidArgumentException(sprintf('The environment key pux.controller_action is a %s; the action method name is expected.', get_debug_type($action)));
        }
        if (!method_exists($this, $action)) {
            throw InvalidCallback::methodNotFound(static::class, $action);
        }

        $options = $this->matchedRoute[3] ?? null;
        if (!is_array($options)) {
            throw new \LogicException('The matched route has no options array; context() sets it from pux.route.');
        }
        $arguments = ArgumentResolver::bind(
            (new ReflectionMethod($this, $action))->getParameters(),
            self::arrayOption($options, 'vars'),
            self::arrayOption($options, 'default'),
            static::class . '::' . $action,
        );

        return $this->$action(...$arguments);
    }

    /**
     * @param array<array-key, mixed> $environment
     * @param array<array-key, mixed> $response
     *
     * @throws \InvalidArgumentException when pux.route is not a route array
     */
    protected function context(array &$environment, array $response): void
    {
        $route = $environment['pux.route'] ?? null;
        if (!is_array($route) || !isset($route[3]) || !is_array($route[3])) {
            throw new \InvalidArgumentException(sprintf('The environment key pux.route is a %s; the matched route [pcre, pattern, callback, options] is expected.', get_debug_type($route)));
        }

        $this->environment = $environment;
        $this->response = $response;
        $this->matchedRoute = $route;
    }

    /**
     * The route option $key, an array when present.
     *
     * @param array<array-key, mixed> $options
     *
     * @return array<array-key, mixed>
     *
     * @throws \InvalidArgumentException when the option is present and not an array
     */
    private static function arrayOption(array $options, string $key): array
    {
        $value = $options[$key] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('The route option %s is a %s; an array is expected.', $key, get_debug_type($value)));
        }

        return $value;
    }

    public function hasMatchedRoute(): bool
    {
        return $this->matchedRoute !== [];
    }

    /**
     * @return array<array-key, mixed> The standard route structure [pcre, pattern, callback, options]; empty before call()
     */
    public function getMatchedRoute(): array
    {
        return $this->matchedRoute;
    }

    /**
     * Create and return the RouteRequest of the environment given to call(): a flat
     * environment of the shape of $_SERVER or one with the server array under _SERVER
     * (see RouteRequest::createFromEnv).
     */
    protected function getRequest(bool $recreate = false): RouteRequest
    {
        if (!$recreate && $this->_request !== null) {
            return $this->_request;
        }

        return $this->_request = RouteRequest::createFromEnv($this->environment);
    }

    /**
     * A JSON response triple.
     *
     * @param int|null $encodeFlags json_encode() flags; null means JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
     *
     * @return array{int, list<string>, string}
     *
     * @throws \JsonException when $data cannot be encoded
     */
    protected function toJson(mixed $data, ?int $encodeFlags = null): array
    {
        $encodeFlags ??= JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return [200, ['Content-Type: application/json'], json_encode($data, $encodeFlags | JSON_THROW_ON_ERROR)];
    }

    /**
     * Run controller action
     *
     * @param string                  $action Action name, the action name should not include "Action" as its suffix.
     * @param array<array-key, mixed> $vars   Action method parameters, bound by name, then by position (ArgumentResolver).
     *
     * @return mixed the result of the action
     *
     * @throws InvalidCallback when the controller has no such action method
     * @throws MissingArgument when a required parameter of the action gets no value
     */
    public function runAction(string $action, array $vars = []): mixed
    {
        $method = sprintf('%sAction', $action);
        if (!method_exists($this, $method)) {
            throw InvalidCallback::methodNotFound(static::class, $method);
        }

        // Bind vars to the method parameters by the ArgumentResolver rule (no route defaults).
        $arguments = ArgumentResolver::bind((new ReflectionMethod($this, $method))->getParameters(), $vars, [], static::class . '::' . $method);

        return $this->$method(...$arguments);
    }

    /**
     * Forward to another controller action
     *
     *     return $this->forward(AuthenticationErrorPage::class, 'index', [
     *         'message' => $e->getMessage(),
     *     ]);
     *
     * @param Controller|string       $controller A controller class name or a controller instance.
     * @param string                  $actionName The action name, without the suffix "Action"
     * @param array<array-key, mixed> $parameters Action method parameters, as $vars of runAction()
     *
     * @return mixed the result of the action
     *
     * @throws InvalidCallback when the class does not exist or has no such action method
     * @throws \InvalidArgumentException when the class is not a Controller
     */
    public function forward(Controller|string $controller, string $actionName = 'index', array $parameters = []): mixed
    {
        if (is_string($controller)) {
            if (!class_exists($controller)) {
                throw InvalidCallback::classNotFound($controller);
            }
            if (!is_a($controller, self::class, true)) {
                throw new \InvalidArgumentException(sprintf('%s is not a %s.', $controller, self::class));
            }
            $controller = new $controller();
        }

        return $controller->runAction($actionName, $parameters);
    }

    /**
     * Check if the controller action exists
     *
     * @param string $action action name, without the suffix "Action"
     *
     * @return string|false the action method name; false when the controller has none
     */
    public function hasAction(string $action): string|false
    {
        $method = sprintf('%sAction', $action);
        if (method_exists($this, $method)) {
            return $method;
        }

        return false;
    }
}
