<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait Color
{
    /**
     * @var bool Colored output.
     */
    protected $colored = true;

    public function isColored(): bool
    {
        return $this->colored;
    }

    public function setColored(bool $colored): void
    {
        $this->colored = $colored;
    }

    private function withColor(string $text, string $color): string
    {
        return $this->isColored() ? "\033[{$color}m$text\033[0m" : $text;
    }
}
