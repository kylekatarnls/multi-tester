<?php

namespace MultiTester\Tests;

use MultiTester\Directory;
use PHPUnit\Framework\TestCase;

class DirectoryTest extends TestCase
{
    public function testDirectoryTools()
    {
        $testDirectory = sys_get_temp_dir() . '/test-' . mt_rand(0, 999999);
        @mkdir($testDirectory, 0777, true);

        $this->assertTrue((new Directory(__DIR__ . '/dependency/vendor'))->copy("$testDirectory/dest", ['bin']));

        $this->assertFileExists("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileNotExists("$testDirectory/dest/bin/program");

        $this->assertTrue((new Directory("$testDirectory/dest"))->clean());

        $this->assertFileNotExists("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileExists("$testDirectory/dest");

        $this->assertTrue((new Directory("$testDirectory/dest"))->remove());

        $this->assertFileNotExists("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileNotExists("$testDirectory/dest");

        $this->assertTrue((new Directory("$testDirectory/dest/foo"))->create());

        $this->assertFileExists("$testDirectory/dest/foo");

        touch("$testDirectory/dest/foo/bar");

        $this->assertFalse((new Directory("$testDirectory/dest/my-org/my-project"))->copy("$testDirectory/dest/foo/bar"));
        $this->assertFalse((new Directory("$testDirectory/dest/my-org/my-project/readme.md"))->copy("$testDirectory/dest/foo/bar"));
        $this->assertFalse((new Directory("$testDirectory/dest/foo/does-not-exists"))->copy("$testDirectory/dest/foo/biz"));
        $this->assertFalse((new Directory("$testDirectory/dest/foo/bar"))->clean());

        $this->assertFileExists("$testDirectory/dest/foo/bar");

        $this->assertTrue((new Directory("$testDirectory/dest/foo"))->create());

        $this->assertFileNotExists("$testDirectory/dest/foo/bar");

        touch("$testDirectory/dest/foo/bar");

        $this->assertTrue(is_file("$testDirectory/dest/foo/bar"));

        $this->assertTrue((new Directory("$testDirectory/dest/foo/bar"))->create());

        $this->assertTrue(is_dir("$testDirectory/dest/foo/bar"));

        touch("$testDirectory/dest/foo/bar/biz");

        $this->assertTrue((new Directory("$testDirectory/dest"))->remove());

        $this->assertFileNotExists("$testDirectory/dest");

        mkdir("$testDirectory/dest/foo/bar/biz", 0777, true);
        touch("$testDirectory/dest/foo/bar/biz/bla");
        include_once __DIR__ . '/Failure.php';
        $failure = new Failure("$testDirectory/dest/foo/bar");
        $this->assertFalse($failure->copy("$testDirectory/dest/other"));
        $this->assertFalse($failure->clean());

        $this->assertTrue((new Directory($testDirectory))->remove());

        $this->assertFileNotExists($testDirectory);
    }
}
