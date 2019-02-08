<?php

namespace MultiTester;

class Project
{
    /**
     * @var Config Configuration of the tester.
     */
    protected $config;

    /**
     * @var array Settings of the particular project.
     */
    protected $settings;

    /**
     * @var string
     */
    protected $package;

    public function __construct($package, Config $config, array $settings)
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
        $settings = $this->getSettings();
        $package = $this->getPackage();
        $config = $this->getConfig();
        $tester = $config->getTester();

        if (!isset($settings['clone'])) {
            if (!isset($settings['source'])) {
                if (!isset($settings['version'])) {
                    $settings['version'] = 'dev-master';
                }
                $composerSettings = $tester->getComposerSettings($package);
                $version = $settings['version'];
                if (!isset($composerSettings[$version])) {
                    $versions = array_filter(array_keys($composerSettings), function ($version) {
                        return substr($version, 0, 4) !== 'dev-';
                    });
                    usort($versions, 'version_compare');
                    $version = count($versions) ? end($versions) : key($composerSettings);
                }

                $settings['source'] = isset($composerSettings[$version]['source'])
                    ? $composerSettings[$version]['source']
                    : null;
            }
            if (!isset($settings['source'])) {
                throw new MultiTesterException("Source not found for $package, you must provide it manually via a 'source' entry.");
            }
            if (!isset($settings['source']['type'], $settings['source']['url'])) {
                throw new MultiTesterException("Source malformed, it should contains at least 'type' and 'url' entries.");
            }
            if ($settings['source']['type'] !== 'git') {
                throw new MultiTesterException("Git source supported only for now, you should provide a manual 'clone' command instead.");
            }
            $settings['clone'] = ['git clone ' . $settings['source']['url'] . ' .'];
            if (isset($settings['source']['reference'])) {
                $settings['clone'][] = 'git checkout ' . $settings['source']['reference'];
            }
        }
        if (!is_array($settings['clone'])) {
            $settings['clone'] = [$settings['clone']];
        }

        (new Directory('.'))->clean();
        $tester->info("empty current directory\n");

        if (!$tester->exec($settings['clone'])) {
            throw new MultiTesterException("Cloning $package failed.");
        }

        $tester->info("clear travis cache.\n");
        $tester->clearTravisSettingsCache();

        if (!isset($settings['install'])) {
            $tester->output("No install script found, 'composer install --no-interaction' used by default, add a 'install' entry if you want to customize it.\n");
            $settings['install'] = 'composer install --no-interaction';
        }

        if ($settings['install'] === 'travis') {
            $travisSettings = $tester->getTravisSettings();
            if (isset($travisSettings['install'])) {
                $tester->output('Install script found in ' . $tester->getTravisFile() . ", add a 'install' entry if you want to customize it.\n");
                $settings['install'] = $travisSettings['install'];
            }
        }

        if (!$tester->exec($settings['install'])) {
            throw new MultiTesterException("Installing $package failed.");
        }

        (new Directory($config->projectDirectory))->copy('vendor/' . $config->packageName, ['.git', 'vendor']);

        if (!isset($settings['script'])) {
            $tester->output("No script found, 'vendor/bin/phpunit --no-coverage' used by default, add a 'script' entry if you want to customize it.\n");
            $settings['script'] = 'vendor/bin/phpunit --no-coverage';
        }

        if ($settings['script'] === 'travis') {
            $travisSettings = $tester->getTravisSettings();
            if (isset($travisSettings['script'])) {
                $tester->output('Script found in ' . $tester->getTravisFile() . ", add a 'script' entry if you want to customize it.\n");
                $settings['script'] = $travisSettings['script'];
            }
        }

        return $this->tryExec($tester, $settings, $package);
    }

    protected function getScript($settings)
    {
        $script = explode(' ', $settings['script'], 2);

        if (file_exists($script[0])) {
            $script[0] = realpath($script[0]);
        }

        return implode(' ', $script);
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
        $tries = 1;

        if (isset($settings['retry'])) {
            $tries = $settings['retry'];
        } elseif (($config = $this->getConfig()->config) && isset($config['retry'])) {
            $tries = $settings['retry'];
        }

        for ($i = $tries; $i > 0; $i--) {
            if ($tester->exec($this->getScript($settings))) {
                return true;
            }
        }

        throw new TestFailedException("Test of $package failed.");
    }
}
