<?php

declare(strict_types=1);

namespace MultiTester\Traits;

use MultiTester\File;

trait TravisSettings
{
    use TravisFile;

    /**
     * @var array|File|null Travis settings cache.
     */
    protected $travisSettings = null;

    /** @return array|File */
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

    public function clearTravisSettingsCache(): void
    {
        $this->travisSettings = null;
    }
}
