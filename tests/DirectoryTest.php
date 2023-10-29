<?php

namespace MultiTester\Tests;

use MultiTester\Directory;
use ReflectionMethod;

class DirectoryTest extends TestCase
{
    public function testDirectoryTools(): void
    {
        $testDirectory = sys_get_temp_dir() . '/test-' . mt_rand(0, 999999);
        @mkdir($testDirectory, 0777, true);

        $this->assertTrue((new Directory(__DIR__ . '/dependency/vendor'))->copy("$testDirectory/dest", ['bin']));

        $this->assertFileExists("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileDoesNotExist("$testDirectory/dest/bin/program");

        $this->assertTrue((new Directory("$testDirectory/dest"))->clean());

        $this->assertFileDoesNotExist("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileExists("$testDirectory/dest");

        $this->assertTrue((new Directory("$testDirectory/dest"))->remove());

        $this->assertFileDoesNotExist("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileDoesNotExist("$testDirectory/dest");

        $this->assertTrue((new Directory("$testDirectory/dest/foo"))->create());

        $this->assertFileExists("$testDirectory/dest/foo");

        touch("$testDirectory/dest/foo/bar");

        $this->assertFalse((new Directory("$testDirectory/dest/my-org/my-project"))->copy("$testDirectory/dest/foo/bar"));
        $this->assertFalse((new Directory("$testDirectory/dest/my-org/my-project/readme.md"))->copy("$testDirectory/dest/foo/bar"));
        $this->assertFalse((new Directory("$testDirectory/dest/foo/does-not-exists"))->copy("$testDirectory/dest/foo/biz"));
        $this->assertFalse((new Directory("$testDirectory/dest/foo/bar"))->clean());

        $this->assertFileExists("$testDirectory/dest/foo/bar");

        $this->assertTrue((new Directory("$testDirectory/dest/foo"))->create());

        $this->assertFileDoesNotExist("$testDirectory/dest/foo/bar");

        touch("$testDirectory/dest/foo/bar");

        $this->assertFileExists("$testDirectory/dest/foo/bar");

        $this->assertTrue((new Directory("$testDirectory/dest/foo/bar"))->create());

        $this->assertDirectoryExists("$testDirectory/dest/foo/bar");

        touch("$testDirectory/dest/foo/bar/biz");

        $this->assertTrue((new Directory("$testDirectory/dest"))->remove());

        $this->assertFileDoesNotExist("$testDirectory/dest");

        mkdir("$testDirectory/dest/foo/bar/biz", 0777, true);
        touch("$testDirectory/dest/foo/bar/biz/bla");
        include_once __DIR__ . '/Failure.php';
        $failure = new Failure("$testDirectory/dest/foo/bar");
        $this->assertFalse($failure->copy("$testDirectory/dest/other"));
        $this->assertFalse($failure->clean());

        $this->assertTrue((new Directory($testDirectory))->remove());

        $this->assertFileDoesNotExist($testDirectory);
    }

    public function testCopyItem(): void
    {
        $testDirectory = sys_get_temp_dir() . '/test-' . mt_rand(0, 999999);
        @mkdir($testDirectory, 0777, true);

        $directory = new Directory($testDirectory);
        $copyItem = new ReflectionMethod($directory, 'copyItem');
        $copyItem->setAccessible(true);

        $success = true;

        $copyItem->invokeArgs($directory, [$testDirectory, $testDirectory, 'foobar', &$success]);

        $this->assertFalse($success);

        $this->assertTrue((new Directory($testDirectory))->remove());

        $this->assertFileDoesNotExist($testDirectory);
    }

    public function testCleanItem(): void
    {
        $testDirectory = sys_get_temp_dir() . '/test-' . mt_rand(0, 999999);
        @mkdir($testDirectory, 0777, true);

        $directory = new Directory($testDirectory);
        $cleanItem = new ReflectionMethod($directory, 'cleanItem');
        $cleanItem->setAccessible(true);

        $success = true;

        $cleanItem->invokeArgs($directory, ['foobar', &$success]);

        $this->assertFalse($success);

        $this->assertTrue((new Directory($testDirectory))->remove());

        $this->assertFileDoesNotExist($testDirectory);
    }
}
