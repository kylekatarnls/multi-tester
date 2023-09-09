<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait Verbose
{
    /**
     * @var bool Verbose output.
     */
    protected $verbose = false;

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }
}
