<?php

namespace MultiTester\Tests;

use MultiTester\Config;
use MultiTester\MultiTester;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @throws \MultiTester\MultiTesterException
     */
    public function testVerbose()
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
    public function testGetConfig()
    {
        chdir(__DIR__ . '/project');

        $tester = new MultiTester();

        $config = new Config($tester, [__DIR__ . '/../bin/multi-tester']);

        $this->assertSame($tester, $config->getTester());
    }

    /**
     * @expectedException        \MultiTester\MultiTesterException
     * @expectedExceptionMessage Multi-tester config file 'does-not-exists' not found.
     */
    public function testMissingConfigFile()
    {
        chdir(__DIR__ . '/project');

        new Config(new MultiTester(), [__DIR__ . '/../bin/multi-tester', 'does-not-exists']);
    }

    /**
     * @expectedException        \MultiTester\MultiTesterException
     * @expectedExceptionMessage Set the 'directory' entry to a path containing a composer.json file.
     */
    public function testMissingComposerFile()
    {
        chdir(__DIR__ . '/project');

        new Config(new MultiTester(), [__DIR__ . '/../bin/multi-tester', __DIR__ . '/dependency/.travis.yml']);
    }

    /**
     * @expectedException        \MultiTester\MultiTesterException
     * @expectedExceptionMessage The composer.json file must contains a 'name' entry.
     */
    public function testMissingProjectName()
    {
        chdir(__DIR__ . '/project');

        new Config(new MultiTester(), [__DIR__ . '/../bin/multi-tester', __DIR__ . '/bad-name/.multi-tester.yml']);
    }
}
