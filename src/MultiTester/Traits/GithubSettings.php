<?php

declare(strict_types=1);

namespace MultiTester\Traits;

use ArrayAccess;
use MultiTester\File;

trait GithubSettings
{
    /**
     * @var array|File|null GitHub's settings cache.
     */
    protected $githubSettings = null;

    /** @return array|File */
    public function getGithubSettings()
    {
        if (!$this->githubSettings) {
            $this->githubSettings = [];

            foreach (glob('.github/workflows/*.yml') as $path) {
                $this->scanFile($path);
            }
        }

        return $this->githubSettings;
    }

    public function clearGithubSettingsCache(): void
    {
        $this->githubSettings = null;
    }

    private function scanFile($path): void
    {
        $this->info("Scanning file: $path\n");
        $file = new File($path);

        foreach ($this->getList($file, 'jobs') as $job) {
            $this->scanJob($job);
        }
    }

    private function scanJob($job): void
    {
        foreach ($this->getList($job, 'steps') as $index => $step) {
            $name = $step['name'] ?? $step['uses'] ?? $step['run'] ?? "$index";
            $name = is_string($name) ? $name : json_encode($name, JSON_PRETTY_PRINT);
            $run = $step['run'] ?? null;
            $runDump = json_encode($run, JSON_PRETTY_PRINT);
            $this->info("Scanning step: $name $runDump\n");

            if (is_string($run)) {
                $this->scanStep($step, preg_replace('/\$\{\{.*?}}/', '', $run));
            }
        }
    }

    private function scanStep($step, $run): void
    {
        $envDump = json_encode($step['env'] ?? null, JSON_PRETTY_PRINT);
        $this->info("Env: $envDump\n");

        if (isset($step['env']['MULTI_TESTER_LABELS'])) {
            $this->info('MULTI_TESTER_LABELS = ' . json_encode($step['env']['MULTI_TESTER_LABELS']) . ".\n");
            $labels = array_filter(preg_split(
                '/[,\s]+/',
                strtolower(implode(',', (array) $step['env']['MULTI_TESTER_LABELS']))
            ));

            foreach ($labels as $label) {
                $this->info('Added step to ' . json_encode($label) . ":\n$run\n");
                $this->githubSettings[$label] = $this->githubSettings[$label] ?? [];
                $this->githubSettings[$label][] = $run;
            }
        }
    }

    private function getList($base, $key): array
    {
        if (
            is_string($key) &&
            (is_array($base) || $base instanceof ArrayAccess) &&
            isset($base[$key]) &&
            is_array($base[$key])
        ) {
            return $base[$key];
        }

        return [];
    }
}
