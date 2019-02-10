<?php

namespace MultiTester\Traits;

trait StorageDirectory
{
    /**
     * @var string Directory where working directories are created.
     */
    protected $storageDirectory = null;

    /**
     * @return string
     */
    public function getStorageDirectory()
    {
        return $this->storageDirectory;
    }

    /**
     * @param string $storageDirectory
     */
    public function setStorageDirectory($storageDirectory)
    {
        $this->storageDirectory = $storageDirectory;
    }
}
