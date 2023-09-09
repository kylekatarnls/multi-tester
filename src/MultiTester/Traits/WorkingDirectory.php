<?php

declare(strict_types=1);

namespace MultiTester\Traits;

use MultiTester\Directory;

trait WorkingDirectory
{
    /**
     * @var string|null Temporary working directory.
     */
    protected $workingDirectory = null;

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    public function setWorkingDirectory(?string $workingDirectory): void
    {
        $this->workingDirectory = $workingDirectory;
    }

    protected function removeWorkingDirectory(?string $executor = null): void
    {
        $workingDirectory = $this->getWorkingDirectory();

        if ($workingDirectory !== null) {
            (new Directory($this->getWorkingDirectory(), $executor))->remove();
        }
    }
}
