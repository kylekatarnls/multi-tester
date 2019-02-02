<?php

namespace MultiTester;

use Symfony\Component\Yaml\Yaml;

class MultiTester
{
    /**
     * @var string Multi-tester default settings file.
     */
    protected $multiTesterFile = '.multi-tester.yml';

    /**
     * @var string Travis CI settings file.
     */
    protected $travisFile = '.travis.yml';

    /**
     * @var array Composer package settings cache.
     */
    protected $composerSettings = [];

    /**
     * @var array Travis settings cache.
     */
    protected $travisSettings = null;

    /**
     * @var array Temporary working directory
     */
    protected $workingDirectory = null;

    /**
     * @return string
     */
    public function getMultiTesterFile()
    {
        return $this->multiTesterFile;
    }

    /**
     * @param string $multiTesterFile
     */
    public function setMultiTesterFile($multiTesterFile)
    {
        $this->multiTesterFile = $multiTesterFile;
    }

    /**
     * @return string
     */
    public function getTravisFile()
    {
        return $this->travisFile;
    }

    /**
     * @param string $travisFile
     */
    public function setTravisFile($travisFile)
    {
        $this->travisFile = $travisFile;
    }

    protected function getTravisSettings()
    {
        if (!$this->travisSettings) {
            $travisFile = $this->getTravisFile();
            if (file_exists($travisFile)) {
                $this->travisSettings = Yaml::parseFile($travisFile);
            }
            if (!$this->travisSettings) {
                $this->travisSettings = [];
            }
        }

        return $this->travisSettings;
    }

    protected function clearTravisSettingsCache()
    {
        $this->travisSettings = null;
    }

    protected function getComposerSettings($package)
    {
        if (!isset($this->composerSettings[$package])) {
            $this->composerSettings[$package] = json_decode(file_get_contents("https://repo.packagist.org/p/$package.json"), JSON_OBJECT_AS_ARRAY);
            $this->composerSettings[$package] = is_array($this->composerSettings[$package]) && isset($this->composerSettings[$package]['packages'], $this->composerSettings[$package]['packages'][$package])
                ? $this->composerSettings[$package]['packages'][$package]
                : null;
        }

        return $this->composerSettings[$package];
    }

    protected function emptyDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir.'/'.$file;
                if (is_dir($path)) {
                    static::emptyDirectory($path);

                    continue;
                }

                unlink($path);
            }
        }
    }

    protected function removeDirectory($dir)
    {
        $this->emptyDirectory($dir);

        return @rmdir($dir);
    }

    protected function createEmptyDirectory($dir)
    {
        if (@is_dir($dir)) {
            $this->emptyDirectory($dir);

            return;
        }

        if (@is_file($dir)) {
            @unlink($dir);
        }

        return @mkdir($dir, 0777, true);
    }

    protected function exec($command)
    {
        if (is_array($command)) {
            foreach ($command as $item) {
                $this->exec($item);
            }

            return;
        }

        $command = trim(preg_replace('/^\s*travis_retry\s/', '', $command));

        echo "> $command\n";

        while (@ob_end_flush());

        $pipes = [];
        $process = proc_open($command, [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            return false;
        }

        while (!feof($pipes[1]))
        {
            echo fread($pipes[1], 4096);
            @flush();
        }
        fclose($pipes[1]);

        while (!feof($pipes[2]))
        {
            echo fread($pipes[2], 4096);
            @flush();
        }
        fclose($pipes[2]);

        echo "\n";

        return proc_close($process) === 0;
    }

    protected function error($message)
    {
        $this->removeDirectory($this->workingDirectory);
        echo "$message\n";
        exit(1);
    }

    public function run($arguments)
    {
        $configFile = isset($arguments[1]) ? $arguments[1] : $this->getMultiTesterFile();

        if (!file_exists($configFile)) {
            $this->error("Multi-tester config file '$configFile' not found.");
        }

        $config = Yaml::parseFile($configFile);

        $directory = sys_get_temp_dir() . '/multi-tester-' . mt_rand(0, 9999999);
        $this->workingDirectory = $directory;

        if (!$this->createEmptyDirectory($directory)) {
            $this->error('Cannot create temporary directory, check you have write access to ' . sys_get_temp_dir());
        }

        foreach ($config as $package => $settings) {
            if (!isset($settings['clone'])) {
                if (!isset($settings['source'])) {
                    if (!isset($settings['version'])) {
                        $settings['version'] = 'dev-master';
                    }
                    $composerSettings = $this->getComposerSettings($package);
                    $version = $settings['version'];
                    if (!isset($composerSettings[$version])) {
                        $versions = array_filter(array_keys($composerSettings), function ($version) {
                            return substr($version, 0, 4) !== 'dev-';
                        });
                        usort($versions, 'version_compare');
                        $version = count($versions) ? end($versions) : key($composerSettings);
                    }

                    $settings['source'] = $composerSettings[$version];
                }
                if (!isset($settings['source'])) {
                    $this->error("Source not found for $package, you must provide it manually via a 'source' entry.");
                }
                if (!isset($settings['source']['type'], $settings['source']['url'])) {
                    $this->error("Source malformed, it should contains at least 'type' and 'url' entries.");
                }
                if ($settings['source']['type'] !== 'git') {
                    $this->error("Git source supported only for now, you should provide a manual 'clone' command instead.");
                }
                $settings['clone'] = ['git clone ' . $settings['source']['url'] . ' .'];
                if (isset($settings['source']['reference'])) {
                    $settings['clone'][] = 'git checkout ' . $settings['source']['reference'];
                }
            }
            if (!is_array($settings['clone'])) {
                $settings['clone'] = [$settings['clone']];
            }

            if (!$this->exec($settings['clone'])) {
                $this->error("Cloning $package failed.");
            }

            $cwd = getcwd();
            chdir($directory);

            $this->clearTravisSettingsCache();

            if (!isset($settings['install'])) {
                $travisSettings = $this->getTravisSettings();
                if (isset($travisSettings['install'])) {
                    echo 'Install script found in ' . $this->getTravisFile() . ", add a 'install' entry if you want to customize it.\n";
                    $settings['install'] = $travisSettings['install'];
                }
            }

            if (!isset($settings['install'])) {
                echo "No install script found, 'composer install' used by default, add a 'install' entry if you want to customize it.\n";
                $settings['script'] = 'composer install';
            }

            if (!$this->exec($settings['install'])) {
                $this->error("Installing $package failed.");
            }

            if (!isset($settings['script'])) {
                $travisSettings = $this->getTravisSettings();
                if (isset($travisSettings['script'])) {
                    echo 'Script found in ' . $this->getTravisFile() . ", add a 'script' entry if you want to customize it.\n";
                    $settings['script'] = $travisSettings['script'];
                }
            }

            if (!isset($settings['script'])) {
                echo "No script found, 'vendor/bin/phpunit' used by default, add a 'script' entry if you want to customize it.\n";
                $settings['script'] = 'vendor/bin/phpunit';
            }

            if (!$this->exec($settings['script'])) {
                $this->error("Test of $package failed.");
            }

            chdir($cwd);
        }

        $this->removeDirectory($this->workingDirectory);
    }
}
