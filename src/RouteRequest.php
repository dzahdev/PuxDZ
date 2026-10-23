<?php

declare(strict_types=1);

namespace Pux;

/**
 * RouteRequest defines request information for routing.
 *
 * You can use RouteRequest's constraint methods to check if a request matches
 * your logics.
 *
 * The class has no parent: it replaces the former base class HttpRequest of the third-party
 * package universal/universal and keeps the public property names that class provided. Every
 * property is readonly: a request is built once, by the constructor or by a factory.
 */
final class RouteRequest implements RouteRequestMatcher
{
    /**
     * @var array<string, mixed> header name (for example "User-Agent") to value, derived from the
     *                           HTTP_* keys, CONTENT_TYPE and CONTENT_LENGTH of the server parameters
     */
    public readonly array $headers;

    /**
     * @param array<array-key, mixed> $serverParameters  server parameters, the shape of $_SERVER
     * @param array<array-key, mixed> $parameters        request parameters, the shape of $_REQUEST
     * @param array<array-key, mixed> $queryParameters   query string parameters, the shape of $_GET
     * @param array<array-key, mixed> $bodyParameters    body parameters, the shape of $_POST
     * @param array<array-key, mixed> $cookieParameters  the shape of $_COOKIE
     * @param array<array-key, mixed> $sessionParameters the shape of $_SESSION
     */
    public function __construct(
        public readonly string $requestMethod,
        public readonly string $path,
        public readonly array $serverParameters = [],
        public readonly array $parameters = [],
        public readonly array $queryParameters = [],
        public readonly array $bodyParameters = [],
        public readonly array $cookieParameters = [],
        public readonly array $sessionParameters = [],
    ) {
        $this->headers = self::headersFromServer($serverParameters);
    }

    /**
     * Return the current path for route dispatching.
     * the path can be PATH_INFO or REQUEST_URI (depends on what the user gives)
     *
     * @return string path
     */
    public function getPath(): string
    {
        return $this->path ?: '/';
    }

    /**
     * Return the request method
     *
     * @return string request method.
     */
    public function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    /**
     * True when the request meets every key of at least one alternative.
     *
     * @param array<array-key, array<string, string>> $constraints list of alternatives; each
     *        alternative is an array with the optional keys host_match, host, request_method,
     *        path_match, path
     */
    public function matchConstraints(array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if ((!isset($constraint['host_match']) || $this->hostMatch($constraint['host_match']))
                && (!isset($constraint['host']) || $this->hostEqual($constraint['host']))
                && (!isset($constraint['request_method']) || $this->requestMethodEqual($constraint['request_method']))
                && (!isset($constraint['path_match']) || $this->pathMatch($constraint['path_match']))
                && (!isset($constraint['path']) || $this->pathEqual($constraint['path']))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * QUERY_STRING is optional in the server array: without it the answer is false, after the
     * expression is compiled, so an invalid expression throws PatternSyntax either way.
     *
     * @param array<int|string, string> $matches
     *
     * @param-out array<int|string, string> $matches
     */
    public function queryStringMatch(string $pattern, array &$matches = []): bool
    {
        $queryString = $this->serverString('QUERY_STRING');
        if ($queryString === null) {
            return self::compiledButAbsent($pattern, $matches);
        }

        return Pcre::match($pattern, $queryString, $matches);
    }

    /**
     * Answer of a regex predicate whose subject is absent from the server array: compiles the
     * expression (PatternSyntax when it is invalid) and returns false with no captures.
     *
     * @param array<int|string, string> $matches
     *
     * @param-out array<int|string, string> $matches
     */
    private static function compiledButAbsent(string $pattern, array &$matches): bool
    {
        Pcre::match($pattern, '');
        $matches = [];

        return false;
    }

    /**
     * SERVER_PORT is optional in the server array: without it the answer is false.
     */
    public function portEqual(int|string $port): bool
    {
        $serverPort = $this->serverString('SERVER_PORT');
        if ($serverPort === null) {
            return false;
        }

        return (int) $serverPort === (int) $port;
    }

    /**
     * Check if the request host is in the list of host: each entry is compared with hostEqual
     * (case-insensitive equality, false without HTTP_HOST).
     *
     * @param array<array-key, string> $hosts
     */
    public function isOneOfHosts(array $hosts): bool
    {
        foreach ($hosts as $host) {
            if ($this->hostEqual($host)) {
                return true;
            }
        }

        return false;
    }

    public function pathLike(string $path): bool
    {
        $pattern = '#' . preg_quote($path, '#') . '#i';

        return preg_match($pattern, $this->path) === 1;
    }

    /**
     * @param array<int|string, string> $matches
     *
     * @param-out array<int|string, string> $matches
     */
    public function pathMatch(string $pattern, array &$matches = []): bool
    {
        return Pcre::match($pattern, $this->path, $matches);
    }

    public function pathEqual(string $path): bool
    {
        return strcasecmp($path, $this->path) === 0;
    }

    public function pathContain(string $path): bool
    {
        return str_contains($this->path, $path);
    }

    public function pathStartWith(string $path): bool
    {
        return str_starts_with($this->path, $path);
    }

    public function pathEndWith(string $suffix): bool
    {
        return str_ends_with($this->path, $suffix);
    }

    /**
     * HTTP_HOST is optional in the server array: without it the answer is false, after the
     * expression is compiled, so an invalid expression throws PatternSyntax either way.
     *
     * @param array<int|string, string> $matches
     *
     * @param-out array<int|string, string> $matches
     */
    public function hostMatch(string $host, array &$matches = []): bool
    {
        $httpHost = $this->serverString('HTTP_HOST');
        if ($httpHost !== null) {
            return Pcre::match($host, $httpHost, $matches);
        }

        return self::compiledButAbsent($host, $matches);
    }

    public function hostEqual(string $host): bool
    {
        $httpHost = $this->serverString('HTTP_HOST');
        if ($httpHost !== null) {
            return strcasecmp($httpHost, $host) === 0;
        }

        return false;
    }

    /**
     * requestMethodEqual does not use PCRE pattern to match request method.
     */
    public function requestMethodEqual(string $requestMethod): bool
    {
        return strcasecmp($this->requestMethod, $requestMethod) === 0;
    }

    /**
     * A helper function for creating request object based on request method and request uri.
     *
     * @param array<array-key, mixed> $env a flat server array, or a globals-shaped array with the key _SERVER
     *
     * @throws \InvalidArgumentException when _SERVER, _REQUEST, _GET, _POST, _COOKIE or _SESSION is not an array
     */
    public static function create(string $method, string $path, array $env = []): self
    {
        return self::fromEnvironment($method, $path, $env);
    }

    /**
     * Create request object from an environment array: a flat server array, or a globals-shaped
     * array with the keys _SERVER, _REQUEST, _GET, _POST, _COOKIE and _SESSION. The path is
     * PATH_INFO, else REQUEST_URI without its query string (as in fromServer); the query string
     * is read from QUERY_STRING by queryStringMatch. The request method defaults to GET when
     * REQUEST_METHOD is absent.
     *
     * @param array<array-key, mixed> $env
     *
     * @throws \InvalidArgumentException when neither PATH_INFO nor REQUEST_URI is present, or a
     *                                   key read here holds a value of the wrong type
     */
    public static function createFromEnv(array $env): self
    {
        $server = self::arrayEntry($env, '_SERVER');

        $path = self::stringEntry($env, 'PATH_INFO');
        if ($path === null && ($uri = self::stringEntry($env, 'REQUEST_URI')) !== null) {
            $path = self::pathOfUri($uri);
        }
        $path ??= self::stringEntry($server, 'PATH_INFO');
        if ($path === null && ($uri = self::stringEntry($server, 'REQUEST_URI')) !== null) {
            $path = self::pathOfUri($uri);
        }
        if ($path === null) {
            throw new \InvalidArgumentException('The environment has no PATH_INFO or REQUEST_URI, neither at the top level nor in _SERVER.');
        }

        // The superglobal shape keeps REQUEST_METHOD under _SERVER.
        $requestMethod = self::stringEntry($env, 'REQUEST_METHOD') ?? self::stringEntry($server, 'REQUEST_METHOD') ?? 'GET';

        return self::fromEnvironment($requestMethod, $path, $env);
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
     * @param array<array-key, mixed>|null $server
     *
     * @throws \InvalidArgumentException when REQUEST_METHOD or both PATH_INFO and REQUEST_URI are missing
     */
    public static function fromServer(?array $server = null): self
    {
        $server ??= $_SERVER;

        $requestMethod = self::stringEntry($server, 'REQUEST_METHOD');
        if ($requestMethod === null) {
            throw new \InvalidArgumentException('The server array has no REQUEST_METHOD.');
        }

        $path = self::stringEntry($server, 'PATH_INFO');
        if ($path === null && ($uri = self::stringEntry($server, 'REQUEST_URI')) !== null) {
            $path = self::pathOfUri($uri);
        }
        if ($path === null) {
            throw new \InvalidArgumentException('The server array has no PATH_INFO or REQUEST_URI.');
        }

        return new self($requestMethod, $path, $server);
    }

    /**
     * A request whose server parameters are $env['_SERVER'], or $env itself when it has no such
     * key, and whose parameter arrays are the superglobal-shaped keys of $env (each optional).
     *
     * @param array<array-key, mixed> $env
     *
     * @throws \InvalidArgumentException when one of those keys is not an array
     */
    private static function fromEnvironment(string $method, string $path, array $env): self
    {
        return new self(
            $method,
            $path,
            isset($env['_SERVER']) ? self::arrayEntry($env, '_SERVER') : $env,
            self::arrayEntry($env, '_REQUEST'),
            self::arrayEntry($env, '_GET'),
            self::arrayEntry($env, '_POST'),
            self::arrayEntry($env, '_COOKIE'),
            self::arrayEntry($env, '_SESSION'),
        );
    }

    private static function pathOfUri(string $uri): string
    {
        return explode('?', $uri, 2)[0];
    }

    /**
     * The server parameter $key as a string; null when it is absent.
     *
     * @throws \InvalidArgumentException when it is present and not a scalar
     */
    private function serverString(string $key): ?string
    {
        return self::stringEntry($this->serverParameters, $key);
    }

    /**
     * $array[$key] as a string; null when the key is absent or null. A scalar is converted.
     *
     * @param array<array-key, mixed> $array
     *
     * @throws \InvalidArgumentException when the value is not a scalar
     */
    private static function stringEntry(array $array, string $key): ?string
    {
        $value = $array[$key] ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf('The value of %s is a %s, a string is expected.', $key, get_debug_type($value)));
        }

        return (string) $value;
    }

    /**
     * $array[$key] as an array; an empty array when the key is absent or null.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     *
     * @throws \InvalidArgumentException when the value is not an array
     */
    private static function arrayEntry(array $array, string $key): array
    {
        $value = $array[$key] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('The value of %s is a %s, an array is expected.', $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * Header name to value from the HTTP_* keys, CONTENT_TYPE and CONTENT_LENGTH of a server
     * array: HTTP_USER_AGENT becomes "User-Agent", CONTENT_TYPE becomes "Content-Type".
     *
     * @param array<array-key, mixed> $server
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
            $headers[implode('-', array_map(ucfirst(...), explode('_', strtolower($name))))] = $value;
        }

        return $headers;
    }
}
