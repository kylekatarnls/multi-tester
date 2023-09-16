<?php

namespace MultiTester\Tests;

use MultiTester\GitHub;
use MultiTester\MultiTesterException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GitHubTest extends TestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testGetCurl(): void
    {
        $calls = [];
        $gitHub = new GitHub('vendor/library', function ($command) use (&$calls) {
            $calls[] = preg_replace('/-H "Authorization: token [^"]*"/', '', $command);

            return '[]';
        });
        $getCurl = new ReflectionMethod($gitHub, 'getCurl');
        $getCurl->setAccessible(true);
        $getCurl->invoke($gitHub, '/foobar');

        $this->assertSame([
            'curl -s -H "Accept: application/vnd.github.antiope-preview+json" https://api.github.com/repos/vendor/library/commits/foobar',
        ], $calls);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetJSON(): void
    {
        $gitHub = new GitHub('vendor/library', function () {
            return '{"foo": "bar"}';
        });
        $getJSON = new ReflectionMethod($gitHub, 'getJSON');
        $getJSON->setAccessible(true);

        $this->assertSame([
            'foo' => 'bar',
        ], $getJSON->invoke($gitHub, '/foobar'));
    }

    /**
     * @throws \ReflectionException
     */
    public function testIsSuccessful(): void
    {
        $gitHub = new GitHub('vendor/library', function (string $url) {
            $getUrl = function (string $sha): string {
                return 'curl -s -H "Accept: application/vnd.github.antiope-preview+json" ' .
                    "https://api.github.com/repos/vendor/library/commits/$sha/check-suites";
            };
            $data = [
                'check_suites' => [
                    [
                        'status'     => 'queued',
                        'conclusion' => null,
                    ],
                    [
                        'status'     => 'completed',
                        'conclusion' => 'success',
                    ],
                    [
                        'status'     => 'completed',
                        'conclusion' => call_user_func(function () use ($url, $getUrl) {
                            switch ($url) {
                                case $getUrl('a12'):
                                    return 'success';
                                case $getUrl('b34'):
                                    return 'failure';
                            }

                            return $url;
                        }),
                    ],
                ],
            ];

            return json_encode($data);
        });
        $isSuccessful = new ReflectionMethod($gitHub, 'isSuccessful');
        $isSuccessful->setAccessible(true);

        $this->assertTrue($isSuccessful->invoke($gitHub, 'a12'));
        $this->assertFalse($isSuccessful->invoke($gitHub, 'b34'));
    }

    public function testGetFirstSuccessfulCommit(): void
    {
        $gitHub = new GitHub('vendor/library', function (string $url) {
            $getUrl = function (string $url): string {
                return 'curl -s -H "Accept: application/vnd.github.antiope-preview+json" ' .
                    "https://api.github.com/repos/vendor/library/commits$url";
            };

            switch ($url) {
                case $getUrl(''):
                    $data = [
                        ['sha' => 'b34'],
                        ['sha' => 'a12'],
                    ];
                    break;
                default:
                    $data = [
                        'check_suites' => [
                            [
                                'status'     => 'queued',
                                'conclusion' => null,
                            ],
                            [
                                'status'     => 'completed',
                                'conclusion' => 'success',
                            ],
                            [
                                'status'     => 'completed',
                                'conclusion' => call_user_func(function () use ($url, $getUrl) {
                                    switch ($url) {
                                        case $getUrl('/a12/check-suites'):
                                            return 'success';
                                        case $getUrl('/b34/check-suites'):
                                            return 'failure';
                                    }

                                    return $url;
                                }),
                            ],
                        ],
                    ];
                    break;
            }

            return json_encode($data);
        });

        $this->assertSame('a12', $gitHub->getFirstSuccessfulCommit());
    }

    /**
     * @throws MultiTesterException
     */
    public function testGetFirstSuccessfulCommitFailure(): void
    {
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage('No successful commit found in the 2 last commits of vendor/library.');

        $gitHub = new GitHub('vendor/library', function (string $url) {
            $getUrl = function (string $url): string {
                return 'curl -s -H "Accept: application/vnd.github.antiope-preview+json" ' .
                    "https://api.github.com/repos/vendor/library/commits$url";
            };

            switch ($url) {
                case $getUrl(''):
                    $data = [
                        ['sha' => 'b34'],
                        ['sha' => 'a12'],
                    ];
                    break;
                default:
                    $data = [
                        'check_suites' => [
                            [
                                'status'     => 'queued',
                                'conclusion' => null,
                            ],
                            [
                                'status'     => 'completed',
                                'conclusion' => 'success',
                            ],
                            [
                                'status'     => 'completed',
                                'conclusion' => call_user_func(function () use ($url, $getUrl) {
                                    switch ($url) {
                                        case $getUrl('/a12/check-suites'):
                                            return 'failure';
                                        case $getUrl('/b34/check-suites'):
                                            return 'failure';
                                    }

                                    return $url;
                                }),
                            ],
                        ],
                    ];
                    break;
            }

            return json_encode($data);
        });

        $gitHub->getFirstSuccessfulCommit();
    }

    /**
     * @throws MultiTesterException
     */
    public function testEmptyResponse(): void
    {
        $word = getenv('GITHUB_TOKEN') ? 'with' : 'without';
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage(
            "Fetching https://api.github.com/repos/vendor/library/commits $word GITHUB_TOKEN failed."
        );

        $gitHub = new GitHub('vendor/library', function () {
            return '';
        });
        $gitHub->getFirstSuccessfulCommit();
    }

    /**
     * @throws MultiTesterException
     */
    public function testGetFirstSuccessfulCommitFailureWithBranch(): void
    {
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage('No successful commit found in the 2 last commits on master of vendor/library.');

        $gitHub = new GitHub('vendor/library', function (string $url) {
            $getUrl = function (string $url): string {
                return 'curl -s -H "Accept: application/vnd.github.antiope-preview+json" ' .
                    "https://api.github.com/repos/vendor/library/commits$url";
            };

            switch ($url) {
                case $getUrl('?sha=master'):
                    $data = [
                        ['sha' => 'b34'],
                        ['sha' => 'a12'],
                    ];
                    break;
                default:
                    $data = [
                        'check_suites' => [
                            [
                                'status'     => 'queued',
                                'conclusion' => null,
                            ],
                            [
                                'status'     => 'completed',
                                'conclusion' => 'success',
                            ],
                            [
                                'status'     => 'completed',
                                'conclusion' => call_user_func(function () use ($url, $getUrl) {
                                    switch ($url) {
                                        case $getUrl('/a12/check-suites'):
                                            return 'failure';
                                        case $getUrl('/b34/check-suites'):
                                            return 'failure';
                                    }

                                    return $url;
                                }),
                            ],
                        ],
                    ];
                    break;
            }

            return json_encode($data);
        });

        $gitHub->getFirstSuccessfulCommit('master');
    }
}
