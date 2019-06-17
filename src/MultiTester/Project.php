<?php

namespace MultiTester;

use Composer\Semver\Semver;
use MultiTester\Exceptions\MultiTesterException;
use MultiTester\Exceptions\TestFailedException;

class Project
{
    /**
     * @var Config Configuration of the tester.
     */
    protected $config;

    /**
     * @var array|string Settings of the particular project.
     */
    protected $settings;

    /**
     * @var string
     */
    protected $package;

    public function __construct($package, Config $config, $settings)
    {
        $this->config = $config;
        $this->settings = $settings;
        $this->package = $package;
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        $settings = $this->settings;

        if ($settings === 'travis') {
            $settings = [
                'script'  => 'travis',
                'install' => 'travis',
            ];
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        return $settings;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return string
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * @throws MultiTesterException
     * @throws TestFailedException
     *
     * @return bool
     */
    public function test()
    {
        $this->download();
        $this->install();

        return $this->exec();
    }

    protected function getScript($script)
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
     * @param $settings
     * @param $package
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
    protected function filterVersion($version, $versions)
    {
        usort($versions, 'version_compare');

        $filteredVersions = $version
            ? Semver::satisfiedBy($versions, $version)
            : array_filter($versions, function ($version) {
                return substr($version, 0, 4) !== 'dev-';
            });

        $filteredVersions = count($filteredVersions) ? $filteredVersions : $versions;

        return end($filteredVersions);
    }

    /**
     * @param array $settings
     */
    protected function seedSourceSetting(&$settings)
    {
        if (!isset($settings['source'])) {
            if (!isset($settings['version'])) {
                $settings['version'] = 'dev-master';
            }

            $package = $this->getPackage();
            $tester = $this->getConfig()->getTester();
            $composerSettings = $tester->getComposerSettings($package);
            $version = $this->filterVersion($settings['version'], array_keys($composerSettings ?: []));

            $settings['source'] = isset($composerSettings[$version]['source'])
                ? $composerSettings[$version]['source']
                : null;
        }
    }

    /**
     * @param array $settings
     *
     * @throws MultiTesterException
     */
    protected function checkSourceSetting($settings)
    {
        if (!isset($settings['source'])) {
            $package = $this->getPackage();

            throw new MultiTesterException("Source not found for $package, you must provide it manually via a 'source' entry.");
        }

        if (!isset($settings['source']['type'], $settings['source']['url'])) {
            throw new MultiTesterException("Source malformed, it should contains at least 'type' and 'url' entries.");
        }

        if ($settings['source']['type'] !== 'git') {
            throw new MultiTesterException("Git source supported only for now, you should provide a manual 'clone' command instead.");
        }
    }

    protected function asArray(&$value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }
    }

    /**
     * @param array $settings
     *
     * @throws MultiTesterException
     */
    protected function seedCloneSetting(&$settings)
    {
        if (!isset($settings['clone'])) {
            $this->seedSourceSetting($settings);
            $this->checkSourceSetting($settings);
            $settings['clone'] = ['git clone ' . $settings['source']['url'] . ' .'];

            if (isset($settings['source']['reference'])) {
                $settings['clone'][] = 'git checkout ' . $settings['source']['reference'];
            }
        }

        $this->asArray($settings['clone']);
    }

    /**
     * @param array $settings
     */
    protected function seedSetting(&$settings, $key, $name, $defaultCommand)
    {
        $tester = $this->getConfig()->getTester();

        if (!isset($settings[$key]) || $settings[$key] === 'default') {
            if (!isset($settings[$key])) {
                $tester->output("No $name found, '$defaultCommand' used by default, add a '$key' entry if you want to customize it.\n");
            }

            $settings[$key] = $defaultCommand;
        }

        if ($settings[$key] === 'travis') {
            $travisSettings = $tester->getTravisSettings();
            if (isset($travisSettings[$key])) {
                $tester->output(ucfirst($name) . ' found in ' . $tester->getTravisFile() . ", add a '$key' entry if you want to customize it.\n");
                $settings[$key] = $travisSettings[$key];
            }
        }

        $this->asArray($settings[$key]);
    }

    /**
     * @param array $settings
     */
    protected function seedInstallSetting(&$settings)
    {
        $this->seedSetting($settings, 'install', 'install script', 'composer install --no-interaction');
    }

    /**
     * @param array $settings
     */
    protected function seedScriptSetting(&$settings)
    {
        $this->seedSetting($settings, 'script', 'script', 'vendor/bin/phpunit --no-coverage');
    }

    /**
     * @throws MultiTesterException
     */
    protected function download()
    {
        $settings = $this->getSettings();
        $package = $this->getPackage();
        $tester = $this->getConfig()->getTester();

        $this->seedCloneSetting($settings);

        (new Directory('.'))->clean();
        $tester->info("empty current directory\n");

        $tester->framedInfo("Cloning $package");

        if (!$tester->exec($settings['clone'])) {
            throw new MultiTesterException("Cloning $package failed.");
        }
    }

    /**
     * @throws MultiTesterException
     */
    protected function install()
    {
        $settings = $this->getSettings();
        $package = $this->getPackage();
        $config = $this->getConfig();
        $tester = $config->getTester();

        $tester->info("clear travis cache.\n");
        $tester->clearTravisSettingsCache();

        $this->seedInstallSetting($settings);

        $tester->framedInfo("Installing $package");

        if (!$tester->exec($settings['install'])) {
            throw new MultiTesterException("Installing $package failed.");
        }
    }

    /**
     * @throws TestFailedException
     *
     * @return bool
     */
    protected function exec()
    {
        $settings = $this->getSettings();
        $package = $this->getPackage();
        $config = $this->getConfig();
        $tester = $config->getTester();

        (new Directory($config->projectDirectory))->copy('vendor/' . $config->packageName, ['.git', 'vendor']);

        $this->seedScriptSetting($settings);

        $tester->framedInfo("Testing $package");

        return $this->tryExec($tester, $settings, $package);
    }
}
