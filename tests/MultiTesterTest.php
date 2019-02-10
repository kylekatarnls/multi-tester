<?php

namespace MultiTester\Tests;

use MultiTester\Config;
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

    public function testStorageDirectory()
    {
        $tester = new MultiTester();

        $this->assertSame(sys_get_temp_dir(), $tester->getStorageDirectory());

        $tester->setStorageDirectory('foobar/');

        $this->assertSame('foobar/', $tester->getStorageDirectory());
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
        ], $method->invoke($tester)->toArray());

        $tester->setTravisFile(__DIR__ . '/dependency/.travis-other.yml');

        $this->assertSame([
            'install' => 'echo "Install"',
            'script'  => [
                'echo "Bla"',
                'echo \'<?php echo 3 + 4; ?>\' | php',
            ],
        ], $method->invoke($tester)->toArray());

        $clearMethod->invoke($tester);

        $this->assertSame([
            'install' => 'echo "Install"',
            'script'  => 'echo "Else"',
        ], $method->invoke($tester)->toArray());
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetConfig()
    {
        $tester = new MultiTester();

        $getConfig = new ReflectionMethod($tester, 'getConfig');
        $getConfig->setAccessible(true);

        /** @var Config $config */
        $config = $getConfig->invoke($tester, ['foo', __DIR__ . '/project/.multi-tester.yml']);

        $this->assertInstanceOf('MultiTester\Config', $config);
        $this->assertSame('my-org/my-project', $config->packageName);

        $message = null;

        try {
            $getConfig->invoke($tester, ['foo', __DIR__ . '/project/not-found/.multi-tester.yml']);
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertRegExp('/Multi-tester config file \'[^\']+\/project\/not-found\/\.multi-tester\.yml\' not found./', $message);
    }

    /**
     * @throws \ReflectionException
     */
    public function testExtractVersion()
    {
        $tester = new MultiTester();

        $extractVersion = new ReflectionMethod($tester, 'extractVersion');
        $extractVersion->setAccessible(true);

        $package = 'foo/bar';
        $settings = [];

        $extractVersion->invokeArgs($tester, [&$package, &$settings]);

        $this->assertSame('foo/bar', $package);
        $this->assertSame([], $settings);

        $package = 'foo/bar:^5.4';
        $settings = [];

        $extractVersion->invokeArgs($tester, [&$package, &$settings]);

        $this->assertSame('foo/bar', $package);
        $this->assertSame(['version' => '^5.4'], $settings);

        $package = 'foo/bar:old';
        $settings = ['version' => '1.0'];

        $extractVersion->invokeArgs($tester, [&$package, &$settings]);

        $this->assertSame('foo/bar', $package);
        $this->assertSame(['version' => '1.0'], $settings);
    }

    /**
     * @throws \ReflectionException
     */
    public function testRemoveDirectories()
    {
        $tester = new MultiTester();

        $removeDirectories = new ReflectionMethod($tester, 'removeDirectories');
        $removeDirectories->setAccessible(true);

        $directories = [
            sys_get_temp_dir() . '/test-' . mt_rand(0, 999999),
            sys_get_temp_dir() . '/test-' . mt_rand(0, 999999),
        ];

        foreach ($directories as $directory) {
            mkdir($directory, 0777, true);
        }

        $removeDirectories->invoke($tester, $directories);

        $this->assertFileNotExists($directories[0]);
        $this->assertFileNotExists($directories[1]);
    }

    /**
     * @throws \ReflectionException
     */
    public function testPrepareWorkingDirectory()
    {
        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'getTravisSettings');
        $method->setAccessible(true);

        $prepareWorkingDirectory = new ReflectionMethod($tester, 'prepareWorkingDirectory');
        $prepareWorkingDirectory->setAccessible(true);

        $directories = [];

        $prepareWorkingDirectory->invokeArgs($tester, [&$directories]);

        $this->assertSame([$tester->getWorkingDirectory()], $directories);
        $this->assertSame(realpath($tester->getWorkingDirectory()), getcwd());
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
    public function testInfo()
    {
        $tester = new MultiTester();
        $info = new ReflectionMethod($tester, 'info');
        $info->setAccessible(true);

        $this->assertFalse($tester->isVerbose());

        $tester->setProcStreams([]);
        ob_start();
        $info->invoke($tester, 'Hello world!');
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertSame('', $result);

        $tester->setVerbose(true);

        $this->assertTrue($tester->isVerbose());

        $tester->setProcStreams([]);
        ob_start();
        $info->invoke($tester, 'Hello world!');
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

        mkdir($directory, 0777, true);
        $message = null;

        try {
            $error->invoke($tester, new MultiTesterException('Failure'));
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertFileNotExists($directory);
        $this->assertSame('Failure', $message);
    }
}
