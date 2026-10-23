<?php
namespace Pux\MuxBuilder;
use Pux\Mux;
use Pux\Expandable;

class RESTfulMuxBuilder
{

    /**
     * @var Pux\Mux
     */
    protected $mux;

    /**
     * @var array $options
     *
     * Valid options are: 'prefix'
     */
    protected $options = [];


    /**
     * @var array $reousrces
     *
     * [ resource Id => expandable controller object ]
     *
     */
    protected $resources = [];

    public function __construct(Mux & $mux = null, array $options = [])
    {
        $this->mux = $mux ?: new Mux;
        $this->options = array_merge(['prefix' => '/'], $options);
    }


    /**
     * Register a RESTful resource; build() mounts it. Registering the same resource id again
     * replaces the earlier resource.
     *
     * @param string $resourceId the RESTful resource ID
     * @param Expandable $expandable a controller object that implements Expandable interface.
     */
    public function addResource($resourceId, Expandable $expandable)
    {
        $this->resources[$resourceId] = $expandable;
    }


    /**
     * build method mounts every registered resource at "<prefix>/<resource id>" and returns
     * the Mux. Each call mounts the resources again, so call it once.
     *
     * @return Pux\Mux
     */
    public function build()
    {
        $prefix = $this->options['prefix'];
        foreach ($this->resources as $resId => $controller) {
            $resourceMux = $controller->expand();
            $path = $prefix . '/' . $resId;
            $this->mux->mount($path, $resourceMux);
        }

        return $this->mux;
    }
}



