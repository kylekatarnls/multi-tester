<?php

namespace MultiTester\Traits;

Trait MultiTesterFile
{
    /**
     * @var string Multi-tester default settings file.
     */
    protected $multiTesterFile = '.multi-tester.yml';

    /**
     * @return string
     */
    public function getMultiTesterFile()
    {
        return $this->multiTesterFile;
    }

    /**
     * @param string $multiTesterFile
     */
    public function setMultiTesterFile($multiTesterFile)
    {
        $this->multiTesterFile = $multiTesterFile;
    }
}
