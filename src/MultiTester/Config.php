<?php

namespace MultiTester;

use MultiTester\Exceptions\MultiTesterException;
use MultiTester\Exceptions\ConfigFileNotFoundException;

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
     * @var string[]
     */
    public $adds = [];

    /**
     * Config constructor.
     *
     * @param MultiTester $multiTester
     * @param array $arguments
     *
     * @throws ConfigFileNotFoundException
     * @throws MultiTesterException
     */
    public function __construct(MultiTester $multiTester, array $arguments)
    {
        $arguments = $this->filterArguments($arguments, '--add');
        $this->tester = $multiTester;
        $this->verbose = in_array('--verbose', $arguments) || in_array('-v', $arguments);
        $arguments = array_filter($arguments, function ($argument) {
            return $argument !== '--verbose' && $argument !== '-v';
        });
        $this->configFile = isset($arguments[1]) ? $arguments[1] : $multiTester->getMultiTesterFile();
        $this->addProjects();

        if (!file_exists($this->configFile)) {
            throw new ConfigFileNotFoundException("Multi-tester config file '$this->configFile' not found.");
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

        $this->projects = isset($config['projects']) ? $config['projects'] : $config;
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
