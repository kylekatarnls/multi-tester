<?php

namespace MultiTester;

class Config
{
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

        $config = new File($configFile);
        $projects = isset($config['projects']) ? $config['projects'] : $config;
        $config = isset($config['config']) ? $config['config'] : $config;

        $base = dirname(realpath($configFile));
        $projectDirectory = isset($config['directory'])
            ? rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($config['directory'], '/\\')
            : $base;
        $composerFile = $projectDirectory . '/composer.json';
        if (!file_exists($composerFile)) {
            throw new MultiTesterException("Set the 'directory' entry to a path containing a composer.json file.");
        }
        $data = new File($composerFile);
        if (!isset($data['name'])) {
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
