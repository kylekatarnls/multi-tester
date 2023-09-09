<?php

declare(strict_types=1);

namespace MultiTester\Traits;

use MultiTester\Config;

trait ConfigHandler
{
    /**
     * @var Config Configuration of the tester.
     */
    protected $config;

    public function getConfig(): Config
    {
        return $this->config;
    }
}
