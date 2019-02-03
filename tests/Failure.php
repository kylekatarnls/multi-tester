<?php

namespace MultiTester\Tests;

use MultiTester\MultiTester;

class Failure extends MultiTester
{
    protected function copyDirectory($source, $destination, $exceptions = [])
    {
        if (substr($source, -3) === 'biz') {
            return false;
        }

        return parent::copyDirectory($source, $destination, $exceptions);
    }

    protected function emptyDirectory($dir)
    {
        if (substr($dir, -3) === 'biz') {
            return false;
        }

        return parent::emptyDirectory($dir);
    }

    public function failEmpty($dir)
    {
        return $this->emptyDirectory($dir);
    }

    public function failCopy($source, $destination, $exceptions = [])
    {
        return $this->copyDirectory($source, $destination, $exceptions);
    }
}
