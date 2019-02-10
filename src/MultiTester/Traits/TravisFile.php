<?php

namespace MultiTester\Traits;

trait TravisFile
{
    /**
     * @var string Travis CI settings file.
     */
    protected $travisFile = '.travis.yml';

    /**
     * @return string
     */
    public function getTravisFile()
    {
        return $this->travisFile;
    }

    /**
     * @param string $travisFile
     */
    public function setTravisFile($travisFile)
    {
        $this->travisFile = $travisFile;
    }
}
