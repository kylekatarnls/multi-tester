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
}
