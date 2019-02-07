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
     * @var string Temporary working directory.
     */
    protected $workingDirectory = null;

    /**
     * @var bool Verbose output.
     */
    protected $verbose = false;

    /**
     * @var array Stream settings for command execution.
     */
    protected $procStreams = [
        ['file', 'php://stdin', 'r'],
        ['file', 'php://stdout', 'w'],
        ['file', 'php://stderr', 'w'],
    ];

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

    /**
     * @return string
     */
    public function getWorkingDirectory()
    {
        return $this->workingDirectory;
    }

    /**
     * @param string $workingDirectory
     */
    public function setWorkingDirectory($workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;
    }

    /**
     * @return array
     */
    public function getProcStreams()
    {
        return $this->procStreams;
    }

    /**
     * @param array $procStreams
     */
    public function setProcStreams($procStreams)
    {
        $this->procStreams = $procStreams;
    }

    /**
     * @return bool
     */
    public function isVerbose()
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    public function parseYamlFile($file)
    {
        return Yaml::parse(file_get_contents($file));
    }

    public function parseJsonFile($file)
    {
        return @json_decode(file_get_contents($file), JSON_OBJECT_AS_ARRAY);
    }

    public function run(array $arguments)
    {
        try {
            $config = new Config($this, $arguments);
        } catch (MultiTesterException $exception) {
            $this->error($exception);
        }

        $this->setVerbose($config->verbose);
        $directories = [];

        foreach ($config->projects as $package => $settings) {
            $directory = sys_get_temp_dir() . '/multi-tester-' . mt_rand(0, 9999999);
            $this->info("working directory: $directory\n");
            $this->setWorkingDirectory($directory);
            $directories[] = $directory;

            if (!$this->createEmptyDirectory($directory)) {
                $this->error('Cannot create temporary directory, check you have write access to ' . sys_get_temp_dir());
            }

            if ($settings === 'travis') {
                $settings = [
                    'script'  => 'travis',
                    'install' => 'travis',
                ];
            }
            if (!is_array($settings)) {
                $settings = [];
            }
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

                    $settings['source'] = isset($composerSettings[$version]['source'])
                        ? $composerSettings[$version]['source']
                        : null;
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

            $cwd = getcwd();
            chdir($this->getWorkingDirectory());

            $this->emptyDirectory('.');
            $this->info("empty directory: $directory\n");
            if ($this->isVerbose()) {
                $this->exec('pwd');
                $this->exec('ls -la');
            }

            if (!$this->exec($settings['clone'])) {
                $this->error("Cloning $package failed.");
            }

            $this->info("clear travis cache.\n");
            $this->clearTravisSettingsCache();

            if (!isset($settings['install'])) {
                $this->output("No install script found, 'composer install --no-interaction' used by default, add a 'install' entry if you want to customize it.\n");
                $settings['install'] = 'composer install --no-interaction';
            }

            if ($settings['install'] === 'travis') {
                $travisSettings = $this->getTravisSettings();
                if (isset($travisSettings['install'])) {
                    $this->output('Install script found in ' . $this->getTravisFile() . ", add a 'install' entry if you want to customize it.\n");
                    $settings['install'] = $travisSettings['install'];
                }
            }

            if (!$this->exec($settings['install'])) {
                $this->error("Installing $package failed.");
            }

            $this->copyDirectory($config->projectDirectory, 'vendor/' . $config->packageName, ['.git', 'vendor']);

            if (!isset($settings['script'])) {
                $this->output("No script found, 'vendor/bin/phpunit --no-coverage' used by default, add a 'script' entry if you want to customize it.\n");
                $settings['script'] = 'vendor/bin/phpunit --no-coverage';
            }

            if ($settings['script'] === 'travis') {
                $travisSettings = $this->getTravisSettings();
                if (isset($travisSettings['script'])) {
                    $this->output('Script found in ' . $this->getTravisFile() . ", add a 'script' entry if you want to customize it.\n");
                    $settings['script'] = $travisSettings['script'];
                }
            }

            $script = explode(' ', $settings['script'], 2);
            if (file_exists($script[0])) {
                $script[0] = realpath($script[0]);
            }

            if (!$this->exec(implode(' ', $script))) {
                $this->error("Test of $package failed.");
            }

            chdir($cwd);

            $this->removeDirectory($this->getWorkingDirectory());
        }

        foreach ($directories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    protected function output($text)
    {
        $streams = $this->getProcStreams();
        $stdout = is_array($streams) && isset($streams[1]) ? $streams[1] : null;
        if (is_array($stdout) && $stdout[0] === 'file') {
            $file = fopen($stdout[1], $stdout[2]);
            fwrite($file, $text);
            fclose($file);

            return;
        }

        echo $text;
    }

    protected function info($text)
    {
        if ($this->isVerbose()) {
            $this->output($text);
        }
    }

    protected function getTravisSettings()
    {
        if (!$this->travisSettings) {
            $travisFile = $this->getTravisFile();
            if (file_exists($travisFile)) {
                $this->travisSettings = $this->parseYamlFile($travisFile);
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
            $this->composerSettings[$package] = $this->parseJsonFile("https://repo.packagist.org/p/$package.json");
            $this->composerSettings[$package] = is_array($this->composerSettings[$package]) && isset($this->composerSettings[$package]['packages'], $this->composerSettings[$package]['packages'][$package])
                ? $this->composerSettings[$package]['packages'][$package]
                : null;
        }

        return $this->composerSettings[$package];
    }

    protected function copyDirectory($source, $destination, $exceptions = [])
    {
        $this->createEmptyDirectory($destination);
        $success = true;

        foreach (@scandir($source) as $file) {
            if ($file !== '.' && $file !== '..' && !in_array($file, $exceptions)) {
                $path = "$source/$file";
                if (@is_dir($path)) {
                    if (!$this->copyDirectory($path, "$destination/$file")) {
                        $success = false;
                    }

                    continue;
                }

                if (!@copy($path, "$destination/$file")) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    protected function emptyDirectory($dir)
    {
        if (!@is_dir($dir)) {
            return false;
        }

        clearstatcache();
        $arg = escapeshellarg($dir);
        shell_exec('rm -rf ' . $arg . '/.* 2>&1 && rm -rf ' . $arg . '/* 2>&1');
        $success = true;

        foreach (@scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir . '/' . $file;
                if (@is_dir($path)) {
                    if (!$this->emptyDirectory($path) || !@rmdir($path)) {
                        $success = false;
                    }

                    continue;
                }

                if (!@unlink($path)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    protected function removeDirectory($dir)
    {
        $this->emptyDirectory($dir);

        return @rmdir($dir);
    }

    protected function createEmptyDirectory($dir)
    {
        if (@is_dir($dir)) {
            return $this->emptyDirectory($dir);
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
                if (!$this->exec($item)) {
                    return false;
                }
            }

            return true;
        }

        $command = trim(preg_replace('/^\s*travis_retry\s/', '', $command));

        $this->output("> $command\n");

        $pipes = [];
        $process = proc_open($command, $this->getProcStreams(), $pipes);
        if (!is_resource($process)) {
            return false; // @codeCoverageIgnore
        }

        $status = proc_get_status($process);
        while ($status['running']) {
            sleep(1);
            $status = proc_get_status($process);
        }

        $this->output("\n");

        return proc_close($process) === 0 || $status['exitcode'] === 0;
    }

    protected function error($message)
    {
        $this->removeDirectory($this->getWorkingDirectory());

        throw $message instanceof MultiTesterException ?
            new MultiTesterException($message->getMessage(), $message) :
            new MultiTesterException($message);
    }
}
