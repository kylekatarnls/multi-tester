<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait TravisShortcut
{
    protected function replaceTravisSetting(array &$settings, string $key, string $name): void
    {
        if ($settings[$key] === 'travis') {
            $tester = $this->getConfig()->getTester();
            $tester->info("$key will use Travis if available.\n");
            $travisSettings = $tester->getTravisSettings();

            if (!isset($travisSettings[$key])) {
                $tester->info(ucfirst($name) . ' not found in ' . $tester->getTravisFile() . ".\n");

                return;
            }

            $tester->output(ucfirst($name) . ' found in ' . $tester->getTravisFile() . ", add a '$key' entry if you want to customize it.\n");
            $settings[$key] = $travisSettings[$key];
        }
    }
}
