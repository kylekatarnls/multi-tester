<?php

namespace MultiTester\Tests;

use MultiTester\MultiTesterException;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Run a callback depending on a remote API, and skip the test if the API is rate-limited or unreachable.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    protected function withRemoteApi(string $api, callable $callback)
    {
        try {
            return $callback();
        } catch (MultiTesterException $exception) {
            if (preg_match('/rate limit|Fetching .+ failed/i', $exception->getMessage())) {
                $this->markTestSkipped("$api is not available: " . $exception->getMessage());
            }

            throw $exception;
        }
    }

    protected function skipIfUnavailable(string $api, string $url): void
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        $headers = @get_headers($url, false, $context);
        $status = is_array($headers) && preg_match('/^HTTP\/\S+\s+(\d+)/', $headers[0] ?? '', $match)
            ? (int) $match[1]
            : 0;

        if ($status === 0 || $status === 429 || $status >= 500) {
            $this->markTestSkipped("$api is not available (HTTP status: $status).");
        }
    }

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
