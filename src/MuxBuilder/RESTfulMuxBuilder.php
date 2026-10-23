<?php

declare(strict_types=1);

namespace Pux\MuxBuilder;

use Pux\Expandable;
use Pux\Mux;

/**
 * Mounts RESTful resources (Expandable controllers) at "<prefix>/<resource id>".
 */
final class RESTfulMuxBuilder
{
    private Mux $mux;

    /**
     * @var array{prefix: string} Valid options are: 'prefix'
     */
    private array $options;

    /**
     * @var array<string, Expandable> resource id => expandable controller object
     */
    private array $resources = [];

    /**
     * true once build() has mounted the resources
     */
    private bool $built = false;

    /**
     * @param array{prefix?: string} $options 'prefix' (default '/') of every resource path; a
     *                                        trailing "/" of the prefix is dropped, so '/' and
     *                                        '' mount at "/<resource id>"
     */
    public function __construct(?Mux $mux = null, array $options = [])
    {
        $this->mux = $mux ?? new Mux();
        $this->options = array_merge(['prefix' => '/'], $options);
    }

    /**
     * Register a RESTful resource; build() mounts it. Registering the same resource id again
     * replaces the earlier resource.
     *
     * @param string     $resourceId the RESTful resource ID
     * @param Expandable $expandable a controller object that implements Expandable interface.
     *
     * @throws \LogicException after build(): the resource would never be mounted
     */
    public function addResource(string $resourceId, Expandable $expandable): void
    {
        if ($this->built) {
            throw new \LogicException(sprintf('Resource %s is added after build(); register every resource before build().', $resourceId));
        }

        $this->resources[$resourceId] = $expandable;
    }

    /**
     * build method mounts every registered resource at "<prefix>/<resource id>" (the prefix
     * without its trailing "/") and returns
     * the Mux. The resources are mounted by the first call only; a later call returns the same
     * Mux unchanged.
     */
    public function build(): Mux
    {
        if ($this->built) {
            return $this->mux;
        }

        $prefix = rtrim($this->options['prefix'], '/');
        foreach ($this->resources as $resId => $controller) {
            $this->mux->mount($prefix . '/' . $resId, $controller->expand());
        }
        $this->built = true;

        return $this->mux;
    }
}
