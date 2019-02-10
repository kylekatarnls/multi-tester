<?php

namespace MultiTester\Traits;

trait Verbose
{
    /**
     * @var bool Verbose output.
     */
    protected $verbose = false;

    /**
     * @return bool
     */
    public function isVerbose()
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }
}
