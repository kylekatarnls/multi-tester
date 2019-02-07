<?php

namespace MultiTester;

use ArrayObject;
use Symfony\Component\Yaml\Yaml;

class File extends ArrayObject
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
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
        return strtolower(substr($this->path, -5)) === '.json' ? $this->json() : $this->yaml();
    }

    public function toArray()
    {
        return $this->getArrayCopy();
    }
}
