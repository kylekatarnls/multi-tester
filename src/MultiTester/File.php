<?php

namespace MultiTester;

use ArrayObject;
use Symfony\Component\Yaml\Yaml;

class File extends ArrayObject
{
    /** @var string */
    protected $path;

    /** @var 'json'|'yaml'|null */
    protected $mode;

    /**
     * @param string             $path
     * @param 'json'|'yaml'|null $mode
     */
    public function __construct($path, $mode = null)
    {
        $this->path = $path;
        $this->mode = $mode;
        $data = $this->parse();
        parent::__construct(is_array($data) ? $data : []);
    }

    public function yaml()
    {
        return Yaml::parse(file_get_contents($this->path));
    }

    public function json()
    {
        return @json_decode(file_get_contents($this->path), JSON_OBJECT_AS_ARRAY);
    }

    public function parse()
    {
        $json = $this->mode === null
            ? (strtolower(substr($this->path, -5)) === '.json')
            : ($this->mode === 'json');

        return $json ? $this->json() : $this->yaml();
    }

    public function toArray()
    {
        return $this->getArrayCopy();
    }

    public function isValid()
    {
        return $this->toArray() !== [];
    }
}
