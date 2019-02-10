<?php

namespace MultiTester\Tests;

use MultiTester\Config;
use MultiTester\Directory;
use MultiTester\MultiTester;
use MultiTester\Project;
use MultiTester\TestFailedException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProjectTest extends TestCase
{
    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testGetSettings()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();

        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);

        $project = new Project('foo/bar', $config, null);

        $this->assertSame([], $project->getSettings());

        $project = new Project('foo/bar', $config, [
            'foo' => 'bar',
        ]);

        $this->assertSame([
            'foo' => 'bar',
        ], $project->getSettings());

        $project = new Project('foo/bar', $config, 'travis');

        $this->assertSame([
            'script'  => 'travis',
            'install' => 'travis',
        ], $project->getSettings());
    }

    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testGetConfig()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();

        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);

        $project = new Project('foo/bar', $config, null);

        $this->assertSame($config, $project->getConfig());
    }

    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testGetPackage()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();

        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);

        $project = new Project('foo/bar', $config, null);

        $this->assertSame('foo/bar', $project->getPackage());
    }

    /**
     * @throws \MultiTester\MultiTesterException
     * @throws \ReflectionException
     */
    public function testGetScript()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();

        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);

        $project = new Project('foo/bar', $config, null);

        $getScript = new ReflectionMethod($project, 'getScript');
        $getScript->setAccessible(true);

        $this->assertSame('foobar', $getScript->invoke($project, 'foobar'));

        $this->assertSame(realpath('./composer.json'), $getScript->invoke($project, './composer.json'));
    }

    /**
     * @throws \MultiTester\MultiTesterException
     * @throws \ReflectionException
     */
    public function testGetTries()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();

        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);

        $project = new Project('foo/bar', $config, null);

        $getTries = new ReflectionMethod($project, 'getTries');
        $getTries->setAccessible(true);

        $this->assertSame(5, $getTries->invoke($project, [], 5));
        $this->assertSame(3, $getTries->invoke($project, [
            'retry' => 3,
        ], 5));
        $config->config = [
            'retry' => 7,
        ];
        $this->assertSame(3, $getTries->invoke($project, [
            'retry' => 3,
        ], 5));
        $this->assertSame(7, $getTries->invoke($project, [], 5));
    }

    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testTest()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $buffer = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        $tester->setProcStreams([
            ['file', 'php://stdin', 'r'],
            ['file', $buffer, 'a'],
            ['file', $buffer, 'a'],
        ]);

        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);

        $exit0 = 'php ' . escapeshellarg(__DIR__ . '/exit-0.php');
        $exit1 = 'php ' . escapeshellarg(__DIR__ . '/exit-1.php');

        $project = new Project('foo/bar', $config, [
            'clone'   => $exit0,
            'install' => $exit0,
            'script'  => $exit0,
        ]);
        @unlink($buffer);
        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        chdir($directory);
        $tester->setWorkingDirectory($directory);

        $this->assertTrue($project->test());

        (new Directory($directory))->remove();

        $project = new Project('foo/bar', $config, [
            'clone'   => $exit0,
            'install' => $exit0,
            'script'  => $exit1,
        ]);
        @unlink($buffer);
        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        chdir($directory);
        $tester->setWorkingDirectory($directory);
        $message = null;

        try {
            $project->test();
        } catch (TestFailedException $exception) {
            $message = $exception->getMessage();
        }
        @unlink($buffer);

        $this->assertSame('Test of foo/bar failed.', $message);

        (new Directory($directory))->remove();
    }
}
