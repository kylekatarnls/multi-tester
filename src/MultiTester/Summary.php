<?php

namespace MultiTester;

use MultiTester\Exceptions\ZeroProjectsTestedException;

class Summary
{
    /**
     * @var array
     */
    protected $state;

    /**
     * @var array
     */
    protected $config;

    public function __construct($state, $config = [])
    {
        $this->state = $state;
        $this->config = $config;
    }

    /**
     * @throws ZeroProjectsTestedException
     *
     * @return string
     */
    public function get()
    {
        $count = count($this->state);
        if (0 === $count) {
            throw new ZeroProjectsTestedException('No projects tested.');
        }
        $pad = max(array_map('strlen', array_keys($this->state)));
        $successString = '%s    Success';
        $failureString = '%s    > Failure!';
        $successFinalString = '%d / %d     No project broken by current changes.';
        $failureFinalString = '%d / %d     %s broken by current changes.';
        $passed = 0;

        if ($this->isColored()) {
            $successString = "\033[42;97m $successString \033[0m";
            $failureString = "\033[41;97m %s    Failure \033[0m";
            $successFinalString = "\033[42;97m %d / %d     No project broken by current changes. \033[0m";
            $failureFinalString = "\033[41;97m %d / %d     %s broken by current changes. \033[0m";
        }

        $output = '';

        foreach ($this->state as $package => $success) {
            $passed += $success ? 1 : 0;
            $output .= sprintf($success ? $successString : $failureString, str_pad($package, $pad)) . "\n";
        }

        $output .= "\n" . sprintf(
            $passed === $count ? $successFinalString : $failureFinalString,
            $passed,
            $count,
            ($count - $passed) . ' project' . ($count - $passed > 1 ? 's' : '')
        ) . "\n";

        return $output;
    }

    /**
     * @return bool
     */
    public function isColored()
    {
        return isset($this->config['color_support'])
            ? $this->config['color_support']
            : Color::isSupported();
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return array_sum($this->state) === count($this->state);
    }
}
