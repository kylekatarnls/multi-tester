<?php

namespace MultiTester\Traits;

use MultiTester\Directory;
use MultiTester\MultiTesterException;

trait ErrorHandler
{
    use WorkingDirectory;

    protected function error($message): void
    {
        (new Directory($this->getWorkingDirectory()))->remove();

        throw $message instanceof MultiTesterException ?
            new MultiTesterException($message->getMessage(), 0, $message) :
            new MultiTesterException($message);
    }
}
