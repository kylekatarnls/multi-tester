<?php

declare(strict_types=1);

namespace MultiTester;

use MultiTester\Traits\ConfigHandler;

final class Cloner
{
    use ConfigHandler;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @throws MultiTesterException
     */
    public function getCloneCommands(array $settings): array
    {
        $url = $settings['source']['url'];
        $commands = ['git clone ' . $url . ' .' . ($this->config->quiet ? ' --quiet' : '')];
        $reference = $settings['source']['reference'] ?? null;
        $successOnly = $settings['source']['success_only'] ?? false;

        if ($reference || $successOnly) {
            $commands[] = 'git checkout --detach ' .
                $this->getReference($successOnly, $url, $reference) .
                ($this->config->quiet ? ' --quiet' : '');
        }

        return $commands;
    }

    /**
     * @throws MultiTesterException
     */
    private function getReference(bool $successOnly, ?string $url, ?string $reference): string
    {
        if ($successOnly) {
            $this->getConfig()->getTester()->info(
                "Search for last successful commit from '$url' with '$reference'.\n"
            );

            return $this->getFirstSuccessfulCommit($url, $reference);
        }

        $this->getConfig()->getTester()->info(
            "Using reference '$reference'.\n"
        );

        return $reference ?? '';
    }

    /**
     * @throws MultiTesterException
     */
    private function getFirstSuccessfulCommit(?string $url, ?string $reference): string
    {
        if (
            !$url ||
            !preg_match('/(?:https?:\/\/github\.com\/|git@github\.com:)([^\/]+\/[^\/]+)(?:\.git)?$/U', $url, $match)
        ) {
            throw new MultiTesterException("'success_only' can be used only with github.com source URLs for now.");
        }

        $gitHub = new GitHub($match[1], $this->config->executor);

        return $gitHub->getFirstSuccessfulCommit($reference);
    }
}
