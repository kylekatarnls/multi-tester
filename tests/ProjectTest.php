<?php

namespace MultiTester\Tests;

use MultiTester\Config;
use MultiTester\Directory;
use MultiTester\MultiTester;
use MultiTester\MultiTesterException;
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

        $this->assertSame(['foobar'], $getScript->invoke($project, 'foobar'));

        $this->assertSame([realpath('./composer.json')], $getScript->invoke($project, './composer.json'));
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
    public function testClone()
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
            'clone'   => $exit1,
            'install' => $exit0,
            'script'  => $exit0,
        ]);
        @unlink($buffer);
        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        chdir($directory);
        $tester->setWorkingDirectory($directory);
        $message = null;

        try {
            $project->test();
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }
        @unlink($buffer);

        $this->assertSame('Cloning foo/bar failed.', $message);

        (new Directory($directory))->remove();
    }

    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testInstall()
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
            'install' => $exit1,
            'script'  => $exit0,
        ]);
        @unlink($buffer);
        $directory = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        mkdir($directory, 0777, true);
        chdir($directory);
        $tester->setWorkingDirectory($directory);
        $message = null;

        try {
            $project->test();
        } catch (MultiTesterException $exception) {
            $message = $exception->getMessage();
        }
        @unlink($buffer);

        $this->assertSame('Installing foo/bar failed.', $message);

        (new Directory($directory))->remove();
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

    /**
     * @throws \ReflectionException
     * @throws MultiTesterException
     */
    public function testFilterVersion()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('foo/bar', $config, []);
        $filterVersion = new ReflectionMethod($project, 'filterVersion');
        $filterVersion->setAccessible(true);

        $this->assertSame(
            '2.10.9',
            $filterVersion->invoke($project, null, ['1.2.3', '2.10.9', '2.0.4'])
        );

        $this->assertSame(
            '2.10.9',
            $filterVersion->invoke($project, '^2.0', ['1.2.3', '2.10.9', '2.0.4'])
        );

        $this->assertSame(
            '2.10.9',
            $filterVersion->invoke($project, '^3.0', ['1.2.3', '2.10.9', '2.0.4'])
        );

        $this->assertSame(
            '2.0.4',
            $filterVersion->invoke($project, '~2.0.0', ['1.2.3', '2.10.9', '2.0.4'])
        );
    }

    /**
     * @throws \ReflectionException
     * @throws MultiTesterException
     */
    public function testSeedSourceSetting()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('pug-php/pug', $config, []);
        $seedSourceSetting = new ReflectionMethod($project, 'seedSourceSetting');
        $seedSourceSetting->setAccessible(true);

        $settings = [
            'source' => 'foobar',
        ];
        $seedSourceSetting->invokeArgs($project, [&$settings]);

        $this->assertSame('foobar', $settings['source']);

        $settings = [];
        $seedSourceSetting->invokeArgs($project, [&$settings]);

        $this->assertTrue(is_array($settings['source']));
        $this->assertSame('git', $settings['source']['type']);
        $this->assertSame('https://github.com/pug-php/pug.git', $settings['source']['url']);

        $project = new Project('pug-php/does-not-exist', $config, []);
        $seedSourceSetting = new ReflectionMethod($project, 'seedSourceSetting');
        $seedSourceSetting->setAccessible(true);

        $settings = [
            'source' => 'foobar',
        ];
        $seedSourceSetting->invokeArgs($project, [&$settings]);

        $this->assertSame('foobar', $settings['source']);

        $settings = [];
        $seedSourceSetting->invokeArgs($project, [&$settings]);

        $this->assertNull($settings['source']);
    }

    public function testCheckSourceSettingGit()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('pug-php/pug', $config, []);
        $checkSourceSetting = new ReflectionMethod($project, 'checkSourceSetting');
        $checkSourceSetting->setAccessible(true);

        $this->assertNull($checkSourceSetting->invoke($project, [
            'source' => [
                'type' => 'git',
                'url'  => 'foo',
            ],
        ]));
    }

    /**
     * @expectedException        \MultiTester\MultiTesterException
     * @expectedExceptionMessage Source not found for pug-php/pug, you must provide it manually via a 'source' entry.
     *
     * @throws \ReflectionException
     */
    public function testCheckSourceSettingNotFound()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('pug-php/pug', $config, []);
        $checkSourceSetting = new ReflectionMethod($project, 'checkSourceSetting');
        $checkSourceSetting->setAccessible(true);

        $checkSourceSetting->invoke($project, []);
    }

    /**
     * @expectedException        \MultiTester\MultiTesterException
     * @expectedExceptionMessage Git source supported only for now, you should provide a manual 'clone' command instead.
     *
     * @throws \ReflectionException
     */
    public function testCheckSourceSettingNotSupportedType()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('pug-php/pug', $config, []);
        $checkSourceSetting = new ReflectionMethod($project, 'checkSourceSetting');
        $checkSourceSetting->setAccessible(true);

        $checkSourceSetting->invoke($project, [
            'source' => [
                'type' => 'not-supported',
                'url'  => 'foo',
            ],
        ]);
    }

    /**
     * @expectedException        \MultiTester\MultiTesterException
     * @expectedExceptionMessage Source malformed, it should contains at least 'type' and 'url' entries.
     *
     * @throws \ReflectionException
     */
    public function testCheckSourceSettingMalFormed()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('pug-php/pug', $config, []);
        $checkSourceSetting = new ReflectionMethod($project, 'checkSourceSetting');
        $checkSourceSetting->setAccessible(true);

        $checkSourceSetting->invoke($project, [
            'source' => [
                'foo' => 'bar',
            ],
        ]);
    }

    /**
     * @throws \ReflectionException
     * @throws MultiTesterException
     */
    public function testSeedCloneSetting()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('pug-php/pug', $config, []);
        $seedCloneSetting = new ReflectionMethod($project, 'seedCloneSetting');
        $seedCloneSetting->setAccessible(true);

        $settings = [
            'clone' => 'foobar',
        ];
        $seedCloneSetting->invokeArgs($project, [&$settings]);

        $this->assertSame(['foobar'], $settings['clone']);

        $settings = [];
        $seedCloneSetting->invokeArgs($project, [&$settings]);

        $clone = $settings['clone'];

        $this->assertTrue(is_array($clone));
        $this->assertCount(2, $clone);
        $this->assertSame('git clone https://github.com/pug-php/pug.git .', $clone[0]);
        $this->assertRegExp('/^git checkout [0-9a-f]+$/', $clone[1]);
    }

    /**
     * @throws \ReflectionException
     * @throws MultiTesterException
     */
    public function testInstallCloneSetting()
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
        $project = new Project('pug-php/pug', $config, []);
        $seedInstallSetting = new ReflectionMethod($project, 'seedInstallSetting');
        $seedInstallSetting->setAccessible(true);

        $settings = [
            'install' => 'foobar',
        ];
        $seedInstallSetting->invokeArgs($project, [&$settings]);

        $this->assertSame(['foobar'], $settings['install']);

        $settings = [];
        $seedInstallSetting->invokeArgs($project, [&$settings]);

        $this->assertSame([
            'composer install --no-interaction',
        ], $settings['install']);

        $settings = [
            'install' => 'travis',
        ];
        $seedInstallSetting->invokeArgs($project, [&$settings]);

        $this->assertSame([
            'travis',
        ], $settings['install']);

        $project = new Project('nesbot/carbon', $config, []);
        $seedInstallSetting = new ReflectionMethod($project, 'seedInstallSetting');
        $seedInstallSetting->setAccessible(true);

        $settings = [
            'install' => 'travis',
        ];
        file_put_contents('.travis.yml', implode("\n", [
            'install:',
            '  - hello',
            '  - world',
        ]));
        $seedInstallSetting->invokeArgs($project, [&$settings]);
        unlink('.travis.yml');

        $this->assertSame([
            'hello',
            'world',
        ], $settings['install']);

        @unlink($buffer);
    }

    /**
     * @throws \ReflectionException
     * @throws MultiTesterException
     */
    public function testSeedScriptSetting()
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
        $project = new Project('pug-php/pug', $config, []);
        $seedScriptSetting = new ReflectionMethod($project, 'seedScriptSetting');
        $seedScriptSetting->setAccessible(true);

        $settings = [
            'script' => 'foobar',
        ];
        $seedScriptSetting->invokeArgs($project, [&$settings]);

        $this->assertSame(['foobar'], $settings['script']);

        $settings = [];
        $seedScriptSetting->invokeArgs($project, [&$settings]);

        $this->assertSame([
            'vendor/bin/phpunit --no-coverage',
        ], $settings['script']);

        $settings = [
            'script' => 'travis',
        ];
        $seedScriptSetting->invokeArgs($project, [&$settings]);

        $this->assertSame([
            'travis',
        ], $settings['script']);

        $project = new Project('nesbot/carbon', $config, []);
        $seedScriptSetting = new ReflectionMethod($project, 'seedScriptSetting');
        $seedScriptSetting->setAccessible(true);

        $settings = [
            'script' => 'travis',
        ];
        file_put_contents('.travis.yml', implode("\n", [
            'script:',
            '  - hello',
            '  - world',
        ]));
        $seedScriptSetting->invokeArgs($project, [&$settings]);
        unlink('.travis.yml');

        $this->assertSame([
            'hello',
            'world',
        ], $settings['script']);

        @unlink($buffer);
    }

    /**
     * @throws \ReflectionException
     * @throws MultiTesterException
     */
    public function testRemoveReplacedPackages()
    {
        chdir(__DIR__ . '/project2');

        $tester = new MultiTester();
        $buffer = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        $tester->setProcStreams([
            ['file', 'php://stdin', 'r'],
            ['file', $buffer, 'a'],
            ['file', $buffer, 'a'],
        ]);
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('pug-php/pug', $config, []);

        chdir(sys_get_temp_dir());
        $dir = 'multi-tester-' . mt_rand(0, 999999);
        $projectDir = 'vendor/my-org/other-project';
        mkdir("$dir/$projectDir", 0777, true);
        chdir($dir);

        $this->assertDirectoryExists($projectDir);

        $removeReplacedPackages = new ReflectionMethod($project, 'removeReplacedPackages');
        $removeReplacedPackages->setAccessible(true);
        $removeReplacedPackages->invoke($project);

        $this->assertDirectoryNotExists($projectDir);

        chdir(sys_get_temp_dir());
        (new Directory($dir))->remove();

        chdir(__DIR__ . '/project');

        $tester = new MultiTester();
        $buffer = sys_get_temp_dir() . '/test-' . mt_rand(0, 99999);
        $tester->setProcStreams([
            ['file', 'php://stdin', 'r'],
            ['file', $buffer, 'a'],
            ['file', $buffer, 'a'],
        ]);
        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);
        $project = new Project('pug-php/pug', $config, []);

        $removeReplacedPackages = new ReflectionMethod($project, 'removeReplacedPackages');
        $removeReplacedPackages->setAccessible(true);

        $this->assertNull($removeReplacedPackages->invoke($project));
    }
}
