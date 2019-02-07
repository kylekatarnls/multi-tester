<?php

namespace MultiTester\Tests;

use MultiTester\File;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
    public function testParseJsonFile()
    {
        $this->assertSame([
            'name' => 'my-org/my-project',
        ], (new File(__DIR__ . '/project/composer.json'))->toArray());
    }
}
