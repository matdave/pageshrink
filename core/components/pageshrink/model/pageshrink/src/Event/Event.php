<?php
namespace PageShrink\Event;

abstract class Event
{
    /** @var \modX */
    protected $modx;

    /** @var \PageShrink */
    protected $pageshrink;

    /** @var array */
    protected $properties = [];

    public function __construct(\PageShrink &$pageshrink, array $properties = [])
    {
        $this->pageshrink =& $pageshrink;
        $this->modx =& $this->pageshrink->modx;
        $this->properties = $properties;
    }

    abstract public function run();

    protected function getOption($key, $default = null)
    {
        return $this->pageshrink->getOption($key, $this->properties, $default);
    }

    protected function getVersion(): int
    {
        $version = 2;
        if (empty($this->modx->version)) {
            $this->modx->getVersionData();
        }
        if (isset($this->modx->version['version'])) {
            $version = (int) $this->modx->version['version'];
        }
        return $version;
    }
}
