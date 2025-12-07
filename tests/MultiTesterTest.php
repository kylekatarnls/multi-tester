<?php

namespace MultiTester\Tests;

use MultiTester\Config;
use MultiTester\Directory;
use MultiTester\MultiTester;
use MultiTester\MultiTesterException;
use MultiTester\Project;
use MultiTester\SourceFinder;
use ReflectionMethod;

class MultiTesterTest extends TestCase
{
    public function testMultiTesterFile(): void
    {
        $tester = new MultiTester();

        $tester->setMultiTesterFile('foo.yml');

        $this->assertSame('foo.yml', $tester->getMultiTesterFile());
    }

    public function testTravisFile(): void
    {
        $tester = new MultiTester();

        $tester->setTravisFile('foo.yml');

        $this->assertSame('foo.yml', $tester->getTravisFile());
    }

    public function testWorkingDirectory(): void
    {
        $tester = new MultiTester();

        $tester->setWorkingDirectory('foo/');

        $this->assertSame('foo/', $tester->getWorkingDirectory());
    }

    public function testStorageDirectory(): void
    {
        $tester = new MultiTester();

        $this->assertSame(sys_get_temp_dir(), $tester->getStorageDirectory());

        $tester->setStorageDirectory('foobar/');

        $this->assertSame('foobar/', $tester->getStorageDirectory());
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetTravisSettings(): void
    {
        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'getTravisSettings');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $clearMethod = new ReflectionMethod($tester, 'clearTravisSettingsCache');
        if (PHP_VERSION_ID < 80100) {
            $clearMethod->setAccessible(true);
        }

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
    public function testGetConfig(): void
    {
        $tester = new MultiTester();

        $getConfig = new ReflectionMethod($tester, 'getConfig');
        if (PHP_VERSION_ID < 80100) {
            $getConfig->setAccessible(true);
        }

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

        $this->assertMatchesRegularExpression('/Multi-tester config file \'[^\']+\/project\/not-found\/\.multi-tester\.yml\' not found./', $message);
    }

    /**
     * @throws \ReflectionException
     */
    public function testExtractVersion(): void
    {
        $tester = new MultiTester();

        $extractVersion = new ReflectionMethod($tester, 'extractVersion');
        if (PHP_VERSION_ID < 80100) {
            $extractVersion->setAccessible(true);
        }

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
    public function testRemoveDirectories(): void
    {
        $tester = new MultiTester();

        $removeDirectories = new ReflectionMethod($tester, 'removeDirectories');
        if (PHP_VERSION_ID < 80100) {
            $removeDirectories->setAccessible(true);
        }

        $directories = [
            sys_get_temp_dir() . '/test-' . mt_rand(0, 999999),
            sys_get_temp_dir() . '/test-' . mt_rand(0, 999999),
        ];

        foreach ($directories as $directory) {
            mkdir($directory, 0777, true);
        }

        $removeDirectories->invoke($tester, $directories);

        $this->assertFileDoesNotExist($directories[0]);
        $this->assertFileDoesNotExist($directories[1]);
    }

    /**
     * @throws \ReflectionException
     */
    public function testPrepareWorkingDirectory(): void
    {
        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'getTravisSettings');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $prepareWorkingDirectory = new ReflectionMethod($tester, 'prepareWorkingDirectory');
        if (PHP_VERSION_ID < 80100) {
            $prepareWorkingDirectory->setAccessible(true);
        }

        $directories = [];

        $prepareWorkingDirectory->invokeArgs($tester, [&$directories]);

        $this->assertSame([$tester->getWorkingDirectory()], $directories);
        $cwd = @getcwd();
        $this->assertSame(realpath($tester->getWorkingDirectory()), $cwd);
    }

    /**
     * @throws \ReflectionException
     */
    public function testPrepareWorkingDirectoryCreationError(): void
    {
        $file = 'is-a-file';
        touch($file);
        $tester = new MultiTester($file);
        $method = new ReflectionMethod($tester, 'getTravisSettings');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $prepareWorkingDirectory = new ReflectionMethod($tester, 'prepareWorkingDirectory');
        if (PHP_VERSION_ID < 80100) {
            $prepareWorkingDirectory->setAccessible(true);
        }
        $message = null;

        try {
            $prepareWorkingDirectory->invoke($tester);
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }

        @unlink($file);

        $this->assertSame('Cannot create temporary directory, check you have write access to is-a-file', $message);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetComposerSettings(): void
    {
        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'getComposerSettings');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $packages = $method->invoke($tester, 'pug-php/pug');

        $this->assertIsArray($packages);
        $this->assertArrayHasKey('3.2.0', $packages);

        $package = $packages['3.2.0'];

        $this->assertIsArray($package);
        $this->assertArrayHasKey('name', $package);
        $this->assertSame('pug-php/pug', $package['name']);
        $this->assertSame('3.2.0', $package['version'] ?? $package['number']);
        $this->assertSame(
            strtotime('2018-06-10T17:27:29.000Z'),
            strtotime($package['time'] ?? $package['published_at'])
        );

        $this->assertNull($method->invoke($tester, 'pug-php/i-will-never-exist'));
    }

    /**
     * @throws \ReflectionException
     */
    public function testLibrariesIoFallback(): void
    {
        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'getComposerSettings');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $package = $method->invoke($tester, 'pug/pug', 'packagist1, libraries.io');

        $this->assertIsArray($package);

        $this->assertSame('pug/pug', $package['0.2.0']['name'] ?? null);
        $this->assertSame('Pug PHP 3 adapter from the shinny new Phug into pug-php', $package['0.2.0']['description'] ?? null);
        $this->assertSame('https://github.com/kylekatarnls/pug3', $package['0.2.0']['repository_url'] ?? null);
        $this->assertSame('2017-10-10T19:07:02.000Z', $package['0.2.0']['published_at'] ?? null);

        $cwd = @getcwd() ?: '.';
        chdir(__DIR__ . '/project');

        $tester = new Project('pug/pug', new Config($tester, [__DIR__ . '/../bin/multi-tester']), null);
        $method = new ReflectionMethod($tester, 'getRepositoryUrl');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $source = $method->invoke($tester, $package['0.2.0']);
        chdir($cwd);

        $this->assertSame([
            'type' => 'git',
            'url'  => 'https://github.com/kylekatarnls/pug3',
        ], $source);
    }

    /**
     * @throws \ReflectionException
     */
    public function testExpandList(): void
    {
        $tester = new SourceFinder('.');
        $method = new ReflectionMethod($tester, 'expandList');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $this->assertNull($method->invoke($tester, null));
        $this->assertSame([], $method->invoke($tester, []));
        $this->assertSame(['a', 'b'], $method->invoke($tester, ['a', 'b']));
        $this->assertSame([
            [
                'a' => 'b',
            ],
            [
                'a' => 'b',
                'b' => 'c',
            ],
            [
                'a' => 'b',
                'b' => 'c',
                'c' => 'd',
            ],
            [
                'a' => 'b',
                'b' => 'c',
                'c' => 'e',
            ],
            [
                'a' => 'b',
                'b' => 'c',
                'c' => 'e',
                'd' => 'f',
            ],
            [
                'a' => 'b',
                'c' => 'e',
                'd' => 'f',
            ],
        ], $method->invoke($tester, [
            ['a' => 'b'],
            ['b' => 'c'],
            ['c' => 'd'],
            ['c' => 'e'],
            ['d' => 'f'],
            ['b' => '__unset'],
        ]));
    }

    /**
     * @throws \ReflectionException
     */
    public function testInvalidPlatform(): void
    {
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage("Unknown platform 'foobar'");

        $tester = new MultiTester();
        $method = new ReflectionMethod($tester, 'getComposerSettings');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $method->invoke($tester, 'pug/pug', 'packagist1, foobar');
    }

    /**
     * @throws \ReflectionException
     */
    public function testOutput(): void
    {
        $tester = new MultiTester();
        $output = new ReflectionMethod($tester, 'output');
        if (PHP_VERSION_ID < 80100) {
            $output->setAccessible(true);
        }

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
    public function testInfo(): void
    {
        $tester = new MultiTester();
        $info = new ReflectionMethod($tester, 'info');
        if (PHP_VERSION_ID < 80100) {
            $info->setAccessible(true);
        }

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
    public function testExec(): void
    {
        $tester = new MultiTester();
        $exec = new ReflectionMethod($tester, 'exec');
        if (PHP_VERSION_ID < 80100) {
            $exec->setAccessible(true);
        }

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
    public function testError(): void
    {
        $tester = new MultiTester();
        $error = new ReflectionMethod($tester, 'error');
        if (PHP_VERSION_ID < 80100) {
            $error->setAccessible(true);
        }

        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        $tester->setWorkingDirectory($directory);
        $message = null;

        try {
            $error->invoke($tester, 'Fail');
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertFileDoesNotExist($directory);
        $this->assertSame('Fail', $message);

        mkdir($directory, 0777, true);
        $message = null;

        try {
            $error->invoke($tester, new MultiTesterException('Failure'));
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertFileDoesNotExist($directory);
        $this->assertSame('Failure', $message);
    }

    /**
     * @throws MultiTesterException
     * @throws \ReflectionException
     */
    public function testTestProject(): void
    {
        $tester = new MultiTester();
        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        chdir($directory);
        copy(__DIR__ . '/project/.multi-tester.yml', '.multi-tester.yml');
        copy(__DIR__ . '/project/composer.json', 'composer.json');
        $tester->setWorkingDirectory($directory);

        $testProject = new ReflectionMethod($tester, 'testProject');
        if (PHP_VERSION_ID < 80100) {
            $testProject->setAccessible(true);
        }
        $buffer = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        $tester->setProcStreams([
            ['file', 'php://stdin', 'r'],
            ['file', $buffer, 'a'],
            ['file', $buffer, 'a'],
        ]);
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester', '--colors']);
        $exit0 = 'php ' . escapeshellarg(realpath(__DIR__ . '/exit-0.php'));
        $exit1 = 'php ' . escapeshellarg(realpath(__DIR__ . '/exit-1.php'));
        $state = true;
        $package = 'pug-php/pug';
        $settings = [
            'clone'    => $exit0,
            'install'  => $exit0,
            'autoload' => $exit0,
            'script'   => $exit0,
        ];

        $testProject->invokeArgs($tester, [$package, $config, $settings, &$state]);
        $output = file_get_contents($buffer);
        @unlink($buffer);

        $this->assertTrue($state);
        $this->assertSame(
            str_repeat("> $exit0\nSuccess command\n\n", 4) .
            "\033[30;42m  Success for: pug-php/pug  \033[0m\n",
            $output
        );

        $settings = [
            'clone'    => $exit0,
            'install'  => $exit0,
            'autoload' => $exit0,
            'script'   => $exit1,
        ];

        $testProject->invokeArgs($tester, [$package, $config, $settings, &$state]);
        $output = file_get_contents($buffer);
        @unlink($buffer);

        $this->assertFalse($state);
        $this->assertSame(
            str_repeat("> $exit0\nSuccess command\n\n", 3) .
            "> $exit1\nFailure command\n\n" .
            "\033[30;41m  Failure for: pug-php/pug  \033[0m\n",
            $output
        );

        (new Directory($directory))->remove();

        $settings = [
            'clone'    => $exit1,
            'install'  => $exit0,
            'autoload' => $exit0,
            'script'   => $exit0,
        ];
        $message = null;

        try {
            $testProject->invokeArgs($tester, [$package, $config, $settings, &$state]);
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }

        $output = strtr(file_get_contents($buffer), [
            "\nsh: 0: getcwd() failed: No such file or directory" => '',
        ]);
        @unlink($buffer);
        $output = trim(str_replace('Failure command', '', $output));

        $this->assertSame('Cloning pug-php/pug failed.', $message);
        $this->assertSame(
            "> $exit1\n\033[30;41m  Error for: pug-php/pug  \033[0m",
            preg_replace('/\n{2,}/', "\n", $output)
        );

        (new Directory($directory))->remove();
    }

    /**
     * @throws MultiTesterException
     */
    public function testRun(): void
    {
        $exit0 = 'php ' . escapeshellarg(realpath(__DIR__ . '/exit-0.php'));
        $tester = new MultiTester();
        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        chdir($directory);
        file_put_contents('.multi-tester.yml', implode("\n", [
            'config:',
            '  color_support: false',
            'some-project:',
            "  clone: $exit0",
            "  install: $exit0",
            "  autoload: $exit0",
            "  script: $exit0",
        ]));
        copy(__DIR__ . '/project/composer.json', 'composer.json');
        $tester->setWorkingDirectory($directory);
        $buffer = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        $tester->setProcStreams([
            ['file', 'php://stdin', 'r'],
            ['file', $buffer, 'a'],
            ['file', $buffer, 'a'],
        ]);

        $success = $tester->run([null, '--no-colors']);

        $output = file_get_contents($buffer);
        @unlink($buffer);
        (new Directory($directory))->remove();

        $this->assertTrue($success);
        $this->assertSame(
            str_repeat("> $exit0\nSuccess command\n\n", 4) .
            "  Success for: some-project  \n" .
            "\n\nsome-project    Success\n\n" .
            "1 / 1     No project broken by current changes.\n",
            $output
        );
    }
}
