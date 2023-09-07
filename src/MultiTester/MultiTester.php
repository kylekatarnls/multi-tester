<?php

namespace MultiTester;

use MultiTester\Traits\MultiTesterFile;
use MultiTester\Traits\ProcStreams;
use MultiTester\Traits\StorageDirectory;
use MultiTester\Traits\TravisFile;
use MultiTester\Traits\Verbose;
use MultiTester\Traits\WorkingDirectory;

class MultiTester
{
    use WorkingDirectory;
    use MultiTesterFile;
    use TravisFile;
    use StorageDirectory;
    use ProcStreams;
    use Verbose;

    /**
     * @var array|File Composer package settings cache.
     */
    protected $composerSettings = [];

    /**
     * @var array|File|null Travis settings cache.
     */
    protected $travisSettings = null;

    public function __construct($storageDirectory = null)
    {
        $this->storageDirectory = $storageDirectory ?: sys_get_temp_dir();
    }

    public function exec($command, $quiet = false)
    {
        return is_array($command)
            ? $this->execCommands($command, $quiet)
            : $this->execCommand($command, $quiet);
    }

    public function getComposerSettings($package)
    {
        if (!isset($this->composerSettings[$package])) {
            $file = new File("https://repo.packagist.org/p/$package.json");
            $this->composerSettings[$package] = $file->isValid()
                ? (($file['packages'] ?? [])[$package] ?? null)
                : $this->getFromLibrariesIo($package);
        }

        return $this->composerSettings[$package];
    }

    public function output($text)
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

    public function info($text)
    {
        if ($this->isVerbose()) {
            $this->output($text);
        }
    }

    public function framedInfo($text)
    {
        $lines = explode("\n", trim($text));
        $widths = array_map('mb_strlen', $lines);
        $widths[] = 120;
        $width = max($widths);
        $bar = str_repeat('*', $width);

        $text = implode("\n", array_map(function ($line) use ($width) {
            return '*' . str_pad($line, $width - 2, ' ', STR_PAD_BOTH) . '*';
        }, $lines));

        $this->info("$bar\n$text\n$bar\n");
    }

    public function getTravisSettings()
    {
        if (!$this->travisSettings) {
            $travisFile = $this->getTravisFile();
            if (file_exists($travisFile)) {
                $this->travisSettings = new File($travisFile);
            }
            if (!$this->travisSettings) {
                $this->travisSettings = [];
            }
        }

        return $this->travisSettings;
    }

    public function clearTravisSettingsCache()
    {
        $this->travisSettings = null;
    }

    /**
     * @param array $arguments
     *
     * @throws MultiTesterException
     *
     * @return bool
     */
    public function run(array $arguments)
    {
        $config = $this->getConfig($arguments);
        $this->setVerbose($config->verbose);

        if (count($config->adds)) {
            return true;
        }

        $directories = [];
        $cwd = @getcwd() ?: '.';
        $state = [];

        foreach ($config->projects as $package => $settings) {
            $state[$package] = true;
            $pointer = &$state[$package];

            $this->extractVersion($package, $settings);
            $this->prepareWorkingDirectory($directories);
            $this->testProject($package, $config, $settings, $pointer);

            chdir($cwd);

            (new Directory($this->getWorkingDirectory(), $config->executor))->remove();
        }

        $this->removeDirectories($directories);

        $summary = new Summary($state, $config->config);

        $this->output("\n\n" . $summary->get());

        return $summary->isSuccessful();
    }

    /**
     * @param array $arguments
     *
     * @throws MultiTesterException
     *
     * @return Config
     */
    protected function getConfig(array $arguments)
    {
        $config = null;

        try {
            $config = new Config($this, $arguments);
        } catch (MultiTesterException $exception) {
            $this->error($exception);
        }

        return $config;
    }

    /**
     * @param array|null $directories
     *
     * @throws MultiTesterException
     */
    protected function prepareWorkingDirectory(&$directories = null)
    {
        $directory = $this->getStorageDirectory() . '/multi-tester-' . mt_rand(0, 9999999);
        $this->info("working directory: $directory\n");
        $this->setWorkingDirectory($directory);
        $directory = $this->getWorkingDirectory();

        if (is_array($directories)) {
            $directories[] = $directory;
        }

        if (!(new Directory($directory))->create()) {
            $this->error('Cannot create temporary directory, check you have write access to ' . $this->getStorageDirectory());
        }

        if (!chdir($directory)) {
            $this->error("Cannot enter $directory"); // @codeCoverageIgnore
        }
    }

    protected function extractVersion(&$package, &$settings)
    {
        [$package, $version] = explode(':', "$package:");

        if ($version !== '' && !isset($settings['version'])) {
            $settings['version'] = $version;
        }
    }

    protected function removeDirectories($directories)
    {
        foreach ($directories as $directory) {
            (new Directory($directory))->remove();
        }
    }

    /**
     * @param string $package
     * @param Config $config
     * @param array  $settings
     * @param bool   $state
     *
     * @throws MultiTesterException
     */
    protected function testProject($package, $config, $settings, &$state)
    {
        try {
            (new Project($package, $config, $settings))->test();
        } catch (TestFailedException $exception) {
            $state = false;

            if ($config->config['stop_on_failure'] ?? false) {
                $this->error($exception);
            }
        } catch (MultiTesterException $exception) {
            $this->error($exception);
        }
    }

    protected function execCommand($command, $quiet = false)
    {
        $command = trim(preg_replace('/^\s*travis_retry\s/', '', $command));

        if (!$quiet) {
            $this->output("> $command\n");
        }

        $pipes = [];
        $process = @proc_open($command, $this->getProcStreams(), $pipes, $this->getWorkingDirectory());

        if (!is_resource($process)) {
            return false; // @codeCoverageIgnore
        }

        $status = proc_get_status($process);

        while ($status['running']) {
            sleep(1);
            $status = proc_get_status($process);
        }

        if (!$quiet) {
            $this->output("\n");
        }

        return proc_close($process) === 0 || $status['exitcode'] === 0;
    }

    protected function execCommands($commands, $quiet = false)
    {
        foreach ($commands as $command) {
            if (!$this->execCommand($command, $quiet)) {
                return false;
            }
        }

        return true;
    }

    protected function error($message)
    {
        (new Directory($this->getWorkingDirectory()))->remove();

        throw $message instanceof MultiTesterException ?
            new MultiTesterException($message->getMessage(), 0, $message) :
            new MultiTesterException($message);
    }

    private function getFromLibrariesIo(string $package): ?File
    {
        $file = new File('https://libraries.io/api/Packagist/' . urlencode($package), 'json');

        return $file->isValid() ? $file : null;
    }
}
