<?php

namespace MultiTester\Tests;

use MultiTester\MultiTester;
use MultiTester\MultiTesterException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MultiTesterTest extends TestCase
{
    public function testMultiTesterFile()
    {
        $tester = new MultiTester();

        $tester->setMultiTesterFile('foo.yml');

        $this->assertSame('foo.yml', $tester->getMultiTesterFile());
    }

    public function testTravisFile()
    {
        $tester = new MultiTester();

        $tester->setTravisFile('foo.yml');

        $this->assertSame('foo.yml', $tester->getTravisFile());
    }

    public function testWorkingDirectory()
    {
        $tester = new MultiTester();

        $tester->setWorkingDirectory('foo/');

        $this->assertSame('foo/', $tester->getWorkingDirectory());
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetTravisSettings()
    {
        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'getTravisSettings');
        $method->setAccessible(true);

        $clearMethod = new ReflectionMethod($tester, 'clearTravisSettingsCache');
        $clearMethod->setAccessible(true);

        $tester->setTravisFile(__DIR__ . '/dependency/.i-do-not-exist.yml');

        $this->assertSame([], $method->invoke($tester));

        $clearMethod->invoke($tester);
        $tester->setTravisFile(__DIR__ . '/dependency/.travis.yml');

        $this->assertSame([
            'install' => 'echo "Install"',
            'script'  => [
                'echo "Bla"',
                'echo \'<?php echo 3 + 4; ?>\' | php',
            ],
        ], $method->invoke($tester));

        $tester->setTravisFile(__DIR__ . '/dependency/.travis-other.yml');

        $this->assertSame([
            'install' => 'echo "Install"',
            'script'  => [
                'echo "Bla"',
                'echo \'<?php echo 3 + 4; ?>\' | php',
            ],
        ], $method->invoke($tester));

        $clearMethod->invoke($tester);

        $this->assertSame([
            'install' => 'echo "Install"',
            'script'  => 'echo "Else"',
        ], $method->invoke($tester));
    }

    /**
     * @throws \ReflectionException
     */
    public function testParseJsonFile()
    {
        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'parseJsonFile');
        $method->setAccessible(true);

        $this->assertSame([
            'name' => 'my-org/my-project',
        ], $method->invoke($tester, __DIR__ . '/project/composer.json'));
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetComposerSettings()
    {
        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'getComposerSettings');
        $method->setAccessible(true);

        $packages = $method->invoke($tester, 'pug-php/pug');

        $this->assertTrue(is_array($packages));
        $this->assertArrayHasKey('3.2.0', $packages);

        $package = $packages['3.2.0'];

        $this->assertTrue(is_array($package));
        $this->assertArrayHasKey('name', $package);
        $this->assertArrayHasKey('version', $package);
        $this->assertSame('pug-php/pug', $package['name']);
        $this->assertSame('3.2.0', $package['version']);

        $this->assertNull($method->invoke($tester, 'pug-php/i-will-never-exist'));
    }

    /**
     * @throws \ReflectionException
     */
    public function testDirectoryTools()
    {
        $tester = new MultiTester();
        $copy = new ReflectionMethod($tester, 'copyDirectory');
        $copy->setAccessible(true);
        $empty = new ReflectionMethod($tester, 'emptyDirectory');
        $empty->setAccessible(true);
        $remove = new ReflectionMethod($tester, 'removeDirectory');
        $remove->setAccessible(true);
        $create = new ReflectionMethod($tester, 'createEmptyDirectory');
        $create->setAccessible(true);

        $testDirectory = sys_get_temp_dir() . '/test-' . mt_rand(0, 999999);
        @mkdir($testDirectory, 0777, true);

        $this->assertTrue($copy->invoke($tester, __DIR__ . '/dependency/vendor', "$testDirectory/dest", ['bin']));

        $this->assertFileExists("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileNotExists("$testDirectory/dest/bin/program");

        $this->assertTrue($empty->invoke($tester, "$testDirectory/dest"));

        $this->assertFileNotExists("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileExists("$testDirectory/dest");

        $this->assertTrue($remove->invoke($tester, "$testDirectory/dest"));

        $this->assertFileNotExists("$testDirectory/dest/my-org/my-project/readme.md");
        $this->assertFileNotExists("$testDirectory/dest");

        $this->assertTrue($create->invoke($tester, "$testDirectory/dest/foo"));

        $this->assertFileExists("$testDirectory/dest/foo");

        touch("$testDirectory/dest/foo/bar");

        $this->assertFalse($empty->invoke($tester, "$testDirectory/dest/foo/bar"));

        $this->assertFileExists("$testDirectory/dest/foo/bar");

        $this->assertTrue($create->invoke($tester, "$testDirectory/dest/foo"));

        $this->assertFileNotExists("$testDirectory/dest/foo/bar");

        touch("$testDirectory/dest/foo/bar");

        $this->assertTrue(is_file("$testDirectory/dest/foo/bar"));

        $this->assertTrue($create->invoke($tester, "$testDirectory/dest/foo/bar"));

        $this->assertTrue(is_dir("$testDirectory/dest/foo/bar"));

        touch("$testDirectory/dest/foo/bar/biz");

        $this->assertTrue($remove->invoke($tester, "$testDirectory/dest"));

        $this->assertFileNotExists("$testDirectory/dest");

        mkdir("$testDirectory/dest/foo/bar/biz", 0777, true);
        touch("$testDirectory/dest/foo/bar/biz/bla");
        include_once __DIR__ . '/Failure.php';
        $failure = new Failure();
        $this->assertFalse($failure->failCopy("$testDirectory/dest/foo/bar", "$testDirectory/dest/other"));
        $this->assertFalse($failure->failEmpty("$testDirectory/dest/foo/bar"));

        $this->assertTrue($remove->invoke($tester, $testDirectory));

        $this->assertFileNotExists($testDirectory);
    }

    /**
     * @throws \ReflectionException
     */
    public function testOutput()
    {
        $tester = new MultiTester();
        $output = new ReflectionMethod($tester, 'output');
        $output->setAccessible(true);

        $tester->setProcStreams([]);
        ob_start();
        $output->invoke($tester, 'Hello world!');
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertSame('Hello world!', $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testExec()
    {
        $tester = new MultiTester();
        $exec = new ReflectionMethod($tester, 'exec');
        $exec->setAccessible(true);

        $streams = $tester->getProcStreams();
        $buffer = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        $tester->setProcStreams([
            $streams[0],
            ['file', $buffer, 'a'],
            $streams[2],
        ]);
        $exec->invoke($tester, 'echo Bla');
        $output = file_get_contents($buffer);
        unlink($buffer);

        $this->assertSame("> echo Bla\nBla", str_replace("\r", '', trim($output)));

        $exec->invoke($tester, ['echo Bla', 'echo Foo']);
        $output = file_get_contents($buffer);
        unlink($buffer);

        $this->assertSame("> echo Bla\nBla\n\n> echo Foo\nFoo", str_replace("\r", '', trim($output)));

        $this->assertTrue($exec->invoke($tester, 'echo "<?php exit(0); ?>" | php'));
        $this->assertFalse($exec->invoke($tester, 'echo "<?php exit(1); ?>" | php'));

        @unlink($buffer);
    }

    /**
     * @throws \ReflectionException
     */
    public function testError()
    {
        $tester = new MultiTester();
        $error = new ReflectionMethod($tester, 'error');
        $error->setAccessible(true);

        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        $tester->setWorkingDirectory($directory);
        $message = null;

        try {
            $error->invoke($tester, 'Fail');
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertFileNotExists($directory);
        $this->assertSame('Fail', $message);
    }
}
