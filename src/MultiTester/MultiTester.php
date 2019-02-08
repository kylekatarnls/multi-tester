<?php

namespace MultiTester;

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
     * @var array|File Composer package settings cache.
     */
    protected $composerSettings = [];

    /**
     * @var array|File|null Travis settings cache.
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
     * @var bool force directory change for commands execution.
     */
    protected $forceDirectoryChange = false;

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

    public function exec($command)
    {
        return is_array($command)
            ? $this->execCommands($command)
            : $this->execCommand($command);
    }

    public function getComposerSettings($package)
    {
        if (!isset($this->composerSettings[$package])) {
            $this->composerSettings[$package] = new File("https://repo.packagist.org/p/$package.json");
            $this->composerSettings[$package] = isset($this->composerSettings[$package]['packages'], $this->composerSettings[$package]['packages'][$package])
                ? $this->composerSettings[$package]['packages'][$package]
                : null;
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
     */
    public function run(array $arguments)
    {
        try {
            $config = new Config($this, $arguments);
        } catch (MultiTesterException $exception) {
            $this->error($exception);
        }

        $this->setVerbose($config->verbose);
        $directories = [];
        $pwd = shell_exec('pwd');
        $cwd = getcwd();
        $state = [];

        foreach ($config->projects as $package => $settings) {
            $state[$package] = true;
            $directory = sys_get_temp_dir() . '/multi-tester-' . mt_rand(0, 9999999);
            $this->info("working directory: $directory\n");
            $this->setWorkingDirectory($directory);
            $directory = $this->getWorkingDirectory();
            $directories[] = $directory;

            if (!(new Directory($directory))->create()) {
                $this->error('Cannot create temporary directory, check you have write access to ' . sys_get_temp_dir());
            }

            if (!chdir($directory)) {
                $this->error("Cannot enter $directory");
            }

            if (!$this->forceDirectoryChange && $pwd === shell_exec('pwd')) {
                $this->forceDirectoryChange = true;
                $this->info("Directory lock detected.\n");
                $this->exec('pwd');
            }

            try {
                (new Project($package, $config, $settings))->test();
            } catch (TestFailedException $exception) {
                $state[$package] = false;
                if (isset($config->config['stop_on_failure']) && $config->config['stop_on_failure']) {
                    $this->error($exception);
                }
            } catch (MultiTesterException $exception) {
                $this->error($exception);
            }

            chdir($cwd);

            (new Directory($this->getWorkingDirectory()))->remove();
        }

        foreach ($directories as $directory) {
            (new Directory($directory))->remove();
        }

        $this->output("\n\n");

        foreach ($state as $package => $success) {
            $this->output(str_pad($package, 100) . ($success ? 'Success' : 'Failure') . "\n");
        }
    }

    protected function execCommand($command)
    {
        $command = trim(preg_replace('/^\s*travis_retry\s/', '', $command));

        $this->output("> $command\n");

        $pipes = [];
        $process = proc_open(
            ($this->forceDirectoryChange ? 'cd ' . escapeshellarg($this->getWorkingDirectory()) . ' && ' : '') . $command,
            $this->getProcStreams(),
            $pipes,
            $this->getWorkingDirectory()
        );
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

    protected function execCommands($commands)
    {
        foreach ($commands as $command) {
            if (!$this->execCommand($command)) {
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
}
