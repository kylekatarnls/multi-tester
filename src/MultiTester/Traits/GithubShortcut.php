<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait GithubShortcut
{
    protected function replaceGitHubSetting(array &$settings, string $key, string $name): void
    {
        if ($settings[$key] === 'github') {
            $tester = $this->getConfig()->getTester();
            $tester->info("$key will use GitHub Actions if available.\n");
            $githubSettings = $tester->getGithubSettings();

            if (!isset($githubSettings[$key])) {
                $tester->info(ucfirst($name) . " not found in .github/workflows.\n");

                return;
            }

            $tester->output(ucfirst($name) . " found in .github/workflows, add a '$key' entry if you want to customize it.\n");
            $settings[$key] = $githubSettings[$key];
        }
    }
}
