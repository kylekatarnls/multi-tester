<?php

namespace MultiTester\Traits;

trait WorkingDirectory
{
    /**
     * @var string Temporary working directory.
     */
    protected $workingDirectory = null;

    /**
     * @return string
     */
    public function getWorkingDirectory()
    {
        return $this->workingDirectory;
    }

    /**
     * @param string $workingDirectory
     */
    public function setWorkingDirectory($workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;
    }
}
