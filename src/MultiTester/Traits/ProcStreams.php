<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait ProcStreams
{
    /**
     * @var array Stream settings for command execution.
     */
    protected $procStreams = [
        ['file', 'php://stdin', 'r'],
        ['file', 'php://stdout', 'w'],
        ['file', 'php://stderr', 'w'],
    ];

    public function getProcStreams(): array
    {
        return $this->procStreams;
    }

    public function setProcStreams(array $procStreams): void
    {
        $this->procStreams = $procStreams;
    }
}
