<?php

declare(strict_types=1);

namespace MultiTester;

use ArrayObject;
use MultiTester\Traits\Color;
use MultiTester\Traits\ErrorHandler;
use MultiTester\Traits\GithubSettings;
use MultiTester\Traits\MultiTesterFile;
use MultiTester\Traits\ProcStreams;
use MultiTester\Traits\StorageDirectory;
use MultiTester\Traits\TravisSettings;
use MultiTester\Traits\Verbose;

class MultiTester
{
    use Color;
    use ErrorHandler;
    use GithubSettings;
    use MultiTesterFile;
    use ProcStreams;
    use StorageDirectory;
    use TravisSettings;
    use Verbose;

    /**
     * @var array|File Composer package settings cache.
     */
    protected $composerSettings = [];

    public function __construct(?string $storageDirectory = null)
    {
        $this->storageDirectory = $storageDirectory ?: sys_get_temp_dir();
    }

    /** @param array|string $command */
    public function exec($command, bool $quiet = false): bool
    {
        return is_array($command)
            ? $this->execCommands($command, $quiet)
            : $this->execCommand($command, $quiet);
    }

    public function getComposerSettings(string $package, $platforms = null): ?array
    {
        if (!isset($this->composerSettings[$package])) {
            $sourceFinder = new SourceFinder($this->getWorkingDirectory());
            $this->composerSettings[$package] = $sourceFinder->getFromFirstValidPlatform($package, $platforms);
        }

        return $this->composerSettings[$package];
    }

    public function output(string $text): void
    {
        $streams = $this->getProcStreams();
        $stdout = $streams[1] ?? null;

        if (is_array($stdout) && $stdout[0] === 'file') {
            $file = fopen($stdout[1], $stdout[2]);
            fwrite($file, $text);
            fclose($file);

            return;
        }

        echo $text;
    }

    public function info(string $text): void
    {
        if ($this->isVerbose()) {
            $this->output($text);
        }
    }

    public function framedInfo(string $text): void
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

    /**
     * @param string[] $arguments
     *
     * @throws MultiTesterException
     */
    public function run(array $arguments): bool
    {
        $config = $this->getConfig($arguments);
        $this->setVerbose($config->verbose);
        $this->setColored($config->colored);

        if (count($config->adds)) {
            return true;
        }

        return $this->runProjectTests($config);
    }

    protected function runProjectTests(Config $config): bool
    {
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

            $this->removeWorkingDirectory($config->executor);
        }

        $this->removeDirectories($directories);

        $subConfig = $config->config;
        $summary = new Summary($state, array_merge(
            ['color_support' => $config->colored],
            $subConfig instanceof ArrayObject ? $subConfig->getArrayCopy() : $subConfig
        ));

        $this->output("\n\n" . $summary->get());

        return $summary->isSuccessful();
    }

    /**
     * @param string[] $arguments
     *
     * @throws MultiTesterException
     */
    protected function getConfig(array $arguments): Config
    {
        try {
            return new Config($this, $arguments);
        } catch (MultiTesterException $exception) {
            $this->error($exception);
        }
    }

    /**
     * @param string[]|null $directories
     *
     * @throws MultiTesterException
     */
    protected function prepareWorkingDirectory(?array &$directories = null): void
    {
        $directory = $this->getStorageDirectory() . '/multi-tester-' . mt_rand(0, 9999999);
        $this->info("working directory: $directory\n");
        $this->setWorkingDirectory($directory);

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

    protected function extractVersion(&$package, &$settings): void
    {
        [$package, $version] = explode(':', "$package:");

        if ($version !== '' && is_array($settings) && !isset($settings['version'])) {
            $settings['version'] = $version;
        }
    }

    protected function removeDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            (new Directory($directory))->remove();
        }
    }

    /**
     * @param array|string|null $settings
     *
     * @throws MultiTesterException
     */
    protected function testProject(string $package, Config $config, $settings, bool &$state): void
    {
        try {
            (new Project($package, $config, $settings))->test();

            $this->output($this->withColor("  Success for: $package  ", '30;42') . "\n");
        } catch (TestFailedException $exception) {
            $state = false;

            $this->output($this->withColor("  Failure for: $package  ", '30;41') . "\n");

            if ($config->config['stop_on_failure'] ?? false) {
                $this->error($exception);
            }
        } catch (MultiTesterException $exception) {
            $this->output($this->withColor("  Error for: $package  ", '30;41') . "\n");
            $this->error($exception);
        }
    }

    protected function execCommand(string $command, bool $quiet = false): bool
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

    protected function execCommands(array $commands, bool $quiet = false): bool
    {
        foreach ($commands as $command) {
            if (!$this->execCommand($command, $quiet)) {
                return false;
            }
        }

        return true;
    }
}
