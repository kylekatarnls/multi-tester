<?php

declare(strict_types=1);

namespace MultiTester;

class Config
{
    /**
     * @var MultiTester
     */
    public $tester;

    /**
     * @var string|null
     */
    public $runner;

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
     * @param MultiTester  $multiTester
     * @param list<string> $rawArguments
     *
     * @throws MultiTesterException
     */
    public function __construct(MultiTester $multiTester, array $rawArguments)
    {
        $this->runner = $rawArguments[0] ?? null;
        $parsedArguments = Arguments::parse(
            array_slice($rawArguments, 1),
            [
                '--verbose'       => '-v',
                '--quiet-install' => '-q',
            ],
            ['--add']
        );
        $this->adds = (array) ($parsedArguments->getOption('--add') ?? []);
        $this->tester = $multiTester;
        $this->verbose = $parsedArguments->hasFlag('--verbose');
        $this->quiet = $parsedArguments->hasFlag('--quiet-install');
        $arguments = $parsedArguments->getArguments(1);

        $this->setConfigFile($arguments[0] ?? $multiTester->getMultiTesterFile());
        $this->initProjects();
        $this->initProjectDirectory();
        $this->initData();
    }

    public function addProjects(): void
    {
        if (count($this->adds)) {
            $file = fopen($this->configFile, 'a');

            foreach ($this->adds as $project) {
                fwrite($file, "\n$project:\n  install: default\n  script: default\n");
            }

            fclose($file);
        }
    }

    public function getTester(): MultiTester
    {
        return $this->tester;
    }

    protected function initProjects(): void
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
    protected function initData(): void
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

    /**
     * @throws MultiTesterException
     */
    private function setConfigFile(string $configFile): void
    {
        $this->configFile = $configFile;
        $this->addProjects();

        if (!file_exists($this->configFile)) {
            throw new MultiTesterException("Multi-tester config file '$this->configFile' not found.");
        }
    }

    private function initProjectDirectory(): void
    {
        $base = dirname(realpath($this->configFile) ?: '.') ?: '.';
        $this->projectDirectory = isset($this->config['directory'])
            ? rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($this->config['directory'], '/\\')
            : $base;
        $this->composerFile = $this->projectDirectory . '/composer.json';
    }
}
