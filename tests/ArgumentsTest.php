<?php

namespace MultiTester\Tests;

use MultiTester\Arguments;
use MultiTester\MultiTesterException;

class ArgumentsTest extends TestCase
{
    public function testArgumentLimit(): void
    {
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage(
            "Expect at most 1 argument.\n" .
            'Found either unknown options or too much arguments among: a, b'
        );

        $arguments = Arguments::parse(['a', 'b']);
        $arguments->getArguments(1);
    }

    public function testUnknownOption(): void
    {
        $this->expectException(MultiTesterException::class);
        $this->expectExceptionMessage('Unknown option --ab');

        Arguments::parse(['--ab=cd']);
    }
}
