<?php

namespace MultiTester\Tests;

use MultiTester\Summary;
use PHPUnit\Framework\TestCase;

class SummaryTest extends TestCase
{
    /**
     * @throws \MultiTester\Exceptions\MultiTesterException
     */
    public function testColor()
    {
        $config = [
            'color_support' => true,
        ];
        $summary = new Summary([], $config);

        $this->assertTrue($summary->isColored());

        $config = [
            'color_support' => false,
        ];
        $summary = new Summary([], $config);

        $this->assertFalse($summary->isColored());

        $summary = new Summary([]);

        $this->assertTrue($summary->isColored() === false || $summary->isColored() === true);
    }

    /**
     * @throws \MultiTester\Exceptions\MultiTesterException
     */
    public function testIsSuccessful()
    {
        $summary = new Summary([]);

        $this->assertTrue($summary->isSuccessful());

        $summary = new Summary([
            'a' => true,
            'b' => false,
        ]);

        $this->assertFalse($summary->isSuccessful());

        $summary = new Summary([
            'a' => true,
            'b' => true,
        ]);

        $this->assertTrue($summary->isSuccessful());
    }

    /**
     * @throws \MultiTester\Exceptions\MultiTesterException
     */
    public function testGet()
    {
        $config = [
            'color_support' => false,
        ];
        $summary = new Summary([
            'a'   => true,
            'baz' => false,
        ], $config);

        $this->assertSame(implode("\n", [
            'a      Success',
            'baz    > Failure!',
            '',
            '1 / 2     1 project broken by current changes.',
        ]), trim($summary->get()));

        $summary = new Summary([
            'a'   => true,
            'baz' => true,
        ], $config);

        $this->assertSame(implode("\n", [
            'a      Success',
            'baz    Success',
            '',
            '2 / 2     No project broken by current changes.',
        ]), trim($summary->get()));

        $config = [
            'color_support' => true,
        ];
        $summary = new Summary([
            'a'   => true,
            'baz' => false,
        ], $config);

        $this->assertSame(implode("\n", [
            "\033[42;97m a      Success \033[0m",
            "\033[41;97m baz    Failure \033[0m",
            '',
            "\033[41;97m 1 / 2     1 project broken by current changes. \033[0m",
        ]), trim($summary->get()));

        $summary = new Summary([
            'a'   => true,
            'baz' => true,
        ], $config);

        $this->assertSame(implode("\n", [
            "\033[42;97m a      Success \033[0m",
            "\033[42;97m baz    Success \033[0m",
            '',
            "\033[42;97m 2 / 2     No project broken by current changes. \033[0m",
        ]), trim($summary->get()));
    }

    /**
     * @expectedException        \MultiTester\Exceptions\ZeroProjectsTestedException
     * @expectedExceptionMessage No projects tested.
     *
     * 
     * @throws \MultiTester\Exceptions\MultiTesterException
     */
    public function testGetWhenStateIsEmpty()
    {
        $config = [
            'color_support' => false,
        ];
        $summary = new Summary([], $config);

        $this->assertSame(implode("\n", [
            'a      Success',
            'baz    > Failure!',
            '',
            '1 / 2     1 project broken by current changes.',
        ]), trim($summary->get()));

    }
}
