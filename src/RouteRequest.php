<?php

declare(strict_types=1);

namespace Pux;

/**
 * RouteRequest defines request information for routing.
 *
 * You can use RouteRequest's constraint methods to check if a request matches
 * your logics.
 *
 * The class has no parent: it replaces the former base class Universal\Http\HttpRequest and keeps
 * the public property names that class provided.
 */
class RouteRequest implements RouteRequestMatcher
{
    /**
     * @var array<string, mixed> server parameters, the shape of $_SERVER
     */
    public array $serverParameters = [];

    /**
     * @var array<string, mixed> request parameters, the shape of $_REQUEST
     */
    public array $parameters = [];

    /**
     * @var array<string, mixed> query string parameters, the shape of $_GET
     */
    public array $queryParameters = [];

    /**
     * @var array<string, mixed> body parameters, the shape of $_POST
     */
    public array $bodyParameters = [];

    /**
     * @var array<string, mixed> the shape of $_COOKIE
     */
    public array $cookieParameters = [];

    /**
     * @var array<string, mixed> the shape of $_SESSION
     */
    public array $sessionParameters = [];

    /**
     * @var array<string, mixed> header name (for example "User-Agent") to value, derived from the
     *                           HTTP_* keys, CONTENT_TYPE and CONTENT_LENGTH of the server parameters
     */
    public array $headers = [];

    public function __construct(public string $requestMethod, public string $path)
    {
    }

    /**
     * Return the current path for route dispatching.
     * the path can be PATH_INFO or REQUEST_URI (depends on what the user gives)
     *
     * @return string path
     */
    public function getPath()
    {
        return $this->path ?: '/';
    }

    /**
     * Return the request method
     *
     * @return string request method.
     */
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

    /**
     * @param array $constraints list of alternatives; each alternative is an array with the
     *                           optional keys host_match, host, request_method, path_match, path
     *
     * @return boolean true on match
     */
    public function matchConstraints(array $constraints)
    {
        foreach ($constraints as $constraint) {
            $result = true;
            if (isset($constraints['host_match'])) {
                $result = $result && $this->hostMatch($constraints['host_match']);
            }

            if (isset($constraints['host'])) {
                $result = $result && $this->hostEqual($constraints['host']);
            }

            if (isset($constraints['request_method'])) {
                $result = $result && $this->requestMethodEqual($constraints['request_method']);
            }

            if (isset($constraints['path_match'])) {
                $result = $result && $this->pathMatch($constraints['path_match']);
            }

            if (isset($constraints['path'])) {
                $result = $result && $this->pathEqual($constraints['path']);
            }

            // If it matches all constraints, we simply return true and skip other constraints
            if ($result) {
                return true;
            }

            // try next one
        }

        return false;
    }

    public function queryStringMatch($pattern, array &$matches = [])
    {
        return preg_match($pattern, (string) $this->serverParameters['QUERY_STRING'], $matches) !== false;
    }

    public function portEqual($port)
    {
        if (isset($this->serverParameters['SERVER_PORT'])) {
            return intval($this->serverParameters['SERVER_PORT']) == intval($port);
        }
    }

    /**
     * Check if the request host is in the list of host.
     *
     *
     * @return bool
     */
    public function isOneOfHosts(array $hosts)
    {
        foreach ($hosts as $host) {
            if ($this->matchHost($host)) {
                return true;
            }
        }

        return false;
    }

    public function pathLike($path)
    {
        $pattern = '#'.preg_quote((string) $path, '#').'#i';

        return preg_match($pattern, $this->path) !== false;
    }

    public function pathMatch($pattern, array &$matches = [])
    {
        return preg_match($pattern, $this->path, $matches) !== false;
    }

    public function pathEqual($path)
    {
        return strcasecmp((string) $path, $this->path) === 0;
    }

    public function pathContain($path)
    {
        return str_contains($this->path, (string) $path);
    }

    public function pathStartWith($path)
    {
        return str_starts_with($this->path, (string) $path);
    }

    public function pathEndWith($suffix)
    {
        $p = strrpos($this->path, (string) $suffix);

        return ($p == strlen($this->path) - strlen((string) $suffix));
    }

    public function hostMatch($host, array &$matches = [])
    {
        if (isset($this->serverParameters['HTTP_HOST'])) {
            return preg_match($host, (string) $this->serverParameters['HTTP_HOST'], $matches) !== false;
        }

        // the HTTP HOST is not defined.
        return false;
    }

    public function hostEqual($host)
    {
        if (isset($this->serverParameters['HTTP_HOST'])) {
            return strcasecmp((string) $this->serverParameters['HTTP_HOST'], (string) $host) === 0;
        }

        return false;
    }

    /**
     * requestMethodEqual does not use PCRE pattern to match request method.
     *
     * @param string $requestMethod
     */
    public function requestMethodEqual($requestMethod)
    {
        return strcasecmp($this->requestMethod, (string) $requestMethod) === 0;
    }

    /**
     * A helper function for creating request object based on request method and request uri.
     *
     * @param string $method
     * @param string $path
     * @param array  $env    a flat server array, or a globals-shaped array with the key _SERVER
     *
     * @return RouteRequest
     */
    public static function create($method, $path, array $env = [])
    {
        $self = new self($method, $path);
        $self->serverParameters = isset($env['_SERVER']) ? $env['_SERVER'] : $env;
        $self->headers = self::headersFromServer($self->serverParameters);

        $self->parameters = $env['_REQUEST'] ?? [];
        $self->queryParameters = $env['_GET'] ?? [];
        $self->bodyParameters = $env['_POST'] ?? [];
        $self->cookieParameters = $env['_COOKIE'] ?? [];
        $self->sessionParameters = $env['_SESSION'] ?? [];

        return $self;
    }

    /**
     * Create request object from an environment array: a flat server array, or a globals-shaped
     * array with the keys _SERVER, _REQUEST, _GET, _POST, _COOKIE and _SESSION.
     *
     * @return RouteRequest
     */
    public static function createFromEnv(array $env)
    {
        // cache
        if (isset($env['__request_object'])) {
            return $env['__request_object'];
        }

        if (isset($env['PATH_INFO'])) {
            $path = $env['PATH_INFO'];
        } elseif (isset($env['REQUEST_URI'])) {
            $path = $env['REQUEST_URI'];
        } elseif (isset($env['_SERVER']['PATH_INFO'])) {
            $path = $env['_SERVER']['PATH_INFO'];
        } elseif (isset($env['_SERVER']['REQUEST_URI'])) {
            $path = $env['_SERVER']['REQUEST_URI'];
        } else {
            // XXX: check path or throw exception
            $path = '/';
        }

        $requestMethod = 'GET';
        if (isset($env['REQUEST_METHOD'])) {
            $requestMethod = $env['REQUEST_METHOD'];
        } else if (isset($env['_SERVER']['REQUEST_METHOD'])) { // compatibility for superglobal
            $requestMethod = $env['_SERVER']['REQUEST_METHOD'];
        }

        // create request object with request method and path,
        // we can assign other parameters later.
        $self = new self($requestMethod, $path);
        $self->serverParameters = isset($env['_SERVER']) ? $env['_SERVER'] : $env;
        $self->headers = self::headersFromServer($self->serverParameters);

        $self->parameters = $env['_REQUEST'];
        $self->queryParameters = $env['_GET'];
        $self->bodyParameters = $env['_POST'];
        $self->cookieParameters = $env['_COOKIE'];
        $self->sessionParameters = $env['_SESSION'];

        return $env['__request_object'] = $self;
    }

    /**
     * Create a request from a server array of the shape of $_SERVER.
     *
     * The method is REQUEST_METHOD; the path is PATH_INFO, or REQUEST_URI without its query
     * string. Headers are derived from the HTTP_* keys, CONTENT_TYPE and CONTENT_LENGTH. The
     * parameter arrays (query, body, cookies, session) stay empty: this factory reads only the
     * server array. This is the only place in the library that reads $_SERVER, and only when
     * $server is null.
     *
     * @param array<string, mixed>|null $server
     *
     * @throws \InvalidArgumentException when REQUEST_METHOD or both PATH_INFO and REQUEST_URI are missing
     */
    public static function fromServer(?array $server = null): self
    {
        $server ??= $_SERVER;

        if (!isset($server['REQUEST_METHOD'])) {
            throw new \InvalidArgumentException('The server array has no REQUEST_METHOD.');
        }

        if (isset($server['PATH_INFO'])) {
            $path = $server['PATH_INFO'];
        } elseif (isset($server['REQUEST_URI'])) {
            $path = explode('?', (string) $server['REQUEST_URI'], 2)[0];
        } else {
            throw new \InvalidArgumentException('The server array has no PATH_INFO or REQUEST_URI.');
        }

        $self = new self($server['REQUEST_METHOD'], $path);
        $self->serverParameters = $server;
        $self->headers = self::headersFromServer($server);

        return $self;
    }

    /**
     * Header name to value from the HTTP_* keys, CONTENT_TYPE and CONTENT_LENGTH of a server
     * array: HTTP_USER_AGENT becomes "User-Agent", CONTENT_TYPE becomes "Content-Type".
     *
     * @param array<string, mixed> $server
     *
     * @return array<string, mixed>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            $key = (string) $key;
            if (str_starts_with($key, 'HTTP_')) {
                $name = substr($key, 5);
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $name = $key;
            } else {
                continue;
            }
            $headers[implode('-', array_map('ucfirst', explode('_', strtolower($name))))] = $value;
        }

        return $headers;
    }
}
