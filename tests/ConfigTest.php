<?php

namespace MultiTester\Tests;

use MultiTester\Config;
use MultiTester\Directory;
use MultiTester\MultiTester;
use MultiTester\MultiTesterException;

class ConfigTest extends TestCase
{
    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testVerbose(): void
    {
        chdir(__DIR__ . '/project');

        $config = new Config(new MultiTester(), [__DIR__ . '/../bin/multi-tester', '-v']);

        $this->assertTrue($config->verbose);

        $config = new Config(new MultiTester(), [__DIR__ . '/../bin/multi-tester']);

        $this->assertFalse($config->verbose);
    }

    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testGetConfig(): void
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();

        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);

        $this->assertSame($tester, $config->getTester());
    }

    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testAddProjects(): void
    {
        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        chdir($directory);
        copy(__DIR__ . '/project/composer.json', 'composer.json');

        $tester = new MultiTester();
        $run = $tester->run([__DIR__ . '/../bin/multi-tester', '--add=foo/bar', '-v', '--add', 'hello/world:~3.4']);

        $this->assertTrue($run);
        $this->assertTrue($tester->isVerbose());
        $this->assertFileExists('.multi-tester.yml');
        $this->assertSame(implode("\n", [
            '',
            'foo/bar:',
            '  install: default',
            '  script: default',
            '',
            'hello/world:~3.4:',
            '  install: default',
            '  script: default',
            '',
        ]), file_get_contents('.multi-tester.yml'));

        (new Directory($directory))->remove();
    }

    /**
     * @throws MultiTesterException
     */
    public function testMissingConfigFile(): void
    {
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage("Multi-tester config file 'does-not-exists' not found.");

        chdir(__DIR__ . '/project');

        new Config(new MultiTester(), [__DIR__ . '/../bin/multi-tester', 'does-not-exists']);
    }

    /**
     * @throws MultiTesterException
     */
    public function testMissingComposerFile(): void
    {
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage("Set the 'directory' entry to a path containing a composer.json file.");

        chdir(__DIR__ . '/project');

        new Config(new MultiTester(), [__DIR__ . '/../bin/multi-tester', __DIR__ . '/dependency/.travis.yml']);
    }

    /**
     * @throws MultiTesterException
     */
    public function testMissingProjectName(): void
    {
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage("The composer.json file must contains a 'name' entry.");

        chdir(__DIR__ . '/project');

        new Config(new MultiTester(), [__DIR__ . '/../bin/multi-tester', __DIR__ . '/bad-name/.multi-tester.yml']);
    }
}
