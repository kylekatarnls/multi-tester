<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait MultiTesterFile
{
    /**
     * @var string Multi-tester default settings file.
     */
    protected $multiTesterFile = '.multi-tester.yml';

    public function getMultiTesterFile(): string
    {
        return $this->multiTesterFile;
    }

    public function setMultiTesterFile(string $multiTesterFile): void
    {
        $this->multiTesterFile = $multiTesterFile;
    }
}
