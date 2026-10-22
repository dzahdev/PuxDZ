# ADR 0001: No external runtime dependencies

## Status

Accepted, 2026-10-23 (Stage 0 of the PHP 8.2 port).

## Context

The 2.0.x-dev line of Pux, forked here as PuxDZ, depended on:

- `phpsgi/phpsgi` (dev-master, 2015): one interface, `PHPSGI\App::call(array &$env, array
  $response)`, implemented by `Pux\Controller\Controller`;
- `universal/universal` (dev-master): `Universal\Http\HttpRequest`, the parent class of
  `Pux\RouteRequest`. On PHP 8.3 it emits four `ArrayAccess` deprecations, and
  `Universal\Exception\UploadException` is a fatal error on autoload because of a property type
  conflict with `\Exception`;
- `corneltek/phpunit-testmore` (dev-master, dev only): global assertion functions `ok()`,
  `is()`, `count_ok()`, `fail()`;
- a C extension in `ext/` (about 3100 lines plus a lemon annotation parser) written against
  the Zend API of PHP 5.4-5.5; it does not build on PHP 5.6 or later.

None of these packages has a tagged release usable from a stable `composer.json`, all of them
are unmaintained, and a router has no functional need for any of them.

## Decision

1. `composer.json` requires only `php: ^8.2` and `ext-pcre: *`. No other package appears in
   `require`.
2. The C extension, the phar stub `pux`, `phprelease.ini`, `scripts/`, `benchmarks/` and the
   extension test runners are deleted.
3. Classes that extend or implement third-party types are replaced by `Pux\*` types (Stage 1):
   `Pux\RouteRequest` without `Universal\Http\HttpRequest`, a `Pux\Handler` interface instead of
   `PHPSGI\App`.
4. Development tools (`phpunit/phpunit`, `phpstan/phpstan`, `friendsofphp/php-cs-fixer`,
   `spatie/phpunit-watcher`) are allowed in `require-dev` only.
5. The testmore functions are provided by a transitional shim in `tests/Support/testmore.php`
   until Stage 2 rewrites the call sites to PHPUnit assertions.
6. `src/` contains no `use` of a class outside `Pux\*` and the PHP standard library
   (architectural invariant 1 of the port plan).

## Consequences

- `composer require dzahdev/puxdz` installs no transitive packages.
- Until Stage 1, `Pux\Controller\Controller` and `Pux\RouteRequest` reference classes that are
  no longer installed: any test that loads them fails with `Error: Interface "PHPSGI\App" not
  found` or `Error: Class "Universal\Http\HttpRequest" not found`. This is accepted for Stage 0;
  Stage 2 records these tests as known defects and Stage 1 removes the references.
- Consumers of 2.0.x-dev that relied on `RouteRequest` inheriting `HttpRequest` methods, or on
  `Controller` being a `PHPSGI\App`, get a breaking change, documented in `UPGRADE-3.0.md`
  (Stage 6).
- The benchmark charts are removed with the extension: they measured the C extension and do
  not describe the pure PHP implementation.
