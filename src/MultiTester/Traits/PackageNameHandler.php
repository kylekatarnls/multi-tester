<?php

declare(strict_types=1);

namespace MultiTester\Traits;

trait PackageNameHandler
{
    /**
     * @var string
     */
    protected $packageName;

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /**
     * @codeCoverageIgnore
     *
     * @deprecated use getPackageName() instead
     */
    public function getPackage(): string
    {
        return $this->packageName;
    }
}
