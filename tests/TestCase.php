<?php

namespace MultiTester\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    public static function assertFileDoesNotExist(string $filename, string $message = ''): void
    {
        if (method_exists(parent::class, 'assertFileDoesNotExist')) {
            parent::assertFileDoesNotExist($filename, $message);

            return;
        }

        parent::assertFileNotExists($filename, $message);
    }

    public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        if (method_exists(parent::class, 'assertMatchesRegularExpression')) {
            parent::assertMatchesRegularExpression($pattern, $string, $message);

            return;
        }

        parent::assertRegExp($pattern, $string, $message);
    }
}
