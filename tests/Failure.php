<?php

namespace MultiTester\Tests;

use MultiTester\Directory;

class Failure extends Directory
{
    public function copy($destination, $exceptions = []): bool
    {
        if (substr($this->path, -3) === 'biz') {
            return false;
        }

        return parent::copy($destination, $exceptions);
    }

    public function clean(): bool
    {
        if (substr($this->path, -3) === 'biz') {
            return false;
        }

        return parent::clean();
    }
}
