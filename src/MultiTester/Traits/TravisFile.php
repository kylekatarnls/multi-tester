<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait TravisFile
{
    /**
     * @var string Travis CI settings file.
     */
    protected $travisFile = '.travis.yml';

    public function getTravisFile(): string
    {
        return $this->travisFile;
    }

    public function setTravisFile(string $travisFile): void
    {
        $this->travisFile = $travisFile;
    }
}
