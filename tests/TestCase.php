<?php

namespace MultiTester\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    public static function assertFileDoesNotExist(string $filename, string $message = ''): void
    {
        if (!method_exists(parent::class, 'assertFileDoesNotExist')) {
            parent::assertFileNotExists($filename, $message);

            return;
        }

        parent::assertFileDoesNotExist($filename, $message);
    }

    public static function assertDirectoryDoesNotExist(string $directory, string $message = ''): void
    {
        if (!method_exists(parent::class, 'assertDirectoryDoesNotExist')) {
            parent::assertDirectoryNotExists($directory, $message);

            return;
        }

        parent::assertDirectoryDoesNotExist($directory, $message);
    }

    public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        if (!method_exists(parent::class, 'assertMatchesRegularExpression')) {
            parent::assertRegExp($pattern, $string, $message);

            return;
        }

        parent::assertMatchesRegularExpression($pattern, $string, $message);
    }
}
