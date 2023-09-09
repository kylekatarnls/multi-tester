<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait StorageDirectory
{
    /**
     * @var string Directory where working directories are created.
     */
    protected $storageDirectory = null;

    public function getStorageDirectory(): string
    {
        return $this->storageDirectory;
    }

    public function setStorageDirectory(string $storageDirectory): void
    {
        $this->storageDirectory = $storageDirectory;
    }
}
