<?php

namespace MultiTester;

class Config
{
    /**
     * @var MultiTester
     */
    public $tester;

    /**
     * @var string
     */
    public $configFile;

    /**
     * @var array|File
     */
    public $config;

    /**
     * @var array|File
     */
    public $projects;

    /**
     * @var string
     */
    public $projectDirectory;

    /**
     * @var string
     */
    public $composerFile;

    /**
     * @var array|File
     */
    public $data;

    /**
     * @var string
     */
    public $packageName;

    /**
     * @var bool
     */
    public $verbose;

    /**
     * @var bool
     */
    public $quiet;

    /**
     * @var string[]
     */
    public $adds = [];

    /**
     * @var string|callable
     */
    public $executor = 'shell_exec';

    /**
     * Config constructor.
     *
     * @param MultiTester $multiTester
     * @param array       $arguments
     *
     * @throws MultiTesterException
     */
    public function __construct(MultiTester $multiTester, array $arguments)
    {
        $arguments = $this->filterArguments($arguments, '--add');
        $this->tester = $multiTester;
        $this->verbose = in_array('--verbose', $arguments, true) || in_array('-v', $arguments, true);
        $this->quiet = in_array('--quiet-install', $arguments, true) || in_array('-q', $arguments, true);
        $arguments = array_slice(array_values(array_filter($arguments, function ($argument) {
            return !in_array($argument, ['--verbose', '-v', '--quiet-install', '-q'], true);
        })), 1);
        $this->configFile = $arguments[0] ?? $multiTester->getMultiTesterFile();
        $this->addProjects();

        if (!file_exists($this->configFile)) {
            throw new MultiTesterException("Multi-tester config file '$this->configFile' not found.");
        }

        $this->initProjects();

        $base = dirname(realpath($this->configFile));
        $this->projectDirectory = isset($this->config['directory'])
            ? rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($this->config['directory'], '/\\')
            : $base;
        $this->composerFile = $this->projectDirectory . '/composer.json';

        $this->initData();
    }

    public function addProjects()
    {
        if (count($this->adds)) {
            $file = fopen($this->configFile, 'a');

            foreach ($this->adds as $project) {
                fwrite($file, "\n$project:\n  install: default\n  script: default\n");
            }

            fclose($file);
        }
    }

    /**
     * @return MultiTester
     */
    public function getTester()
    {
        return $this->tester;
    }

    protected function filterArguments($arguments, $key)
    {
        $result = [];
        $add = false;
        $length = strlen($key) + 1;

        foreach ($arguments as $argument) {
            if ($add) {
                $add = false;
                $this->adds[] = $argument;

                continue;
            }

            if ($argument === $key) {
                $add = true;

                continue;
            }

            if (substr($argument, 0, $length) === "$key=") {
                $this->adds[] = substr($argument, $length);

                continue;
            }

            $result[] = $argument;
        }

        return $result;
    }

    protected function initProjects()
    {
        $config = new File($this->configFile);
        $this->config = $config;

        if (isset($config['config'])) {
            $this->config = $config['config'];
            unset($config['config']);
        }

        $this->projects = $config['projects'] ?? $config;
    }

    /**
     * @throws MultiTesterException
     */
    protected function initData()
    {
        if (!file_exists($this->composerFile)) {
            throw new MultiTesterException("Set the 'directory' entry to a path containing a composer.json file.");
        }

        $this->data = new File($this->composerFile);

        if (!isset($this->data['name'])) {
            throw new MultiTesterException("The composer.json file must contains a 'name' entry.");
        }

        $this->packageName = $this->data['name'];
    }
}
