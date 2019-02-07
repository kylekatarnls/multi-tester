<?php

namespace MultiTester;

class Config
{
    /**
     * @var string
     */
    public $configFile;

    /**
     * @var array
     */
    public $config;

    /**
     * @var array
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
     * @var array
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
     * Config constructor.
     *
     * @param MultiTester $multiTester
     * @param array       $arguments
     *
     * @throws MultiTesterException
     */
    public function __construct(MultiTester $multiTester, array $arguments)
    {
        $verbose = in_array('--verbose', $arguments) || in_array('-v', $arguments);
        $arguments = array_filter($arguments, function ($argument) {
            return $argument !== '--verbose' && $argument !== '-v';
        });
        $configFile = isset($arguments[1]) ? $arguments[1] : $multiTester->getMultiTesterFile();

        if (!file_exists($configFile)) {
            throw new MultiTesterException("Multi-tester config file '$configFile' not found.");
        }

        $config = $multiTester->parseYamlFile($configFile);
        $projects = isset($config['projects']) ? $config['projects'] : $config;
        $config = isset($config['config']) ? $config['config'] : $config;

        $projectDirectory = isset($config['directory']) ? $config['directory'] : dirname(realpath($configFile));
        $composerFile = $projectDirectory . '/composer.json';
        if (!file_exists($composerFile)) {
            throw new MultiTesterException("Set the 'directory' entry to a path containing a composer.json file.");
        }
        $data = $multiTester->parseJsonFile($composerFile);
        if (!is_array($data) || !isset($data['name'])) {
            throw new MultiTesterException("The composer.json file must contains a 'name' entry.");
        }
        $packageName = $data['name'];

        $this->configFile = $configFile;
        $this->config = $config;
        $this->projects = $projects;
        $this->projectDirectory = $projectDirectory;
        $this->composerFile = $composerFile;
        $this->data = $data;
        $this->packageName = $packageName;
        $this->verbose = $verbose;
    }
}
