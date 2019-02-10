<?php

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

    /**
     * @return array
     */
    public function getProcStreams()
    {
        return $this->procStreams;
    }

    /**
     * @param array $procStreams
     */
    public function setProcStreams($procStreams)
    {
        $this->procStreams = $procStreams;
    }
}
