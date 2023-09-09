<?php

declare(strict_types=1);

namespace MultiTester;

final class Arguments
{
    /** @var array<string, string> */
    private $options;

    /** @var array<string, true> */
    private $flags;

    /** @var list<string> */
    private $arguments;

    /**
     * @param array<string, string> $options
     * @param array<string, true>   $flags
     * @param list<string>          $arguments
     */
    private function __construct(array $options, array $flags, array $arguments)
    {
        $this->options = $options;
        $this->flags = $flags;
        $this->arguments = $arguments;
    }

    /**
     * @param string[] $rawArguments
     *
     * @throws MultiTesterException
     */
    public static function parse(array $rawArguments, array $allowedFlags = [], array $allowedOptions = []): self
    {
        $filteredArguments = [];
        $options = [];
        $flags = [];
        $optionKey = null;

        foreach ($rawArguments as $argument) {
            if (
                self::readFlag($flags, $allowedFlags, $argument) ||
                self::readOption($optionKey, $options, $allowedOptions, $argument)
            ) {
                continue;
            }

            $filteredArguments[] = $argument;
        }

        return new self($options, $flags, $filteredArguments);
    }

    /**
     * @throws MultiTesterException
     *
     * @return list<string>
     */
    public function getArguments(?int $maximum = null): array
    {
        if ($maximum !== null && count($this->arguments) > $maximum) {
            $plural = $maximum === 1 ? '' : 's';

            throw new MultiTesterException(
                "Expect at most $maximum argument$plural.\n" .
                'Found either unknown options or too much arguments among: ' .
                implode(', ', $this->arguments)
            );
        }

        return $this->arguments;
    }

    public function hasFlag(string $flag): bool
    {
        return isset($this->flags[$flag]);
    }

    /** @return string|string[]|null */
    public function getOption(string $option)
    {
        return $this->options[$option] ?? null;
    }

    private static function readFlag(
        array &$flags,
        array $allowedFlags,
        string $argument
    ): bool {
        $flag = self::getFlag($argument, $allowedFlags);

        if ($flag !== null) {
            $flags[$flag] = true;

            return true;
        }

        return false;
    }

    private static function readOption(
        ?string &$optionKey,
        array &$options,
        array $allowedOptions,
        string $argument
    ): bool {
        if ($optionKey) {
            self::setOption($options, $optionKey, $argument);
            $optionKey = null;

            return true;
        }

        $pieces = explode('=', $argument, 2);

        if (count($pieces) === 2) {
            $key = $pieces[0];
            self::assertOptionExist($key, $allowedOptions);
            self::setOption($options, $key, $pieces[1]);

            return true;
        }

        if (in_array($argument, $allowedOptions, true)) {
            $optionKey = $argument;

            return true;
        }

        return false;
    }

    private static function assertOptionExist(string $option, array $allowedOptions): void
    {
        if (!in_array($option, $allowedOptions, true)) {
            throw new MultiTesterException("Unknown option $option");
        }
    }

    private static function getFlag(string $argument, array $allowedFlags): ?string
    {
        foreach ($allowedFlags as $allowedFlag => $aliases) {
            if ($argument === $allowedFlag || in_array($argument, (array) $aliases, true)) {
                return $allowedFlag;
            }
        }

        return null;
    }

    private static function setOption(array &$options, string $name, string $value): void
    {
        if (isset($options[$name])) {
            $options[$name] = (array) $options[$name];
            $options[$name][] = $value;

            return;
        }

        $options[$name] = $value;
    }
}
