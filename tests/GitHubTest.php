<?php

namespace MultiTester\Tests;

use MultiTester\GitHub;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GitHubTest extends TestCase
{
    public function testGetCurl()
    {
        $calls = [];
        $gitHub = new GitHub('vendor/library', function ($command) use (&$calls) {
            $calls[] = preg_replace('/-H "Authorization: token [^"]*"/', '', $command);
        });
        $getCurl = new ReflectionMethod($gitHub, 'getCurl');
        $getCurl->setAccessible(true);
        $getCurl->invoke($gitHub, '/foobar');

        $this->assertSame([
            'curl -s -H "Accept: application/vnd.github.antiope-preview+json" https://api.github.com/repos/vendor/library/commits/foobar',
        ], $calls);
    }

    public function testGetJSON()
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

    public function testIsSuccessful()
    {
        $gitHub = new GitHub('vendor/library', function (string $url) {
            $getUrl = function (string $sha): string {
                return 'curl -s -H "Accept: application/vnd.github.antiope-preview+json" ' .
                    "https://api.github.com/repos/vendor/library/commits/$sha/check-suites";
            };
            $data = [
                'check_suites' => [
                    [
                        'status' => 'queued',
                        'conclusion' => null,
                    ],
                    [
                        'status' => 'completed',
                        'conclusion' => 'success',
                    ],
                    [
                        'status' => 'completed',
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
}
