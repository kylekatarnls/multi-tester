<?php

namespace MultiTester;

use ArrayAccess;
use Composer\Semver\Semver;
use MultiTester\Traits\Composer;
use MultiTester\Traits\ConfigHandler;
use MultiTester\Traits\PackageNameHandler;
use MultiTester\Traits\Settings;

class Project
{
    use ConfigHandler;
    use Composer;
    use PackageNameHandler;
    use Settings;

    public function __construct(string $package, Config $config, $settings = null)
    {
        $this->config = $config;
        $this->settings = $settings;
        $this->packageName = $package;
    }

    /**
     * @throws MultiTesterException
     * @throws TestFailedException
     */
    public function test(): bool
    {
        $this->download();
        $this->install();

        return $this->exec();
    }

    public function removeReplacedPackages()
    {
        $replace = (array) ($this->config->data['replace'] ?? []);

        foreach ($replace as $package => $version) {
            (new Directory('vendor/' . $package, $this->config->executor))->remove();
        }
    }

    protected function getScript($script): array
    {
        $script = is_array($script) ? $script : [$script];

        foreach ($script as &$line) {
            $line = explode(' ', $line, 2);

            if (file_exists($line[0])) {
                $line[0] = realpath($line[0]);
            }

            $line = implode(' ', $line);
        }

        return $script;
    }

    protected function getTries($settings = [], $tries = 1)
    {
        if (isset($settings['retry'])) {
            $tries = $settings['retry'];
        } elseif (($config = $this->getConfig()->config) && isset($config['retry'])) {
            $tries = $config['retry'];
        }

        return $tries;
    }

    /**
     * @param MultiTester $tester
     * @param array       $settings
     * @param string      $package
     *
     * @throws TestFailedException
     *
     * @return bool
     */
    protected function tryExec(MultiTester $tester, $settings, $package)
    {
        for ($i = $this->getTries($settings); $i > 0; $i--) {
            if ($tester->exec($this->getScript($settings['script']))) {
                return true;
            }
        }

        throw new TestFailedException("Test of $package failed.");
    }

    /**
     * @param string   $version
     * @param string[] $versions
     *
     * @return string
     */
    protected function filterVersion($version, array $versions): ?string
    {
        usort($versions, 'version_compare');

        $filteredVersions = $version
            ? Semver::satisfiedBy($versions, $version)
            : array_filter($versions, function ($version) {
                return substr($version, 0, 4) !== 'dev-';
            });

        $filteredVersions = count($filteredVersions) ? $filteredVersions : $versions;

        return end($filteredVersions) ?: null;
    }

    protected function seedSourceSetting(array &$settings): void
    {
        if (!isset($settings['source'])) {
            $settings['version'] = (string) ($settings['version'] ?? 'dev-master');
            $package = $this->getPackageName();
            $tester = $this->getConfig()->getTester();
            $composerSettings = $tester->getComposerSettings($package, $settings['platforms'] ?? null);
            $version = is_array($composerSettings)
                ? $this->filterVersion($settings['version'], array_keys((array) ($composerSettings ?: [])))
                : '';

            $settings['source'] = $composerSettings[$version]['source']
                ?? $this->getRepositoryUrl($composerSettings);
        }
    }

    /** @param ArrayAccess|array|null $composerSettings */
    protected function getRepositoryUrl($composerSettings): ?array
    {
        return isset($composerSettings['repository_url'])
            ? [
                'type' => 'git',
                'url'  => $composerSettings['repository_url'],
            ]
            : null;
    }

    /**
     * @throws MultiTesterException
     */
    protected function checkSourceSetting(array $settings): void
    {
        if (!isset($settings['source'])) {
            $package = $this->getPackageName();

            throw new MultiTesterException("Source not found for $package, you must provide it manually via a 'source' entry.");
        }

        if (!isset($settings['source']['type'], $settings['source']['url'])) {
            throw new MultiTesterException("Source malformed, it should contains at least 'type' and 'url' entries.");
        }

        if ($settings['source']['type'] !== 'git') {
            throw new MultiTesterException("Git is the only supported source for now, you should provide a manual 'clone' command instead.");
        }
    }

    /**
     * @throws MultiTesterException
     */
    protected function seedCloneSetting(array &$settings): void
    {
        if (!isset($settings['clone'])) {
            $this->seedSourceSetting($settings);
            $this->checkSourceSetting($settings);
            $cloner = new Cloner($this->config);
            $settings['clone'] = $cloner->getCloneCommands($settings);
        }

        $this->asArray($settings['clone']);
    }

    protected function seedSetting(array &$settings, string $key, string $name, string $defaultCommand): void
    {
        if (!isset($settings[$key]) || $settings[$key] === 'default') {
            if (!isset($settings[$key])) {
                $this->getConfig()->getTester()->output(
                    "No $name found, '$defaultCommand' used by default, add a '$key' entry if you want to customize it.\n"
                );
            }

            $settings[$key] = $defaultCommand;
        }

        $this->replaceTravisSetting($settings, $key, $name);
        $this->asArray($settings[$key]);
    }

    protected function replaceTravisSetting(array &$settings, string $key, string $name): void
    {
        if ($settings[$key] === 'travis') {
            $tester = $this->getConfig()->getTester();
            $travisSettings = $tester->getTravisSettings();

            if (isset($travisSettings[$key])) {
                $tester->output(ucfirst($name) . ' found in ' . $tester->getTravisFile() . ", add a '$key' entry if you want to customize it.\n");
                $settings[$key] = $travisSettings[$key];
            }
        }
    }

    protected function seedAutoloadSetting(array &$settings): void
    {
        $this->seedSetting(
            $settings,
            'autoload',
            'autoload build script',
            $this->getComposerProgram($settings) .
                ' dump-autoload --optimize --no-interaction' .
                ($this->config->quiet ? ' --quiet' : '')
        );
    }

    protected function seedInstallSetting(array &$settings): void
    {
        $this->seedSetting(
            $settings,
            'install',
            'install script',
            $this->getComposerProgram($settings) .
                ' install --no-interaction' .
                ($this->config->quiet ? ' --quiet' : '')
        );
    }

    protected function seedScriptSetting(array &$settings): void
    {
        $this->seedSetting(
            $settings,
            'script',
            'script',
            'vendor/bin/phpunit --no-coverage'
        );
    }

    /**
     * @throws MultiTesterException
     */
    protected function download()
    {
        $settings = $this->getSettings();
        $package = $this->getPackageName();
        $config = $this->getConfig();
        $tester = $config->getTester();

        $this->seedCloneSetting($settings);

        (new Directory('.', $this->config->executor))->clean();

        if (!$config->quiet) {
            $tester->info("empty current directory\n");
            $tester->framedInfo("Cloning $package");
        }

        if (!$tester->exec($settings['clone'], $config->quiet)) {
            throw new MultiTesterException("Cloning $package failed.");
        }
    }

    /**
     * @throws MultiTesterException
     */
    protected function install()
    {
        $settings = $this->getSettings();
        $package = $this->getPackageName();
        $config = $this->getConfig();
        $tester = $config->getTester();

        if (!$config->quiet) {
            $tester->info("clear travis cache.\n");
        }

        $tester->clearTravisSettingsCache();

        $this->seedInstallSetting($settings);

        if (!$config->quiet) {
            $tester->framedInfo("Installing $package");
        }

        if (!$tester->exec($settings['install'], $config->quiet)) {
            throw new MultiTesterException("Installing $package failed.");
        }
    }

    /**
     * @throws MultiTesterException
     */
    protected function autoload()
    {
        $settings = $this->getSettings();
        $package = $this->getPackageName();
        $config = $this->getConfig();
        $tester = $config->getTester();

        $this->seedAutoloadSetting($settings);

        if (!$tester->exec($settings['autoload'], $config->quiet)) {
            throw new MultiTesterException("Building autoloader of $package failed.");
        }
    }

    /**
     * @throws MultiTesterException|TestFailedException
     */
    protected function exec(): bool
    {
        $settings = $this->getSettings();
        $package = $this->getPackageName();
        $config = $this->getConfig();
        $tester = $config->getTester();

        $this->removeReplacedPackages();

        (new Directory($config->projectDirectory, $this->config->executor))->copy('vendor/' . $config->packageName, ['.git', 'vendor']);

        $this->autoload();

        $this->seedScriptSetting($settings);

        $tester->framedInfo("Testing $package");

        return $this->tryExec($tester, $settings, $package);
    }
}
