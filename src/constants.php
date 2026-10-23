<?php

declare(strict_types=1);

/*
 * Global request method constants of Pux 2.x, loaded through composer.json autoload.files.
 *
 * @deprecated since 3.0, use the cases of Pux\RequestMethod (the values are the same); the
 *             constants are removed in 4.0. Each define is guarded, so loading this file twice
 *             or after another definition does not fail.
 */

use Pux\RequestMethod;

if (!defined('REQUEST_METHOD_GET')) {
    /** @deprecated since 3.0, use Pux\RequestMethod::GET */
    define('REQUEST_METHOD_GET', RequestMethod::GET->value);
}

if (!defined('REQUEST_METHOD_POST')) {
    /** @deprecated since 3.0, use Pux\RequestMethod::POST */
    define('REQUEST_METHOD_POST', RequestMethod::POST->value);
}

if (!defined('REQUEST_METHOD_PUT')) {
    /** @deprecated since 3.0, use Pux\RequestMethod::PUT */
    define('REQUEST_METHOD_PUT', RequestMethod::PUT->value);
}

if (!defined('REQUEST_METHOD_DELETE')) {
    /** @deprecated since 3.0, use Pux\RequestMethod::DELETE */
    define('REQUEST_METHOD_DELETE', RequestMethod::DELETE->value);
}

if (!defined('REQUEST_METHOD_PATCH')) {
    /** @deprecated since 3.0, use Pux\RequestMethod::PATCH */
    define('REQUEST_METHOD_PATCH', RequestMethod::PATCH->value);
}

if (!defined('REQUEST_METHOD_HEAD')) {
    /** @deprecated since 3.0, use Pux\RequestMethod::HEAD */
    define('REQUEST_METHOD_HEAD', RequestMethod::HEAD->value);
}

if (!defined('REQUEST_METHOD_OPTIONS')) {
    /** @deprecated since 3.0, use Pux\RequestMethod::OPTIONS */
    define('REQUEST_METHOD_OPTIONS', RequestMethod::OPTIONS->value);
}
