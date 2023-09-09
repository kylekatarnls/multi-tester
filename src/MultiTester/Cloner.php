<?php

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
            if ($successOnly ?? false) {
                $reference = $this->getFirstSuccessfulCommit($url, $reference);
            }

            $commands[] = 'git checkout ' . $reference . ($this->config->quiet ? ' --quiet' : '');
        }

        return $commands;
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
