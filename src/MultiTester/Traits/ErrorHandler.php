<?php

declare(strict_types=1);

namespace MultiTester\Traits;

use MultiTester\MultiTesterException;

trait ErrorHandler
{
    use WorkingDirectory;

    protected function error($message): void
    {
        $this->removeWorkingDirectory();

        throw $message instanceof MultiTesterException ?
            new MultiTesterException($message->getMessage(), 0, $message) :
            new MultiTesterException($message);
    }
}
